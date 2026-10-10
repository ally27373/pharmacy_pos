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
| Archive is an Administrator-only module. This replaces a hand-rolled
| $_SESSION['role_id'] check: that one never confirmed the account still
| existed or was still Active, so a deactivated admin kept access until
| the session expired. requireAdminApi() runs the same validation chain
| as the page guard and answers with JSON rather than an HTML page.
|
*/
AuthMiddleware::requireAdminApi();

/*
| Unarchiving writes, so it also has to prove the request came from a page
| in this app. An admin's cookie is attached by the browser to any
| cross-site form post, so the role check alone would let a third-party
| page restore archived products as that admin.
*/
Csrf::requireValid();

require_once __DIR__ . '/../../../Controllers/InventoryController.php';

try {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $controller = new InventoryController();
    $result = $controller->unarchiveProduct($productId);

    if (!($result['success'] ?? false)) {
        http_response_code(400);
    }

    echo json_encode([
        'status' => ($result['success'] ?? false) ? 'success' : 'error',
        'message' => $result['message'] ?? 'Unable to unarchive product.',
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}