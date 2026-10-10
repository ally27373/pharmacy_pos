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
    $productId = (int) ($_GET['product_id'] ?? 0);
    if ($productId <= 0) {
        throw new InvalidArgumentException('Invalid product.');
    }

    $controller = new InventoryController();
    $product = $controller->getProductDetails($productId);
    if (!$product) {
        throw new RuntimeException('Product not found.');
    }

    echo json_encode([
        'status' => 'success',
        'batches' => $controller->getProductBatches($productId),
        'product' => [
            'product_id' => $productId,
            'unit_cost' => (float) ($product['unit_cost'] ?? 0),
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
