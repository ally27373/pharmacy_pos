# Unit testing infrastructure

Shared scaffold for the parallel test-team / dev-team workflow. Read this
fully before writing tests or fixes.

## Running tests

PHP and PHPUnit are not on PATH globally on this machine. Always invoke them
by full path from the repo root:

```
"C:\xampp\php\php.exe" vendor\bin\phpunit --testsuite <SuiteName>
```

Suite names (see `phpunit.xml`): `AuthUsers`, `InventoryProducts`,
`PosSalesBilling`, `ReportsForecasting`, `NotificationsAuditHelpers`.

Run a single test file:

```
"C:\xampp\php\php.exe" vendor\bin\phpunit tests/Unit/AuthUsers/UserTest.php
```

## No live database — mock the PDO layer

The DB layer (`app/config/database.php`, aliased at `config/database.php`)
connects to a real MySQL instance and `die()`s on failure. Tests must never
depend on a live database. Instead:

1. Use the constructor-injection seam described below to hand each class
   under test a **mock** `PDO` / `PDOStatement` (via the `MocksPdo` trait in
   `tests/Support/MocksPdo.php`), never a real `Database` instance.
2. Never call `(new Database())->connect()` from a test.

### The DI seam pattern

Model/Controller constructors currently hard-wire their dependencies, e.g.:

```php
// app/Models/User.php — BEFORE
public function __construct()
{
    $database = new Database();
    $this->conn = $database->connect();
}
```

This is untestable as-is. Apply this **minimal, backward-compatible** change
wherever you need to inject a mock — an optional parameter that defaults to
today's exact real-DB behavior, so nothing about production call sites
changes:

```php
// app/Models/User.php — AFTER
public function __construct(?PDO $conn = null)
{
    $this->conn = $conn ?? (new Database())->connect();
}
```

Same pattern for Controllers that construct Models directly:

```php
// app/Controllers/AuthController.php — BEFORE
public function __construct()
{
    $this->user = new User();
}

// AFTER
public function __construct(?User $user = null)
{
    $this->user = $user ?? new User();
}
```

Rules for applying this seam:
- Only touch the constructor signature/body. Do not change any other
  method's behavior while adding the seam.
- Keep the default (no-arg) behavior byte-for-byte identical to today.
- This is the *only* kind of production-code edit the **test team** should
  make. Everything else you find (real bugs) goes in a defect report for the
  dev team to fix — do not fix production bugs yourselves.

## Directory conventions

- `tests/Unit/<Domain>/...Test.php` — your domain's test files, one test
  class per production class roughly, named `<ClassName>Test.php`.
- `tests/Support/` — shared helpers (`MocksPdo` trait). Add to this only if
  a helper is genuinely reusable across domains; otherwise keep helpers
  local to your domain folder.
- `tests/Reports/DEFECTS_<domain-slug>.md` — your defect report. Copy
  `tests/Reports/DEFECTS_TEMPLATE.md` as a starting point.

## What counts as a valid defect

File a defect only when a failing test traces back to a genuine behavioral
bug in production code reachable through its public API (wrong return
value, wrong SQL, missed validation, incorrect branching, off-by-one, etc).

Do **not** file a defect when:
- The test's own expectation was wrong (fix the test instead).
- The "bug" is a deliberate design choice with no observable harm.
- The failure is only about code style/formatting, not behavior.

## Workflow

1. **Test team**: for your assigned domain, apply DI seams as needed, write
   PHPUnit unit tests covering the normal path, edge cases, and validation
   logic for each class. Run the suite. For every failure that is a real
   production bug, write it up in `tests/Reports/DEFECTS_<domain-slug>.md`.
   Commit everything (tests, seams, defect report) to your branch.
2. **Dev team**: starting from the corresponding test-team branch, read the
   defect report. For each defect, reproduce it by running the cited test,
   confirm it's valid per the criteria above, then fix the production code
   with the smallest correct change. If a "defect" turns out invalid, leave
   a short note in the report explaining why instead of changing code.
   Re-run the full suite for your domain before committing.
3. **Retest**: the original test-team agent pulls the dev branch into its
   worktree, re-runs the suite, and confirms each defect is actually
   resolved (not just "tests green" — re-check the reasoning).

## Domain split

| Domain | Suite name | Key files |
|---|---|---|
| Auth & Users | `AuthUsers` | `app/Controllers/AuthController.php`, `app/Models/User.php`, `app/Models/UserManagement.php`, `app/Controllers/UserManagementController.php`, `app/Middleware/AuthMiddleware.php`, `app/Helpers/Auth.php`, `middlewares/auth.php`, `middlewares/guest.php`, `controllers/Authcontrollers.php`, `models/users.php` |
| Inventory & Products | `InventoryProducts` | `app/Controllers/InventoryController.php`, `app/Models/Inventory.php`, `app/Controllers/InventoryHistoryController.php`, `app/Models/InventoryHistory.php`, `app/Controllers/ProductController.php`, `app/Models/Product.php`, `app/Controllers/DataManagementController.php`, `app/Models/DataManagement.php`, `app/Services/ImportService.php` |
| POS, Sales & Billing | `PosSalesBilling` | `app/Controllers/POSController.php`, `app/Models/POS.php`, `app/Controllers/SalesController.php`, `app/Models/Sales.php`, `app/Controllers/BillingController.php`, `app/Models/Billings.php`, `app/Controllers/process_sale.php` |
| Reports, Dashboard & Forecasting | `ReportsForecasting` | `app/Controllers/ReportsController.php`, `app/Models/Reports.php`, `app/Controllers/DashboardController.php`, `app/Models/Dashboard.php`, `app/Services/ForecastService.php`, `app/Services/ForecastRefreshService.php`, `app/Services/ProductDemandForecastService.php` |
| Notifications, Audit & Shared Helpers | `NotificationsAuditHelpers` | `app/Controllers/NotificationController.php`, `app/Models/Notification.php`, `app/Controllers/get_notifications.php`, `app/Controllers/AuditLogController.php`, `app/Models/AuditLog.php`, `app/Services/AuditLogger.php`, `app/Helpers/Validator.php`, `helpers/response.php`, `helpers/security.php`, `helpers/validators.php` |

Stay inside your assigned files. If a shared helper needs a seam, only add
it — don't refactor it further.

## vendor/ is gitignored

`vendor/` is not tracked in git, so fresh worktrees won't have it. Before
running tests in a new worktree, copy it from the main checkout:

```powershell
Copy-Item -Recurse -Force "C:\Arjan\PROGRAMMING\COMMISSION\pharmacy_pos\vendor" ".\vendor"
```

(PHPUnit itself is already required in `composer.json`/`composer.lock`, so
this copy is all you need — no network/composer call required.)
