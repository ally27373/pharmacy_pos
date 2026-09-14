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

$fastMonth =
$_GET['fast_month']
?? date('n');

$fastYear =
$_GET['fast_year']
?? date('Y');

$labels = [];
$data = [];

foreach($dashboardData['fast_moving'] as $row){

    $labels[] = $row['product_name'];

    $data[] = (int)$row['total_sold'];

}

?>

<div class="dashboard-panel">

<div class="chart-header">

    <div class="chart-title">

        <h4>🚀 Fast Moving Products</h4>

        <p class="chart-subtitle">

            Highest selling medicines for

<?= date(
    "F",
    mktime(
        0,
        0,
        0,
        $fastMonth,
        1
    )
); ?>

<?= $fastYear; ?>

        </p>

    </div>

    <div class="chart-controls">
<form method="GET">

<input
type="hidden"
name="period"
value="<?= htmlspecialchars($_GET['period'] ?? '7days'); ?>">

<select
    id="fastMonth"
    class="chart-select"
    onchange="updateFastMoving()">

<?php

$currentMonth =
$_GET['fast_month']
?? date('n');

for($m=1;$m<=12;$m++):

?>

<option
value="<?= $m; ?>"
<?= $currentMonth==$m ? 'selected':''; ?>>

<?= date("F",mktime(0,0,0,$m,1)); ?>

</option>

<?php endfor; ?>

</select>

<select
    id="fastYear"
    class="chart-select"
    onchange="updateFastMoving()">

<?php

$currentYear =
    $_GET['fast_year']
    ?? date('Y');

$analyticsYears =
    $dashboardData['analytics_years']
    ?? [date('Y')];

foreach ($analyticsYears as $y):

?>

<option
    value="<?= $y; ?>"
    <?= (int)$currentYear === (int)$y ? 'selected' : ''; ?>
>

    <?= $y; ?>

</option>

<?php endforeach; ?>

</select>

</form>

        <div class="chart-menu">

            <button
                class="chart-menu-button"
                onclick="toggleChartMenu(this)"
                type="button">

                ⋮

            </button>

            <div class="chart-menu-dropdown">

                <button onclick="setFastLimit(5)">
    Top 5 Products
</button>

<button onclick="setFastLimit(10)">
    Top 10 Products
</button>

                <hr>

                <button>

                    Export CSV

                </button>

            </div>

        </div>

    </div>

</div>



<?php if(empty($data)): ?>

<div class="empty-chart">

<i class="bi bi-graph-up-arrow"></i>

<h5>No Sales Yet</h5>

<p>

Fast moving medicines will appear after sales are recorded.

</p>

</div>

<?php else: ?>

<div class="chart-container">

    <canvas id="fastMovingChart"></canvas>

</div>

<div class="sales-summary">

<?php if(!empty($dashboardData['fast_moving'])): ?>

<div class="summary-item">

<span class="summary-title">

🚀 Fastest Product

</span>

<strong>

<?= $dashboardData['fast_moving'][0]['product_name']; ?>

</strong>

</div>

<div class="summary-item">

<span class="summary-title">

📦 Units Sold

</span>

<strong>

<?= $dashboardData['fast_moving'][0]['total_sold']; ?>

</strong>

</div>

<div class="summary-item">

<span class="summary-title">

📅 Current Month

</span>

<strong>

<?= date(
    "F",
    mktime(
        0,
        0,
        0,
        $fastMonth,
        1
    )
); ?>

<?= $fastYear; ?>

</strong>

</div>

<?php endif; ?>

</div>

<script>

const fastMovingCtx =
document.getElementById("fastMovingChart").getContext("2d");

const fastMovingLabels =
<?= json_encode($labels); ?>;

const fastMovingData =
<?= json_encode($data); ?>;

new Chart(fastMovingCtx,{

    type:"bar",

    data:{

        labels: fastMovingLabels,

        datasets:[{

            label:"Units Sold",

            data: fastMovingData,

            backgroundColor:
                fastMovingData.map((value,index)=>{

                    if(index===0) return "#16a34a";   // Green
                    if(index===1) return "#3b82f6";   // Blue
                    if(index===2) return "#f59e0b";   // Orange

                    return "#94a3b8";                 // Gray

                }),

            borderRadius:10,

            borderSkipped:false,

            maxBarThickness:28

        }]

    },

    options:{

        indexAxis:"y",

        responsive:true,

        maintainAspectRatio:false,

        interaction:{

            mode:"nearest",

            intersect:false

        },

        plugins:{

            legend:{

                display:false

            },

            tooltip:{

                backgroundColor:"#111827",

                padding:14,

                displayColors:false,

                callbacks:{

                    title:function(context){

                        return context[0].label;

                    },

                    label:function(context){

                        return "Units Sold : " + context.raw;

                    },

                    footer:function(){

                        return "Fast Moving Product";

                    }

                }

            }

        },

        scales:{

            x:{

                beginAtZero:true,

                title:{

                    display:true,

                    text:"Units Sold"

                }

            },

            y:{

                title:{

                    display:true,

                    text:"Medicine"

                }

            }

        }

    }

});

</script>
<?php endif; ?>

</div>