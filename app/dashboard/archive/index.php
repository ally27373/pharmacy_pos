<?php
require_once '../../../config/session.php';
require_once '../../Middleware/AuthMiddleware.php';

AuthMiddleware::check();

require_once '../../Controllers/InventoryController.php';

$inventoryController = new InventoryController();

// Get archived products
$archived = $inventoryController->getArchivedProducts();

$menu = "archive";
$page = "archive";
require_once '../../../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/dashboard-shell.css">
<link rel="stylesheet" href="/assets/css/sidebar.css">
<link rel="stylesheet" href="/assets/css/navbar.css">
<link rel="stylesheet" href="/assets/css/inventory.css">

<div class="dashboard-wrapper">
    <?php include '../../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../../includes/navbar.php'; ?>
        <div class="dashboard-content">
            <div id="archive-alert"></div>
            <div class="page-header">
                <h2>Archived Products</h2>
                <p>Manage archived inventory. Unarchive or permanently delete.</p>
            </div>
            <section class="inventory-card">
                <div class="inventory-table-wrap">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Barcode</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="archive-table-body">
                        <?php if (empty($archived)): ?>
                            <tr>
                                <td colspan="6" class="inventory-empty">
                                    <i class="bi bi-archive"></i>
                                    <strong>No archived products</strong>
                                    <span>Archived items will appear here.</span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($archived as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['product_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($product['barcode'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($product['type_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($product['category_name'] ?? 'N/A') ?></td>
                                <td><span class="inventory-status status-available">Archived</span></td>
                                <td>
                                    <div class="inventory-actions">
                                        <button type="button" class="btn btn-outline-success btn-sm unarchive-product"
                                            data-product="<?= (int) $product['product_id'] ?>"
                                            title="Unarchive">Unarchive</button>
                                        <button type="button" class="btn btn-outline-danger btn-sm delete-archived-product"
                                            data-product="<?= (int) $product['product_id'] ?>"
                                            title="Permanently delete">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>

<script src="/assets/js/archive.js"></script>
<?php require_once '../../../includes/footer.php'; ?>
