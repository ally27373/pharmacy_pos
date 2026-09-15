<?php

require_once '../../Controllers/POSController.php';

$posController = new POSController();

$productResult = $posController->getProducts();

$products = $productResult['products'] ?? [];

$pagination = $productResult['pagination'] ?? [];

$categories = $posController->getCategories();

$productTypes = $posController->getProductTypes();

require_once '../../../config/session.php';
require_once '../../Middleware/AuthMiddleware.php';

AuthMiddleware::check();

$menu = "pos";
$page = "pos";

require_once '../../../includes/header.php';

?>

<link rel="stylesheet" href="/assets/css/pos.css">

<div class="terminal-page">

    <div class="terminal-layout">

        <div class="left-panel">

            <section class="cart-panel">

                <?php include __DIR__ . '/../partials/cart.php'; ?>

            </section>


            <section class="products-panel">

                <?php include __DIR__ . '/../partials/products.php'; ?>

            </section>

        </div>


        <aside class="payment-panel">

            <?php include __DIR__ . '/../partials/payment.php'; ?>

        </aside>

    </div>

</div>


<script src="/assets/js/pos.js"></script>

<?php require_once '../../../includes/footer.php'; ?>
```
