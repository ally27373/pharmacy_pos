"""
======================================================================
CRISP-DM PHASE 6 - FINAL MODEL TRAINING
NICA XANDRA PHARMACY POS

Purpose:
    Train the final production SARIMA model using the complete
    historical daily sales dataset.

Selected model from Phase 5:
    SARIMA(1,0,2)(1,0,1,7)

Target:
    quantity_sold

Seasonal period:
    7 days
======================================================================
"""

from pathlib import Path
import json
import warnings

import joblib
import numpy as np
import pandas as pd
from statsmodels.tsa.statespace.sarimax import SARIMAX

warnings.filterwarnings("ignore")


# ======================================================================
# PATH CONFIGURATION
# ======================================================================

BASE_DIR = Path(__file__).resolve().parents[1]

DATA_FILE = BASE_DIR / "data" / "prepared" / "daily_sales.csv"

MODEL_DIR = BASE_DIR / "models"

OUTPUT_DIR = BASE_DIR / "outputs" / "deployment"


# ======================================================================
# FINAL MODEL CONFIGURATION
# ======================================================================

MODEL_ORDER = (1, 0, 2)
SEASONAL_ORDER = (1, 0, 1, 7)

TARGET_COLUMN = "quantity_sold"

SEASONAL_PERIOD = 7


# ======================================================================
# MAIN
# ======================================================================

def main():

    print("=" * 70)
    print("CRISP-DM PHASE 6 - FINAL MODEL TRAINING")
    print("NICA XANDRA PHARMACY POS")
    print("=" * 70)

    # ------------------------------------------------------------------
    # 1. LOAD PREPARED DATA
    # ------------------------------------------------------------------

    print("\n[1] Loading prepared daily sales dataset...")

    if not DATA_FILE.exists():
        raise FileNotFoundError(
            f"Prepared dataset not found:\n{DATA_FILE}"
        )

    df = pd.read_csv(DATA_FILE)

    print(f"Dataset loaded successfully: {len(df):,} observations.")

    # ------------------------------------------------------------------
    # 2. VALIDATE DATA
    # ------------------------------------------------------------------

    print("\n[2] Validating final training dataset...")

    required_columns = ["Date", TARGET_COLUMN]

    missing_columns = [
        column for column in required_columns
        if column not in df.columns
    ]

    if missing_columns:
        raise ValueError(
            f"Missing required columns: {missing_columns}"
        )

    df["Date"] = pd.to_datetime(df["Date"], errors="coerce")

    if df["Date"].isna().any():
        raise ValueError("Invalid dates detected in prepared dataset.")

    if df[TARGET_COLUMN].isna().any():
        raise ValueError(
            f"Missing values detected in {TARGET_COLUMN}."
        )

    # Sort chronologically
    df = df.sort_values("Date").reset_index(drop=True)

    # Check duplicate dates
    duplicate_dates = df["Date"].duplicated().sum()

    if duplicate_dates > 0:
        raise ValueError(
            f"Duplicate dates detected: {duplicate_dates}"
        )

    print("Dataset validation: PASSED")

    # ------------------------------------------------------------------
    # 3. FINAL TRAINING PERIOD
    # ------------------------------------------------------------------

    print("\n[3] Final training period")
    print("-" * 70)

    start_date = df["Date"].min()
    end_date = df["Date"].max()

    print(f"Start date : {start_date.date()}")
    print(f"End date   : {end_date.date()}")
    print(f"Observations: {len(df):,}")

    # ------------------------------------------------------------------
    # 4. PREPARE TIME SERIES
    # ------------------------------------------------------------------

    print("\n[4] Preparing final time-series target...")

    y = (
        df.set_index("Date")[TARGET_COLUMN]
        .astype(float)
        .asfreq("D")
    )

    if y.isna().any():
        raise ValueError(
            "Missing daily observations detected after enforcing "
            "daily frequency."
        )

    print(f"Target variable : {TARGET_COLUMN}")
    print(f"Frequency       : Daily")
    print(f"Seasonal period : {SEASONAL_PERIOD} days")

    print("\nTarget statistics:")
    print(y.describe())

    # ------------------------------------------------------------------
    # 5. TRAIN FINAL SARIMA MODEL
    # ------------------------------------------------------------------

    print("\n[5] Training final SARIMA model")
    print("-" * 70)

    print(
        f"Model: SARIMA"
        f"{MODEL_ORDER}"
        f"{SEASONAL_ORDER}"
    )

    print("Training on the complete historical dataset...")

    model = SARIMAX(
        y,
        order=MODEL_ORDER,
        seasonal_order=SEASONAL_ORDER,
        enforce_stationarity=False,
        enforce_invertibility=False
    )

    fitted_model = model.fit(
        disp=False
    )

    print("Final SARIMA model trained successfully.")

    # ------------------------------------------------------------------
    # 6. MODEL SUMMARY
    # ------------------------------------------------------------------

    print("\n[6] Final model information")
    print("-" * 70)

    print(f"Model order          : {MODEL_ORDER}")
    print(f"Seasonal order       : {SEASONAL_ORDER}")
    print(f"Training observations: {len(y):,}")
    print(f"Log likelihood       : {fitted_model.llf:.4f}")
    print(f"AIC                  : {fitted_model.aic:.4f}")
    print(f"BIC                  : {fitted_model.bic:.4f}")

    # ------------------------------------------------------------------
    # 7. CREATE MODEL DIRECTORY
    # ------------------------------------------------------------------

    print("\n[7] Creating deployment directories...")

    MODEL_DIR.mkdir(
        parents=True,
        exist_ok=True
    )

    OUTPUT_DIR.mkdir(
        parents=True,
        exist_ok=True
    )

    print(f"Model directory:")
    print(MODEL_DIR)

    print(f"\nDeployment output directory:")
    print(OUTPUT_DIR)

    # ------------------------------------------------------------------
    # 8. SAVE MODEL
    # ------------------------------------------------------------------

    print("\n[8] Saving final trained model...")

    model_path = MODEL_DIR / "sarima_final_model.pkl"

    joblib.dump(
        fitted_model,
        model_path
    )

    print("Final model saved:")
    print(model_path)

    # ------------------------------------------------------------------
    # 9. SAVE MODEL CONFIGURATION
    # ------------------------------------------------------------------

    print("\n[9] Saving model configuration...")

    configuration = {
        "model_name": "SARIMA",
        "model_order": list(MODEL_ORDER),
        "seasonal_order": list(SEASONAL_ORDER),
        "seasonal_period": SEASONAL_PERIOD,
        "target_variable": TARGET_COLUMN,
        "frequency": "D",
        "training_observations": int(len(y)),
        "training_start": start_date.strftime("%Y-%m-%d"),
        "training_end": end_date.strftime("%Y-%m-%d"),
        "aic": float(fitted_model.aic),
        "bic": float(fitted_model.bic),
        "log_likelihood": float(fitted_model.llf),
        "selection_method": "Out-of-sample forecasting performance",
        "evaluation_metrics": [
            "MAE",
            "RMSE",
            "MAPE",
            "SMAPE"
        ],
        "test_period_days": 30
    }

    config_path = (
        OUTPUT_DIR /
        "final_model_configuration.json"
    )

    with open(
        config_path,
        "w",
        encoding="utf-8"
    ) as file:

        json.dump(
            configuration,
            file,
            indent=4
        )

    print("Configuration saved:")
    print(config_path)

    # ------------------------------------------------------------------
    # 10. GENERATE SANITY-CHECK FORECAST
    # ------------------------------------------------------------------

    print("\n[10] Generating 30-day sanity-check forecast...")

    forecast_steps = 30

    forecast_result = fitted_model.get_forecast(
        steps=forecast_steps
    )

    forecast_mean = forecast_result.predicted_mean

    confidence_interval = forecast_result.conf_int()

    forecast_df = pd.DataFrame({
        "Date": forecast_mean.index,
        "Forecast_Quantity": forecast_mean.values,
        "Lower_Bound": confidence_interval.iloc[:, 0].values,
        "Upper_Bound": confidence_interval.iloc[:, 1].values
    })

    # Forecast quantities cannot be negative.
    forecast_df["Forecast_Quantity"] = (
        forecast_df["Forecast_Quantity"]
        .clip(lower=0)
    )

    forecast_df["Lower_Bound"] = (
        forecast_df["Lower_Bound"]
        .clip(lower=0)
    )

    forecast_df["Upper_Bound"] = (
        forecast_df["Upper_Bound"]
        .clip(lower=0)
    )

    forecast_path = (
        OUTPUT_DIR /
        "initial_30_day_forecast.csv"
    )

    forecast_df.to_csv(
        forecast_path,
        index=False
    )

    print("Initial forecast saved:")
    print(forecast_path)

    # ------------------------------------------------------------------
    # 11. DISPLAY INITIAL FORECAST
    # ------------------------------------------------------------------

    print("\n[11] Initial 30-day forecast")
    print("-" * 70)

    print(
        forecast_df.to_string(
            index=False
        )
    )

    # ------------------------------------------------------------------
    # 12. SAVE TRAINING SUMMARY
    # ------------------------------------------------------------------

    summary_path = (
        OUTPUT_DIR /
        "final_training_summary.txt"
    )

    with open(
        summary_path,
        "w",
        encoding="utf-8"
    ) as file:

        file.write(
            "CRISP-DM PHASE 6 - FINAL MODEL TRAINING\n"
        )

        file.write(
            "NICA XANDRA PHARMACY POS\n\n"
        )

        file.write(
            "FINAL MODEL\n"
        )

        file.write(
            f"SARIMA{MODEL_ORDER}"
            f"{SEASONAL_ORDER}\n\n"
        )

        file.write(
            "TRAINING DATA\n"
        )

        file.write(
            f"Start date: {start_date.date()}\n"
        )

        file.write(
            f"End date: {end_date.date()}\n"
        )

        file.write(
            f"Observations: {len(y)}\n"
        )

        file.write(
            f"Target variable: {TARGET_COLUMN}\n"
        )

        file.write(
            "Frequency: Daily\n"
        )

        file.write(
            f"Seasonal period: {SEASONAL_PERIOD}\n\n"
        )

        file.write(
            "MODEL INFORMATION\n"
        )

        file.write(
            f"AIC: {fitted_model.aic:.6f}\n"
        )

        file.write(
            f"BIC: {fitted_model.bic:.6f}\n"
        )

        file.write(
            f"Log likelihood: {fitted_model.llf:.6f}\n\n"
        )

        file.write(
            "PHASE 5 OUT-OF-SAMPLE EVALUATION\n"
        )

        file.write(
            "MAE: 54.1700\n"
        )

        file.write(
            "RMSE: 60.8193\n"
        )

        file.write(
            "MAPE: 14.53%\n"
        )

        file.write(
            "SMAPE: 14.55%\n\n"
        )

        file.write(
            "DEPLOYMENT ARTIFACT\n"
        )

        file.write(
            f"{model_path}\n"
        )

    print("\nTraining summary saved:")
    print(summary_path)

    # ------------------------------------------------------------------
    # COMPLETE
    # ------------------------------------------------------------------

    print("\n" + "=" * 70)
    print("CRISP-DM PHASE 6 - FINAL MODEL TRAINING COMPLETE")
    print("=" * 70)

    print("\nFinal production model:")
    print(
        f"SARIMA{MODEL_ORDER}"
        f"{SEASONAL_ORDER}"
    )

    print("\nTraining dataset:")
    print(
        f"{start_date.date()} to {end_date.date()}"
    )

    print(f"Training observations: {len(y):,}")

    print("\nSaved deployment artifacts:")

    print(f"- {model_path}")
    print(f"- {config_path}")
    print(f"- {forecast_path}")
    print(f"- {summary_path}")

    print("\nNext CRISP-DM phase:")
    print("DEPLOYMENT / SYSTEM INTEGRATION")


# ======================================================================
# ENTRY POINT
# ======================================================================

if __name__ == "__main__":
    main()