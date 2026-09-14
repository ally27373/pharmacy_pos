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

$selectedMonth =
    $_GET['demand_month']
    ?? date('n');

$selectedYear =
    $_GET['demand_year']
    ?? date('Y');

$labels = [];

$demandData = [];

foreach (
    $dashboardData['demand_quantity']
    as $row
) {

    $labels[] =
        'Day ' . $row['day'];

    $demandData[] =
        (int)$row['total_quantity'];

}
?>

<div class="dashboard-panel demand-panel">

    <div class="chart-header">

    <div>

        <h4>Demand Quantity</h4>

        <p class="chart-subtitle">

            Demand trend for

            <?= date(
                'F',
                mktime(
                    0,
                    0,
                    0,
                    $selectedMonth,
                    1
                )
            ); ?>

            <?= htmlspecialchars($selectedYear); ?>

        </p>

    </div>


    <div class="chart-controls">

        <form method="GET">

            <input
                type="hidden"
                name="period"
                value="<?= htmlspecialchars(
                    $_GET['period'] ?? '7days'
                ); ?>"
            >

            <select
                name="demand_month"
                class="chart-select"
                onchange="this.form.submit()"
            >

                <?php for ($m = 1; $m <= 12; $m++): ?>

                    <option
                        value="<?= $m; ?>"
                        <?= $selectedMonth == $m
                            ? 'selected'
                            : ''; ?>
                    >

                        <?= date(
                            'F',
                            mktime(
                                0,
                                0,
                                0,
                                $m,
                                1
                            )
                        ); ?>

                    </option>

                <?php endfor; ?>

            </select>


            <select
                name="demand_year"
                class="chart-select year-select"
                onchange="this.form.submit()"
            >

<?php

$analyticsYears = $dashboardData['analytics_years'] ?? [date('Y')];

foreach ($analyticsYears as $y):
?>

                    <option
                        value="<?= $y; ?>"
                        <?= $selectedYear == $y
                            ? 'selected'
                            : ''; ?>
                    >

                        <?= $y; ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </form>


        <!-- KEEP THE 3-DOT MENU -->

        <div class="chart-menu">

            <button
                class="chart-menu-button"
                type="button"
                onclick="toggleChartMenu(this)"
            >

                ⋮

            </button>


            <div class="chart-menu-dropdown">

                <button
                    type="button"
                    onclick="toggleTrendLine()"
                >
                    Show Trend Line
                </button>


                <button
                    type="button"
                    onclick="showStableDemand()"
                >
                    Stable Demand
                </button>


                <button
                    type="button"
                    onclick="showDynamicDemand()"
                >
                    Dynamic Demand
                </button>


                <button
                    type="button"
                    onclick="exportDemandData()"
                >
                    Export Data
                </button>

            </div>

        </div>

    </div>

</div>
    <?php if (empty($demandData)): ?>

        <div class="empty-chart">

            <i class="bi bi-graph-up"></i>

            <h5>No Demand Data Yet</h5>

            <p>
                Demand analytics will appear after
                completed sales.
            </p>

        </div>

    <?php else: ?>
        <div>
        <canvas id="demandChart"></canvas>
        </div>
        

    <?php endif; ?>

</div>

<?php if (!empty($demandData)): ?>

<script>

const demandLabels =
    <?= json_encode($labels); ?>;

const demandValues =
    <?= json_encode($demandData); ?>;

let demandChart;

let trendVisible = false;

document.addEventListener(
    "DOMContentLoaded",
    function()
    {

        const ctx =
            document
            .getElementById("demandChart");

        demandChart = new Chart(ctx, {

            type: "line",

            data: {

                labels: demandLabels,

                datasets: [

                    {

                        label: "Actual Demand",

                        data: demandValues,

                        borderWidth: 3,

                        tension: 0.35,

                        fill: true

                    },

                    {

                        label: "Trend",

                        data: calculateTrendLine(),

                        borderDash: [8, 5],

                        borderWidth: 2,

                        pointRadius: 0,

                        hidden: true

                    }

                ]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                plugins: {

                    legend: {

                        display: true,

                        position: "bottom"

                    }

                },

                scales: {

                    y: {

                        beginAtZero: true,

                        title: {

                            display: true,

                            text: "Quantity Sold"

                        }

                    },

                    x: {

                        title: {

                            display: true,

                            text: "Date"

                        }

                    }

                }

            }

        });

    }

);

function calculateTrendLine()
{

    const n = demandValues.length;

    if (n < 2) {

        return demandValues;

    }

    const x = demandValues.map(
        (_, index) => index
    );

    const y = demandValues;

    const xMean =
        x.reduce((a,b) => a+b, 0) / n;

    const yMean =
        y.reduce((a,b) => a+b, 0) / n;

    let numerator = 0;

    let denominator = 0;

    for (let i = 0; i < n; i++) {

        numerator +=
            (x[i] - xMean)
            *
            (y[i] - yMean);

        denominator +=
            Math.pow(x[i] - xMean, 2);

    }

    const slope =
        denominator === 0
            ? 0
            : numerator / denominator;

    const intercept =
        yMean - slope * xMean;

    return x.map(
        value => intercept + slope * value
    );

}

function toggleTrendLine()
{
    if (!demandChart) return;

    const trendDataset =
        demandChart.data.datasets[1];

    trendDataset.hidden =
        !trendDataset.hidden;

    demandChart.update();

    closeAllChartMenus();
}

function showStableDemand()
{
    if (!demandChart) return;

    const average =
        demandValues.reduce(
            (a, b) => a + b,
            0
        )
        /
        demandValues.length;

    demandChart.data.datasets[0].data =
        demandValues.map(
            () => average
        );

    demandChart.update();

    closeAllChartMenus();
}

function showDynamicDemand()
{
    if (!demandChart) return;

    demandChart.data.datasets[0].data =
        demandValues;

    demandChart.update();

    closeAllChartMenus();
}

function exportDemandData()
{

    let csv =
        "Date,Quantity Sold\n";

    demandLabels.forEach(
        (date, index) => {

            csv +=
                date
                + ","
                + demandValues[index]
                + "\n";

        }

    );

    const blob =
        new Blob(
            [csv],
            {type: "text/csv"}
        );

    const url =
        URL.createObjectURL(blob);

    const a =
        document.createElement("a");

    a.href = url;

    a.download =
        "demand_data.csv";

    a.click();

    URL.revokeObjectURL(url);

    closeAllChartMenus();

}

</script>

<?php endif; ?>