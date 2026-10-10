<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/User.php';

class AuthMiddleware
{
    public static function check(?User $userModel = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        /*
        |--------------------------------------------------------------------------
        | No authenticated session
        |--------------------------------------------------------------------------
        */

        if (!isset($_SESSION['user_id'])) {

            self::redirectToLogin();

        }


        /*
        |--------------------------------------------------------------------------
        | Verify current user still exists
        |--------------------------------------------------------------------------
        */

        $userModel = $userModel ?? new User();

        $user = $userModel->getById(
            (int) $_SESSION['user_id']
        );


        if (!$user) {

            self::destroySession();

            self::redirectToLogin();

        }


        /*
        |--------------------------------------------------------------------------
        | Verify account is still Active
        |--------------------------------------------------------------------------
        */

        if (($user['account_status'] ?? 'Inactive') !== 'Active') {

            self::destroySession();

            self::redirectToLogin(
                'Your account is no longer active. Please contact an Administrator.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Refresh session information
        |--------------------------------------------------------------------------
        |
        | This protects against role changes while the user is logged in.
        |
        */

        $_SESSION['username'] = $user['username'];

        $_SESSION['role_id'] = (int) $user['role_id'];
    }


    /*
    |--------------------------------------------------------------------------
    | Administrator-only access
    |--------------------------------------------------------------------------
    */

    public static function admin(?User $userModel = null): void
    {
        self::check($userModel);

        if ((int) ($_SESSION['role_id'] ?? 0) !== 1) {

            http_response_code(403);

            echo '<!doctype html>
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <title>Access Denied</title>
            </head>

            <body style="font-family:Arial,sans-serif;padding:40px">

                <h2>Access Denied</h2>

                <p>
                    This module is available to Administrator accounts only.
                </p>

                <p>
                    <a href="../pos/index.php">
                        Return to POS Terminal
                    </a>
                </p>

            </body>
            </html>';

            exit;
        }
    }

    /*
|--------------------------------------------------------------------------
| Data Management Access
|--------------------------------------------------------------------------
|
| Administrator-only access.
|
| The Data Management module (dataset viewing/exporting) is surfaced only
| under the Administrator-only navigation section (see includes/sidebar.php)
| and is not exposed to Cashier accounts anywhere in the app.
|
|--------------------------------------------------------------------------
*/

public static function dataManagement(?User $userModel = null): void
{
    self::check($userModel);

    if ((int) ($_SESSION['role_id'] ?? 0) !== 1) {

        http_response_code(403);

        echo '<!doctype html>
        <html lang="en">

        <head>
            <meta charset="utf-8">
            <title>Access Denied</title>
        </head>

        <body style="font-family:Arial,sans-serif;padding:40px">

            <h2>Access Denied</h2>

            <p>
                This module is available to Administrator accounts only.
            </p>

            <p>
                <a href="/app/dashboard/pos/index.php?page=terminal">
                    Return to POS Terminal
                </a>
            </p>

        </body>

        </html>';

        exit;
    }
}


    /*
    |--------------------------------------------------------------------------
    | API access control
    |--------------------------------------------------------------------------
    |
    | admin() and dataManagement() render an HTML page when they refuse,
    | which is wrong for an ajax/ endpoint: fetch() would follow the
    | redirect and inject a whole login page into a modal, and a JSON
    | endpoint would answer with HTML under a JSON content type.
    |
    | These are the JSON-native equivalents. They validate the same chain
    | check() does -- session present, user still exists, account still
    | Active -- before applying a role rule, so an endpoint guarded by
    | them cannot be reached with a stale or deleted account either.
    |
    */

    private static function denyApi(int $status, string $message): never
    {
        if (!headers_sent()) {

            http_response_code($status);

            header(
                'Content-Type: application/json; charset=utf-8'
            );

        }

        echo json_encode([
            'status' => 'error',
            'message' => $message
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Any signed-in account (Admin or Cashier)
    |--------------------------------------------------------------------------
    */

    public static function requireAuthenticatedApi(?User $userModel = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {

            session_start();

        }

        if (!isset($_SESSION['user_id'])) {

            self::denyApi(
                401,
                'Authentication is required.'
            );

        }

        $userModel = $userModel ?? new User();

        $user = $userModel->getById(
            (int) $_SESSION['user_id']
        );

        if (!$user) {

            self::destroySession();

            self::denyApi(
                401,
                'Your account is no longer available. Please sign in again.'
            );

        }

        if (($user['account_status'] ?? 'Inactive') !== 'Active') {

            self::destroySession();

            self::denyApi(
                403,
                'Your account is no longer active.'
            );

        }

        /*
        | Refresh the cached role so a role change made while the user was
        | signed in takes effect on the very next request rather than the
        | next page load.
        */

        $_SESSION['username'] = $user['username'];

        $_SESSION['role_id'] = (int) $user['role_id'];

    }

    /*
    |--------------------------------------------------------------------------
    | Administrator-only API access
    |--------------------------------------------------------------------------
    */

    public static function requireAdminApi(?User $userModel = null): void
    {
        self::requireAuthenticatedApi($userModel);

        if ((int) ($_SESSION['role_id'] ?? 0) !== 1) {

            self::denyApi(
                403,
                'Administrator access is required.'
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Destroy authenticated session
    |--------------------------------------------------------------------------
    */

    private static function destroySession(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }


    /*
    |--------------------------------------------------------------------------
    | Redirect to Login
    |--------------------------------------------------------------------------
    */

    private static function redirectToLogin(
        string $message = ''
    ): never {

        if ($message !== '') {

            $_SESSION['auth_message'] = $message;

        }

        /*
         * All dashboard modules are inside:
         *
         * /app/dashboard/...
         *
         * Login is:
         *
         * /app/auth/login.php
         *
         * Use the application root path so this does not depend
         * on the current module's folder depth.
         */

        header(
            'Location: /app/auth/login.php'
        );

        exit;
    }
}