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

$currentYear =
    $_GET['sales_year'] ?? date('Y');

$months = [
    "Jan","Feb","Mar","Apr","May","Jun",
    "Jul","Aug","Sep","Oct","Nov","Dec"
];

$sales = array_fill(0, 12, 0);

foreach ($dashboardData['monthly_sales'] as $row) {

    $sales[$row['month'] - 1] =
        (float)$row['total_sales'];

}

?>

<div class="dashboard-panel">

    <div class="chart-header">

        <h4>Sales Performance</h4>

        <div class="chart-filter-wrapper">

    <i class="bi bi-calendar3"></i>

    <span>Year</span>

    <select
        class="chart-filter"
        onchange="changeSalesYear(this.value)"
    >
    <?php
$analyticsYears = $dashboardData['analytics_years'] ?? [date('Y')];

foreach ($analyticsYears as $year):
?>

            <option
                value="<?= $year; ?>"
                <?= $currentYear == $year
                    ? 'selected'
                    : ''; ?>
            >

                <?= $year; ?>

            </option>

        <?php endforeach; ?>

    </select>

</div>
    </div>

    <?php if (array_sum($sales) == 0): ?>

        <div class="empty-chart">

            <i class="bi bi-graph-up"></i>

            <h5>No Sales Yet</h5>

            <p>
                No sales recorded for <?= $currentYear; ?>.
            </p>

        </div>

    <?php else: ?>
        <div>

        <canvas id="salesChart"></canvas>

        </div>
        

        <script>

        const salesCtx =
            document.getElementById("salesChart");

        new Chart(salesCtx, {

            type: 'line',

            data: {

                labels:
                    <?= json_encode($months); ?>,

                datasets: [{

                    label:
                        'Monthly Sales <?= $currentYear; ?>',

                    data:
                        <?= json_encode($sales); ?>,

                    fill: true,

                    tension: 0.35

                }]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                plugins: {

                    legend: {

                        display: false

                    }

                }

            }

        });

        </script>

    <?php endif; ?>

</div>