<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/session.php';
require_once __DIR__ . '/../../../Middleware/AuthMiddleware.php';

header('Content-Type: application/json; charset=utf-8');

// Archiving is Administrator-only. Without this guard any authenticated
// account (including a Cashier) could archive products by POSTing here
// directly, even though the page that renders the button is admin-gated.
if ((int) ($_SESSION['role_id'] ?? 0) !== 1) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Administrator access is required to archive products.',
    ]);
    exit;
}

require_once __DIR__ . '/../../../Controllers/InventoryController.php';

try {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $controller = new InventoryController();
    $result = $controller->archiveProduct($productId);

    if (!($result['success'] ?? false)) {
        http_response_code(400);
    }

    echo json_encode([
        'status' => ($result['success'] ?? false) ? 'success' : 'error',
        'message' => $result['message'] ?? 'Unable to archive product.',
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}