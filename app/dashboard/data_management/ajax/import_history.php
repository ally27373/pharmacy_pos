<?php

declare(strict_types=1);

require_once '../../../../config/session.php';
require_once '../../../../app/Middleware/AuthMiddleware.php';
require_once '../../../../config/database.php';

AuthMiddleware::dataManagement();

header('Content-Type: application/json; charset=UTF-8');

try {
    $database = new Database();
    $conn = $database->connect();

    $stmt = $conn->prepare("
        SELECT
            audit_id,
            user_id,
            action_type,
            description,
            ip_address,
            created_at
        FROM audit_logs
        WHERE module_name = 'DATA_MANAGEMENT'
          AND action_type = 'IMPORT'
        ORDER BY created_at DESC
        LIMIT 25
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'entries' => $rows,
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to load import history.',
    ]);
    error_log('Import history failed: ' . $e->getMessage());
    exit;
}
