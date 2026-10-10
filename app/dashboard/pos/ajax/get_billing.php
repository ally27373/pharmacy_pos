<?php

require_once __DIR__ . '/../../../../config/session.php';
require_once __DIR__ . '/../../../Middleware/AuthMiddleware.php';

/*
|--------------------------------------------------------------------------
| Access control
|--------------------------------------------------------------------------
|
| This endpoint is reachable directly by URL, independently of the page that
| hosts the modal, so it has to authenticate for itself. Without the check
| below, anyone could read any transaction's amount, payment method and
| payment reference just by walking ?id=1,2,3...
|
| The billings table is open to any signed-in account -- the POS page uses
| AuthMiddleware::check(), not ::admin() -- so the requirement here is a
| valid session, not an Administrator role.
|
*/
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo '<div class="alert alert-danger mb-0">Your session has expired. Please sign in again.</div>';
    exit;
}

/*
 * Re-validate before serving: the account may have been deleted or
 * deactivated since the page that opened this modal was rendered.
 */
AuthMiddleware::check();

require_once __DIR__ . '/../../../Controllers/BillingController.php';

$controller = new BillingController();

$paymentId = (int) ($_GET['id'] ?? 0);

$billing = $controller->getBillingDetails($paymentId);

if(empty($billing))
{
    echo "<p>No billing record found.</p>";
    exit;
}

?>

<h5><?= $billing['transaction_number']; ?></h5>

<p>
<strong>Invoice:</strong>
<?= $billing['invoice_number']; ?>
</p>

<p>
<strong>Payment Method:</strong>
<?= $billing['payment_method']; ?>
</p>

<?php if(
    $billing['payment_method'] !== 'Cash' &&
    !empty($billing['reference_number'])
): ?>

<p>
<strong>Reference Number:</strong>
<?= htmlspecialchars($billing['reference_number']); ?>
</p>

<?php endif; ?>

<p>
<strong>Amount Due:</strong>
₱<?= number_format($billing['amount_due'],2); ?>
</p>

<p>
<strong>Subtotal:</strong>
₱<?= number_format(($billing['subtotal'] ?? 0), 2); ?>
</p>

<p>
<strong>Discount:</strong>
₱<?= number_format(($billing['discount_amount'] ?? 0), 2); ?>
</p>

<p>
<strong>Amount Paid:</strong>
₱<?= number_format($billing['amount_paid'],2); ?>
</p>

<p>
<strong>Change:</strong>
₱<?= number_format($billing['change_amount'],2); ?>
</p>

<p>
<strong>Status:</strong>
<?= $billing['payment_status']; ?>
</p>

<p>
<strong>Date:</strong>
<?= toPhTime($billing['payment_date'] ?? null, "M d, Y h:i A"); ?>
</p>

<hr>

<div class="d-flex justify-content-end">

<button
class="btn btn-secondary"
data-bs-dismiss="modal">

Close

</button>

<button

    id="print-billing-receipt"

    class="btn btn-success"

    data-sale="<?= $billing['sale_id']; ?>">

    <i class="bi bi-printer"></i>

    Reprint Receipt

</button>

</div>