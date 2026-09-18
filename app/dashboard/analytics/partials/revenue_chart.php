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

foreach ($dashboardData['revenue_by_category'] as $row){

    $labels[] = $row['category_name'];

    $data[] = (float)$row['revenue'];

}

?>

<div class="dashboard-panel">

    <h4>Revenue by Category</h4>

<?php if(array_sum($data)==0): ?>

<div class="empty-chart">

    <i class="bi bi-pie-chart"></i>

    <h5>No Revenue Yet</h5>

    <p>

        Revenue analytics will appear after
        completed sales transactions.

    </p>

</div>

<?php else: ?>

    <div>

        <canvas id="revenueChart"></canvas>

    </div>



<script>

const revenueChart = document.getElementById("revenueChart");

new Chart(revenueChart,{

type:"doughnut",

data:{

labels:<?= json_encode($labels); ?>,

datasets:[{

data:<?= json_encode($data); ?>,

borderWidth:2

}]

},

options:{

responsive:true,

maintainAspectRatio:false,

plugins:{

legend:{

position:"bottom",

labels:{

boxWidth:12,

padding:10,

font:{size:11}

}

}

}

}

});

</script>

<?php endif; ?>

</div>

