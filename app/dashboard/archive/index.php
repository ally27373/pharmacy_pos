<?php

require_once '../../../config/session.php';
require_once '../../Middleware/AuthMiddleware.php';

AuthMiddleware::admin();

require_once '../../Controllers/InventoryController.php';

$inventoryController = new InventoryController();

$search = trim((string) ($_GET['search'] ?? ''));
$selectedCategory = trim((string) ($_GET['category'] ?? ''));
$selectedType = trim((string) ($_GET['type'] ?? ''));

$archived = $inventoryController->getArchivedProducts(
    $search,
    $selectedCategory,
    $selectedType
);

$categories = $inventoryController->getCategories();
$types = $inventoryController->getTypes();

$menu = 'archive';
$page = 'archive';

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
                <p>Restore archived items, or permanently delete the ones you no longer need.</p>
            </div>

            <section class="inventory-card">

                <form class="inventory-toolbar" method="get" action="">
                    <input type="hidden" name="page" value="archive">

                    <div class="inventory-search-wrap">
                        <i class="bi bi-search"></i>
                        <input
                            type="search"
                            name="search"
                            id="archive-search"
                            class="inventory-search"
                            placeholder="Search name, barcode, generic or brand"
                            value="<?= htmlspecialchars($search) ?>"
                            autocomplete="off">
                    </div>

                    <select name="category" id="archive-category" class="inventory-filter">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option
                                value="<?= htmlspecialchars($category['category_name']) ?>"
                                <?= strcasecmp($selectedCategory, $category['category_name']) === 0 ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="type" id="archive-type" class="inventory-filter">
                        <option value="">All Types</option>
                        <?php foreach ($types as $type): ?>
                            <option
                                value="<?= htmlspecialchars($type['type_name']) ?>"
                                <?= strcasecmp($selectedType, $type['type_name']) === 0 ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['type_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel me-1"></i>
                        Filter
                    </button>

                    <?php if ($search !== '' || $selectedCategory !== '' || $selectedType !== ''): ?>
                        <a href="?page=archive" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>
                            Reset
                        </a>
                    <?php endif; ?>
                </form>

                <div class="inventory-summary">
                    <div>
                        <strong id="archive-result-count"><?= count($archived) ?></strong>
                        <span>archived product<?= count($archived) === 1 ? '' : 's' ?></span>
                    </div>
                </div>

                <div class="inventory-table-wrap">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Barcode</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Price</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="archive-table-body">
                        <?php if (empty($archived)): ?>
                            <tr>
                                <td colspan="7" class="inventory-empty">
                                    <i class="bi bi-archive"></i>
                                    <strong>
                                        <?= ($search !== '' || $selectedCategory !== '' || $selectedType !== '')
                                            ? 'No archived products match your filters.'
                                            : 'No archived products' ?>
                                    </strong>
                                    <span>
                                        <?= ($search !== '' || $selectedCategory !== '' || $selectedType !== '')
                                            ? 'Try another search term or reset the filters.'
                                            : 'Archived items will appear here.' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($archived as $product): ?>
                                <tr class="archive-row">
                                    <td class="product-cell">
                                        <div class="product-name">
                                            <?= htmlspecialchars($product['product_name'] ?? 'Unnamed Product') ?>
                                        </div>
                                        <?php if (!empty($product['generic_name'])): ?>
                                            <div class="product-subname">
                                                <?= htmlspecialchars($product['generic_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="barcode-cell">
                                        <?= htmlspecialchars($product['barcode'] ?? 'N/A') ?>
                                    </td>
                                    <td><?= htmlspecialchars($product['type_name'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($product['category_name'] ?? '—') ?></td>
                                    <td class="text-end">
                                        <span class="quantity-value <?= (int) ($product['quantity'] ?? 0) <= 0 ? 'quantity-zero' : '' ?>">
                                            <?= (int) ($product['quantity'] ?? 0) ?> pcs
                                        </span>
                                    </td>
                                    <td class="money-cell text-end">
                                        ₱<?= number_format((float) ($product['selling_price'] ?? 0), 2) ?>
                                    </td>
                                    <td class="action-cell">
                                        <div class="inventory-actions">
                                            <button
                                                type="button"
                                                class="btn btn-outline-success btn-sm unarchive-product"
                                                data-product="<?= (int) $product['product_id'] ?>"
                                                data-name="<?= htmlspecialchars($product['product_name'] ?? '') ?>"
                                                title="Restore this product to inventory"
                                                aria-label="Unarchive product">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>

                                            <button
                                                type="button"
                                                class="btn btn-outline-danger btn-sm delete-archived-product"
                                                data-product="<?= (int) $product['product_id'] ?>"
                                                data-name="<?= htmlspecialchars($product['product_name'] ?? '') ?>"
                                                title="Permanently delete this archived product"
                                                aria-label="Delete archived product">
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

                <div class="current-inventory-note">
                    <i class="bi bi-info-circle"></i>
                    <span>Unarchive restores the product to the active inventory list. Permanent delete only works on archived products that have no sales or batch history.</span>
                </div>

            </section>

            <style>
                /* The archive table is narrow by design (7 columns) — let it
                   shrink to the viewport instead of forcing a horizontal
                   scrollbar that hides the Actions buttons off-screen. */
                .inventory-card .inventory-table-wrap{ overflow-x:visible; }
                .inventory-card .inventory-table{ width:100%; min-width:0; table-layout:auto; }
                .inventory-card .inventory-table td,
                .inventory-card .inventory-table th{ padding:11px 10px; font-size:13px; }
                .inventory-card .inventory-table .col-actions,
                .inventory-card .inventory-table .action-cell{ white-space:nowrap; width:1%; }
                .inventory-card .inventory-toolbar{ flex-wrap:wrap; }
                .inventory-card .inventory-search-wrap{ flex:1 1 260px; }
                @media (max-width:768px){
                    /* Below tablet the table legitimately needs to scroll —
                       re-enable it only there rather than on every screen. */
                    .inventory-card .inventory-table-wrap{ overflow-x:auto; }
                    .inventory-card .inventory-table{ min-width:720px; }
                }
            </style>

        </div>

    </div>

</div>

<script src="/assets/js/archive.js"></script>
<?php require_once '../../../includes/footer.php'; ?>