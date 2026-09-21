# Defect Report — Reports, Dashboard & Forecasting

Filed by: test-team/reports-forecasting
Branch: test-team/reports-forecasting
Date: 2026-09-21

Test run summary: 126 tests, 126 passed, 0 failed (324 assertions).

---

## No defects found

Every class in scope (`app/Controllers/ReportsController.php`,
`app/Models/Reports.php`, `app/Controllers/DashboardController.php`,
`app/Models/Dashboard.php`, `app/Services/ForecastService.php`,
`app/Services/ForecastRefreshService.php`,
`app/Services/ProductDemandForecastService.php`) behaved as documented/
implied by its own code and adjacent consumers across the normal-path,
edge-case, and validation/error-handling scenarios covered by the 126
tests in `tests/Unit/ReportsForecasting/`. No test failure traced back to
production code; the only failures hit during development were two wrong
assumptions in my own test code (PHP's `max(0, -3.0)` returns the int `0`,
not `0.0` — fixed in `ForecastServiceTest.php` before this run) rather
than production bugs.

Two things were specifically investigated as *possible* bugs and ruled
out after checking adjacent code/consumers — documented here rather than
filed, per "deliberate design choice with no observable harm":

- **`Reports::getReportData()` does not clamp `page` down when
  `total === 0`.** When there are zero matching rows, `total_pages` is 0
  and the `if ($totalPages > 0 && $filters['page'] > $totalPages)` guard
  is skipped, so an out-of-range requested page (e.g. `page=99`) is
  echoed back as-is in `pagination.page`/`filters.page` instead of being
  clamped to 1. Since `rows` is empty either way and `has_previous`/
  `has_next` are both correctly `false`, this has no observable effect on
  behavior — just a slightly surprising echoed page number with no
  matching rows regardless. Not filed.

- **`DashboardController::getDashboardData()['demand_quantity']` calls
  `Dashboard::getMonthlyDemand()` (the same call used for
  `'monthly_demand'`), not `Dashboard::getDemandQuantity()`** — despite
  the key name closely matching the latter method's name. This looked
  like a copy-paste bug at first glance (there's a whole distinct,
  well-formed `getDemandQuantity()` method that appears otherwise unused
  outside `DashboardController::getDemandAnalytics()`). Checked the only
  consumer, `app/dashboard/analytics/partials/demand_chart.php`: it reads
  `$row['day']` and `$row['total_quantity']` from
  `$dashboardData['demand_quantity']` — exactly `getMonthlyDemand()`'s
  row shape (`day`, `total_quantity`), not `getDemandQuantity()`'s
  (`product_name`, `month`, `year`, `total_quantity`). So the current
  wiring is what the view actually expects; not filed.

<!-- No DEFECT-N entries: no failing test traced back to a genuine
     production bug. -->

---

## Dev team validation

Branch: `dev/reports-forecasting` (based on `test-team/reports-forecasting`).
Date: 2026-09-21.

### Suite reproduction

`"C:\xampp\php\php.exe" vendor\bin\phpunit --testsuite ReportsForecasting`
in a fresh worktree (vendor/ copied from the main checkout, no composer
network call needed): **126 tests, 126 passed, 324 assertions, 0
failures.** Matches the filed summary exactly.

### Production diff audit (`git diff main..test-team/reports-forecasting -- app/`)

Five files changed, 26 insertions / 17 deletions total:
`app/Controllers/DashboardController.php`, `app/Controllers/ReportsController.php`,
`app/Models/Dashboard.php`, `app/Models/Reports.php`,
`app/Services/ForecastRefreshService.php`. `app/Services/ForecastService.php`
and `app/Services/ProductDemandForecastService.php` have zero diff (already
constructor-injectable via an optional `$projectRoot` param before the
test team touched anything).

Every hunk was checked line-by-line: each is an added optional constructor
parameter (`?PDO $conn = null`, `?Dashboard $dashboard = null`,
`?Reports $reports = null`, `?\App\Services\ForecastService
$forecastService = null`, `?string $productDemandForecastFile = null`,
`?string $projectRoot = null`) whose default expression reproduces the
exact prior hard-coded behavior (`$conn ?? (new Database())->connect()`,
`$forecastService ?? new \App\Services\ForecastService()`, the same
`sarima_forecasting/...` path built inline when no override is given, etc).
No method body logic changed outside of substituting a hard-coded
expression for `$this->propertyName` where that property now holds either
the injected value or the same default. Nothing beyond the documented DI
seam pattern was found — no behavior, branching, or query changes.

### Independent re-check of the two ruled-out candidates

**#1 — `Reports::getReportData()` not clamping `page` when `total === 0`.**
Traced the real consumer chain independently:
`app/dashboard/reports/ajax/get_report.php` → `ReportsController::getReportData()`
→ `Reports::getReportData()`, and the only front-end consumer,
`assets/js/reports.js`. Found the exact place this could show up:
`renderPagination()` does
`pageLabel.textContent = \`Page ${page} of ${Math.max(1, totalPages)}\`;`
(line 153) — so an unclamped `page` *would* render a nonsensical label like
"Page 99 of 1" if the API ever returned `page: 99` with `total_pages: 0`.
However, tracing every call site in `reports.js` shows the page value sent
to the API can never reach that state through the UI: every filter change
(`periodSelect`, `sourceSelect`, `categorySelect`, `typeSelect`, the search
box, the custom-date apply button) explicitly calls `loadReport(1)`, and
the prev/next buttons are gated by `has_previous`/`has_next` from the
*previous* response, which are computed correctly even when `total_pages`
is 0 (`page > 1` / `page < 0` both correctly evaluate false when starting
from `page = 1`). There is no polling/auto-refresh that could revisit a
stale high page number after a result set collapses to zero. The only way
to observe the unclamped value is to hand-craft the AJAX URL directly
(e.g. `get_report.php?page=99&search=nonsense`), bypassing the UI
entirely. **Conclusion: CONFIRMED as ruled — no reachable user-facing harm
through the actual application UI.** Not filed, no fix applied.

**#2 — `demand_quantity` key wired to `getMonthlyDemand()` instead of
`getDemandQuantity()`.** Independently opened
`app/dashboard/analytics/partials/demand_chart.php`: it iterates
`$dashboardData['demand_quantity']` reading `$row['day']` and
`$row['total_quantity']` (lines 27-38). Independently read both model
methods in `app/Models/Dashboard.php`: `getMonthlyDemand($month, $year)`
(line 1108) returns rows shaped `{day, total_quantity}` via
`SELECT DAY(s.created_at) AS day, SUM(si.quantity) AS total_quantity ...
GROUP BY DAY(s.created_at)` — an exact match for the view's expectations.
`getDemandQuantity($year, $month)` (line 477) returns a materially
different shape, `{product_name, month, year, total_quantity}`, grouped
per-product-per-month — clearly meant for a different, per-product demand
breakdown, not a daily trend chart. Confirmed `getDemandQuantity()` is not
dead/orphaned code either: it's wired to
`DashboardController::getDemandAnalytics($year, $month)`, a distinct public
controller method (currently not called from any AJAX endpoint file found
in the repo, i.e. it looks like a prepared-but-not-yet-wired-up endpoint
for a future per-product view — separate from, and irrelevant to, the
`demand_quantity` dashboard key). **Conclusion: CONFIRMED — this is not a
naming-collision bug. The current wiring in `getDashboardData()` is
correct.** Not filed, no fix applied.

### Fault-injection spot-checks (4 performed, all caught; reverted cleanly)

All edits were made, tests re-run to observe red, then reverted with
`git checkout --` and confirmed via `git status --short` / `git diff`
returning empty before moving to the next check.

1. **Dashboard — `getSalesGrowthDetails()` rounding/formula**
   (`app/Models/Dashboard.php`): flipped the growth formula from
   `($currentSales - $previousSales) / $previousSales` to
   `($previousSales - $currentSales) / $previousSales` (sign inversion).
   Result: 3 tests went red in `DashboardTest.php`
   (`test_sales_growth_percent_computed_and_rounded`,
   `test_sales_growth_negative_when_current_below_previous`,
   `test_get_sales_growth_returns_just_the_percent`), each failing with
   the exact negated value (e.g. expected `33.33`, got `-33.33`). Reverted;
   suite green again.

2. **ForecastService — negative-value clamping**
   (`app/Services/ForecastService.php`): removed the `max(0, ...)` clamp
   around `forecast_quantity` in `readForecastPayload()`. Result:
   `test_get_forecast_accepts_lower_case_keys_and_negative_quantity` went
   red (expected `0`, got `-5.0`). Reverted; suite green again.

3. **ProductDemandForecastService — skip-rows-without-product-name
   validation** (`app/Services/ProductDemandForecastService.php`): removed
   the `empty($row['product_name'])` half of the skip guard. Result:
   `test_get_forecast_skips_rows_without_product_name` went red (expected
   array size 1, got 3), plus a PHP undefined-array-key warning surfaced
   for the row that should have been skipped. Reverted; suite green again.

4. **Exec-stub interception (extra check, doubles as item below)** — see
   next section.

### Exec-stub verification

Read `tests/Unit/ReportsForecasting/Support/ForecastExecStub.php` in full
and confirmed the mechanism: it declares `function exec(...)` inside
`namespace App\Services { ... }`. `ForecastRefreshService` (also declared
in `namespace App\Services`) calls unqualified `exec($command, $output,
$exitCode)` in `refresh()` (line 112 and line 129 for the annual script).
PHP's namespaced-function-call resolution checks the *calling* code's own
namespace for a same-named function before falling back to the global
one — this is standard PHP behavior, not test-framework magic — so both
calls resolve to `App\Services\exec()`, which forwards to
`ForecastExecStub::handle()`, never to the real global `exec()`.

To prove this empirically rather than just by static reading, temporarily
edited `ForecastExecStub::handle()` to unconditionally
`throw new \RuntimeException('FAULT_INJECTION_MARKER: stub was called
for: ' . $command);` before doing anything else, then re-ran
`ForecastRefreshServiceTest.php`. All 4 tests that reach a real `exec()`
call failed/errored, every one carrying the `FAULT_INJECTION_MARKER`
message with the exact command string built by `ForecastRefreshService`
(e.g. `"python" "C:\...\07_forecast_generation.py" 2>&1` and, for the
PYTHON_BIN-override test, `"/custom/python3.11" "..." 2>&1`), with a stack
trace running `ForecastRefreshService.php:112` →
`ForecastExecStub.php:79` (the namespaced wrapper) →
`ForecastExecStub.php:60` (`handle()`). This is direct proof the stub — not
a real shell/Python process — handles every `exec()` call made by
`refresh()` during the test run. No real `exec()`/Python invocation is
possible from these tests. Reverted the stub edit; suite green again.

### Outcome

No production-code changes were required. Every seam in the test-team's
diff is a genuine, minimal, behavior-preserving DI seam. Both previously
ruled-out candidates were independently re-derived from first-hand
inspection of the actual consumers and confirmed correct as documented.
Four fault-injection spot-checks (3 required + 1 for the exec-stub) all
correctly turned the relevant tests red, confirming the suite are real
fault detectors, not tautological tests. The exec-stub was independently
proven, by direct fault injection, to intercept 100% of `exec()` calls
made by `ForecastRefreshService::refresh()` — no real shell-out/Python
process is reachable from this test run.
