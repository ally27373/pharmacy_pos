<?php

require_once __DIR__ . '/../../../Controllers/InventoryController.php';

header('Content-Type: application/json');

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
