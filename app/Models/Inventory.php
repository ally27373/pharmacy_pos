<?php

require_once __DIR__ . '/../../config/database.php';

class Inventory
{
    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? (new Database())->connect();
    }

    public function getProducts(
        int $page = 1,
        int $limit = 20,
        string $search = '',
        string $category = '',
        string $type = ''
    ): array {
        $page = max(1, $page);
        $limit = min(max(1, $limit), 100);
        $search = trim($search);
        $category = trim($category);
        $type = trim($type);

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = "(
                p.product_name LIKE :search_product
                OR p.barcode LIKE :search_barcode
                OR COALESCE(p.generic_name, '') LIKE :search_generic
            )";
            $searchValue = '%' . $search . '%';
            $params[':search_product'] = $searchValue;
            $params[':search_barcode'] = $searchValue;
            $params[':search_generic'] = $searchValue;
        }

        if ($category !== '') {
            $where[] = 'c.category_name = :category';
            $params[':category'] = $category;
        }

        if ($type !== '') {
            $where[] = 'pt.type_name = :type';
            $params[':type'] = $type;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "
            SELECT COUNT(*)
            FROM products p
            INNER JOIN product_types pt ON p.type_id = pt.type_id
            INNER JOIN categories c ON p.category_id = c.category_id
            {$whereSql}
        ";

        $countStmt = $this->conn->prepare($countSql);
        $this->bindStringParams($countStmt, $params);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 0;
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $limit;

        // product_batches is the inventory source of truth. products.quantity is
        // synchronized as an aggregate cache for compatibility with the rest of
        // the existing application.
        $sql = "
            SELECT
                p.product_id,
                p.barcode,
                p.product_name,
                p.generic_name,
                p.brand_name,
                pt.type_name,
                c.category_name,
                p.supplier_id,
                COALESCE(s.supplier_name, '') AS supplier_name,
                p.dosage,
                p.strength,
                p.unit,
                COALESCE(batch_totals.batch_quantity, 0) AS quantity,
                p.unit_cost,
                p.selling_price,
                p.manufacturing_date,
                batch_totals.nearest_expiration_date AS expiration_date,
                batch_totals.nearest_batch_number AS batch_number,
                p.description,
                p.is_test_data,
                CASE
                    WHEN COALESCE(batch_totals.batch_quantity, 0) <= 0 THEN 'Out of Stock'
                    WHEN batch_totals.nearest_expiration_date IS NOT NULL
                         AND batch_totals.nearest_expiration_date < CURDATE() THEN 'Expired'
                    WHEN COALESCE(batch_totals.batch_quantity, 0) <= COALESCE(p.reorder_level, 10) THEN 'Low Stock'
                    ELSE 'Available'
                END AS product_status
            FROM products p
            INNER JOIN product_types pt ON p.type_id = pt.type_id
            INNER JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
            LEFT JOIN (
                SELECT
                    pb.product_id,
                    SUM(CASE
                        WHEN pb.quantity > 0
                             AND (pb.expiration_date IS NULL OR pb.expiration_date >= CURDATE())
                        THEN pb.quantity ELSE 0 END
                    ) AS batch_quantity,
                    MIN(CASE
                        WHEN pb.quantity > 0
                             AND (pb.expiration_date IS NULL OR pb.expiration_date >= CURDATE())
                        THEN pb.expiration_date ELSE NULL END
                    ) AS nearest_expiration_date,
                    SUBSTRING_INDEX(
                        GROUP_CONCAT(
                            CASE
                                WHEN pb.quantity > 0
                                     AND (pb.expiration_date IS NULL OR pb.expiration_date >= CURDATE())
                                THEN COALESCE(pb.batch_number, '')
                                ELSE NULL
                            END
                            ORDER BY pb.expiration_date IS NULL, pb.expiration_date ASC, pb.batch_id ASC
                            SEPARATOR ','
                        ), ',', 1
                    ) AS nearest_batch_number
                FROM product_batches pb
                GROUP BY pb.product_id
            ) batch_totals ON batch_totals.product_id = p.product_id
            {$whereSql}
            ORDER BY p.product_name ASC, p.product_id ASC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->conn->prepare($sql);
        $this->bindStringParams($stmt, $params);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'products' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
            ],
        ];
    }

    public function getProductDetails($id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT
                p.*,
                pt.type_name,
                c.category_name,
                COALESCE(s.supplier_name, '') AS supplier_name
            FROM products p
            INNER JOIN product_types pt ON p.type_id = pt.type_id
            INNER JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
            WHERE p.product_id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => (int) $id]);

        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        return $product ?: null;
    }

    public function getProductBatches(int $productId): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                batch_id,
                product_id,
                batch_number,
                expiration_date,
                quantity,
                unit_cost,
                received_date,
                source_reference,
                CASE
                    WHEN quantity <= 0 THEN 'Depleted'
                    WHEN expiration_date IS NOT NULL AND expiration_date < CURDATE() THEN 'Expired'
                    ELSE 'Active'
                END AS batch_status,
                created_at,
                updated_at
            FROM product_batches
            WHERE product_id = :product_id
            ORDER BY expiration_date IS NULL ASC, expiration_date ASC, batch_id ASC
        ");
        $stmt->execute([':product_id' => $productId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategories(): array
    {
        return $this->conn
            ->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTypes(): array
    {
        return $this->conn
            ->query("SELECT type_id, type_name FROM product_types ORDER BY type_name ASC")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSuppliers(): array
    {
        return $this->conn
            ->query("SELECT supplier_id, supplier_name FROM suppliers WHERE supplier_status = 'Active' ORDER BY supplier_name ASC")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resolveSupplierId(?string $supplierName, $supplierId = null): ?int
    {
        $supplierName = trim((string) ($supplierName ?? ''));
        $supplierId = (int) ($supplierId ?? 0);

        if ($supplierName === '') {
            return $supplierId > 0 ? $supplierId : null;
        }

        $find = $this->conn->prepare("SELECT supplier_id FROM suppliers WHERE LOWER(TRIM(supplier_name)) = LOWER(TRIM(:name)) LIMIT 1");
        $find->execute([':name' => $supplierName]);
        $existing = $find->fetchColumn();

        if ($existing !== false) {
            return (int) $existing;
        }

        $insert = $this->conn->prepare("INSERT INTO suppliers (supplier_name, supplier_status) VALUES (:name, 'Active')");
        $insert->execute([':name' => $supplierName]);

        return (int) $this->conn->lastInsertId();
    }

    private function barcodeExists(string $barcode, ?int $excludeProductId = null): bool
    {
        $sql = "SELECT product_id FROM products WHERE barcode = :barcode";
        $params = [':barcode' => $barcode];

        if ($excludeProductId !== null) {
            $sql .= " AND product_id <> :exclude_product_id";
            $params[':exclude_product_id'] = $excludeProductId;
        }

        $stmt = $this->conn->prepare($sql . " LIMIT 1");
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Used when staff don't have the product's real manufacturer barcode on hand yet
     * (common for the client's unlabeled/repackaged stock). Staff can still scan/type
     * the real barcode in later via Edit Product once it's known.
     */
    private function generateFallbackBarcode(int $productId): string
    {
        return 'INT-' . str_pad((string) $productId, 6, '0', STR_PAD_LEFT);
    }

    public function saveProduct(array $data): array
    {
        try {
            $barcode = trim((string) ($data['barcode'] ?? ''));
            $productName = trim((string) ($data['product_name'] ?? ''));
            $categoryId = (int) ($data['category_id'] ?? 0);
            $typeId = (int) ($data['type_id'] ?? 0);
            $quantity = max(0, (int) ($data['quantity'] ?? 0));
            $unitCost = max(0, (float) ($data['unit_cost'] ?? 0));
            $sellingPrice = max(0, (float) ($data['selling_price'] ?? 0));
            $expiration = trim((string) ($data['expiration_date'] ?? '')) ?: null;
            $batchNumber = trim((string) ($data['batch_number'] ?? '')) ?: null;
            $receivedDate = trim((string) ($data['received_date'] ?? '')) ?: date('Y-m-d');
            $isTestData = !empty($data['is_test_data']) ? 1 : 0;

            if ($productName === '' || $categoryId <= 0 || $typeId <= 0) {
                throw new InvalidArgumentException('Please complete the required product fields.');
            }

            if ($barcode !== '' && $this->barcodeExists($barcode)) {
                throw new InvalidArgumentException('This barcode is already used by another product.');
            }

            if ($quantity > 0 && !$batchNumber) {
                throw new InvalidArgumentException('Batch number is required when initial stock is greater than zero.');
            }

            if ($expiration && strtotime($expiration) === false) {
                throw new InvalidArgumentException('Invalid expiration date.');
            }

            $this->conn->beginTransaction();

            $supplierId = $this->resolveSupplierId(
                $data['supplier_name'] ?? null,
                $data['supplier_id'] ?? null
            );

            // barcode is NOT NULL + UNIQUE; when the real barcode isn't known yet, insert a
            // collision-safe placeholder and swap in a product_id-based fallback code once we
            // have the product_id (see below), instead of blocking product creation on it.
            $barcodeForInsert = $barcode !== '' ? $barcode : 'TEMP-' . bin2hex(random_bytes(8));

            $stmt = $this->conn->prepare("
                INSERT INTO products (
                    barcode, product_name, generic_name, brand_name, category_id, type_id, supplier_id,
                    dosage, strength, unit, quantity, unit_cost, selling_price, expiration_date, batch_number,
                    description, is_test_data, product_status
                ) VALUES (
                    :barcode, :product_name, :generic_name, :brand_name, :category_id, :type_id, :supplier_id,
                    :dosage, :strength, :unit, 0, :unit_cost, :selling_price, NULL, NULL,
                    :description, :is_test_data, 'Out of Stock'
                )
            ");
            $stmt->execute([
                ':barcode' => $barcodeForInsert,
                ':product_name' => $productName,
                ':generic_name' => trim((string) ($data['generic_name'] ?? '')) ?: null,
                ':brand_name' => trim((string) ($data['brand_name'] ?? '')) ?: null,
                ':category_id' => $categoryId,
                ':type_id' => $typeId,
                ':supplier_id' => $supplierId,
                ':dosage' => trim((string) ($data['dosage'] ?? '')) ?: null,
                ':strength' => trim((string) ($data['strength'] ?? '')) ?: null,
                ':unit' => trim((string) ($data['unit'] ?? '')) ?: null,
                ':unit_cost' => $unitCost,
                ':selling_price' => $sellingPrice,
                ':description' => trim((string) ($data['description'] ?? '')) ?: null,
                ':is_test_data' => $isTestData,
            ]);

            $productId = (int) $this->conn->lastInsertId();

            if ($barcode === '') {
                $barcode = $this->generateFallbackBarcode($productId);
                $barcodeUpdate = $this->conn->prepare("UPDATE products SET barcode = :barcode WHERE product_id = :product_id");
                $barcodeUpdate->execute([':barcode' => $barcode, ':product_id' => $productId]);
            }

            if ($quantity > 0) {
                $this->upsertBatch($productId, $batchNumber, $expiration, $quantity, $unitCost, $receivedDate, $data['source_reference'] ?? null);
            }

            $this->syncProductAggregate($productId);

            if ($quantity > 0) {
                $history = $this->conn->prepare("
                    INSERT INTO inventory_history (product_id, action_type, quantity, remarks)
                    VALUES (:product_id, 'IN', :quantity, :remarks)
                ");
                $history->execute([
                    ':product_id' => $productId,
                    ':quantity' => $quantity,
                    ':remarks' => 'Initial stock received. Batch: ' . $batchNumber . ($expiration ? ' | Expiry: ' . $expiration : ''),
                ]);
            }

            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Product added successfully.',
                'product_id' => $productId,
                'barcode' => $barcode,
            ];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function adjustStock(array $data): array
    {
        try {
            $productId = (int) ($data['product_id'] ?? 0);
            $action = strtoupper(trim((string) ($data['action'] ?? '')));
            $quantity = (int) ($data['quantity'] ?? 0);
            $remarks = trim((string) ($data['remarks'] ?? ''));
            $batchId = (int) ($data['batch_id'] ?? 0);
            $batchNumber = trim((string) ($data['batch_number'] ?? '')) ?: null;
            $expiration = trim((string) ($data['expiration_date'] ?? '')) ?: null;
            $receivedDate = trim((string) ($data['received_date'] ?? '')) ?: date('Y-m-d');
            $unitCost = max(0, (float) ($data['unit_cost'] ?? 0));

            if ($productId <= 0) {
                throw new InvalidArgumentException('Invalid product.');
            }
            if (!in_array($action, ['IN', 'OUT'], true)) {
                throw new InvalidArgumentException('Invalid stock action.');
            }
            if ($quantity <= 0) {
                throw new InvalidArgumentException('Quantity must be greater than zero.');
            }

            $this->conn->beginTransaction();

            $productStmt = $this->conn->prepare("SELECT product_id, unit_cost FROM products WHERE product_id = :id FOR UPDATE");
            $productStmt->execute([':id' => $productId]);
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                throw new RuntimeException('Product not found.');
            }

            if ($action === 'IN') {
                if ($batchId > 0) {
                    $batch = $this->lockBatch($batchId, $productId);
                    if (!$batch) {
                        throw new RuntimeException('Selected batch not found.');
                    }
                    $newBatchQty = (int) $batch['quantity'] + $quantity;
                    $update = $this->conn->prepare("UPDATE product_batches SET quantity = :quantity, unit_cost = :unit_cost, batch_status = 'Active' WHERE batch_id = :batch_id AND product_id = :product_id");
                    $update->execute([
                        ':quantity' => $newBatchQty,
                        ':unit_cost' => $unitCost > 0 ? $unitCost : (float) $batch['unit_cost'],
                        ':batch_id' => $batchId,
                        ':product_id' => $productId,
                    ]);
                    $usedBatchNumber = $batch['batch_number'];
                } else {
                    if (!$batchNumber) {
                        throw new InvalidArgumentException('Batch number is required for Stock In.');
                    }
                    $this->upsertBatch($productId, $batchNumber, $expiration, $quantity, $unitCost ?: (float) $product['unit_cost'], $receivedDate, $data['source_reference'] ?? null);
                    $usedBatchNumber = $batchNumber;
                }
            } else {
                if ($batchId <= 0) {
                    throw new InvalidArgumentException('Select a batch for Stock Out.');
                }
                $batch = $this->lockBatch($batchId, $productId);
                if (!$batch) {
                    throw new RuntimeException('Selected batch not found.');
                }
                if ((int) $batch['quantity'] < $quantity) {
                    throw new RuntimeException('Not enough stock in the selected batch.');
                }

                $newBatchQty = (int) $batch['quantity'] - $quantity;
                $newStatus = $newBatchQty <= 0 ? 'Depleted' : 'Active';
                $update = $this->conn->prepare("UPDATE product_batches SET quantity = :quantity, batch_status = :status WHERE batch_id = :batch_id AND product_id = :product_id");
                $update->execute([
                    ':quantity' => $newBatchQty,
                    ':status' => $newStatus,
                    ':batch_id' => $batchId,
                    ':product_id' => $productId,
                ]);
                $usedBatchNumber = $batch['batch_number'];
            }

            $this->syncProductAggregate($productId);

            $history = $this->conn->prepare("
                INSERT INTO inventory_history (product_id, action_type, quantity, remarks)
                VALUES (:product_id, :action_type, :quantity, :remarks)
            ");
            $history->execute([
                ':product_id' => $productId,
                ':action_type' => $action,
                ':quantity' => $quantity,
                ':remarks' => ($remarks !== '' ? $remarks . ' | ' : '') . 'Batch: ' . ($usedBatchNumber ?: 'N/A'),
            ]);

            $this->conn->commit();

            return ['success' => true, 'message' => 'Stock adjustment saved successfully.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateProduct(array $data): array
    {
        try {
            $productId = (int) ($data['product_id'] ?? 0);
            if ($productId <= 0) {
                throw new InvalidArgumentException('Invalid product.');
            }

            $barcode = trim((string) ($data['barcode'] ?? ''));
            if ($barcode !== '' && $this->barcodeExists($barcode, $productId)) {
                throw new InvalidArgumentException('This barcode is already used by another product.');
            }
            if ($barcode === '') {
                $barcode = $this->generateFallbackBarcode($productId);
            }

            $this->conn->beginTransaction();

            $supplierId = $this->resolveSupplierId(
                $data['supplier_name'] ?? null,
                $data['supplier_id'] ?? null
            );

            $stmt = $this->conn->prepare("
                UPDATE products
                SET
                    barcode = :barcode,
                    product_name = :product_name,
                    generic_name = :generic_name,
                    brand_name = :brand_name,
                    category_id = :category_id,
                    type_id = :type_id,
                    supplier_id = :supplier_id,
                    dosage = :dosage,
                    strength = :strength,
                    unit = :unit,
                    selling_price = :selling_price,
                    description = :description,
                    is_test_data = :is_test_data
                WHERE product_id = :product_id
            ");
            $stmt->execute([
                ':product_id' => $productId,
                ':barcode' => $barcode,
                ':product_name' => trim((string) ($data['product_name'] ?? '')),
                ':generic_name' => trim((string) ($data['generic_name'] ?? '')) ?: null,
                ':brand_name' => trim((string) ($data['brand_name'] ?? '')) ?: null,
                ':category_id' => (int) ($data['category_id'] ?? 0),
                ':type_id' => (int) ($data['type_id'] ?? 0),
                ':supplier_id' => $supplierId,
                ':dosage' => trim((string) ($data['dosage'] ?? '')) ?: null,
                ':strength' => trim((string) ($data['strength'] ?? '')) ?: null,
                ':unit' => trim((string) ($data['unit'] ?? '')) ?: null,
                ':selling_price' => max(0, (float) ($data['selling_price'] ?? 0)),
                ':description' => trim((string) ($data['description'] ?? '')) ?: null,
                ':is_test_data' => !empty($data['is_test_data']) ? 1 : 0,
            ]);

            if ($stmt->rowCount() === 0) {
                $check = $this->getProductDetails($productId);
                if (!$check) {
                    throw new RuntimeException('Product not found.');
                }
            }

            $this->syncProductAggregate($productId);
            $this->conn->commit();

            return ['success' => true, 'message' => 'Product information updated successfully.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteProduct(int $productId): array
    {
        try {
            if ($productId <= 0) {
                throw new InvalidArgumentException('Invalid product.');
            }

            $this->conn->beginTransaction();

            $check = $this->conn->prepare("SELECT product_id, product_name FROM products WHERE product_id = :id FOR UPDATE");
            $check->execute([':id' => $productId]);
            $product = $check->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                throw new RuntimeException('Product not found.');
            }

            $sales = $this->conn->prepare("SELECT COUNT(*) FROM sale_items WHERE product_id = :id");
            $sales->execute([':id' => $productId]);
            if ((int) $sales->fetchColumn() > 0) {
                throw new RuntimeException('This product cannot be deleted because it has sales history.');
            }

            $history = $this->conn->prepare("SELECT COUNT(*) FROM inventory_history WHERE product_id = :id");
            $history->execute([':id' => $productId]);
            if ((int) $history->fetchColumn() > 0) {
                throw new RuntimeException('This product cannot be deleted because it has inventory history.');
            }

            $batches = $this->conn->prepare("SELECT COUNT(*) FROM product_batches WHERE product_id = :id");
            $batches->execute([':id' => $productId]);
            if ((int) $batches->fetchColumn() > 0) {
                throw new RuntimeException('This product cannot be deleted because it has batch inventory records.');
            }

            $delete = $this->conn->prepare("DELETE FROM products WHERE product_id = :id");
            $delete->execute([':id' => $productId]);

            $this->conn->commit();
            return ['success' => true, 'message' => 'Product deleted successfully.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function cleanupTestProduct(int $productId): array
    {
        try {
            if ($productId <= 0) {
                throw new InvalidArgumentException('Invalid test product.');
            }

            $this->conn->beginTransaction();

            $check = $this->conn->prepare("
                SELECT product_id, product_name, is_test_data
                FROM products
                WHERE product_id = :id
                FOR UPDATE
            ");
            $check->execute([':id' => $productId]);
            $product = $check->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new RuntimeException('Test product not found.');
            }

            if ((int) ($product['is_test_data'] ?? 0) !== 1) {
                throw new RuntimeException('This product is not marked as test data. Normal product deletion is protected.');
            }

            $sales = $this->conn->prepare("
                SELECT COUNT(*)
                FROM sale_items
                WHERE product_id = :id
            ");
            $sales->execute([':id' => $productId]);

            if ((int) $sales->fetchColumn() > 0) {
                throw new RuntimeException(
                    'Test cleanup is blocked because this product has sales history. Sales records must be preserved.'
                );
            }

            // Test-only cleanup: remove operational records belonging exclusively
            // to this product, then remove the product master itself.
            // Capture stock-adjustment headers that belong to this product so
            // cleanup never touches unrelated adjustment records.
            $adjustmentStmt = $this->conn->prepare("
                SELECT DISTINCT adjustment_id
                FROM stock_adjustment_items
                WHERE product_id = :id
            ");
            $adjustmentStmt->execute([':id' => $productId]);
            $adjustmentIds = array_map('intval', $adjustmentStmt->fetchAll(PDO::FETCH_COLUMN));

            foreach ([
                'inventory_history',
                'inventory_movements',
                'notifications',
                'forecast_results',
                'product_batches',
                'stock_adjustment_items',
            ] as $table) {
                $stmt = $this->conn->prepare("DELETE FROM {$table} WHERE product_id = :id");
                $stmt->execute([':id' => $productId]);
            }

            // Remove only the now-orphaned adjustment headers that were
            // associated with this test product.
            if ($adjustmentIds) {
                $placeholders = implode(',', array_fill(0, count($adjustmentIds), '?'));
                $orphanStmt = $this->conn->prepare("
                    DELETE sa
                    FROM stock_adjustments sa
                    LEFT JOIN stock_adjustment_items sai
                        ON sai.adjustment_id = sa.adjustment_id
                    WHERE sa.adjustment_id IN ({$placeholders})
                      AND sai.adjustment_id IS NULL
                ");
                $orphanStmt->execute($adjustmentIds);
            }

            $delete = $this->conn->prepare("DELETE FROM products WHERE product_id = :id AND is_test_data = 1");
            $delete->execute([':id' => $productId]);

            if ($delete->rowCount() !== 1) {
                throw new RuntimeException('Test product cleanup could not remove the product.');
            }

            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Test product and its test inventory records were cleaned up successfully.',
            ];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function upsertBatch(
        int $productId,
        string $batchNumber,
        ?string $expirationDate,
        int $quantity,
        float $unitCost,
        string $receivedDate,
        ?string $sourceReference = null
    ): int {
        $find = $this->conn->prepare("
            SELECT batch_id, batch_number, expiration_date, quantity, unit_cost
            FROM product_batches
            WHERE product_id = :product_id
              AND batch_number = :batch_number
            LIMIT 1
            FOR UPDATE
        ");
        $find->execute([
            ':product_id' => $productId,
            ':batch_number' => $batchNumber,
        ]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $existingExpiration = $existing['expiration_date'] ?? null;
            if ($existingExpiration !== $expirationDate) {
                throw new InvalidArgumentException(
                    'The batch number already exists with a different expiration date. Use a new batch number for stock with a different expiry.'
                );
            }

            $newQuantity = (int) $existing['quantity'] + $quantity;
            $update = $this->conn->prepare("
                UPDATE product_batches
                SET quantity = :quantity,
                    unit_cost = COALESCE(NULLIF(:unit_cost, 0), unit_cost),
                    source_reference = COALESCE(NULLIF(:source_reference, ''), source_reference),
                    batch_status = 'Active'
                WHERE batch_id = :batch_id
            ");
            $update->execute([
                ':quantity' => $newQuantity,
                ':unit_cost' => $unitCost,
                ':source_reference' => $sourceReference ?? '',
                ':batch_id' => (int) $existing['batch_id'],
            ]);
            return (int) $existing['batch_id'];
        }

        $insert = $this->conn->prepare("
            INSERT INTO product_batches (
                product_id, batch_number, expiration_date, quantity, unit_cost,
                received_date, source_reference, batch_status
            ) VALUES (
                :product_id, :batch_number, :expiration_date, :quantity, :unit_cost,
                :received_date, :source_reference, 'Active'
            )
        ");
        $insert->execute([
            ':product_id' => $productId,
            ':batch_number' => $batchNumber,
            ':expiration_date' => $expirationDate,
            ':quantity' => $quantity,
            ':unit_cost' => $unitCost,
            ':received_date' => $receivedDate,
            ':source_reference' => $sourceReference,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    private function lockBatch(int $batchId, int $productId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM product_batches
            WHERE batch_id = :batch_id AND product_id = :product_id
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([
            ':batch_id' => $batchId,
            ':product_id' => $productId,
        ]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);
        return $batch ?: null;
    }

    private function syncProductAggregate(int $productId): void
    {
        $stmt = $this->conn->prepare("
            SELECT
                COALESCE(SUM(CASE
                    WHEN quantity > 0
                         AND (expiration_date IS NULL OR expiration_date >= CURDATE())
                    THEN quantity ELSE 0 END), 0) AS total_quantity,
                MIN(CASE
                    WHEN quantity > 0
                         AND (expiration_date IS NULL OR expiration_date >= CURDATE())
                    THEN expiration_date ELSE NULL END) AS nearest_expiration,
                SUBSTRING_INDEX(
                    GROUP_CONCAT(
                        CASE
                            WHEN quantity > 0
                                 AND (expiration_date IS NULL OR expiration_date >= CURDATE())
                            THEN COALESCE(batch_number, '')
                            ELSE NULL
                        END
                        ORDER BY expiration_date IS NULL, expiration_date ASC, batch_id ASC
                        SEPARATOR ','
                    ), ',', 1
                ) AS nearest_batch,
                MAX(CASE WHEN quantity > 0 AND (expiration_date IS NULL OR expiration_date >= CURDATE()) THEN unit_cost ELSE NULL END) AS latest_unit_cost
            FROM product_batches
            WHERE product_id = :product_id
        ");
        $stmt->execute([':product_id' => $productId]);
        $aggregate = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $quantity = (int) ($aggregate['total_quantity'] ?? 0);
        $expiration = $aggregate['nearest_expiration'] ?? null;
        $batchNumber = $aggregate['nearest_batch'] ?? null;

        if ($quantity <= 0) {
            $status = 'Out of Stock';
        } elseif ($expiration && strtotime($expiration) < strtotime(date('Y-m-d'))) {
            $status = 'Expired';
        } else {
            $productStmt = $this->conn->prepare("SELECT reorder_level FROM products WHERE product_id = :id");
            $productStmt->execute([':id' => $productId]);
            $reorder = (int) ($productStmt->fetchColumn() ?: 10);
            $status = $quantity <= $reorder ? 'Low Stock' : 'Available';
        }

        $update = $this->conn->prepare("
            UPDATE products
            SET quantity = :quantity,
                expiration_date = :expiration_date,
                batch_number = :batch_number,
                unit_cost = COALESCE(:latest_unit_cost, unit_cost),
                product_status = :product_status
            WHERE product_id = :product_id
        ");
        $update->execute([
            ':quantity' => $quantity,
            ':expiration_date' => $expiration,
            ':batch_number' => $batchNumber,
            ':latest_unit_cost' => $aggregate['latest_unit_cost'] ?? null,
            ':product_status' => $status,
            ':product_id' => $productId,
        ]);
    }

    private function bindStringParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
}
