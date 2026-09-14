"""Generate product-level 30-day demand forecasts.

Uses the validated SARIMA configuration for products with sufficient observed
sales history. For the historical baseline, a day with pharmacy-wide sales but
no sale line for a product is treated as zero observed sales for that product.
This is a sales-observation measure, not an estimate of unmet demand.
"""
from __future__ import annotations

import json
import warnings
from pathlib import Path

import numpy as np
import pandas as pd
from statsmodels.tsa.statespace.sarimax import SARIMAX

warnings.filterwarnings("ignore")

ROOT = Path(__file__).resolve().parents[2]
INPUT = ROOT / "sarima_forecasting" / "nica_xandra_sales_2025.csv"
OUTPUT_DIR = ROOT / "sarima_forecasting" / "outputs" / "deployment"
OUTPUT_JSON = OUTPUT_DIR / "product_demand_forecast_30_day.json"
OUTPUT_CSV = OUTPUT_DIR / "product_demand_forecast_30_day.csv"

MODEL_ORDER = (1, 0, 2)
SEASONAL_ORDER = (1, 0, 1, 7)
HORIZON = 30
MONTHLY_HORIZON = 365
MIN_OBSERVED_SALE_DAYS = 60
TOP_PRODUCTS = 10


def forecast_product(name: str, series: pd.Series) -> tuple[np.ndarray, np.ndarray, np.ndarray] | None:
    if int((series > 0).sum()) < MIN_OBSERVED_SALE_DAYS:
        return None
    try:
        model = SARIMAX(
            series.astype(float),
            order=MODEL_ORDER,
            seasonal_order=SEASONAL_ORDER,
            enforce_stationarity=False,
            enforce_invertibility=False,
        ).fit(disp=False, maxiter=200)
        fc = model.get_forecast(MONTHLY_HORIZON)
        mean_all = np.maximum(0, np.asarray(fc.predicted_mean, dtype=float))
        ci = fc.conf_int()
        lower_all = np.maximum(0, np.asarray(ci.iloc[:, 0], dtype=float))
        upper_all = np.maximum(0, np.asarray(ci.iloc[:, 1], dtype=float))

        return (
            mean_all[:HORIZON],
            lower_all[:HORIZON],
            upper_all[:HORIZON],
            mean_all,
            lower_all,
            upper_all,
        )
    except Exception:
        return None


def main() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    df = pd.read_csv(INPUT, parse_dates=["Date"])
    required = {"Date", "Description", "Quantity"}
    missing = required - set(df.columns)
    if missing:
        raise RuntimeError(f"Missing required columns: {sorted(missing)}")

    df = df.dropna(subset=["Date", "Description", "Quantity"]).copy()
    df["Description"] = df["Description"].astype(str).str.strip()
    df["Quantity"] = pd.to_numeric(df["Quantity"], errors="coerce").fillna(0)
    df = df[df["Quantity"] > 0]

    all_days = pd.date_range(df["Date"].min(), df["Date"].max(), freq="D")
    daily = (
        df.groupby(["Description", "Date"], as_index=False)["Quantity"]
        .sum()
        .pivot(index="Description", columns="Date", values="Quantity")
        .fillna(0)
        .reindex(columns=all_days, fill_value=0)
    )

    # Select the strongest products by historical units, then forecast each.
    ranked = daily.sum(axis=1).sort_values(ascending=False)
    records = []
    for name in ranked.index:
        if len(records) >= TOP_PRODUCTS:
            break
        series = daily.loc[name]
        result = forecast_product(str(name), series)
        if result is None:
            continue
        mean, lower, upper, mean_all, lower_all, upper_all = result
        total = float(mean.sum())
        monthly_days = pd.date_range(all_days[-1] + pd.Timedelta(days=1), periods=MONTHLY_HORIZON, freq="D")
        monthly_forecast = pd.Series(mean_all, index=monthly_days).groupby(monthly_days.month).sum()
        monthly_lower = pd.Series(lower_all, index=monthly_days).groupby(monthly_days.month).sum()
        monthly_upper = pd.Series(upper_all, index=monthly_days).groupby(monthly_days.month).sum()
        monthly_rows = [
            {
                "month": month,
                "month_name": pd.Timestamp(2026, month, 1).strftime("%B"),
                "actual_2025_units": round(float(series[series.index.month == month].sum()), 2),
                "forecast_units": round(float(monthly_forecast.get(month, 0)), 2),
                "lower_bound": round(float(monthly_lower.get(month, 0)), 2),
                "upper_bound": round(float(monthly_upper.get(month, 0)), 2),
            }
            for month in range(1, 13)
        ]
        peak_month = max(monthly_rows, key=lambda row: row["forecast_units"])

        records.append({
            "product_name": str(name),
            "historical_units_sold": int(round(float(series.sum()))),
            "observed_sale_days": int((series > 0).sum()),
            "forecast_30_day_units": round(total, 2),
            "average_daily_forecast": round(float(mean.mean()), 2),
            "lower_30_day_units": round(float(lower.sum()), 2),
            "upper_30_day_units": round(float(upper.sum()), 2),
            "peak_month": peak_month["month_name"],
            "peak_month_units": peak_month["forecast_units"],
            "monthly_forecast_2026": monthly_rows,
            "forecast": [
                {
                    "date": (all_days[-1] + pd.Timedelta(days=i + 1)).strftime("%Y-%m-%d"),
                    "forecast_quantity": round(float(mean[i]), 2),
                    "lower_bound": round(float(lower[i]), 2),
                    "upper_bound": round(float(upper[i]), 2),
                }
                for i in range(HORIZON)
            ],
        })

    if not records:
        raise RuntimeError("No products met the minimum observed-history requirement.")

    # Highest expected 30-day demand first.
    records.sort(key=lambda r: r["forecast_30_day_units"], reverse=True)
    for i, r in enumerate(records, 1):
        r["rank"] = i
        if r["average_daily_forecast"] >= 2.5:
            r["demand_level"] = "High"
        elif r["average_daily_forecast"] >= 1.5:
            r["demand_level"] = "Moderate"
        else:
            r["demand_level"] = "Low"

    payload = {
        "metadata": {
            "model": "SARIMA",
            "model_order": list(MODEL_ORDER),
            "seasonal_order": list(SEASONAL_ORDER),
            "seasonality": "Weekly",
            "forecast_horizon_days": HORIZON,
            "training_start": all_days[0].strftime("%Y-%m-%d"),
            "training_end": all_days[-1].strftime("%Y-%m-%d"),
            "baseline": True,
            "minimum_observed_sale_days": MIN_OBSERVED_SALE_DAYS,
            "product_count": len(records),
            "generated_at": pd.Timestamp.now().isoformat(timespec="seconds"),
            "method_note": "Product demand is forecast from observed daily unit sales. Monthly rows include 2025 observed units and 2026 SARIMA forecast units. Days with pharmacy-wide completed sales but no product sale are represented as zero observed product sales in the historical baseline.",
        },
        "products": records,
    }
    OUTPUT_JSON.write_text(json.dumps(payload, indent=2), encoding="utf-8")

    rows = []
    for r in records:
        rows.append({k: r[k] for k in ["rank", "product_name", "historical_units_sold", "observed_sale_days", "forecast_30_day_units", "average_daily_forecast", "demand_level"]})
    pd.DataFrame(rows).to_csv(OUTPUT_CSV, index=False)

    print(f"PRODUCT DEMAND FORECAST GENERATED")
    print(f"Products: {len(records)}")
    print(f"JSON: {OUTPUT_JSON}")
    print(f"CSV: {OUTPUT_CSV}")
    for r in records:
        print(f"{r['rank']:>2}. {r['product_name']}: {r['forecast_30_day_units']:.2f} units / 30 days ({r['demand_level']})")


if __name__ == "__main__":
    main()
