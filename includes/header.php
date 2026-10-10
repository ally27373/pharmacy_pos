<?php
// ======================================================
// HEADER
// Shared by every page (auth + dashboard) — includes the
// mobile viewport meta tag every responsive rule depends on.
// ======================================================

require_once __DIR__ . '/../app/Helpers/Csrf.php';

/*
 * Start the session before a single byte of output.
 *
 * The CSRF token is rendered into <meta> further down and has to survive to
 * the next request. Every page that includes this file already had a session
 * started at the top, so this was a no-op -- until it wasn't. If a page ever
 * reaches the header without one, Csrf::token() would call session_start()
 * after the doctype had been echoed: PHP warns, the session silently fails
 * to persist, and every subsequent POST is rejected as a bad token. Failing
 * quietly in a way that only shows up as "403 for no reason" is the worst
 * possible failure mode here, so the session is opened up front instead.
 */
if (session_status() === PHP_SESSION_NONE) {

    session_start();

}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Nica Xandra Pharmacy POS</title>

    <link rel="icon" type="image/png" href="/assets/image/nica-xandra-logo.png">

    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

        <link rel="stylesheet" href="/assets/css/style.css">
        <link rel="stylesheet" href="/assets/css/auth.css">
        <link rel="stylesheet" href="/assets/css/polish.css">
        <link rel="stylesheet" href="/assets/css/swal.css">
        <link rel="stylesheet" href="/assets/css/search.css">

        <script src="/assets/js/search.js"></script>
        <script src="/assets/js/csrf.js"></script>

    <?php
    /*
     | CSRF token for state-changing requests. Emitted here rather than per
     | page so every screen carries it, including login.php, which includes
     | this file after handling its own POST.
     |
     | The session is opened at the top of this file, so reading the token
     | here cannot warn about headers already being sent.
     */
    ?>
        <meta name="csrf-token" content="<?= htmlspecialchars(
            Csrf::token(),
            ENT_QUOTES,
            'UTF-8'
        ) ?>">

    <!-- SweetAlert2: every confirm()/alert() goes through NxSwal.
         Loaded here (not in footer) so pages like receipt.php that skip
         includes/footer.php still get the dialogs. -->

    <script
        src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="/assets/js/swal.js"></script>

    <!-- Google Font -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">


</head>

<body>