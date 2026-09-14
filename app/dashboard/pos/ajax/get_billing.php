<?php

require_once __DIR__ . '/../../../Controllers/BillingController.php';

$controller = new BillingController();

$paymentId = $_GET['id'] ?? 0;

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
<?= $billing['payment_date']; ?>
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