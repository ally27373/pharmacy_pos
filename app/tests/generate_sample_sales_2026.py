"""Generate a sample historical sales CSV (Jan 1 - Sep 30 2026) for testing
the import + forecasting pipeline. Continuous daily sales (no gaps)."""
import csv
import random
from datetime import date, timedelta

products = [
    ("Biogesic 500mg", 8.50),
    ("Bioflu", 12.00),
    ("Neozep Forte", 9.50),
    ("Cetirizine", 10.00),
    ("Enervon", 9.00),
    ("Amoxicillin 500mg", 18.00),
    ("Mefenamic Acid", 11.00),
    ("Kremil-S", 7.50),
    ("Solmux Syrup", 145.00),
    ("Tempra Syrup", 95.00),
]

random.seed(42)
start = date(2026, 1, 1)
end = date(2026, 9, 30)

rows = []
cur = start
while cur <= end:
    # 2-4 products per day to keep continuity
    chosen = random.sample(products, random.randint(2, 4))
    for name, price in chosen:
        qty = random.randint(1, 5)
        rows.append([cur.isoformat(), name, qty, price])
    cur += timedelta(days=1)

out = "sample_sales_2026.csv"
with open(out, "w", newline="") as f:
    w = csv.writer(f)
    w.writerow(["date", "product_name", "quantity", "unit_price"])
    w.writerows(rows)

print(f"Generated {len(rows)} rows covering {start} to {end}")
print(f"Saved to {out}")
