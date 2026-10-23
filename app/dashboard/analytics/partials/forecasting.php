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
            . ($forecastStatus['reason'] ?? 'Insufficient continuous live history for a refit.');
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
                'Missing %d calendar day(s) in the input series (gaps block a model refit).',
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
            'Post-training (2026) observations available: %d (minimum 30 required for refit).',
            $postTraining
        );
    }

    // Annual forecast availability
    if (!$hasAnnualForecast) {
        $limitations[] = 'Annual (monthly) forecast artifact has not been generated yet.';
    }

    // Generic constraints
    $limitations[] = 'Baseline model is trained on 2025 historical data; live 2026 data is used only after 30 consecutive days of complete daily sales.';
    $limitations[] = 'Missing calendar days are not treated as zero sales and will block a model refit.';
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
