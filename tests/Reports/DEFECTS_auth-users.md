# Defect Report — Auth & Users

Filed by: test-team/auth-users
Branch: test-team/auth-users
Date: 2026-09-21

Test run summary: 82 tests, 80 passed, 2 failed.

Suite: `& "C:\xampp\php\php.exe" vendor\bin\phpunit --testsuite AuthUsers`

---

## DEFECT-1: Inactive/locked-account login message is broken when `account_status` is missing or NULL

- **File / function**: `app/Controllers/AuthController.php` — `login()`
- **Test**: `tests/Unit/AuthUsers/AuthControllerTest.php::test_login_treats_missing_account_status_as_inactive`
- **Severity**: low
- **Expected behavior**: When a fetched user row has no usable `account_status`
  (missing key or `NULL` value), the code's own guard treats this as
  `'Inactive'` — see line 105:
  ```php
  if (($user['account_status'] ?? 'Inactive') !== 'Active') {
  ```
  The rejection message built two lines later should therefore read:
  `'Your account is currently inactive. Please contact an Administrator.'`
- **Actual behavior**: The message-building code on line 109 reads
  `$user['account_status']` directly, without the same `?? 'Inactive'`
  fallback used in the condition just above it:
  ```php
  'message' => 'Your account is currently ' .
      strtolower((string)$user['account_status']) .
      '. Please contact an Administrator.'
  ```
  When the key is entirely absent, this raises a PHP warning
  (`Undefined array key "account_status"`) and produces the broken message
  `'Your account is currently . Please contact an Administrator.'` (note the
  blank between "currently" and the period). When the key is present but
  `NULL` (no warning, since the key exists), the same blank message is
  produced silently.
- **Steps to reproduce**: Call `AuthController::login()` (via the DI seam,
  injecting a mock `User`) with `findByUsernameOrEmail()` returning a row
  whose `account_status` key is omitted, using a correct username/password
  pair. Observe the returned `message` and the triggered PHP warning.
- **Why this looks like a real bug (not a bad test assumption)**: The
  condition on line 105 explicitly uses `?? 'Inactive'`, proving the author
  intended to handle a missing/`NULL` `account_status` gracefully. The
  message-construction code two lines later drops that same fallback,
  contradicting the guard's own stated intent and producing a
  warning-triggering, user-facing message with a missing word.

---

## DEFECT-2: `AuthMiddleware::dataManagement()` blocks Cashiers despite its own doc comment saying they're allowed

- **File / function**: `app/Middleware/AuthMiddleware.php` — `dataManagement()`
- **Test**: `tests/Unit/AuthUsers/AuthMiddlewareTest.php::test_dataManagement_allows_cashier_per_documented_intent`
- **Severity**: medium
- **Expected behavior**: The method's own doc block (immediately above it)
  states:
  ```
  | Data Management Access
  |
  | Administrators and Cashiers may access Data Management.
  |
  | Cashiers are intentionally limited to the actions exposed by the
  | Data Management interface, currently dataset viewing/exporting.
  ```
  Role IDs are 1 = Administrator, 2 = Cashier (confirmed by
  `AuthController::login()`'s redirect logic — role 2 goes to the POS
  terminal — and by `UserManagementController::save()`'s comment "New
  employee accounts created from User Management are Cashier accounts
  only" gating new accounts to `role_id === 2`). Per the comment, a Cashier
  (role_id 2) hitting a page guarded by `AuthMiddleware::dataManagement()`
  should be let through.
- **Actual behavior**: The implementation denies everyone except role_id 1:
  ```php
  public static function dataManagement(?User $userModel = null): void
  {
      self::check($userModel);

      if ((int) ($_SESSION['role_id'] ?? 0) !== 1) {
          http_response_code(403);
          echo '... Access Denied ... available to Administrator accounts only ...';
          exit;
      }
  }
  ```
  A Cashier is served the "Access Denied — This module is available to
  Administrator accounts only" page and process exits, exactly like a
  non-admin hitting `admin()`. Cashiers can never reach Data Management,
  contradicting the comment directly above the method.
- **Steps to reproduce**: With an active session for a user whose
  `role_id` is 2 (Cashier), call `AuthMiddleware::dataManagement()` (DI
  seam: pass a stub `User` whose `getById()` returns
  `['role_id' => 2, 'account_status' => 'Active', ...]`). Observe the
  response is the Administrator-only 403 page instead of a normal return.
  Reproduced out-of-process in the cited test (spawns a child PHP process
  so the method's own `exit;` doesn't terminate the test run); the child's
  stdout contains the "Access Denied" page instead of continuing past the
  guard.
- **Why this looks like a real bug (not a bad test assumption)**: The
  doc comment directly above `dataManagement()` explicitly and
  unambiguously states Cashiers may access this module — the code's own
  stated intent, not a preference the test team is imposing. The `!== 1`
  check appears to be a copy/paste of `admin()`'s Administrator-only check
  that was never updated to also allow role_id 2 for this specifically
  Cashier-inclusive guard.

---

## Notes (not defects)

- `app/Helpers/Auth.php` is a 0-byte file with no code and nothing else in
  the app requires it — there is nothing to unit test, so no
  `AuthTest.php` was written for it.
- The legacy root-level files (`controllers/Authcontrollers.php`,
  `models/users.php`, `middlewares/auth.php`, `middlewares/guest.php`)
  were left untouched, per scope — nothing in the live app references
  them (confirmed no requires of these paths; `index.php` redirects
  straight to `app/auth/login.php`).
- Password handling was specifically checked across `User::register()`,
  `User Management::create()/update()`, and `AuthController::login()`:
  passwords are always hashed with `password_hash()` before storage and
  compared with `password_verify()` on login, never in plaintext (see
  `UserTest::test_register_hashes_password_and_inserts_when_available`,
  `UserManagementTest::test_create_hashes_password_and_converts_empty_optional_fields_to_null`,
  `UserManagementTest::test_update_with_password_change_hashes_and_includes_password_field`,
  and `AuthControllerTest::test_login_never_compares_password_in_plaintext`).
  No defect found here.
- Login failure messages were checked for username enumeration: a
  non-existent login and a wrong password for an existing account both
  return the exact same generic message
  (`AuthControllerTest::test_login_fails_with_generic_message_when_user_not_found`
  and `test_login_fails_with_same_generic_message_when_password_is_wrong`).
  This matches the code's own apparent intent (identical message string in
  both branches) — no defect found here.
