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
| Administrators and Cashiers may access Data Management.
|
| Cashiers are intentionally limited to the actions exposed by the
| Data Management interface, currently dataset viewing/exporting.
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