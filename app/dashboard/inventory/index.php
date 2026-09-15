<?php
require_once "../../../config/session.php";
require_once "../../Middleware/AuthMiddleware.php";
require_once "../../../vendor/autoload.php";
AuthMiddleware::admin();
$menu="dashboard"; $page="inventory_dashboard";
require_once "../../../includes/header.php";
require_once "../../Controllers/DashboardController.php";
$dashboardController=new DashboardController();
$dashboardData=$dashboardController->getDashboardData();
?>
<link rel="stylesheet" href="/assets/css/dashboard.css">
<link rel="stylesheet" href="/assets/css/charts.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="/assets/css/inventory.css">
<link rel="stylesheet" href="/assets/css/sidebar.css">
<link rel="stylesheet" href="/assets/css/navbar.css">
<div class="dashboard-wrapper">
<?php include "../../../includes/sidebar.php"; ?>
<div class="main-content">
<?php include "../../../includes/navbar.php"; ?>
<main class="dashboard-content inventory-dashboard-layout">
<section class="inventory-metrics-row"><?php include __DIR__."/partials/metrics.php"; ?></section>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<section class="inventory-full-row inventory-distribution-row"><?php include __DIR__."/partials/quantity_chart.php"; ?></section>
<section class="inventory-full-row inventory-current-row"><?php include __DIR__."/partials/current_inventory.php"; ?></section>
<section class="inventory-full-row inventory-demand-row"><?php include __DIR__."/partials/product_demand.php"; ?></section>
<section class="inventory-two-column-row inventory-operational-row"><div class="inventory-column-panel"><?php include __DIR__."/partials/fast_moving.php"; ?></div><div class="inventory-column-panel"><?php include __DIR__."/partials/near_expiry.php"; ?></div></section>
<section class="inventory-full-row inventory-insights-row"><?php include __DIR__."/partials/inventory_insights.php"; ?></section>
</main></div></div>
<script src="/assets/js/dashboard.js"></script>
<?php require_once "../../../includes/footer.php"; ?>