<?php
/**
 * TEMPORARY diagnostic — delete after use.
 * Hits every code path the archive page depends on and prints the real error.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__, 2);
chdir($root);

echo "PHP: " . PHP_VERSION . "\n";
echo "CWD: " . getcwd() . "\n\n";

echo "--- file presence ---\n";
foreach ([
    'config/session.php',
    'config/database.php',
    'includes/header.php',
    'includes/sidebar.php',
    'includes/navbar.php',
    'includes/footer.php',
    'app/Middleware/AuthMiddleware.php',
    'app/Controllers/InventoryController.php',
    'app/Models/Inventory.php',
    'app/dashboard/archive/index.php',
    'assets/js/archive.js',
] as $rel) {
    $abs = $root . '/' . $rel;
    printf("%-45s %s%s\n", $rel, file_exists($abs) ? 'OK' : 'MISSING', is_readable($abs) ? '' : ' (NOT READABLE)');
}

echo "\n--- requires ---\n";
foreach ([
    'config/database.php',
    'config/session.php',
    'app/Models/Inventory.php',
    'app/Controllers/InventoryController.php',
] as $rel) {
    try {
        require_once $root . '/' . $rel;
        echo "OK   $rel\n";
    } catch (Throwable $e) {
        echo "FAIL $rel -> " . get_class($e) . ': ' . $e->getMessage() . "\n";
    }
}

echo "\n--- db ---\n";
try {
    $db = new Database();
    $pdo = $db->connect();
    echo "connect: OK\n";

    $col = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='products'
                          AND COLUMN_NAME='is_archived'")->fetchColumn();
    echo "products.is_archived present: " . ((int) $col === 1 ? 'YES' : 'NO') . "\n";

    $c = new InventoryController();
    echo "\n-- getArchivedProducts() --\n";
    $rows = $c->getArchivedProducts();
    echo "returned rows: " . count($rows) . "\n";

    echo "\n-- getCategories() --\n";
    echo "returned rows: " . count($c->getCategories()) . "\n";

    echo "\n-- getTypes() --\n";
    echo "returned rows: " . count($c->getTypes()) . "\n";

    echo "\nDONE\n";
} catch (Throwable $e) {
    echo "DB FAIL -> " . get_class($e) . ': ' . $e->getMessage() . "\n";
}