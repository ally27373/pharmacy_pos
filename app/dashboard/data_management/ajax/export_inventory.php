<?php

declare(strict_types=1);

require_once '../../../../config/session.php';
require_once '../../../../app/Middleware/AuthMiddleware.php';
require_once '../../../../app/Controllers/DataManagementController.php';
require_once '../../../../config/database.php';

AuthMiddleware::dataManagement();

try {
    $controller = new DataManagementController();
    $rows = $controller->exportInventory();

    // Record the export before streaming the response.
    $database = new Database();
    $conn = $database->connect();
    $audit = $conn->prepare("\n        INSERT INTO audit_logs\n            (user_id, action_type, module_name, record_id, description, ip_address, user_agent)\n        VALUES\n            (:user_id, 'EXPORT', 'DATA_MANAGEMENT', NULL, :description, :ip, :user_agent)\n    ");
    $audit->execute([
        ':user_id' => (int) $_SESSION['user_id'],
        ':description' => 'Exported inventory dataset as CSV (' . count($rows) . ' records).',
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);

    $filename = 'inventory_export_' . date('Y-m-d_H-i-s') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
        throw new RuntimeException('Unable to open CSV output stream.');
    }

    // UTF-8 BOM helps Excel recognize the file correctly.
    fwrite($output, "\xEF\xBB\xBF");

    if (!empty($rows)) {
        fputcsv($output, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
    } else {
        fputcsv($output, ['message']);
        fputcsv($output, ['No inventory records found.']);
    }

    fclose($output);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'status' => 'error',
        'message' => 'Inventory export failed. Please check the server error log.'
    ]);
    error_log('Inventory export failed: ' . $e->getMessage());
    exit;
}
