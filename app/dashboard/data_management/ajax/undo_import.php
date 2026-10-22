<?php

declare(strict_types=1);

require_once '../../../../config/session.php';
require_once '../../../../app/Middleware/AuthMiddleware.php';
require_once '../../../../app/Helpers/Csrf.php';
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

    $database = new Database();
    $conn = $database->connect();

    // Count imported sales + their line items BEFORE deleting.
    $countStmt = $conn->prepare("
        SELECT COUNT(*) AS sales_count
        FROM sales
        WHERE remarks = 'Imported historical sales data'
    ");
    $countStmt->execute();
    $importedSales = (int) $countStmt->fetchColumn();

    if ($importedSales === 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Nothing to undo. No imported sales found.']);
        exit;
    }

    $itemsStmt = $conn->prepare("
        SELECT COUNT(*) AS item_count
        FROM sale_items si
        INNER JOIN sales s ON s.sale_id = si.sale_id
        WHERE s.remarks = 'Imported historical sales data'
    ");
    $itemsStmt->execute();
    $importedItems = (int) $itemsStmt->fetchColumn();

    // Delete in a transaction. Remove dependent rows explicitly (in FK-safe
    // order) so we do not rely on cascade behaviour across every table.
    $conn->beginTransaction();
    try {
        // 1. sale_item_batches (depends on sale_items)
        $conn->exec("
            DELETE sib FROM sale_item_batches sib
            INNER JOIN sale_items si ON si.sale_item_id = sib.sale_item_id
            INNER JOIN sales s ON s.sale_id = si.sale_id
            WHERE s.remarks = 'Imported historical sales data'
        ");

        // 2. sale_items
        $conn->exec("
            DELETE si FROM sale_items si
            INNER JOIN sales s ON s.sale_id = si.sale_id
            WHERE s.remarks = 'Imported historical sales data'
        ");

        // 3. payments
        $conn->exec("
            DELETE p FROM payments p
            INNER JOIN sales s ON s.sale_id = p.sale_id
            WHERE s.remarks = 'Imported historical sales data'
        ");

        // 4. inventory_movements (nullable sale_id FK; delete to keep things clean)
        $conn->exec("
            DELETE inv FROM inventory_movements inv
            INNER JOIN sales s ON s.sale_id = inv.sale_id
            WHERE s.remarks = 'Imported historical sales data'
        ");

        // 5. sales
        $deleteStmt = $conn->prepare("
            DELETE FROM sales
            WHERE remarks = 'Imported historical sales data'
        ");
        $deleteStmt->execute();
        $deletedSales = $deleteStmt->rowCount();

        // Audit log.
        $audit = $conn->prepare("
            INSERT INTO audit_logs
                (user_id, action_type, module_name, record_id, description, ip_address, user_agent)
            VALUES
                (:user_id, 'UNDO_IMPORT', 'DATA_MANAGEMENT', NULL, :description, :ip, :user_agent)
        ");
        $audit->execute([
            ':user_id' => (int) ($_SESSION['user_id'] ?? 1),
            ':description' => 'Undo imported historical sales data: ' . $deletedSales . ' sales, ' . $importedItems . ' line items removed.',
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollBack();
        throw $e;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Undo complete: ' . $deletedSales . ' sales and ' . $importedItems . ' line items removed. Database restored to pre-import state.',
        'deleted_sales' => $deletedSales,
        'deleted_items' => $importedItems,
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Undo failed: ' . $e->getMessage(),
    ]);
    error_log('Undo import failed: ' . $e->getMessage());
    exit;
}
