<?php
/** @var array $dashboardData */

$productForecast = $dashboardData['product_demand_forecast'] ?? [];
$products = $productForecast['products'] ?? [];
$metadata = $productForecast['metadata'] ?? [];
$isBaseline = (bool) ($metadata['baseline'] ?? true);
$trainingEnd = $metadata['training_end'] ?? null;
?>

<div class="dashboard-panel product-demand-panel">
    <div class="chart-header">
        <div class="chart-title">
            <h4>Product Demand Prediction</h4>
            <p class="chart-subtitle">SARIMA • Top products by expected 30-day demand</p>
        </div>
        <?php if (!empty($products)): ?>
            <span class="forecast-status<?= $isBaseline ? ' forecast-status-warning' : ''; ?>">
                <i class="bi <?= $isBaseline ? 'bi-info-circle-fill' : 'bi-check-circle-fill'; ?>"></i>
                <?= $isBaseline ? 'Baseline Prediction' : 'Updated Prediction'; ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if ($isBaseline && !empty($products)): ?>
        <div class="forecast-refresh-note forecast-notice-warning">
            <i class="bi bi-info-circle-fill"></i>
            <span>
                Product predictions are based on the validated historical baseline ending
                <?= htmlspecialchars((string) $trainingEnd); ?>. They are retained for reference until sufficient legitimate post-training product sales are available for an update.
            </span>
        </div>
    <?php endif; ?>

    <?php if (empty($products)): ?>
        <div class="empty-chart">
            <i class="bi bi-box-seam"></i>
            <h5>No Product Demand Prediction</h5>
            <p>Generate the product-level SARIMA forecast after sufficient product sales history is available.</p>
        </div>
    <?php else: ?>
        <div class="product-demand-table-wrap">
            <table class="product-demand-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>30-Day Demand</th>
                        <th>Avg/Day</th>
                        <th>Level</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $row): ?>
                    <?php
                    $level = strtolower((string) ($row['demand_level'] ?? 'unknown'));
                    $badgeClass = $level === 'high'
                        ? 'demand-badge-high'
                        : ($level === 'moderate' ? 'demand-badge-moderate' : 'demand-badge-low');
                    ?>
                    <tr>
                        <td><?= (int) $row['rank']; ?></td>
                        <td class="product-demand-name"><?= htmlspecialchars($row['product_name']); ?></td>
                        <td><strong><?= number_format((float) $row['forecast_30_day_units'], 1); ?></strong> units</td>
                        <td><?= number_format((float) $row['average_daily_forecast'], 2); ?></td>
                        <td><span class="demand-badge <?= $badgeClass; ?>"><?= htmlspecialchars($row['demand_level']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="forecast-model-note">
            <strong>SARIMA(1,0,2)(1,0,1,7)</strong>
            · Weekly seasonality
            · Minimum <?= (int) ($metadata['minimum_observed_sale_days'] ?? 60); ?> observed sale days per product
        </div>
    <?php endif; ?>
</div>

<style>
.product-demand-table-wrap { overflow-x: auto; }
.product-demand-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
.product-demand-table th, .product-demand-table td { padding: 10px 8px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: middle; }
.product-demand-table th { font-weight: 700; white-space: nowrap; }
.product-demand-name { min-width: 180px; font-weight: 600; }
.demand-badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 0.74rem; font-weight: 700; }
.demand-badge-high { background: #fee2e2; color: #991b1b; }
.demand-badge-moderate { background: #fef3c7; color: #92400e; }
.demand-badge-low { background: #dcfce7; color: #166534; }
</style>
