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

        <div class="card-icon">📦</div>

        <div>

            <h5>Total Products</h5>

            <h3><?= number_format($dashboardData['total_products']) ?></h3>

        </div>

    </div>

    <div class="card-box">

        <div class="card-icon">🗂</div>

        <div>

            <h5>Categories</h5>

            <h3><?= number_format($dashboardData['total_categories']) ?></h3>

        </div>

    </div>

    <div class="card-box">

        <div class="card-icon">⚠</div>

        <div>

            <h5>Low Stock</h5>

            <h3><?= number_format($dashboardData['low_stock']) ?></h3>

        </div>

    </div>

    <div class="card-box">

        <div class="card-icon">❌</div>

        <div>

            <h5>Out of Stock</h5>

            <h3><?= number_format($dashboardData['out_of_stock']) ?></h3>

        </div>

    </div>

</div>