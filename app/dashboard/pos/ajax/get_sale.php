<?php

declare(strict_types=1);

/*
| MONEY VALUES MUST BE CAST, NOT COERCED
|
| declare(strict_types=1) above turns number_format()'s first parameter from
| "anything numeric" into "a real float". PDO returns DECIMAL columns as
| PHP strings ("38.00"), so passing one straight through raises
|
|     TypeError: number_format(): Argument #1 ($num) must be of type float
|
| That is a fatal, not a warning: it kills the response mid-document, so the
| browser gets a truncated 500 and the caller reports it as a generic load
| failure. Every amount below therefore goes through (float) first. The same
| trap applies to app/dashboard/pos/receipt.php.
|
| Do not remove the casts when tidying these files.
*/

/*
| Path depths from /app/dashboard/pos/ajax:
|   ../../../../  -> project root   (config/ lives here)
|   ../../../     -> app/           (Middleware/ and Controllers/ live here)
*/

require_once __DIR__ . '/../../../../config/session.php';
require_once __DIR__ . '/../../../Middleware/AuthMiddleware.php';

header('Content-Type: text/html; charset=utf-8');

/*
| Receipts belong to whichever account rang the sale up, so this requires a
| signed-in account rather than the Administrator role. Without a guard any
| visitor could enumerate ?id= and read every transaction.
*/
AuthMiddleware::requireAuthenticatedApi();

require_once __DIR__ . '/../../../Controllers/SalesController.php';

$controller = new SalesController();

$saleId = (int) ($_GET['id'] ?? 0);

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

<td>₱<?= number_format((float) ($item['unit_price'] ?? 0), 2); ?></td>

<td>₱<?= number_format((float) ($item['item_subtotal'] ?? 0), 2); ?></td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<hr>

<h4>

Total:

₱<?= number_format((float) ($header['total_amount'] ?? 0), 2); ?>

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