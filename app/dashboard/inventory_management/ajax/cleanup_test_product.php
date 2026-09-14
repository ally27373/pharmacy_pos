<?php

require_once __DIR__ . '/../../../Controllers/InventoryController.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Test-data cleanup is an Admin-only destructive operation.
    if ((int) ($_SESSION['role_id'] ?? 0) !== 1) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Only an administrator can clean up test data.',
        ]);
        exit;
    }

    $productId = (int) ($_POST['product_id'] ?? 0);
    $controller = new InventoryController();
    $result = $controller->cleanupTestProduct($productId);

    if (!($result['success'] ?? false)) {
        http_response_code(400);
    }

    echo json_encode([
        'status' => ($result['success'] ?? false) ? 'success' : 'error',
        'message' => $result['message'] ?? 'Unable to clean up test product.',
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
