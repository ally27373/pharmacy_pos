"""
CRISP-DM PHASE 5 - MODEL EVALUATION
NICA XANDRA PHARMACY POS

Purpose:
    Evaluate SARIMA forecasting performance using the
    chronological hold-out testing period.

Evaluation metrics:
    - MAE
    - RMSE
    - MAPE
    - SMAPE

The test data remains completely unseen during model training.
"""

from pathlib import Path
import warnings

import numpy as np
import pandas as pd

from statsmodels.tsa.statespace.sarimax import SARIMAX
from sklearn.metrics import mean_absolute_error, mean_squared_error

warnings.filterwarnings("ignore")


# ============================================================================
# PATH CONFIGURATION
# ============================================================================

BASE_DIR = Path(__file__).resolve().parents[1]

DATA_FILE = (
    BASE_DIR
    / "data"
    / "prepared"
    / "daily_sales.csv"
)

MODEL_RESULTS_FILE = (
    BASE_DIR
    / "outputs"
    / "modeling"
    / "sarima_candidate_results.csv"
)

OUTPUT_DIR = (
    BASE_DIR
    / "outputs"
    / "evaluation"
)

OUTPUT_DIR.mkdir(
    parents=True,
    exist_ok=True
)


# ============================================================================
# CONFIGURATION
# ============================================================================

TARGET = "quantity_sold"

SEASONAL_PERIOD = 7

TEST_DAYS = 30


# ============================================================================
# METRIC FUNCTIONS
# ============================================================================

def calculate_mape(actual, predicted):
    """
    Calculate Mean Absolute Percentage Error.

    Zero actual values are excluded because percentage
    error is undefined when actual = 0.
    """

    actual = np.asarray(actual, dtype=float)
    predicted = np.asarray(predicted, dtype=float)

    mask = actual != 0

    if not np.any(mask):
        return np.nan

    return (
        np.mean(
            np.abs(
                (actual[mask] - predicted[mask])
                / actual[mask]
            )
        )
        * 100
    )


def calculate_smape(actual, predicted):
    """
    Calculate Symmetric Mean Absolute Percentage Error.
    """

    actual = np.asarray(actual, dtype=float)
    predicted = np.asarray(predicted, dtype=float)

    denominator = (
        np.abs(actual)
        + np.abs(predicted)
    )

    mask = denominator != 0

    if not np.any(mask):
        return np.nan

    return (
        np.mean(
            2
            * np.abs(
                actual[mask] - predicted[mask]
            )
            / denominator[mask]
        )
        * 100
    )


# ============================================================================
# MODEL STRING PARSING
# ============================================================================

def parse_tuple(value):
    """
    Convert a stored tuple-like string such as:

        '(0, 0, 2)'

    into:

        (0, 0, 2)
    """

    value = str(value).strip()

    value = (
        value
        .replace("(", "")
        .replace(")", "")
    )

    parts = [
        part.strip()
        for part in value.split(",")
        if part.strip()
    ]

    return tuple(
        int(float(part))
        for part in parts
    )


def parse_seasonal_tuple(value):
    """
    Convert:

        '(1, 1, 1, 7)'

    into:

        (1, 1, 1, 7)
    """

    return parse_tuple(value)


# ============================================================================
# LOAD DATA
# ============================================================================

def load_data():
    print("[1] Loading prepared daily sales dataset...")

    if not DATA_FILE.exists():
        raise FileNotFoundError(
            f"Prepared dataset not found:\n{DATA_FILE}"
        )

    df = pd.read_csv(
        DATA_FILE
    )

    if "Date" not in df.columns:
        raise ValueError(
            "Date column is missing from daily_sales.csv."
        )

    if TARGET not in df.columns:
        raise ValueError(
            f"Target column '{TARGET}' "
            "is missing from daily_sales.csv."
        )

    df["Date"] = pd.to_datetime(
        df["Date"],
        errors="coerce"
    )

    if df["Date"].isna().any():
        raise ValueError(
            "Invalid dates detected in prepared dataset."
        )

    df = (
        df
        .sort_values("Date")
        .reset_index(drop=True)
    )

    df[TARGET] = pd.to_numeric(
        df[TARGET],
        errors="coerce"
    )

    if df[TARGET].isna().any():
        raise ValueError(
            f"Invalid values detected in target: {TARGET}"
        )

    print(
        f"Dataset loaded successfully: "
        f"{len(df):,} observations."
    )

    return df


# ============================================================================
# LOAD CANDIDATE MODELS
# ============================================================================

def load_candidate_models():
    print()
    print("[2] Loading SARIMA candidate results...")

    if not MODEL_RESULTS_FILE.exists():
        raise FileNotFoundError(
            "SARIMA candidate results not found:\n"
            f"{MODEL_RESULTS_FILE}"
        )

    results = pd.read_csv(
        MODEL_RESULTS_FILE
    )

    required_columns = [
        "model_order",
        "seasonal_order",
        "aic"
    ]

    missing = [
        column
        for column in required_columns
        if column not in results.columns
    ]

    if missing:
        raise ValueError(
            "Missing columns in candidate results: "
            + ", ".join(missing)
        )

    print(
        f"Candidate models loaded: "
        f"{len(results)}"
    )

    return results


# ============================================================================
# TRAIN / TEST SPLIT
# ============================================================================

def create_split(df):
    print()
    print("=" * 70)
    print("[3] Creating chronological train/test split")
    print("=" * 70)

    if len(df) <= TEST_DAYS:
        raise ValueError(
            "Dataset does not contain enough observations "
            "for the configured test period."
        )

    train = df.iloc[:-TEST_DAYS].copy()

    test = df.iloc[-TEST_DAYS:].copy()

    print(
        f"Total observations : {len(df)}"
    )

    print(
        f"Training observations: {len(train)}"
    )

    print(
        f"Testing observations : {len(test)}"
    )

    print()
    print("Training period:")
    print(
        f"  {train['Date'].min().date()} "
        f"to "
        f"{train['Date'].max().date()}"
    )

    print()
    print("Testing period:")
    print(
        f"  {test['Date'].min().date()} "
        f"to "
        f"{test['Date'].max().date()}"
    )

    return train, test


# ============================================================================
# EVALUATE ONE MODEL
# ============================================================================

def evaluate_model(
    train_series,
    test_series,
    model_order,
    seasonal_order
):
    """
    Train the specified SARIMA model using only the
    training data, then forecast the complete test period.
    """

    model = SARIMAX(
        train_series,
        order=model_order,
        seasonal_order=seasonal_order,
        enforce_stationarity=False,
        enforce_invertibility=False
    )

    fitted_model = model.fit(
        disp=False
    )

    forecast = fitted_model.forecast(
        steps=len(test_series)
    )

    forecast = np.asarray(
        forecast,
        dtype=float
    )

    actual = np.asarray(
        test_series,
        dtype=float
    )

    mae = mean_absolute_error(
        actual,
        forecast
    )

    rmse = np.sqrt(
        mean_squared_error(
            actual,
            forecast
        )
    )

    mape = calculate_mape(
        actual,
        forecast
    )

    smape = calculate_smape(
        actual,
        forecast
    )

    return {
        "mae": mae,
        "rmse": rmse,
        "mape": mape,
        "smape": smape,
        "forecast": forecast,
        "aic": fitted_model.aic
    }


# ============================================================================
# MAIN EVALUATION
# ============================================================================

def main():

    print("=" * 70)
    print("CRISP-DM PHASE 5 - MODEL EVALUATION")
    print("NICA XANDRA PHARMACY POS")
    print("=" * 70)

    # ------------------------------------------------------------------------
    # LOAD DATA
    # ------------------------------------------------------------------------

    df = load_data()

    candidates = load_candidate_models()

    train, test = create_split(
        df
    )

    train_series = (
        train
        .set_index("Date")[TARGET]
    )

    test_series = (
        test
        .set_index("Date")[TARGET]
    )

    print()
    print("=" * 70)
    print("[4] Evaluating candidate SARIMA models")
    print("=" * 70)

    evaluation_results = []

    detailed_forecasts = []

    total_candidates = len(candidates)

    for index, row in candidates.iterrows():

        model_order = parse_tuple(
            row["model_order"]
        )

        seasonal_order = parse_seasonal_tuple(
            row["seasonal_order"]
        )

        print(
            f"[{index + 1}/{total_candidates}] "
            f"SARIMA{model_order}"
            f"{seasonal_order}"
            " ... ",
            end=""
        )

        try:

            result = evaluate_model(
                train_series,
                test_series,
                model_order,
                seasonal_order
            )

            evaluation_results.append({

                "model_order":
                    str(model_order),

                "seasonal_order":
                    str(seasonal_order),

                "training_aic":
                    float(row["aic"]),

                "evaluation_aic":
                    float(result["aic"]),

                "MAE":
                    result["mae"],

                "RMSE":
                    result["rmse"],

                "MAPE":
                    result["mape"],

                "SMAPE":
                    result["smape"]

            })

            print(
                f"MAE={result['mae']:.4f}, "
                f"RMSE={result['rmse']:.4f}, "
                f"MAPE={result['mape']:.2f}%, "
                f"SMAPE={result['smape']:.2f}%"
            )

        except Exception as error:

            print(
                f"FAILED: {error}"
            )

            evaluation_results.append({

                "model_order":
                    str(model_order),

                "seasonal_order":
                    str(seasonal_order),

                "training_aic":
                    float(row["aic"]),

                "evaluation_aic":
                    np.nan,

                "MAE":
                    np.nan,

                "RMSE":
                    np.nan,

                "MAPE":
                    np.nan,

                "SMAPE":
                    np.nan

            })

    # ------------------------------------------------------------------------
    # RESULTS DATAFRAME
    # ------------------------------------------------------------------------

    evaluation_df = pd.DataFrame(
        evaluation_results
    )

    valid_results = (
        evaluation_df
        .dropna(
            subset=[
                "MAE",
                "RMSE",
                "MAPE",
                "SMAPE"
            ]
        )
        .copy()
    )

    if valid_results.empty:
        raise RuntimeError(
            "No SARIMA model produced valid evaluation results."
        )

    # ------------------------------------------------------------------------
    # MODEL RANKINGS
    # ------------------------------------------------------------------------

    valid_results = (
        valid_results
        .sort_values(
            [
                "RMSE",
                "MAE",
                "SMAPE"
            ],
            ascending=True
        )
        .reset_index(drop=True)
    )

    valid_results.insert(
        0,
        "rank",
        range(
            1,
            len(valid_results) + 1
        )
    )

    # ------------------------------------------------------------------------
    # BEST MODEL
    # ------------------------------------------------------------------------

    best = valid_results.iloc[0]

    best_order = parse_tuple(
        best["model_order"]
    )

    best_seasonal_order = parse_seasonal_tuple(
        best["seasonal_order"]
    )

    # ------------------------------------------------------------------------
    # FINAL BEST MODEL FORECAST
    # ------------------------------------------------------------------------

    print()
    print("=" * 70)
    print("[5] Final evaluation of selected model")
    print("=" * 70)

    print(
        "Selected model based on "
        "out-of-sample forecasting performance:"
    )

    print(
        f"SARIMA{best_order}"
        f"{best_seasonal_order}"
    )

    final_model = SARIMAX(
        train_series,
        order=best_order,
        seasonal_order=best_seasonal_order,
        enforce_stationarity=False,
        enforce_invertibility=False
    )

    final_fitted = final_model.fit(
        disp=False
    )

    final_forecast = (
        final_fitted
        .forecast(
            steps=len(test_series)
        )
    )

    final_forecast = np.asarray(
        final_forecast,
        dtype=float
    )

    forecast_df = pd.DataFrame({

        "Date":
            test["Date"].values,

        "Actual":
            test_series.values,

        "Predicted":
            final_forecast

    })

    forecast_df["Error"] = (
        forecast_df["Actual"]
        - forecast_df["Predicted"]
    )

    forecast_df["Absolute_Error"] = (
        forecast_df["Error"]
        .abs()
    )

    forecast_df["Absolute_Percentage_Error"] = np.where(

        forecast_df["Actual"] != 0,

        (
            forecast_df["Absolute_Error"]
            / forecast_df["Actual"]
        )
        * 100,

        np.nan

    )

    # ------------------------------------------------------------------------
    # SAVE RESULTS
    # ------------------------------------------------------------------------

    print()
    print("=" * 70)
    print("[6] Saving evaluation results")
    print("=" * 70)

    ranking_file = (
        OUTPUT_DIR
        / "sarima_model_evaluation.csv"
    )

    forecast_file = (
        OUTPUT_DIR
        / "best_model_test_forecast.csv"
    )

    evaluation_summary_file = (
        OUTPUT_DIR
        / "evaluation_summary.txt"
    )

    valid_results.to_csv(
        ranking_file,
        index=False
    )

    forecast_df.to_csv(
        forecast_file,
        index=False
    )

    with open(
        evaluation_summary_file,
        "w",
        encoding="utf-8"
    ) as file:

        file.write(
            "CRISP-DM PHASE 5 - MODEL EVALUATION\n"
        )

        file.write(
            "NICA XANDRA PHARMACY POS\n"
        )

        file.write(
            "=" * 60 + "\n\n"
        )

        file.write(
            "Target variable: quantity_sold\n"
        )

        file.write(
            "Seasonal period: 7 days\n"
        )

        file.write(
            f"Training observations: {len(train)}\n"
        )

        file.write(
            f"Testing observations: {len(test)}\n"
        )

        file.write(
            f"Training period: "
            f"{train['Date'].min().date()} "
            f"to "
            f"{train['Date'].max().date()}\n"
        )

        file.write(
            f"Testing period: "
            f"{test['Date'].min().date()} "
            f"to "
            f"{test['Date'].max().date()}\n\n"
        )

        file.write(
            "SELECTED MODEL\n"
        )

        file.write(
            f"SARIMA{best_order}"
            f"{best_seasonal_order}\n\n"
        )

        file.write(
            "EVALUATION METRICS\n"
        )

        file.write(
            f"MAE   : {best['MAE']:.6f}\n"
        )

        file.write(
            f"RMSE  : {best['RMSE']:.6f}\n"
        )

        file.write(
            f"MAPE  : {best['MAPE']:.6f}%\n"
        )

        file.write(
            f"SMAPE : {best['SMAPE']:.6f}%\n\n"
        )

        file.write(
            "MODEL SELECTION NOTE\n"
        )

        file.write(
            "The model was selected using "
            "out-of-sample forecasting performance "
            "on the chronological hold-out test set. "
            "AIC was used during model training and "
            "candidate generation, while MAE, RMSE, "
            "MAPE, and SMAPE were used for final "
            "forecast evaluation.\n"
        )

    # ------------------------------------------------------------------------
    # DISPLAY RESULTS
    # ------------------------------------------------------------------------

    print(
        f"Evaluation ranking saved to:\n"
        f"{ranking_file}"
    )

    print(
        f"Test forecasts saved to:\n"
        f"{forecast_file}"
    )

    print(
        f"Evaluation summary saved to:\n"
        f"{evaluation_summary_file}"
    )

    print()
    print("=" * 70)
    print("BEST MODEL")
    print("=" * 70)

    print(
        f"SARIMA{best_order}"
        f"{best_seasonal_order}"
    )

    print()
    print(
        f"MAE   : {best['MAE']:.4f}"
    )

    print(
        f"RMSE  : {best['RMSE']:.4f}"
    )

    print(
        f"MAPE  : {best['MAPE']:.2f}%"
    )

    print(
        f"SMAPE : {best['SMAPE']:.2f}%"
    )

    print()
    print("=" * 70)
    print("TOP 10 MODELS BY OUT-OF-SAMPLE PERFORMANCE")
    print("=" * 70)

    print(
        valid_results[
            [
                "rank",
                "model_order",
                "seasonal_order",
                "MAE",
                "RMSE",
                "MAPE",
                "SMAPE",
                "training_aic"
            ]
        ]
        .head(10)
        .to_string(index=False)
    )

    print()
    print("=" * 70)
    print("CRISP-DM PHASE 5 - MODEL EVALUATION COMPLETE")
    print("=" * 70)

    print()
    print(
        "The final model was selected using "
        "out-of-sample forecasting performance."
    )

    print()
    print(
        "Next CRISP-DM phase:"
    )

    print(
        "DEPLOYMENT / FORECASTING INTEGRATION"
    )


# ============================================================================
# ENTRY POINT
# ============================================================================

if __name__ == "__main__":
    main()