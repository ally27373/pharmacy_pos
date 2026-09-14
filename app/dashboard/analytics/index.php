<?php
/**
 * ==========================================================
 * Dashboard Metrics
 * Displays the four KPI cards
 * ==========================================================
 */

/** @var array $dashboardData */
?>


<?php

$period = $_GET['period'] ?? '7days';

?>

<?php

require_once '../../../config/session.php';
require_once '../../Middleware/AuthMiddleware.php';
require_once '../../../vendor/autoload.php';

AuthMiddleware::admin();

$menu = 'dashboard';
$page = 'analytics';



require_once '../../../includes/header.php';

require_once '../../Controllers/DashboardController.php';

$dashboardController = new DashboardController();

$dashboardData = $dashboardController->getDashboardData($period);
?>




<link rel="stylesheet" href="/pharmacy_pos/assets/css/dashboard.css">
<link rel="stylesheet" href="/pharmacy_pos/assets/css/sidebar.css">
<link rel="stylesheet" href="/pharmacy_pos/assets/css/navbar.css">
<link rel="stylesheet" href="/pharmacy_pos/assets/css/charts.css">
<link rel="stylesheet" href="/pharmacy_pos/assets/css/analytics.css">

<div class="dashboard-wrapper">

   <?php include '../../../includes/sidebar.php'; ?>

    <div class="main-content">

        <?php include '../../../includes/navbar.php'; ?>

        <div class="dashboard-content">

     <?php include __DIR__.'/partials/filters.php'; ?>

    <?php include __DIR__.'/partials/metrics.php'; ?>

    <!-- Load Chart.js BEFORE chart partials -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="/pharmacy_pos/assets/js/dashboard.js"></script>


    <div class="analytics-dashboard-layout">

        <div class="analytics-forecast-row">
            <?php include __DIR__.'/partials/forecasting.php'; ?>
        </div>

        <div class="analytics-two-column-row">
            <?php include __DIR__.'/partials/revenue_chart.php'; ?>
            <?php include __DIR__.'/partials/sales_chart.php'; ?>
        </div>

        <div class="analytics-three-column-row">
            <?php include __DIR__.'/partials/payment_chart.php'; ?>
            <?php include __DIR__.'/partials/demand_chart.php'; ?>
            <?php include __DIR__.'/partials/best_seller.php'; ?>
        </div>

    </div>

</div>

    </div>

</div>

<?php require_once '../../../includes/footer.php'; ?>

<script>
function changePeriod(period)
{
    const url = new URL(window.location.href);

    url.searchParams.set('period', period);

    window.location.href = url.toString();
}

function changeSalesYear(year)
{
    const url =
        new URL(window.location.href);

    url.searchParams.set(
        'sales_year',
        year
    );

    window.location.href =
        url.toString();
}

function changeDemandPeriod(month)
{
    const url =
        new URL(window.location.href);

    url.searchParams.set(
        'demand_month',
        month
    );

    url.searchParams.set(
        'demand_year',
        new Date().getFullYear()
    );

    window.location.href =
        url.toString();
}
</script>

