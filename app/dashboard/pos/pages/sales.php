<?php

require_once '../../../config/session.php';
require_once '../../Middleware/AuthMiddleware.php';
require_once '../../Controllers/SalesController.php';

AuthMiddleware::check();

$menu = 'pos';
$page = 'sales';

$salesPage = max(1, (int) ($_GET['sales_page'] ?? 1));
$limit = 20;
$search = trim((string) ($_GET['search'] ?? ''));

$salesController = new SalesController();
$result = $salesController->getTransactions($salesPage, $limit, $search);

$transactions = $result['transactions'] ?? [];
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

<link rel="stylesheet" href="/pharmacy_pos/assets/css/dashboard.css">
<link rel="stylesheet" href="/pharmacy_pos/assets/css/sidebar.css">
<link rel="stylesheet" href="/pharmacy_pos/assets/css/navbar.css">
<link rel="stylesheet" href="/pharmacy_pos/assets/css/sales.css">

<div class="dashboard-content records-page">

    <div class="page-header">
        <h2>Sales</h2>
        <p>Manage completed transactions.</p>
    </div>

    <div class="sales-card">

        <form class="sales-toolbar" method="get" action="">
            <input type="hidden" name="page" value="sales">

            <input
                type="search"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                class="search-box"
                placeholder="Search transaction, invoice, receipt or payment"
                autocomplete="off">

            <button type="submit" class="btn btn-success">Search</button>

            <?php if ($search !== ''): ?>
                <a href="?page=sales" class="btn btn-outline-secondary">Reset</a>
            <?php endif; ?>
        </form>

        <div class="table-responsive">
            <table class="sales-table">
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Invoice No.</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="8" class="empty-state">
                            <?= $search !== ''
                                ? 'No sales matched your search.'
                                : 'No completed sales yet.' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr class="sales-row">
                            <td><?= htmlspecialchars($transaction['transaction_number']) ?></td>
                            <td><?= htmlspecialchars($transaction['invoice_number']) ?></td>
                            <td><?= date('m/d/Y', strtotime($transaction['sale_date'])) ?></td>
                            <td><?= date('h:i A', strtotime($transaction['sale_time'])) ?></td>
                            <td><?= htmlspecialchars($transaction['payment_method']) ?></td>
                            <td>
                                <span class="status-paid">
                                    <?= htmlspecialchars($transaction['payment_status']) ?>
                                </span>
                            </td>
                            <td>₱<?= number_format((float) $transaction['total_amount'], 2) ?></td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-primary btn-sm view-sale"
                                    data-sale="<?= (int) $transaction['sale_id'] ?>">
                                    <i class="bi bi-eye"></i>
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
                    of <strong><?= $total ?></strong> sales
                <?php else: ?>
                    No sales found.
                <?php endif; ?>
            </div>

            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls">
                    <?php if ($currentPage > 1): ?>
                        <a class="btn btn-outline-secondary btn-sm"
                           href="?page=sales&sales_page=<?= $currentPage - 1 ?>&search=<?= urlencode($search) ?>">
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
                           href="?page=sales&sales_page=<?= $currentPage + 1 ?>&search=<?= urlencode($search) ?>">
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

<!-- Transaction Details Modal -->
<div class="modal fade" id="saleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="saleModalBody">
                <div class="text-center p-5">
                    <div class="spinner-border text-success"></div>
                    <p class="mt-3">Loading transaction...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/pharmacy_pos/assets/js/sales.js"></script>

<?php require_once '../../../includes/footer.php'; ?>
