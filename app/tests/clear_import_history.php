<?php
/**
 * CLI helper: clear all DATA_MANAGEMENT import history log entries.
 * Usage: php app/tests/clear_import_history.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__, 2) . '/config/database.php';

$database = new Database();
$conn = $database->connect();

$stmt = $conn->prepare("DELETE FROM audit_logs WHERE module_name = 'DATA_MANAGEMENT' AND action_type = 'IMPORT'");
$stmt->execute();
$deleted = $stmt->rowCount();

echo "Cleared {$deleted} import history log entries.\n";
