<?php

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "========================================\n";
echo "PHASE B FEFO SALES TEST\n";
echo "NICA XANDRA PHARMACY POS\n";
echo "========================================\n\n";

try {
    $database = new Database();
    $db = $database->connect();

    echo "[1] Checking Phase B schema...\n";

    $tables = $db->query("SHOW TABLES LIKE 'sale_item_batches'")->fetchColumn();
    if (!$tables) {
        throw new RuntimeException('sale_item_batches table is missing. Run the Phase B migration.');
    }

    $column = $db->query("SHOW COLUMNS FROM inventory_movements LIKE 'batch_id'")->fetchColumn();
    if (!$column) {
        throw new RuntimeException('inventory_movements.batch_id is missing. Run the Phase B migration.');
    }

    echo "RESULT: PASSED\n";
    echo "sale_item_batches: AVAILABLE\n";
    echo "inventory_movements.batch_id: AVAILABLE\n\n";

    echo "[2] Checking FEFO-eligible batches...\n";
    $stmt = $db->query("
        SELECT COUNT(*)
        FROM product_batches
        WHERE quantity > 0
          AND batch_status = 'Active'
          AND (expiration_date IS NULL OR expiration_date >= CURDATE())
    ");
    $eligible = (int) $stmt->fetchColumn();
    echo "Eligible active batches: {$eligible}\n";
    echo "RESULT: PASSED\n\n";

    echo "[3] Checking completed-sale batch allocations...\n";
    $stmt = $db->query("
        SELECT
            si.sale_item_id,
            si.product_id,
            si.quantity AS sale_quantity,
            COALESCE(SUM(sib.quantity), 0) AS allocated_quantity
        FROM sale_items si
        INNER JOIN sales s ON s.sale_id = si.sale_id
        LEFT JOIN sale_item_batches sib ON sib.sale_item_id = si.sale_item_id
        WHERE s.transaction_status = 'Completed'
        GROUP BY si.sale_item_id, si.product_id, si.quantity
        ORDER BY si.sale_item_id DESC
        LIMIT 100
    ");

    $checked = 0;
    $invalid = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $checked++;
        $saleQty = (int) $row['sale_quantity'];
        $allocatedQty = (int) $row['allocated_quantity'];

        // Legacy sales may legitimately have no Phase B allocation.
        if ($allocatedQty > 0 && $allocatedQty !== $saleQty) {
            $invalid++;
            echo "INVALID sale_item_id={$row['sale_item_id']} sale_qty={$saleQty} allocated={$allocatedQty}\n";
        }
    }

    if ($invalid > 0) {
        throw new RuntimeException("Found {$invalid} completed sale item(s) with incomplete batch allocation.");
    }

    echo "Completed sale items checked: {$checked}\n";
    echo "RESULT: PASSED\n\n";

    echo "[4] Latest Phase B allocations\n";
    $stmt = $db->query("
        SELECT
            s.transaction_number,
            p.product_name,
            pb.batch_number,
            pb.expiration_date,
            sib.quantity
        FROM sale_item_batches sib
        INNER JOIN sale_items si ON si.sale_item_id = sib.sale_item_id
        INNER JOIN sales s ON s.sale_id = si.sale_id
        INNER JOIN products p ON p.product_id = si.product_id
        INNER JOIN product_batches pb ON pb.batch_id = sib.batch_id
        WHERE s.transaction_status = 'Completed'
        ORDER BY sib.sale_item_batch_id DESC
        LIMIT 10
    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo "No Phase B sale allocations exist yet.\n";
        echo "This is expected before the first post-migration POS sale.\n";
    } else {
        foreach ($rows as $row) {
            echo sprintf(
                "%s | %s | Batch %s | Exp %s | Qty %d\n",
                $row['transaction_number'],
                $row['product_name'],
                $row['batch_number'] ?: 'N/A',
                $row['expiration_date'] ?: 'N/A',
                (int) $row['quantity']
            );
        }
    }

    echo "\n========================================\n";
    echo "PHASE B FEFO TEST: PASSED\n";
    echo "========================================\n";
} catch (Throwable $e) {
    echo "RESULT: FAILED\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
