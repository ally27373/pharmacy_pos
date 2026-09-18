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

foreach ($dashboardData['payment_methods'] as $row) {

    $labels[] = $row['payment_method'];

    $data[] = (int)$row['total'];

}

?>

<div class="dashboard-panel">

<h4>Payment Methods</h4>

<?php if(count($data)==0): ?>

<div class="empty-chart">

<i class="bi bi-credit-card"></i>

<h5>No Payment Data Yet</h5>

<p>

Payment statistics will appear
after completed sales.

</p>

</div>

<?php else: ?>
<div>
<canvas id="paymentChart"></canvas>
</div>


<script>

const paymentCtx =
document.getElementById("paymentChart");

new Chart(paymentCtx,{

type:'pie',

data:{

labels:
<?= json_encode($labels); ?>,

datasets:[{

data:
<?= json_encode($data); ?>

}]

},

options:{

responsive:true,

maintainAspectRatio:false,

plugins:{

legend:{

position:'bottom',

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