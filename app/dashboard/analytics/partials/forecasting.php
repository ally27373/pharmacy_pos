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
                const actual = <?= json_encode($actual); ?>;
                const forecast = <?= json_encode($forecast); ?>;
                const lower = <?= json_encode($lower); ?>;
                const upper = <?= json_encode($upper); ?>;

                window.forecastChartInstance = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: <?= json_encode($chartActualLabel); ?>,
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
                                label: <?= json_encode($chartForecastLabel); ?>,
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
</div>
