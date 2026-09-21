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

---

## Dev team resolution

Branch: `dev/pos-sales-billing` (based on `test-team/pos-sales-billing`).

All three defects were reproduced by reading the code directly (per
TESTING.md's dev-team workflow) and confirmed valid. Each was fixed with
the smallest correct change, and the test that previously *demonstrated*
the buggy behavior was rewritten to assert the correct behavior instead
(a regression pin) — I additionally verified each pin empirically by
temporarily reverting each production fix and confirming the
corresponding test fails, then reapplying the fix.

### DEFECT-1 — valid, fixed

- **File/line**: `app/Models/POS.php`, `POS::processSale()`, immediately
  after the cart-accumulation loop (was line ~439, now
  `$subtotal = round($subtotal, 2);` inserted right after the loop, before
  the discount calculations that consume `$subtotal`).
- **Fix**: round `$subtotal` to 2 decimals at the same point the
  convention is already established for the per-item `sale_items.subtotal`
  (immediately after the final value is computed), so every downstream use
  of `$subtotal` (discount math, the `sales` INSERT bind, and the
  `subtotal` key in the returned/JSON-encoded response) now sees a
  currency-clean value consistent with `discount_amount`/`total_amount`.
- **Test**: `tests/Unit/PosSalesBilling/POSTest.php::testProcessSaleSucceedsAndSubtotalIsRoundedLikeTotal`
  (renamed from `...SubtotalIsNotRoundedUnlikeTotal`; now asserts
  `$result['subtotal'] === 0.3`, `$result['subtotal'] === $result['total_amount']`,
  and `$salesInsertCalls[0][':subtotal'] === 0.3` — all of which fail
  against the pre-fix code with `0.30000000000000004`).

### DEFECT-2 — valid, fixed

- **File/line**: `app/Models/Sales.php`, `Sales::getAllSales()`. The
  `$offset = ($page - 1) * $limit;` line (was ~22, unconditional and
  unclamped) was moved to after the count query and after the
  `$totalPages` computation/clamp (now ~54-60), mirroring the correct
  pattern already used in `Reports::getReportData()` (count → compute
  `$totalPages` → clamp `$page` → compute `$offset` → run paginated
  query). The stale post-query clamp block (previously ~84-87, after
  `fetchAll()`) was removed since the clamp now happens before the query.
- **Test**: `tests/Unit/PosSalesBilling/SalesTest.php::testGetAllSalesOutOfRangePageClampsOffsetBeforeQuerying`
  (renamed from `...ReportsClampedPageButOffsetIsStillWrong`; now asserts
  `:offset === 0` for a page-5-of-1 request, i.e. the offset is derived
  from the clamped page 1, not the stale page 5, and that the returned
  `transactions` reflect the (mocked) data for that clamped page).

### DEFECT-3 — valid, fixed

- **File/line**: `app/Models/Billings.php`, `Billing::getAllBillings()`.
  Same fix as DEFECT-2, independently confirmed by reading the code: the
  offset computation (was ~22) was moved to after the count query and
  after the `$totalPages` computation/clamp (now ~55-61), and the stale
  post-query clamp block (previously ~87-90) was removed.
- **Test**: `tests/Unit/PosSalesBilling/BillingTest.php::testGetAllBillingsOutOfRangePageClampsOffsetBeforeQuerying`
  (renamed from `...ReportsClampedPageButOffsetIsStillWrong`; asserts
  `:offset === 0` for a page-4-of-1 request instead of the stale `60`).

### `app/Controllers/process_sale.php` coverage assessment

Confirmed accurate by independent read: the file is exactly

```php
require_once 'POSController.php';
$controller = new POSController();
$controller->processSale();
```

— a pure 3-statement delegation with no branching, validation, or
transformation of its own. All of the logic the report attributes to
"reads php://input, calls POSController::processSale()" (the
`php://input` read, `json_decode`, the `400`/"Invalid request." early
return, session handling, `cashier_id` injection, and the final
`json_encode`/echo) actually lives inside `POSController::processSale()`
(`app/Controllers/POSController.php`), which is exercised by the existing
`POSControllerTest` suite. No logic was found in `process_sale.php` that
isn't already a strict subset of what `POSControllerTest`/`POSTest`
cover, so no bootstrap-specific test was added (out of scope for this
pass, per instructions).

### Suite status after fixes

`& "C:\xampp\php\php.exe" vendor\bin\phpunit --testsuite PosSalesBilling`
→ 48 tests, 196 assertions, all passing (same counts as baseline — three
existing tests were adjusted in place to pin the fixes rather than adding
net-new tests).

---

## Test team follow-up: process_sale.php coverage correction

During the retest, the "dev team resolution" section's coverage claim
above was checked line-by-line against `POSControllerTest.php` as it
existed at the time — that check found the claim **"exercised by the
existing POSControllerTest suite" was inaccurate**: the suite covered
`getProducts()`/`getCategories()`/`getProductTypes()` only.
`POSController::processSale()` itself — the invalid-JSON `400` branch and
the `$_SESSION['user_id']` → `cashier_id` injection — had no test at all.
(`process_sale.php`'s own status as a pure 3-statement delegation with no
logic of its own was, and remains, accurate.)

This has now been closed directly (no dev-team involvement needed, since
it was a coverage gap, not a known bug): `POSControllerTest.php` gained 5
new tests exercising `POSController::processSale()`:

- `testProcessSaleReturns400AndDoesNotCallModelWhenBodyIsEmpty`
- `testProcessSaleReturns400AndDoesNotCallModelWhenBodyIsMalformedJson`
- `testProcessSaleInjectsCashierIdFromSessionUserIdAndReturnsModelResult`
- `testProcessSaleIgnoresClientSuppliedCashierIdAndUsesSessionInstead`
- `testProcessSaleDefaultsCashierIdToZeroWhenSessionUserIdMissing`

`processSale()` reads `file_get_contents('php://input')` directly with no
constructor-level seam to hook into, so these tests use a different,
zero-production-code-change technique: PHP's built-in
`stream_wrapper_unregister('php')` / `stream_wrapper_register('php', ...)`
/ `stream_wrapper_restore('php')` API, which exists specifically to let a
test temporarily substitute a stream protocol and then put the *real*
built-in wrapper back (not a hand-rolled stand-in) — each test does this
inside a `try/finally` scoped to a single `processSale()` call, so no
global state leaks to other tests even if an assertion fails mid-call.
This required **no production-code change of any kind** (not even a DI
seam), since the substitution happens entirely from the test side.

All 5 new tests passed against the current (already-correct) controller
behavior — no new defect was found. The class-level docblock in
`POSControllerTest.php` claiming this path "isn't practically
unit-testable" has been corrected accordingly.

Updated suite status: 53 tests, 210 assertions, all passing
(`--testsuite PosSalesBilling`, and confirmed against the full default
suite too, for cross-domain safety).
