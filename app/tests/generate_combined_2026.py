#!/usr/bin/env python3
"""Generate a single verified CSV: base (Jan 1 - Sep 30 2026) + top-up (Oct 1-21 2026).
This produces a clean, continuous 294-day historical dataset for forecasting.
No duplicates; exact product names from seed data; no missing calendar days."""
import csv
import random
from datetime import date, timedelta
from pathlib import Path

random.seed(42)

# Exact 10 product names as seeded in database/pharmacy_pos.sql
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
    ("Amoxicillin 500mg", 18.00),
]

start = date(2026, 1, 1)
end = date(2026, 10, 21)

rows = []
current = start
while current <= end:
    # 2-4 random products per day ensures continuity with realistic variety
    chosen = random.sample(products, random.randint(2, 4))
    for name, price in chosen:
        qty = random.randint(1, 5)
        rows.append([current.isoformat(), name, qty, price])
    current += timedelta(days=1)

ROOT = Path(__file__).resolve().parent
with open(ROOT / "sample_combined_2026.csv", "w", newline="") as f:
    w = csv.writer(f)
    w.writerow(["date", "product_name", "quantity", "unit_price"])
    w.writerows(rows)

print(f"Combined CSV: {len(rows)} rows, {start} to {end} ({(end - start).days + 1} days)")
print("Saved to: sample_combined_2026.csv")
print("Products:", [p[0] for p in products])
