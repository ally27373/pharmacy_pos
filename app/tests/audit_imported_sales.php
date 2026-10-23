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

// ---- Trailing-30 window (Dashboard::getRecentWindowTotals verification) -- //
// The Analytics forecasting KPI cards use a trailing-30 day window anchored
// to the newest completed sale date. Recompute it here so the card values
// can be verified against the database directly.
$wEndRow = $conn->query("
    SELECT DATE(MAX(s.created_at)) AS window_end
    FROM sales s
    WHERE s.transaction_status = 'Completed'
")->fetch(PDO::FETCH_ASSOC);

$wEnd = is_array($wEndRow) ? ($wEndRow['window_end'] ?? null) : null;

if ($wEnd === null) {
    echo "\nNo completed sales found; trailing-30 window unavailable.\n";
    exit(0);
}

$wStart = date('Y-m-d', strtotime($wEnd) - 29 * 86400);

$wTotals = $conn->prepare("
    SELECT
        COUNT(DISTINCT s.sale_id) AS transactions,
        COALESCE(SUM(si.quantity), 0) AS units,
        COALESCE(SUM(
            si.subtotal - COALESCE(
                s.discount_amount * si.subtotal
                    / NULLIF(sl.line_subtotal, 0),
                0
            )
        ), 0) AS peso,
        SUM(CASE WHEN {$imported} THEN si.quantity ELSE 0 END) AS imported_units,
        SUM(CASE WHEN {$imported} THEN 0 ELSE si.quantity END) AS live_units,
        SUM(CASE WHEN {$imported} THEN
                si.subtotal - COALESCE(
                    s.discount_amount * si.subtotal / NULLIF(sl.line_subtotal, 0),
                    0
                )
            ELSE 0 END) AS imported_peso,
        SUM(CASE WHEN {$imported} THEN 0 ELSE
                si.subtotal - COALESCE(
                    s.discount_amount * si.subtotal / NULLIF(sl.line_subtotal, 0),
                    0
                )
        END) AS live_peso
    FROM sales s
    INNER JOIN sale_items si ON si.sale_id = s.sale_id
    INNER JOIN (
        SELECT sale_id, SUM(subtotal) AS line_subtotal
        FROM sale_items
        GROUP BY sale_id
    ) sl ON sl.sale_id = si.sale_id
    WHERE s.transaction_status = 'Completed'
      AND DATE(s.created_at) BETWEEN :ws AND :we
");
$wTotals->execute([':ws' => $wStart, ':we' => $wEnd]);
$w = $wTotals->fetch(PDO::FETCH_ASSOC) ?: [];

printf("\nTRAILING-30 WINDOW (%s -> %s)\n", $wStart, $wEnd);
printf(
    "  KPI 'Units sold (last 30)'   : %s  (imported %.0f + live %.0f)\n",
    number_format((float) $w['units']),
    (float) $w['imported_units'],
    (float) $w['live_units']
);
printf(
    "  KPI 'Sales (last 30)'        : P%s  (imported P%.2f + live P%.2f)\n",
    number_format((float) $w['peso'], 2),
    (float) $w['imported_peso'],
    (float) $w['live_peso']
);
printf("  Transactions in window       : %s\n", number_format((int) $w['transactions']));
echo "  Peso = sale-item subtotals net of sale-level discount (Revenue by\n";
echo "  Category / getMonthlySales convention). These two KPI values must match\n";
echo "  the Analytics forecasting cards exactly.\n";

$wProducts = $conn->prepare("
    SELECT
        TRIM(p.product_name) AS product_name,
        SUM(si.quantity) AS units,
        ROUND(COALESCE(SUM(
            si.subtotal - COALESCE(
                s.discount_amount * si.subtotal / NULLIF(sl.line_subtotal, 0),
                0
            )
        ), 0), 2) AS peso
    FROM sales s
    INNER JOIN sale_items si ON si.sale_id = s.sale_id
    INNER JOIN products p ON p.product_id = si.product_id
    INNER JOIN (
        SELECT sale_id, SUM(subtotal) AS line_subtotal
        FROM sale_items
        GROUP BY sale_id
    ) sl ON sl.sale_id = si.sale_id
    WHERE s.transaction_status = 'Completed'
      AND DATE(s.created_at) BETWEEN :ws AND :we
    GROUP BY TRIM(p.product_name)
    ORDER BY units DESC
    LIMIT 7
");
$wProducts->execute([':ws' => $wStart, ':we' => $wEnd]);

echo "\nTop products in window (bars on the 'Top Demand Medicines' panel):\n";
foreach ($wProducts->fetchAll(PDO::FETCH_ASSOC) as $wp) {
    printf(
        "  %-30s units: %8.0f   peso: %10.2f\n",
        $wp['product_name'],
        (float) $wp['units'],
        (float) $wp['peso']
    );
}

