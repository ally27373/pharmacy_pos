<?php

require_once '../../../config/session.php';
require_once '../../Middleware/AuthMiddleware.php';
require_once '../../Controllers/BillingController.php';

AuthMiddleware::check();

$menu = 'pos';
$page = 'billings';

$billingPage = max(1, (int) ($_GET['billing_page'] ?? 1));
$limit = 20;
$search = trim((string) ($_GET['search'] ?? ''));

$billingController = new BillingController();
$result = $billingController->getBillings($billingPage, $limit, $search);

$billings = $result['billings'] ?? [];
$pagination = $result['pagination'] ?? [
    'page' => 1,
    'limit' => $limit,
    'total' => 0,
    'total_pages' => 0,
    'has_previous' => false,
    'has_next' => false,
];

require_once '../../../includes/header.php';
?>

<link rel="stylesheet" href="/assets/css/dashboard-shell.css">
<link rel="stylesheet" href="/assets/css/sidebar.css">
<link rel="stylesheet" href="/assets/css/navbar.css">
<link rel="stylesheet" href="/assets/css/sales.css">

<div class="dashboard-content records-page">

    <div class="page-header">
        <h2>Billings</h2>
        <p>Manage payment records.</p>
    </div>

    <div class="sales-card">

        <form class="sales-toolbar" method="get" action="">
            <input type="hidden" name="page" value="billings">

            <input
                type="search"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                class="search-box"
                placeholder="Search transaction, invoice, payment or status"
                autocomplete="off">

            <button type="submit" class="btn btn-success">Search</button>

            <?php if ($search !== ''): ?>
                <a href="?page=billings" class="btn btn-outline-secondary">Reset</a>
            <?php endif; ?>
        </form>

        <div class="table-responsive table-responsive-wrap">
            <table class="sales-table">
                <thead>
                    <tr>
                        <th>Transaction</th>
                        <th>Invoice</th>
                        <th>Method</th>
                        <th>Amount Due</th>
                        <th>Paid</th>
                        <th>Change</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (empty($billings)): ?>
                    <tr>
                        <td colspan="9" class="empty-state">
                            <?= $search !== ''
                                ? 'No billing records matched your search.'
                                : 'No billing records yet.' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($billings as $billing): ?>
                        <tr class="billing-row">
                            <td><?= htmlspecialchars($billing['transaction_number']) ?></td>
                            <td><?= htmlspecialchars($billing['invoice_number']) ?></td>
                            <td><?= htmlspecialchars($billing['payment_method']) ?></td>
                            <td>₱<?= number_format((float) $billing['amount_due'], 2) ?></td>
                            <td>₱<?= number_format((float) $billing['amount_paid'], 2) ?></td>
                            <td>₱<?= number_format((float) $billing['change_amount'], 2) ?></td>
                            <td><?= htmlspecialchars($billing['payment_status']) ?></td>
                            <td><?= htmlspecialchars(toPhTime($billing['payment_date'] ?? null, 'M d, Y h:i A')) ?></td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-success btn-sm view-billing"
                                    data-payment="<?= (int) $billing['payment_id'] ?>">
                                    View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        $currentPage = (int) $pagination['page'];
        $totalPages = (int) $pagination['total_pages'];
        $total = (int) $pagination['total'];
        $start = $total > 0 ? (($currentPage - 1) * $limit) + 1 : 0;
        $end = $total > 0 ? min($currentPage * $limit, $total) : 0;
        ?>

        <div class="records-pagination">
            <div class="pagination-info">
                <?php if ($total > 0): ?>
                    Showing <strong><?= $start ?></strong>–<strong><?= $end ?></strong>
                    of <strong><?= $total ?></strong> billing records
                <?php else: ?>
                    No billing records found.
                <?php endif; ?>
            </div>

            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls">
                    <?php if ($currentPage > 1): ?>
                        <a class="btn btn-outline-secondary btn-sm"
                           href="?page=billings&billing_page=<?= $currentPage - 1 ?>&search=<?= urlencode($search) ?>">
                            Previous
                        </a>
                    <?php else: ?>
                        <button class="btn btn-outline-secondary btn-sm" disabled>Previous</button>
                    <?php endif; ?>

                    <span class="pagination-page">
                        Page <strong><?= $currentPage ?></strong> of <strong><?= $totalPages ?></strong>
                    </span>

                    <?php if ($currentPage < $totalPages): ?>
                        <a class="btn btn-outline-secondary btn-sm"
                           href="?page=billings&billing_page=<?= $currentPage + 1 ?>&search=<?= urlencode($search) ?>">
                            Next
                        </a>
                    <?php else: ?>
                        <button class="btn btn-outline-secondary btn-sm" disabled>Next</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<div class="modal fade" id="billingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Billing Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="billingModalBody"></div>
        </div>
    </div>
</div>

<script src="/assets/js/billings.js"></script>
<script src="/assets/js/sales.js"></script>

<?php require_once '../../../includes/footer.php'; ?>
