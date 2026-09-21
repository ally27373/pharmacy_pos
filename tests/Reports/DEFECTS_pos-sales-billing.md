# Defect Report — POS, Sales & Billing

Filed by: test-team/pos-sales-billing
Branch: test-team/pos-sales-billing
Date: 2026-09-21

Test run summary: 48 tests, 48 passed, 0 failed (all tests are green;
each defect below was captured as a passing test that *demonstrates* the
buggy behavior, per tests/TESTING.md's workflow — the dev team should
make the test's assertions describe *correct* behavior instead, then fix
the code until it's green again).

---

## DEFECT-1: Sale subtotal is never rounded, leaking float noise into the DB insert and API response

- **File / function**: `app/Models/POS.php` — `POS::processSale()`
- **Test**: `tests/Unit/PosSalesBilling/POSTest.php::testProcessSaleSucceedsAndSubtotalIsNotRoundedUnlikeTotal`
- **Severity**: medium
- **Expected behavior**: `subtotal`, like `discount_amount` and
  `total_amount`, should be rounded to 2 decimal places before being
  bound into the `sales` INSERT and returned to the caller. With a 0%
  discount, `total_amount` should equal `subtotal` (both represent the
  same peso amount).
- **Actual behavior**: `$subtotal` is accumulated via
  `$subtotal += $qty * $price;` (line ~439) and is **never** passed
  through `round(..., 2)` anywhere before being used. It is bound
  directly into the `sales` INSERT (`':subtotal' => $subtotal`, line
  ~497) and returned as-is in the response array
  (`'subtotal' => $subtotal`, line ~718). By contrast,
  `$discountAmount` and `$total` are both explicitly
  `round(..., 2)`-ed (lines ~454-455) — and even the *per-item*
  subtotal stored in `sale_items` is rounded
  (`$itemSubtotal = round($qty * $price, 2);`, line ~637). Only the
  cart-level `subtotal` is left as a raw float.
  Because most decimal money values aren't exactly representable in
  IEEE-754 binary floating point, this raw float frequently carries
  trailing noise. With PHP's default `serialize_precision=-1`,
  `json_encode()` (used by `POSController::processSale()` to send the
  API response) renders the *shortest round-trippable* representation of
  that noise, e.g. `0.30000000000000004` instead of `0.3`.
- **Steps to reproduce**: Call `POS::processSale()` (via the test's
  `fullChainPdo()` mock harness) with a cart of two items priced `0.10`
  and `0.20` (qty 1 each) and a 0% discount. The returned
  `$result['subtotal']` is `0.1 + 0.2` (`0.30000000000000004...` as a
  PHP double), which is **not** `===` to `$result['total_amount']`
  (`0.3`), even though the discount is zero and the two figures should
  be numerically identical. The same unrounded value is what gets bound
  to `:subtotal` in the `sales` INSERT.
- **Why this looks like a real bug (not a bad test assumption)**: the
  code's own treatment of `discount_amount`, `total_amount`, and the
  per-item `sale_items.subtotal` establishes the intended convention —
  round every currency figure to 2 decimals before storing/returning it.
  `subtotal` is the one place that convention was missed, and the
  resulting value is both stored in the `sales` table with excess
  (meaningless) precision and can visibly corrupt the JSON API response
  the POS frontend receives (a receipt showing "₱0.30000000000000004").
  This is a plain arithmetic/formatting bug, not a design choice — there
  is no scenario where an un-rounded subtotal is desirable here.

---

## DEFECT-2: `Sales::getAllSales()` reports a clamped page number but still queries with the stale, out-of-range offset

- **File / function**: `app/Models/Sales.php` — `Sales::getAllSales()`
- **Test**: `tests/Unit/PosSalesBilling/SalesTest.php::testGetAllSalesOutOfRangePageReportsClampedPageButOffsetIsStillWrong`
- **Severity**: medium
- **Expected behavior**: when a caller requests a page beyond the last
  valid page (e.g. `page=5` when there's only 1 page of results), the
  response should either (a) return the data for the clamped page (i.e.
  recompute the offset from the clamped page and re-run/adjust the
  query), or (b) report the out-of-range page truthfully alongside the
  empty result set. Either is internally consistent.
- **Actual behavior**: the SQL query is built and executed using
  `$offset = ($page - 1) * $limit` computed from the **original,
  unclamped** `$page` (line ~23), before the paginated query runs. Only
  *after* the query has already executed does the code clamp the
  reported `page` value:
  ```php
  if ($totalPages > 0 && $page > $totalPages) {
      $page = $totalPages;
  }
  ```
  (lines ~85-87). The result is a response that claims
  `"pagination": {"page": 1, ...}` (a page that legitimately has data)
  while `"transactions": []` (empty, because the query actually ran
  with the stale out-of-range offset, e.g. `OFFSET 80` against a
  5-row table). A frontend trusting `pagination.page` would show "Page 1
  of 1" with no rows, even though page 1 genuinely has 5 rows.
- **Steps to reproduce**: with `countStmt` returning a total of `5` and
  `limit=20` (so `total_pages=1`), call
  `getAllSales(5, 20)`. The bound `:offset` is `80`
  (`(5-1)*20`, computed from the original page 5), the returned
  `pagination.page` is clamped to `1`, but `transactions` is `[]`.
- **Why this looks like a real bug (not a bad test assumption)**: the
  presence of the clamp-back-down logic shows the author's clear intent
  to keep `page` within `[1, total_pages]` — but the clamp runs too late
  to affect the query that already executed, making the fix
  (incomplete/misordered, not "no fix intended"). The same bug pattern
  is duplicated in `Billing::getAllBillings()` — see DEFECT-3.

---

## DEFECT-3: `Billing::getAllBillings()` has the same clamp-after-query pagination bug as DEFECT-2

- **File / function**: `app/Models/Billings.php` — `Billing::getAllBillings()`
- **Test**: `tests/Unit/PosSalesBilling/BillingTest.php::testGetAllBillingsOutOfRangePageReportsClampedPageButOffsetIsStillWrong`
- **Severity**: medium
- **Expected behavior**: same as DEFECT-2 — the reported `page` and the
  actually-queried offset must correspond to the same page.
- **Actual behavior**: identical pattern to `Sales::getAllSales()`:
  `$offset` is computed from the original `$page` (line ~23) and the
  paginated query runs with it; only afterwards is `$page` clamped down
  to `$totalPages` (lines ~88-90) for the returned pagination metadata,
  without recomputing/re-querying.
- **Steps to reproduce**: with `countStmt` returning a total of `3` and
  `limit=20` (`total_pages=1`), call `getAllBillings(4, 20)`. The bound
  `:offset` is `60` (`(4-1)*20`), the returned `pagination.page` is
  clamped to `1`, but `billings` is `[]`.
- **Why this looks like a real bug (not a bad test assumption)**: same
  reasoning as DEFECT-2 — this is copy-pasted pagination logic with the
  same ordering mistake in both files. Fixing DEFECT-2 and DEFECT-3
  together (e.g. clamping `$page` *before* computing `$offset`, or
  re-deriving `$offset` after the clamp and re-running the paginated
  query) is likely the right approach for both.

<!-- No other production-code defects were found. All other test
     failures encountered during development were due to mistakes in
     the test's own expectations and were fixed in the test rather than
     reported here (e.g. PDO::lastInsertId()'s string|false return type
     vs. an int mock return value; PDOStatement mock dispatch markers
     that needed to be more specific). -->
