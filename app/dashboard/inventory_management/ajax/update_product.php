<?php

require_once __DIR__ . '/../../../Controllers/InventoryController.php';

header('Content-Type: application/json');

try {
    $controller = new InventoryController();
    $result = $controller->updateProduct($_POST);

    if (!($result['success'] ?? false)) {
        http_response_code(400);
    }

    echo json_encode([
        'status' => ($result['success'] ?? false) ? 'success' : 'error',
        'message' => $result['message'] ?? 'Unable to update product.',
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
