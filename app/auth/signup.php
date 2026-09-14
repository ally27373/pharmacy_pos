<?php

declare(strict_types=1);

http_response_code(403);

?>
<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Registration Disabled</title>

</head>

<body
    style="
        font-family: Arial, sans-serif;
        padding: 40px;
        background: #f8f9fa;
    "
>

    <div
        style="
            max-width: 600px;
            margin: 80px auto;
            background: #ffffff;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
        "
    >

        <h2>
            Registration Disabled
        </h2>

        <p>
            Public account registration is disabled.
        </p>

        <p>
            Employee accounts can only be created
            by an Administrator.
        </p>

        <a href="login.php">
            Return to Login
        </a>

    </div>

</body>

</html>

<?php

require_once __DIR__ . '/../Controllers/AuthController.php';

$controller = new AuthController();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $result = $controller->register($_POST);

    if ($result['success']) {

        header("Location: login.php?registered=1");
        exit;
    }

    $message = $result['message'];
}

if (!isset($_SESSION['user_id'])) {

    http_response_code(403);

    exit('Public registration is disabled.');

}

require_once '../../includes/header.php';
?>

<div class="wrapper">


<?php if (!empty($message)): ?>

<div class="alert alert-danger">
    <?= htmlspecialchars($message) ?>
</div>

<?php endif; ?>

<div class="container-fluid">

<div class="row login-row">

<!-- Left Side -->

<div class="col-lg-6 left-side">

    <h5 class="pharmacy-name">

        NICA XANDRA PHARMACY

    </h5>

    <h1 class="system-title">

        CREATE<br>ACCOUNT

    </h1>

    <div class="features">

        <div>

            <i class="bi bi-shield-check"></i>

            Secure User Authentication

        </div>

        <div>

            <i class="bi bi-person-plus"></i>

            Employee Registration

        </div>

        <div>

            <i class="bi bi-lock-fill"></i>

            Password Encryption

        </div>

    </div>

</div>

<!-- Right Side -->

<div class="col-lg-6 right-side">

    <div class="login-card">

        <h2 class="text-center mb-4">

Create Account

</h2>

<form
method="POST"
id="signupForm">

<div class="mb-3">

<label class="form-label">

Full Name

</label>

<input
type="text"
name="full_name"
class="form-control"
required>



</div>

<div class="mb-3">

<label class="form-label">

Username

</label>

<input
type="text"
name="username"
id="username"
class="form-control"
maxlength="20"
autocomplete="off"
required>

<small
id="usernameMessage">

</small>

</div>

<div class="mb-3">

    <label class="form-label">

        Email Address

    </label>

    <input
        type="email"
        name="email"
        id="email"
        class="form-control"
        autocomplete="off"
        required>

    <small
        id="emailMessage">

    </small>

</div>

<div class="mb-3">

<label class="form-label">

Contact Number

</label>

<input
type="text"
name="contact"
id="contact"
class="form-control"
maxlength="13"
placeholder="09171234567"
autocomplete="off"
required>

<small
id="contactMessage">

</small>

</div>

<div class="mb-3">

<label class="form-label">

Password

</label>

<input
type="password"
name="password"
id="password"
class="form-control"
required>

<div class="mt-2">

    <div
        class="progress"
        style="height:8px;">

        <div
            id="passwordStrengthBar"
            class="progress-bar"
            style="width:0%;">

        </div>

    </div>

    <small
        id="passwordStrengthText"
        class="text-muted">

        Password strength

    </small>

</div>

</div>

<div class="mb-3">

    <label class="form-label">

        Confirm Password

    </label>

    <input
        type="password"
        name="confirm_password"
        id="confirmPassword"
        class="form-control"
        required>

    <small
        id="passwordMatchMessage"
        class="text-muted">

    </small>

</div>

<input
type="hidden"
name="role"
value="Staff">

<button
class="btn login-btn w-100"
type="submit">

Create Account

</button>

<div class="text-center mt-3">

Already have an account?

<a href="login.php">

Sign In

</a>

</div>

</form>

    </div>

</div>

</div>

</div>

</div>

<?php require_once '../../includes/footer.php'; ?>