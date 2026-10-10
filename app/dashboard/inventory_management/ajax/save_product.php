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
| Inventory Management is Administrator-only. Guarding the page that
| renders these forms is not enough: a form is not a control, so any
| signed-in account -- including a Cashier -- could POST straight here.
|
*/
AuthMiddleware::requireAdminApi();

/*
| Creating a product is a write, so it also has to prove the request came
| from a page in this app. An admin's cookie is attached by the browser to
| any cross-site form post, so the role check alone would let a third-party
| page create products as that admin.
*/
Csrf::requireValid();

require_once __DIR__ . '/../../../Controllers/InventoryController.php';

try {
    $controller = new InventoryController();

    $required = ['product_name', 'category_id', 'type_id', 'quantity', 'unit_cost', 'selling_price'];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || trim((string) $_POST[$field]) === '') {
            throw new InvalidArgumentException('Please complete all required product fields.');
        }
    }

    $result = $controller->saveProduct($_POST);

    if (!($result['success'] ?? false)) {
        http_response_code(400);
    }

    echo json_encode([
        'status' => ($result['success'] ?? false) ? 'success' : 'error',
        'message' => $result['message'] ?? 'Unable to save product.',
        'product_id' => $result['product_id'] ?? null,
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
