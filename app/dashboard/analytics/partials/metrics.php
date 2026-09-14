


<?php
/**
 * ==========================================================
 * Dashboard Metrics
 * Displays the four KPI cards
 * ==========================================================
 */

/** @var array $dashboardData */
?>

<div class="dashboard-cards">

    <div class="card-box">
        <div class="card-icon">💰</div>
        <div>
            <h5>Total Sales</h5>
            <h3>₱<?= number_format($dashboardData['total_sales'], 2); ?></h3>
        </div>
    </div>

    <div class="card-box">
        <div class="card-icon">🛒</div>
        <div>
            <h5>Items Sold</h5>
            <h3><?= number_format($dashboardData['items_sold']); ?></h3>
        </div>
    </div>

    <div class="card-box">
        <div class="card-icon">🧾</div>
        <div>
            <h5>Transactions</h5>
            <h3><?= number_format($dashboardData['transactions']); ?></h3>
        </div>
    </div>

<?php

$salesGrowthDetails =
    $dashboardData['sales_growth_details']
    ?? [];

$growthPercent =
    $salesGrowthDetails['growth_percent']
    ?? null;

$hasPreviousPeriod =
    $salesGrowthDetails['has_previous_period']
    ?? false;

$hasCurrentPeriod =
    $salesGrowthDetails['has_current_period']
    ?? false;

$comparisonLabel =
    $salesGrowthDetails['comparison_label']
    ?? 'vs previous period';

?>

<div class="card-box">

    <div class="card-icon">📈</div>

    <div>

        <h5>Sales Growth</h5>

        <?php if (!$hasPreviousPeriod && $hasCurrentPeriod): ?>

            <h3>NEW</h3>

            <small>
                No previous sales
            </small>

        <?php elseif (!$hasPreviousPeriod && !$hasCurrentPeriod): ?>

            <h3>—</h3>

            <small>
                No sales comparison
            </small>

        <?php else: ?>

            <h3>
                <?= $growthPercent > 0 ? '+' : ''; ?>
                <?= number_format($growthPercent, 2); ?>%
            </h3>

            <small>
                <?= htmlspecialchars($comparisonLabel); ?>
            </small>

        <?php endif; ?>

    </div>

</div>

</div>