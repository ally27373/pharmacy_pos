<?php
// =====================================================
// LOGIN PAGE
// Nica Xandra Pharmacy Management System
// Phase 4.1
// =====================================================
?>

<?php

/*
 * Report everything to the server log, never to the page.
 *
 * This file used to set display_errors to 1 and was the only file in the
 * project that did. It is also the one page anyone can reach without an
 * account, so a malformed request that made a query fail printed the SQL,
 * the bound parameters and the absolute paths of config/ and app/ straight
 * into the response -- free reconnaissance for anyone probing the form.
 *
 * log_errors stays on, so nothing is lost: the same detail is in the
 * server's error log, which is where it belongs.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../Helpers/Csrf.php';
require_once __DIR__ . '/../Controllers/AuthController.php';

$controller = new AuthController();

$message = '';

if (!empty($_SESSION['auth_message'])) {

    $message = $_SESSION['auth_message'];

    unset($_SESSION['auth_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     | Login CSRF: without this, a page elsewhere could silently sign a
     | visitor into an account the attacker controls, and whatever they then
     | type into the app would land on the attacker's records.
     |
     | Rendered as HTML rather than JSON because this is a real navigation.
     */
    Csrf::requireValid(false);

    $result = $controller->login($_POST);

    if ($result['success']) {

        /*
         | The visitor is now authenticated, so the token they held while
         | anonymous is retired. The next page render mints a fresh one.
         */
        Csrf::reset();

        header('Location: ' . $result['redirect']);
        exit;
    }

    $message = $result['message'];
}

require_once '../../includes/header.php';

?>





<div class="wrapper">

    <!-- =============================
         TOP NAVIGATION
    ============================== -->



     


    <!-- =============================
         MAIN CONTENT
    ============================== -->

    <div class="container-fluid">

        <div class="row login-row">

            <!-- ==========================
                 LEFT SIDE
            =========================== -->

            <div class="col-lg-6 left-side">
                <div class="left-decor-corner"></div>
                <div class="left-decor-bottom"></div>

                <div class="brand-header">
                    <div class="brand-icon-box">
                        <i class="bi bi-capsule-fill"></i>
                    </div>
                    <div>
                        <h2>NICA XANDRA</h2>
                        <div class="brand-subtitle">Nica Xandra Pharmacy Management System</div>
                    </div>
                </div>

                <h2 class="welcome-title">Welcome Back</h2>

                <p class="welcome-subtitle">
                    Sign in to manage your inventory, sales, and reports.
                </p>

            </div>

            <!-- ==========================
                 RIGHT SIDE
            =========================== -->

            <div class="col-lg-6 right-side">

                <div class="login-card">

                <div class="login-card-header mb-4 text-center">
                    <i class="bi bi-shield-lock-fill" style="font-size:42px; color:#0F8A6B; opacity:.9;"></i>
                    <h3 style="font-weight:800; letter-spacing:-.03em; color:#1e1b1b; margin-top:14px; font-size:22px;">Sign In</h3>
                    <p style="color:#7b8794; font-size:14px; margin-top:4px;">Enter your credentials to open your account</p>
                </div>

                <?php if (!empty($message)): ?>

                    <div class="alert alert-danger">

                        <?= htmlspecialchars($message); ?>

                    </div>

                <?php endif; ?>

                    <form
                        method="POST"
                        id="loginForm">

                        <?= Csrf::field() ?>

                        <div class="mb-4">

                            <label class="form-label">

                                Username

                            </label>

                            <div class="input-group">

                                <span class="input-group-text">

                                    <i class="bi bi-person"></i>

                                </span>

                                <input

                                    type="text"

                                    name="login"

                                    class="form-control"

                                    placeholder="Enter Username"

                                    required>

                            </div>

                        </div>

                        <div class="mb-4">

                            <label class="form-label">

                                Password

                            </label>

                            <div class="input-group">

                                <span class="input-group-text">

                                    <i class="bi bi-lock"></i>

                                </span>

                                <input

                                    id="password"

                                    type="password"

                                    name="password"

                                    class="form-control"

                                    placeholder="Enter Password"

                                    required>

                                <button

                                    class="btn btn-outline-secondary"

                                    type="button"

                                    id="togglePassword">

                                    <i class="bi bi-eye-slash"></i>

                                </button>

                            </div>

                        </div>

                        <div class="d-flex justify-content-center align-items-center mb-3">

    <div class="d-flex align-items-center gap-2">

        <input
            type="checkbox"
            id="remember">

        <label for="remember" class="mb-0">

            Remember Me

        </label>

    </div>

</div>
                        <button
                            class="btn login-btn w-100"

                            type="submit">

                            <i class="bi bi-box-arrow-in-right"></i>

                            SIGN IN

                        </button>


                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

<?php include '../../includes/footer.php'; ?>