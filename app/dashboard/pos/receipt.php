<?php

require_once '../../Controllers/SalesController.php';

if (!isset($_GET['sale_id'])) {
    die("Invalid Sale.");
}

$saleId = (int)$_GET['sale_id'];

$salesController = new SalesController();

$details = $salesController->getSaleDetails($saleId);

if (!$details) {
    die("Sale not found.");
}

$header = $details[0];

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<title>Receipt</title>

<link rel="stylesheet" href="/assets/css/receipt.css">

</head>

<body onload="window.print()">

<div class="receipt">

<h2>NICAXANDRA PHARMACY</h2>

<p>Official Receipt</p>

<hr>

<p><strong>Invoice:</strong> <?= $header['invoice_number']; ?></p>

<p><strong>Transaction:</strong> <?= $header['transaction_number']; ?></p>

<p><strong>Date:</strong> <?= htmlspecialchars(toPhTime($header['created_at'] ?? null, "M d, Y h:i A")); ?></p>

<hr>

<table>

<thead>

<tr>

<th>Medicine</th>

<th>Qty</th><th>Batch</th>

<th>Total</th>

</tr>

</thead>

<tbody>

<?php foreach($details as $item): ?>

<tr>

<td><?= htmlspecialchars($item['product_name']); ?></td>

<td><?= $item['quantity']; ?></td><td><?= htmlspecialchars($item['batch_allocations'] ?? '—'); ?></td>

<td>₱<?= number_format($item['subtotal'],2); ?></td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<hr>

<p><strong>Total:</strong> ₱<?= number_format($header['total_amount'],2); ?></p>

<p><strong>Payment:</strong> <?= $header['payment_method']; ?></p>

<p><strong>Reference:</strong>

<?= $header['reference_number'] ?: "N/A"; ?>

</p>

<p><strong>Amount Paid:</strong>

₱<?= number_format($header['amount_paid'],2); ?>

</p>

<p><strong>Change:</strong>

₱<?= number_format($header['change_amount'],2); ?>

</p>

<hr>

<center>

Thank you for your purchase!<br>

Get Well Soon!

</center>

</div>

</body>
</html>