"""Generate a current-year SARIMA annual outlook for the Analytics Dashboard.

The annual artifact is intentionally year-dynamic. It uses the latest deployed
SARIMA model and the latest legitimate POS daily-demand input. Observed POS
sales for the current year are shown as actuals; the remaining days of the
current year are forecast from the deployed model. Missing POS days are not
invented as zero-sales observations.
"""
from __future__ import annotations

import json
from datetime import datetime
from pathlib import Path

import numpy as np
import pandas as pd
import joblib

ROOT = Path(__file__).resolve().parents[2]
SARIMA_ROOT = ROOT / "sarima_forecasting"
MODEL_PATH = SARIMA_ROOT / "models" / "sarima_final_model.pkl"
CONFIG_PATH = SARIMA_ROOT / "outputs" / "deployment" / "final_model_configuration.json"
STATUS_PATH = SARIMA_ROOT / "outputs" / "deployment" / "forecast_status.json"
INPUT_PATH = SARIMA_ROOT / "outputs" / "deployment" / "latest_daily_demand.csv"
OUTPUT_DIR = SARIMA_ROOT / "outputs" / "deployment"

TIMEZONE = "Asia/Manila"


def load_json(path: Path) -> dict:
    if not path.exists() or path.stat().st_size == 0:
        return {}
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return {}
    return data if isinstance(data, dict) else {}


def load_latest_daily_demand() -> pd.Series:
    if not INPUT_PATH.exists():
        return pd.Series(dtype=float)

    df = pd.read_csv(INPUT_PATH)
    required = {"date", "quantity_sold"}
    missing = required - set(df.columns)
    if missing:
        raise RuntimeError(f"Missing required columns in latest daily demand: {sorted(missing)}")

    df["date"] = pd.to_datetime(df["date"], errors="raise")
    df["quantity_sold"] = pd.to_numeric(df["quantity_sold"], errors="raise")

    if df["quantity_sold"].isna().any() or (df["quantity_sold"] < 0).any():
        raise RuntimeError("Invalid quantity_sold values detected in latest daily demand.")

    return (
        df.groupby("date")["quantity_sold"]
        .sum()
        .sort_index()
        .astype(float)
    )


def model_series(model) -> pd.Series:
    labels = pd.DatetimeIndex(model.model.data.row_labels)
    values = np.asarray(model.model.data.endog).reshape(-1)
    return pd.Series(values, index=labels, dtype=float).sort_index()


def forecast_window(model, start_date: pd.Timestamp, end_date: pd.Timestamp):
    if start_date > end_date:
        return pd.DataFrame(columns=["date", "forecast", "lower_bound", "upper_bound"])

    fitted_series = model_series(model)
    model_last_date = fitted_series.index.max()
    if start_date <= model_last_date:
        raise RuntimeError(
            f"Annual forecast start {start_date.date()} must be after deployed model endpoint {model_last_date.date()}."
        )

    total_steps = int((end_date - model_last_date).days)
    result = model.get_forecast(steps=total_steps)
    mean = pd.Series(np.asarray(result.predicted_mean, dtype=float), index=result.predicted_mean.index)
    ci = result.conf_int()

    mask = (mean.index >= start_date) & (mean.index <= end_date)
    dates = mean.index[mask]

    lower = np.asarray(ci.iloc[:, 0], dtype=float)
    upper = np.asarray(ci.iloc[:, 1], dtype=float)

    lower_series = pd.Series(lower, index=ci.index)
    upper_series = pd.Series(upper, index=ci.index)

    return pd.DataFrame({
        "date": dates,
        "forecast": np.maximum(0, mean.loc[dates].to_numpy(dtype=float)),
        "lower_bound": np.maximum(0, lower_series.loc[dates].to_numpy(dtype=float)),
        "upper_bound": np.maximum(0, upper_series.loc[dates].to_numpy(dtype=float)),
    })


def main() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

    if not MODEL_PATH.exists() or MODEL_PATH.stat().st_size == 0:
        raise RuntimeError(f"Deployed SARIMA model not found: {MODEL_PATH}")

    config = load_json(CONFIG_PATH)
    status = load_json(STATUS_PATH)
    model = joblib.load(MODEL_PATH)
    demand = load_latest_daily_demand()

    now = pd.Timestamp.now(tz=TIMEZONE)
    forecast_year = int(now.year)
    year_start = pd.Timestamp(f"{forecast_year}-01-01")
    year_end = pd.Timestamp(f"{forecast_year}-12-31")

    current_actuals = demand[demand.index.year == forecast_year].copy()
    actual_through = current_actuals.index.max() if not current_actuals.empty else None

    # Only dates not already represented by legitimate POS observations are
    # forecast. If no current-year observations exist, forecast the full year.
    forecast_start = (actual_through + pd.Timedelta(days=1)) if actual_through is not None else year_start
    forecast_start = max(forecast_start, year_start)

    forecast_df = forecast_window(model, forecast_start, year_end)

    actual_monthly = current_actuals.groupby(current_actuals.index.month).sum()
    forecast_monthly = forecast_df.groupby(forecast_df["date"].dt.month)["forecast"].sum()
    lower_monthly = forecast_df.groupby(forecast_df["date"].dt.month)["lower_bound"].sum()
    upper_monthly = forecast_df.groupby(forecast_df["date"].dt.month)["upper_bound"].sum()

    rows = []
    for month in range(1, 13):
        has_actual = month in actual_monthly.index
        has_forecast = month in forecast_monthly.index
        rows.append({
            "month": month,
            "month_name": pd.Timestamp(forecast_year, month, 1).strftime("%b"),
            "actual_units": round(float(actual_monthly.loc[month]), 2) if has_actual else None,
            "forecast_units": round(float(forecast_monthly.loc[month]), 2) if has_forecast else None,
            "lower_bound": round(float(lower_monthly.loc[month]), 2) if has_forecast else None,
            "upper_bound": round(float(upper_monthly.loc[month]), 2) if has_forecast else None,
        })

    model_last_date = model_series(model).index.max()
    payload = {
        "metadata": {
            "model": config.get("model_name", "SARIMA"),
            "model_order": config.get("model_order", [1, 0, 2]),
            "seasonal_order": config.get("seasonal_order", [1, 0, 1, 7]),
            "seasonality": "Weekly",
            "training_start": config.get("training_start"),
            "training_end": config.get("training_end"),
            "model_endpoint": model_last_date.strftime("%Y-%m-%d"),
            "forecast_year": forecast_year,
            "actual_year": forecast_year,
            "frequency": "D",
            "baseline": (status.get("status") != "updated"),
            "source_forecast_status": status.get("status"),
            "actual_data_through": actual_through.strftime("%Y-%m-%d") if actual_through is not None else None,
            "forecast_start": forecast_df["date"].min().strftime("%Y-%m-%d") if not forecast_df.empty else None,
            "forecast_end": forecast_df["date"].max().strftime("%Y-%m-%d") if not forecast_df.empty else None,
            "generated_at": datetime.now().astimezone().isoformat(timespec="seconds"),
            "purpose": "Current-year annual outlook: legitimate current-year POS actuals plus SARIMA forecast for the remaining days. The rolling 30-day forecast remains the operational deployment artifact.",
        },
        "months": rows,
    }

    output_json = OUTPUT_DIR / f"annual_forecast_{forecast_year}.json"
    temp_json = output_json.with_suffix(".json.tmp")
    temp_json.write_text(json.dumps(payload, indent=2), encoding="utf-8")
    temp_json.replace(output_json)

    print("ANNUAL FORECAST GENERATED")
    print(f"Forecast year       : {forecast_year}")
    print(f"Actual data through : {actual_through.date() if actual_through is not None else 'none'}")
    print(f"Forecast start      : {forecast_df['date'].min().date() if not forecast_df.empty else 'none'}")
    print(f"Forecast end        : {forecast_df['date'].max().date() if not forecast_df.empty else 'none'}")
    print(f"JSON                : {output_json}")
    print(f"Months              : {len(rows)}")


if __name__ == "__main__":
    main()
