"""
Generate the October 2026 "fill" sales CSV for the Analytics refit demo.

Purpose
-------
October 2026 contains 13 calendar days with no recorded POS transactions
(audited with app/tests/audit_imported_sales.php on 2026-10-23):

    2026-10-01 .. 2026-10-04, 2026-10-07, 2026-10-08, 2026-10-14,
    2026-10-16 .. 2026-10-21

These gaps are inside the trailing-30 refit window, so they postpone the
SARIMA refit. This script generates a DISCLOSED SIMULATED fill for exactly
those missing dates so the refit can fire for the capstone demo.

Method (fully deterministic, no randomness):
  * per-product daily quantity = round(product_daily_mean x weekday_factor)
  * product_daily_mean comes from the corrected Jan-Sep 2026 import CSV
  * weekday_factor = product weekday mean / product overall mean (same CSV),
    so the weekly rhythm of the historical data is reproduced
  * unit_price copies the product's canonical price from the same CSV
  * output columns/format identical to the import template:
        date, product_name, quantity, unit_price

Usage:
    python app/tests/make_october_fill.py \
        --source "C:/path/simulated_pharmacy_sales_jan_sep_2026_CORRECTED.csv" \
        --out    "C:/path/simulated_pharmacy_sales_october_fill_2026.csv"

Every generated row maps to dates with ZERO existing transactions; live POS
dates are never touched.
"""
import argparse
import csv
import sys
from collections import defaultdict
from datetime import date, timedelta
from pathlib import Path

# Audited on the live VPS on 2026-10-23 (app/tests/audit_imported_sales.php):
# dates in October 2026 that have no sale_items transactions at all.
DEFAULT_FILL_DATES = [
    "2026-10-01", "2026-10-02", "2026-10-03", "2026-10-04",
    "2026-10-07", "2026-10-08", "2026-10-14",
    "2026-10-16", "2026-10-17", "2026-10-18",
    "2026-10-19", "2026-10-20", "2026-10-21",
]


def weekday_factor_table(rows_by_product):
    """product -> {weekday_index(0=Mon): factor} where factor normalizes the
    product's own weekday mean to its overall daily mean."""
    factors = {}
    for product, day_totals in rows_by_product.items():
        overall = sum(day_totals.values()) / len(day_totals)
        weekday_sums = defaultdict(list)
        for day, total in day_totals.items():
            weekday_sums[day.weekday()].append(total)
        means = {wd: sum(v) / len(v) for wd, v in weekday_sums.items() if v}
        factors[product] = (
            {wd: (mean / overall) for wd, mean in means.items()},
            overall,
        )
    return factors


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source", required=True, help="Corrected Jan-Sep 2026 import CSV")
    parser.add_argument("--out", required=True, help="Output October fill CSV path")
    parser.add_argument("--dates", nargs="*", default=DEFAULT_FILL_DATES,
                        help="Dates to fill (default: the 13 audited missing October dates)")
    args = parser.parse_args()

    fill_dates = [date.fromisoformat(d) for d in args.dates]

    with open(args.source, newline="", encoding="utf-8-sig") as f:
        source_rows = list(csv.DictReader(f))

    if not source_rows:
        raise SystemExit("Source CSV has no rows.")

    # Collect per (product, day) quantities + canonical price per product.
    day_totals = defaultdict(lambda: defaultdict(int))
    product_price = {}
    product_names = set()
    for row in source_rows:
        name = row["product_name"].strip()
        qty = int(row["quantity"])
        day = date.fromisoformat(row["date"])
        day_totals[name][day] += qty
        product_names.add(name)
        product_price.setdefault(name, row["unit_price"].strip())

    factors = weekday_factor_table(day_totals)

    missing_products = [p for p in product_price if p not in factors]
    if missing_products:
        raise SystemExit(f"Products missing daily history: {missing_products}")

    with open(args.out, "w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f, lineterminator="\n")
        writer.writerow(["date", "product_name", "quantity", "unit_price"])
        written = 0
        per_product_total = defaultdict(int)
        for day in sorted(fill_dates):
            for product in sorted(product_names):
                wd_factors, overall = factors[product]
                factor = wd_factors[day.weekday()]
                quantity = max(1, round(overall * factor))
                writer.writerow([day.isoformat(), product, quantity, product_price[product]])
                per_product_total[product] += quantity
                written += 1

    grand_total = sum(per_product_total.values())
    print(f"Source        : {args.source}")
    print(f"Output        : {args.out}")
    print(f"Fill dates    : {len(fill_dates)} ({', '.join(sorted(set(d.isoformat() for d in fill_dates)))})")
    print(f"Rows written  : {written} ({len(product_names)} products x {len(fill_dates)} dates)")
    print(f"Simulated units: {grand_total}")
    print("DISCLOSED: this file is simulated demo data for dates with no")
    print("recorded POS transactions; it never touches live POS sale rows.")
    return written


if __name__ == "__main__":
    main()
    sys.exit(0)
