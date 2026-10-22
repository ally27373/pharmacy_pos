<?php

declare(strict_types=1);

require_once '../../../../config/session.php';
require_once '../../../../app/Middleware/AuthMiddleware.php';
require_once '../../../../app/Helpers/Csrf.php';
require_once '../../../../app/Controllers/DataManagementController.php';
require_once '../../../../app/Models/DataManagement.php';
require_once '../../../../config/database.php';

AuthMiddleware::requireAdminApi();

Csrf::requireValid(true);

header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
        exit;
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'No valid CSV file uploaded.']);
        exit;
    }

    $file = $_FILES['file']['tmp_name'];
    $confirm = isset($_POST['confirm']) && $_POST['confirm'] === '1';

    // Parse CSV.
    $handle = fopen($file, 'r');
    if ($handle === false) {
        throw new RuntimeException('Unable to read uploaded file.');
    }

    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        throw new RuntimeException('Empty CSV file.');
    }

    // Normalize header names.
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
        $colIndex[$r] = array_search($r, $header, true);
    }

    $rows = [];
    while (($line = fgetcsv($handle)) !== false) {
        // Skip blank lines.
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

    if (!$confirm) {
        // Preview only.
        $preview = $model->previewSalesImport($rows);
        echo json_encode([
            'status' => 'preview',
            'message' => 'Preview generated. Review and confirm to import.',
            'preview' => $preview,
        ]);
        exit;
    }

    // Execute: only import valid rows.
    $preview = $model->previewSalesImport($rows);
    if ($preview['valid_count'] === 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'No valid rows to import.']);
        exit;
    }

    // Re-extract valid rows with product_id matched.
    $validRows = $preview['valid_rows'];

    // Audit before execution.
    $result = $model->executeSalesImport($validRows);

    // Audit log.
    $database = new Database();
    $conn = $database->connect();
    $audit = $conn->prepare("
        INSERT INTO audit_logs
            (user_id, action_type, module_name, record_id, description, ip_address, user_agent)
        VALUES
            (:user_id, 'IMPORT', 'DATA_MANAGEMENT', NULL, :description, :ip, :user_agent)
    ");
    $audit->execute([
        ':user_id' => (int) $_SESSION['user_id'],
        ':description' => 'Imported historical sales data: ' . $result['imported_sales'] . ' sales, ' . $result['imported_items'] . ' line items.',
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);

    // Trigger forecast refresh automatically after import.
    try {
        require_once '../../../../app/Services/ForecastRefreshService.php';
        use App\Services\ForecastRefreshService;
        $service = new ForecastRefreshService(new Database()->connect());
        $service->refresh();
    } catch (Throwable $refreshErr) {
        // Forecast refresh failure must not block the import success.
        error_log('Auto-refresh after import failed: ' . $refreshErr->getMessage());
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Import completed: ' . $result['imported_sales'] . ' sales, ' . $result['imported_items'] . ' line items.',
        'result' => $result,
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Import failed. Please check the server error log.',
    ]);
    error_log('Sales import failed: ' . $e->getMessage());
    exit;
}