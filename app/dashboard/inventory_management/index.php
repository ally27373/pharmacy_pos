<?php

require_once '../../../config/session.php';
require_once '../../Middleware/AuthMiddleware.php';

AuthMiddleware::check();

$menu = "inventory_management";
$page = "inventory_management";

require_once '../../Controllers/InventoryController.php';

$inventoryController = new InventoryController();

$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(max(1, (int) ($_GET['limit'] ?? 20)), 100);
$search = trim((string) ($_GET['search'] ?? ''));
$selectedCategory = trim((string) ($_GET['category'] ?? ''));
$selectedType = trim((string) ($_GET['type'] ?? ''));

$productResult = $inventoryController->getProducts(
    $currentPage,
    $limit,
    $search,
    $selectedCategory,
    $selectedType
);

$products = $productResult['products'] ?? [];
$pagination = $productResult['pagination'] ?? [];
$currentPage = (int) ($pagination['page'] ?? $currentPage);
$categories = $inventoryController->getCategories();
$types = $inventoryController->getTypes();

require_once '../../../includes/header.php';

?>

<link rel="stylesheet" href="/assets/css/dashboard.css">
<link rel="stylesheet" href="/assets/css/sidebar.css">
<link rel="stylesheet" href="/assets/css/navbar.css">
<link rel="stylesheet" href="/assets/css/inventory.css">

<div class="dashboard-wrapper">

    <?php include '../../../includes/sidebar.php'; ?>
    <?php include 'modals/stock_modal.php'; ?>

    <div class="main-content">

        <?php include '../../../includes/navbar.php'; ?>

        <div class="dashboard-content">

        <div id="inventory-alert"></div>

    <div class="page-header">

        <h2>Inventory</h2>

        <p>Manage product inventory.</p>

    </div>

    <section class="inventory-card">

        <div class="inventory-toolbar">

            <div class="inventory-search-wrap">
                <i class="bi bi-search"></i>
                <input
                    type="text"
                    id="inventory-search"
                    class="inventory-search"
                    placeholder="Search product name or barcode"
                    value="<?= htmlspecialchars($search) ?>"
                    autocomplete="off">
            </div>

            <select id="category-filter" class="inventory-filter">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <?php $categoryValue = $category['category_name']; ?>
                    <option value="<?= htmlspecialchars($categoryValue) ?>" <?= strcasecmp($selectedCategory, $categoryValue) === 0 ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select id="type-filter" class="inventory-filter">
                <option value="">All Types</option>
                <?php foreach ($types as $type): ?>
                    <?php $typeValue = $type['type_name']; ?>
                    <option value="<?= htmlspecialchars($typeValue) ?>" <?= strcasecmp($selectedType, $typeValue) === 0 ? 'selected' : '' ?>>
                        <?= htmlspecialchars($type['type_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button
                type="button"
                class="btn btn-primary inventory-add-btn"
                data-bs-toggle="modal"
                data-bs-target="#addProductModal">
                <i class="bi bi-plus-lg me-1"></i>
                Add Item
            </button>

        </div>

        <div class="inventory-summary">
            <div>
                <strong id="inventory-result-count"><?= (int) ($pagination['total'] ?? 0) ?></strong>
                <span>product<?= ((int) ($pagination['total'] ?? 0)) === 1 ? '' : 's' ?></span>
            </div>
            <button type="button" id="inventory-reset" class="inventory-reset-btn">
                <i class="bi bi-arrow-counterclockwise me-1"></i>
                Reset Filters
            </button>
        </div>

        <div class="inventory-table-wrap">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th class="col-index">#</th>
                        <th>Product</th>
                        <th>Barcode</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Unit Cost</th>
                        <th class="text-end">Selling Price</th>
                        <th>Status</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>

                <tbody id="inventory-table-body">
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="10" class="inventory-empty">
                            <i class="bi bi-box-seam"></i>
                            <strong>No products found</strong>
                            <span>Add a product or adjust your filters.</span>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $index => $product): ?>
                        <?php
                            $status = $product['product_status'] ?? 'Available';
                            $statusClass = match ($status) {
                                'Available' => 'status-available',
                                'Low Stock' => 'status-low',
                                'Out of Stock' => 'status-out',
                                'Expired' => 'status-expired',
                                default => 'status-default'
                            };
                            $expiry = $product['expiration_date'] ?? null;
                            $expiryLabel = $expiry ? date('M d, Y', strtotime($expiry)) : 'N/A';
                            $quantity = (int) ($product['quantity'] ?? 0);
                        ?>
                        <tr
                            class="inventory-row"
                            data-name="<?= htmlspecialchars(strtolower($product['product_name'] ?? '')) ?>"
                            data-barcode="<?= htmlspecialchars(strtolower($product['barcode'] ?? '')) ?>"
                            data-category="<?= htmlspecialchars(strtolower($product['category_name'] ?? '')) ?>"
                            data-type="<?= htmlspecialchars(strtolower($product['type_name'] ?? '')) ?>">

                            <td class="col-index"><?= (($currentPage - 1) * $limit) + $index + 1 ?></td>

                            <td class="product-cell">
                                <div class="product-name">
                                    <?= htmlspecialchars($product['product_name'] ?? 'Unnamed Product') ?>
                                </div>
                                <?php if (!empty($product['generic_name']) && $product['generic_name'] !== 'N/A'): ?>
                                    <div class="product-subname">
                                        <?= htmlspecialchars($product['generic_name']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="barcode-cell">
                                <?= htmlspecialchars($product['barcode'] ?? 'N/A') ?>
                            </td>

                            <td><?= htmlspecialchars($product['type_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($product['category_name'] ?? 'N/A') ?></td>

                            <td class="text-end">
                                <span class="quantity-value <?= $quantity <= 0 ? 'quantity-zero' : '' ?>">
                                    <?= $quantity ?> pcs
                                </span>
                            </td>

                            <td class="money-cell text-end">
                                ₱<?= number_format((float) ($product['unit_cost'] ?? 0), 2) ?>
                            </td>

                            <td class="money-cell text-end selling-price">
                                ₱<?= number_format((float) ($product['selling_price'] ?? 0), 2) ?>
                            </td>

                            <td>
                                <span class="inventory-status <?= $statusClass ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </td>

                            <td class="action-cell">
                                <div class="inventory-actions">
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary btn-sm view-product"
                                        data-product="<?= (int) $product['product_id'] ?>"
                                        title="View product and batches"
                                        aria-label="View product and batches">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-outline-warning btn-sm edit-product"
                                        data-product="<?= (int) $product['product_id'] ?>"
                                        title="Edit product"
                                        aria-label="Edit product">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-info btn-sm stock-product"
                                        data-product="<?= (int) $product['product_id'] ?>"
                                        data-name="<?= htmlspecialchars($product['product_name'] ?? '') ?>"
                                        title="Adjust stock"
                                        aria-label="Adjust stock">
                                        <i class="bi bi-box-seam"></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-danger btn-sm delete-product"
                                        data-product="<?= (int) $product['product_id'] ?>"
                                        data-name="<?= htmlspecialchars($product['product_name'] ?? '') ?>"
                                        title="Delete product"
                                        aria-label="Delete product">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="inventory-no-results" class="inventory-empty inventory-empty-hidden">
            <i class="bi bi-search"></i>
            <strong>No matching products</strong>
            <span>Try another search term or reset the filters.</span>
        </div>

        <div class="inventory-pagination" id="inventory-pagination">
            <div class="pagination-info">
                <?php if (($pagination['total'] ?? 0) > 0): ?>
                    Showing <strong><?= (($currentPage - 1) * $limit) + 1 ?></strong>–<strong><?= min($currentPage * $limit, (int) $pagination['total']) ?></strong>
                    of <strong><?= (int) $pagination['total'] ?></strong> products
                <?php else: ?>
                    No products found.
                <?php endif; ?>
            </div>

            <div class="pagination-controls">
                <?php
                    $query = $_GET;
                    $query['limit'] = $limit;
                ?>
                <a class="btn btn-outline-secondary btn-sm <?= empty($pagination['has_previous']) ? 'disabled' : '' ?>"
                   href="?<?= http_build_query(array_merge($query, ['page' => max(1, $currentPage - 1)])) ?>"
                   <?= empty($pagination['has_previous']) ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Previous</a>

                <span class="pagination-page">
                    Page <strong><?= $currentPage ?></strong> of <strong><?= max(1, (int) ($pagination['total_pages'] ?? 0)) ?></strong>
                </span>

                <a class="btn btn-outline-secondary btn-sm <?= empty($pagination['has_next']) ? 'disabled' : '' ?>"
                   href="?<?= http_build_query(array_merge($query, ['page' => $currentPage + 1])) ?>"
                   <?= empty($pagination['has_next']) ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Next</a>
            </div>
        </div>

    </section>

<div
class="modal fade"
id="productModal"
tabindex="-1">

<div class="modal-dialog modal-lg">

<div class="modal-content">

<div class="modal-header">

<h5 class="modal-title">

Product & Batch Details

</h5>

<button
class="btn-close"
data-bs-dismiss="modal">

</button>

</div>

<div
class="modal-body"
id="productModalBody">

</div>

</div>

</div>

</div>

<div
class="modal fade"
id="editProductModal"
tabindex="-1">

<div class="modal-dialog modal-xl">

<div class="modal-content">

<div class="modal-header">

<h5 class="modal-title">

<i class="bi bi-pencil-square me-2"></i>

Edit Product

</h5>

<button
class="btn-close"
data-bs-dismiss="modal">
</button>

</div>

<div class="modal-body">

<form id="edit-product-form">

<input
type="hidden"
id="edit_product_id">

<div class="row">

<div class="col-md-6 mb-3">

<label>Product Name</label>

<input
type="text"
id="edit_product_name"
class="form-control">

</div>

<div class="col-md-6 mb-3">

<label>Barcode</label>

<input
type="text"
id="edit_barcode"
class="form-control">

</div>

<div class="col-md-6 mb-3">

<label>Generic Name</label>

<input
type="text"
id="edit_generic_name"
class="form-control">

</div>

<div class="col-md-6 mb-3">

<label>Brand Name</label>

<input
type="text"
id="edit_brand_name"
class="form-control">

</div>

<div class="col-md-4 mb-3">

<label>Category</label>

<select
id="edit_category_id"
class="form-select">

<option value="">
Loading...
</option>

</select>

</div>

<div class="col-md-4 mb-3">

<label>Product Type</label>

<select
id="edit_type_id"
class="form-select">

<option value="">
Loading...
</option>

</select>

</div>

<div class="col-md-4 mb-3">

<label>Supplier</label>

<input
type="text"
id="edit_supplier_name"
class="form-control"
placeholder="Enter supplier name"
autocomplete="off">

</div>

<div class="col-md-4 mb-3">

<label>Dosage</label>

<input
type="text"
id="edit_dosage"
class="form-control">

</div>

<div class="col-md-4 mb-3">

<label>Strength</label>

<input
type="text"
id="edit_strength"
class="form-control">

</div>

<div class="col-md-4 mb-3">

<label>Unit</label>

<select
id="edit_unit"
class="form-select">

<option>Box</option>
<option>Bottle</option>
<option>Piece</option>
<option>Strip</option>

</select>

</div>

<div class="col-md-4 mb-3">

<label>Current Unit Cost</label>

<input
type="number"
step="0.01"
id="edit_unit_cost"
class="form-control"
readonly>

</div>

<div class="col-md-4 mb-3">

<label>Selling Price</label>

<input
type="number"
step="0.01"
id="edit_selling_price"
class="form-control">

</div>

<div class="col-md-12">

<label>Description</label>

<textarea
id="edit_description"
rows="3"
class="form-control"></textarea>

</div>

</div>

</form>

</div>

<div class="modal-footer">

<button
class="btn btn-secondary"
data-bs-dismiss="modal">

Close

</button>

<button
class="btn btn-warning"
id="update-product">

<i class="bi bi-floppy-fill me-2"></i>

Update Product

</button>

</div>

</div>

</div>

</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">

    <div class="modal-dialog modal-xl">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">

                    <i class="bi bi-capsule"></i>

                    Add New Product

                </h5>

                <button
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <form id="add-product-form">

                    <div class="row">

                        <div class="col-md-6 mb-3">

                            <label>Product Name</label>

                            <input
                                type="text"
                                id="product_name"
                                class="form-control">

                        </div>

                        <div class="col-md-6 mb-3">

                            <label>Barcode</label>

                            <input
                                type="text"
                                id="barcode"
                                class="form-control">

                        </div>

                        <div class="col-md-6 mb-3">

                            <label>Generic Name</label>

                            <input
                                type="text"
                                id="generic_name"
                                class="form-control">

                        </div>

                        <div class="col-md-6 mb-3">

                            <label>Brand Name</label>

                            <input
                                type="text"
                                id="brand_name"
                                class="form-control">

                        </div>

                       <div class="col-md-4 mb-3">

                            <label>Category</label>

                            <select
                                id="category_id"
                                class="form-select">

                                <option value="">Select Category</option>

                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int) $category['category_id'] ?>">
                                        <?= htmlspecialchars($category['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label>Product Type</label>

                            <select
                                id="type_id"
                                class="form-select">

                                <option value="">Select Type</option>

                                <?php foreach ($types as $type): ?>
                                    <option value="<?= (int) $type['type_id'] ?>">
                                        <?= htmlspecialchars($type['type_name']) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label>Supplier</label>

                            <input
                                type="text"
                                id="supplier_name"
                                class="form-control"
                                placeholder="Enter supplier name"
                                autocomplete="off">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label>Dosage</label>

                            <input
                                type="text"
                                id="dosage"
                                class="form-control">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label>Strength</label>

                            <input
                                type="text"
                                id="strength"
                                class="form-control">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label>Unit</label>

                            <select
                                id="unit"
                                class="form-select">

                                <option>Box</option>

                                <option>Bottle</option>

                                <option>Piece</option>

                                <option>Strip</option>

                                <option>Pack</option>

                            </select>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label>Quantity</label>

                            <input
                                type="number"
                                id="quantity"
                                class="form-control">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label>Unit Cost</label>

                            <input
                                type="number"
                                step="0.01"
                                id="unit_cost"
                                class="form-control">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label>Selling Price</label>

                            <input
                                type="number"
                                step="0.01"
                                id="selling_price"
                                class="form-control">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label>Batch Number</label>

                            <input
                                type="text"
                                id="batch_number"
                                class="form-control"
                                placeholder="e.g. BATCH-2026-001"
                                autocomplete="off">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label>Expiration Date</label>

                            <input
                                type="date"
                                id="expiration_date"
                                class="form-control">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label>Date Received</label>

                            <input
                                type="date"
                                id="received_date"
                                class="form-control"
                                value="<?= date('Y-m-d') ?>">

                        </div>

                        <div class="col-md-12">

                            <label>Description</label>

                            <textarea
                                id="description"
                                class="form-control"
                                rows="3"></textarea>

                        </div>

                    </div>

                </form>

            </div>

            <div class="modal-footer">

                <button
                    class="btn btn-secondary"
                    data-bs-dismiss="modal">

                    Close

                </button>

<button
    type="button"
    class="btn btn-success"
    id="save-product">

    <i class="bi bi-check-circle-fill"></i>

    Save Product

</button>
            </div>

        </div>

    </div>

</div>

<!-- Delete Product Modal -->

<div class="modal fade" id="deleteProductModal" tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header bg-danger text-white">

                <h5 class="modal-title">

                    <i class="bi bi-trash3-fill me-2"></i>

                    Delete Product

                </h5>

                <button
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body text-center">

                <i class="bi bi-exclamation-triangle-fill text-warning"
                   style="font-size:60px;"></i>

                <h4 class="mt-3">

                    Are you sure?

                </h4>

                <p class="text-muted">

                    This action cannot be undone.

                </p>

                <h5 id="delete-product-name"
                    class="fw-bold text-danger">

                </h5>

            </div>

            <div class="modal-footer">

                <button
                    class="btn btn-secondary"
                    data-bs-dismiss="modal">

                    Cancel

                </button>

                <button
                    class="btn btn-danger"
                    id="confirm-delete-product">

                    <i class="bi bi-trash-fill me-2"></i>

                    Delete

                </button>

            </div>

        </div>

    </div>

</div>

<script src="/assets/js/inventory.js"></script>

<?php require_once '../../../includes/footer.php'; ?>