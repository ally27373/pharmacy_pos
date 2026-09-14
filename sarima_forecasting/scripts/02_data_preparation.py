from pathlib import Path
import pandas as pd


# ============================================================
# CRISP-DM PHASE 3
# DATA PREPARATION
# NICA XANDRA PHARMACY POS
# ============================================================


# ------------------------------------------------------------
# PROJECT PATHS
# ------------------------------------------------------------

BASE_DIR = Path(__file__).resolve().parent.parent

DATA_FILE = BASE_DIR / "nica_xandra_sales_2025.csv"

PREPARED_DIR = BASE_DIR / "data" / "prepared"


# ------------------------------------------------------------
# MODELING ELIGIBILITY
# ------------------------------------------------------------

MIN_PRODUCT_RECORDS = 30


# ------------------------------------------------------------
# REQUIRED COLUMNS
# ------------------------------------------------------------

REQUIRED_COLUMNS = [
    "Date",
    "Category",
    "Description",
    "Quantity",
    "UnitPrice",
    "TotalPrice",
    "TaxType"
]


# ============================================================
# HELPER FUNCTIONS
# ============================================================

def clean_product_description(series: pd.Series) -> pd.Series:
    """
    Normalize product descriptions without changing
    the original historical CSV.

    Operations:
    - Convert to string
    - Replace tabs/newlines with spaces
    - Remove leading/trailing whitespace
    - Collapse repeated whitespace
    """

    return (
        series.astype("string")
        .str.replace(r"[\t\r\n]+", " ", regex=True)
        .str.replace(r"\s+", " ", regex=True)
        .str.strip()
    )


def validate_required_columns(df: pd.DataFrame) -> None:

    missing_columns = [
        column
        for column in REQUIRED_COLUMNS
        if column not in df.columns
    ]

    if missing_columns:

        raise ValueError(
            "Missing required columns:\n"
            + "\n".join(f"- {column}" for column in missing_columns)
        )


# ============================================================
# MAIN
# ============================================================

def main():

    print("=" * 70)
    print("CRISP-DM PHASE 3 - DATA PREPARATION")
    print("NICA XANDRA PHARMACY POS")
    print("=" * 70)


    # --------------------------------------------------------
    # 1. LOAD DATA
    # --------------------------------------------------------

    print("\n[1] Loading historical sales dataset...")

    if not DATA_FILE.exists():

        raise FileNotFoundError(
            f"Dataset not found:\n{DATA_FILE}"
        )

    df = pd.read_csv(DATA_FILE)

    print(
        f"Dataset loaded successfully: "
        f"{len(df):,} records."
    )


    # --------------------------------------------------------
    # 2. VALIDATE COLUMNS
    # --------------------------------------------------------

    print("\n[2] Validating required columns...")

    validate_required_columns(df)

    print("All required columns are present.")


    # --------------------------------------------------------
    # 3. CREATE WORKING COPY
    # --------------------------------------------------------

    # IMPORTANT:
    # The original CSV is never modified.

    prepared = df.copy()


    # --------------------------------------------------------
    # 4. DATE PREPARATION
    # --------------------------------------------------------

    print("\n[3] Preparing date field...")

    prepared["Date"] = pd.to_datetime(
        prepared["Date"],
        errors="coerce"
    )

    invalid_dates = prepared["Date"].isna().sum()

    print(
        f"Invalid/missing dates after conversion: "
        f"{invalid_dates}"
    )

    if invalid_dates > 0:

        raise ValueError(
            "Date preparation failed because "
            "invalid/missing dates were detected."
        )


    # --------------------------------------------------------
    # 5. PRODUCT DESCRIPTION CLEANING
    # --------------------------------------------------------

    print("\n[4] Cleaning product descriptions...")

    before_unique = prepared["Description"].nunique()

    prepared["Description"] = clean_product_description(
        prepared["Description"]
    )

    after_unique = prepared["Description"].nunique()

    print(
        f"Unique descriptions before cleaning: "
        f"{before_unique:,}"
    )

    print(
        f"Unique descriptions after cleaning: "
        f"{after_unique:,}"
    )


    # --------------------------------------------------------
    # 6. NUMERIC VALIDATION
    # --------------------------------------------------------

    print("\n[5] Validating numerical fields...")

    numeric_columns = [
        "Quantity",
        "UnitPrice",
        "TotalPrice"
    ]

    for column in numeric_columns:

        prepared[column] = pd.to_numeric(
            prepared[column],
            errors="coerce"
        )

    for column in numeric_columns:

        invalid_count = prepared[column].isna().sum()

        print(
            f"{column} invalid/missing values: "
            f"{invalid_count}"
        )

        if invalid_count > 0:

            raise ValueError(
                f"Invalid/missing values detected "
                f"in {column}."
            )


    # --------------------------------------------------------
    # 7. QUANTITY VALIDATION
    # --------------------------------------------------------

    negative_quantity = (
        prepared["Quantity"] < 0
    ).sum()

    zero_quantity = (
        prepared["Quantity"] == 0
    ).sum()

    print(
        f"Negative quantity records: "
        f"{negative_quantity}"
    )

    print(
        f"Zero quantity records: "
        f"{zero_quantity}"
    )

    if negative_quantity > 0:

        raise ValueError(
            "Negative quantity records detected. "
            "Review the historical data before modeling."
        )


    # --------------------------------------------------------
    # 8. SALES VALUE VALIDATION
    # --------------------------------------------------------

    negative_sales = (
        prepared["TotalPrice"] < 0
    ).sum()

    print(
        f"Negative sales records: "
        f"{negative_sales}"
    )

    if negative_sales > 0:

        raise ValueError(
            "Negative sales values detected."
        )


    # --------------------------------------------------------
    # 9. PREPARED DATE RANGE
    # --------------------------------------------------------

    start_date = prepared["Date"].min()
    end_date = prepared["Date"].max()

    print("\n[6] Historical period")

    print(
        f"Start date: "
        f"{start_date.date()}"
    )

    print(
        f"End date: "
        f"{end_date.date()}"
    )


    # ========================================================
    # 10. DAILY OVERALL SALES
    # ========================================================

    print("\n[7] Creating daily overall sales dataset...")


    daily_sales = (
        prepared
        .groupby("Date")
        .agg(
            quantity_sold=("Quantity", "sum"),
            sales_amount=("TotalPrice", "sum"),
            sales_records=("Description", "size")
        )
        .reset_index()
        .sort_values("Date")
    )


    # --------------------------------------------------------
    # CREATE COMPLETE CALENDAR
    # --------------------------------------------------------

    complete_dates = pd.date_range(
        start=start_date,
        end=end_date,
        freq="D"
    )

    daily_sales = (
        daily_sales
        .set_index("Date")
        .reindex(complete_dates)
        .rename_axis("Date")
        .reset_index()
    )


    # Days without transactions become zero.
    daily_sales["quantity_sold"] = (
        daily_sales["quantity_sold"]
        .fillna(0)
    )

    daily_sales["sales_amount"] = (
        daily_sales["sales_amount"]
        .fillna(0)
    )

    daily_sales["sales_records"] = (
        daily_sales["sales_records"]
        .fillna(0)
    )


    print(
        f"Daily sales rows: "
        f"{len(daily_sales):,}"
    )

    print(
        f"Days without sales records after "
        f"calendar completion: "
        f"{(daily_sales['sales_records'] == 0).sum():,}"
    )


    # ========================================================
    # 11. PRODUCT SUMMARY
    # ========================================================

    print("\n[8] Creating product summary...")


    product_summary = (
        prepared
        .groupby("Description")
        .agg(
            sales_records=("Description", "size"),
            active_days=("Date", "nunique"),
            total_quantity_sold=("Quantity", "sum"),
            total_sales=("TotalPrice", "sum"),
            average_unit_price=("UnitPrice", "mean"),
            category_count=("Category", "nunique")
        )
        .reset_index()
        .rename(
            columns={
                "Description": "product_name"
            }
        )
    )


    product_summary["eligible_for_product_sarima"] = (
        product_summary["sales_records"]
        >= MIN_PRODUCT_RECORDS
    )


    product_summary = product_summary.sort_values(
        by="sales_records",
        ascending=False
    )


    eligible_products = (
        product_summary[
            "eligible_for_product_sarima"
        ]
        .sum()
    )

    total_products = len(product_summary)

    excluded_products = (
        total_products - eligible_products
    )


    print(
        f"Total products: "
        f"{total_products:,}"
    )

    print(
        f"Eligible products "
        f"(>= {MIN_PRODUCT_RECORDS} records): "
        f"{eligible_products:,}"
    )

    print(
        f"Products below eligibility threshold: "
        f"{excluded_products:,}"
    )


    # ========================================================
    # 12. PRODUCT DAILY DEMAND
    # ========================================================

    print("\n[9] Creating daily product demand dataset...")


    # Only products with sufficient historical records
    # are prepared for individual SARIMA modeling.

    eligible_product_names = set(
        product_summary.loc[
            product_summary[
                "eligible_for_product_sarima"
            ],
            "product_name"
        ]
    )


    eligible_transactions = prepared[
        prepared["Description"].isin(
            eligible_product_names
        )
    ].copy()


    # Aggregate transactions occurring on the
    # same date for the same product.

    product_daily = (
        eligible_transactions
        .groupby(
            ["Date", "Description"]
        )
        .agg(
            quantity_sold=("Quantity", "sum"),
            sales_amount=("TotalPrice", "sum"),
            sales_records=("Description", "size")
        )
        .reset_index()
    )


    # --------------------------------------------------------
    # COMPLETE DATE × PRODUCT GRID
    # --------------------------------------------------------

    product_dates = pd.date_range(
        start=start_date,
        end=end_date,
        freq="D"
    )

    product_index = pd.MultiIndex.from_product(
        [
            product_dates,
            sorted(eligible_product_names)
        ],
        names=["Date", "product_name"]
    )


    product_daily = (
        product_daily
        .rename(
            columns={
                "Description": "product_name"
            }
        )
        .set_index(
            ["Date", "product_name"]
        )
        .reindex(product_index)
        .reset_index()
    )


    # No recorded sale for an eligible product on a day
    # is represented as zero observed sales quantity.

    product_daily["quantity_sold"] = (
        product_daily["quantity_sold"]
        .fillna(0)
    )

    product_daily["sales_amount"] = (
        product_daily["sales_amount"]
        .fillna(0)
    )

    product_daily["sales_records"] = (
        product_daily["sales_records"]
        .fillna(0)
    )


    product_daily = product_daily.sort_values(
        [
            "product_name",
            "Date"
        ]
    )


    print(
        f"Eligible product count: "
        f"{len(eligible_product_names):,}"
    )

    print(
        f"Daily product-demand rows: "
        f"{len(product_daily):,}"
    )


    # ========================================================
    # 13. CREATE OUTPUT DIRECTORY
    # ========================================================

    print("\n[10] Creating prepared-data directory...")

    PREPARED_DIR.mkdir(
        parents=True,
        exist_ok=True
    )

    print(
        f"Output directory:\n"
        f"{PREPARED_DIR}"
    )


    # ========================================================
    # 14. SAVE PREPARED DATASETS
    # ========================================================

    print("\n[11] Saving prepared datasets...")


    daily_sales_file = (
        PREPARED_DIR / "daily_sales.csv"
    )

    product_daily_file = (
        PREPARED_DIR / "daily_product_demand.csv"
    )

    product_summary_file = (
        PREPARED_DIR / "product_summary.csv"
    )


    daily_sales.to_csv(
        daily_sales_file,
        index=False
    )

    product_daily.to_csv(
        product_daily_file,
        index=False
    )

    product_summary.to_csv(
        product_summary_file,
        index=False
    )


    print(
        f"Saved:\n"
        f"- {daily_sales_file.name}\n"
        f"- {product_daily_file.name}\n"
        f"- {product_summary_file.name}"
    )


    # ========================================================
    # 15. FINAL VALIDATION
    # ========================================================

    print("\n[12] Final preparation validation...")


    daily_sales_dates = (
        pd.to_datetime(
            daily_sales["Date"]
        )
    )


    expected_days = (
        end_date - start_date
    ).days + 1


    if len(daily_sales) != expected_days:

        raise ValueError(
            "Daily sales calendar validation failed."
        )


    if product_daily.empty:

        raise ValueError(
            "Product daily demand dataset is empty."
        )


    if product_daily["quantity_sold"].isna().any():

        raise ValueError(
            "Product demand dataset contains "
            "missing quantities."
        )


    print("Daily sales calendar: VALID")
    print("Product demand dataset: VALID")
    print("Prepared datasets: VALID")


    # ========================================================
    # COMPLETE
    # ========================================================

    print("\n" + "=" * 70)
    print("DATA PREPARATION COMPLETE")
    print("=" * 70)

    print("\nPrepared datasets:")

    print(
        f"1. Daily sales: "
        f"{len(daily_sales):,} rows"
    )

    print(
        f"2. Daily product demand: "
        f"{len(product_daily):,} rows"
    )

    print(
        f"3. Product summary: "
        f"{len(product_summary):,} products"
    )

    print("\nProduct SARIMA eligibility:")

    print(
        f"- Eligible: "
        f"{eligible_products:,}"
    )

    print(
        f"- Below threshold: "
        f"{excluded_products:,}"
    )

    print("\nOriginal historical CSV was not modified.")

    print("\nNext CRISP-DM phase:")
    print("MODEL DATA EXPLORATION / MODELING")


# ============================================================
# SCRIPT ENTRY POINT
# ============================================================

if __name__ == "__main__":
    main()