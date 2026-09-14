from pathlib import Path
import pandas as pd


# ============================================================
# CRISP-DM PHASE 2
# DATA UNDERSTANDING
# ============================================================

BASE_DIR = Path(__file__).resolve().parent.parent

DATA_FILE = BASE_DIR / "nica_xandra_sales_2025.csv"


def main():

    print("=" * 70)
    print("CRISP-DM PHASE 2 - DATA UNDERSTANDING")
    print("NICA XANDRA PHARMACY POS")
    print("=" * 70)

    print("\n[1] Loading historical sales dataset...")

    if not DATA_FILE.exists():
        raise FileNotFoundError(
            f"Dataset not found:\n{DATA_FILE}"
        )

    df = pd.read_csv(DATA_FILE)

    print("Dataset loaded successfully.")

    # --------------------------------------------------------
    # BASIC DATASET INFORMATION
    # --------------------------------------------------------

    print("\n" + "=" * 70)
    print("[2] DATASET OVERVIEW")
    print("=" * 70)

    print(f"Rows: {len(df):,}")
    print(f"Columns: {len(df.columns)}")

    print("\nColumns:")

    for column in df.columns:
        print(f"  - {column}")

    # --------------------------------------------------------
    # DATA TYPES
    # --------------------------------------------------------

    print("\n" + "=" * 70)
    print("[3] DATA TYPES")
    print("=" * 70)

    print(df.dtypes)

    # --------------------------------------------------------
    # DATE CONVERSION
    # --------------------------------------------------------

    print("\n" + "=" * 70)
    print("[4] DATE ANALYSIS")
    print("=" * 70)

    df["Date"] = pd.to_datetime(
        df["Date"],
        errors="coerce"
    )

    invalid_dates = df["Date"].isna().sum()

    print(f"Invalid/missing dates: {invalid_dates:,}")

    if df["Date"].notna().any():

        print(
            "Earliest date:",
            df["Date"].min()
        )

        print(
            "Latest date:",
            df["Date"].max()
        )

        print(
            "Number of calendar days:",
            df["Date"].dt.date.nunique()
        )

    # --------------------------------------------------------
    # MISSING VALUES
    # --------------------------------------------------------

    print("\n" + "=" * 70)
    print("[5] MISSING VALUES")
    print("=" * 70)

    missing = df.isna().sum()

    for column, count in missing.items():

        percentage = (
            count / len(df) * 100
            if len(df) > 0
            else 0
        )

        print(
            f"{column}: "
            f"{count:,} "
            f"({percentage:.2f}%)"
        )

    # --------------------------------------------------------
    # DUPLICATES
    # --------------------------------------------------------

    print("\n" + "=" * 70)
    print("[6] DUPLICATE RECORDS")
    print("=" * 70)

    duplicate_count = df.duplicated().sum()

    print(
        f"Duplicate rows: {duplicate_count:,}"
    )

    # --------------------------------------------------------
    # UNIQUE PRODUCTS
    # --------------------------------------------------------

    print("\n" + "=" * 70)
    print("[7] PRODUCT INFORMATION")
    print("=" * 70)

    unique_products = (
        df["Description"]
        .nunique(dropna=True)
    )

    unique_categories = (
        df["Category"]
        .nunique(dropna=True)
    )

    print(
        f"Unique products: {unique_products:,}"
    )

    print(
        f"Unique categories: {unique_categories:,}"
    )

    # --------------------------------------------------------
    # NUMERIC CONVERSION
    # --------------------------------------------------------

    df["Quantity"] = pd.to_numeric(
        df["Quantity"],
        errors="coerce"
    )

    df["UnitPrice"] = pd.to_numeric(
        df["UnitPrice"],
        errors="coerce"
    )

    df["TotalPrice"] = pd.to_numeric(
        df["TotalPrice"],
        errors="coerce"
    )

    # --------------------------------------------------------
    # SALES / QUANTITY SUMMARY
    # --------------------------------------------------------

    print("\n" + "=" * 70)
    print("[8] SALES SUMMARY")
    print("=" * 70)

    print(
        f"Total quantity sold: "
        f"{df['Quantity'].sum():,.2f}"
    )

    print(
        f"Total historical sales: "
        f"₱{df['TotalPrice'].sum():,.2f}"
    )

    print(
        f"Average unit price: "
        f"₱{df['UnitPrice'].mean():,.2f}"
    )

    print(
        f"Average quantity per record: "
        f"{df['Quantity'].mean():,.2f}"
    )

    # --------------------------------------------------------
    # DAILY AGGREGATION
    # --------------------------------------------------------

    print("\n" + "=" * 70)
    print("[9] DAILY SALES COVERAGE")
    print("=" * 70)

    valid_dates = df.dropna(
        subset=["Date"]
    ).copy()

    daily = (
        valid_dates
        .groupby(
            valid_dates["Date"].dt.normalize()
        )
        .agg(
            quantity_sold=("Quantity", "sum"),
            sales_amount=("TotalPrice", "sum"),
            transaction_records=("Description", "count")
        )
        .sort_index()
    )

    print(
        f"Active sales days: {len(daily):,}"
    )

    if len(daily) > 0:

        full_date_range = pd.date_range(
            start=daily.index.min(),
            end=daily.index.max(),
            freq="D"
        )

        missing_days = (
            full_date_range
            .difference(daily.index)
        )

        print(
            f"Calendar days in range: "
            f"{len(full_date_range):,}"
        )

        print(
            f"Days with sales records: "
            f"{len(daily):,}"
        )

        print(
            f"Days without sales records: "
            f"{len(missing_days):,}"
        )

    # --------------------------------------------------------
    # MONTHLY SUMMARY
    # --------------------------------------------------------

    print("\n" + "=" * 70)
    print("[10] MONTHLY SALES SUMMARY")
    print("=" * 70)

    monthly = (
        valid_dates
        .set_index("Date")
        .resample("ME")
        .agg(
            quantity_sold=("Quantity", "sum"),
            sales_amount=("TotalPrice", "sum"),
            sales_records=("Description", "count")
        )
    )

    print(monthly.to_string())

    # --------------------------------------------------------
    # TOP PRODUCTS
    # --------------------------------------------------------

    print("\n" + "=" * 70)
    print("[11] TOP PRODUCTS BY QUANTITY SOLD")
    print("=" * 70)

    top_products = (
        df.groupby("Description", dropna=True)
        .agg(
            quantity_sold=("Quantity", "sum"),
            sales_amount=("TotalPrice", "sum"),
            sales_records=("Description", "count")
        )
        .sort_values(
            "quantity_sold",
            ascending=False
        )
        .head(20)
    )

    print(top_products.to_string())

    # --------------------------------------------------------
    # PRODUCT HISTORY COVERAGE
    # --------------------------------------------------------

    print("\n" + "=" * 70)
    print("[12] PRODUCT HISTORY COVERAGE")
    print("=" * 70)

    product_frequency = (
        df.groupby("Description")
        .agg(
            sales_records=("Description", "count"),
            quantity_sold=("Quantity", "sum")
        )
        .sort_values(
            "sales_records",
            ascending=False
        )
    )

    print(
        "Products with at least one historical record:",
        len(product_frequency)
    )

    print(
        "\nProducts with fewer than 10 records:",
        (product_frequency["sales_records"] < 10).sum()
    )

    print(
        "Products with fewer than 30 records:",
        (product_frequency["sales_records"] < 30).sum()
    )

    # --------------------------------------------------------
    # QUANTITY QUALITY CHECK
    # --------------------------------------------------------

    print("\n" + "=" * 70)
    print("[13] QUANTITY QUALITY CHECK")
    print("=" * 70)

    negative_quantity = (
        (df["Quantity"] < 0)
        .sum()
    )

    zero_quantity = (
        (df["Quantity"] == 0)
        .sum()
    )

    print(
        f"Negative quantity records: "
        f"{negative_quantity:,}"
    )

    print(
        f"Zero quantity records: "
        f"{zero_quantity:,}"
    )

    # --------------------------------------------------------
    # FINAL SUMMARY
    # --------------------------------------------------------

    print("\n" + "=" * 70)
    print("DATA UNDERSTANDING COMPLETE")
    print("=" * 70)

    print(
        "\nThe dataset has been profiled without modifying "
        "the original historical CSV."
    )

    print(
        "\nNext CRISP-DM phase:"
        "\nDATA PREPARATION"
    )


if __name__ == "__main__":
    main()