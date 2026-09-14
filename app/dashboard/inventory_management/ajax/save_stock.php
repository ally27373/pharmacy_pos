<?php

require_once __DIR__ . '/../../../Controllers/InventoryController.php';

header('Content-Type: application/json');

try {
    $controller = new InventoryController();
    $result = $controller->adjustStock($_POST);

    if (!($result['success'] ?? false)) {
        http_response_code(400);
    }

    echo json_encode([
        'status' => ($result['success'] ?? false) ? 'success' : 'error',
        'message' => $result['message'] ?? 'Unable to save stock adjustment.',
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
