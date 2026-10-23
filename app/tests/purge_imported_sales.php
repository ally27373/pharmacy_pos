<?php
/**
 * CLI helper: delete ONLY rows that were deposited by the historical sales
 * import. Imported sale headers use the transaction_number prefix "IMP-..."
 * (see DataManagement::executeSalesImport), so live POS transactions are
 * never touched.
 *
 * Read-only by default; pass --confirm to actually delete.
 *   php app/tests/purge_imported_sales.php            (shows counts only)
 *   php app/tests/purge_imported_sales.php --confirm  (deletes imported rows)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__, 2) . '/config/database.php';

$database = new Database();
$conn = $database->connect();

function importedStats(PDO $conn): array
{
    $sales = $conn->query("
        SELECT COUNT(*) AS n FROM sales WHERE transaction_number LIKE 'IMP-%'
    ")->fetch(PDO::FETCH_ASSOC);

    $items = $conn->query("
        SELECT COUNT(*) AS n, COALESCE(SUM(si.quantity), 0) AS units
        FROM sale_items si
        INNER JOIN sales s ON s.sale_id = si.sale_id
        WHERE s.transaction_number LIKE 'IMP-%'
    ")->fetch(PDO::FETCH_ASSOC);

    return [$sales['n'], $items['n'], (float) $items['units']];
}

[$salesCount, $itemsCount, $units] = importedStats($conn);

echo "Rows deposited by the historical sales import (IMP- prefix):\n";
echo "  sale headers : {$salesCount}\n";
echo "  line items   : {$itemsCount}\n";
echo "  units        : {$units}\n";

$confirm = ($argv[1] ?? '') === '--confirm';
if (!$confirm) {
    echo "\nNo rows were deleted. To delete these imported rows (live POS sales are\n";
    echo "never touched), run:\n";
    echo "  php app/tests/purge_imported_sales.php --confirm\n";
    exit(0);
}

$conn->beginTransaction();
try {
    $itemsDel = $conn->prepare("
        DELETE si FROM sale_items si
        INNER JOIN sales s ON s.sale_id = si.sale_id
        WHERE s.transaction_number LIKE 'IMP-%'
    ");
    $itemsDel->execute();
    $deletedItems = $itemsDel->rowCount();

    $headersDel = $conn->prepare("DELETE FROM sales WHERE transaction_number LIKE 'IMP-%'");
    $headersDel->execute();
    $deletedSales = $headersDel->rowCount();

    $conn->commit();
    echo "\nDeleted {$deletedItems} line items and {$deletedSales} imported sale headers.\n";

    [$salesLeft, $itemsLeft, $unitsLeft] = importedStats($conn);
    echo "Remaining imported rows : {$salesLeft} headers, {$itemsLeft} items, {$unitsLeft} units.\n";
} catch (Throwable $e) {
    $conn->rollBack();
    fwrite(STDERR, 'PURGE FAILED (rolled back): ' . $e->getMessage() . "\n");
    exit(1);
}
