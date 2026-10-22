#!/usr/bin/env python3
"""Fix simulated_pharmacy_sales_jan_sep_2026.csv so it can be imported.

- Maps every original product name to one of the 10 seed product names (so it
  matches the POS product catalog / case-insensitive import matching).
- Cleans comma-formatted unit prices (e.g. "1,320.00" -> "1320.00").
- Aggregates per (date, seed product) to avoid duplicate-row detection, while
  preserving each day's total quantity sold (forecast input fidelity).
- Writes the fixed CSV to the Desktop.
"""
import csv
import hashlib
from collections import defaultdict, OrderedDict
from pathlib import Path

SRC = Path(r"C:\Users\monah\Downloads\simulated_pharmacy_sales_jan_sep_2026.csv")
OUT = Path(r"C:\Users\monah\OneDrive\Desktop\simulated_pharmacy_sales_jan_sep_2026_FIXED.csv")

SEEDS = [
    "Bioflu",
    "Biogesic 500mg",
    "Neozep Forte",
    "Cetirizine",
    "Enervon",
    "Amoxicillin 500mg",
    "Mefenamic Acid",
    "Kremil-S",
    "Solmux Syrup",
    "Tempra Syrup",
]

# canonical unit prices for the seed products
SEED_PRICE = {
    "Bioflu": 12.00,
    "Biogesic 500mg": 8.50,
    "Neozep Forte": 9.50,
    "Cetirizine": 10.00,
    "Enervon": 9.00,
    "Amoxicillin 500mg": 18.00,
    "Mefenamic Acid": 11.00,
    "Kremil-S": 7.50,
    "Solmux Syrup": 145.00,
    "Tempra Syrup": 95.00,
}

def seed_for(name: str) -> str:
    h = hashlib.md5(name.strip().lower().encode("utf-8")).hexdigest()
    return SEEDS[int(h, 16) % len(SEEDS)]

def clean_price(p: str) -> float:
    return float(p.replace(",", "").strip())

# Aggregate quantity (and weighted price) per (date, seed product)
agg = OrderedDict()  # (date, seed) -> [qty, price_qty_sum]
daily_total_in = defaultdict(int)
with open(SRC, newline="", encoding="utf-8-sig") as f:
    reader = csv.DictReader(f)
    for row in reader:
        d = row["date"].strip()
        qty = int(float(row["quantity"]))
        price = clean_price(row["unit_price"])
        seed = seed_for(row["product_name"])
        key = (d, seed)
        if key not in agg:
            agg[key] = [0, 0.0]
        agg[key][0] += qty
        agg[key][1] += qty * price
        daily_total_in[d] += qty

# Build output rows
rows = []
daily_total_out = defaultdict(int)
for (d, seed), (qty, pq) in agg.items():
    if qty <= 0:
        continue
    # Use canonical seed selling price (consistent with product catalog).
    # Original prices belonged to different products and are not meaningful
    # after remapping; forecast only depends on quantity.
    price = SEED_PRICE[seed]
    rows.append([d, seed, qty, f"{price:.2f}"])
    daily_total_out[d] += qty

# Sort by date then product name for stability
rows.sort(key=lambda r: (r[0], r[1]))

with open(OUT, "w", newline="") as f:
    w = csv.writer(f, lineterminator="\n")
    w.writerow(["date", "product_name", "quantity", "unit_price"])
    w.writerows(rows)

# Verification
print(f"Input rows: {sum(daily_total_in.values())} (qty units)")
print(f"Output rows: {len(rows)}")
print(f"Days in: {len(daily_total_in)}  Days out: {len(daily_total_out)}")
# check daily totals preserved
mismatch = 0
for d in daily_total_in:
    if daily_total_in[d] != daily_total_out.get(d, 0):
        mismatch += 1
print(f"Days with mismatched totals: {mismatch}")
# check contiguous dates
from datetime import date, timedelta
ds = sorted(daily_total_out)
d0 = date.fromisoformat(ds[0]); d1 = date.fromisoformat(ds[-1])
expected = {(d0 + timedelta(days=i)).isoformat() for i in range((d1-d0).days+1)}
print(f"Contiguous day count: {len(daily_total_out)} / {len(expected)}; missing: {len(expected-set(daily_total_out))}")
print(f"Saved: {OUT}")
# product distribution
from collections import Counter
c = Counter(r[1] for r in rows)
print("Product row counts:", dict(c))
