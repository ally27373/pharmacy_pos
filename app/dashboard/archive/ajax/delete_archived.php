<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/session.php';
require_once __DIR__ . '/../../../Middleware/AuthMiddleware.php';

header('Content-Type: application/json; charset=utf-8');

// Permanent deletion is destructive and Administrator-only. Guard before
// the model runs so an unauthenticated POST cannot delete inventory.
if ((int) ($_SESSION['role_id'] ?? 0) !== 1) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Administrator access is required to delete archived products.',
    ]);
    exit;
}

require_once __DIR__ . '/../../../Controllers/InventoryController.php';

try {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $controller = new InventoryController();
    $result = $controller->deleteArchivedProduct($productId);

    if (!($result['success'] ?? false)) {
        http_response_code(400);
    }

    echo json_encode([
        'status' => ($result['success'] ?? false) ? 'success' : 'error',
        'message' => $result['message'] ?? 'Unable to delete archived product.',
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}