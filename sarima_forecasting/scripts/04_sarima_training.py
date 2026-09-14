"""
CRISP-DM PHASE 4 - SARIMA MODEL TRAINING

NICA XANDRA PHARMACY POS

Purpose:
    Train and compare a controlled set of SARIMA models
    using the prepared daily sales quantity dataset.

Important:
    - Historical data is kept chronological.
    - The test period is never used during model fitting.
    - AIC is used for preliminary model comparison.
    - MAE, RMSE, MAPE, and SMAPE will be handled in Phase 5.
"""

from pathlib import Path
from itertools import product
import time
import warnings

import pandas as pd
from statsmodels.tsa.statespace.sarimax import SARIMAX


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

OUTPUT_DIR = (
    BASE_DIR
    / "outputs"
    / "modeling"
)

RESULT_FILE = (
    OUTPUT_DIR
    / "sarima_candidate_results.csv"
)


# ============================================================================
# MODEL CONFIGURATION
# ============================================================================

TARGET_COLUMN = "quantity_sold"

DATE_COLUMN = "Date"

# Daily pharmacy demand commonly contains weekly behavior.
SEASONAL_PERIOD = 7

# ---------------------------------------------------------------------------
# Chronological train/test split
# ---------------------------------------------------------------------------
#
# We reserve the final 30 days as unseen testing data.
#
# Training:
#     January 1, 2025 -> December 1, 2025
#
# Testing:
#     December 2, 2025 -> December 31, 2025
#
# The exact split is calculated from the dataset rather than hard-coded.
# ---------------------------------------------------------------------------

TEST_DAYS = 30


# ============================================================================
# CONTROLLED SARIMA SEARCH SPACE
# ============================================================================

# Non-seasonal AR order
P_VALUES = [0, 1, 2]

# Differencing
D_VALUES = [0]

# Non-seasonal MA order
Q_VALUES = [0, 1, 2]

# Seasonal AR order
SEASONAL_P_VALUES = [0, 1]

# Seasonal differencing
SEASONAL_D_VALUES = [0, 1]

# Seasonal MA order
SEASONAL_Q_VALUES = [0, 1]


# ============================================================================
# HELPER FUNCTIONS
# ============================================================================

def print_separator():
    print("=" * 70)


def load_data():
    """
    Load and validate the prepared daily sales dataset.
    """

    print("[1] Loading prepared daily sales dataset...")

    if not DATA_FILE.exists():

        raise FileNotFoundError(
            f"Prepared dataset not found:\n{DATA_FILE}"
        )

    df = pd.read_csv(DATA_FILE)

    required_columns = [
        DATE_COLUMN,
        TARGET_COLUMN
    ]

    missing_columns = [
        column
        for column in required_columns
        if column not in df.columns
    ]

    if missing_columns:

        raise ValueError(
            "Required columns are missing:\n"
            + ", ".join(missing_columns)
        )

    df[DATE_COLUMN] = pd.to_datetime(
        df[DATE_COLUMN],
        errors="coerce"
    )

    df[TARGET_COLUMN] = pd.to_numeric(
        df[TARGET_COLUMN],
        errors="coerce"
    )

    df = df.dropna(
        subset=[
            DATE_COLUMN,
            TARGET_COLUMN
        ]
    )

    df = df.sort_values(
        DATE_COLUMN
    )

    df = df.set_index(
        DATE_COLUMN
    )

    return df


def create_train_test_split(df):
    """
    Create chronological training and testing datasets.
    """

    print()
    print_separator()
    print("[2] Creating chronological train/test split")
    print_separator()

    if len(df) <= TEST_DAYS:

        raise ValueError(
            "Dataset does not contain enough observations "
            "for the selected test period."
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

    print(
        "Training period:"
    )

    print(
        f"  {train.index.min().date()} "
        f"to "
        f"{train.index.max().date()}"
    )

    print()

    print(
        "Testing period:"
    )

    print(
        f"  {test.index.min().date()} "
        f"to "
        f"{test.index.max().date()}"
    )

    return train[TARGET_COLUMN], test[TARGET_COLUMN]


def generate_model_configurations():
    """
    Generate the controlled SARIMA candidate configurations.
    """

    configurations = []

    for (
        p,
        d,
        q,
        P,
        D,
        Q
    ) in product(
        P_VALUES,
        D_VALUES,
        Q_VALUES,
        SEASONAL_P_VALUES,
        SEASONAL_D_VALUES,
        SEASONAL_Q_VALUES
    ):

        order = (
            p,
            d,
            q
        )

        seasonal_order = (
            P,
            D,
            Q,
            SEASONAL_PERIOD
        )

        configurations.append(
            (
                order,
                seasonal_order
            )
        )

    return configurations


def train_model(
    train_series,
    order,
    seasonal_order
):
    """
    Train one SARIMA candidate.
    """

    with warnings.catch_warnings():

        warnings.filterwarnings(
            "ignore"
        )

        model = SARIMAX(
            train_series,

            order=order,

            seasonal_order=seasonal_order,

            enforce_stationarity=False,

            enforce_invertibility=False
        )

        fitted_model = model.fit(
            disp=False
        )

    return fitted_model


# ============================================================================
# MAIN
# ============================================================================

def main():

    print_separator()

    print(
        "CRISP-DM PHASE 4 - SARIMA MODEL TRAINING"
    )

    print(
        "NICA XANDRA PHARMACY POS"
    )

    print_separator()

    # ------------------------------------------------------------------------
    # Load data
    # ------------------------------------------------------------------------

    df = load_data()

    print(
        f"Dataset loaded successfully: {len(df):,} observations."
    )

    print()

    print(
        f"Target variable: {TARGET_COLUMN}"
    )

    print(
        f"Seasonal period: {SEASONAL_PERIOD} days"
    )

    # ------------------------------------------------------------------------
    # Train/test split
    # ------------------------------------------------------------------------

    train_series, test_series = create_train_test_split(
        df
    )

    # ------------------------------------------------------------------------
    # Generate candidate models
    # ------------------------------------------------------------------------

    print()
    print_separator()
    print("[3] Generating SARIMA candidate configurations")
    print_separator()

    configurations = (
        generate_model_configurations()
    )

    print(
        f"Candidate configurations: "
        f"{len(configurations)}"
    )

    print()

    print(
        "Model structure:"
    )

    print(
        "SARIMA(p,d,q)(P,D,Q,7)"
    )

    print()

    print(
        f"p values : {P_VALUES}"
    )

    print(
        f"d values : {D_VALUES}"
    )

    print(
        f"q values : {Q_VALUES}"
    )

    print(
        f"P values : {SEASONAL_P_VALUES}"
    )

    print(
        f"D values : {SEASONAL_D_VALUES}"
    )

    print(
        f"Q values : {SEASONAL_Q_VALUES}"
    )

    # ------------------------------------------------------------------------
    # Create output directory
    # ------------------------------------------------------------------------

    OUTPUT_DIR.mkdir(
        parents=True,
        exist_ok=True
    )

    # ------------------------------------------------------------------------
    # Train candidates
    # ------------------------------------------------------------------------

    print()
    print_separator()
    print("[4] Training SARIMA candidates")
    print_separator()

    results = []

    total_models = len(configurations)

    for index, (
        order,
        seasonal_order
    ) in enumerate(
        configurations,
        start=1
    ):

        print(
            f"[{index}/{total_models}] "
            f"SARIMA{order}"
            f"{seasonal_order}",
            end=" ... "
        )

        start_time = time.perf_counter()

        try:

            fitted_model = train_model(
                train_series,
                order,
                seasonal_order
            )

            elapsed_time = (
                time.perf_counter()
                - start_time
            )

            aic = fitted_model.aic

            if pd.isna(aic):

                status = "invalid"

                print(
                    f"INVALID "
                    f"({elapsed_time:.2f}s)"
                )

            else:

                status = "success"

                print(
                    f"AIC={aic:.2f} "
                    f"({elapsed_time:.2f}s)"
                )

            results.append({

                "model_order":
                    str(order),

                "seasonal_order":
                    str(seasonal_order),

                "p": order[0],

                "d": order[1],

                "q": order[2],

                "P": seasonal_order[0],

                "D": seasonal_order[1],

                "Q": seasonal_order[2],

                "seasonal_period":
                    seasonal_order[3],

                "aic":
                    aic,

                "training_seconds":
                    round(
                        elapsed_time,
                        4
                    ),

                "status":
                    status

            })

        except Exception as e:

            elapsed_time = (
                time.perf_counter()
                - start_time
            )

            print(
                f"FAILED "
                f"({elapsed_time:.2f}s)"
            )

            results.append({

                "model_order":
                    str(order),

                "seasonal_order":
                    str(seasonal_order),

                "p": order[0],

                "d": order[1],

                "q": order[2],

                "P": seasonal_order[0],

                "D": seasonal_order[1],

                "Q": seasonal_order[2],

                "seasonal_period":
                    seasonal_order[3],

                "aic":
                    None,

                "training_seconds":
                    round(
                        elapsed_time,
                        4
                    ),

                "status":
                    f"failed: {str(e)[:200]}"

            })

    # ------------------------------------------------------------------------
    # Results dataframe
    # ------------------------------------------------------------------------

    print()
    print_separator()
    print("[5] Preparing candidate-model results")
    print_separator()

    results_df = pd.DataFrame(
        results
    )

    results_df = results_df.sort_values(
        by="aic",
        na_position="last"
    )

    results_df = results_df.reset_index(
        drop=True
    )

    # ------------------------------------------------------------------------
    # Save results
    # ------------------------------------------------------------------------

    results_df.to_csv(
        RESULT_FILE,
        index=False
    )

    print(
        f"Saved candidate results to:"
    )

    print(
        RESULT_FILE
    )

    # ------------------------------------------------------------------------
    # Display best candidates
    # ------------------------------------------------------------------------

    successful_models = (
        results_df[
            results_df["status"] == "success"
        ]
        .dropna(
            subset=["aic"]
        )
    )

    print()
    print_separator()
    print("[6] BEST SARIMA CANDIDATES BY AIC")
    print_separator()

    if successful_models.empty:

        print(
            "No successful SARIMA models were produced."
        )

        print(
            "Review the error information in the "
            "candidate results file."
        )

    else:

        display_columns = [
            "model_order",
            "seasonal_order",
            "aic",
            "training_seconds"
        ]

        print(
            successful_models[
                display_columns
            ]
            .head(10)
            .to_string(
                index=False
            )
        )

    # ------------------------------------------------------------------------
    # Final summary
    # ------------------------------------------------------------------------

    print()
    print_separator()

    print(
        "SARIMA MODEL TRAINING COMPLETE"
    )

    print_separator()

    print(
        f"Total candidates tested: "
        f"{total_models}"
    )

    print(
        f"Successful models: "
        f"{len(successful_models)}"
    )

    print(
        f"Failed/invalid models: "
        f"{total_models - len(successful_models)}"
    )

    print()

    print(
        "Training data was kept separate from "
        "the final 30-day testing period."
    )

    print()

    print(
        "Candidate results saved for the next phase."
    )

    print()

    print(
        "Next CRISP-DM phase:"
    )

    print(
        "MODEL EVALUATION"
    )


if __name__ == "__main__":
    main()