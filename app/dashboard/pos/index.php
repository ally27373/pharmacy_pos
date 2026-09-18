<?php

require_once '../../../config/session.php';
require_once '../../Middleware/AuthMiddleware.php';

AuthMiddleware::check();

$menu = "pos";
$page = $_GET['page'] ?? "terminal";

require_once '../../../includes/header.php';

?>

<link rel="stylesheet" href="/assets/css/dashboard-shell.css">
<link rel="stylesheet" href="/assets/css/sidebar.css">
<link rel="stylesheet" href="/assets/css/navbar.css">
<link rel="stylesheet" href="/assets/css/pos.css">

<div class="dashboard-wrapper">

    <?php include '../../../includes/sidebar.php'; ?>

    <div class="main-content">

        <?php include '../../../includes/navbar.php'; ?>

        <div class="dashboard-content">

            <?php

            switch($page){

                case "sales":
                    include "pages/sales.php";
                    break;

                case "billings":
                    include "pages/billings.php";
                    break;

                default:
                    include "pages/terminal.php";

            }

            ?>

        </div>

    </div>

</div>

<?php require_once '../../../includes/footer.php'; ?>