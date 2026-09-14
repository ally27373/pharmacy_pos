<?php

require_once __DIR__ . '/../../../Controllers/InventoryController.php';

$controller = new InventoryController();
$productId = (int) ($_GET['id'] ?? 0);
$product = $controller->getProductDetails($productId);

if (!$product) {
    echo '<p class="text-danger">Product not found.</p>';
    exit;
}

$batches = $controller->getProductBatches($productId);
$totalQuantity = 0;
foreach ($batches as $batch) {
    if (($batch['batch_status'] ?? '') !== 'Expired') {
        $totalQuantity += (int) $batch['quantity'];
    }
}
?>

<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h4 class="mb-1"><?= htmlspecialchars($product['product_name'] ?? 'Unnamed Product') ?></h4>
        <?php if (!empty($product['generic_name'])): ?>
            <div class="text-muted"><?= htmlspecialchars($product['generic_name']) ?></div>
        <?php endif; ?>
    </div>
    <span class="badge bg-success">Total Stock: <?= $totalQuantity ?> pcs</span>
</div>

<table class="table table-bordered table-sm mb-4">
    <tr><th>Barcode</th><td><?= htmlspecialchars($product['barcode'] ?? 'N/A') ?></td></tr>
    <tr><th>Category</th><td><?= htmlspecialchars($product['category_name'] ?? 'N/A') ?></td></tr>
    <tr><th>Type</th><td><?= htmlspecialchars($product['type_name'] ?? 'N/A') ?></td></tr>
    <tr><th>Supplier</th><td><?= htmlspecialchars($product['supplier_name'] ?? 'N/A') ?></td></tr>
    <tr><th>Unit</th><td><?= htmlspecialchars($product['unit'] ?? 'N/A') ?></td></tr>
    <tr><th>Selling Price</th><td>₱<?= number_format((float) ($product['selling_price'] ?? 0), 2) ?></td></tr>
    <tr><th>Description</th><td><?= nl2br(htmlspecialchars($product['description'] ?? '')) ?></td></tr>
</table>

<h5 class="mb-3">Inventory Batches</h5>

<?php if (empty($batches)): ?>
    <div class="alert alert-light border">No batch records found for this product.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead>
                <tr>
                    <th>Batch No.</th>
                    <th>Qty</th>
                    <th>Unit Cost</th>
                    <th>Expiry</th>
                    <th>Date Received</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($batches as $batch): ?>
                    <?php
                        $status = $batch['batch_status'] ?? 'Active';
                        $statusClass = match ($status) {
                            'Active' => 'bg-success',
                            'Expired' => 'bg-danger',
                            'Depleted' => 'bg-secondary',
                            default => 'bg-secondary',
                        };
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($batch['batch_number'] ?: 'N/A') ?></td>
                        <td><?= (int) $batch['quantity'] ?> pcs</td>
                        <td>₱<?= number_format((float) ($batch['unit_cost'] ?? 0), 2) ?></td>
                        <td><?= $batch['expiration_date'] ? htmlspecialchars(date('M d, Y', strtotime($batch['expiration_date']))) : 'N/A' ?></td>
                        <td><?= $batch['received_date'] ? htmlspecialchars(date('M d, Y', strtotime($batch['received_date']))) : 'N/A' ?></td>
                        <td><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($status) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
