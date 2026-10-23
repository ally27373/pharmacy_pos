"""
Unit test for the trailing-30 refit rule implemented in
sarima_forecasting/scripts/07_forecast_generation.py.

stdlib only: mirrors the rule's semantics (pandas date_range.difference)
exactly with datetime set arithmetic, and validates every decision branch
on synthetic series. Run:  python app/tests/test_trailing_window_rule.py
"""
from datetime import date, timedelta


def trailing_missing_days(dates, window_days=30):
    """Mirror of the refit-window check in 07_forecast_generation.py.

    window_end = newest observation; window_start = window_end-(N-1) days.
    Returns the sorted list of calendar days inside the window that have no
    observation. An empty result means the refit is allowed.
    """
    day_set = set(dates)
    window_end = max(day_set)
    window_start = window_end - timedelta(days=window_days - 1)
    expected = {window_start + timedelta(days=i) for i in range(window_days)}
    return sorted(expected - day_set)


def refit_allowed(dates):
    return not trailing_missing_days(dates)


def steady_state_noop(dates, model_last_date):
    """Mirror of the covered-guard: no post-model observations and the model
    already covers the newest input date -> preserve status, run nothing."""
    post_training = [d for d in dates if d > model_last_date]
    input_last = max(dates)
    return (not post_training) and input_last <= model_last_date


def expect(label, got, want):
    ok = got == want
    print(f"  [{'PASS' if ok else 'FAIL'}] {label}: got {got!r}" + ("" if ok else f"  (want {want!r})"))
    return ok


def main():
    D = date
    results = []

    # 1) Fully covered trailing window -> refit allowed.
    complete = [D(2026, 9, 23) + timedelta(days=i) for i in range(30)]
    results.append(expect("complete window -> refit", refit_allowed(complete), True))
    results.append(expect("complete window -> missing[]", trailing_missing_days(complete), []))

    # 2) One gap inside the window -> refit postponed with the exact missing date.
    gap15 = [d for d in complete if d != D(2026, 10, 7)]
    results.append(expect("single gap -> no refit", refit_allowed(gap15), False))
    results.append(expect("single gap -> missing", trailing_missing_days(gap15), [D(2026, 10, 7)]))
    gap_many = [d for d in complete if d.year == 2026 and d.day not in (1, 2, 3, 4, 7, 8, 14, 16, 17, 18, 19, 20, 21)]
    results.append(expect("13 october gaps -> no refit", refit_allowed(gap_many), False))
    results.append(expect("13 october gaps -> missing count = 13", len(trailing_missing_days(gap_many)), 13))

    # 3) Early operations: 10 days of data -> window reaches before the first
    #    recorded sale; those days also count as not yet covered.
    early = [D(2026, 8, 30) + timedelta(days=i) for i in range(10)]
    results.append(expect("early ops -> no refit", refit_allowed(early), False))
    results.append(expect("early ops -> missing includes pre-history days", len(trailing_missing_days(early)), 20))

    # 4) Steady state after a refit: model endpoint == newest input date and
    #    no new rows -> preserve status and artifacts (the badge stays 'updated').
    covered = complete.copy()
    results.append(expect("steady state -> noop", steady_state_noop(covered, D(2026, 10, 22)), True))
    results.append(expect("one new sale day -> not noop", steady_state_noop(covered + [D(2026, 10, 23)], D(2026, 10, 22)), False))

    print()
    print(f"RESULT: {sum(results)}/{len(results)} assertions passed")
    raise SystemExit(0 if all(results) else 1)


if __name__ == "__main__":
    main()
