<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/session.php';
require_once __DIR__ . '/../../../Middleware/AuthMiddleware.php';

header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| Access control
|--------------------------------------------------------------------------
|
| Inventory Management is Administrator-only. Guarding the page that
| renders these forms is not enough: a form is not a control, so any
| signed-in account -- including a Cashier -- could POST straight here.
|
*/
AuthMiddleware::requireAdminApi();

require_once __DIR__ . '/../../../Controllers/InventoryController.php';

try {
    $controller = new InventoryController();
    $productId = (int) ($_GET['id'] ?? 0);
    $product = $controller->getProductDetails($productId);

    if (!$product) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Product not found.']);
        exit;
    }

    echo json_encode($product);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
