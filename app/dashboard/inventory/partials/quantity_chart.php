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

$labels = [];
$data = [];

foreach($dashboardData['quantity_by_category'] as $row){

    $labels[] = $row['category_name'];

    $data[] = $row['total_quantity'];

}

?>




<div class="dashboard-panel">

<h4>

📦 Inventory Distribution by Category

</h4>

<?php if(count($data)==0): ?>

<div class="empty-chart">

<i class="bi bi-bar-chart"></i>

<h5>No Inventory Yet</h5>

<p>

Import your medicines to see category distribution.

</p>

</div>

<?php else: ?>

<div class="inventory-chart-container inventory-distribution-chart">
    <canvas id="categoryChart"></canvas>
</div>

<script>

const canvas = document.getElementById("categoryChart");

canvas.removeAttribute("width");
canvas.removeAttribute("height");

const categoryCtx = canvas.getContext("2d");

let categoryChart;

window.addEventListener("load", () => {

    categoryChart = new Chart(categoryCtx, {

        type:'bar',

        data:{
            labels: <?= json_encode($labels); ?>,
            datasets:[{
                label:'Quantity',
                data: <?= json_encode($data); ?>,
                borderWidth:1
            }]
        },

        options:{
            responsive:true,
            maintainAspectRatio:false,
            indexAxis:'y',
            scales:{
                x:{beginAtZero:true, ticks:{precision:0}},
                y:{ticks:{autoSkip:false}}
            },
            plugins:{
                legend:{display:true, position:'top'}
            }
        }

    });

});


</script>

<?php endif; ?>

</div>