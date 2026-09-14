<?php
/**
 * ==========================================================
 * Dashboard Metrics
 * Displays the four KPI cards
 * ==========================================================
 */

/** @var array $dashboardData */
?>

<div class="dashboard-panel">

<h4>⏰ Near Expiry Medicines</h4>

<?php if(empty($dashboardData['near_expiry'])): ?>

<div class="empty-chart">

<i class="bi bi-clock-history"></i>

<h5>No Near Expiry Medicines</h5>

<p>

Products expiring within 30 days will appear here.

</p>

</div>

<?php else: ?>

<table class="table table-hover">

<thead>

<tr>

<th>Medicine</th>

<th>Qty</th>

<th>Expiry</th>

<th>Status</th>

</tr>

</thead>

<tbody>

<?php foreach($dashboardData['near_expiry'] as $medicine): ?>

<?php

$days = $medicine['days_left'];

if($days <= 7){

    $badge = "danger";
    $text = "Critical";

}elseif($days <= 15){

    $badge = "warning";
    $text = "Warning";

}else{

    $badge = "success";
    $text = "Monitor";

}

?>

<tr>

<td>

<strong><?= htmlspecialchars($medicine['product_name']) ?></strong><br>

<small><?= htmlspecialchars($medicine['batch_number']) ?></small>

</td>

<td>

<?= $medicine['quantity']; ?>

</td>

<td>

<?= date('M d, Y',strtotime($medicine['expiration_date'])) ?>

<br>

<small><?= $days ?> days left</small>

</td>

<td>

<span class="badge bg-<?= $badge ?>">

<?= $text ?>

</span>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<?php endif; ?>

</div>