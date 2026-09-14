from pathlib import Path

import warnings

import numpy as np
import pandas as pd

import matplotlib.pyplot as plt

from statsmodels.tsa.stattools import adfuller
from statsmodels.tsa.seasonal import seasonal_decompose
from statsmodels.graphics.tsaplots import plot_acf, plot_pacf


warnings.filterwarnings("ignore")


# ============================================================
# CRISP-DM PHASE 4
# MODEL DATA EXPLORATION
# ============================================================

BASE_DIR = Path(__file__).resolve().parent.parent

PREPARED_DIR = BASE_DIR / "data" / "prepared"

OUTPUT_DIR = BASE_DIR / "outputs" / "exploration"

OUTPUT_DIR.mkdir(
    parents=True,
    exist_ok=True
)


DAILY_SALES_FILE = (
    PREPARED_DIR / "daily_sales.csv"
)

PRODUCT_DEMAND_FILE = (
    PREPARED_DIR / "daily_product_demand.csv"
)


# ============================================================
# HELPER FUNCTIONS
# ============================================================

def find_column(df, candidates):

    normalized = {
        column.lower().replace(" ", "_"): column
        for column in df.columns
    }

    for candidate in candidates:

        key = candidate.lower().replace(" ", "_")

        if key in normalized:
            return normalized[key]

    return None


def run_adf_test(series, name):

    series = series.dropna()

    result = adfuller(
        series,
        autolag="AIC"
    )

    statistic = result[0]
    p_value = result[1]

    print()
    print("=" * 70)
    print(f"ADF STATIONARITY TEST: {name}")
    print("=" * 70)

    print(
        f"ADF Statistic: {statistic:.6f}"
    )

    print(
        f"p-value: {p_value:.6f}"
    )

    print(
        f"Used observations: {result[3]}"
    )

    print()

    if p_value < 0.05:

        print(
            "Conclusion: The series is likely stationary."
        )

        return True

    else:

        print(
            "Conclusion: The series is likely non-stationary."
        )

        return False


# ============================================================
# MAIN
# ============================================================

def main():

    print("=" * 70)
    print("CRISP-DM PHASE 4 - MODEL DATA EXPLORATION")
    print("NICA XANDRA PHARMACY POS")
    print("=" * 70)


    # ========================================================
    # 1. LOAD DAILY SALES
    # ========================================================

    print()
    print("[1] Loading prepared daily sales dataset...")

    if not DAILY_SALES_FILE.exists():

        raise FileNotFoundError(
            f"Daily sales dataset not found:\n"
            f"{DAILY_SALES_FILE}"
        )

    daily_sales = pd.read_csv(
        DAILY_SALES_FILE
    )

    print(
        f"Dataset loaded successfully: "
        f"{len(daily_sales):,} rows."
    )

    print()
    print("Columns:")
    print(
        daily_sales.columns.tolist()
    )


    # ========================================================
    # 2. IDENTIFY DATE COLUMN
    # ========================================================

    date_column = find_column(
        daily_sales,
        [
            "date",
            "sales_date"
        ]
    )

    if date_column is None:

        raise ValueError(
            "Unable to identify the date column."
        )

    daily_sales[date_column] = pd.to_datetime(
        daily_sales[date_column],
        errors="coerce"
    )


    # ========================================================
    # 3. IDENTIFY SALES TARGET
    # ========================================================

    quantity_column = find_column(
        daily_sales,
        [
            "quantity_sold",
            "quantity",
            "daily_quantity"
        ]
    )

    sales_column = find_column(
        daily_sales,
        [
            "sales_amount",
            "total_sales",
            "daily_sales"
        ]
    )


    if quantity_column is None:

        raise ValueError(
            "Unable to identify quantity column."
        )


    print()
    print("=" * 70)
    print("TARGET VARIABLES")
    print("=" * 70)

    print(
        f"Quantity target: {quantity_column}"
    )

    if sales_column:

        print(
            f"Sales-value target: {sales_column}"
        )


    # ========================================================
    # 4. CREATE TIME SERIES
    # ========================================================

    daily_sales = (
        daily_sales
        .sort_values(date_column)
        .set_index(date_column)
    )


    quantity_series = (
        daily_sales[quantity_column]
        .astype(float)
    )


    if sales_column:

        sales_series = (
            daily_sales[sales_column]
            .astype(float)
        )

    else:

        sales_series = None


    print()
    print("=" * 70)
    print("TIME SERIES INFORMATION")
    print("=" * 70)

    print(
        f"Start date: {quantity_series.index.min().date()}"
    )

    print(
        f"End date: {quantity_series.index.max().date()}"
    )

    print(
        f"Observations: {len(quantity_series):,}"
    )


    # ========================================================
    # 5. DESCRIPTIVE STATISTICS
    # ========================================================

    print()
    print("=" * 70)
    print("DESCRIPTIVE STATISTICS - DAILY QUANTITY")
    print("=" * 70)

    print(
        quantity_series.describe()
    )


    if sales_series is not None:

        print()
        print("=" * 70)
        print("DESCRIPTIVE STATISTICS - DAILY SALES")
        print("=" * 70)

        print(
            sales_series.describe()
        )


    # ========================================================
    # 6. WEEKLY SEASONALITY
    # ========================================================

    print()
    print("=" * 70)
    print("WEEKLY SEASONALITY ANALYSIS")
    print("=" * 70)

    quantity_by_day = (
        quantity_series
        .groupby(
            quantity_series.index.dayofweek
        )
        .mean()
    )

    day_names = [
        "Monday",
        "Tuesday",
        "Wednesday",
        "Thursday",
        "Friday",
        "Saturday",
        "Sunday"
    ]

    for day_number, value in quantity_by_day.items():

        print(
            f"{day_names[day_number]:<10}: "
            f"{value:.2f}"
        )


    # ========================================================
    # 7. SAVE WEEKLY SEASONALITY
    # ========================================================

    weekly_output = pd.DataFrame({

        "day_of_week":
            range(7),

        "day_name":
            day_names,

        "average_quantity_sold":
            [
                quantity_by_day.get(
                    i,
                    np.nan
                )
                for i in range(7)
            ]

    })

    weekly_output.to_csv(
        OUTPUT_DIR / "weekly_seasonality.csv",
        index=False
    )


    # ========================================================
    # 8. ADF TEST - ORIGINAL SERIES
    # ========================================================

    stationary_original = run_adf_test(
        quantity_series,
        "Daily Quantity Sold - Original"
    )


    # ========================================================
    # 9. FIRST DIFFERENCING
    # ========================================================

    quantity_diff = (
        quantity_series
        .diff()
        .dropna()
    )


    stationary_diff = run_adf_test(
        quantity_diff,
        "Daily Quantity Sold - First Difference"
    )


    # ========================================================
    # 10. SEASONAL DIFFERENCING
    # ========================================================

    quantity_seasonal_diff = (
        quantity_series
        .diff(7)
        .dropna()
    )


    stationary_seasonal_diff = run_adf_test(
        quantity_seasonal_diff,
        "Daily Quantity Sold - Seasonal Difference (7)"
    )


    # ========================================================
    # 11. DIFFERENCING DECISION
    # ========================================================

    print()
    print("=" * 70)
    print("STATIONARITY DECISION")
    print("=" * 70)

    print(
        f"Original stationary: "
        f"{stationary_original}"
    )

    print(
        f"First difference stationary: "
        f"{stationary_diff}"
    )

    print(
        f"Seasonal difference (7) stationary: "
        f"{stationary_seasonal_diff}"
    )


    if stationary_original:

        recommended_d = 0

    else:

        recommended_d = 1


    print()
    print(
        f"Initial recommended d value: "
        f"{recommended_d}"
    )

    print(
        "Initial seasonal period (m): 7"
    )


    # ========================================================
    # 12. TIME SERIES PLOT
    # ========================================================

    print()
    print("[12] Generating time-series plot...")

    plt.figure(
        figsize=(12, 6)
    )

    plt.plot(
        quantity_series.index,
        quantity_series.values
    )

    plt.title(
        "Daily Quantity Sold - Historical Time Series"
    )

    plt.xlabel("Date")

    plt.ylabel(
        "Quantity Sold"
    )

    plt.tight_layout()

    plt.savefig(
        OUTPUT_DIR /
        "daily_quantity_time_series.png",
        dpi=150
    )

    plt.close()


    # ========================================================
    # 13. SEASONAL DECOMPOSITION
    # ========================================================

    print(
        "[13] Performing seasonal decomposition..."
    )

    decomposition = seasonal_decompose(
        quantity_series,
        model="additive",
        period=7
    )

    decomposition.plot()

    plt.tight_layout()

    plt.savefig(
        OUTPUT_DIR /
        "daily_quantity_decomposition.png",
        dpi=150
    )

    plt.close()


    # ========================================================
    # 14. ACF
    # ========================================================

    print(
        "[14] Generating ACF plot..."
    )

    plt.figure(
        figsize=(12, 6)
    )

    plot_acf(
        quantity_series,
        lags=30
    )

    plt.tight_layout()

    plt.savefig(
        OUTPUT_DIR /
        "daily_quantity_acf.png",
        dpi=150
    )

    plt.close()


    # ========================================================
    # 15. PACF
    # ========================================================

    print(
        "[15] Generating PACF plot..."
    )

    plt.figure(
        figsize=(12, 6)
    )

    plot_pacf(
        quantity_series,
        lags=30,
        method="ywm"
    )

    plt.tight_layout()

    plt.savefig(
        OUTPUT_DIR /
        "daily_quantity_pacf.png",
        dpi=150
    )

    plt.close()


    # ========================================================
    # 16. SAVE EXPLORATION SUMMARY
    # ========================================================

    summary = {

        "historical_start":
            str(quantity_series.index.min().date()),

        "historical_end":
            str(quantity_series.index.max().date()),

        "observations":
            len(quantity_series),

        "original_stationary":
            stationary_original,

        "first_difference_stationary":
            stationary_diff,

        "seasonal_difference_stationary":
            stationary_seasonal_diff,

        "recommended_d":
            recommended_d,

        "initial_seasonal_period":
            7

    }


    pd.DataFrame(
        [summary]
    ).to_csv(
        OUTPUT_DIR /
        "model_exploration_summary.csv",
        index=False
    )


    # ========================================================
    # COMPLETE
    # ========================================================

    print()
    print("=" * 70)
    print("MODEL DATA EXPLORATION COMPLETE")
    print("=" * 70)

    print()
    print(
        "Exploration outputs saved to:"
    )

    print(
        OUTPUT_DIR
    )

    print()
    print(
        "Next step:"
    )

    print(
        "SARIMA MODEL CONFIGURATION AND TRAINING"
    )


if __name__ == "__main__":

    main()