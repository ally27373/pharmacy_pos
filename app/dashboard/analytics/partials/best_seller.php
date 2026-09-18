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

$month =
$_GET['best_month'] ?? date('n');

$year =
$_GET['best_year'] ?? date('Y');

$labels = [];

$data = [];

foreach($dashboardData['best_sellers'] as $row){

    $labels[] = $row['product_name'];

    $data[] = (int)$row['total_quantity'];

}
$mode =
$_GET['mode']
??
'best';

?>

<div class="dashboard-panel">

    <div class="chart-header">

        <div class="chart-title">

    <h4>

        <?= $mode === 'least'
            ? 'Least Selling Products'
            : 'Best Selling Products'; ?>

    </h4>

    <p class="chart-subtitle">

        <?= $mode === 'least'
            ? 'Lowest selling medicines this month'
            : 'Top selling medicines this month'; ?>

    </p>

</div>

        <div class="chart-controls">

            <select
                id="bestMonth"
                class="chart-select"
                onchange="updateBestSeller()">

<?php

for($m=1;$m<=12;$m++){

?>

<option
value="<?= $m ?>"

<?= $m==$month ? 'selected':'' ?>>

<?= date("F",mktime(0,0,0,$m,1)); ?>

</option>

<?php

}

?>

            </select>

            <select
                id="bestYear"
                class="chart-select"
                onchange="updateBestSeller()">

<?php

$analyticsYears = $dashboardData['analytics_years'] ?? [date('Y')];

foreach($analyticsYears as $y){

?>

<option
value="<?= $y ?>"

<?= $y==$year ? 'selected':'' ?>>

<?= $y ?>

</option>

<?php

}

?>

            </select>

            <div class="chart-menu">

                <button

                    class="chart-menu-button"

                    onclick="toggleChartMenu(this)"

                    type="button">

                    ⋮

                </button>

                <div class="chart-menu-dropdown">

                    <button
                    onclick="showBestProducts()">

                        Best Selling

                    </button>

                    <button
                    onclick="showLeastProducts()">

                        Least Selling

                    </button>

                    <hr>

                    <button
                    onclick="setTopProducts(5)">

                        Top 5

                    </button>

                    <button
                    onclick="setTopProducts(10)">

                        Top 10

                    </button>

                    <hr>

                    <button
                    onclick="exportBestSellerCSV()">

                        Export CSV

                    </button>

                </div>

            </div>

        </div>

    </div>

<div class="chart-container">
    <canvas id="bestSellerChart"></canvas>
</div>

<div class="chart-summary">

    <?php if (!empty($dashboardData['best_sellers'])): ?>

        <div class="summary-item">

            <span class="summary-title">

                🏆 Top Product

            </span>

            <strong>

                <?= $dashboardData['best_sellers'][0]['product_name']; ?>

            </strong>

        </div>

        <div class="summary-item">

            <span class="summary-title">

                📦 Units Sold

            </span>

            <strong>

                <?= $dashboardData['best_sellers'][0]['total_quantity']; ?>

            </strong>

        </div>

        <div class="summary-item">

            <span class="summary-title">

                📅 Current View

            </span>

            <strong>

                <?= date("F", mktime(0,0,0,$month,1)); ?>

                <?= $year; ?>

            </strong>

        </div>

    <?php endif; ?>

</div>

<script>




const ctx =
document
.getElementById("bestSellerChart")
.getContext("2d");


const bestSellerData =
<?= json_encode($data); ?>;

const bestSellerChart =
new Chart(ctx,{

type:"bar",

data:{

labels:
<?= json_encode($labels); ?>,

datasets:[{

    label:"Products Sold",

    data: bestSellerData,

backgroundColor:

bestSellerData.map((value,index)=>{

if(index===0) return "#16a34a";

if(index===1) return "#3b82f6";

if(index===2) return "#f59e0b";

return "#cbd5e1";

}),

    borderRadius:14,

    borderSkipped:false,

    maxBarThickness:40

}]

},

options:{

responsive:true,

maintainAspectRatio:false,

layout:{

padding:20

},

interaction:{

mode:"index",

intersect:false

},

animation:{

duration:1200,

easing:"easeOutQuart"

},

plugins:{

legend:{

display:false

},

tooltip:{

backgroundColor:"#111827",

padding:14,

displayColors:false,

titleFont:{

size:14,

weight:"bold"

},

bodyFont:{

size:13

},

callbacks:{

title:function(context){

return context[0].label;

},

label:function(context){

return "Units Sold : " + context.raw;

},

footer:function(){

return "Monthly Sales";

}

}

},

}

}

});

</script>

</div>
    