<?php

declare(strict_types=1);

/*
| Every amount below is cast with (float) before number_format(). Under
| declare(strict_types=1) that cast is no longer optional: PDO hands back
| DECIMAL columns as PHP strings, and number_format() rejects a string with
| a TypeError. A TypeError is fatal, so it truncates the document mid-receipt
| and the customer gets a receipt with the line items and no totals at all.
|
| Do not remove the casts when tidying this file. Same rule as
| app/dashboard/pos/ajax/get_sale.php.
*/

/*
| Path depths from /app/dashboard/pos:
|   ../../../  -> project root   (config/ lives here)
|   ../../     -> app/           (Middleware/ and Controllers/ live here)
*/

require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../Middleware/AuthMiddleware.php';

/*
| This is a standalone printable page opened in a new tab, not an XHR
| endpoint, so check() is the right guard: a refusal redirects to login,
| which is what an expired session should do in a real navigation.
|
| Receipts belong to whichever account rang the sale up, so any signed-in
| account is allowed -- an Administrator-only rule would lock cashiers out
| of the receipts they just printed. Without a guard, enumerating
| ?sale_id= handed every transaction to any visitor.
*/
AuthMiddleware::check();

require_once __DIR__ . '/../../Controllers/SalesController.php';

$saleId = (int) ($_GET['sale_id'] ?? 0);

if ($saleId <= 0) {
    die("Invalid Sale.");
}

$salesController = new SalesController();

$details = $salesController->getSaleDetails($saleId);

if (!$details) {
    die("Sale not found.");
}

$header = $details[0];

/*
 | Auto-print
 |
 | ?auto=1 makes the receipt print as soon as it opens, which is what both
 | callers want: the POS opens this straight after a completed sale, and the
 | Sales screen already promises "The receipt will open in a new window
 | ready for printing". Until now both landed the user on a page with a
 | button, a confirmation dialog and then the print dialog -- three steps
 | to print one receipt.
 |
 | Browsers only honour window.print() when it runs from a user gesture, so
 | this cannot always be silent; when the browser blocks it the button is
 | still on the page and nothing is lost.
 */
$autoPrint = isset($_GET['auto']) && $_GET['auto'] === '1';

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Receipt</title>

<?php
/*
 * This page renders standalone, so it cannot use asset_url() from
 * includes/header.php. Versioning is still worth it here: a cached
 * stylesheet on the receipt is the same class of bug as a cached one
 * anywhere else.
 */
$receiptAsset = static function (string $path): string {
    $relative = '/' . ltrim($path, '/');
    $full = __DIR__ . '/../../../' . $relative;

    return $relative . '?v=' . (is_file($full) ? (string) filemtime($full) : '1');
};
?>

<link rel="stylesheet" href="<?= $receiptAsset('/assets/css/receipts.css') ?>">

<!-- The print button uses a bi-printer glyph. This page renders standalone
     and never loads includes/header.php, so the icon font has to be pulled in
     here or the button renders as bare text with an invisible icon. Same CDN
     pin as header.php uses. -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<!--
  This page used to load SweetAlert2, swal.js, toast.js and swal.css for the
  "Print this receipt?" confirmation and the "Receipt sent to printer" toast.
  Printing now happens directly from ?auto=1 or the button, so none of it is
  called any more.

  Dropping the SweetAlert2 CDN matters here rather than being tidiness: that
  script is render-blocking, and this page opens on its own the moment a sale
  completes and is expected to be printing before the cashier looks up.
-->

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

<td>₱<?= number_format((float) ($item['subtotal'] ?? 0), 2); ?></td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<hr>

<div class="receipt-totals">

<p><span>Subtotal:</span> <span>₱<?= number_format((float) ($header['subtotal'] ?? 0), 2); ?></span></p>

<p><span>Discount:</span> <span>₱<?= number_format((float) ($header['discount_amount'] ?? 0), 2); ?></span></p>

<p class="receipt-grand-total"><span>Total:</span> <span>₱<?= number_format((float) ($header['total_amount'] ?? 0), 2); ?></span></p>

</div>

<p><strong>Payment:</strong> <?= $header['payment_method']; ?></p>

<p><strong>Reference:</strong>

<?= $header['reference_number'] ?: "N/A"; ?>

</p>

<p><strong>Amount Paid:</strong>

₱<?= number_format((float) ($header['amount_paid'] ?? 0), 2); ?>

</p>

<p><strong>Change:</strong>

₱<?= number_format((float) ($header['change_amount'] ?? 0), 2); ?>

</p>

<hr>

<center>

Thank you for your purchase!<br>

Get Well Soon!

</center>

<hr>

<div style="text-align:center; padding: 10px 0;">

<button class="receipt-print-button" onclick="printReceipt()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; background: #198754; color: white; border: none; border-radius: 4px;">

<i class="bi bi-printer"></i> Print Receipt

</button>

</div>

</div>

<script>

/*
 * One print per page load.
 *
 * Auto-print and the on-page button can both fire -- the user may click the
 * button while the automatic print is still queued, or the browser may
 * ignore the automatic one and they try again. Without this guard that is
 * two identical receipts on the roll.
 */
var hasPrinted = false;

function printReceipt() {

    if (hasPrinted) return;

    hasPrinted = true;

    window.print();

}

var autoPrint = <?= $autoPrint ? 'true' : 'false'; ?>;

window.addEventListener('load', function () {

    if (!autoPrint) {

        // Nothing to print automatically, so put the cursor on the button.
        document.querySelector('.receipt-print-button')?.focus();

        return;

    }

    /*
     * Wait for the icon webfont and the layout to settle first. Printing
     * before the font lands produces a page that reflows once the dialog
     * closes, which is how a receipt ends up printed slightly short.
     */
    setTimeout(printReceipt, 350);

});

</script>

</body>
</html>