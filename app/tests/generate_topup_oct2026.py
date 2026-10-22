"""Generate the CSV files needed to bridge the forecast continuity gap:

1. sample_oct2026_topup.csv      - daily sales for Oct 1-21, 2026, so the
                                    live POS series stays continuous from
                                    the imported Jan-Sep baseline through
                                    the latest real observation.
2. sample_amox_only_2026.csv     - the 80 Amoxicillin 500mg rows filtered
                                    from the Jan-Sep base CSV, to be used
                                    ONLY if that product is added to the
                                    inventory (base file must not be
                                    re-imported or volumes double-count).
"""
import csv
import random
from datetime import date, timedelta
from pathlib import Path

ROOT = Path(__file__).resolve().parent

products = [
    ("Bioflu", 12.00),
    ("Biogesic 500mg", 8.50),
    ("Neozep Forte", 9.50),
    ("Cetirizine", 10.00),
    ("Enervon", 9.00),
    ("Mefenamic Acid", 11.00),
    ("Kremil-S", 7.50),
    ("Solmux Syrup", 145.00),
    ("Tempra Syrup", 95.00),
]

random.seed(24)
start = date(2026, 10, 1)
end = date(2026, 10, 21)

rows = []
cur = start
while cur <= end:
    for name, price in random.sample(products, random.randint(2, 4)):
        rows.append([cur.isoformat(), name, random.randint(1, 5), price])
    cur += timedelta(days=1)

with open(ROOT / "sample_oct2026_topup.csv", "w", newline="") as f:
    w = csv.writer(f)
    w.writerow(["date", "product_name", "quantity", "unit_price"])
    w.writerows(rows)
print(f"top-up: {len(rows)} rows, {start} to {end}")

# Filter Amoxicillin rows from the Jan-Sep base CSV.
base = ROOT / "sample_sales_2026.csv"
with open(base, newline="") as f:
    reader = csv.reader(f)
    header = next(reader)
    amox_rows = [r for r in reader if r[1].strip().lower() == "amoxicillin 500mg"]

with open(ROOT / "sample_amox_only_2026.csv", "w", newline="") as f:
    w = csv.writer(f)
    w.writerow(header)
    w.writerows(amox_rows)
print(f"amox-only: {len(amox_rows)} rows")
