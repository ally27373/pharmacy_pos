# Defect Report — Notifications, Audit & Shared Helpers

Filed by: test-team/notifications-audit-helpers
Branch: test-team/notifications-audit-helpers
Date: 2026-09-21

Test run summary: 48 tests, 48 passed, 0 failed (206 assertions, 0 deprecations).

---

## No defects found

Every test written for this domain passes against the production code as-is
(only the constructor DI seams described below were applied; no other
production behavior was changed). No failing test traced back to a genuine
behavioral bug in `NotificationController`, `Notification`,
`AuditLogController`, `AuditLog`, or `AuditLogger`.

Coverage highlights (see the corresponding test files for the full list of
cases):

- `Notification::getLiveNotifications()` — out-of-stock / low-stock /
  near-expiry (today, tomorrow, N days) message & details wording, the
  defensive `alert_type === null` skip branch, int-casting of DB string
  values, unique-product-count vs. per-type-count summary math, and the
  empty-result-set case.
- `NotificationController::getNotifications()` — delegates to the injected
  `Notification` and returns its result unchanged, plus a reflection check
  that the DI seam's default value still matches pre-existing behavior.
- `AuditLog::create()` — every allow-listed action type
  (CREATE/UPDATE/DELETE/IMPORT/EXPORT), rejection of an out-of-list value,
  case-sensitive rejection of a lowercase action type, and default `null`
  values for the optional `record_id` / `ip_address` / `user_agent` fields.
- `AuditLog::getLogs()` / `countLogs()` — default paging (page 1 / 25 per
  page), offset arithmetic, clamping of `page` (≥ 1) and `perPage`
  (1–100 inclusive) at both ends, WHERE-clause construction and bound
  values for every filter (`action_type`, `module_name`, `date_from`,
  `date_to`, `search` — including the 4-column `LIKE` fan-out), and the
  no-filters case (no `WHERE` at all).
- `AuditLog::getActionTypes()` / `getModules()` — fixed allow-list order,
  and the `query()`-based (not `prepare`/`execute`) distinct-module lookup.
- `AuditLogController` — pure pass-through of filters/page/perPage to the
  model for all four public methods.
- `AuditLogger::log()` — no-op + `false` when there is no session user
  (absent, `0`, or negative), correct actor/action/module/description/
  record-id/IP/user-agent captured on success, numeric-string session
  user IDs cast to `int`, IP/user-agent defaulting to `null` when the
  `$_SERVER` keys are absent, propagation of the underlying `create()`
  return value, and that any `Throwable` from `create()` (including the
  model's own `InvalidArgumentException` for a bad action type) is
  swallowed, logged via `error_log()` with an `"AuditLogger error: ..."`
  prefix containing the original message, and turned into a `false`
  return instead of propagating.

## Notes for the dev team (not filed as defects — no reachable failing behavior)

1. **`app/Helpers/Validator.php` is a 0-byte file** (git blob
   `e69de29bb2d1d6434b8b29ae775ad8c2e48c5391`, same empty blob as
   `app/Helpers/Auth.php` in the Auth & Users domain). It declares no
   class, no functions — nothing. A repo-wide grep for `Validator` finds
   no reference to it anywhere else in `app/` (no `new Validator`, no
   `Validator::...`), so it is not reachable through any public API and
   there is no "individual validation rule" to unit test. This isn't
   filed as a defect because there is no observable behavior to be wrong
   — but it's worth flagging in case this was meant to hold real
   validation logic that never got committed. Validation in this domain
   is currently done inline instead (e.g. `AuditLog::create()`'s
   allow-list check, `AuditLog::getLogs()`'s page/perPage clamping).

2. **`app/Controllers/get_notifications.php` was not executed directly by
   any test.** It's a procedural entry script (no class, no constructor)
   that unconditionally does `new NotificationController()` with no
   arguments — there is no seam to inject a mocked model, and the
   DI-seam pattern in `tests/TESTING.md` is specifically for constructors.
   More importantly, if it *were* run with no live database, the failure
   path is unsafe to exercise in-process: `Database::connect()` calls
   `die()` directly on a `PDOException` (see `config/database.php`)
   instead of throwing, so it would terminate the whole PHP process
   running it (and, if invoked in-process, the PHPUnit runner itself)
   rather than being caught by `get_notifications.php`'s own
   `try { ... } catch (Throwable $e)` block. This is pre-existing,
   documented infrastructure behavior (`tests/TESTING.md` already notes
   the DB layer "`die()`s on failure"), not something new to this file,
   so it isn't filed as a defect either. Its actual logic — building the
   `success/summary/notifications` JSON envelope around
   `NotificationController::getNotifications()`, and the `success: false`
   + zeroed-summary shape on a caught `Throwable` — is a thin wrapper
   that is fully exercised indirectly through the `NotificationController`
   and `Notification` test coverage above.

## Dev team findings

Dev team validated: suite reproduces green in isolated worktree (48 tests,
48 passed, 206 assertions, `dev/notifications-audit-helpers` branched from
this branch). `git diff` of the five DI-seamed files
(`app/Models/Notification.php`, `app/Controllers/NotificationController.php`,
`app/Models/AuditLog.php`, `app/Controllers/AuditLogController.php`,
`app/Services/AuditLogger.php`) against `main` confirms only the documented
optional-constructor-param seams were applied - no other production
behavior changed.

Spot-checked fault-injection on 4 tests across 3 different files/layers to
confirm the assertions are real (would fail on a real regression), each
broken then reverted with `git status`/`git diff` clean afterward:

- `AuditLogTest::test_create_rejects_an_action_type_outside_the_allow_list`
  - added `'READ'` to `AuditLog::ACTION_TYPES` -> test went red
  (`prepare()` was called when it should never have been). Reverted.
- `AuditLogTest::test_get_logs_builds_where_clause_and_binds_every_filter`
  - removed the `:action_type` param binding in
  `AuditLog::buildFilterQuery()` while leaving the WHERE fragment -> test
  went red (`null` bound instead of `'CREATE'`). Reverted.
- `NotificationTest::test_low_stock_row_reports_remaining_units_in_details`
  - changed the low-stock message text in
  `Notification::getLiveNotifications()` from `'Low stock'` to
  `'Low stock alert'` -> test went red. Reverted.
- `AuditLoggerTest::test_log_returns_false_when_session_user_id_is_zero`
  - loosened `AuditLogger::log()`'s guard from `$userId <= 0` to
  `$userId < 0` -> test went red (`create()` was called once instead of
  never). Reverted.

All four confirmed the tests catch real regressions rather than being
tautological/no-op.

Confirmed both non-defect notes independently:
- `app/Helpers/Validator.php` is 0 bytes in
  `test-team/notifications-audit-helpers`
  (`git show ...:app/Helpers/Validator.php | wc -c` -> `0`) and in the dev
  worktree; a repo-wide grep for `Validator` under `app/` finds no
  references anywhere (no class defined, nothing instantiates it).
- `app/Controllers/get_notifications.php` unconditionally constructs
  `new NotificationController()` (no seam) and its `catch (Throwable $e)`
  cannot actually catch a DB connection failure: `Database::connect()` in
  `app/config/database.php` calls `die()` directly inside its own
  `catch (PDOException $e)` block rather than rethrowing, so a connection
  failure terminates the process before `get_notifications.php`'s own
  try/catch ever sees it. Confirmed by reading `app/config/database.php`
  lines 44-84.

No defects found or fixed. No production-code changes made (all
fault-injection edits above were reverted; working tree is clean per
`git status`/`git diff`).
