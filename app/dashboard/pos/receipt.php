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

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Receipt</title>

<link rel="stylesheet" href="/assets/css/receipts.css">
<link rel="stylesheet" href="/assets/css/swal.css">

<!-- This page renders standalone (no includes/header.php), so the
     SweetAlert2 bundle + NxSwal wrapper are loaded explicitly here. -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="/assets/js/swal.js"></script>
<script src="/assets/js/toast.js"></script>

</head>

<body>

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

<p><strong>Subtotal:</strong> ₱<?= number_format(($header['subtotal'] ?? 0), 2); ?></p>
<p><strong>Discount:</strong> ₱<?= number_format(($header['discount_amount'] ?? 0), 2); ?></p>
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

<hr>

<div style="text-align:center; padding: 10px 0;">

<button onclick="confirmAndPrint()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; background: #198754; color: white; border: none; border-radius: 4px;">

<i class="bi bi-printer"></i> Confirm & Print Receipt

</button>

</div>

</div>

<script>

function confirmAndPrint() {

    NxSwal.confirm(
        "Print this receipt?",
        "This will send the receipt to the printer.",
        { confirmText: "Print now", icon: "question" }
    ).then(function (confirmed) {

        if (!confirmed) return;

        if (typeof NxToast !== 'undefined') {
            NxToast.show("🖨 Receipt sent to printer.", "success");
        }

        window.print();

    });

}

// Optional: auto-focus for quick confirmation

window.addEventListener('load', function() {

    document.querySelector('button')?.focus();

});

</script>

</body>
</html>