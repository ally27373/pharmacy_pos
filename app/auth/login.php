<?php
// =====================================================
// LOGIN PAGE
// Pharmacy POS System
// Phase 4.1
// =====================================================
?>

<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../Controllers/AuthController.php';

$controller = new AuthController();

$message = '';

if (!empty($_SESSION['auth_message'])) {

    $message = $_SESSION['auth_message'];

    unset($_SESSION['auth_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $result = $controller->login($_POST);

    if ($result['success']) {

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

                <h5 class="pharmacy-name">

                    NICA X4NDRA PHARMACY

                </h5>

                <h1 class="system-title">

                    MANAGEMENT<br>

                    SYSTEM

                </h1>

                <div class="features">

                    <div>

                        <i class="bi bi-capsule"></i>

                        Inventory Control

                    </div>

                    <div>

                        <i class="bi bi-cash-stack"></i>

                        Transaction Processing

                    </div>

                    <div>

                        <i class="bi bi-bar-chart-fill"></i>

                        Sales Reporting

                    </div>

                </div>

            </div>

            <!-- ==========================
                 RIGHT SIDE
            =========================== -->

            <div class="col-lg-6 right-side">

                <div class="login-card">

                <?php if (!empty($message)): ?>

                    <div class="alert alert-danger">

                        <?= htmlspecialchars($message); ?>

                    </div>

                <?php endif; ?>

                    <form
                        method="POST"
                        id="loginForm">

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