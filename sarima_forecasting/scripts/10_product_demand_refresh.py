"""Refresh product-level 30-day demand forecasts from the LIVE POS data.

Input : sarima_forecasting/outputs/deployment/latest_product_daily_demand.csv
        (exported by ForecastRefreshService::exportProductDailyDemand —
        (date, product_name, quantity_sold) rows from completed transactions)
Output: sarima_forecasting/outputs/deployment/product_demand_forecast_30_day.json
        (+ matching CSV the way 08_product_demand_forecast.py did)

This keeps the artifact consumed by Dashboard::getProductDemandForecast()
current with the imported historical data + live POS sales instead of the
2025 training file that script 08 used.

Rules (same philosophy as the company-wide pipeline):
  * A product needs >= MIN_OBSERVED_SALE_DAYS observed sale days before it is
    forecast with SARIMA. Below that, the product is reported with
    forecast_method "not_enough_history".
  * Days are aggregated per (product, date); missing calendar days between
    recorded dates are treated as zero product sales only for the product's
    own series (no pharmacy-wide day is invented).
  * SARIMA(1,0,2)(1,0,1,7) per product, weekly seasonality, horizon 365 days
    so the remaining months of the current year stay readable; the 30-day
    slice drives the dashboard panels.
  * If a fit fails, that product falls back to "share_allocation": its
    share of the last-30-day pharmacy-wide units estimate times the
    pharmacy-wide 30-day total from the same input. Clearly labeled.

Throttle: when the input (last date + row count) matches the previous
artifact, the script exits without refitting so the 5-minute cron stays
cheap.
"""
from __future__ import annotations

import json
import sys
import warnings
from datetime import datetime
from pathlib import Path

import numpy as np
import pandas as pd
from statsmodels.tsa.statespace.sarimax import SARIMAX

warnings.filterwarnings("ignore")

ROOT = Path(__file__).resolve().parents[2]
SARIMA_ROOT = ROOT / "sarima_forecasting"
DEPLOY = SARIMA_ROOT / "outputs" / "deployment"
INPUT = DEPLOY / "latest_product_daily_demand.csv"
OUTPUT_JSON = DEPLOY / "product_demand_forecast_30_day.json"
OUTPUT_CSV = DEPLOY / "product_demand_forecast_30_day.csv"

MODEL_ORDER = (1, 0, 2)
SEASONAL_ORDER = (1, 0, 1, 7)
HORIZON = 30
YEAR_HORIZON = 365
MIN_OBSERVED_SALE_DAYS = 60
TOP_PRODUCTS = 10


def fit_product_forecast(series: pd.Series):
    """Fit one product and return (mean30, lower30, upper30, mean_year).

    Raises on failure — the caller downgrades that product to the labeled
    share-allocation fallback. series must be daily-continuous.
    """
    SARIMAX_model = SARIMAX(
        series.astype(float),
        order=MODEL_ORDER,
        seasonal_order=SEASONAL_ORDER,
        enforce_stationarity=False,
        enforce_invertibility=False,
    ).fit(disp=False, maxiter=300)

    fc = SARIMAX_model.get_forecast(YEAR_HORIZON)
    mean_all = np.maximum(0.0, np.asarray(fc.predicted_mean, dtype=float))
    ci = fc.conf_int()
    lower_all = np.maximum(0.0, np.asarray(ci.iloc[:, 0], dtype=float))
    upper_all = np.maximum(0.0, np.asarray(ci.iloc[:, 1], dtype=float))

    return mean_all[:HORIZON], lower_all[:HORIZON], upper_all[:HORIZON], mean_all


def main() -> int:
    DEPLOY.mkdir(parents=True, exist_ok=True)
    if not INPUT.exists() or INPUT.stat().st_size == 0:
        print("No product daily demand input; artifact left untouched.")
        return 0

    df = pd.read_csv(INPUT, parse_dates=["date"])
    required = {"date", "product_name", "quantity_sold"}
    missing = required - set(df.columns)
    if missing:
        raise RuntimeError(f"Per-product input missing columns: {sorted(missing)}")

    df = df.dropna(subset=["date", "product_name"]).copy()
    df["product_name"] = df["product_name"].astype(str).str.strip()
    df["quantity_sold"] = pd.to_numeric(df["quantity_sold"], errors="coerce").fillna(0.0)

    source_rows = int(len(df))
    source_last = df["date"].max()
    source_units = round(float(df["quantity_sold"].sum()), 2)

    # Throttle: skip refitting when the deployed artifact already covers
    # exactly this input state (last date, row count and total units —
    # the units sum catches in-day quantity updates on existing rows).
    previous_meta = {}
    if OUTPUT_JSON.exists() and OUTPUT_JSON.stat().st_size > 0:
        try:
            payload_old = json.loads(OUTPUT_JSON.read_text(encoding="utf-8"))
            previous_meta = payload_old.get("metadata", {})
        except (OSError, json.JSONDecodeError):
            previous_meta = {}
    if (
        previous_meta.get("source_last_date") == source_last.strftime("%Y-%m-%d")
        and previous_meta.get("source_rows") == source_rows
        and previous_meta.get("source_units") == source_units
    ):
        print(
            "Product demand artifact is current "
            f"(input through {source_last:%Y-%m-%d}, {source_rows} rows); refit skipped."
        )
        return 0

    daily = (
        df.pivot_table(index="date", columns="product_name", values="quantity_sold",
                       aggfunc="sum")
        .fillna(0.0)
        .sort_index()
    )

    # Pharmacy-wide daily series, for the share-allocation fallback only.
    total_daily = daily.sum(axis=1)

    actual_window_start = source_last - pd.Timedelta(days=HORIZON - 1)
    observed_days_all = df.groupby("product_name")["date"].nunique().to_dict()

    records = []
    for product in daily.columns:
        name = str(product)
        series = daily[name]

        actual_window_mask = (series.index >= actual_window_start) & (series.index <= source_last)
        actual_last_30 = float(series[actual_window_mask].sum())
        observed_days = int(observed_days_all.get(name, (series > 0).sum()))

        record = {
            "product_name": name,
            "actual_last_30_units": round(actual_last_30, 2),
            "observed_sale_days": observed_days,
            "forecast_method": "not_enough_history",
            "delta_pct": None,
            "average_daily_forecast": 0.0,
            "demand_level": "Low",
        }

        if observed_days >= MIN_OBSERVED_SALE_DAYS:
            expected_index = pd.date_range(series.index.min(), series.index.max(), freq="D")
            continuous = series.reindex(expected_index, fill_value=0.0)
            try:
                mean30, lower30, upper30, mean_all = fit_product_forecast(continuous)

                forecast_start = expected_index.max() + pd.Timedelta(days=1)
                record.update({
                    "historical_units_sold": int(round(float(series.sum()))),
                    "forecast_30_day_units": round(float(mean30.sum()), 2),
                    "lower_30_day_units": round(float(lower30.sum()), 2),
                    "upper_30_day_units": round(float(upper30.sum()), 2),
                    "average_daily_forecast": round(float(mean30.mean()), 2),
                    "forecast_method": "per_product_sarima",
                    "forecast_window_start": forecast_start.strftime("%Y-%m-%d"),
                    "forecast_window_end": (forecast_start + pd.Timedelta(days=HORIZON - 1)).strftime("%Y-%m-%d"),
                    "forecast": [
                        {
                            "date": (forecast_start + pd.Timedelta(days=i)).strftime("%Y-%m-%d"),
                            "forecast_quantity": round(float(mean30[i]), 2),
                            "lower_bound": round(float(lower30[i]), 2),
                            "upper_bound": round(float(upper30[i]), 2),
                        }
                        for i in range(HORIZON)
                    ],
                })
            except Exception as exc:  # noqa: BLE001
                print(f"SARIMA fit failed for {name}: {exc}")
                record["forecast_method"] = "share_allocation"

        records.append(record)

    # Share-allocation fallback: the pharmacy-wide deployed model is not
    # reloaded inside this script, so the allocation uses the pharmacy-wide
    # trailing-30 level times each product's own share — clearly labeled.
    wide_mask = (total_daily.index >= actual_window_start) & (total_daily.index <= source_last)
    wide_last_30 = float(total_daily[wide_mask].sum())
    wide_daily_mean = wide_last_30 / HORIZON

    for record in records:
        if record["forecast_method"] != "share_allocation":
            continue
        share = (
            record["actual_last_30_units"] / wide_last_30
            if wide_last_30 > 0
            else 0.0
        )
        estimated = round(share * wide_last_30, 2)
        record.update({
            "historical_units_sold": int(round(float(daily[record["product_name"]].sum()))),
            "forecast_30_day_units": estimated,
            "lower_30_day_units": estimated,
            "upper_30_day_units": estimated,
            "average_daily_forecast": round(share * wide_daily_mean, 2),
        })

    # Fill shared helper fields (delta + levels) and the ranking.
    for record in records:
        forecast_total = record.get("forecast_30_day_units")
        if forecast_total is not None and record["actual_last_30_units"] > 0:
            record["delta_pct"] = round(
                (forecast_total - record["actual_last_30_units"])
                / record["actual_last_30_units"] * 100.0,
                1,
            )
        record["average_daily_forecast"] = round(float(record.get("average_daily_forecast", 0.0)), 2)
        if record["average_daily_forecast"] >= 10.0:
            record["demand_level"] = "High"
        elif record["average_daily_forecast"] >= 5.0:
            record["demand_level"] = "Moderate"
        else:
            record["demand_level"] = "Low"

    records.sort(
        key=lambda r: ((r.get("forecast_30_day_units") or 0), r["actual_last_30_units"]),
        reverse=True,
    )
    records = records[:TOP_PRODUCTS]
    for i, record in enumerate(records, 1):
        record["rank"] = i

    payload = {
        "metadata": {
            "model": "SARIMA",
            "model_order": list(MODEL_ORDER),
            "seasonal_order": list(SEASONAL_ORDER),
            "seasonality": "Weekly",
            "forecast_horizon_days": HORIZON,
            "training_start": daily.index.min().strftime("%Y-%m-%d"),
            "training_end": source_last.strftime("%Y-%m-%d"),
            "actual_window_start": actual_window_start.strftime("%Y-%m-%d"),
            "source_last_date": source_last.strftime("%Y-%m-%d"),
            "source_rows": source_rows,
            "source_units": source_units,
            "baseline": False,
            "minimum_observed_sale_days": MIN_OBSERVED_SALE_DAYS,
            "product_count": len(records),
            "generated_at": datetime.now().astimezone().isoformat(timespec="seconds"),
            "method_note": (
                "Per-product SARIMA refit on the completed POS per-product daily demand "
                "(imported history + live sales). Days without a recorded product sale "
                "count as zero observed sales for that product only. Products whose "
                "series are too short for SARIMA, or whose SARIMA fit fails, fall back "
                "to a labeled share-allocation estimate instead of a SARIMA forecast."
            ),
        },
        "products": records,
    }

    OUTPUT_JSON.write_text(json.dumps(payload, indent=2), encoding="utf-8")

    rows = [
        {k: record.get(k) for k in [
            "rank",
            "product_name",
            "actual_last_30_units",
            "forecast_30_day_units",
            "average_daily_forecast",
            "demand_level",
            "forecast_method",
        ]}
        for record in records
    ]
    pd.DataFrame(rows).to_csv(OUTPUT_CSV, index=False)

    print("PRODUCT DEMAND FORECAST REFRESHED")
    print(f"Input rows        : {source_rows} (through {source_last:%Y-%m-%d})")
    print(f"Products ranked   : {len(records)}")
    print(f"JSON: {OUTPUT_JSON}")
    for record in records[:10]:
        method = record["forecast_method"]
        total = record.get("forecast_30_day_units")
        shown = "-" if total is None else f"{total:.1f} units"
        print(f"  #{record['rank']:>2} {record['product_name']}: last30 {record['actual_last_30_units']:.0f} -> {shown} [{method}]")
    return 0


if __name__ == "__main__":
    sys.exit(main())
