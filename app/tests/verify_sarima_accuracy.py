#!/usr/bin/env python3
"""Verify the deployed SARIMA model and the analytics forecast charts.

Checks (all from real artifacts, no re-fitting required):
  1. Recomputes the model's held-out accuracy from the evaluation CSV
     (verification that MAE/RMSE/MAPE/SMAPE reported in
     outputs/evaluation/evaluation_summary.txt are the real numbers).
  2. Rebuilds the actual demand series (from the imported sales CSV when
     available, otherwise from the deployed latest_daily_demand.csv) and
     reports totals, continuity, and weekday structure.
  3. Compares that series, month by month, against what the deployed
     model actually forecast in the annual artifact. Split months (the
     month where the actual series ends and the forecast begins) are
     excluded from variance/MAE/MAPE - the two numbers come from
     different slices of the month and are not a valid comparison.
  4. Reports the operational 30-day forecast window and band width.
  5. Optional data-fidelity audit against the original import CSV:
         python3 app/tests/verify_sarima_accuracy.py /path/to/import.csv
"""
from __future__ import annotations

import calendar
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
    REPO / "sarima_forecasting" / "outputs" / "deployment" / "latest_daily_demand.csv",
]

WEEKDAYS = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"]


def load_json(path: Path) -> dict:
    try:
        if not path.exists() or path.stat().st_size == 0:
            return {}
        data = json.loads(path.read_text(encoding="utf-8"))
        return data if isinstance(data, dict) else {}
    except (OSError, json.JSONDecodeError):
        return {}


def section(title: str) -> None:
    print(f"\n{'=' * 70}\n{title}\n{'=' * 70}")


def parse_demand_rows(reader: csv.DictReader) -> dict[date, int]:
    qty_field = (
        "quantity_sold"
        if "quantity_sold" in (reader.fieldnames or [])
        else "quantity"
    )
    daily: dict[date, int] = {}
    for row in reader:
        d = date.fromisoformat(row["date"].strip())
        daily[d] = daily.get(d, 0) + int(float(row[qty_field]))
    return daily


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

    print(f"  Days evaluated          : {n}")
    print(f"  Actual daily (mean)     : {sum(actual) / n:8.2f} units   range {min(actual):.0f} .. {max(actual):.0f}")
    print(f"  Predicted daily (mean)  : {sum(pred) / n:8.2f} units   range {min(pred):.1f} .. {max(pred):.1f}")
    print(f"  Recomputed MAE          : {mae:8.4f}   (reported 54.170049)")
    print(f"  Recomputed RMSE         : {rmse:8.4f}   (reported 60.819345)")
    print(f"  Recomputed MAPE         : {mape:7.4f}%  (reported 14.528579%)")
    print(f"  Recomputed SMAPE        : {smape_avg:7.4f}%  (reported 14.547799%)")
    print(f"  Mean bias (actual-pred) : {bias:+8.2f} units/day (positive = model under-forecasts)")

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
# 2. Actual demand series
# ----------------------------------------------------------------- #
def build_daily_series(candidates: list[Path]) -> dict[date, int]:
    """Daily demand series. Prefers the imported sales CSV; falls back to
    the SARIMA deployment input (date,quantity_sold) so this also runs on
    the VPS where the raw import CSV is not present."""
    for src in candidates:
        if not src.exists():
            continue
        try:
            with src.open(newline="", encoding="utf-8-sig") as f:
                daily = parse_demand_rows(csv.DictReader(f))
        except (OSError, ValueError, KeyError) as exc:
            print(f"  Skipping {src.name}: {exc}")
            continue
        if daily:
            print(f"  Source file: {src}")
            return daily
    print(f"  MISSING: no demand source found ({[p.name for p in candidates]})")
    return {}


def month_sums(daily: dict[date, int]) -> dict[int, int]:
    monthly: dict[int, int] = defaultdict(int)
    for d, q in daily.items():
        monthly[d.month] += q
    return dict(monthly)


def report_actuals(daily: dict[date, int]) -> None:
    section("2. ACTUAL DEMAND SERIES REBUILT FROM THE IMPORTED DATA")
    if not daily:
        return
    first, last = min(daily), max(daily)
    expected = [(first + timedelta(days=i)) for i in range((last - first).days + 1)]
    missing = [d.isoformat() for d in expected if d not in daily]

    print(f"  Source range            : {first} .. {last}")
    print(f"  Days present            : {len(daily)} / {len(expected)}   missing: {len(missing)}")
    if missing:
        print(f"  Missing dates           : {', '.join(missing[:10])}{' ...' if len(missing) > 10 else ''}")

    monthly = month_sums(daily)
    print(f"\n  {'Month':6} | {'Actual units':>12}")
    print("  " + "-" * 25)
    for m in sorted(monthly):
        print(f"  {m:6} | {monthly[m]:12,.0f}")

    values = list(daily.values())
    print(f"\n  Daily total (mean)      : {statistics.mean(values):8.2f} units/day")
    print(f"  Daily total (median)    : {statistics.median(values):8.1f} units/day")
    print(f"  Daily total (min..max)  : {min(values)} .. {max(values)}")

    by_wd = defaultdict(list)
    for d, q in daily.items():
        by_wd[d.weekday()].append(q)
    wk_end = statistics.mean(by_wd[5] + by_wd[6])
    wk_day = statistics.mean([q for wd in range(5) for q in by_wd[wd]])
    print(f"  Weekend vs weekday mean : {wk_end:.1f} vs {wk_day:.1f} "
          f"(weekend {'HIGHER' if wk_end > wk_day else 'lower'} by {abs(wk_end - wk_day):.1f})")


# ----------------------------------------------------------------- #
# 3. Compare actual vs the deployed model's published forecast
# ----------------------------------------------------------------- #
def compare_with_model(daily: dict[date, int]) -> None:
    section("3. CHART CROSS-CHECK: ACTUAL vs DEPLOYED SARIMA FORECAST")
    payload = load_json(DEPLOY / "annual_forecast_2026.json")
    if not payload or not daily:
        print("  MISSING: annual artifact or demand series.")
        print(f"  Chart reads : {DEPLOY / 'annual_forecast_2026.json'}")
        return

    meta = payload.get("metadata", {})
    year = int(meta.get("forecast_year") or 2026)
    legacy = all("forecast_2026" in m for m in payload["months"])
    fkey = "forecast_2026" if legacy else "forecast_units"
    print(f"  Artifact generated at   : {meta.get('generated_at')}")
    print(f"  Forecast year           : {year}")
    print(f"  Schema detected         : {'legacy (actual_2025/forecast_2026)' if legacy else 'current (actual_units/forecast_units)'}")

    actual_through = str(meta.get("actual_data_through") or "")[:10]
    forecast_start = str(meta.get("forecast_start") or "")[:10]
    split_month = None
    if actual_through[:7] and actual_through[:7] == forecast_start[:7]:
        split_month = int(actual_through[5:7])
        print(f"  Split month detected    : {calendar.month_abbr[split_month]} "
              f"(actual through {actual_through}, forecast starts {forecast_start})")
        print("                            -> excluded from variance/MAE/MAPE: the two "
              "numbers come from different slices of the same month")

    months = {int(m["month"]): m for m in payload["months"]}
    actual_mean_day = statistics.mean(daily.values())

    print(f"\n  {'Mon':4} | {'Actual':>9} | {'Forecast':>10} | {'Var (A-F)':>10} | In band?")
    print("  " + "-" * 56)
    errs: list[float] = []
    pct: list[float] = []
    in_band = 0
    fcst_days = [0.0, 0]  # [forecast sum, day count] across forecast months
    for m in sorted(months):
        row = months[m]
        a = monthly_value(daily, m)
        f = row.get(fkey)
        lo, hi = row.get("lower_bound"), row.get("upper_bound")
        if a is None and f is None:
            continue

        if f is not None:
            days_in_month = calendar.monthrange(year, m)[1]
            if m == split_month:
                start_day = int(forecast_start[8:10]) if forecast_start[:4] == str(year) else 1
                fcst_days[0] += float(f)
                fcst_days[1] += max(1, days_in_month - (start_day - 1))
            else:
                fcst_days[0] += float(f)
                fcst_days[1] += days_in_month

        if a is None or f is None:
            a_txt = f"{a:9,.0f}" if a is not None else "        -"
            f_txt = f"{f:10,.2f}" if f is not None else "         -"
            print(f"  {m:4} | {a_txt} | {f_txt} | {'        -':>10} | "
                  f"{'actual-only (no forecast stored)' if a is not None else 'forecast-only (future)'}")
            continue

        if m == split_month:
            print(f"  {m:4} | {a:9,.0f} | {f:10,.2f} | {'SPLIT':>10} | excluded (not comparable)")
            continue

        v = a - f
        band_ok = lo is not None and a >= lo and a <= hi
        in_band += 1 if band_ok else 0
        errs.append(abs(v))
        if f:
            pct.append(abs(v) / f * 100)
        print(f"  {m:4} | {a:9,.0f} | {f:10,.2f} | {v:+10,.1f} | "
              f"{'YES' if band_ok else 'NO'}  [{lo:,.0f} .. {hi:,.0f}]")

    if errs:
        n = len(errs)
        print(f"\n  Like-for-like months    : {n}")
        print(f"  Monthly MAE (|A-F|)     : {sum(errs) / n:8.1f} units/month")
        print(f"  Monthly MAPE (of fcst)  : {sum(pct) / n:7.1f}%")
        print(f"  In-band coverage        : {in_band}/{n} months inside the 95% forecast band")
    else:
        print("\n  Like-for-like months    : none")
        print("  In the current-year artifact the past months carry actuals only and")
        print("  the forecast months are still in the future, so there is no stored")
        print("  historical forecast to compare against month-for-month. Fidelity to")
        print("  the model's backtest (Section 1) plus the daily-level check below")
        print("  are the valid accuracy statements.")

    if fcst_days[1]:
        implied = fcst_days[0] / fcst_days[1]
        ratio = (implied - actual_mean_day) / actual_mean_day * 100 if actual_mean_day else float("nan")
        print(f"\n  LEVEL CHECK")
        print(f"  Actual daily level      : {actual_mean_day:8.2f} units/day")
        print(f"  Model implied daily     : {implied:8.2f} units/day "
              f"(over the published forecast window)")
        print(f"  Over-projection         : {ratio:+.1f}% "
              f"({'~' if abs(ratio) < 10 else ''}{implied / actual_mean_day if actual_mean_day else float('nan'):.2f}x "
              f"the current actual level)")


def monthly_value(daily: dict[date, int], month: int) -> int | None:
    total = sum(q for d, q in daily.items() if d.month == month)
    if month not in {d.month for d in daily}:
        return None
    return total


# ----------------------------------------------------------------- #
# 4. Operational 30-day forecast sanity
# ----------------------------------------------------------------- #
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
        mean = float(r.get("forecast_quantity", r.get("forecast", 0)))
        lo = float(r.get("lower_bound", mean))
        hi = float(r.get("upper_bound", mean))
        half.append(((hi - lo) / 2, r.get("date")))
    print(f"  Training window (artifact) : {meta.get('training_start')} .. {meta.get('training_end')}")
    print(f"  Forecast window            : {recs[0].get('date')} .. {recs[-1].get('date')}")
    print(f"  Band half-width (95% CI)   : day 1 \u00b1{half[0][0]:.1f}  |  day 30 \u00b1{half[-1][0]:.1f}")
    print(f"  Forecast type              : {meta.get('forecast_type', 'unknown')}")


# ----------------------------------------------------------------- #
# 5. Optional: data fidelity audit (imported CSV vs database series)
# ----------------------------------------------------------------- #
def fidelity_audit(db_daily: dict[date, int], csv_path: Path | None) -> None:
    if csv_path is None:
        return
    if not csv_path.exists():
        print(f"  MISSING: {csv_path}")
        return
    section("5. DATA FIDELITY AUDIT: original import CSV vs database demand")
    with csv_path.open(newline="", encoding="utf-8-sig") as f:
        csv_daily = parse_demand_rows(csv.DictReader(f))

    common = set(db_daily) & set(csv_daily)
    only_csv = sorted(set(csv_daily) - set(db_daily))
    only_db = sorted(set(db_daily) - set(csv_daily))
    diff_days = [d for d in sorted(common) if db_daily[d] != csv_daily[d]]
    total_db = sum(db_daily[d] for d in common)
    total_csv = sum(csv_daily[d] for d in common)

    print(f"  Source CSV              : {csv_path}")
    print(f"  Dates in both           : {len(common)}")
    print(f"  Dates only in CSV       : {len(only_csv)}" + (f"  e.g. {[str(d) for d in only_csv[:5]]}" if only_csv else ""))
    print(f"  Dates only in database  : {len(only_db)}" + (f"  e.g. {[str(d) for d in only_db[:5]]}" if only_db else ""))
    print(f"  Dates with quantity gap : {len(diff_days)}")
    for d in diff_days[:12]:
        print(f"    {d}: DB {db_daily[d]} vs CSV {csv_daily[d]}  (gap {db_daily[d] - csv_daily[d]:+d})")
    if total_csv:
        print(f"  Totals (shared dates)   : DB {total_db:,} vs CSV {total_csv:,} "
              f"({(total_db - total_csv) / total_csv * 100:+.1f}%)")

    m_db = month_sums(db_daily)
    m_csv = month_sums(csv_daily)
    print(f"\n  {'Mon':4} | {'DB':>8} | {'CSV':>8} | {'DB vs CSV':>10}")
    print("  " + "-" * 38)
    for m in sorted(set(m_db) | set(m_csv)):
        a, b = m_db.get(m, 0), m_csv.get(m, 0)
        pct = (a - b) / b * 100 if b else float("nan")
        print(f"  {m:4} | {a:8,} | {b:8,} | {pct:+9.1f}%")


def main() -> None:
    try:  # Windows console is cp1252 by default; keep Unicode output safe.
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    except (AttributeError, OSError):
        pass

    csv_arg = Path(sys.argv[1]) if len(sys.argv) > 1 and sys.argv[1] not in ("", "-") else None

    verify_holdout()
    daily = build_daily_series(CSV_CANDIDATES)
    if daily:
        report_actuals(daily)
        compare_with_model(daily)
    report_operational()
    fidelity_audit(daily, csv_arg)


if __name__ == "__main__":
    sys.exit(main())
