<?php

require_once __DIR__ . '/../../../Controllers/InventoryController.php';

header('Content-Type: application/json');

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
