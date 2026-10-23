<?php
/**
 * CLI audit: split the demand series into imported vs live POS sales and
 * report exactly how many units the historical import deposited into the
 * database, per month.
 *
 * Imported rows are identified by the transaction_number prefix used by
 * DataManagement::executeSalesImport() ("IMP-YYYYMMDD..."). Everything
 * else was a live POS transaction.
 *
 * Usage: php app/tests/audit_imported_sales.php
 * Read-only - performs no writes.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__, 2) . '/config/database.php';

$database = new Database();
$conn = $database->connect();

$imported = "(s.transaction_number LIKE 'IMP-%')";

// ---- Global split ------------------------------------------------------ //
$row = $conn->query("
    SELECT
        {$imported} AS is_imported,
        COUNT(DISTINCT s.sale_id) AS sales,
        COUNT(si.sale_item_id) AS line_items,
        SUM(si.quantity) AS units
    FROM sales s
    INNER JOIN sale_items si ON si.sale_id = s.sale_id
    WHERE s.transaction_status = 'Completed'
    GROUP BY {$imported}
    ORDER BY {$imported} DESC
")->fetchAll(PDO::FETCH_ASSOC);

$summary = [];
foreach ($row as $r) {
    $label = ((int) $r['is_imported']) === 1 ? 'IMPORTED (IMP- prefix)' : 'LIVE POS transactions';
    $summary[] = $r;
    printf(
        "%-28s sales: %6d   line items: %6d   units: %8.0f\n",
        $label,
        (int) $r['sales'],
        (int) $r['line_items'],
        (float) $r['units']
    );
}

// ---- Monthly units, imported vs live ----------------------------------- //
$monthly = $conn->query("
    SELECT
        DATE_FORMAT(s.created_at, '%Y-%m') AS mon,
        SUM(CASE WHEN {$imported} THEN si.quantity ELSE 0 END) AS imported_units,
        SUM(CASE WHEN {$imported} THEN 0 ELSE si.quantity END) AS live_units
    FROM sales s
    INNER JOIN sale_items si ON si.sale_id = s.sale_id
    WHERE s.transaction_status = 'Completed'
    GROUP BY mon
    ORDER BY mon ASC
")->fetchAll(PDO::FETCH_ASSOC);

echo "\nMonth  |  Import units |  Live units |  Total\n";
echo str_repeat('-', 50) . "\n";
$totImport = 0;
$totLive = 0;
foreach ($monthly as $m) {
    $i = (float) $m['imported_units'];
    $l = (float) $m['live_units'];
    $totImport += $i;
    $totLive += $l;
    printf("%-6s  | %13.0f | %11.0f | %6.0f\n", $m['mon'], $i, $l, $i + $l);
}
echo str_repeat('-', 50) . "\n";
printf("%-6s  | %13.0f | %11.0f | %6.0f\n", 'TOTAL', $totImport, $totLive, $totImport + $totLive);

// ---- Referential note -------------------------------------------------- //
$liveRows = $conn->query("
    SELECT DATE(s.created_at) AS d, SUM(si.quantity) AS units
    FROM sales s
    INNER JOIN sale_items si ON si.sale_id = s.sale_id
    WHERE s.transaction_status = 'Completed'
      AND s.transaction_number NOT LIKE 'IMP-%'
    GROUP BY DATE(s.created_at)
    ORDER BY d ASC
")->fetchAll(PDO::FETCH_ASSOC);

echo "\nLive POS dates (non-import):\n";
foreach ($liveRows as $r) {
    printf("  %s  units: %s\n", $r['d'], (float) $r['units']);
}

echo "\nNote: imported units are whatever the import deposited. Compare the\n";
echo "IMPORTED column per month against the source CSV (Desktop total was\n";
echo "40,092 units for Jan-Sep 2026 in simulated_pharmacy_sales_jan_sep_2026.csv).\n";
