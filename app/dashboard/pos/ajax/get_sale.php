<?php

require_once __DIR__ . '/../../../Controllers/SalesController.php';

$controller = new SalesController();

$saleId = $_GET['id'] ?? 0;

$items = $controller->getSaleDetails($saleId);

if(empty($items))
{
    echo "<p>No transaction found.</p>";

    exit;
}

$header = $items[0];
?>

<h5><?= $header['transaction_number']; ?></h5>

<p>

<strong>Invoice:</strong>

<?= $header['invoice_number']; ?>

</p>

<p>

<strong>Payment:</strong>

<?= $header['payment_method']; ?>

</p>

<p>
<strong>Payment Method:</strong>
<?= htmlspecialchars($header['payment_method']); ?>
</p>

<?php if (
    $header['payment_method'] !== 'Cash' &&
    !empty($header['reference_number'])
): ?>

<p>
<strong>Reference No.:</strong>
<?= htmlspecialchars($header['reference_number']); ?>
</p>

<?php endif; ?>



<p>

<strong>Status:</strong>

<?= $header['payment_status']; ?>

</p>

<hr>

<table class="table table-bordered">

<thead>

<tr>

<th>Medicine</th>

<th>Qty</th>

<th>FEFO Batch</th>

<th>Price</th>

<th>Subtotal</th>

</tr>

</thead>

<tbody>

<?php foreach($items as $item): ?>

<tr>

<td><?= htmlspecialchars($item['product_name']); ?></td>

<td><?= $item['quantity']; ?></td>

<td><?= htmlspecialchars($item['batch_allocations'] ?? '—'); ?></td>

<td>₱<?= number_format($item['unit_price'],2); ?></td>

<td>₱<?= number_format($item['subtotal'],2); ?></td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<hr>

<h4>

Total:

₱<?= number_format($header['total_amount'],2); ?>

</h4>

<hr>

<div class="text-end">

<button
class="btn btn-secondary"
data-bs-dismiss="modal">

Close

</button>

<button
    id="print-receipt"
    class="btn btn-success"
    data-sale="<?= $header['sale_id']; ?>">

    <i class="bi bi-printer"></i>

    Print Receipt

</button>

</div>