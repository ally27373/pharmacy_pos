<?php
/** @var array $dashboardData */

$inventoryProducts = $dashboardData['inventory_products'] ?? [];
$inventoryBatches = $dashboardData['inventory_batches'] ?? [];
$inventoryPageSize = 5;
?>

<div class="dashboard-panel current-inventory-panel">
    <div class="chart-header current-inventory-header">
        <div class="chart-title">
            <h4><i class="bi bi-box-seam"></i> Current Inventory</h4>
            <p class="chart-subtitle">
                Product-level quantity with batch details available on selection
            </p>
        </div>
        <div class="current-inventory-total">
            <?= number_format(count($inventoryProducts)); ?> products
        </div>
    </div>

    <?php if (empty($inventoryProducts)): ?>
        <div class="empty-chart">
            <i class="bi bi-box-seam"></i>
            <h5>No inventory products found</h5>
            <p>Product quantities will appear here when inventory is available.</p>
        </div>
    <?php else: ?>
        <div class="current-inventory-table-wrap">
            <table class="current-inventory-table">
                <thead>
                    <tr>
                        <th class="inventory-check-col">Check</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventoryProducts as $loopIndex => $row): ?>
                        <?php
                        $productId = (int) $row['product_id'];
                        $quantity = (int) $row['quantity'];
                        $batches = $inventoryBatches[$productId] ?? [];
                        ?>
                        <tr class="current-inventory-product-row" data-product-id="<?= $productId; ?>" data-inventory-index="<?= $loopIndex ?? 0; ?>">
                            <td class="inventory-check-col">
                                <input
                                    type="checkbox"
                                    class="inventory-product-check"
                                    id="inventory-product-<?= $productId; ?>"
                                    data-product-id="<?= $productId; ?>"
                                    aria-label="Show batches for <?= htmlspecialchars((string) $row['product_name']); ?>"
                                >
                            </td>
                            <td>
                                <div class="current-inventory-product-name">
                                    <?= htmlspecialchars((string) $row['product_name']); ?>
                                </div>
                                <div class="current-inventory-barcode">
                                    <?= htmlspecialchars((string) ($row['barcode'] ?? '')); ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars((string) ($row['category_name'] ?? '—')); ?></td>
                            <td><?= htmlspecialchars((string) ($row['type_name'] ?? '—')); ?></td>
                            <td>
                                <span class="current-inventory-quantity <?= $quantity <= 0 ? 'quantity-zero' : ''; ?>">
                                    <?= number_format($quantity); ?> pcs
                                </span>
                            </td>
                        </tr>
                        <tr
                            class="current-inventory-batch-row"
                            id="inventory-batches-<?= $productId; ?>"
                            hidden
                        >
                            <td colspan="5">
                                <div class="current-inventory-batch-panel">
                                    <div class="batch-panel-title">
                                        <strong>Individual Batches</strong>
                                        <span><?= number_format(count($batches)); ?> batch<?= count($batches) === 1 ? '' : 'es'; ?></span>
                                    </div>

                                    <?php if (empty($batches)): ?>
                                        <div class="batch-empty">No batch records found for this product.</div>
                                    <?php else: ?>
                                        <div class="batch-table-wrap">
                                            <table class="batch-detail-table">
                                                <thead>
                                                    <tr>
                                                        <th>Batch</th>
                                                        <th>Quantity</th>
                                                        <th>Expiration</th>
                                                        <th>Date Received</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($batches as $batch): ?>
                                                        <?php
                                                        $status = (string) ($batch['display_status'] ?? $batch['batch_status'] ?? 'Active');
                                                        $statusClass = strtolower(str_replace(' ', '-', $status));
                                                        ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars((string) ($batch['batch_number'] ?: '—')); ?></td>
                                                            <td><?= number_format((int) $batch['quantity']); ?> pcs</td>
                                                            <td>
                                                                <?= $batch['expiration_date']
                                                                    ? htmlspecialchars(date('M d, Y', strtotime($batch['expiration_date'])))
                                                                    : 'No expiry'; ?>
                                                            </td>
                                                            <td>
                                                                <?= $batch['received_date']
                                                                    ? htmlspecialchars(date('M d, Y', strtotime($batch['received_date'])))
                                                                    : '—'; ?>
                                                            </td>
                                                            <td>
                                                                <span class="batch-status batch-status-<?= htmlspecialchars($statusClass); ?>">
                                                                    <?= htmlspecialchars($status); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="current-inventory-pagination" aria-label="Current inventory pagination">
            <div class="current-inventory-pagination-summary" id="current-inventory-pagination-summary"></div>
            <div class="current-inventory-pagination-controls">
                <button type="button" class="inventory-page-btn" id="inventory-page-prev" disabled>
                    <i class="bi bi-chevron-left"></i> Previous
                </button>
                <span class="inventory-page-indicator" id="inventory-page-indicator">Page 1 of 1</span>
                <button type="button" class="inventory-page-btn" id="inventory-page-next" disabled>
                    Next <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>

        <div class="current-inventory-note">
            <i class="bi bi-info-circle"></i>
            <span>Quantity is the product-level total. Check a product to view its individual batches and expiration dates.</span>
        </div>
    <?php endif; ?>
</div>

<style>
.current-inventory-panel { width: 100%; }
.current-inventory-header { margin-bottom: 14px; }
.current-inventory-total {
    padding: 6px 10px;
    border-radius: 999px;
    background: #eef7f4;
    color: #118d6d;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}
.current-inventory-table-wrap { width: 100%; overflow-x: auto; border: 1px solid #e7ecef; border-radius: 10px; }
.current-inventory-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.current-inventory-table th,
.current-inventory-table td { padding: 11px 12px; border-bottom: 1px solid #edf1f3; text-align: left; vertical-align: middle; }
.current-inventory-table th { background: #118d6d; color: #fff; font-weight: 700; white-space: nowrap; }
.current-inventory-table tbody tr.current-inventory-product-row:hover td { background: #f8fbfa; }
.inventory-check-col { width: 68px; text-align: center !important; }
.inventory-product-check { width: 17px; height: 17px; accent-color: #118d6d; cursor: pointer; }
.current-inventory-product-name { font-weight: 700; color: #202a33; }
.current-inventory-barcode { margin-top: 2px; font-size: 11px; color: #8a949d; }
.current-inventory-quantity { font-weight: 800; color: #118d6d; white-space: nowrap; }
.current-inventory-batch-row > td { padding: 0 !important; background: #f7faf9 !important; }
.current-inventory-batch-panel { padding: 15px 18px 17px 82px; border-bottom: 1px solid #dfe8e4; }
.batch-panel-title { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 9px; color: #374151; font-size: 12px; }
.batch-panel-title span { color: #7b8794; }
.batch-table-wrap { overflow-x: auto; }
.batch-detail-table { width: 100%; border-collapse: collapse; font-size: 12px; background: #fff; border: 1px solid #e3e9e6; border-radius: 8px; overflow: hidden; }
.batch-detail-table th, .batch-detail-table td { padding: 9px 10px; border-bottom: 1px solid #edf1f3; text-align: left; }
.batch-detail-table th { background: #f0f6f3; color: #4b5563; font-weight: 700; }
.batch-detail-table tr:last-child td { border-bottom: 0; }
.batch-status { display: inline-flex; padding: 4px 8px; border-radius: 999px; font-size: 10px; font-weight: 700; white-space: nowrap; }
.batch-status-active { background: #dcfce7; color: #15803d; }
.batch-status-expired { background: #fee2e2; color: #b91c1c; }
.batch-status-depleted { background: #eef2f7; color: #64748b; }
.batch-status-no-expiry { background: #e0f2fe; color: #0369a1; }
.batch-empty { color: #7b8794; font-size: 12px; padding: 8px 0; }
.current-inventory-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid #edf1f3;
}
.current-inventory-pagination-summary { color: #6b7280; font-size: 12px; }
.current-inventory-pagination-controls { display: flex; align-items: center; gap: 10px; }
.inventory-page-btn {
    border: 1px solid #d9e2de;
    background: #fff;
    color: #118d6d;
    border-radius: 7px;
    padding: 7px 10px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}
.inventory-page-btn:hover:not(:disabled) { background: #eef7f4; }
.inventory-page-btn:disabled { color: #aeb8b4; background: #f7f8f8; cursor: not-allowed; }
.inventory-page-indicator { min-width: 80px; text-align: center; color: #4b5563; font-size: 12px; font-weight: 600; }
.current-inventory-note { display: flex; align-items: center; gap: 8px; margin-top: 11px; color: #6b7280; font-size: 12px; }
.current-inventory-note i { color: #118d6d; }
@media (max-width: 700px) {
    .current-inventory-batch-panel { padding-left: 18px; }
    .current-inventory-total { display: none; }
    .current-inventory-pagination { align-items: flex-start; flex-direction: column; }
    .current-inventory-pagination-controls { width: 100%; justify-content: space-between; }
}

</style>

<script>
(function () {
    var pageSize = <?= (int) $inventoryPageSize; ?>;
    var currentPage = 1;
    var productRows = Array.prototype.slice.call(document.querySelectorAll('.current-inventory-product-row'));
    var paginationSummary = document.getElementById('current-inventory-pagination-summary');
    var pageIndicator = document.getElementById('inventory-page-indicator');
    var prevButton = document.getElementById('inventory-page-prev');
    var nextButton = document.getElementById('inventory-page-next');

    function closeAllBatchRows() {
        document.querySelectorAll('.inventory-product-check').forEach(function (checkbox) {
            checkbox.checked = false;
            var id = checkbox.getAttribute('data-product-id');
            var row = document.getElementById('inventory-batches-' + id);
            if (row) row.hidden = true;
        });
    }

    function closeOtherBatchRows(activeProductId) {
        document.querySelectorAll('.inventory-product-check').forEach(function (checkbox) {
            var id = checkbox.getAttribute('data-product-id');
            if (id !== activeProductId) {
                checkbox.checked = false;
                var row = document.getElementById('inventory-batches-' + id);
                if (row) row.hidden = true;
            }
        });
    }

    function renderPage() {
        var total = productRows.length;
        var totalPages = Math.max(1, Math.ceil(total / pageSize));
        currentPage = Math.min(Math.max(currentPage, 1), totalPages);
        closeAllBatchRows();

        var start = (currentPage - 1) * pageSize;
        var end = Math.min(start + pageSize, total);

        productRows.forEach(function (row, index) {
            var visible = index >= start && index < end;
            row.hidden = !visible;
            var productId = row.getAttribute('data-product-id');
            var batchRow = document.getElementById('inventory-batches-' + productId);
            if (batchRow) batchRow.hidden = true;
        });

        paginationSummary.textContent = total === 0
            ? 'No products'
            : 'Showing ' + (start + 1) + '–' + end + ' of ' + total + ' products';
        pageIndicator.textContent = 'Page ' + currentPage + ' of ' + totalPages;
        prevButton.disabled = currentPage <= 1;
        nextButton.disabled = currentPage >= totalPages;
    }

    document.querySelectorAll('.inventory-product-check').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            var productId = this.getAttribute('data-product-id');
            var row = document.getElementById('inventory-batches-' + productId);
            if (!row) return;

            if (this.checked) {
                closeOtherBatchRows(productId);
                row.hidden = false;
            } else {
                row.hidden = true;
            }
        });
    });

    prevButton.addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            renderPage();
        }
    });

    nextButton.addEventListener('click', function () {
        var totalPages = Math.max(1, Math.ceil(productRows.length / pageSize));
        if (currentPage < totalPages) {
            currentPage++;
            renderPage();
        }
    });

    renderPage();
})();
</script>
