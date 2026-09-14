<?php

require_once '../../../../config/database.php';

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

header('Content-Type: application/json');

echo json_encode($result);