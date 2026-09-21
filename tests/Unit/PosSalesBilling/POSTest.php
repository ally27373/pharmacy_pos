<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Support/MocksPdo.php';
require_once __DIR__ . '/../../../app/Models/POS.php';

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for app/Models/POS.php.
 *
 * The real Database/PDO connection is never touched: every test injects a
 * mock PDO through the constructor-injection seam described in
 * tests/TESTING.md.
 */
final class POSTest extends TestCase
{
    use MocksPdo;

    /**
     * Builds a PDO mock whose prepare() dispatches to a specific statement
     * mock based on a unique substring found in the SQL text. This lets a
     * single test wire up several different prepared statements (count
     * query, insert, update, ...) without caring about call order.
     */
    private function pdoDispatching(array $map): PDO&MockObject
    {
        $pdo = $this->createPdoMock();
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($map) {
            foreach ($map as $marker => $stmt) {
                if (str_contains($sql, $marker)) {
                    return $stmt;
                }
            }
            throw new RuntimeException('No mock configured for prepared SQL: ' . $sql);
        });
        return $pdo;
    }

    // -----------------------------------------------------------------
    // getAvailableProducts()
    // -----------------------------------------------------------------

    public function testGetAvailableProductsNormalizesPaginationAndReturnsMeta(): void
    {
        $countStmt = $this->createStatementMock();
        $countStmt->method('fetchColumn')->willReturn(25);

        $boundLimitOffset = [];
        $dataStmt = $this->createStatementMock();
        $dataStmt->method('bindValue')->willReturnCallback(function ($key, $value) use (&$boundLimitOffset) {
            $boundLimitOffset[$key] = $value;
            return true;
        });
        $dataStmt->method('fetchAll')->willReturn([
            ['product_id' => 1, 'product_name' => 'Amoxicillin'],
            ['product_id' => 2, 'product_name' => 'Biogesic'],
        ]);

        $pdo = $this->pdoDispatching([
            'COUNT(*)' => $countStmt,
            'p.barcode' => $dataStmt,
        ]);

        $pos = new POS($pdo);
        $result = $pos->getAvailableProducts(2, 10);

        $this->assertSame([
            ['product_id' => 1, 'product_name' => 'Amoxicillin'],
            ['product_id' => 2, 'product_name' => 'Biogesic'],
        ], $result['products']);

        $this->assertSame(2, $result['pagination']['page']);
        $this->assertSame(10, $result['pagination']['limit']);
        $this->assertSame(25, $result['pagination']['total']);
        $this->assertSame(3, $result['pagination']['total_pages']);
        $this->assertTrue($result['pagination']['has_previous']);
        $this->assertTrue($result['pagination']['has_next']);

        // offset = (page - 1) * limit = 10
        $this->assertSame(10, $boundLimitOffset[':limit']);
        $this->assertSame(10, $boundLimitOffset[':offset']);
    }

    public function testGetAvailableProductsClampsLimitAbove100To100(): void
    {
        $countStmt = $this->createStatementMock();
        $countStmt->method('fetchColumn')->willReturn(0);

        $bound = [];
        $dataStmt = $this->createStatementMock();
        $dataStmt->method('bindValue')->willReturnCallback(function ($key, $value) use (&$bound) {
            $bound[$key] = $value;
            return true;
        });
        $dataStmt->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoDispatching([
            'COUNT(*)' => $countStmt,
            'p.barcode' => $dataStmt,
        ]);

        $pos = new POS($pdo);
        $result = $pos->getAvailableProducts(1, 500);

        $this->assertSame(100, $result['pagination']['limit']);
        $this->assertSame(100, $bound[':limit']);
    }

    public function testGetAvailableProductsClampsPageAndLimitBelow1To1(): void
    {
        $countStmt = $this->createStatementMock();
        $countStmt->method('fetchColumn')->willReturn(0);

        $bound = [];
        $dataStmt = $this->createStatementMock();
        $dataStmt->method('bindValue')->willReturnCallback(function ($key, $value) use (&$bound) {
            $bound[$key] = $value;
            return true;
        });
        $dataStmt->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoDispatching([
            'COUNT(*)' => $countStmt,
            'p.barcode' => $dataStmt,
        ]);

        $pos = new POS($pdo);
        $result = $pos->getAvailableProducts(0, 0);

        $this->assertSame(1, $result['pagination']['page']);
        $this->assertSame(1, $result['pagination']['limit']);
        // offset = (1 - 1) * 1 = 0
        $this->assertSame(0, $bound[':offset']);
    }

    public function testGetAvailableProductsBindsSearchCategoryAndTypeFilters(): void
    {
        $bound = [];

        $countStmt = $this->createStatementMock();
        $countStmt->method('bindValue')->willReturnCallback(function ($key, $value) use (&$bound) {
            $bound[$key] = $value;
            return true;
        });
        $countStmt->method('fetchColumn')->willReturn(0);

        $dataStmt = $this->createStatementMock();
        $dataStmt->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoDispatching([
            'COUNT(*)' => $countStmt,
            'p.barcode' => $dataStmt,
        ]);

        $pos = new POS($pdo);
        $pos->getAvailableProducts(1, 20, 'amox', 'Antibiotics', 'Tablet');

        $this->assertSame('%amox%', $bound[':search_product']);
        $this->assertSame('%amox%', $bound[':search_barcode']);
        $this->assertSame('Antibiotics', $bound[':category']);
        $this->assertSame('Tablet', $bound[':type']);
    }

    public function testGetAvailableProductsWithZeroTotalReturnsZeroPagesAndNoPrevNext(): void
    {
        $countStmt = $this->createStatementMock();
        $countStmt->method('fetchColumn')->willReturn(0);

        $dataStmt = $this->createStatementMock();
        $dataStmt->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoDispatching([
            'COUNT(*)' => $countStmt,
            'p.barcode' => $dataStmt,
        ]);

        $pos = new POS($pdo);
        $result = $pos->getAvailableProducts();

        $this->assertSame([], $result['products']);
        $this->assertSame(0, $result['pagination']['total']);
        $this->assertSame(0, $result['pagination']['total_pages']);
        $this->assertFalse($result['pagination']['has_previous']);
        $this->assertFalse($result['pagination']['has_next']);
    }

    // -----------------------------------------------------------------
    // getCategories() / getProductTypes()
    // -----------------------------------------------------------------

    public function testGetCategoriesReturnsRowsFromQuery(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('fetchAll')->willReturn([
            ['category_id' => 1, 'category_name' => 'Antibiotics'],
            ['category_id' => 2, 'category_name' => 'Analgesics'],
        ]);

        $pdo = $this->createPdoMock();
        $pdo->expects($this->once())
            ->method('query')
            ->with($this->stringContains('FROM categories'))
            ->willReturn($stmt);

        $pos = new POS($pdo);
        $result = $pos->getCategories();

        $this->assertSame([
            ['category_id' => 1, 'category_name' => 'Antibiotics'],
            ['category_id' => 2, 'category_name' => 'Analgesics'],
        ], $result);
    }

    public function testGetProductTypesReturnsRowsFromQuery(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('fetchAll')->willReturn([
            ['type_id' => 1, 'type_name' => 'Tablet'],
        ]);

        $pdo = $this->createPdoMock();
        $pdo->expects($this->once())
            ->method('query')
            ->with($this->stringContains('FROM product_types'))
            ->willReturn($stmt);

        $pos = new POS($pdo);
        $result = $pos->getProductTypes();

        $this->assertSame([
            ['type_id' => 1, 'type_name' => 'Tablet'],
        ], $result);
    }

    // -----------------------------------------------------------------
    // processSale() — validation failures that return before any
    // product/stock lookups are needed.
    // -----------------------------------------------------------------

    public function testProcessSaleFailsWhenCashierIdMissing(): void
    {
        $pdo = $this->createPdoMock();
        $pos = new POS($pdo);

        $result = $pos->processSale([
            'cart' => [['id' => 1, 'qty' => 1]],
            'paymentMethod' => 'Cash',
            'cash' => 100,
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Your session has expired. Please log in again.', $result['message']);
    }

    public function testProcessSaleFailsWhenPaymentMethodInvalid(): void
    {
        $pdo = $this->createPdoMock();
        $pos = new POS($pdo);

        $result = $pos->processSale([
            'cashier_id' => 1,
            'cart' => [['id' => 1, 'qty' => 1]],
            'paymentMethod' => 'Bitcoin',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid payment method.', $result['message']);
    }

    public function testProcessSaleFailsWhenCartEmpty(): void
    {
        $pdo = $this->createPdoMock();
        $pos = new POS($pdo);

        $result = $pos->processSale([
            'cashier_id' => 1,
            'cart' => [],
            'paymentMethod' => 'Cash',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Please add at least one product.', $result['message']);
    }

    public function testProcessSaleFailsWhenElectronicPaymentMissingReference(): void
    {
        $pdo = $this->createPdoMock();
        $pos = new POS($pdo);

        $result = $pos->processSale([
            'cashier_id' => 1,
            'cart' => [['id' => 1, 'qty' => 1]],
            'paymentMethod' => 'GCash',
            'reference' => '   ',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Reference number is required for electronic payments.', $result['message']);
    }

    public function testProcessSaleFailsWhenDiscountTypeInvalid(): void
    {
        $pdo = $this->createPdoMock();
        $pos = new POS($pdo);

        $result = $pos->processSale([
            'cashier_id' => 1,
            'cart' => [['id' => 1, 'qty' => 1]],
            'paymentMethod' => 'Cash',
            'discountType' => 'coupon',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid discount type.', $result['message']);
    }

    public function testProcessSaleFailsWhenCartItemHasInvalidIdOrQty(): void
    {
        // productStmt is always prepared once up front, regardless of
        // whether the loop body ever reaches execute().
        $productStmt = $this->createStatementMock();
        $pdo = $this->pdoDispatching([
            'product_id, product_name, selling_price, product_status' => $productStmt,
        ]);

        $pos = new POS($pdo);
        $result = $pos->processSale([
            'cashier_id' => 1,
            'cart' => [['id' => 0, 'qty' => 1]],
            'paymentMethod' => 'Cash',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid product or quantity.', $result['message']);
    }

    public function testProcessSaleFailsWhenProductDoesNotExist(): void
    {
        $productStmt = $this->createStatementMock();
        $productStmt->method('fetch')->willReturn(false);

        $pdo = $this->pdoDispatching([
            'product_id, product_name, selling_price, product_status' => $productStmt,
        ]);

        $pos = new POS($pdo);
        $result = $pos->processSale([
            'cashier_id' => 1,
            'cart' => [['id' => 999, 'qty' => 1]],
            'paymentMethod' => 'Cash',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Product no longer exists.', $result['message']);
    }

    public function testProcessSaleFailsWhenSellingPriceIsZero(): void
    {
        $productStmt = $this->createStatementMock();
        $productStmt->method('fetch')->willReturn([
            'product_id' => 1,
            'product_name' => 'Free Sample',
            'selling_price' => '0.00',
            'product_status' => 'Available',
        ]);

        $pdo = $this->pdoDispatching([
            'product_id, product_name, selling_price, product_status' => $productStmt,
        ]);

        $pos = new POS($pdo);
        $result = $pos->processSale([
            'cashier_id' => 1,
            'cart' => [['id' => 1, 'qty' => 1]],
            'paymentMethod' => 'Cash',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Free Sample cannot be sold because it has no valid selling price.', $result['message']);
    }

    public function testProcessSaleFailsWhenBatchStockInsufficientAndRollsBack(): void
    {
        $productStmt = $this->createStatementMock();
        $productStmt->method('fetch')->willReturn([
            'product_id' => 1,
            'product_name' => 'Amoxicillin',
            'selling_price' => '10.00',
            'product_status' => 'Available',
        ]);

        $availableStmt = $this->createStatementMock();
        $availableStmt->method('fetchColumn')->willReturn(2); // only 2 in stock

        $pdo = $this->pdoDispatching([
            'product_id, product_name, selling_price, product_status' => $productStmt,
            'COALESCE(SUM(pb.quantity), 0)' => $availableStmt,
        ]);
        $pdo->method('inTransaction')->willReturn(true);
        $pdo->expects($this->once())->method('rollBack');
        $pdo->expects($this->never())->method('commit');

        $pos = new POS($pdo);
        $result = $pos->processSale([
            'cashier_id' => 1,
            'cart' => [['id' => 1, 'qty' => 5]], // requesting more than available
            'paymentMethod' => 'Cash',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame(
            'Insufficient sellable batch stock for Amoxicillin. Available: 2, requested: 5.',
            $result['message']
        );
    }

    public function testProcessSaleFailsWhenPercentDiscountExceeds100(): void
    {
        $pdo = $this->pdoForValidCartOf(1, 'Amoxicillin', 10.00, 5, 5);

        $pos = new POS($pdo);
        $result = $pos->processSale([
            'cashier_id' => 1,
            'cart' => [['id' => 1, 'qty' => 5]],
            'paymentMethod' => 'Cash',
            'discountType' => 'percent',
            'discountValue' => 150,
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Percentage discount cannot exceed 100%.', $result['message']);
    }

    public function testProcessSaleFailsWhenPesoDiscountExceedsSubtotal(): void
    {
        $pdo = $this->pdoForValidCartOf(1, 'Amoxicillin', 10.00, 2, 5);

        $pos = new POS($pdo);
        $result = $pos->processSale([
            'cashier_id' => 1,
            'cart' => [['id' => 1, 'qty' => 2]], // subtotal = 20.00
            'paymentMethod' => 'Cash',
            'discountType' => 'peso',
            'discountValue' => 50,
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Peso discount cannot exceed the subtotal.', $result['message']);
    }

    public function testProcessSaleFailsWhenCashIsInsufficient(): void
    {
        $pdo = $this->pdoForValidCartOf(1, 'Amoxicillin', 10.00, 2, 5);

        $pos = new POS($pdo);
        $result = $pos->processSale([
            'cashier_id' => 1,
            'cart' => [['id' => 1, 'qty' => 2]], // subtotal = total = 20.00
            'paymentMethod' => 'Cash',
            'cash' => 10, // not enough
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Insufficient cash.', $result['message']);
    }

    /**
     * Helper: builds a PDO mock that satisfies the product-lookup and
     * available-stock checks for a single cart line, so tests can exercise
     * logic that runs after the per-item loop (discount / cash checks)
     * without wiring the full insert chain.
     */
    private function pdoForValidCartOf(
        int $productId,
        string $productName,
        float $price,
        int $availableQty,
        float $reorderLevelUnused = 5
    ): PDO&MockObject {
        $productStmt = $this->createStatementMock();
        $productStmt->method('fetch')->willReturn([
            'product_id' => $productId,
            'product_name' => $productName,
            'selling_price' => (string) $price,
            'product_status' => 'Available',
        ]);

        $availableStmt = $this->createStatementMock();
        $availableStmt->method('fetchColumn')->willReturn($availableQty);

        return $this->pdoDispatching([
            'product_id, product_name, selling_price, product_status' => $productStmt,
            'COALESCE(SUM(pb.quantity), 0)' => $availableStmt,
        ]);
    }

    // -----------------------------------------------------------------
    // processSale() — full success paths (require the entire insert
    // chain to be mocked).
    // -----------------------------------------------------------------

    /**
     * Builds a PDO mock covering the complete processSale() happy path
     * for a cart of known-price items, each fulfilled from one or more
     * batches. The three trailing parameters are filled (by reference)
     * with the captured bound parameters of each sales/payments/sale_items
     * INSERT execute() call, in call order, so the caller can assert on
     * them after invoking processSale().
     */
    private function fullChainPdo(
        array $products,   // productId => ['name' => ..., 'price' => ..., 'available' => ..., 'batches' => [...]]
        array $lastInsertIds, // sequence of lastInsertId() return values
        array &$salesInsertCalls = [],
        array &$paymentsInsertCalls = [],
        array &$itemInsertCalls = []
    ): PDO&MockObject {
        $productRows = [];
        $availableQty = [];
        $batchesByProduct = [];
        foreach ($products as $productId => $info) {
            $productRows[$productId] = [
                'product_id' => $productId,
                'product_name' => $info['name'],
                'selling_price' => (string) $info['price'],
                'product_status' => 'Available',
            ];
            $availableQty[$productId] = $info['available'];
            $batchesByProduct[$productId] = $info['batches'];
        }

        $lastProductId = null;
        $productStmt = $this->createStatementMock();
        $productStmt->method('execute')->willReturnCallback(function ($params) use (&$lastProductId) {
            $lastProductId = $params[':product_id'];
            return true;
        });
        $productStmt->method('fetch')->willReturnCallback(function () use (&$lastProductId, $productRows) {
            return $productRows[$lastProductId] ?? false;
        });

        $lastAvailProductId = null;
        $availableStmt = $this->createStatementMock();
        $availableStmt->method('execute')->willReturnCallback(function ($params) use (&$lastAvailProductId) {
            $lastAvailProductId = $params[':product_id'];
            return true;
        });
        $availableStmt->method('fetchColumn')->willReturnCallback(function () use (&$lastAvailProductId, $availableQty) {
            return $availableQty[$lastAvailProductId] ?? 0;
        });

        $salesInsertCalls = [];
        $salesInsertStmt = $this->createStatementMock();
        $salesInsertStmt->method('execute')->willReturnCallback(function ($params) use (&$salesInsertCalls) {
            $salesInsertCalls[] = $params;
            return true;
        });

        $paymentsInsertCalls = [];
        $paymentsInsertStmt = $this->createStatementMock();
        $paymentsInsertStmt->method('execute')->willReturnCallback(function ($params) use (&$paymentsInsertCalls) {
            $paymentsInsertCalls[] = $params;
            return true;
        });

        $itemInsertCalls = [];
        $itemStmt = $this->createStatementMock();
        $itemStmt->method('execute')->willReturnCallback(function ($params) use (&$itemInsertCalls) {
            $itemInsertCalls[] = $params;
            return true;
        });

        $allocationStmt = $this->createStatementMock();

        $lastBatchProductId = null;
        $batchStmt = $this->createStatementMock();
        $batchStmt->method('execute')->willReturnCallback(function ($params) use (&$lastBatchProductId) {
            $lastBatchProductId = $params[':product_id'];
            return true;
        });
        $batchStmt->method('fetchAll')->willReturnCallback(function () use (&$lastBatchProductId, $batchesByProduct) {
            return $batchesByProduct[$lastBatchProductId] ?? [];
        });

        $batchUpdateStmt = $this->createStatementMock();
        $batchUpdateStmt->method('rowCount')->willReturn(1);

        $movementStmt = $this->createStatementMock();
        $aggregateSelectStmt = $this->createStatementMock();
        $reorderSelectStmt = $this->createStatementMock();
        $syncUpdateStmt = $this->createStatementMock();

        $pdo = $this->pdoDispatching([
            'product_id, product_name, selling_price, product_status' => $productStmt,
            'COALESCE(SUM(pb.quantity), 0)' => $availableStmt,
            'INSERT INTO sales' => $salesInsertStmt,
            'INSERT INTO payments' => $paymentsInsertStmt,
            'INSERT INTO sale_items' => $itemStmt,
            'INSERT INTO sale_item_batches' => $allocationStmt,
            'received_date' => $batchStmt,
            'UPDATE product_batches' => $batchUpdateStmt,
            'INSERT INTO inventory_movements' => $movementStmt,
            'COALESCE(SUM(CASE' => $aggregateSelectStmt,
            'SELECT reorder_level FROM products' => $reorderSelectStmt,
            'UPDATE products' => $syncUpdateStmt,
        ]);

        // beginTransaction()/commit()/rollBack() expectations are left to
        // the caller to configure, since success vs. failure scenarios
        // need opposite expectations (e.g. commit() must NOT be called on
        // a mid-transaction failure).
        // PDO::lastInsertId() is declared to return string|false.
        $pdo->method('lastInsertId')->willReturnOnConsecutiveCalls(...array_map('strval', $lastInsertIds));

        return $pdo;
    }

    public function testProcessSaleSucceedsAndSubtotalIsRoundedLikeTotal(): void
    {
        $salesInsertCalls = [];
        $paymentsInsertCalls = [];
        $itemInsertCalls = [];

        $pdo = $this->fullChainPdo([
            1 => [
                'name' => 'Item A',
                'price' => 0.10,
                'available' => 5,
                'batches' => [[
                    'batch_id' => 10,
                    'batch_number' => 'B10',
                    'expiration_date' => '2027-01-01',
                    'quantity' => 5,
                    'received_date' => '2025-01-01',
                ]],
            ],
            2 => [
                'name' => 'Item B',
                'price' => 0.20,
                'available' => 5,
                'batches' => [[
                    'batch_id' => 20,
                    'batch_number' => 'B20',
                    'expiration_date' => '2027-01-01',
                    'quantity' => 5,
                    'received_date' => '2025-01-01',
                ]],
            ],
        ], [501, 9001, 9002], $salesInsertCalls, $paymentsInsertCalls, $itemInsertCalls);
        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('commit');

        $pos = new POS($pdo);
        $result = $pos->processSale([
            'cashier_id' => 7,
            'cart' => [
                ['id' => 1, 'qty' => 1],
                ['id' => 2, 'qty' => 1],
            ],
            'paymentMethod' => 'Cash',
            'discountType' => 'percent',
            'discountValue' => 0,
            'cash' => 1.00,
        ]);

        $this->assertTrue($result['success']);

        // 0.1 + 0.2 is the textbook IEEE-754 example that does not add up
        // to exactly 0.3 in raw floating point — subtotal must be
        // round()-ed to 2 decimals just like discount_amount/total_amount,
        // so the noise never reaches the DB insert or the API response.
        $this->assertSame(0.3, $result['subtotal']);
        $this->assertSame(0.0, $result['discount_amount']);
        $this->assertSame(0.3, $result['total_amount']);

        // With a 0% discount, total should equal subtotal exactly, since
        // both are rounded the same way.
        $this->assertSame(
            $result['subtotal'],
            $result['total_amount'],
            'subtotal and total_amount should represent the same amount when discount is 0'
        );

        $this->assertSame(0.7, $result['change_amount']);
        $this->assertMatchesRegularExpression('/^TID-\d{14}-\d{3}$/', $result['transaction_number']);

        // The rounded subtotal (not the raw float-noisy sum) is what gets
        // bound into the INSERT ... sales statement too.
        $this->assertSame(0.3, $salesInsertCalls[0][':subtotal']);
        $this->assertSame(0.0, $salesInsertCalls[0][':discount']);
        $this->assertSame(0.3, $salesInsertCalls[0][':total']);
        $this->assertSame(7, $salesInsertCalls[0][':cashier_id']);

        $this->assertSame(0.3, $paymentsInsertCalls[0][':amount_due']);
        $this->assertSame(1.0, $paymentsInsertCalls[0][':amount_paid']);
        $this->assertSame(0.7, $paymentsInsertCalls[0][':change_amount']);
        $this->assertNull($paymentsInsertCalls[0][':reference_number']);
        $this->assertSame('Cash', $paymentsInsertCalls[0][':payment_method']);

        $this->assertCount(2, $itemInsertCalls);
        $this->assertSame(0.1, $itemInsertCalls[0][':unit_price']);
        $this->assertSame(0.1, $itemInsertCalls[0][':subtotal']); // per-item subtotal IS rounded
        $this->assertSame(0.2, $itemInsertCalls[1][':unit_price']);
        $this->assertSame(0.2, $itemInsertCalls[1][':subtotal']);
    }

    public function testProcessSaleAllocatesAcrossMultipleBatchesInFefoOrder(): void
    {
        // This scenario needs bespoke per-statement call capturing
        // (batch update / allocation / movement calls), so it builds its
        // own PDO mock directly rather than using fullChainPdo().
        $batchUpdateCalls = [];
        $movementCalls = [];
        $allocationCalls = [];

        $productStmt = $this->createStatementMock();
        $productStmt->method('fetch')->willReturn([
            'product_id' => 1,
            'product_name' => 'Amoxicillin',
            'selling_price' => '5.00',
            'product_status' => 'Available',
        ]);

        $availableStmt = $this->createStatementMock();
        $availableStmt->method('fetchColumn')->willReturn(20);

        $salesInsertStmt = $this->createStatementMock();
        $paymentsInsertStmt = $this->createStatementMock();
        $itemStmt = $this->createStatementMock();

        $allocationStmt = $this->createStatementMock();
        $allocationStmt->method('execute')->willReturnCallback(function ($params) use (&$allocationCalls) {
            $allocationCalls[] = $params;
            return true;
        });

        $batchStmt = $this->createStatementMock();
        $batchStmt->method('fetchAll')->willReturn([
            [
                'batch_id' => 100,
                'batch_number' => 'EARLY',
                'expiration_date' => '2026-01-01',
                'quantity' => 10,
                'received_date' => '2025-01-01',
            ],
            [
                'batch_id' => 200,
                'batch_number' => 'LATER',
                'expiration_date' => '2026-06-01',
                'quantity' => 10,
                'received_date' => '2025-02-01',
            ],
        ]);

        $batchUpdateStmt = $this->createStatementMock();
        $batchUpdateStmt->method('rowCount')->willReturn(1);
        $batchUpdateStmt->method('execute')->willReturnCallback(function ($params) use (&$batchUpdateCalls) {
            $batchUpdateCalls[] = $params;
            return true;
        });

        $movementStmt = $this->createStatementMock();
        $movementStmt->method('execute')->willReturnCallback(function ($params) use (&$movementCalls) {
            $movementCalls[] = $params;
            return true;
        });

        $aggregateSelectStmt = $this->createStatementMock();
        $reorderSelectStmt = $this->createStatementMock();
        $syncUpdateStmt = $this->createStatementMock();

        $pdo = $this->pdoDispatching([
            'product_id, product_name, selling_price, product_status' => $productStmt,
            'COALESCE(SUM(pb.quantity), 0)' => $availableStmt,
            'INSERT INTO sales' => $salesInsertStmt,
            'INSERT INTO payments' => $paymentsInsertStmt,
            'INSERT INTO sale_items' => $itemStmt,
            'INSERT INTO sale_item_batches' => $allocationStmt,
            'received_date' => $batchStmt,
            'UPDATE product_batches' => $batchUpdateStmt,
            'INSERT INTO inventory_movements' => $movementStmt,
            'COALESCE(SUM(CASE' => $aggregateSelectStmt,
            'SELECT reorder_level FROM products' => $reorderSelectStmt,
            'UPDATE products' => $syncUpdateStmt,
        ]);
        $pdo->method('lastInsertId')->willReturnOnConsecutiveCalls('300', '777');

        $pos = new POS($pdo);
        $result = $pos->processSale([
            'cashier_id' => 1,
            'cart' => [['id' => 1, 'qty' => 15]], // needs both batches (10 + 5)
            'paymentMethod' => 'Cash',
            'cash' => 100,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(75.0, $result['total_amount']); // 15 * 5.00

        $this->assertCount(2, $batchUpdateCalls);
        $this->assertSame(0, $batchUpdateCalls[0][':quantity']); // batch 100 fully drained
        $this->assertSame(100, $batchUpdateCalls[0][':batch_id']);
        $this->assertSame(5, $batchUpdateCalls[1][':quantity']); // batch 200 partially drained
        $this->assertSame(200, $batchUpdateCalls[1][':batch_id']);

        $this->assertCount(2, $allocationCalls);
        $this->assertSame(10, $allocationCalls[0][':quantity']);
        $this->assertSame(100, $allocationCalls[0][':batch_id']);
        $this->assertSame(5, $allocationCalls[1][':quantity']);
        $this->assertSame(200, $allocationCalls[1][':batch_id']);

        $this->assertCount(2, $movementCalls);
        $this->assertSame(-10, $movementCalls[0][':quantity_changed']);
        $this->assertSame(10, $movementCalls[0][':previous_stock']);
        $this->assertSame(0, $movementCalls[0][':new_stock']);
        $this->assertSame(-5, $movementCalls[1][':quantity_changed']);
        $this->assertSame(10, $movementCalls[1][':previous_stock']);
        $this->assertSame(5, $movementCalls[1][':new_stock']);
    }

    public function testProcessSaleFailsWhenFefoAllocationCannotCoverRequestedQty(): void
    {
        // The "available" SUM check says there's enough stock, but the
        // FOR UPDATE batch listing comes back empty (e.g. a concurrent
        // sale consumed the batches in between the two reads). This must
        // be treated as a failure, not silently short-ship the sale.
        $pdo = $this->fullChainPdo([
            1 => [
                'name' => 'Amoxicillin',
                'price' => 10.00,
                'available' => 10,
                'batches' => [], // nothing left when we actually try to allocate
            ],
        ], [400, 888]);

        $pdo->method('inTransaction')->willReturn(true);
        $pdo->expects($this->once())->method('rollBack');
        $pdo->expects($this->never())->method('commit');

        $pos = new POS($pdo);
        $result = $pos->processSale([
            'cashier_id' => 1,
            'cart' => [['id' => 1, 'qty' => 10]],
            'paymentMethod' => 'Cash',
            'cash' => 200,
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame(
            'Unable to allocate complete FEFO stock for Amoxicillin.',
            $result['message']
        );
    }

    public function testProcessSaleFailsWhenBatchUpdateAffectsNoRows(): void
    {
        // Simulates another process having modified/consumed the same
        // batch row concurrently: the UPDATE matches zero rows. This
        // needs a bespoke rowCount() override, so it builds its own PDO
        // mock directly rather than using fullChainPdo().
        $productStmt = $this->createStatementMock();
        $productStmt->method('fetch')->willReturn([
            'product_id' => 1,
            'product_name' => 'Amoxicillin',
            'selling_price' => '10.00',
            'product_status' => 'Available',
        ]);
        $availableStmt = $this->createStatementMock();
        $availableStmt->method('fetchColumn')->willReturn(5);
        $salesInsertStmt = $this->createStatementMock();
        $paymentsInsertStmt = $this->createStatementMock();
        $itemStmt = $this->createStatementMock();
        $allocationStmt = $this->createStatementMock();
        $batchStmt = $this->createStatementMock();
        $batchStmt->method('fetchAll')->willReturn([[
            'batch_id' => 55,
            'batch_number' => 'B55',
            'expiration_date' => '2027-01-01',
            'quantity' => 5,
            'received_date' => '2025-01-01',
        ]]);
        $batchUpdateStmt = $this->createStatementMock();
        $batchUpdateStmt->method('rowCount')->willReturn(0); // <- race condition
        $movementStmt = $this->createStatementMock();
        $aggregateSelectStmt = $this->createStatementMock();
        $reorderSelectStmt = $this->createStatementMock();
        $syncUpdateStmt = $this->createStatementMock();

        $pdo = $this->pdoDispatching([
            'product_id, product_name, selling_price, product_status' => $productStmt,
            'COALESCE(SUM(pb.quantity), 0)' => $availableStmt,
            'INSERT INTO sales' => $salesInsertStmt,
            'INSERT INTO payments' => $paymentsInsertStmt,
            'INSERT INTO sale_items' => $itemStmt,
            'INSERT INTO sale_item_batches' => $allocationStmt,
            'received_date' => $batchStmt,
            'UPDATE product_batches' => $batchUpdateStmt,
            'INSERT INTO inventory_movements' => $movementStmt,
            'COALESCE(SUM(CASE' => $aggregateSelectStmt,
            'SELECT reorder_level FROM products' => $reorderSelectStmt,
            'UPDATE products' => $syncUpdateStmt,
        ]);
        $pdo->method('lastInsertId')->willReturnOnConsecutiveCalls('401', '889');
        $pdo->method('inTransaction')->willReturn(true);
        $pdo->expects($this->once())->method('rollBack');

        $pos = new POS($pdo);
        $result = $pos->processSale([
            'cashier_id' => 1,
            'cart' => [['id' => 1, 'qty' => 5]],
            'paymentMethod' => 'Cash',
            'cash' => 100,
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Failed to update batch stock for Amoxicillin.', $result['message']);
    }

    public function testProcessSaleWith100PercentDiscountResultsInZeroTotal(): void
    {
        $salesInsertCalls = [];

        $pdo = $this->fullChainPdo([
            1 => [
                'name' => 'Freebie',
                'price' => 25.00,
                'available' => 5,
                'batches' => [[
                    'batch_id' => 1,
                    'batch_number' => 'B1',
                    'expiration_date' => '2027-01-01',
                    'quantity' => 5,
                    'received_date' => '2025-01-01',
                ]],
            ],
        ], [600, 1000], $salesInsertCalls);

        $pos = new POS($pdo);
        $result = $pos->processSale([
            'cashier_id' => 1,
            'cart' => [['id' => 1, 'qty' => 1]],
            'paymentMethod' => 'Cash',
            'discountType' => 'percent',
            'discountValue' => 100,
            'cash' => 0,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(25.0, $result['subtotal']);
        $this->assertSame(25.0, $result['discount_amount']);
        $this->assertSame(0.0, $result['total_amount']);
        $this->assertSame(0.0, $result['change_amount']);
        $this->assertSame(25.0, $salesInsertCalls[0][':discount']);
    }

    public function testProcessSaleWithElectronicPaymentDoesNotRequireCashAndHasZeroChange(): void
    {
        $salesInsertCalls = [];
        $paymentsInsertCalls = [];

        $pdo = $this->fullChainPdo([
            1 => [
                'name' => 'Amoxicillin',
                'price' => 10.00,
                'available' => 5,
                'batches' => [[
                    'batch_id' => 1,
                    'batch_number' => 'B1',
                    'expiration_date' => '2027-01-01',
                    'quantity' => 5,
                    'received_date' => '2025-01-01',
                ]],
            ],
        ], [700, 1100], $salesInsertCalls, $paymentsInsertCalls);

        $pos = new POS($pdo);
        $result = $pos->processSale([
            'cashier_id' => 1,
            'cart' => [['id' => 1, 'qty' => 2]],
            'paymentMethod' => 'GCash',
            'reference' => 'GC-12345',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(20.0, $result['total_amount']);
        $this->assertSame(0.00, $result['change_amount']);
        $this->assertSame('GC-12345', $paymentsInsertCalls[0][':reference_number']);
        $this->assertSame(20.0, $paymentsInsertCalls[0][':amount_paid']);
    }
}
