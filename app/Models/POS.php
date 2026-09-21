<?php

require_once __DIR__ . '/../../config/database.php';

class POS
{
    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? (new Database())->connect();
    }

public function getAvailableProducts(
    int $page = 1,
    int $limit = 20,
    string $search = '',
    string $category = '',
    string $type = ''
): array {

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE PAGINATION
    |--------------------------------------------------------------------------
    */

    $page = max(1, $page);

    $limit = min(
        max(1, $limit),
        100
    );

    $offset = ($page - 1) * $limit;


    /*
    |--------------------------------------------------------------------------
    | BASE SELLABLE PRODUCT CONDITIONS
    |--------------------------------------------------------------------------
    |
    | A product appears in the POS only when:
    |
    | 1. It is not expired
    | 2. It has available stock
    | 3. It has a valid selling price
    |
    */

    $where = [
        "p.product_status != 'Expired'",
        "p.quantity > 0",
        "p.selling_price > 0"
    ];

    $params = [];


    /*
    |--------------------------------------------------------------------------
    | SEARCH FILTER
    |--------------------------------------------------------------------------
    */

    if ($search !== '') {

        $where[] = "
            (
                p.product_name LIKE :search_product
                OR p.barcode LIKE :search_barcode
            )
        ";

        $searchValue = '%' . $search . '%';
        $params[':search_product'] = $searchValue;
        $params[':search_barcode'] = $searchValue;
    }


    /*
    |--------------------------------------------------------------------------
    | CATEGORY FILTER
    |--------------------------------------------------------------------------
    */

    if ($category !== '') {

        $where[] = "
            c.category_name = :category
        ";

        $params[':category'] = $category;
    }


    /*
    |--------------------------------------------------------------------------
    | PRODUCT TYPE FILTER
    |--------------------------------------------------------------------------
    */

    if ($type !== '') {

        $where[] = "
            t.type_name = :type
        ";

        $params[':type'] = $type;
    }


    /*
    |--------------------------------------------------------------------------
    | BUILD WHERE CLAUSE
    |--------------------------------------------------------------------------
    */

    $whereSql = implode(
        " AND ",
        $where
    );


    /*
    |--------------------------------------------------------------------------
    | COUNT FILTERED SELLABLE PRODUCTS
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | This uses the EXACT SAME filters as the product query.
    |
    */

    $countSql = "
        SELECT COUNT(*)

        FROM products p

        LEFT JOIN categories c
            ON p.category_id = c.category_id

        LEFT JOIN product_types t
            ON p.type_id = t.type_id

        WHERE {$whereSql}
    ";

    $countStmt = $this->conn->prepare(
        $countSql
    );


    foreach ($params as $key => $value) {

        $countStmt->bindValue(
            $key,
            $value,
            PDO::PARAM_STR
        );
    }


    $countStmt->execute();


    $total = (int) $countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | GET PAGINATED PRODUCTS
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT

            p.product_id,

            p.barcode,

            p.product_name,

            COALESCE(
                c.category_name,
                'Uncategorized'
            ) AS category_name,

            COALESCE(
                t.type_name,
                'Unspecified'
            ) AS type_name,

            p.quantity,

            p.selling_price,

            p.product_status

        FROM products p

        LEFT JOIN categories c
            ON p.category_id = c.category_id

        LEFT JOIN product_types t
            ON p.type_id = t.type_id

        WHERE {$whereSql}

        ORDER BY
            p.product_name ASC,
            p.product_id ASC

        LIMIT :limit
        OFFSET :offset
    ";


    $stmt = $this->conn->prepare(
        $sql
    );


    foreach ($params as $key => $value) {

        $stmt->bindValue(
            $key,
            $value,
            PDO::PARAM_STR
        );
    }


    /*
    |--------------------------------------------------------------------------
    | LIMIT / OFFSET
    |--------------------------------------------------------------------------
    */

    $stmt->bindValue(
        ':limit',
        $limit,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':offset',
        $offset,
        PDO::PARAM_INT
    );


    $stmt->execute();


    $products = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    /*
    |--------------------------------------------------------------------------
    | PAGINATION INFORMATION
    |--------------------------------------------------------------------------
    */

    $totalPages =
        $total > 0
            ? (int) ceil(
                $total / $limit
            )
            : 0;


    return [

        'products' => $products,

        'pagination' => [

            'page' => $page,

            'limit' => $limit,

            'total' => $total,

            'total_pages' => $totalPages,

            'has_previous' =>
                $page > 1,

            'has_next' =>
                $page < $totalPages

        ]

    ];
}




public function getCategories(): array
{
    $sql = "

        SELECT
            category_id,
            category_name

        FROM categories

        ORDER BY category_name ASC

    ";

    return $this->conn
        ->query($sql)
        ->fetchAll(PDO::FETCH_ASSOC);
}


public function getProductTypes(): array
{
    $sql = "

        SELECT
            type_id,
            type_name

        FROM product_types

        ORDER BY type_name ASC

    ";

    return $this->conn
        ->query($sql)
        ->fetchAll(PDO::FETCH_ASSOC);
}



public function processSale(array $data): array
{
    try {
        $cart = $data['cart'] ?? [];
        $cashierId = (int) ($data['cashier_id'] ?? 0);
        $paymentMethod = (string) ($data['paymentMethod'] ?? 'Cash');
        $reference = trim((string) ($data['reference'] ?? ''));

        if ($cashierId <= 0) {
            throw new Exception('Your session has expired. Please log in again.');
        }

        if (!in_array($paymentMethod, ['Cash', 'GCash', 'Maya'], true)) {
            throw new Exception('Invalid payment method.');
        }

        if (empty($cart)) {
            throw new Exception('Please add at least one product.');
        }

        if ($paymentMethod !== 'Cash' && $reference === '') {
            throw new Exception('Reference number is required for electronic payments.');
        }

        $discountType = (string) ($data['discountType'] ?? 'percent');
        $discountValue = max(0, (float) ($data['discountValue'] ?? 0));

        if (!in_array($discountType, ['percent', 'peso'], true)) {
            throw new Exception('Invalid discount type.');
        }

        $this->conn->beginTransaction();

        /*
         * Validate the cart against live product data first.
         * Stock is intentionally read from product_batches, not products.quantity.
         */
        $productStmt = $this->conn->prepare("
            SELECT product_id, product_name, selling_price, product_status
            FROM products
            WHERE product_id = :product_id
            LIMIT 1
            FOR UPDATE
        ");

        $validatedCart = [];
        $subtotal = 0.00;

        foreach ($cart as $item) {
            $productId = (int) ($item['id'] ?? 0);
            $qty = (int) ($item['qty'] ?? 0);

            if ($productId <= 0 || $qty <= 0) {
                throw new Exception('Invalid product or quantity.');
            }

            $productStmt->execute([':product_id' => $productId]);
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception('Product no longer exists.');
            }

            $price = max(0, (float) $product['selling_price']);
            if ($price <= 0) {
                throw new Exception($product['product_name'] . ' cannot be sold because it has no valid selling price.');
            }

            $availableStmt = $this->conn->prepare("
                SELECT COALESCE(SUM(pb.quantity), 0)
                FROM product_batches pb
                WHERE pb.product_id = :product_id
                  AND pb.quantity > 0
                  AND (pb.expiration_date IS NULL OR pb.expiration_date >= CURDATE())
                  AND pb.batch_status = 'Active'
            ");
            $availableStmt->execute([':product_id' => $productId]);
            $available = (int) $availableStmt->fetchColumn();

            if ($available < $qty) {
                throw new Exception(
                    'Insufficient sellable batch stock for ' . $product['product_name'] .
                    '. Available: ' . $available . ', requested: ' . $qty . '.'
                );
            }

            $validatedCart[] = [
                'id' => $productId,
                'qty' => $qty,
                'price' => $price,
                'name' => $product['product_name'],
            ];

            $subtotal += $qty * $price;
        }

        $subtotal = round($subtotal, 2);

        if ($discountType === 'percent') {
            if ($discountValue > 100) {
                throw new Exception('Percentage discount cannot exceed 100%.');
            }
            $discountAmount = $subtotal * ($discountValue / 100);
        } else {
            if ($discountValue > $subtotal) {
                throw new Exception('Peso discount cannot exceed the subtotal.');
            }
            $discountAmount = $discountValue;
        }

        $discountAmount = round(min($discountAmount, $subtotal), 2);
        $total = round(max($subtotal - $discountAmount, 0), 2);
        $cash = max(0, (float) ($data['cash'] ?? 0));

        if ($paymentMethod === 'Cash' && $cash < $total) {
            throw new Exception('Insufficient cash.');
        }

        $amountPaid = $paymentMethod === 'Cash' ? $cash : $total;
        $change = $paymentMethod === 'Cash' ? round(max($cash - $total, 0), 2) : 0.00;

        $transactionNumber = 'TID-' . date('YmdHis') . '-' . random_int(100, 999);

        $stmt = $this->conn->prepare("
            INSERT INTO sales
            (
                transaction_number,
                customer_id,
                cashier_id,
                subtotal,
                discount_amount,
                vat_amount,
                total_amount,
                payment_status,
                transaction_status
            )
            VALUES
            (
                :transaction_number,
                NULL,
                :cashier_id,
                :subtotal,
                :discount,
                0,
                :total,
                'Paid',
                'Completed'
            )
        ");

        $stmt->execute([
            ':transaction_number' => $transactionNumber,
            ':cashier_id' => $cashierId,
            ':subtotal' => $subtotal,
            ':discount' => $discountAmount,
            ':total' => $total,
        ]);

        $saleId = (int) $this->conn->lastInsertId();

        $paymentStmt = $this->conn->prepare("
            INSERT INTO payments
            (
                sale_id,
                payment_method,
                amount_due,
                amount_paid,
                change_amount,
                reference_number,
                payment_status
            )
            VALUES
            (
                :sale_id,
                :payment_method,
                :amount_due,
                :amount_paid,
                :change_amount,
                :reference_number,
                'Paid'
            )
        ");

        $paymentStmt->execute([
            ':sale_id' => $saleId,
            ':payment_method' => $paymentMethod,
            ':amount_due' => $total,
            ':amount_paid' => $amountPaid,
            ':change_amount' => $change,
            ':reference_number' => $paymentMethod === 'Cash' ? null : $reference,
        ]);

        $itemStmt = $this->conn->prepare("
            INSERT INTO sale_items
            (
                sale_id,
                product_id,
                quantity,
                unit_price,
                discount_amount,
                subtotal
            )
            VALUES
            (
                :sale_id,
                :product_id,
                :quantity,
                :unit_price,
                0,
                :subtotal
            )
        ");

        $allocationStmt = $this->conn->prepare("
            INSERT INTO sale_item_batches
            (
                sale_item_id,
                batch_id,
                quantity
            )
            VALUES
            (
                :sale_item_id,
                :batch_id,
                :quantity
            )
        ");

        $batchStmt = $this->conn->prepare("
            SELECT
                batch_id,
                batch_number,
                expiration_date,
                quantity,
                received_date
            FROM product_batches
            WHERE product_id = :product_id
              AND quantity > 0
              AND batch_status = 'Active'
              AND (expiration_date IS NULL OR expiration_date >= CURDATE())
            ORDER BY
                expiration_date IS NULL ASC,
                expiration_date ASC,
                received_date ASC,
                batch_id ASC
            FOR UPDATE
        ");

        $batchUpdateStmt = $this->conn->prepare("
            UPDATE product_batches
            SET
                quantity = :quantity,
                batch_status = CASE
                    WHEN :quantity_status <= 0 THEN 'Depleted'
                    ELSE 'Active'
                END
            WHERE batch_id = :batch_id
              AND product_id = :product_id
        ");

        $movementStmt = $this->conn->prepare("
            INSERT INTO inventory_movements
            (
                product_id,
                batch_id,
                user_id,
                sale_id,
                movement_type,
                quantity_changed,
                previous_stock,
                new_stock,
                reference_number,
                remarks
            )
            VALUES
            (
                :product_id,
                :batch_id,
                :user_id,
                :sale_id,
                'Sale',
                :quantity_changed,
                :previous_stock,
                :new_stock,
                :reference_number,
                :remarks
            )
        ");

        foreach ($validatedCart as $item) {
            $productId = (int) $item['id'];
            $qty = (int) $item['qty'];
            $price = (float) $item['price'];
            $itemSubtotal = round($qty * $price, 2);

            $itemStmt->execute([
                ':sale_id' => $saleId,
                ':product_id' => $productId,
                ':quantity' => $qty,
                ':unit_price' => $price,
                ':subtotal' => $itemSubtotal,
            ]);
            $saleItemId = (int) $this->conn->lastInsertId();

            /*
             * FEFO: earliest non-expired expiration date first.
             * No-expiry batches are intentionally last.
             */
            $remaining = $qty;
            $batchStmt->execute([':product_id' => $productId]);
            $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $batchQuantity = (int) $batch['quantity'];
                if ($batchQuantity <= 0) {
                    continue;
                }

                $allocated = min($remaining, $batchQuantity);
                $newBatchQuantity = $batchQuantity - $allocated;

                $batchUpdateStmt->execute([
                    ':quantity' => $newBatchQuantity,
                    ':quantity_status' => $newBatchQuantity,
                    ':batch_id' => (int) $batch['batch_id'],
                    ':product_id' => $productId,
                ]);

                if ($batchUpdateStmt->rowCount() !== 1) {
                    throw new RuntimeException('Failed to update batch stock for ' . $item['name'] . '.');
                }

                $allocationStmt->execute([
                    ':sale_item_id' => $saleItemId,
                    ':batch_id' => (int) $batch['batch_id'],
                    ':quantity' => $allocated,
                ]);

                $movementStmt->execute([
                    ':product_id' => $productId,
                    ':batch_id' => (int) $batch['batch_id'],
                    ':user_id' => $cashierId,
                    ':sale_id' => $saleId,
                    ':quantity_changed' => -$allocated,
                    ':previous_stock' => $batchQuantity,
                    ':new_stock' => $newBatchQuantity,
                    ':reference_number' => $transactionNumber,
                    ':remarks' => 'FEFO sale allocation | Batch: ' . ($batch['batch_number'] ?: 'N/A') .
                        ' | Expiry: ' . ($batch['expiration_date'] ?: 'N/A'),
                ]);

                $remaining -= $allocated;
            }

            if ($remaining > 0) {
                throw new RuntimeException(
                    'Unable to allocate complete FEFO stock for ' . $item['name'] . '.'
                );
            }

            $this->syncProductAggregate($productId);
        }

        $this->conn->commit();

        return [
            'success' => true,
            'message' => 'Sale completed successfully.',
            'transaction_number' => $transactionNumber,
            'sale_id' => $saleId,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'total_amount' => $total,
            'change_amount' => $change,
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

private function syncProductAggregate(int $productId): void
{
    $stmt = $this->conn->prepare("
        SELECT
            COALESCE(SUM(CASE
                WHEN quantity > 0
                 AND (expiration_date IS NULL OR expiration_date >= CURDATE())
                THEN quantity ELSE 0 END), 0) AS quantity,
            MIN(CASE
                WHEN quantity > 0
                 AND (expiration_date IS NULL OR expiration_date >= CURDATE())
                THEN expiration_date ELSE NULL END) AS nearest_expiration
        FROM product_batches
        WHERE product_id = :product_id
    ");
    $stmt->execute([':product_id' => $productId]);
    $aggregate = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['quantity' => 0, 'nearest_expiration' => null];

    $quantity = (int) $aggregate['quantity'];
    $expiration = $aggregate['nearest_expiration'] ?: null;

    $status = $quantity <= 0 ? 'Out of Stock' : 'Available';

    $product = $this->conn->prepare("SELECT reorder_level FROM products WHERE product_id = :product_id FOR UPDATE");
    $product->execute([':product_id' => $productId]);
    $reorderLevel = $product->fetchColumn();
    if ($quantity > 0 && $quantity <= (int) ($reorderLevel ?? 10)) {
        $status = 'Low Stock';
    }

    $update = $this->conn->prepare("
        UPDATE products
        SET
            quantity = :quantity,
            expiration_date = :expiration_date,
            product_status = :product_status
        WHERE product_id = :product_id
    ");
    $update->execute([
        ':quantity' => $quantity,
        ':expiration_date' => $expiration,
        ':product_status' => $status,
        ':product_id' => $productId,
    ]);
}

}


