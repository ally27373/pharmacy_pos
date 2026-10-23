#!/usr/bin/env python3
"""Verify the deployed SARIMA model and the analytics forecast charts.

Checks (all from real artifacts, no re-fitting required):
  1. Recomputes the model's held-out accuracy from the evaluation CSV
     (verification that MAE/RMSE/MAPE/SMAPE reported in
     outputs/evaluation/evaluation_summary.txt are the real numbers).
  2. Rebuilds the actual 2026 daily demand series from the imported
     sales CSV and compares it, month by month, against the deployed
     SARIMA model's 2026 monthly forecast (annual artifact).
  3. Prints the exact monthly values the Analytics annual chart should
     display so they can be compared against the dashboard.

Run:  python app/tests/verify_sarima_accuracy.py
"""
from __future__ import annotations

import csv
import json
import math
import statistics
import sys
from collections import defaultdict
from datetime import date, timedelta
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
OUT = REPO / "sarima_forecasting" / "outputs"
DEPLOY = OUT / "deployment"
EVAL = OUT / "evaluation"

DESKTOP = Path(r"C:\Users\monah\OneDrive\Desktop")
CSV_CANDIDATES = [
    DESKTOP / "simulated_pharmacy_sales_jan_sep_2026.csv",
    DESKTOP / "simulated_pharmacy_sales_jan_sep_2026_FIXED.csv",
]

WEEKDAYS = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"]


def load_json(path: Path) -> dict:
    try:
        if not path.exists() or path.stat().st_size == 0:
            return {}
        return json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return {}


def section(title: str) -> None:
    print(f"\n{'=' * 70}\n{title}\n{'=' * 70}")


# ----------------------------------------------------------------- #
# 1. Held-out evaluation: recompute reported metrics
# ----------------------------------------------------------------- #
def verify_holdout() -> None:
    section("1. HOLD-OUT VERIFICATION (30-day test, 2025-12-02 .. 2025-12-31)")
    path = EVAL / "best_model_test_forecast.csv"
    if not path.exists():
        print(f"  MISSING: {path}")
        return

    rows = list(csv.DictReader(path.open(encoding="utf-8")))
    actual = [float(r["Actual"]) for r in rows]
    pred = [float(r["Predicted"]) for r in rows]
    n = len(actual)
    abs_err = [abs(a - p) for a, p in zip(actual, pred)]
    pct_err = [100 * abs(a - p) / a for a, p in zip(actual, pred)]
    smape = [100 * abs(a - p) / ((abs(a) + abs(p)) / 2) for a, p in zip(actual, pred)]

    mae = sum(abs_err) / n
    rmse = math.sqrt(sum(e * e for e in (a - p for a, p in zip(actual, pred))) / n)
    mape = sum(pct_err) / n
    smape_avg = sum(smape) / n
    bias = sum(a - p for a, p in zip(actual, pred)) / n
    mean_actual = sum(actual) / n
    mean_pred = sum(pred) / n

    print(f"  Days evaluated          : {n}")
    print(f"  Actual daily (mean)     : {mean_actual:8.2f} units   range {min(actual):.0f} .. {max(actual):.0f}")
    print(f"  Predicted daily (mean)  : {mean_pred:8.2f} units   range {min(pred):.1f} .. {max(pred):.1f}")
    print(f"  Recomputed MAE          : {mae:8.4f}   (reported 54.170049)")
    print(f"  Recomputed RMSE         : {rmse:8.4f}   (reported 60.819345)")
    print(f"  Recomputed MAPE         : {mape:7.4f}%  (reported 14.528579%)")
    print(f"  Recomputed SMAPE        : {smape_avg:7.4f}%  (reported 14.547799%)")
    print(f"  Mean bias (actual−pred) : {bias:+8.2f} units/day (positive = model under-forecasts)")

    by_day = defaultdict(list)
    by_day_pred = defaultdict(list)
    for r in rows:
        wd = date.fromisoformat(r["Date"]).weekday()
        by_day[wd].append(float(r["Actual"]))
        by_day_pred[wd].append(float(r["Predicted"]))

    print(f"\n  {'Day':4} | {'Actual mean':>11} | {'Predicted mean':>14}")
    print("  " + "-" * 36)
    for wd in range(7):
        a = sum(by_day[wd]) / len(by_day[wd])
        p = sum(by_day_pred[wd]) / len(by_day_pred[wd])
        print(f"  {WEEKDAYS[wd]:4} | {a:11.1f} | {p:14.1f}")


# ----------------------------------------------------------------- #
# 2. Actual 2026 series from the imported CSV
# ----------------------------------------------------------------- #
def build_daily_series(candidates: list[Path]) -> dict[date, int]:
    """Daily demand series. Prefers the imported sales CSV; falls back to the
    SARIMA deployment input (date,quantity_sold) so this also runs on the VPS."""
    for src in candidates:
        if not src.exists():
            continue
        daily: dict[date, int] = {}
        with src.open(newline="", encoding="utf-8-sig") as f:
            reader = csv.DictReader(f)
            qty_field = (
                "quantity_sold" if "quantity_sold" in (reader.fieldnames or []) else "quantity"
            )
            for row in reader:
                d = date.fromisoformat(row["date"].strip())
                daily[d] = daily.get(d, 0) + int(float(row[qty_field]))
        if daily:
            print(f"  Source file: {src}")
            return daily
    print(f"  MISSING: no demand source found ({[p.name for p in candidates]})")
    return {}


def report_actuals(daily: dict[date, int]) -> None:
    section("2. ACTUAL 2026 SALES REBUILT FROM THE IMPORTED CSV")
    if not daily:
        return
    first, last = min(daily), max(daily)
    expected = [(first + timedelta(days=i)) for i in range((last - first).days + 1)]
    missing = [d.isoformat() for d in expected if d not in daily]

    print(f"  Source range            : {first} .. {last}")
    print(f"  Days present            : {len(daily)} / {len(expected)}   missing: {len(missing)}")
    if missing:
        print(f"  Missing dates           : {', '.join(missing[:10])}{' ...' if len(missing) > 10 else ''}")

    monthly = defaultdict(int)
    for d, q in daily.items():
        monthly[d.month] += q
    print(f"\n  {'Month':6} | {'Actual units':>12}")
    print("  " + "-" * 25)
    for m in sorted(monthly):
        print(f"  {m:6} | {monthly[m]:12,.0f}")

    values = list(daily.values())
    values_no_zero = [v for v in values if v > 0] or values
    print(f"\n  Daily total (mean)      : {statistics.mean(values):8.2f} units/day")
    print(f"  Daily total (mean>0)    : {statistics.mean(values_no_zero):8.2f} units/day")
    print(f"  Daily total (median)    : {statistics.median(values):8.1f} units/day")
    print(f"  Daily total (min..max)  : {min(values)} .. {max(values)}")

    by_wd = defaultdict(list)
    for d, q in daily.items():
        by_wd[d.weekday()].append(q)
    print(f"\n  {'Day':4} | {'Mean units':>10}")
    print("  " + "-" * 20)
    for wd in range(7):
        print(f"  {WEEKDAYS[wd]:4} | {statistics.mean(by_wd[wd]):10.1f}")

    wk_end = statistics.mean(by_wd[5] + by_wd[6])
    wk_day = statistics.mean(by_wd[0] + by_wd[1] + by_wd[2] + by_wd[3] + by_wd[4])
    print(f"  Weekend vs weekday mean : {wk_end:.1f} vs {wk_day:.1f} "
          f"(weekend {'HIGHER' if wk_end > wk_day else 'lower'} by {abs(wk_end - wk_day):.1f})")
    return monthly


# ----------------------------------------------------------------- #
# 3. Compare actual 2026 against the deployed model's 2026 forecast
# ----------------------------------------------------------------- #
def compare_with_model(monthly: dict | None) -> None:
    section("3. CHART CROSS-CHECK: ACTUAL 2026 vs DEPLOYED SARIMA 2026 FORECAST")
    payload = load_json(DEPLOY / "annual_forecast_2026.json")
    if not payload or not monthly:
        print("  MISSING: annual artifact or sales CSV.")
        print(f"  Chart reads : {DEPLOY / 'annual_forecast_2026.json'}")
        return

    meta = payload.get("metadata", {})
    legacy = all("forecast_2026" in m for m in payload["months"])
    print(f"  Artifact generated at   : {meta.get('generated_at')}")
    print(f"  Schema detected         : {'legacy (actual_2025/forecast_2026)' if legacy else 'current (actual_units/forecast_units)'}")

    months = {m["month"]: m for m in payload["months"]}
    means = {1: 31, 2: 28, 3: 31, 4: 30, 5: 31, 6: 30, 7: 31, 8: 31, 9: 30}

    print(f"\n  {'Mon':4} | {'Actual26':>9} | {'Model fcst':>10} | {'Var (A−F)':>10} | {'~daily gap':>10} | {'Δ vs 2025':>9} | In band?")
    print("  " + "-" * 80)
    errs, pct, in_band = [], [], 0
    for m in sorted(monthly):
        row = months[m]
        fkey = "forecast_2026" if legacy else "forecast_units"
        f = row.get(fkey)
        lo, hi = row.get("lower_bound"), row.get("upper_bound")
        a = monthly[m]
        v = a - f
        errs.append(abs(v))
        pct.append(abs(v) / f * 100)
        var_band = lo is not None and a >= lo and a <= hi
        in_band += 1 if var_band else 0
        gap = v / means[m]
        a25 = row.get("actual_2025") if legacy else None
        shift = (a - a25) / a25 * 100 if a25 else float("nan")
        print(f"  {m:4} | {a:9,.0f} | {f:10,.2f} | {v:+10,.1f} | {gap:+10.1f}/d | {shift:+8.1f}% | "
              f"{'YES' if var_band else 'NO'}  [{lo:,.0f} .. {hi:,.0f}]")

    n = len(monthly)
    print(f"\n  Monthly MAE (|A−F|)     : {sum(errs) / n:8.1f} units/month")
    print(f"  Monthly MAPE            : {sum(pct) / n:7.1f}%")
    print(f"  In-band coverage        : {in_band}/{n} months inside the 95% forecast band")
    total_a = sum(monthly.values())
    total_f = sum(months[m].get("forecast_2026" if legacy else "forecast_units") for m in monthly)
    print(f"  Total Jan–Sep           : actual {total_a:,.0f} vs forecast {total_f:,.0f} "
          f"({(total_a - total_f) / total_f * 100:+.1f}%)")


def report_operational() -> None:
    section("4. OPERATIONAL 30-DAY FORECAST (the daily chart / next 30 days)")
    payload = load_json(DEPLOY / "forecast_30_day.json")
    if not payload:
        print(f"  MISSING/empty: {DEPLOY / 'forecast_30_day.json'}")
        return
    meta = payload.get("metadata", {})
    recs = payload.get("forecast", [])
    if not recs:
        print("  Artifact has no forecast records.")
        return
    half = []
    for r in recs:
        mean = float(r.get("forecast", r.get("predicted", 0)))
        lo = float(r.get("lower_bound", mean))
        hi = float(r.get("upper_bound", mean))
        half.append(((hi - lo) / 2, r.get("date")))
    print(f"  Training window (artifact) : {meta.get('training_start')} .. {meta.get('training_end')}")
    print(f"  Forecast window            : {recs[0].get('date')} .. {recs[-1].get('date')}")
    print(f"  Band half-width (95% CI)   : day 1 ±{half[0][0]:.1f}  |  day 30 ±{half[-1][0]:.1f}")


def main() -> None:
    try:  # Windows console is cp1252 by default; keep Unicode output safe.
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    except (AttributeError, OSError):
        pass

    csv_candidates = list(CSV_CANDIDATES) + [
        REPO / "sarima_forecasting" / "outputs" / "deployment" / "latest_daily_demand.csv",
    ]

    verify_holdout()
    monthly = report_actuals(build_daily_series(csv_candidates))
    compare_with_model(monthly)
    report_operational()


if __name__ == "__main__":
    sys.exit(main())
