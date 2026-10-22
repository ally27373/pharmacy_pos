<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

class DataManagement
{
    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? (new Database())->connect();
    }

    /**
     * Get the current product/inventory dataset for export.
     */
    public function getInventoryForExport(): array
    {
        $sql = "
            SELECT
                p.product_id,
                p.barcode,
                p.product_name,
                p.generic_name,
                p.brand_name,
                c.category_name,
                pt.type_name AS product_type,
                s.supplier_name,
                p.dosage,
                p.strength,
                p.unit,
                p.unit_cost,
                p.selling_price,
                p.quantity,
                p.reorder_level,
                p.manufacturing_date,
                p.expiration_date,
                p.batch_number,
                p.product_status,
                p.description,
                p.created_at,
                p.updated_at
            FROM products p

            LEFT JOIN categories c
                ON c.category_id = p.category_id

            LEFT JOIN product_types pt
                ON pt.type_id = p.type_id

            LEFT JOIN suppliers s
                ON s.supplier_id = p.supplier_id

            ORDER BY
                p.product_name ASC,
                p.product_id ASC
        ";

        return $this->conn
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get completed sales transaction lines for export.
     *
     * Cancelled/void transactions are excluded.
     */
    public function getSalesForExport(): array
    {
        $sql = "
            SELECT
                s.sale_id,
                s.transaction_number,

                DATE(s.created_at) AS sale_date,
                TIME(s.created_at) AS sale_time,

                p.product_id,
                p.barcode,
                p.product_name,

                si.quantity,
                si.unit_price,
                si.discount_amount,
                si.subtotal,

                s.subtotal AS transaction_subtotal,
                s.discount_amount AS transaction_discount,
                s.total_amount,

                s.payment_status,
                s.transaction_status,

                pay.payment_methods,

                u.user_id AS cashier_id,
                u.username AS cashier_username,
                u.full_name AS cashier_name,

                s.remarks

            FROM sales s

            INNER JOIN sale_items si
                ON si.sale_id = s.sale_id

            INNER JOIN products p
                ON p.product_id = si.product_id

            LEFT JOIN users u
                ON u.user_id = s.cashier_id

            LEFT JOIN (
                SELECT
                    sale_id,

                    GROUP_CONCAT(
                        DISTINCT payment_method
                        ORDER BY payment_id
                        SEPARATOR ', '
                    ) AS payment_methods

                FROM payments

                GROUP BY sale_id
            ) pay
                ON pay.sale_id = s.sale_id

            WHERE s.transaction_status = 'Completed'

            ORDER BY
                s.created_at ASC,
                s.sale_id ASC,
                si.sale_item_id ASC
        ";

        return $this->conn
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count current inventory records.
     */
    public function countInventory(): int
    {
        return (int) $this->conn
            ->query("
                SELECT COUNT(*)
                FROM products
            ")
            ->fetchColumn();
    }

    /**
     * Count completed sales line items.
     */
    public function countSalesRows(): int
    {
        return (int) $this->conn
            ->query("
                SELECT COUNT(*)
                FROM sales s

                INNER JOIN sale_items si
                    ON si.sale_id = s.sale_id

                WHERE s.transaction_status = 'Completed'
            ")
            ->fetchColumn();
    }

    /**
     * Preview a sales import without writing anything.
     *
     * Input rows must already be parsed into associative arrays with keys:
     *   date, product_name, quantity, unit_price
     *
     * Returns a structured preview: valid/invalid rows, matched products,
     * duplicate detection, and a per-date summary. Nothing is persisted.
     */
    public function previewSalesImport(array $rows): array
    {
        $validRows = [];
        $invalidRows = [];
        $seen = [];
        $duplicateCount = 0;
        $matchedProducts = [];
        $unmatchedProducts = [];

        // Product lookup (case-insensitive name -> product_id).
        $productMap = [];
        $stmt = $this->conn->query('SELECT product_id, product_name FROM products');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $productMap[strtolower(trim((string) $p['product_name']))] = (int) $p['product_id'];
        }

        foreach ($rows as $index => $row) {
            $date = trim((string) ($row['date'] ?? ''));
            $productName = trim((string) ($row['product_name'] ?? ''));
            $quantity = (float) ($row['quantity'] ?? 0);
            $unitPrice = (float) ($row['unit_price'] ?? 0);

            $errors = [];

            if ($date === '' || strtotime($date) === false) {
                $errors[] = 'Invalid or missing date.';
            }
            if ($productName === '') {
                $errors[] = 'Missing product name.';
            }
            if ($quantity <= 0) {
                $errors[] = 'Quantity must be greater than 0.';
            }
            if ($unitPrice < 0) {
                $errors[] = 'Unit price cannot be negative.';
            }

            $productId = null;
            if ($productName !== '') {
                $key = strtolower($productName);
                if (isset($productMap[$key])) {
                    $productId = $productMap[$key];
                } else {
                    $errors[] = 'Product not found in inventory.';
                    $unmatchedProducts[$productName] = true;
                }
            }

            // Duplicate detection (same date + product + quantity).
            $dupKey = $date . '|' . strtolower($productName) . '|' . $quantity;
            if (isset($seen[$dupKey])) {
                $duplicateCount++;
                $errors[] = 'Possible duplicate row.';
            }
            $seen[$dupKey] = true;

            $entry = [
                'row_number' => $index + 2, // +2 = header offset + 1-based
                'date' => $date,
                'product_name' => $productName,
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => round($quantity * $unitPrice, 2),
                'errors' => $errors,
            ];

            if (empty($errors)) {
                $validRows[] = $entry;
                if ($productId !== null) {
                    $matchedProducts[$productId] = true;
                }
            } else {
                $invalidRows[] = $entry;
            }
        }

        // Per-date summary.
        $dateSummary = [];
        foreach ($validRows as $vr) {
            $d = $vr['date'];
            if (!isset($dateSummary[$d])) {
                $dateSummary[$d] = ['date' => $d, 'items' => 0, 'total' => 0.0];
            }
            $dateSummary[$d]['items']++;
            $dateSummary[$d]['total'] += $vr['subtotal'];
        }
        foreach ($dateSummary as &$ds) {
            $ds['total'] = round($ds['total'], 2);
        }
        unset($ds);

        return [
            'total_rows' => count($rows),
            'valid_count' => count($validRows),
            'invalid_count' => count($invalidRows),
            'duplicate_count' => $duplicateCount,
            'matched_products' => count($matchedProducts),
            'unmatched_products' => count($unmatchedProducts),
            'unmatched_product_names' => array_keys($unmatchedProducts),
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'date_summary' => array_values($dateSummary),
            'date_count' => count($dateSummary),
        ];
    }

    /**
     * Execute a sales import. Assumes rows have already been previewed/validated.
     * Inserts one sale per distinct date, with multiple sale_items per sale.
     *
     * Historical import: does NOT touch product stock/batches (already consumed).
     * transaction_status is set to 'Completed'.
     *
     * @return array{imported_sales:int, imported_items:int}
     */
    public function executeSalesImport(array $rows): array
    {
        // Group valid rows by date.
        $byDate = [];
        foreach ($rows as $row) {
            $date = trim((string) ($row['date'] ?? ''));
            if ($date === '') continue;
            $byDate[$date][] = $row;
        }

        $importedSales = 0;
        $importedItems = 0;

        $this->conn->beginTransaction();

        try {
            foreach ($byDate as $date => $items) {
                // Compute transaction totals.
                $subtotal = 0.0;
                foreach ($items as $it) {
                    $subtotal += (float) $it['quantity'] * (float) $it['unit_price'];
                }
                $subtotal = round($subtotal, 2);
                $total = $subtotal; // no discount on imported historical data

                // Insert sale header.
                $saleStmt = $this->conn->prepare("
                    INSERT INTO sales
                        (transaction_number, cashier_id, subtotal, discount_amount, total_amount,
                         payment_status, transaction_status, remarks, created_at, updated_at)
                    VALUES
                        (:transaction_number, :cashier_id, :subtotal, 0, :total_amount,
                         'Paid', 'Completed', :remarks, :created_at, :updated_at)
                ");

                $transactionNumber = 'IMP-' . date('YmdHis', strtotime($date)) . '-' . substr(md5($date . microtime()), 0, 6);
                $saleStmt->execute([
                    ':transaction_number' => $transactionNumber,
                    ':cashier_id' => (int) ($_SESSION['user_id'] ?? 0) ?: 1,
                    ':subtotal' => $subtotal,
                    ':total_amount' => $total,
                    ':remarks' => 'Imported historical sales data',
                    ':created_at' => $date . ' 00:00:00',
                    ':updated_at' => $date . ' 00:00:00',
                ]);

                $saleId = (int) $this->conn->lastInsertId();

                // Insert sale items.
                $itemStmt = $this->conn->prepare("
                    INSERT INTO sale_items
                        (sale_id, product_id, quantity, unit_price, discount_amount, subtotal)
                    VALUES
                        (:sale_id, :product_id, :quantity, :unit_price, 0, :subtotal)
                ");

                foreach ($items as $it) {
                    $q = (float) $it['quantity'];
                    $up = (float) $it['unit_price'];
                    $itemStmt->execute([
                        ':sale_id' => $saleId,
                        ':product_id' => (int) $it['product_id'],
                        ':quantity' => (int) $q,
                        ':unit_price' => $up,
                        ':subtotal' => round($q * $up, 2),
                    ]);
                    $importedItems++;
                }

                $importedSales++;
            }

            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }

        return [
            'imported_sales' => $importedSales,
            'imported_items' => $importedItems,
            'dates' => array_keys($byDate),
        ];
    }
}