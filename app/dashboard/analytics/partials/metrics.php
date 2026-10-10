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

    <div
        class="card-box kpi-card"
        data-kpi="sales"
        role="button"
        tabindex="0"
        aria-haspopup="dialog"
        title="View the sales behind this total"
    >
        <div class="card-icon">💰</div>
        <div>
            <h5>Total Sales</h5>
            <h3>₱<?= number_format($dashboardData['total_sales'], 2); ?></h3>
            <span class="kpi-card-hint">View transactions</span>
        </div>
    </div>

    <div
        class="card-box kpi-card"
        data-kpi="items"
        role="button"
        tabindex="0"
        aria-haspopup="dialog"
        title="View the products behind this count"
    >
        <div class="card-icon">🛒</div>
        <div>
            <h5>Items Sold</h5>
            <h3><?= number_format($dashboardData['items_sold']); ?></h3>
            <span class="kpi-card-hint">View products</span>
        </div>
    </div>

    <div
        class="card-box kpi-card"
        data-kpi="transactions"
        role="button"
        tabindex="0"
        aria-haspopup="dialog"
        title="View every transaction in this period"
    >
        <div class="card-icon">🧾</div>
        <div>
            <h5>Transactions</h5>
            <h3><?= number_format($dashboardData['transactions']); ?></h3>
            <span class="kpi-card-hint">View all</span>
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

    <div
        class="card-box kpi-card"
        data-kpi="growth"
        role="button"
        tabindex="0"
        aria-haspopup="dialog"
        title="View how this period compares to the previous one"
    >

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

            <span class="kpi-card-hint">View comparison</span>

        </div>

    </div>

</div>

<!--
    One shared modal serves all four cards. dashboard.js fills the title,
    body and footer from the metric on the card that was clicked.
-->
<div
    class="modal fade"
    id="kpiDetailModal"
    tabindex="-1"
    aria-labelledby="kpiDetailTitle"
    aria-hidden="true"
>
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="kpiDetailTitle">KPI details</h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>
            </div>

            <div class="modal-body" id="kpiDetailBody"></div>

            <div class="modal-footer justify-content-between" id="kpiDetailFooter"></div>

        </div>

    </div>
</div>