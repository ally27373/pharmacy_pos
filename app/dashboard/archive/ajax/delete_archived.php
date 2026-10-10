<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/session.php';
require_once __DIR__ . '/../../../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../../Helpers/Csrf.php';

header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| Access control
|--------------------------------------------------------------------------
|
| Permanent deletion is destructive and Administrator-only. This replaces
| a hand-rolled $_SESSION['role_id'] check, which never confirmed the
| account still existed or was still Active before allowing a delete.
|
*/
AuthMiddleware::requireAdminApi();

/*
| Permanent deletion is irreversible, so it carries the CSRF check as well as
| the role check: an admin's cookie rides along on any cross-site post.
*/
Csrf::requireValid();

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