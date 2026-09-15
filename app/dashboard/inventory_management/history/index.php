<?php

require_once '../../../../config/session.php';
require_once '../../../Middleware/AuthMiddleware.php';
require_once '../../../Controllers/InventoryHistoryController.php';

AuthMiddleware::check();

$menu = "inventory_management";
$page = "inventory_history";

$controller = new InventoryHistoryController();

$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(max(1, (int) ($_GET['limit'] ?? 20)), 100);
$search = trim((string) ($_GET['search'] ?? ''));
$selectedAction = strtoupper(trim((string) ($_GET['action'] ?? '')));
$fromDate = trim((string) ($_GET['from'] ?? ''));
$toDate = trim((string) ($_GET['to'] ?? ''));

$historyResult = $controller->getHistory(
    $currentPage,
    $limit,
    $search,
    $selectedAction,
    $fromDate,
    $toDate
);

$history = $historyResult['history'] ?? [];
$pagination = $historyResult['pagination'] ?? [];
$currentPage = (int) ($pagination['page'] ?? $currentPage);

require_once '../../../../includes/header.php';

?>

<link rel="stylesheet"
      href="/assets/css/dashboard.css">

<link rel="stylesheet"
      href="/assets/css/sidebar.css">

<link rel="stylesheet"
      href="/assets/css/navbar.css">

<link rel="stylesheet"
      href="/assets/css/inventory.css">

<link rel="stylesheet"
      href="/assets/css/sales.css">

<link rel="stylesheet"
      href="/assets/css/inventory_history.css">


<div class="dashboard-wrapper">

    <?php include '../../../../includes/sidebar.php'; ?>


    <div class="main-content">

        <?php include '../../../../includes/navbar.php'; ?>


        <div class="dashboard-content">


            <div class="page-header">

                <h2>
                    Inventory History
                </h2>

                <p>
                    Track all inventory stock movements.
                </p>

            </div>


            <div class="sales-card inventory-history-card">


                <!-- TOOLBAR -->

<div class="sales-toolbar history-toolbar">

    <!-- PRODUCT SEARCH -->

    <input
        type="text"
        id="history-search"
        class="search-box"
        placeholder="Search Product"
        value="<?= htmlspecialchars($search) ?>">


    <!-- ACTION FILTER -->

    <select
        id="history-action-filter"
        class="history-filter">

        <option value="">
            All Actions
        </option>

        <option value="IN" <?= $selectedAction === 'IN' ? 'selected' : '' ?>>
            Stock In
        </option>

        <option value="OUT" <?= $selectedAction === 'OUT' ? 'selected' : '' ?>>
            Stock Out
        </option>

    </select>


    <!-- DATE FROM -->

    <input
        type="date"
        id="history-date-from"
        class="history-date-filter"
        title="From Date"
        value="<?= htmlspecialchars($fromDate) ?>">


    <!-- DATE TO -->

    <input
        type="date"
        id="history-date-to"
        class="history-date-filter"
        title="To Date"
        value="<?= htmlspecialchars($toDate) ?>">


    <!-- CLEAR FILTERS -->

    <button
        type="button"
        id="clear-history-filters"
        class="btn btn-outline-secondary">

        <i class="bi bi-arrow-counterclockwise"></i>

        Clear

    </button>

</div>


                <!-- HISTORY TABLE -->

                <div class="table-responsive">

                    <table class="sales-table inventory-history-table">

                        <thead>

                            <tr>

                                <th>
                                    Product
                                </th>

                                <th>
                                    Code
                                </th>

                                <th>
                                    Action
                                </th>

                                <th>
                                    Quantity
                                </th>

                                <th>
                                    Remarks
                                </th>

                                <th>
                                    Date
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php if(empty($history)): ?>

                            <tr>

<td
    colspan="6"
    class="history-empty">

    <div class="history-empty-icon">

        <i class="bi bi-clock-history"></i>

    </div>

    <p class="history-empty-title">

        No inventory history found

    </p>

    <p class="history-empty-text">

        Stock movements will appear here
        once inventory activity is recorded.

    </p>

</td>

                            </tr>


                        <?php else: ?>


                            <?php foreach($history as $item): ?>


<tr
    class="history-row"

    data-product="<?= htmlspecialchars(
        strtolower(
            $item['product_name']
        )
    ); ?>"

    data-action="<?= htmlspecialchars(
        $item['action_type']
    ); ?>"

    data-date="<?= htmlspecialchars(
        date(
            'Y-m-d',
            strtotime($item['created_at'])
        )
    ); ?>">


                                    <td>

                                    <div class="history-product-name">
                                        <?= htmlspecialchars(
                                            $item['product_name']
                                        ); ?>

                                    </div>



                                    </td>


                                    <td>

                                    <div class="history-barcode">
                                        <?= htmlspecialchars(
                                            $item['barcode']
                                        ); ?>

                                    </div>


                                    </td>


                                    <td>


                                       <?php if(
    $item['action_type'] === 'IN'
): ?>

    <span class="history-badge history-in">

        <i class="bi bi-arrow-down-circle-fill"></i>

        Stock In

    </span>

<?php else: ?>

    <span class="history-badge history-out">

        <i class="bi bi-arrow-up-circle-fill"></i>

        Stock Out

    </span>

<?php endif; ?>


                                    </td>


<td>

    <?php if(
        $item['action_type'] === 'IN'
    ): ?>

        <span class="history-quantity history-quantity-in">

            +<?= (int)$item['quantity']; ?>

        </span>

    <?php else: ?>

        <span class="history-quantity history-quantity-out">

            -<?= (int)$item['quantity']; ?>

        </span>

    <?php endif; ?>

</td>


                                    <td>

                                    <div class="history-remarks">

                                        <?= htmlspecialchars(
                                            $item['remarks'] ?? ''
                                        ); ?>

                                    </div>



                                    </td>

                                    <td>

    <?php

    $historyDate =
        new DateTime(
            $item['created_at']
        );

    ?>

    <div class="history-date">

        <div class="history-date-main">

            <?= $historyDate->format('M d, Y'); ?>

        </div>

        <div class="history-date-time">

            <?= $historyDate->format('h:i A'); ?>

        </div>

    </div>

</td>


                                </tr>


                            <?php endforeach; ?>


                        <?php endif; ?>


                        </tbody>

                    </table>

                </div>

                <div class="inventory-pagination history-pagination" id="history-pagination">
                    <div class="pagination-info">
                        <?php if (($pagination['total'] ?? 0) > 0): ?>
                            Showing <strong><?= (($currentPage - 1) * $limit) + 1 ?></strong>–<strong><?= min($currentPage * $limit, (int) $pagination['total']) ?></strong>
                            of <strong><?= (int) $pagination['total'] ?></strong> records
                        <?php else: ?>
                            No records found.
                        <?php endif; ?>
                    </div>

                    <div class="pagination-controls">
                        <?php $query = $_GET; $query['limit'] = $limit; ?>
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


            </div>


        </div>

    </div>

</div>


<script src="/assets/js/inventory_history.js"></script>


<?php

require_once '../../../../includes/footer.php';

?>
