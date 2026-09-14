<?php

/** @var array $products */
/** @var array $pagination */
/** @var array $categories */
/** @var array $productTypes */

$products = $products ?? [];

$pagination = $pagination ?? [
    'page' => 1,
    'limit' => 20,
    'total' => 0,
    'total_pages' => 0,
    'has_previous' => false,
    'has_next' => false
];

$currentPage = (int) $pagination['page'];
$totalPages  = (int) $pagination['total_pages'];
$total       = (int) $pagination['total'];
$limit       = (int) $pagination['limit'];

?>

<div class="products-header">

    <input
        type="text"
        id="product-search"
        class="form-control"
        placeholder="Search product name or barcode"
    >

<select
    id="category-filter"
    class="form-select">

    <option value="">All Categories</option>

    <?php foreach ($categories as $category): ?>

        <option
            value="<?= htmlspecialchars($category['category_name']) ?>">

            <?= htmlspecialchars($category['category_name']) ?>

        </option>

    <?php endforeach; ?>

</select>


<select
    id="type-filter"
    class="form-select">

    <option value="">All Types</option>

    <?php foreach ($productTypes as $type): ?>

        <option
            value="<?= htmlspecialchars($type['type_name']) ?>">

            <?= htmlspecialchars($type['type_name']) ?>

        </option>

    <?php endforeach; ?>

</select>

</div>


<div class="products-table-wrapper">

    <table class="products-table">

        <thead>

            <tr>

                <th>Code</th>
                <th>Name</th>
                <th>Type</th>
                <th>Category</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Status</th>
                <th>Add</th>

            </tr>

        </thead>


        <tbody id="products-table-body">

            <?php if (empty($products)): ?>

                <tr>

                    <td colspan="8">

                        No products available.

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($products as $product): ?>

                    <tr
                        class="product-row"

                        data-name="<?= htmlspecialchars(
                            strtolower(
                                $product['product_name']
                            )
                        ) ?>"

                        data-barcode="<?= htmlspecialchars(
                            strtolower(
                                $product['barcode']
                            )
                        ) ?>"

                        data-category="<?= htmlspecialchars(
                            strtolower(
                                $product['category_name']
                            )
                        ) ?>"

                        data-type="<?= htmlspecialchars(
                            strtolower(
                                $product['type_name']
                            )
                        ) ?>"
                    >

                        <td>
                            <?= htmlspecialchars(
                                $product['barcode']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $product['product_name']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $product['type_name']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $product['category_name']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $product['quantity']
                            ) ?>
                        </td>

                        <td>
                            ₱<?= number_format(
                                (float) $product['selling_price'],
                                2
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $product['product_status']
                            ) ?>
                        </td>

                        <td>

                            <button
                                type="button"

                                class="btn btn-success btn-sm add-product"

                                data-id="<?= htmlspecialchars(
                                    $product['product_id']
                                ) ?>"

                                data-name="<?= htmlspecialchars(
                                    $product['product_name']
                                ) ?>"

                                data-price="<?= htmlspecialchars(
                                    $product['selling_price']
                                ) ?>"

                                data-barcode="<?= htmlspecialchars(
                                    $product['barcode']
                                ) ?>"
                            >

                                <i class="bi bi-cart-plus"></i>

                                Add

                            </button>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

        </tbody>

    </table>

</div>


<!-- ==========================================================
     PAGINATION
     ========================================================== -->

<div
    class="products-pagination"
    id="products-pagination"
    data-page="<?= $currentPage ?>"
    data-limit="<?= $limit ?>"
>

    <div class="pagination-info">

        <?php if ($total > 0): ?>

            Showing

            <strong>
                <?= (($currentPage - 1) * $limit) + 1 ?>
            </strong>

            –

            <strong>
                <?= min(
                    $currentPage * $limit,
                    $total
                ) ?>
            </strong>

            of

            <strong>
                <?= $total ?>
            </strong>

            products

        <?php else: ?>

            No products found.

        <?php endif; ?>

    </div>


    <div class="pagination-controls">

        <button
            type="button"
            id="products-prev"
            class="btn btn-secondary btn-sm"
            <?= !$pagination['has_previous']
                ? 'disabled'
                : '' ?>
        >

            Previous

        </button>


        <span class="pagination-page">

            Page

            <strong>
                <?= $currentPage ?>
            </strong>

            of

            <strong>
                <?= max(1, $totalPages) ?>
            </strong>

        </span>


<button
    type="button"
    id="products-next"
    class="btn btn-secondary btn-sm"
    <?= !$pagination['has_next'] ? 'disabled' : '' ?>>
    Next
</button>

</div>
