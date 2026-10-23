<?php
/** @var array $dashboardData */

/*
 * The annual artifact has two supported schemas:
 * 1) Legacy 2026 artifact: actual_2025 / forecast_2026
 * 2) Dynamic current-year artifact: actual_units / forecast_units
 *
 * Keep the legacy schema readable so the dashboard never loses its
 * Actual-vs-Forecast chart while the dynamic artifact is being generated.
 */
$annual = $dashboardData['annual_forecast'] ?? [];
if (empty($annual)) {
    // Backward compatibility with a controller/source that still exposes the
    // old fixed key while the dynamic controller is being deployed.
    $annual = $dashboardData['annual_forecast_2026'] ?? [];
}

$annualMonths = is_array($annual['months'] ?? null) ? $annual['months'] : [];
$annualMeta = is_array($annual['metadata'] ?? null) ? $annual['metadata'] : [];

// Keep the operational 30-day forecast metadata available for the status line.
$forecastStatus = $dashboardData['forecast_status'] ?? [];
if (empty($forecastStatus)) {
    try {
        $forecastStatus = (new \App\Services\ForecastService())->getStatus();
    } catch (Throwable $e) {
        $forecastStatus = [];
    }
}

$today = $forecastStatus['today'] ?? date('Y-m-d');
$forecastEnd = $forecastStatus['forecast_end'] ?? null;
$isStale = (bool) ($forecastStatus['is_stale'] ?? ($forecastEnd !== null && $forecastEnd < $today));
$statusLabel = $forecastStatus['display_label'] ?? ($isStale ? 'Baseline Forecast • Update Pending' : 'Baseline Forecast');
$modelOrder = $forecastStatus['model_order'] ?? [1, 0, 2];
$seasonalOrder = $forecastStatus['seasonal_order'] ?? [1, 0, 1, 7];
$modelText = 'SARIMA(' . implode(',', $modelOrder) . ')(' . implode(',', $seasonalOrder) . ')';
$metrics = $forecastStatus['evaluation_metrics'] ?? [];

$forecastYear = (int) ($annualMeta['forecast_year'] ?? date('Y'));
$actualYear = (int) ($annualMeta['actual_year'] ?? $forecastYear);
$actualThrough = $annualMeta['actual_data_through'] ?? null;

$actual = array_fill(0, 12, null);
$forecast = array_fill(0, 12, null);
$lower = array_fill(0, 12, null);
$upper = array_fill(0, 12, null);

$legacySchema = false;

foreach ($annualMonths as $row) {
    if (!is_array($row)) {
        continue;
    }

    $month = (int) ($row['month'] ?? 0);
    if ($month < 1 || $month > 12) {
        continue;
    }

    if (array_key_exists('actual_2025', $row) || array_key_exists('forecast_2026', $row)) {
        $legacySchema = true;
    }
}

foreach ($annualMonths as $row) {
    if (!is_array($row)) {
        continue;
    }

    $month = (int) ($row['month'] ?? 0);
    if ($month < 1 || $month > 12) {
        continue;
    }

    $index = $month - 1;

    if ($legacySchema) {
        // Existing deployed 2026 artifact. Preserve the original chart exactly:
        // 2025 actual sales versus 2026 SARIMA forecast.
        $actual[$index] = isset($row['actual_2025'])
            ? (float) $row['actual_2025']
            : null;

        $forecast[$index] = isset($row['forecast_2026'])
            ? (float) $row['forecast_2026']
            : null;
    } else {
        // New dynamic current-year artifact.
        $actual[$index] = isset($row['actual_units']) && $row['actual_units'] !== null
            ? (float) $row['actual_units']
            : null;

        $forecast[$index] = isset($row['forecast_units']) && $row['forecast_units'] !== null
            ? (float) $row['forecast_units']
            : null;
    }

    $lower[$index] = isset($row['lower_bound']) && $row['lower_bound'] !== null
        ? (float) $row['lower_bound']
        : null;

    $upper[$index] = isset($row['upper_bound']) && $row['upper_bound'] !== null
        ? (float) $row['upper_bound']
        : null;
}

$validMonths = 0;
foreach ($annualMonths as $row) {
    if (is_array($row)) {
        $month = (int) ($row['month'] ?? 0);
        if ($month >= 1 && $month <= 12) {
            $validMonths++;
        }
    }
}

$hasAnnualForecast = $validMonths === 12;

$actualValues = array_values(array_filter(
    $actual,
    static fn($v) => $v !== null
));

$forecastValues = array_values(array_filter(
    $forecast,
    static fn($v) => $v !== null
));

$actualTotal = array_sum($actualValues);
$forecastTotal = array_sum($forecastValues);

if ($legacySchema) {
    $chartActualLabel = 'Actual Sales (2025)';
    $chartForecastLabel = 'Forecast (2026)';
} else {
    $chartActualLabel = 'Actual Sales (' . $actualYear . ')';
    $chartForecastLabel = 'Forecast (' . $forecastYear . ')';
}

/*
 * Visual forecasting cluster (KPI cards + 30-day daily chart + top demand
 * medicines). Every number comes from recorded data or the deployed
 * artifacts — nothing is invented:
 *  - Trailing-30 actual totals (units/peso/transactions + per product):
 *    Dashboard::getRecentWindowTotals via the controller, anchored to the
 *    newest completed sale date so it matches the refit window exactly.
 *    Peso follows the dashboard's net-revenue convention (sale-item subtotals
 *    minus the proportional sale-level discount), the same basis as the
 *    Revenue by Category panel and getMonthlySales, so they always agree.
 *  - Next-30 predicted units + confidence band: deployed operational
 *    30-day SARIMA forecast rows.
 *  - Hold-out accuracy: the deployed artifact's own evaluation metadata
 *    (2025 hold-out backtest of the validated SARIMA configuration).
 *  - Per-product forecast: product_demand_forecast_30_day artifact
 *    (per-product SARIMA refits on live POS + imported history).
 */
$forecastDailyRows = is_array($dashboardData['forecast'] ?? null) ? $dashboardData['forecast'] : [];
$recentWindow = is_array($dashboardData['recent_window'] ?? null) ? $dashboardData['recent_window'] : [];
$productForecast = $dashboardData['product_demand_forecast'] ?? [];

$fxForecastDates = [];
$fxForecastSeries = [];
$fxLowerSeries = [];
$fxUpperSeries = [];
$fxPredicted30 = 0.0;
foreach ($forecastDailyRows as $fxRow) {
    if (!is_array($fxRow) || empty($fxRow['date']) || !array_key_exists('forecast_quantity', $fxRow)) {
        continue;
    }
    $fxForecastDates[] = (string) $fxRow['date'];
    $fxForecastSeries[] = round((float) $fxRow['forecast_quantity'], 2);
    $fxLowerSeries[] = isset($fxRow['lower_bound']) && $fxRow['lower_bound'] !== null
        ? round((float) $fxRow['lower_bound'], 2)
        : null;
    $fxUpperSeries[] = isset($fxRow['upper_bound']) && $fxRow['upper_bound'] !== null
        ? round((float) $fxRow['upper_bound'], 2)
        : null;
    $fxPredicted30 += (float) $fxRow['forecast_quantity'];
}
$fxPredicted30 = round($fxPredicted30, 2);

$fxHistoryByDate = [];
foreach ((is_array($dashboardData['historical_demand'] ?? null) ? $dashboardData['historical_demand'] : []) as $fxHRow) {
    if (is_array($fxHRow) && !empty($fxHRow['date'])) {
        $fxHistoryByDate[(string) $fxHRow['date']] = (float) $fxHRow['quantity_sold'];
    }
}

$fxActualDates = [];
$fxActualSeries = [];
$fxLastActualValue = null;
$fxPrev30Total = null;
if (!empty($fxForecastDates)) {
    $fxForecastStartTs = strtotime($fxForecastDates[0]);

    for ($fxI = 30; $fxI >= 1; $fxI--) {
        $fxDay = date('Y-m-d', $fxForecastStartTs - $fxI * 86400);
        $fxActualDates[] = $fxDay;
        $fxActualSeries[] = array_key_exists($fxDay, $fxHistoryByDate)
            ? round($fxHistoryByDate[$fxDay], 2)
            : null;
    }

    // Bridge point: the value of the LAST recorded day in the window,
    // only if that exact day is present (missing dates stay missing).
    $fxLastActualDate = count($fxActualDates) ? end($fxActualDates) : null;
    if ($fxLastActualDate !== null && array_key_exists($fxLastActualDate, $fxHistoryByDate)) {
        $fxLastActualValue = round($fxHistoryByDate[$fxLastActualDate], 2);
    }

    // Previous 30-day window for an honest delta — only when all 30 days
    // are recorded; missing dates are never treated as zero.
    $fxPrevTotal = 0.0;
    $fxPrevComplete = true;
    for ($fxI = 60; $fxI >= 31; $fxI--) {
        $fxDay = date('Y-m-d', $fxForecastStartTs - $fxI * 86400);
        if (array_key_exists($fxDay, $fxHistoryByDate)) {
            $fxPrevTotal += (float) $fxHistoryByDate[$fxDay];
        } else {
            $fxPrevComplete = false;
            break;
        }
    }
    $fxPrev30Total = $fxPrevComplete ? $fxPrevTotal : null;
}

$fxWindowStart = count($fxActualDates) ? $fxActualDates[0] : null;
$fxWindowEnd = count($fxActualDates) ? end($fxActualDates) : null;
$fxForecastStart = count($fxForecastDates) ? $fxForecastDates[0] : null;
$fxForecastEnd = count($fxForecastDates) ? end($fxForecastDates) : null;

$fxUnitsDeltaPct = null;
if ($fxPrev30Total !== null && (float) $fxPrev30Total != 0.0 && (float) ($recentWindow['units'] ?? 0) > 0) {
    $fxUnitsDeltaPct = round(
        ((float) $recentWindow['units'] - (float) $fxPrev30Total) / (float) $fxPrev30Total * 100.0,
        1
    );
}

$fxPredictedDeltaPct = null;
if ((float) ($recentWindow['units'] ?? 0) > 0) {
    $fxPredictedDeltaPct = round(
        ($fxPredicted30 - (float) $recentWindow['units']) / (float) $recentWindow['units'] * 100.0,
        1
    );
}

$fxMape = isset($metrics['MAPE']) && $metrics['MAPE'] !== null ? (float) $metrics['MAPE'] : null;
$fxMae = isset($metrics['MAE']) && $metrics['MAE'] !== null ? (float) $metrics['MAE'] : null;

// Top demand medicines: join the DB trailing-30 product breakdown with the
// per-product forecast artifact by product name.
$fxProductByName = [];
foreach ((is_array($productForecast['products'] ?? null) ? $productForecast['products'] : []) as $fxPRow) {
    if (is_array($fxPRow) && !empty($fxPRow['product_name'])) {
        $fxProductByName[(string) $fxPRow['product_name']] = $fxPRow;
    }
}

$fxProductChart = [];
foreach (($recentWindow['products'] ?? []) as $fxPRow) {
    if (!is_array($fxPRow) || empty($fxPRow['product_name'])) {
        continue;
    }
    $fxName = (string) $fxPRow['product_name'];
    $fxActual = (float) ($fxPRow['units'] ?? 0);
    $fxEntry = [
        'name' => $fxName,
        'actual' => round($fxActual, 2),
        'forecast' => null,
        'delta_pct' => null,
        'method' => 'no_product_forecast',
    ];
    $fxPF = $fxProductByName[$fxName] ?? null;
    if ($fxPF !== null
        && array_key_exists('forecast_30_day_units', $fxPF)
        && $fxPF['forecast_30_day_units'] !== null) {
        $fxFc = (float) $fxPF['forecast_30_day_units'];
        $fxEntry['forecast'] = round($fxFc, 2);
        $fxEntry['method'] = (string) ($fxPF['forecast_method'] ?? 'per_product_sarima');
        if ($fxActual > 0) {
            $fxEntry['delta_pct'] = round(($fxFc - $fxActual) / $fxActual * 100.0, 1);
        }
    }
    $fxProductChart[] = $fxEntry;
}

$fxPayload = [
    'actualDates' => $fxActualDates,
    'actualValues' => $fxActualSeries,
    'forecastDates' => $fxForecastDates,
    'forecastValues' => $fxForecastSeries,
    'lowerValues' => $fxLowerSeries,
    'upperValues' => $fxUpperSeries,
    'lastActualValue' => $fxLastActualValue,
    'windowActual' => ['start' => $fxWindowStart, 'end' => $fxWindowEnd],
    'windowForecast' => ['start' => $fxForecastStart, 'end' => $fxForecastEnd],
    'productLabels' => array_column($fxProductChart, 'name'),
    'productActual' => array_column($fxProductChart, 'actual'),
    'productForecast' => array_column($fxProductChart, 'forecast'),
    'productDelta' => array_column($fxProductChart, 'delta_pct'),
    'productMethod' => array_column($fxProductChart, 'method'),
];

$fxPayloadJson = json_encode(
    $fxPayload,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
);
if (!is_string($fxPayloadJson) || trim($fxPayloadJson) === '') {
    $fxPayloadJson = 'null';
}

?>


<div class="dashboard-panel forecast-panel analytics-forecast-landscape">
    <div class="chart-header">
        <div class="chart-title">
            <h4>Sales Forecast</h4>
            <p class="chart-subtitle">
                SARIMA •
                <?= htmlspecialchars((string) $forecastYear); ?>
                <?= $legacySchema ? 'Monthly Sales &amp; Forecast' : 'Current-Year Outlook'; ?>
            </p>
        </div>

        <?php if ($hasAnnualForecast): ?>
            <span class="forecast-status<?= $isStale ? ' forecast-status-warning' : ''; ?>">
                <i class="bi <?= $isStale ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'; ?>"></i>
                <?= htmlspecialchars($statusLabel); ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if (!empty($fxForecastDates)): ?>
    <style>
        .fx-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(215px, 1fr)); gap: 12px; margin-bottom: 14px; }
        .fx-card {
            background: linear-gradient(180deg, #ffffff, #f6fbf8);
            border: 1px solid #dcebe3;
            border-radius: 14px;
            padding: 13px 16px;
            box-shadow: 0 1px 2px rgba(15, 61, 46, 0.06);
        }
        .fx-card-label {
            font-size: 0.72rem; font-weight: 600; letter-spacing: .05em;
            text-transform: uppercase; color: #5b7a6f;
            display: flex; align-items: center; gap: 6px;
        }
        .fx-card-value { font-size: 1.42rem; font-weight: 700; color: #0f3d2e; line-height: 1.3; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .fx-card-note { font-size: 0.72rem; color: #7d958b; margin-top: 3px; }
        .fx-chip {
            display: inline-block; font-size: 0.68rem; font-weight: 700;
            padding: 1px 8px; border-radius: 999px; white-space: nowrap;
        }
        .fx-chip-up   { background: #e4f5ee; color: #0e7a5a; }
        .fx-chip-down { background: #fdf1e2; color: #b4540a; }
        .fx-chip-neutral { background: #eef2f9; color: #46576f; }
        .fx-visual-grid {
            display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
            gap: 12px; margin-bottom: 16px;
        }
        .fx-visual-panel {
            background: #ffffff; border: 1px solid #dcebe3; border-radius: 14px;
            padding: 14px; box-shadow: 0 1px 2px rgba(15, 61, 46, 0.05);
            display: flex; flex-direction: column;
        }
        .fx-panel-title { font-size: 0.82rem; font-weight: 700; color: #234d3d; margin-bottom: 2px; }
        .fx-panel-sub { font-size: 0.71rem; color: #7d958b; margin-bottom: 8px; }
        .fx-chart-wrap { position: relative; flex: 1 1 auto; min-height: 320px; height: auto; }
        .dashboard-panel .fx-visual-panel .fx-chart-wrap canvas {
            position: absolute; inset: 0;
            width: 100% !important; height: 100% !important;
            max-height: none;
        }
        .fx-delta-list { list-style: none; margin: 10px 0 0; padding: 0; font-size: 0.74rem; max-height: 148px; overflow: auto; }
        .fx-delta-list li { display: flex; justify-content: space-between; gap: 8px; padding: 4px 0; border-top: 1px dashed #e3ece7; }
        .fx-method-note { font-size: 0.7rem; color: #80968c; margin: 0 0 10px; }
        @media (max-width: 991.98px) {
            .fx-visual-grid { grid-template-columns: 1fr; }
            .fx-chart-wrap { flex: 0 0 250px; min-height: 250px; height: 250px; }
        }
    </style>

    <div class="fx-cards">
        <div class="fx-card">
            <div class="fx-card-label"><i class="bi bi-cash-coin"></i> Sales — last 30 days</div>
            <div class="fx-card-value">₱<?= number_format((float) ($recentWindow['peso'] ?? 0), 2); ?></div>
            <div class="fx-card-note">
                <?php if (!empty($recentWindow['window_start']) && !empty($recentWindow['window_end'])): ?>
                    <?= htmlspecialchars($recentWindow['window_start']); ?> → <?= htmlspecialchars($recentWindow['window_end']); ?>
                    · <?= (int) ($recentWindow['transactions'] ?? 0); ?> transactions ·
                <?php endif; ?>
                sale-item subtotals
            </div>
        </div>

        <div class="fx-card">
            <div class="fx-card-label"><i class="bi bi-box-seam"></i> Units sold — last 30 days</div>
            <div class="fx-card-value"><?= number_format((float) ($recentWindow['units'] ?? 0)); ?></div>
            <div class="fx-card-note">
                <?php if ($fxUnitsDeltaPct !== null): ?>
                    <span class="fx-chip <?= $fxUnitsDeltaPct >= 0 ? 'fx-chip-up' : 'fx-chip-down'; ?>">
                        <?= ($fxUnitsDeltaPct >= 0 ? '+' : ''); ?><?= number_format($fxUnitsDeltaPct, 1); ?>% vs prev 30
                    </span>
                <?php else: ?>
                    recorded completed sales
                <?php endif; ?>
            </div>
        </div>

        <div class="fx-card">
            <div class="fx-card-label"><i class="bi bi-graph-up-arrow"></i> Predicted units — next 30 days</div>
            <div class="fx-card-value"><?= number_format($fxPredicted30); ?></div>
            <div class="fx-card-note">
                <?php if ($fxForecastStart && $fxForecastEnd): ?>
                    <?= htmlspecialchars($fxForecastStart); ?> → <?= htmlspecialchars($fxForecastEnd); ?>
                <?php endif; ?>
                <?php if ($fxPredictedDeltaPct !== null): ?>
                    · <span class="fx-chip fx-chip-neutral">
                        <?= ($fxPredictedDeltaPct >= 0 ? '+' : ''); ?><?= number_format($fxPredictedDeltaPct, 1); ?>% vs last 30
                    </span>
                <?php endif; ?>
                · refit SARIMA
            </div>
        </div>

        <div class="fx-card">
            <div class="fx-card-label"><i class="bi bi-bullseye"></i> Model accuracy — hold-out MAPE</div>
            <div class="fx-card-value"><?= $fxMape !== null ? number_format($fxMape, 2) . '%' : 'n/a'; ?></div>
            <div class="fx-card-note">
                2025 hold-out backtest<?= $fxMae !== null ? ' · MAE ' . number_format($fxMae, 2) . ' units' : ''; ?>
            </div>
        </div>
    </div>

    <p class="fx-method-note">
        All values come from recorded data and the deployed SARIMA artifacts. Predictions are
        model outputs, not guarantees; the October historical fill is disclosed as simulated in
        the methodology.
    </p>

    <div class="fx-visual-grid">
        <div class="fx-visual-panel">
            <div class="fx-panel-title">Daily Demand &amp; 30-Day Forecast</div>
            <div class="fx-panel-sub" id="fxDailyRange">…</div>
            <div class="fx-chart-wrap"><canvas id="fxDailyChart"></canvas></div>
        </div>

        <div class="fx-visual-panel">
            <div class="fx-panel-title">Top Demand Medicines</div>
            <div class="fx-panel-sub">Actual (last 30 days) vs forecast (next 30 days), units</div>
            <div class="fx-chart-wrap"><canvas id="fxProductChart"></canvas></div>
            <ul class="fx-delta-list" id="fxDeltaList"></ul>
        </div>
    </div>

    <script>
        (function () {
            function fxFmtNum(v, dec) {
                return Number(v).toLocaleString(undefined, {
                    minimumFractionDigits: dec || 0,
                    maximumFractionDigits: dec || 0
                });
            }
            function fxSigned(pct) {
                return (pct > 0 ? '+' : (pct < 0 ? '' : '')) + Number(pct).toFixed(1) + '%';
            }
            function fxDayLabel(d) {
                try {
                    return new Date(d + 'T00:00:00').toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
                } catch (e) {
                    return d;
                }
            }

            function initFxCharts() {
                const payload = <?= $fxPayloadJson; ?>;
                if (!payload || typeof Chart === 'undefined') return;

                const fxColors = {
                    actual: '#159577',
                    forecast: '#2563eb',
                    band: 'rgba(37, 99, 235, 0.10)',
                    soft: 'rgba(21, 149, 119, 0.16)'
                };

                // 30-day daily demand + forecast ------------------------------- //
                const dailyCanvas = document.getElementById('fxDailyChart');
                if (dailyCanvas) {
                    const aDates = payload.actualDates || [];
                    const fDates = payload.forecastDates || [];
                    const aVals = payload.actualValues || [];
                    const fVals = payload.forecastValues || [];
                    const labels = aDates.concat(fDates);

                    const actualLine = aVals.map(function (v) { return v === null ? null : v; })
                        .concat(fDates.map(function () { return null; }));
                    const bridge = (payload.lastActualValue === null || payload.lastActualValue === undefined)
                        ? []
                        : [payload.lastActualValue];
                    const forecastLine = aDates.slice(0, -1).map(function () { return null; })
                        .concat(bridge, fVals);
                    const lowerLine = aDates.map(function () { return null; })
                        .concat(payload.lowerValues || fDates.map(function () { return null; }));
                    const upperLine = aDates.map(function () { return null; })
                        .concat(payload.upperValues || fDates.map(function () { return null; }));

                    new Chart(dailyCanvas, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [
                                {
                                    label: 'Actual units',
                                    data: actualLine,
                                    borderColor: fxColors.actual,
                                    backgroundColor: 'rgba(21, 149, 119, 0.08)',
                                    fill: true,
                                    tension: 0.25,
                                    pointRadius: 0,
                                    pointHoverRadius: 3,
                                    borderWidth: 2,
                                    spanGaps: true
                                },
                                {
                                    label: '30-day forecast',
                                    data: forecastLine,
                                    borderColor: fxColors.forecast,
                                    borderDash: [6, 4],
                                    backgroundColor: 'transparent',
                                    fill: false,
                                    tension: 0.25,
                                    pointRadius: 0,
                                    pointHoverRadius: 3,
                                    borderWidth: 2,
                                    spanGaps: true
                                },
                                {
                                    label: '95% band lower',
                                    data: lowerLine,
                                    borderColor: 'transparent',
                                    backgroundColor: fxColors.band,
                                    borderWidth: 0,
                                    pointRadius: 0,
                                    fill: '+1',
                                    tension: 0.25
                                },
                                {
                                    label: '95% band upper',
                                    data: upperLine,
                                    borderColor: 'transparent',
                                    backgroundColor: 'transparent',
                                    borderWidth: 0,
                                    pointRadius: 0,
                                    fill: false,
                                    tension: 0.25
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: {
                                    labels: {
                                        boxWidth: 12,
                                        font: { size: 10 },
                                        filter: function (item) { return item.datasetIndex <= 1; }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        title: function (items) {
                                            return items.length ? fxDayLabel(items[0].label) : '';
                                        },
                                        label: function (ctx) {
                                            if (ctx.parsed.y === null || ctx.parsed.y === undefined) return;
                                            return ctx.dataset.label + ': ' + fxFmtNum(ctx.parsed.y, 1) + ' units';
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: { display: true, text: 'Units per day' },
                                    ticks: { precision: 0, font: { size: 10 } }
                                },
                                x: {
                                    ticks: {
                                        autoSkip: true,
                                        maxTicksLimit: 10,
                                        maxRotation: 0,
                                        font: { size: 10 },
                                        callback: function (value) {
                                            return fxDayLabel(this.getLabelForValue(value));
                                        }
                                    }
                                }
                            }
                        }
                    });

                    const rangeEl = document.getElementById('fxDailyRange');
                    const wA = payload.windowActual || {};
                    const wF = payload.windowForecast || {};
                    if (rangeEl && wA.start && wF.start) {
                        rangeEl.textContent = 'Actual ' + wA.start + ' → ' + wA.end
                            + ' • Forecast ' + wF.start + ' → ' + wF.end;
                    }
                }

                // Top demand medicines --------------------------------------- //
                const deltaList = document.getElementById('fxDeltaList');
                if (deltaList && Array.isArray(payload.productLabels)) {
                    payload.productLabels.forEach(function (name, i) {
                        const li = document.createElement('li');
                        const nm = document.createElement('span');
                        nm.textContent = name;
                        const chip = document.createElement('span');
                        const pct = payload.productDelta ? payload.productDelta[i] : null;
                        chip.textContent = (pct === null || pct === undefined) ? 'no forecast' : fxSigned(pct) + ' anticipated';
                        chip.className = 'fx-chip '
                            + (pct === null || pct === undefined ? 'fx-chip-neutral' : (pct >= 0 ? 'fx-chip-up' : 'fx-chip-down'));
                        li.appendChild(nm);
                        li.appendChild(chip);
                        deltaList.appendChild(li);
                    });
                }

                const prodCanvas = document.getElementById('fxProductChart');
                if (prodCanvas && Array.isArray(payload.productLabels) && payload.productLabels.length) {
                    const pLabels = payload.productLabels;
                    const trunc = function (s) {
                        const t = String(s);
                        return t.length > 18 ? t.slice(0, 17) + '…' : t;
                    };
                    const truncMap = [];
                    pLabels.forEach(function (name, i) { truncMap.push(trunc(name)); });

                    new Chart(prodCanvas, {
                        type: 'bar',
                        data: {
                            labels: truncMap,
                            datasets: [
                                {
                                    label: 'Actual (last 30d)',
                                    data: payload.productActual,
                                    backgroundColor: 'rgba(21, 149, 119, 0.85)',
                                    borderColor: fxColors.actual,
                                    borderWidth: 1,
                                    borderRadius: 6,
                                    categoryPercentage: 0.72,
                                    barPercentage: 0.9
                                },
                                {
                                    label: 'Forecast (next 30d)',
                                    data: payload.productForecast,
                                    backgroundColor: fxColors.soft,
                                    borderColor: fxColors.actual,
                                    borderWidth: 1.5,
                                    borderDash: [5, 4],
                                    borderRadius: 6,
                                    categoryPercentage: 0.72,
                                    barPercentage: 0.9
                                }
                            ]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } },
                                tooltip: {
                                    mode: 'index',
                                    callbacks: {
                                        title: function (items) {
                                            return items.length ? pLabels[items[0].dataIndex] : '';
                                        },
                                        label: function (ctx) {
                                            return ctx.dataset.label + ': ' + fxFmtNum(ctx.parsed.x) + ' units';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    title: { display: true, text: 'Units' },
                                    ticks: { precision: 0, font: { size: 10 } }
                                },
                                y: { ticks: { font: { size: 10 } } }
                            }
                        }
                    });
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initFxCharts, { once: true });
            } else {
                initFxCharts();
            }

            /*
             * Rescue sweep: some page states (late layout, browser zoom,
             * fonts still loading) can leave a constructed chart unsized or
             * a canvas without an instance. Re-check shortly after load and
             * force a resize where possible; log anything still missing so
             * problems are visible instead of a silent blank canvas.
             */
            function fxRescue(attempt) {
                ['fxDailyChart', 'fxProductChart'].forEach(function (id) {
                    const c = document.getElementById(id);
                    if (!c || typeof Chart === 'undefined' || !Chart.getChart) return;
                    const inst = Chart.getChart(c);
                    if (inst) {
                        const r = c.getBoundingClientRect();
                        if (r.height < 2 || r.width < 2) {
                            console.warn('[fx] zero-size canvas, forcing resize:', id);
                            try { inst.resize(); } catch (e) { console.warn('[fx] resize failed:', id, e); }
                        }
                    } else if (attempt > 0) {
                        console.warn('[fx] canvas has no Chart instance:', id);
                    }
                });
            }
            [300, 800, 1600].forEach(function (ms, i) {
                setTimeout(function () { fxRescue(i); }, ms);
            });
        })();
    </script>
    <?php endif; ?>

    <?php if (!$hasAnnualForecast): ?>
        <div class="empty-chart">
            <i class="bi bi-graph-up-arrow"></i>
            <h5>Monthly Forecast Unavailable</h5>
            <p>The current-year SARIMA annual outlook has not been generated yet.</p>
        </div>
    <?php else: ?>
        <div class="forecast-chart-container forecast-monthly-container">
            <canvas id="forecastChart"></canvas>
        </div>

        <script>
        (function () {
            function initForecastChart() {
                const canvas = document.getElementById('forecastChart');
                if (!canvas || typeof Chart === 'undefined') return;

                if (window.forecastChartInstance) {
                    window.forecastChartInstance.destroy();
                }

                const labels = <?= json_encode(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']); ?>;
                const actual = <?= json_encode($actual, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
                const forecast = <?= json_encode($forecast, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
                const lower = <?= json_encode($lower, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
                const upper = <?= json_encode($upper, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;

                window.forecastChartInstance = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: <?= json_encode($chartActualLabel, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>,
                                data: actual,
                                borderColor: '#2563eb',
                                backgroundColor: 'rgba(37,99,235,0.08)',
                                borderWidth: 3,
                                tension: 0.3,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                spanGaps: false,
                                fill: false
                            },
                            {
                                label: <?= json_encode($chartForecastLabel, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>,
                                data: forecast,
                                borderColor: '#159577',
                                backgroundColor: 'rgba(21,149,119,0.08)',
                                borderWidth: 3,
                                borderDash: [7, 5],
                                tension: 0.3,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                spanGaps: false,
                                fill: false
                            },
                            {
                                label: 'Lower Bound',
                                data: lower,
                                borderColor: 'rgba(21,149,119,0.20)',
                                borderWidth: 1,
                                borderDash: [3, 4],
                                pointRadius: 0,
                                spanGaps: false,
                                fill: false
                            },
                            {
                                label: 'Upper Bound',
                                data: upper,
                                borderColor: 'rgba(21,149,119,0.20)',
                                backgroundColor: 'rgba(21,149,119,0.10)',
                                borderWidth: 1,
                                borderDash: [3, 4],
                                pointRadius: 0,
                                spanGaps: false,
                                fill: '-1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    filter: function(item, data) {
                                        return data.datasets[item.datasetIndex].label !== 'Lower Bound'
                                            && data.datasets[item.datasetIndex].label !== 'Upper Bound';
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        if (context.parsed.y === null) return;
                                        return context.dataset.label + ': '
                                            + Number(context.parsed.y).toFixed(0)
                                            + ' units';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Units Sold' },
                                ticks: { precision: 0 }
                            },
                            x: {
                                title: { display: true, text: 'Month' }
                            }
                        }
                    }
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initForecastChart, { once: true });
            } else {
                initForecastChart();
            }
        })();
        </script>
    <?php endif; ?>

    <?php
    /*
     * Data completeness indicator + Actual vs Forecast variance panel.
     *
     * Months are only compared like-for-like: if the actual series ends
     * mid-month and the forecast starts in that same month (a "split"
     * month), the two numbers come from different slices of the month and
     * must not be used for A-F variance or MAE/MAPE.
     */
    $completeness = $dashboardData['data_completeness'] ?? [];

    $splitActualThrough = $annualMeta['actual_data_through'] ?? null;
    $splitForecastStart = $annualMeta['forecast_start'] ?? null;
    $splitMonth = null;
    if ($splitActualThrough && $splitForecastStart
        && substr((string) $splitActualThrough, 0, 7) === substr((string) $splitForecastStart, 0, 7)) {
        $splitMonth = (int) substr((string) $splitActualThrough, 5, 2);
    }

    // Variance (actual - forecast) per month from the annual artifact.
    $varianceRows = [];
    $absErrors = [];
    $pctErrors = [];
    $monthsLabel = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    for ($m = 1; $m <= 12; $m++) {
        $idx = $m - 1;
        $a = $actual[$idx];
        $f = $forecast[$idx];
        $l = $lower[$idx];
        $u = $upper[$idx];
        if ($a === null || $f === null) {
            $varianceRows[] = [
                'month' => $monthsLabel[$idx],
                'actual' => $a, 'forecast' => $f, 'variance' => null,
                'lower' => $l, 'upper' => $u, 'in_band' => null, 'split' => false,
            ];
            continue;
        }
        if ($splitMonth === $m) {
            $varianceRows[] = [
                'month' => $monthsLabel[$idx],
                'actual' => (float) $a, 'forecast' => (float) $f, 'variance' => null,
                'lower' => $l, 'upper' => $u, 'in_band' => null, 'split' => true,
            ];
            continue;
        }
        $var = (float) $a - (float) $f;
        $varianceRows[] = [
            'month' => $monthsLabel[$idx],
            'actual' => (float) $a, 'forecast' => (float) $f, 'variance' => $var,
            'lower' => $l, 'upper' => $u,
            'in_band' => ($l === null || $u === null) ? null : ($a >= $l && $a <= $u),
            'split' => false,
        ];
        $absErrors[] = abs($var);
        if ((float) $f != 0.0) {
            $pctErrors[] = abs($var) / (float) $f * 100.0;
        }
    }
    $mae = !empty($absErrors) ? array_sum($absErrors) / count($absErrors) : null;
    $mape = !empty($pctErrors) ? array_sum($pctErrors) / count($pctErrors) : null;
    ?>

    <?php if (!empty($completeness) && ($completeness['expected_days'] ?? 0) > 0): ?>
    <div class="forecast-completeness mt-2 p-2 border rounded small">
        <strong>Data completeness (forecast input):</strong>
        <?= (int) ($completeness['present_days'] ?? 0); ?> /
        <?= (int) ($completeness['expected_days'] ?? 0); ?> days present
        (<?= number_format((float) ($completeness['completeness_pct'] ?? 0), 1); ?>%)
        <?php if (($completeness['missing_days'] ?? 0) > 0): ?>
            — <span class="text-danger"><?= (int) $completeness['missing_days']; ?> missing</span>
        <?php else: ?>
            — <span class="text-success">no gaps</span>
        <?php endif; ?>
        <?php if (($completeness['first_date'] ?? null) && ($completeness['last_date'] ?? null)): ?>
            <span class="text-muted">(<?= htmlspecialchars($completeness['first_date']); ?> → <?= htmlspecialchars($completeness['last_date']); ?>)</span>
        <?php endif; ?>
        | Post-training 2026 obs: <?= (int) ($completeness['post_training_days'] ?? 0); ?>
    </div>
    <?php endif; ?>

    <?php if ($hasAnnualForecast && !empty($varianceRows)): ?>
    <details class="forecast-variance mt-2">
        <summary class="small">Actual vs Forecast variance (monthly)</summary>
        <div class="table-responsive mt-1">
            <table class="table table-sm table-striped mb-1">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Actual</th>
                        <th>Forecast</th>
                        <th>Variance (A−F)</th>
                        <th>In band?</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($varianceRows as $vr): ?>
                        <tr>
                            <td><?= htmlspecialchars($vr['month']); ?></td>
                            <td><?= $vr['actual'] === null ? '—' : number_format($vr['actual'], 0); ?></td>
                            <td><?= $vr['forecast'] === null ? '—' : number_format($vr['forecast'], 0); ?></td>
                            <td>
                                <?php if (!empty($vr['split'])): ?>
                                    <span class="text-warning" title="Split month: partial actual (through <?= htmlspecialchars((string) $splitActualThrough); ?>) and partial forecast (from <?= htmlspecialchars((string) $splitForecastStart); ?>) fall inside the same month, so they come from different slices of the month — excluded from variance and accuracy.">Split</span>
                                <?php elseif ($vr['variance'] === null): ?>
                                    —
                                <?php else: ?>
                                    <?= number_format($vr['variance'], 0); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($vr['in_band'] === null): ?>
                                    —
                                <?php elseif ($vr['in_band']): ?>
                                    <span class="text-success">Yes</span>
                                <?php else: ?>
                                    <span class="text-danger">No</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="small text-muted mb-0">
            <?php if (empty($absErrors)): ?>
                No like-for-like full-month comparison yet: past months contain actuals only (the deployed baseline does not store historical month forecasts) and forecast months are still upcoming. Split months are excluded from MAE/MAPE by design.
            <?php else: ?>
                Accuracy (full months with both actual &amp; forecast<?= $splitMonth !== null ? ', split month excluded' : ''; ?>):
                MAE = <?= number_format($mae, 2); ?> units,
                MAPE = <?= number_format($mape, 2) . '%'; ?> (of forecast)
                (<?= count($absErrors); ?> month(s)).
            <?php endif; ?>
        </p>
    </details>
    <?php endif; ?>

    <?php
    /*
     * Dynamic limitations: built from the actual system state (forecast status,
     * data completeness, annual forecast availability) instead of a static list.
     */
    $completeness = $dashboardData['data_completeness'] ?? [];
    $limitations = [];

    // Model / refit status
    if (($forecastStatus['ready'] ?? false) === true) {
        $limitations[] = 'Forecast model was refit on live 2026 POS demand (Ready: YES).';
    } else {
        $limitations[] = 'Forecast is still the validated 2025 baseline (Ready: NO). '
            . ($forecastStatus['reason'] ?? 'Insufficient trailing 30-day live history for a refit.');
    }

    // Data completeness
    $present = (int) ($completeness['present_days'] ?? 0);
    $expected = (int) ($completeness['expected_days'] ?? 0);
    $missing = (int) ($completeness['missing_days'] ?? 0);
    $postTraining = (int) ($completeness['post_training_days'] ?? 0);
    if ($expected > 0) {
        $limitations[] = sprintf(
            'Input data completeness: %d of %d calendar days present (%.1f%%).',
            $present, $expected, (float) ($completeness['completeness_pct'] ?? 0)
        );
        if ($missing > 0) {
            $limitations[] = sprintf(
                'Missing %d calendar day(s) in the input series (gaps inside the trailing 30-day window postpone a model refit).',
                $missing
            );
            $sampleMissing = array_slice($completeness['missing_dates'] ?? [], 0, 5);
            if (!empty($sampleMissing)) {
                $limitations[] = 'Sample missing dates: ' . implode(', ', $sampleMissing)
                    . (count($completeness['missing_dates'] ?? []) > 5 ? ' ...' : '');
            }
        } else {
            $limitations[] = 'Input series is fully continuous (no missing calendar days).';
        }
    } else {
        $limitations[] = 'No completed POS sales available yet; forecast is the 2025 baseline.';
    }

    if ($postTraining > 0) {
        $limitations[] = sprintf(
            'Post-training (2026) observations available: %d. The model refits when the newest 30 consecutive calendar days are fully covered by POS sales.',
            $postTraining
        );
    }

    // Annual forecast availability
    if (!$hasAnnualForecast) {
        $limitations[] = 'Annual (monthly) forecast artifact has not been generated yet.';
    }

    // Generic constraints
    $limitations[] = 'The SARIMA model refits whenever the newest 30 consecutive calendar days of daily sales are complete (trailing-30 refit rule).';
    $limitations[] = 'Missing calendar days are not treated as zero sales; only gaps inside the trailing 30-day window postpone a refit.';
    $limitations[] = 'Product demand forecasts require at least 60 observed sale days per product.';
    $limitations[] = 'Lower bounds are clamped to 0; negative forecasts are not displayed.';
    $limitations[] = 'Historical import (Data Management) records completed sales without changing product stock.';
    ?>

    <details class="forecast-limitations mt-2">
        <summary>Forecast limitations &amp; data requirements</summary>
        <ul class="mb-0 small text-muted">
            <?php foreach ($limitations as $lim): ?>
                <li><?= htmlspecialchars($lim); ?></li>
            <?php endforeach; ?>
        </ul>
    </details>
</div>
