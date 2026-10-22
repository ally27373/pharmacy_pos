<?php
/**
 * CLI-only sales CSV importer.
 *
 * Usage (from project root):
 *   php app/tests/cli_import_sales.php <csv_path>            # dry-run preview
 *   php app/tests/cli_import_sales.php <csv_path> --confirm  # execute import
 *
 * Refuses to run under a web SAPI so it cannot be triggered anonymously.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

if ($argc < 2 || $argc > 3) {
    fwrite(STDERR, "Usage: php app/tests/cli_import_sales.php <csv_path> [--confirm]\n");
    exit(1);
}

$csvPath = $argv[1];
$confirm = isset($argv[2]) && $argv[2] === '--confirm';

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__) . '/Models/DataManagement.php';

// The import model reads cashier_id from the session; provide an admin identity
// for the CLI so the FK constraint on sales.cashier_id is satisfied.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['user_id'] = 1;

try {
    $handle = fopen($csvPath, 'r');
    if ($handle === false) {
        throw new RuntimeException('Unable to open file: ' . $csvPath);
    }

    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        throw new RuntimeException('Empty CSV file.');
    }
    $header = array_map(static fn($h) => strtolower(trim((string) $h)), $header);

    $required = ['date', 'product_name', 'quantity', 'unit_price'];
    foreach ($required as $r) {
        if (!in_array($r, $header, true)) {
            fclose($handle);
            throw new RuntimeException('Missing required column: ' . $r);
        }
    }
    $colIndex = [];
    foreach ($required as $r) {
        $colIndex[$r] = (int) array_search($r, $header, true);
    }

    $rows = [];
    while (($line = fgetcsv($handle)) !== false) {
        if ($line === [null] || (count($line) === 1 && trim((string) $line[0]) === '')) {
            continue;
        }
        $rows[] = [
            'date' => trim((string) ($line[$colIndex['date']] ?? '')),
            'product_name' => trim((string) ($line[$colIndex['product_name']] ?? '')),
            'quantity' => trim((string) ($line[$colIndex['quantity']] ?? '')),
            'unit_price' => trim((string) ($line[$colIndex['unit_price']] ?? '')),
        ];
    }
    fclose($handle);

    if (empty($rows)) {
        throw new RuntimeException('No data rows found in CSV.');
    }

    $model = new DataManagement();
    $preview = $model->previewSalesImport($rows);

    echo "PREVIEW\n";
    echo "  Total rows        : {$preview['total_rows']}\n";
    echo "  Valid rows        : {$preview['valid_count']}\n";
    echo "  Invalid rows      : {$preview['invalid_count']}\n";
    echo "  Duplicate rows    : {$preview['duplicate_count']}\n";
    echo "  Matched products  : {$preview['matched_products']}\n";
    echo "  Unmatched products: {$preview['unmatched_products']}\n";
    if ($preview['unmatched_products'] > 0) {
        echo "  Unmatched names   : " . implode(', ', $preview['unmatched_product_names']) . "\n";
    }
    echo "  Distinct dates    : {$preview['date_count']}\n";

    if ($preview['valid_count'] === 0) {
        fwrite(STDERR, "No valid rows to import. Aborting.\n");
        exit(1);
    }

    if (!$confirm) {
        echo "\nDRY RUN only. Re-run with --confirm to execute the import.\n";
        exit(0);
    }

    $result = $model->executeSalesImport($preview['valid_rows']);

    // Audit log (best effort; do not fail the import if audit insert fails).
    try {
        $database = new Database();
        $conn = $database->connect();
        $audit = $conn->prepare("
            INSERT INTO audit_logs
                (user_id, action_type, module_name, record_id, description, ip_address, user_agent)
            VALUES
                (:user_id, 'IMPORT', 'DATA_MANAGEMENT', NULL, :description, :ip, :user_agent)
        ");
        $audit->execute([
            ':user_id' => 1,
            ':description' => 'CLI import of historical sales data: ' . $result['imported_sales'] . ' sales, ' . $result['imported_items'] . ' line items.',
            ':ip' => '127.0.0.1',
            ':user_agent' => 'cli_import_sales.php',
        ]);
    } catch (Throwable $auditError) {
        fwrite(STDERR, "Audit log warning: " . $auditError->getMessage() . "\n");
    }

    echo "\nIMPORT COMPLETE\n";
    echo "  Imported sales    : {$result['imported_sales']}\n";
    echo "  Imported items    : {$result['imported_items']}\n";
    echo "  Date range        : " . $result['dates'][0] . " -> " . end($result['dates']) . "\n";
    exit(0);

} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
