from pathlib import Path
import json
import shutil
import warnings
from datetime import datetime

import joblib
import pandas as pd
from statsmodels.tsa.statespace.sarimax import SARIMAX

warnings.filterwarnings("ignore")

BASE_DIR = Path(__file__).resolve().parents[1]
MODEL_PATH = BASE_DIR / "models" / "sarima_final_model.pkl"
CONFIG_PATH = BASE_DIR / "outputs" / "deployment" / "final_model_configuration.json"
INPUT_PATH = BASE_DIR / "outputs" / "deployment" / "latest_daily_demand.csv"
OUTPUT_DIR = BASE_DIR / "outputs" / "deployment"
STATUS_PATH = OUTPUT_DIR / "forecast_status.json"

FORECAST_HORIZON = 30
MIN_NEW_OBSERVATIONS_FOR_REFIT = 30


def load_config():
    if not CONFIG_PATH.exists():
        return {}
    with CONFIG_PATH.open("r", encoding="utf-8") as f:
        return json.load(f)


def load_daily_input():
    if not INPUT_PATH.exists():
        raise FileNotFoundError(f"Latest daily demand file not found: {INPUT_PATH}")

    df = pd.read_csv(INPUT_PATH)
    required = {"date", "quantity_sold"}
    missing = required - set(df.columns)
    if missing:
        raise ValueError(f"Missing required columns: {sorted(missing)}")

    df = df.copy()
    df["date"] = pd.to_datetime(df["date"], errors="raise")
    df["quantity_sold"] = pd.to_numeric(df["quantity_sold"], errors="raise")

    if df["quantity_sold"].isna().any():
        raise ValueError("Missing quantity_sold values detected.")
    if (df["quantity_sold"] < 0).any():
        raise ValueError("Negative demand quantities are not allowed.")

    return (
        df.groupby("date", as_index=False)["quantity_sold"]
        .sum()
        .sort_values("date")
        .reset_index(drop=True)
    )


def write_status(status):
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    with STATUS_PATH.open("w", encoding="utf-8") as f:
        json.dump(status, f, indent=4)


def get_model_series(fitted_model):
    labels = pd.DatetimeIndex(fitted_model.model.data.row_labels)
    values = fitted_model.model.data.endog
    values = pd.Series(values.reshape(-1), index=labels, dtype=float)
    return values.sort_index().asfreq("D")


def generate_forecast(
    fitted_model,
    config,
    model_last_date,
    input_last_date,
    new_observations,
    refit,
    forecast_start_date=None,
):
    """Generate the next 30 forecast days after the requested live date.

    For the initial live baseline, the trained 2025 model is used to project
    forward until the live operational date, then the next 30 days are kept.
    This does not invent missing POS observations or treat them as zeros.
    """
    forecast_start_date = pd.Timestamp(forecast_start_date) if forecast_start_date is not None else None

    if forecast_start_date is None:
        forecast_result = fitted_model.get_forecast(steps=FORECAST_HORIZON)
        forecast_mean = forecast_result.predicted_mean
        confidence_interval = forecast_result.conf_int()
    else:
        if forecast_start_date <= model_last_date:
            raise ValueError("Forecast start date must be after the trained model endpoint.")

        steps_to_start = int((forecast_start_date - model_last_date).days)
        total_steps = steps_to_start + FORECAST_HORIZON
        forecast_result = fitted_model.get_forecast(steps=total_steps)
        forecast_mean = forecast_result.predicted_mean
        confidence_interval = forecast_result.conf_int()

        forecast_mean = forecast_mean[forecast_mean.index >= forecast_start_date].iloc[:FORECAST_HORIZON]
        confidence_interval = confidence_interval.loc[confidence_interval.index >= forecast_start_date].iloc[:FORECAST_HORIZON]

    forecast_df = pd.DataFrame({
        "Date": forecast_mean.index,
        "Forecast_Quantity": forecast_mean.values,
        "Lower_Bound": confidence_interval.iloc[:, 0].values,
        "Upper_Bound": confidence_interval.iloc[:, 1].values,
    })

    for col in ["Forecast_Quantity", "Lower_Bound", "Upper_Bound"]:
        forecast_df[col] = forecast_df[col].clip(lower=0)

    csv_path = OUTPUT_DIR / "forecast_30_day.csv"
    json_path = OUTPUT_DIR / "forecast_30_day.json"
    forecast_df.to_csv(csv_path, index=False)

    records = []
    for row in forecast_df.itertuples(index=False):
        records.append({
            "date": pd.Timestamp(row.Date).strftime("%Y-%m-%d"),
            "forecast_quantity": round(float(row.Forecast_Quantity), 6),
            "lower_bound": round(float(row.Lower_Bound), 6),
            "upper_bound": round(float(row.Upper_Bound), 6),
        })

    metadata = {
        "model_name": config.get("model_name", "SARIMA"),
        "model_order": config.get("model_order", [1, 0, 2]),
        "seasonal_order": config.get("seasonal_order", [1, 0, 1, 7]),
        "seasonal_period": config.get("seasonal_period", 7),
        "target_variable": config.get("target_variable", "quantity_sold"),
        "training_start": config.get("training_start"),
        "training_end": config.get("training_end"),
        "frequency": config.get("frequency", "D"),
        "input_first_date": None,
        "input_last_date": input_last_date.strftime("%Y-%m-%d"),
        "model_last_date_before_update": model_last_date.strftime("%Y-%m-%d") if model_last_date is not None else None,
        "live_operational_start": None,
        "new_observations_appended": int(new_observations),
        "model_refit": bool(refit),
        "forecast_type": "updated" if refit else "baseline",
        "forecast_horizon_days": FORECAST_HORIZON,
        "forecast_generated_at": datetime.now().astimezone().isoformat(),
        "evaluation_metrics": {
            "MAE": 54.1700,
            "RMSE": 60.8193,
            "MAPE": 14.53,
            "SMAPE": 14.55,
        },
    }

    if forecast_start_date is not None:
        metadata["forecast_start"] = forecast_df["Date"].min().strftime("%Y-%m-%d")
        metadata["forecast_end"] = forecast_df["Date"].max().strftime("%Y-%m-%d")

    payload = {"metadata": metadata, "forecast": records}
    with json_path.open("w", encoding="utf-8") as f:
        json.dump(payload, f, indent=4)

    return forecast_df, metadata

def main():
    print("=" * 70)
    print("SARIMA 30-DAY FORECAST GENERATION")
    print("=" * 70)

    if not MODEL_PATH.exists():
        raise FileNotFoundError(f"Trained SARIMA model not found: {MODEL_PATH}")

    config = load_config()
    df = load_daily_input()
    fitted_model = joblib.load(MODEL_PATH)

    model_series = get_model_series(fitted_model)
    model_last_date = model_series.index.max()
    input_last_date = df["date"].max()
    input_first_date = df["date"].min()

    print(f"Input observations : {len(df):,}")
    print(f"Input start date   : {input_first_date.date()}")
    print(f"Input last date    : {input_last_date.date()}")
    print(f"Model endpoint     : {model_last_date.date()}")

    series = df.set_index("date")["quantity_sold"].astype(float).sort_index()
    post_training = series[series.index > model_last_date]

    # No post-training observations: preserve the already deployed artifact.
    if post_training.empty:
        status = {
            "ready": False,
            "status": "baseline_only",
            "reason": "No post-training POS observations are available after the trained model endpoint.",
            "model_last_date": model_last_date.strftime("%Y-%m-%d"),
            "input_last_date": input_last_date.strftime("%Y-%m-%d"),
            "new_observations": 0,
            "minimum_new_observations_for_refit": MIN_NEW_OBSERVATIONS_FOR_REFIT,
            "updated_at": datetime.now().astimezone().isoformat(),
        }
        write_status(status)
        print("No post-training observations available; deployed model retained.")
        print("Forecast refresh not ready; existing forecast artifact was not overwritten.")
        return

    # The first legitimate POS observation establishes the live operational
    # start. We do NOT require observations for the calendar gap between the
    # historical training period and system go-live.
    live_start = post_training.index.min()
    expected_live_index = pd.date_range(live_start, post_training.index.max(), freq="D")

    if not expected_live_index.equals(pd.DatetimeIndex(post_training.index)):
        # A calendar gap blocks MODEL REFITTING, but it does not prevent the
        # trained 2025 model from producing a legitimate baseline forecast.
        # Anchor that baseline to the latest live POS observation so the
        # dashboard remains useful during the early operational period.
        forecast_df, metadata = generate_forecast(
            fitted_model,
            config,
            model_last_date,
            input_last_date,
            len(post_training),
            False,
            forecast_start_date=input_last_date + pd.Timedelta(days=1),
        )
        metadata["input_first_date"] = input_first_date.strftime("%Y-%m-%d")
        metadata["live_operational_start"] = live_start.strftime("%Y-%m-%d")
        metadata_path = OUTPUT_DIR / "forecast_30_day.json"
        metadata_payload = {
            "metadata": metadata,
            "forecast": [
                {
                    "date": pd.Timestamp(row.Date).strftime("%Y-%m-%d"),
                    "forecast_quantity": round(float(row.Forecast_Quantity), 6),
                    "lower_bound": round(float(row.Lower_Bound), 6),
                    "upper_bound": round(float(row.Upper_Bound), 6),
                }
                for row in forecast_df.itertuples(index=False)
            ],
        }
        with metadata_path.open("w", encoding="utf-8") as f:
            json.dump(metadata_payload, f, indent=4)

        status = {
            "ready": False,
            "status": "waiting_for_continuous_history",
            "reason": "Live POS demand contains one or more missing calendar days after the operational start. Missing dates are not treated as zero sales. The trained 2025 SARIMA model remains available as the baseline forecast.",
            "model_last_date": model_last_date.strftime("%Y-%m-%d"),
            "training_start": config.get("training_start"),
            "training_end": config.get("training_end"),
            "live_operational_start": live_start.strftime("%Y-%m-%d"),
            "input_last_date": input_last_date.strftime("%Y-%m-%d"),
            "new_observations": int(len(post_training)),
            "minimum_new_observations_for_refit": MIN_NEW_OBSERVATIONS_FOR_REFIT,
            "forecast_start": forecast_df["Date"].min().strftime("%Y-%m-%d"),
            "forecast_end": forecast_df["Date"].max().strftime("%Y-%m-%d"),
            "forecast_type": "baseline",
            "updated_at": metadata["forecast_generated_at"],
        }
        write_status(status)
        print(f"Refresh not ready: live history from {live_start.date()} contains calendar gaps.")
        print("Missing dates are not treated as zero sales.")
        print(f"Baseline forecast start  : {forecast_df['Date'].min().date()}")
        print(f"Baseline forecast end    : {forecast_df['Date'].max().date()}")
        print("Baseline forecast updated safely; model refit not performed.")
        return

    # Until enough legitimate live observations exist, publish a baseline
    # forecast anchored to the latest live POS observation. The trained model
    # forecasts through the calendar gap; no artificial POS observations are
    # inserted into the training series.
    if len(post_training) < MIN_NEW_OBSERVATIONS_FOR_REFIT:
        forecast_df, metadata = generate_forecast(
            fitted_model,
            config,
            model_last_date,
            input_last_date,
            len(post_training),
            False,
            forecast_start_date=input_last_date + pd.Timedelta(days=1),
        )
        metadata["input_first_date"] = input_first_date.strftime("%Y-%m-%d")
        metadata["live_operational_start"] = live_start.strftime("%Y-%m-%d")

        # Rewrite the JSON with the completed baseline metadata.
        json_path = OUTPUT_DIR / "forecast_30_day.json"
        payload = {
            "metadata": metadata,
            "forecast": [
                {
                    "date": pd.Timestamp(row.Date).strftime("%Y-%m-%d"),
                    "forecast_quantity": round(float(row.Forecast_Quantity), 6),
                    "lower_bound": round(float(row.Lower_Bound), 6),
                    "upper_bound": round(float(row.Upper_Bound), 6),
                }
                for row in forecast_df.itertuples(index=False)
            ],
        }
        with json_path.open("w", encoding="utf-8") as f:
            json.dump(payload, f, indent=4)

        status = {
            "ready": False,
            "status": "collecting_new_data",
            "reason": "Live POS demand is continuous but there are not yet enough observations for a production SARIMA refit. The trained 2025 model is retained as the baseline and the forecast is anchored to the latest live POS date.",
            "model_last_date": model_last_date.strftime("%Y-%m-%d"),
            "training_start": config.get("training_start"),
            "training_end": config.get("training_end"),
            "live_operational_start": live_start.strftime("%Y-%m-%d"),
            "input_last_date": input_last_date.strftime("%Y-%m-%d"),
            "new_observations": int(len(post_training)),
            "minimum_new_observations_for_refit": MIN_NEW_OBSERVATIONS_FOR_REFIT,
            "forecast_start": forecast_df["Date"].min().strftime("%Y-%m-%d"),
            "forecast_end": forecast_df["Date"].max().strftime("%Y-%m-%d"),
            "forecast_type": "baseline",
            "updated_at": metadata["forecast_generated_at"],
        }
        write_status(status)
        print(f"Continuous live observations: {len(post_training)}")
        print(f"Baseline forecast start  : {forecast_df['Date'].min().date()}")
        print(f"Baseline forecast end    : {forecast_df['Date'].max().date()}")
        print(f"Refresh not ready: {len(post_training)} new observations; {MIN_NEW_OBSERVATIONS_FOR_REFIT} required for refit.")
        print("Baseline forecast updated safely; model refit not performed.")
        return

    # Refit using the complete historical model series plus the legitimate
    # continuous live observations. The Jan-Aug 2026 calendar gap remains NaN
    # (missing), never zero. SARIMAX can handle missing observations during
    # state-space estimation.
    combined = pd.concat([model_series, post_training]).sort_index()
    combined = combined[~combined.index.duplicated(keep="last")].asfreq("D")

    order = tuple(config.get("model_order", [1, 0, 2]))
    seasonal_order = tuple(config.get("seasonal_order", [1, 0, 1, 7]))

    print(f"Refitting SARIMA on {len(combined):,} calendar observations...")

    # Protect the last known-good deployment artifacts. A failed refit,
    # failed forecast generation, or failed model write must never destroy
    # the previously working model/forecast.
    protected_paths = [
        MODEL_PATH,
        OUTPUT_DIR / "forecast_30_day.csv",
        OUTPUT_DIR / "forecast_30_day.json",
        STATUS_PATH,
    ]
    backups = {}
    for path in protected_paths:
        if path.exists():
            backup = path.with_name(path.name + ".task3.bak")
            shutil.copy2(path, backup)
            backups[path] = backup

    refit_model = None
    convergence_attempts = []

    try:
        # A SARIMA refit is accepted only when the optimizer reports
        # convergence. This prevents a non-converged model from replacing
        # the last known-good model. Try a second optimizer before rejecting
        # the refit entirely.
        fit_attempts = [
            ("lbfgs", {"maxiter": 2000}),
            ("powell", {"maxiter": 1000}),
        ]

        last_fit_error = None
        for method, fit_kwargs in fit_attempts:
            try:
                with warnings.catch_warnings(record=True) as fit_warnings:
                    warnings.simplefilter("always")
                    candidate = SARIMAX(
                        combined,
                        order=order,
                        seasonal_order=seasonal_order,
                        enforce_stationarity=False,
                        enforce_invertibility=False,
                    ).fit(disp=False, method=method, **fit_kwargs)

                mle_retvals = getattr(candidate, "mle_retvals", {}) or {}
                converged = bool(mle_retvals.get("converged", False))
                warning_names = sorted({type(w.message).__name__ for w in fit_warnings})
                convergence_attempts.append({
                    "method": method,
                    "converged": converged,
                    "iterations": mle_retvals.get("iterations"),
                    "warnings": warning_names,
                })

                print(f"Optimizer {method}: converged={converged}")

                if converged:
                    refit_model = candidate
                    break

                last_fit_error = RuntimeError(
                    f"SARIMA optimizer '{method}' did not report convergence."
                )
            except Exception as exc:
                convergence_attempts.append({
                    "method": method,
                    "converged": False,
                    "iterations": None,
                    "warnings": [],
                    "error": str(exc),
                })
                last_fit_error = exc
                print(f"Optimizer {method} failed: {exc}")

        if refit_model is None:
            raise RuntimeError(
                "SARIMA refit rejected: optimizer did not converge. "
                "The last known-good model and forecast were preserved."
            ) from last_fit_error

        # Generate and validate the forecast BEFORE replacing the deployed
        # model. If forecasting fails, the old model remains untouched.
        forecast_df, metadata = generate_forecast(
            refit_model,
            config,
            model_last_date,
            input_last_date,
            len(post_training),
            True,
            forecast_start_date=input_last_date + pd.Timedelta(days=1),
        )

        if len(forecast_df) != FORECAST_HORIZON:
            raise RuntimeError(
                f"SARIMA refit rejected: expected {FORECAST_HORIZON} forecast records, "
                f"got {len(forecast_df)}."
            )

        if forecast_df[["Forecast_Quantity", "Lower_Bound", "Upper_Bound"]].isna().any().any():
            raise RuntimeError("SARIMA refit rejected: forecast contains missing values.")

        metadata["convergence_verified"] = True
        metadata["convergence_attempts"] = convergence_attempts

        # Save the new model only after the refit and forecast have both
        # passed validation. The temporary file is atomically promoted.
        temp_model_path = MODEL_PATH.with_suffix(".pkl.tmp")
        joblib.dump(refit_model, temp_model_path)
        temp_model_path.replace(MODEL_PATH)

    except Exception as exc:
        # Restore every protected artifact, including the previous model and
        # forecast. This is the critical rollback guarantee for a failed
        # production refit.
        for path, backup in backups.items():
            if backup.exists():
                shutil.copy2(backup, path)
        temp_model_path = MODEL_PATH.with_suffix(".pkl.tmp")
        if temp_model_path.exists():
            temp_model_path.unlink()

        failed_status = {
            "ready": False,
            "status": "refit_failed",
            "reason": str(exc),
            "model_last_date": model_last_date.strftime("%Y-%m-%d"),
            "training_start": config.get("training_start"),
            "training_end": config.get("training_end"),
            "live_operational_start": live_start.strftime("%Y-%m-%d"),
            "input_last_date": input_last_date.strftime("%Y-%m-%d"),
            "new_observations": int(len(post_training)),
            "minimum_new_observations_for_refit": MIN_NEW_OBSERVATIONS_FOR_REFIT,
            "forecast_type": "baseline_preserved",
            "convergence_verified": False,
            "convergence_attempts": convergence_attempts,
            "updated_at": datetime.now().astimezone().isoformat(),
        }
        write_status(failed_status)
        print("REFIT REJECTED: last known-good model and forecast preserved.")
        print(f"Reason: {exc}")
        raise
    finally:
        for backup in backups.values():
            if backup.exists():
                backup.unlink()
    metadata["input_first_date"] = input_first_date.strftime("%Y-%m-%d")
    metadata["live_operational_start"] = live_start.strftime("%Y-%m-%d")
    metadata["updated_model_last_date"] = input_last_date.strftime("%Y-%m-%d")

    json_path = OUTPUT_DIR / "forecast_30_day.json"
    payload = {
        "metadata": metadata,
        "forecast": [
            {
                "date": pd.Timestamp(row.Date).strftime("%Y-%m-%d"),
                "forecast_quantity": round(float(row.Forecast_Quantity), 6),
                "lower_bound": round(float(row.Lower_Bound), 6),
                "upper_bound": round(float(row.Upper_Bound), 6),
            }
            for row in forecast_df.itertuples(index=False)
        ],
    }
    with json_path.open("w", encoding="utf-8") as f:
        json.dump(payload, f, indent=4)

    status = {
        "ready": True,
        "status": "updated",
        "reason": "Continuous live POS demand was sufficient for a SARIMA refit.",
        "model_last_date_before_refit": model_last_date.strftime("%Y-%m-%d"),
        "updated_model_last_date": input_last_date.strftime("%Y-%m-%d"),
        "training_start": config.get("training_start"),
        "training_end": config.get("training_end"),
        "live_operational_start": live_start.strftime("%Y-%m-%d"),
        "new_observations": int(len(post_training)),
        "minimum_new_observations_for_refit": MIN_NEW_OBSERVATIONS_FOR_REFIT,
        "forecast_start": forecast_df["Date"].min().strftime("%Y-%m-%d"),
        "forecast_end": forecast_df["Date"].max().strftime("%Y-%m-%d"),
        "forecast_type": "updated",
        "updated_at": metadata["forecast_generated_at"],
    }
    write_status(status)

    print(f"Forecast records    : {len(forecast_df):,}")
    print(f"Forecast start      : {forecast_df['Date'].min().date()}")
    print(f"Forecast end        : {forecast_df['Date'].max().date()}")
    print(f"New observations    : {len(post_training):,}")
    print("Model refit         : YES")
    print("SARIMA forecast generation completed successfully.")


if __name__ == "__main__":
    main()
