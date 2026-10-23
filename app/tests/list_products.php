<?php
/**
 * CLI helper: list catalog products (read-only).
 * Usage: php app/tests/list_products.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__, 2) . '/config/database.php';

$database = new Database();
$conn = $database->connect();

$stmt = $conn->query("
    SELECT product_id, product_name, selling_price, product_status, is_archived
    FROM products
    ORDER BY product_name ASC
");

echo "Catalog products (the import name-matches against this list, case-insensitive):\n";
echo str_repeat('-', 78) . "\n";
printf("%-6s %-28s %10s  %-12s %s\n", 'ID', 'NAME', 'PRICE', 'STATUS', 'ARCHIVED');
echo str_repeat('-', 78) . "\n";
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
    printf(
        "%-6s %-28s %10s  %-12s %s\n",
        $p['product_id'],
        $p['product_name'],
        number_format((float) $p['selling_price'], 2),
        $p['product_status'],
        ((int) $p['is_archived']) === 1 ? 'YES' : 'no'
    );
}
