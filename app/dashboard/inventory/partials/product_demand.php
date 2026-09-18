<?php
/** @var array $dashboardData */

$productForecast = $dashboardData['product_demand_forecast'] ?? [];
$products = $productForecast['products'] ?? [];
$metadata = $productForecast['metadata'] ?? [];
$isBaseline = (bool) ($metadata['baseline'] ?? true);
$trainingEnd = $metadata['training_end'] ?? null;
$selectedMonth = max(1, min(12, (int) ($_GET['demand_forecast_month'] ?? 1)));
$monthName = date('F', mktime(0, 0, 0, $selectedMonth, 1));

$monthRows = [];
foreach ($products as $row) {
    $monthly = $row['monthly_forecast_2026'] ?? [];
    $selectedUnits = 0.0;
    $actualUnits = 0.0;
    foreach ($monthly as $m) {
        if ((int) ($m['month'] ?? 0) === $selectedMonth) {
            $selectedUnits = (float) ($m['forecast_units'] ?? 0);
            $actualUnits = (float) ($m['actual_2025_units'] ?? 0);
            break;
        }
    }
    $copy = $row;
    $copy['selected_month_units'] = $selectedUnits;
    $copy['selected_month_actual_units'] = $actualUnits;
    $copy['selected_month_avg'] = $selectedUnits / max(1, cal_days_in_month(CAL_GREGORIAN, $selectedMonth, 2026));
    $monthRows[] = $copy;
}

usort($monthRows, static function ($a, $b) {
    return ($b['selected_month_units'] ?? 0) <=> ($a['selected_month_units'] ?? 0);
});

$chartProducts = array_slice($monthRows, 0, 10);
$chartLabels = array_map(static fn($r) => $r['product_name'], $chartProducts);
$chartValues = array_map(static fn($r) => round((float) $r['selected_month_units'], 2), $chartProducts);
?>

<div class="dashboard-panel product-demand-panel">
    <div class="chart-header product-demand-header">
        <div class="chart-title">
            <h4>📈 Product Demand Prediction</h4>
            <p class="chart-subtitle">SARIMA • Monthly demand outlook by product</p>
        </div>
        <div class="chart-controls">
            <select class="chart-select" onchange="updateProductDemandMonth(this.value)">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m; ?>" <?= $m === $selectedMonth ? 'selected' : ''; ?>>
                        <?= date('F', mktime(0, 0, 0, $m, 1)); ?> 2026
                    </option>
                <?php endfor; ?>
            </select>
            <?php if (!empty($products)): ?>
                <span class="forecast-status<?= $isBaseline ? ' forecast-status-warning' : ''; ?>">
                    <i class="bi <?= $isBaseline ? 'bi-info-circle-fill' : 'bi-check-circle-fill'; ?>"></i>
                    <?= $isBaseline ? 'Baseline Prediction' : 'Updated Prediction'; ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isBaseline && !empty($products)): ?>
        <div class="forecast-refresh-note forecast-notice-warning">
            <i class="bi bi-info-circle-fill"></i>
            <span>
                Monthly product demand is projected from the validated SARIMA baseline ending
                <?= htmlspecialchars((string) $trainingEnd); ?>. It is retained for reference until sufficient legitimate post-training product sales are available for an update.
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
        <div class="product-demand-month-heading">
            <div>
                <strong><?= htmlspecialchars($monthName); ?> 2026 demand</strong>
                <span>2025 observed units compared with the 2026 SARIMA outlook</span>
            </div>
        </div>

        <div class="product-demand-chart-container">
            <canvas id="productDemandMonthlyChart"></canvas>
        </div>

        <div class="product-demand-table-wrap">
            <table class="product-demand-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>2025 Actual</th>
                        <th>2026 Forecast</th>
                        <th>Avg/Day</th>
                        <th>Peak Month</th>
                        <th>Level</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($monthRows as $index => $row): ?>
                    <?php
                    $level = strtolower((string) ($row['demand_level'] ?? 'unknown'));
                    $badgeClass = $level === 'high' ? 'demand-badge-high' : ($level === 'moderate' ? 'demand-badge-moderate' : 'demand-badge-low');
                    ?>
                    <tr class="product-demand-row">
                        <td><?= $index + 1; ?></td>
                        <td class="product-demand-name"><?= htmlspecialchars((string) $row['product_name']); ?></td>
                        <td><?= number_format((float) $row['selected_month_actual_units'], 1); ?> units</td>
                        <td><strong><?= number_format((float) $row['selected_month_units'], 1); ?></strong> units</td>
                        <td><?= number_format((float) $row['selected_month_avg'], 2); ?></td>
                        <td><?= htmlspecialchars((string) ($row['peak_month'] ?? '—')); ?></td>
                        <td><span class="demand-badge <?= $badgeClass; ?>"><?= htmlspecialchars((string) ($row['demand_level'] ?? 'Unknown')); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="product-demand-pagination" class="product-demand-pagination">
            <div class="pagination-info"></div>
            <div class="pagination-controls">
                <button type="button" id="product-demand-prev" class="btn btn-outline-secondary btn-sm">Previous</button>
                <span class="pagination-page"></span>
                <button type="button" id="product-demand-next" class="btn btn-outline-secondary btn-sm">Next</button>
            </div>
        </div>

        <div class="forecast-model-note">
            <strong>SARIMA(1,0,2)(1,0,1,7)</strong>
            · Weekly seasonality
            · 2025 observed product units + monthly 2026 SARIMA demand forecast
            · Minimum <?= (int) ($metadata['minimum_observed_sale_days'] ?? 60); ?> observed sale days per product
        </div>
    <?php endif; ?>
</div>

<style>
.product-demand-panel { width: 100%; }
.product-demand-header { align-items: center; }
.product-demand-header .chart-controls { flex-wrap: wrap; justify-content: flex-end; }
.product-demand-month-heading { display:flex; justify-content:space-between; align-items:center; margin:0 0 8px; }
.product-demand-month-heading strong { display:block; font-size:15px; color:#1f2937; }
.product-demand-month-heading span { display:block; margin-top:2px; font-size:12px; color:#8a94a6; }
.product-demand-chart-container { position:relative; width:100%; height:230px; margin-bottom:14px; }
.product-demand-chart-container canvas { width:100% !important; height:100% !important; }
.product-demand-table-wrap { overflow-x:auto; -webkit-overflow-scrolling: touch; }
.product-demand-table { width:100%; min-width:680px; border-collapse:collapse; font-size:.86rem; }
.product-demand-table th, .product-demand-table td { padding:9px 8px; border-bottom:1px solid #e5e7eb; text-align:left; vertical-align:middle; }
.product-demand-table th { font-weight:700; white-space:nowrap; }
.product-demand-table tbody tr:hover { background:#f8fafc; }
.product-demand-name { min-width:190px; font-weight:600; }
.demand-badge { display:inline-block; padding:4px 8px; border-radius:999px; font-size:.72rem; font-weight:700; white-space:nowrap; }
.demand-badge-high { background:#fee2e2; color:#991b1b; }
.demand-badge-moderate { background:#fef3c7; color:#92400e; }
.demand-badge-low { background:#dcfce7; color:#166534; }
.forecast-model-note { margin-top:12px; padding-top:10px; border-top:1px solid #e5e7eb; font-size:.78rem; color:#6b7280; line-height:1.45; }
.forecast-refresh-note { display:flex; align-items:flex-start; gap:10px; margin-bottom:14px; padding:10px 12px; border-radius:8px; font-size:.8rem; line-height:1.45; }
.forecast-notice-warning { background:#fff8e6; color:#795500; border-left:4px solid #f0ad00; }
.forecast-status-warning { background:#fff8e6; color:#795500; }
.product-demand-pagination { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-top:12px; padding-top:10px; border-top:1px solid #e5e7eb; }
.product-demand-pagination .pagination-info { font-size:.78rem; color:#6b7280; }
.product-demand-pagination .pagination-controls { display:flex; align-items:center; gap:10px; }
.product-demand-pagination .pagination-page { min-width:82px; text-align:center; font-size:.78rem; color:#4b5563; }
.product-demand-pagination button { min-height:44px; padding:8px 14px; }
.product-demand-pagination button:disabled { opacity:.5; cursor:not-allowed; }
@media (max-width:768px) { .product-demand-header { align-items:flex-start; flex-direction:column; } .product-demand-header .chart-controls { width:100%; justify-content:flex-start; } .product-demand-pagination { flex-direction:column; align-items:stretch; } .product-demand-pagination .pagination-info { text-align:center; } .product-demand-pagination .pagination-controls { justify-content:center; } }
</style>

<?php if (!empty($products)): ?>
<script>
function updateProductDemandMonth(month) {
    const url = new URL(window.location.href);
    url.searchParams.set('demand_forecast_month', month);
    window.location.href = url.toString();
}

document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('productDemandMonthlyChart');
    if (canvas && typeof Chart !== 'undefined') {
        const labels = <?= json_encode($chartLabels); ?>;
        const values = <?= json_encode($chartValues); ?>;
        const actualValues = <?= json_encode(array_map(static fn($r) => round((float) ($r['selected_month_actual_units'] ?? 0), 2), $chartProducts)); ?>;
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: '2025 Actual',
                        data: actualValues,
                        backgroundColor: 'rgba(37,99,235,0.22)',
                        borderColor: '#2563eb',
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 18
                    },
                    {
                        label: '2026 SARIMA Forecast',
                        data: values,
                        backgroundColor: 'rgba(21,149,119,0.72)',
                        borderColor: '#159577',
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 18
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: { callbacks: { label: ctx => 'Expected: ' + Number(ctx.raw).toFixed(1) + ' units' } }
                },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Expected Units' } },
                    y: { ticks: { autoSkip: false, font: { size: 11 } } }
                }
            }
        });
    }

    const rows = Array.from(document.querySelectorAll('.product-demand-row'));
    const pagination = document.getElementById('product-demand-pagination');
    if (!pagination || !rows.length) return;
    const info = pagination.querySelector('.pagination-info');
    const pageLabel = pagination.querySelector('.pagination-page');
    const previous = document.getElementById('product-demand-prev');
    const next = document.getElementById('product-demand-next');
    const perPage = 5;
    let page = 1;
    const totalPages = Math.max(1, Math.ceil(rows.length / perPage));

    function render() {
        const start = (page - 1) * perPage;
        const end = Math.min(start + perPage, rows.length);
        rows.forEach((row, i) => row.style.display = i >= start && i < end ? '' : 'none');
        info.textContent = `Showing ${start + 1}–${end} of ${rows.length} products`;
        pageLabel.textContent = `Page ${page} of ${totalPages}`;
        previous.disabled = page === 1;
        next.disabled = page === totalPages;
    }
    previous.addEventListener('click', () => { if (page > 1) { page--; render(); } });
    next.addEventListener('click', () => { if (page < totalPages) { page++; render(); } });
    render();
});
</script>
<?php endif; ?>
