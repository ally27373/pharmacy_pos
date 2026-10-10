<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/session.php';
require_once __DIR__ . '/../../../Middleware/AuthMiddleware.php';

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

require_once __DIR__ . '/../../../../config/database.php';

$database = new Database();
$conn = $database->connect();

$result = [];

/* Categories */
$stmt = $conn->query("
    SELECT
        category_id,
        category_name
    FROM categories
    ORDER BY category_name ASC
");

$result['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Product Types */
$stmt = $conn->query("
    SELECT
        type_id,
        type_name
    FROM product_types
    ORDER BY type_name ASC
");

$result['types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Suppliers */
$stmt = $conn->query("
    SELECT
        supplier_id,
        supplier_name
    FROM suppliers
    ORDER BY supplier_name ASC
");

$result['suppliers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($result);