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
