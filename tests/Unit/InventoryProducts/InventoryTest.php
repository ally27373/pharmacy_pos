<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/Models/Inventory.php';
require_once __DIR__ . '/../../Support/MocksPdo.php';
require_once __DIR__ . '/Support/PdoDispatch.php';

use PHPUnit\Framework\TestCase;

final class InventoryTest extends TestCase
{
    use MocksPdo;
    use PdoDispatch;

    // ------------------------------------------------------------------
    // getProducts() — pagination math and WHERE-clause construction
    // ------------------------------------------------------------------

    public function testGetProductsClampsLimitAboveHundredToHundred(): void
    {
        $countStmt = $this->createStatementMock();
        $countStmt->method('bindValue')->willReturn(true);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(0);

        $mainStmt = $this->createStatementMock();
        $mainStmt->method('bindValue')->willReturn(true);
        $mainStmt->method('execute')->willReturn(true);
        $mainStmt->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoDispatching([
            'SELECT COUNT(*)' => $countStmt,
            'LEFT JOIN suppliers s' => $mainStmt,
        ]);

        $inventory = new Inventory($pdo);
        $result = $inventory->getProducts(1, 500);

        self::assertSame(100, $result['pagination']['limit']);
    }

    public function testGetProductsClampsLimitBelowOneToOne(): void
    {
        $countStmt = $this->createStatementMock();
        $countStmt->method('bindValue')->willReturn(true);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(0);

        $mainStmt = $this->createStatementMock();
        $mainStmt->method('bindValue')->willReturn(true);
        $mainStmt->method('execute')->willReturn(true);
        $mainStmt->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoDispatching([
            'SELECT COUNT(*)' => $countStmt,
            'LEFT JOIN suppliers s' => $mainStmt,
        ]);

        $inventory = new Inventory($pdo);

        $result = $inventory->getProducts(1, 0);
        self::assertSame(1, $result['pagination']['limit']);
    }

    public function testGetProductsClampsPageBelowOneToOne(): void
    {
        $countStmt = $this->createStatementMock();
        $countStmt->method('bindValue')->willReturn(true);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(0);

        $mainStmt = $this->createStatementMock();
        $mainStmt->method('bindValue')->willReturn(true);
        $mainStmt->method('execute')->willReturn(true);
        $mainStmt->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoDispatching([
            'SELECT COUNT(*)' => $countStmt,
            'LEFT JOIN suppliers s' => $mainStmt,
        ]);

        $inventory = new Inventory($pdo);

        $result = $inventory->getProducts(-5, 20);
        self::assertSame(1, $result['pagination']['page']);
    }

    public function testGetProductsClampsRequestedPageDownToLastPageWhenBeyondTotal(): void
    {
        // total = 5 rows, limit = 2 -> totalPages = 3. Requesting page 10
        // must clamp to page 3, and the OFFSET bound to the main query must
        // reflect that clamped page, not the raw requested one.
        $countStmt = $this->createStatementMock();
        $countStmt->method('bindValue')->willReturn(true);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(5);

        $mainStmt = $this->createStatementMock();
        $mainStmt->expects(self::atLeastOnce())
            ->method('bindValue')
            ->willReturnCallback(function (string $param, $value) {
                if ($param === ':offset') {
                    self::assertSame(4, $value, 'offset should be based on the clamped page (3-1)*2=4');
                }
                return true;
            });
        $mainStmt->method('execute')->willReturn(true);
        $mainStmt->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoDispatching([
            'SELECT COUNT(*)' => $countStmt,
            'LEFT JOIN suppliers s' => $mainStmt,
        ]);

        $inventory = new Inventory($pdo);
        $result = $inventory->getProducts(10, 2);

        self::assertSame(3, $result['pagination']['page']);
        self::assertSame(3, $result['pagination']['total_pages']);
        self::assertFalse($result['pagination']['has_next']);
        self::assertTrue($result['pagination']['has_previous']);
    }

    public function testGetProductsWithZeroResultsHasZeroTotalPagesAndNoNext(): void
    {
        $countStmt = $this->createStatementMock();
        $countStmt->method('bindValue')->willReturn(true);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(0);

        $mainStmt = $this->createStatementMock();
        $mainStmt->method('bindValue')->willReturn(true);
        $mainStmt->method('execute')->willReturn(true);
        $mainStmt->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoDispatching([
            'SELECT COUNT(*)' => $countStmt,
            'LEFT JOIN suppliers s' => $mainStmt,
        ]);

        $inventory = new Inventory($pdo);
        $result = $inventory->getProducts(1, 20);

        self::assertSame(0, $result['pagination']['total_pages']);
        self::assertFalse($result['pagination']['has_next']);
        self::assertFalse($result['pagination']['has_previous']);
        self::assertSame([], $result['products']);
    }

    public function testGetProductsBuildsWhereClauseForSearchCategoryAndType(): void
    {
        $countStmt = $this->createStatementMock();
        $countStmt->method('bindValue')->willReturn(true);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(0);

        $mainStmt = $this->createStatementMock();
        $mainStmt->method('bindValue')->willReturn(true);
        $mainStmt->method('execute')->willReturn(true);
        $mainStmt->method('fetchAll')->willReturn([]);

        $capturedSql = [];
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(
            function (string $sql) use (&$capturedSql, $countStmt, $mainStmt) {
                $capturedSql[] = $sql;
                return str_contains($sql, 'LEFT JOIN suppliers s') ? $mainStmt : $countStmt;
            }
        );

        $inventory = new Inventory($pdo);
        $inventory->getProducts(1, 20, 'amoxi', 'Antibiotics', 'Tablet');

        self::assertCount(2, $capturedSql);
        foreach ($capturedSql as $sql) {
            self::assertStringContainsString('p.product_name LIKE :search_product', $sql);
            self::assertStringContainsString('c.category_name = :category', $sql);
            self::assertStringContainsString('pt.type_name = :type', $sql);
        }
    }

    public function testGetProductsWithNoFiltersOmitsWhereClause(): void
    {
        $countStmt = $this->createStatementMock();
        $countStmt->method('bindValue')->willReturn(true);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(0);

        $mainStmt = $this->createStatementMock();
        $mainStmt->method('bindValue')->willReturn(true);
        $mainStmt->method('execute')->willReturn(true);
        $mainStmt->method('fetchAll')->willReturn([]);

        $capturedSql = [];
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(
            function (string $sql) use (&$capturedSql, $countStmt, $mainStmt) {
                $capturedSql[] = $sql;
                return str_contains($sql, 'LEFT JOIN suppliers s') ? $mainStmt : $countStmt;
            }
        );

        $inventory = new Inventory($pdo);
        $inventory->getProducts();

        foreach ($capturedSql as $sql) {
            self::assertStringNotContainsString('WHERE', $sql);
        }
    }

    // ------------------------------------------------------------------
    // getProductDetails() / getProductBatches() / lookups
    // ------------------------------------------------------------------

    public function testGetProductDetailsReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->pdoThatPrepares($stmt);

        $inventory = new Inventory($pdo);
        self::assertNull($inventory->getProductDetails(999));
    }

    public function testGetProductDetailsReturnsRowWhenFound(): void
    {
        $row = ['product_id' => 5, 'product_name' => 'Paracetamol 500mg'];

        $stmt = $this->createStatementMock();
        $stmt->expects(self::once())->method('execute')->with([':id' => 5])->willReturn(true);
        $stmt->method('fetch')->willReturn($row);

        $pdo = $this->pdoThatPrepares($stmt);

        $inventory = new Inventory($pdo);
        self::assertSame($row, $inventory->getProductDetails('5'));
    }

    public function testGetProductBatchesOrdersByExpirationForFefo(): void
    {
        $rows = [
            ['batch_id' => 1, 'expiration_date' => '2026-01-01'],
            ['batch_id' => 2, 'expiration_date' => '2026-06-01'],
        ];

        $stmt = $this->createStatementMock();
        $stmt->expects(self::once())->method('execute')->with([':product_id' => 7])->willReturn(true);
        $stmt->method('fetchAll')->willReturn($rows);

        $capturedSql = null;
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
            $capturedSql = $sql;
            return $stmt;
        });

        $inventory = new Inventory($pdo);
        $result = $inventory->getProductBatches(7);

        self::assertSame($rows, $result);
        self::assertStringContainsString(
            'ORDER BY expiration_date IS NULL ASC, expiration_date ASC, batch_id ASC',
            $capturedSql
        );
    }

    public function testGetCategoriesReturnsQueryResult(): void
    {
        $rows = [['category_id' => 1, 'category_name' => 'Antibiotics']];

        $stmt = $this->createStatementMock();
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createPdoMock();
        $pdo->method('query')->willReturn($stmt);

        $inventory = new Inventory($pdo);
        self::assertSame($rows, $inventory->getCategories());
    }

    public function testGetTypesReturnsQueryResult(): void
    {
        $rows = [['type_id' => 1, 'type_name' => 'Tablet']];

        $stmt = $this->createStatementMock();
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createPdoMock();
        $pdo->method('query')->willReturn($stmt);

        $inventory = new Inventory($pdo);
        self::assertSame($rows, $inventory->getTypes());
    }

    public function testGetSuppliersReturnsQueryResult(): void
    {
        $rows = [['supplier_id' => 1, 'supplier_name' => 'Acme Pharma']];

        $stmt = $this->createStatementMock();
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createPdoMock();
        $pdo->method('query')->willReturn($stmt);

        $inventory = new Inventory($pdo);
        self::assertSame($rows, $inventory->getSuppliers());
    }

    // ------------------------------------------------------------------
    // resolveSupplierId()
    // ------------------------------------------------------------------

    public function testResolveSupplierIdReturnsGivenIdWhenNameEmpty(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('prepare');

        $inventory = new Inventory($pdo);
        self::assertSame(5, $inventory->resolveSupplierId('', 5));
        self::assertSame(5, $inventory->resolveSupplierId(null, '5'));
    }

    public function testResolveSupplierIdReturnsNullWhenNameEmptyAndIdMissing(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('prepare');

        $inventory = new Inventory($pdo);
        self::assertNull($inventory->resolveSupplierId('', 0));
        self::assertNull($inventory->resolveSupplierId(null, null));
    }

    public function testResolveSupplierIdReturnsExistingIdWhenNameMatches(): void
    {
        $find = $this->createStatementMock();
        $find->expects(self::once())->method('execute')->with([':name' => 'Acme Pharma'])->willReturn(true);
        $find->method('fetchColumn')->willReturn('7');

        $pdo = $this->pdoDispatching([
            'LOWER(TRIM(supplier_name))' => $find,
        ]);

        $inventory = new Inventory($pdo);
        self::assertSame(7, $inventory->resolveSupplierId('Acme Pharma', null));
    }

    public function testResolveSupplierIdInsertsNewSupplierWhenNotFound(): void
    {
        $find = $this->createStatementMock();
        $find->method('execute')->willReturn(true);
        $find->method('fetchColumn')->willReturn(false);

        $insert = $this->createStatementMock();
        $insert->expects(self::once())->method('execute')->with([':name' => 'New Supplier Co'])->willReturn(true);

        $pdo = $this->pdoDispatching([
            'LOWER(TRIM(supplier_name))' => $find,
            'INSERT INTO suppliers' => $insert,
        ]);
        $pdo->method('lastInsertId')->willReturn('9');

        $inventory = new Inventory($pdo);
        self::assertSame(9, $inventory->resolveSupplierId('New Supplier Co', null));
    }

    // ------------------------------------------------------------------
    // saveProduct() — validation short-circuits (no DB calls expected)
    // ------------------------------------------------------------------

    public function testSaveProductRejectsMissingProductName(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('prepare');
        $pdo->expects(self::never())->method('beginTransaction');

        $inventory = new Inventory($pdo);
        $result = $inventory->saveProduct([
            'product_name' => '',
            'category_id' => 1,
            'type_id' => 1,
        ]);

        self::assertFalse($result['success']);
        self::assertSame('Please complete the required product fields.', $result['message']);
    }

    public function testSaveProductRejectsMissingCategory(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('prepare');

        $inventory = new Inventory($pdo);
        $result = $inventory->saveProduct([
            'product_name' => 'Amoxicillin',
            'category_id' => 0,
            'type_id' => 1,
        ]);

        self::assertFalse($result['success']);
        self::assertSame('Please complete the required product fields.', $result['message']);
    }

    public function testSaveProductRejectsMissingType(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('prepare');

        $inventory = new Inventory($pdo);
        $result = $inventory->saveProduct([
            'product_name' => 'Amoxicillin',
            'category_id' => 1,
            'type_id' => 0,
        ]);

        self::assertFalse($result['success']);
        self::assertSame('Please complete the required product fields.', $result['message']);
    }

    public function testSaveProductRejectsDuplicateBarcode(): void
    {
        $check = $this->createStatementMock();
        $check->method('execute')->willReturn(true);
        $check->method('fetchColumn')->willReturn('3'); // an existing product_id

        $pdo = $this->pdoDispatching([
            'WHERE barcode = :barcode' => $check,
        ]);
        $pdo->expects(self::never())->method('beginTransaction');

        $inventory = new Inventory($pdo);
        $result = $inventory->saveProduct([
            'product_name' => 'Amoxicillin',
            'category_id' => 1,
            'type_id' => 1,
            'barcode' => '12345',
        ]);

        self::assertFalse($result['success']);
        self::assertSame('This barcode is already used by another product.', $result['message']);
    }

    public function testSaveProductRequiresBatchNumberWhenInitialQuantityPositive(): void
    {
        $pdo = $this->createMock(PDO::class);
        // barcode is blank, so barcodeExists() is never even queried.
        $pdo->expects(self::never())->method('prepare');
        $pdo->expects(self::never())->method('beginTransaction');

        $inventory = new Inventory($pdo);
        $result = $inventory->saveProduct([
            'product_name' => 'Amoxicillin',
            'category_id' => 1,
            'type_id' => 1,
            'quantity' => 5,
            'batch_number' => '',
        ]);

        self::assertFalse($result['success']);
        self::assertSame('Batch number is required when initial stock is greater than zero.', $result['message']);
    }

    public function testSaveProductRejectsUnparsableExpirationDate(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('beginTransaction');

        $inventory = new Inventory($pdo);
        $result = $inventory->saveProduct([
            'product_name' => 'Amoxicillin',
            'category_id' => 1,
            'type_id' => 1,
            'quantity' => 0,
            'expiration_date' => 'not-a-real-date',
        ]);

        self::assertFalse($result['success']);
        self::assertSame('Invalid expiration date.', $result['message']);
    }

    public function testSaveProductClampsNegativeQuantityToZero(): void
    {
        // A negative initial quantity must not be treated as "> 0" (which
        // would demand a batch number) nor stored as a negative stock level.
        $barcodeCheck = $this->createStatementMock();
        $barcodeCheck->method('execute')->willReturn(true);
        $barcodeCheck->method('fetchColumn')->willReturn(false);

        $insert = $this->createStatementMock();
        $insert->method('execute')->willReturn(true);

        $aggregate = $this->createStatementMock();
        $aggregate->method('execute')->willReturn(true);
        $aggregate->method('fetch')->willReturn([
            'total_quantity' => 0,
            'nearest_expiration' => null,
            'nearest_batch' => null,
            'latest_unit_cost' => null,
        ]);

        $finalUpdate = $this->createStatementMock();
        $finalUpdate->expects(self::once())
            ->method('execute')
            ->with(self::callback(function (array $params) {
                self::assertSame(0, $params[':quantity']);
                self::assertSame('Out of Stock', $params[':product_status']);
                return true;
            }))
            ->willReturn(true);

        $pdo = $this->pdoDispatching([
            'WHERE barcode = :barcode' => $barcodeCheck,
            'INSERT INTO products (' => $insert,
            'AS total_quantity' => $aggregate,
            'product_status = :product_status' => $finalUpdate,
        ]);
        $pdo->method('lastInsertId')->willReturn('11');

        $inventory = new Inventory($pdo);
        $result = $inventory->saveProduct([
            'product_name' => 'Amoxicillin',
            'category_id' => 1,
            'type_id' => 1,
            'quantity' => -10,
            'barcode' => '999',
        ]);

        self::assertTrue($result['success']);
    }

    public function testSaveProductHappyPathInsertsBatchSyncsAggregateAndLogsHistory(): void
    {
        $barcodeCheck = $this->createStatementMock();
        $barcodeCheck->method('execute')->willReturn(true);
        $barcodeCheck->method('fetchColumn')->willReturn(false);

        $supplierFind = $this->createStatementMock();
        $supplierFind->method('execute')->willReturn(true);
        $supplierFind->method('fetchColumn')->willReturn('2');

        $productInsert = $this->createStatementMock();
        $productInsert->expects(self::once())->method('execute')->willReturn(true);

        $batchFind = $this->createStatementMock();
        $batchFind->method('execute')->willReturn(true);
        $batchFind->method('fetch')->willReturn(false); // no existing batch

        $batchInsert = $this->createStatementMock();
        $batchInsert->expects(self::once())
            ->method('execute')
            ->with(self::callback(function (array $params) {
                self::assertSame(10, $params[':quantity']);
                return true;
            }))
            ->willReturn(true);

        $aggregate = $this->createStatementMock();
        $aggregate->method('execute')->willReturn(true);
        $aggregate->method('fetch')->willReturn([
            'total_quantity' => 10,
            'nearest_expiration' => '2027-01-01',
            'nearest_batch' => 'B100',
            'latest_unit_cost' => 5.5,
        ]);

        $reorder = $this->createStatementMock();
        $reorder->method('execute')->willReturn(true);
        $reorder->method('fetchColumn')->willReturn(5);

        $finalUpdate = $this->createStatementMock();
        $finalUpdate->expects(self::once())
            ->method('execute')
            ->with(self::callback(function (array $params) {
                self::assertSame(10, $params[':quantity']);
                self::assertSame('Available', $params[':product_status']);
                return true;
            }))
            ->willReturn(true);

        $historyInsert = $this->createStatementMock();
        $historyInsert->expects(self::once())
            ->method('execute')
            ->with(self::callback(function (array $params) {
                self::assertSame(10, $params[':quantity']);
                return true;
            }))
            ->willReturn(true);

        $pdo = $this->pdoDispatching([
            'WHERE barcode = :barcode' => $barcodeCheck,
            'LOWER(TRIM(supplier_name))' => $supplierFind,
            'INSERT INTO products (' => $productInsert,
            'AND batch_number = :batch_number' => $batchFind,
            'INSERT INTO product_batches (' => $batchInsert,
            "VALUES (:product_id, 'IN', :quantity, :remarks)" => $historyInsert,
            'AS total_quantity' => $aggregate,
            'SELECT reorder_level' => $reorder,
            'product_status = :product_status' => $finalUpdate,
        ]);
        $pdo->method('lastInsertId')->willReturn('55');

        $inventory = new Inventory($pdo);
        $result = $inventory->saveProduct([
            'product_name' => 'Amoxicillin 500mg',
            'category_id' => 1,
            'type_id' => 1,
            'quantity' => 10,
            'batch_number' => 'B100',
            'expiration_date' => '2027-01-01',
            'barcode' => '999888777',
            'supplier_name' => 'Acme Pharma',
        ]);

        self::assertTrue($result['success']);
        self::assertSame(55, $result['product_id']);
        self::assertSame('999888777', $result['barcode']);
    }

    public function testSaveProductGeneratesFallbackBarcodeWhenBlank(): void
    {
        $productInsert = $this->createStatementMock();
        $productInsert->method('execute')->willReturn(true);

        $fallbackUpdate = $this->createStatementMock();
        $fallbackUpdate->expects(self::once())
            ->method('execute')
            ->with([':barcode' => 'INT-000042', ':product_id' => 42])
            ->willReturn(true);

        $aggregate = $this->createStatementMock();
        $aggregate->method('execute')->willReturn(true);
        $aggregate->method('fetch')->willReturn([
            'total_quantity' => 0,
            'nearest_expiration' => null,
            'nearest_batch' => null,
            'latest_unit_cost' => null,
        ]);

        $finalUpdate = $this->createStatementMock();
        $finalUpdate->method('execute')->willReturn(true);

        $pdo = $this->pdoDispatching([
            'INSERT INTO products (' => $productInsert,
            'SET barcode = :barcode WHERE product_id' => $fallbackUpdate,
            'AS total_quantity' => $aggregate,
            'product_status = :product_status' => $finalUpdate,
        ]);
        $pdo->method('lastInsertId')->willReturn('42');

        $inventory = new Inventory($pdo);
        $result = $inventory->saveProduct([
            'product_name' => 'Amoxicillin',
            'category_id' => 1,
            'type_id' => 1,
            'quantity' => 0,
            'barcode' => '',
        ]);

        self::assertTrue($result['success']);
        self::assertSame('INT-000042', $result['barcode']);
    }

    public function testSaveProductRollsBackAndReturnsFailureOnUnexpectedException(): void
    {
        $barcodeCheck = $this->createStatementMock();
        $barcodeCheck->method('execute')->willReturn(true);
        $barcodeCheck->method('fetchColumn')->willReturn(false);

        $pdo = $this->pdoDispatching([
            'WHERE barcode = :barcode' => $barcodeCheck,
        ]);
        // Force resolveSupplierId's lookup to blow up once inside the transaction.
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($barcodeCheck) {
            if (str_contains($sql, 'WHERE barcode = :barcode')) {
                return $barcodeCheck;
            }
            throw new RuntimeException('unexpected prepare: ' . $sql);
        });
        $pdo->expects(self::once())->method('rollBack');

        $inventory = new Inventory($pdo);
        $result = $inventory->saveProduct([
            'product_name' => 'Amoxicillin',
            'category_id' => 1,
            'type_id' => 1,
            'quantity' => 0,
            'barcode' => '999',
            'supplier_name' => 'Acme Pharma',
        ]);

        self::assertFalse($result['success']);
    }

    // ------------------------------------------------------------------
    // adjustStock() — validation and quantity math
    // ------------------------------------------------------------------

    public function testAdjustStockRejectsInvalidProductId(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('prepare');

        $inventory = new Inventory($pdo);
        $result = $inventory->adjustStock(['product_id' => 0, 'action' => 'IN', 'quantity' => 1]);

        self::assertFalse($result['success']);
        self::assertSame('Invalid product.', $result['message']);
    }

    public function testAdjustStockRejectsInvalidAction(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('prepare');

        $inventory = new Inventory($pdo);
        $result = $inventory->adjustStock(['product_id' => 1, 'action' => 'SIDEWAYS', 'quantity' => 1]);

        self::assertFalse($result['success']);
        self::assertSame('Invalid stock action.', $result['message']);
    }

    public function testAdjustStockRejectsZeroQuantity(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('prepare');

        $inventory = new Inventory($pdo);
        $result = $inventory->adjustStock(['product_id' => 1, 'action' => 'IN', 'quantity' => 0]);

        self::assertFalse($result['success']);
        self::assertSame('Quantity must be greater than zero.', $result['message']);
    }

    public function testAdjustStockRejectsNegativeQuantity(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('prepare');

        $inventory = new Inventory($pdo);
        $result = $inventory->adjustStock(['product_id' => 1, 'action' => 'OUT', 'quantity' => -5]);

        self::assertFalse($result['success']);
        self::assertSame('Quantity must be greater than zero.', $result['message']);
    }

    public function testAdjustStockFailsWhenProductNotFound(): void
    {
        $productSelect = $this->createStatementMock();
        $productSelect->method('execute')->willReturn(true);
        $productSelect->method('fetch')->willReturn(false);

        $pdo = $this->pdoDispatching([
            'SELECT product_id, unit_cost FROM products' => $productSelect,
        ]);
        $pdo->expects(self::once())->method('rollBack');

        $inventory = new Inventory($pdo);
        $result = $inventory->adjustStock(['product_id' => 999, 'action' => 'IN', 'quantity' => 1]);

        self::assertFalse($result['success']);
        self::assertSame('Product not found.', $result['message']);
    }

    public function testAdjustStockOutFailsWhenNoBatchSelected(): void
    {
        $productSelect = $this->createStatementMock();
        $productSelect->method('execute')->willReturn(true);
        $productSelect->method('fetch')->willReturn(['product_id' => 1, 'unit_cost' => 2.0]);

        $pdo = $this->pdoDispatching([
            'SELECT product_id, unit_cost FROM products' => $productSelect,
        ]);
        $pdo->expects(self::once())->method('rollBack');

        $inventory = new Inventory($pdo);
        $result = $inventory->adjustStock(['product_id' => 1, 'action' => 'OUT', 'quantity' => 5, 'batch_id' => 0]);

        self::assertFalse($result['success']);
        self::assertSame('Select a batch for Stock Out.', $result['message']);
    }

    public function testAdjustStockOutFailsWhenSelectedBatchNotFound(): void
    {
        $productSelect = $this->createStatementMock();
        $productSelect->method('execute')->willReturn(true);
        $productSelect->method('fetch')->willReturn(['product_id' => 1, 'unit_cost' => 2.0]);

        $batchLock = $this->createStatementMock();
        $batchLock->method('execute')->willReturn(true);
        $batchLock->method('fetch')->willReturn(false);

        $pdo = $this->pdoDispatching([
            'SELECT product_id, unit_cost FROM products' => $productSelect,
            'SELECT *' => $batchLock,
        ]);
        $pdo->expects(self::once())->method('rollBack');

        $inventory = new Inventory($pdo);
        $result = $inventory->adjustStock(['product_id' => 1, 'action' => 'OUT', 'quantity' => 5, 'batch_id' => 77]);

        self::assertFalse($result['success']);
        self::assertSame('Selected batch not found.', $result['message']);
    }

    public function testAdjustStockOutFailsWhenRequestedQuantityExceedsBatchStock(): void
    {
        $productSelect = $this->createStatementMock();
        $productSelect->method('execute')->willReturn(true);
        $productSelect->method('fetch')->willReturn(['product_id' => 1, 'unit_cost' => 2.0]);

        $batchLock = $this->createStatementMock();
        $batchLock->method('execute')->willReturn(true);
        $batchLock->method('fetch')->willReturn([
            'batch_id' => 77,
            'quantity' => 5,
            'batch_number' => 'B1',
            'unit_cost' => 2.0,
        ]);

        $pdo = $this->pdoDispatching([
            'SELECT product_id, unit_cost FROM products' => $productSelect,
            'SELECT *' => $batchLock,
        ]);
        $pdo->expects(self::once())->method('rollBack');

        $inventory = new Inventory($pdo);
        $result = $inventory->adjustStock(['product_id' => 1, 'action' => 'OUT', 'quantity' => 10, 'batch_id' => 77]);

        self::assertFalse($result['success']);
        self::assertSame('Not enough stock in the selected batch.', $result['message']);
    }

    public function testAdjustStockOutAllowsDepletingBatchToExactlyZero(): void
    {
        // Boundary: requested quantity equal to the batch's remaining stock
        // must succeed (the guard is "<", not "<="), and drive the batch
        // status to Depleted.
        $productSelect = $this->createStatementMock();
        $productSelect->method('execute')->willReturn(true);
        $productSelect->method('fetch')->willReturn(['product_id' => 1, 'unit_cost' => 2.0]);

        $batchLock = $this->createStatementMock();
        $batchLock->method('execute')->willReturn(true);
        $batchLock->method('fetch')->willReturn([
            'batch_id' => 77,
            'quantity' => 5,
            'batch_number' => 'B1',
            'unit_cost' => 2.0,
        ]);

        $batchUpdate = $this->createStatementMock();
        $batchUpdate->expects(self::once())
            ->method('execute')
            ->with(self::callback(function (array $params) {
                self::assertSame(0, $params[':quantity']);
                self::assertSame('Depleted', $params[':status']);
                return true;
            }))
            ->willReturn(true);

        $aggregate = $this->createStatementMock();
        $aggregate->method('execute')->willReturn(true);
        $aggregate->method('fetch')->willReturn([
            'total_quantity' => 0,
            'nearest_expiration' => null,
            'nearest_batch' => null,
            'latest_unit_cost' => null,
        ]);

        $finalUpdate = $this->createStatementMock();
        $finalUpdate->method('execute')->willReturn(true);

        $historyInsert = $this->createStatementMock();
        $historyInsert->expects(self::once())
            ->method('execute')
            ->with(self::callback(function (array $params) {
                self::assertSame(5, $params[':quantity']);
                self::assertSame('OUT', $params[':action_type']);
                return true;
            }))
            ->willReturn(true);

        $pdo = $this->pdoDispatching([
            'SELECT product_id, unit_cost FROM products' => $productSelect,
            'SELECT *' => $batchLock,
            "batch_status = :status WHERE batch_id" => $batchUpdate,
            'VALUES (:product_id, :action_type, :quantity, :remarks)' => $historyInsert,
            'AS total_quantity' => $aggregate,
            'product_status = :product_status' => $finalUpdate,
        ]);

        $inventory = new Inventory($pdo);
        $result = $inventory->adjustStock(['product_id' => 1, 'action' => 'OUT', 'quantity' => 5, 'batch_id' => 77]);

        self::assertTrue($result['success']);
    }

    public function testAdjustStockInWithExistingBatchAddsQuantity(): void
    {
        $productSelect = $this->createStatementMock();
        $productSelect->method('execute')->willReturn(true);
        $productSelect->method('fetch')->willReturn(['product_id' => 1, 'unit_cost' => 2.0]);

        $batchLock = $this->createStatementMock();
        $batchLock->method('execute')->willReturn(true);
        $batchLock->method('fetch')->willReturn([
            'batch_id' => 77,
            'quantity' => 5,
            'batch_number' => 'B1',
            'unit_cost' => 2.0,
        ]);

        $batchUpdate = $this->createStatementMock();
        $batchUpdate->expects(self::once())
            ->method('execute')
            ->with(self::callback(function (array $params) {
                self::assertSame(15, $params[':quantity']); // 5 existing + 10 added
                return true;
            }))
            ->willReturn(true);

        $aggregate = $this->createStatementMock();
        $aggregate->method('execute')->willReturn(true);
        $aggregate->method('fetch')->willReturn([
            'total_quantity' => 15,
            'nearest_expiration' => null,
            'nearest_batch' => 'B1',
            'latest_unit_cost' => 2.0,
        ]);

        $reorder = $this->createStatementMock();
        $reorder->method('execute')->willReturn(true);
        $reorder->method('fetchColumn')->willReturn(10);

        $finalUpdate = $this->createStatementMock();
        $finalUpdate->method('execute')->willReturn(true);

        $historyInsert = $this->createStatementMock();
        $historyInsert->method('execute')->willReturn(true);

        $pdo = $this->pdoDispatching([
            'SELECT product_id, unit_cost FROM products' => $productSelect,
            'SELECT *' => $batchLock,
            "batch_status = 'Active' WHERE batch_id" => $batchUpdate,
            'VALUES (:product_id, :action_type, :quantity, :remarks)' => $historyInsert,
            'AS total_quantity' => $aggregate,
            'SELECT reorder_level' => $reorder,
            'product_status = :product_status' => $finalUpdate,
        ]);

        $inventory = new Inventory($pdo);
        $result = $inventory->adjustStock([
            'product_id' => 1,
            'action' => 'in', // lower-case must be normalized
            'quantity' => 10,
            'batch_id' => 77,
        ]);

        self::assertTrue($result['success']);
    }

    public function testAdjustStockInWithoutBatchIdRequiresBatchNumber(): void
    {
        $productSelect = $this->createStatementMock();
        $productSelect->method('execute')->willReturn(true);
        $productSelect->method('fetch')->willReturn(['product_id' => 1, 'unit_cost' => 2.0]);

        $pdo = $this->pdoDispatching([
            'SELECT product_id, unit_cost FROM products' => $productSelect,
        ]);
        $pdo->expects(self::once())->method('rollBack');

        $inventory = new Inventory($pdo);
        $result = $inventory->adjustStock([
            'product_id' => 1,
            'action' => 'IN',
            'quantity' => 10,
            'batch_id' => 0,
            'batch_number' => '',
        ]);

        self::assertFalse($result['success']);
        self::assertSame('Batch number is required for Stock In.', $result['message']);
    }

    public function testExpiredOnlyStockIsMisreportedAsOutOfStockInsteadOfExpired(): void
    {
        // KNOWN DEFECT — see tests/Reports/DEFECTS_inventory-products.md
        // (DEFECT-1). syncProductAggregate()'s aggregate query computes
        // total_quantity and nearest_expiration under the SAME filter
        // "quantity > 0 AND (expiration_date IS NULL OR expiration_date >=
        // CURDATE())". That means nearest_expiration can never be a past
        // date - it is always NULL or >= today - so the 'Expired' branch
        // below is unreachable. A product whose only remaining stock has
        // expired ends up reported as plain 'Out of Stock' instead, hiding
        // real expired stock sitting on the shelf. This mock reproduces
        // exactly what that aggregate query returns for such a product.
        $productSelect = $this->createStatementMock();
        $productSelect->method('execute')->willReturn(true);
        $productSelect->method('fetch')->willReturn(['product_id' => 1, 'unit_cost' => 2.0]);

        $batchLock = $this->createStatementMock();
        $batchLock->method('execute')->willReturn(true);
        $batchLock->method('fetch')->willReturn([
            'batch_id' => 50,
            'quantity' => 2,
            'batch_number' => 'B50',
            'unit_cost' => 2.0,
        ]);

        $batchUpdate = $this->createStatementMock();
        $batchUpdate->method('execute')->willReturn(true);

        $aggregate = $this->createStatementMock();
        $aggregate->method('execute')->willReturn(true);
        $aggregate->method('fetch')->willReturn([
            'total_quantity' => 0,
            'nearest_expiration' => null,
            'nearest_batch' => null,
            'latest_unit_cost' => null,
        ]);

        $historyInsert = $this->createStatementMock();
        $historyInsert->method('execute')->willReturn(true);

        $capturedStatusParams = null;
        $finalUpdate = $this->createStatementMock();
        $finalUpdate->method('execute')->willReturnCallback(function (array $params) use (&$capturedStatusParams) {
            $capturedStatusParams = $params;
            return true;
        });

        $pdo = $this->pdoDispatching([
            'SELECT product_id, unit_cost FROM products' => $productSelect,
            'SELECT *' => $batchLock,
            "batch_status = 'Active' WHERE batch_id" => $batchUpdate,
            'VALUES (:product_id, :action_type, :quantity, :remarks)' => $historyInsert,
            'AS total_quantity' => $aggregate,
            'product_status = :product_status' => $finalUpdate,
        ]);

        $inventory = new Inventory($pdo);
        $result = $inventory->adjustStock([
            'product_id' => 1,
            'action' => 'IN',
            'quantity' => 3,
            'batch_id' => 50,
        ]);

        self::assertTrue($result['success']);
        self::assertNotNull($capturedStatusParams);
        // A product whose only physical stock has expired should be
        // flagged 'Expired' (distinct from genuinely zero stock) so staff
        // know to write it off rather than simply reorder.
        self::assertSame('Expired', $capturedStatusParams[':product_status']);
    }

    // ------------------------------------------------------------------
    // updateProduct()
    // ------------------------------------------------------------------

    public function testUpdateProductRejectsInvalidProductId(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('prepare');

        $inventory = new Inventory($pdo);
        $result = $inventory->updateProduct(['product_id' => 0]);

        self::assertFalse($result['success']);
        self::assertSame('Invalid product.', $result['message']);
    }

    public function testUpdateProductRejectsDuplicateBarcode(): void
    {
        $check = $this->createStatementMock();
        $check->method('execute')->willReturn(true);
        $check->method('fetchColumn')->willReturn('9'); // some other product owns it

        $pdo = $this->pdoDispatching([
            'WHERE barcode = :barcode' => $check,
        ]);
        $pdo->expects(self::never())->method('beginTransaction');

        $inventory = new Inventory($pdo);
        $result = $inventory->updateProduct(['product_id' => 1, 'barcode' => '555']);

        self::assertFalse($result['success']);
        self::assertSame('This barcode is already used by another product.', $result['message']);
    }

    public function testUpdateProductGeneratesFallbackBarcodeWhenCleared(): void
    {
        $update = $this->createStatementMock();
        $update->expects(self::once())
            ->method('execute')
            ->with(self::callback(function (array $params) {
                self::assertSame('INT-000042', $params[':barcode']);
                return true;
            }))
            ->willReturn(true);
        $update->method('rowCount')->willReturn(1);

        $aggregate = $this->createStatementMock();
        $aggregate->method('execute')->willReturn(true);
        $aggregate->method('fetch')->willReturn([
            'total_quantity' => 0,
            'nearest_expiration' => null,
            'nearest_batch' => null,
            'latest_unit_cost' => null,
        ]);

        $finalUpdate = $this->createStatementMock();
        $finalUpdate->method('execute')->willReturn(true);

        $pdo = $this->pdoDispatching([
            'is_test_data = :is_test_data' => $update,
            'AS total_quantity' => $aggregate,
            'product_status = :product_status' => $finalUpdate,
        ]);

        $inventory = new Inventory($pdo);
        $result = $inventory->updateProduct(['product_id' => 42, 'barcode' => '']);

        self::assertTrue($result['success']);
    }

    public function testUpdateProductFailsWhenNoRowsChangedAndProductMissing(): void
    {
        $barcodeCheck = $this->createStatementMock();
        $barcodeCheck->method('execute')->willReturn(true);
        $barcodeCheck->method('fetchColumn')->willReturn(false);

        $update = $this->createStatementMock();
        $update->method('execute')->willReturn(true);
        $update->method('rowCount')->willReturn(0);

        $detailsCheck = $this->createStatementMock();
        $detailsCheck->method('execute')->willReturn(true);
        $detailsCheck->method('fetch')->willReturn(false);

        $pdo = $this->pdoDispatching([
            'WHERE barcode = :barcode' => $barcodeCheck,
            'is_test_data = :is_test_data' => $update,
            'INNER JOIN product_types pt' => $detailsCheck,
        ]);
        $pdo->expects(self::once())->method('rollBack');

        $inventory = new Inventory($pdo);
        $result = $inventory->updateProduct(['product_id' => 123, 'barcode' => '111']);

        self::assertFalse($result['success']);
        self::assertSame('Product not found.', $result['message']);
    }

    public function testUpdateProductSucceedsWhenNoRowsChangedButProductExists(): void
    {
        // rowCount() === 0 can legitimately mean "the update matched but the
        // values were already identical" — must not be treated as an error
        // as long as the product still exists.
        $barcodeCheck = $this->createStatementMock();
        $barcodeCheck->method('execute')->willReturn(true);
        $barcodeCheck->method('fetchColumn')->willReturn(false);

        $update = $this->createStatementMock();
        $update->method('execute')->willReturn(true);
        $update->method('rowCount')->willReturn(0);

        $detailsCheck = $this->createStatementMock();
        $detailsCheck->method('execute')->willReturn(true);
        $detailsCheck->method('fetch')->willReturn(['product_id' => 123]);

        $aggregate = $this->createStatementMock();
        $aggregate->method('execute')->willReturn(true);
        $aggregate->method('fetch')->willReturn([
            'total_quantity' => 0,
            'nearest_expiration' => null,
            'nearest_batch' => null,
            'latest_unit_cost' => null,
        ]);

        $finalUpdate = $this->createStatementMock();
        $finalUpdate->method('execute')->willReturn(true);

        $pdo = $this->pdoDispatching([
            'WHERE barcode = :barcode' => $barcodeCheck,
            'is_test_data = :is_test_data' => $update,
            'INNER JOIN product_types pt' => $detailsCheck,
            'AS total_quantity' => $aggregate,
            'product_status = :product_status' => $finalUpdate,
        ]);

        $inventory = new Inventory($pdo);
        $result = $inventory->updateProduct(['product_id' => 123, 'barcode' => '111']);

        self::assertTrue($result['success']);
    }

    // ------------------------------------------------------------------
    // deleteProduct()
    // ------------------------------------------------------------------

    public function testDeleteProductRejectsInvalidProductId(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('prepare');

        $inventory = new Inventory($pdo);
        $result = $inventory->deleteProduct(0);

        self::assertFalse($result['success']);
        self::assertSame('Invalid product.', $result['message']);
    }

    public function testDeleteProductFailsWhenProductNotFound(): void
    {
        $check = $this->createStatementMock();
        $check->method('execute')->willReturn(true);
        $check->method('fetch')->willReturn(false);

        $pdo = $this->pdoDispatching([
            'product_id, product_name FROM products' => $check,
        ]);
        $pdo->expects(self::once())->method('rollBack');

        $inventory = new Inventory($pdo);
        $result = $inventory->deleteProduct(999);

        self::assertFalse($result['success']);
        self::assertSame('Product not found.', $result['message']);
    }

    public function testDeleteProductBlockedBySalesHistory(): void
    {
        $check = $this->createStatementMock();
        $check->method('execute')->willReturn(true);
        $check->method('fetch')->willReturn(['product_id' => 1, 'product_name' => 'X']);

        $sales = $this->createStatementMock();
        $sales->method('execute')->willReturn(true);
        $sales->method('fetchColumn')->willReturn(3);

        $pdo = $this->pdoDispatching([
            'product_id, product_name FROM products' => $check,
            'FROM sale_items WHERE product_id' => $sales,
        ]);
        $pdo->expects(self::once())->method('rollBack');

        $inventory = new Inventory($pdo);
        $result = $inventory->deleteProduct(1);

        self::assertFalse($result['success']);
        self::assertSame('This product cannot be deleted because it has sales history.', $result['message']);
    }

    public function testDeleteProductBlockedByInventoryHistory(): void
    {
        $check = $this->createStatementMock();
        $check->method('execute')->willReturn(true);
        $check->method('fetch')->willReturn(['product_id' => 1, 'product_name' => 'X']);

        $sales = $this->createStatementMock();
        $sales->method('execute')->willReturn(true);
        $sales->method('fetchColumn')->willReturn(0);

        $history = $this->createStatementMock();
        $history->method('execute')->willReturn(true);
        $history->method('fetchColumn')->willReturn(2);

        $pdo = $this->pdoDispatching([
            'product_id, product_name FROM products' => $check,
            'FROM sale_items WHERE product_id' => $sales,
            'FROM inventory_history WHERE product_id' => $history,
        ]);
        $pdo->expects(self::once())->method('rollBack');

        $inventory = new Inventory($pdo);
        $result = $inventory->deleteProduct(1);

        self::assertFalse($result['success']);
        self::assertSame('This product cannot be deleted because it has inventory history.', $result['message']);
    }

    public function testDeleteProductBlockedByBatchRecords(): void
    {
        $check = $this->createStatementMock();
        $check->method('execute')->willReturn(true);
        $check->method('fetch')->willReturn(['product_id' => 1, 'product_name' => 'X']);

        $sales = $this->createStatementMock();
        $sales->method('execute')->willReturn(true);
        $sales->method('fetchColumn')->willReturn(0);

        $history = $this->createStatementMock();
        $history->method('execute')->willReturn(true);
        $history->method('fetchColumn')->willReturn(0);

        $batches = $this->createStatementMock();
        $batches->method('execute')->willReturn(true);
        $batches->method('fetchColumn')->willReturn(1);

        $pdo = $this->pdoDispatching([
            'product_id, product_name FROM products' => $check,
            'FROM sale_items WHERE product_id' => $sales,
            'FROM inventory_history WHERE product_id' => $history,
            'FROM product_batches WHERE product_id = :id' => $batches,
        ]);
        $pdo->expects(self::once())->method('rollBack');

        $inventory = new Inventory($pdo);
        $result = $inventory->deleteProduct(1);

        self::assertFalse($result['success']);
        self::assertSame('This product cannot be deleted because it has batch inventory records.', $result['message']);
    }

    public function testDeleteProductSucceedsWhenNoBlockingRecords(): void
    {
        $check = $this->createStatementMock();
        $check->method('execute')->willReturn(true);
        $check->method('fetch')->willReturn(['product_id' => 1, 'product_name' => 'X']);

        $sales = $this->createStatementMock();
        $sales->method('execute')->willReturn(true);
        $sales->method('fetchColumn')->willReturn(0);

        $history = $this->createStatementMock();
        $history->method('execute')->willReturn(true);
        $history->method('fetchColumn')->willReturn(0);

        $batches = $this->createStatementMock();
        $batches->method('execute')->willReturn(true);
        $batches->method('fetchColumn')->willReturn(0);

        $delete = $this->createStatementMock();
        $delete->expects(self::once())->method('execute')->with([':id' => 1])->willReturn(true);

        $pdo = $this->pdoDispatching([
            'product_id, product_name FROM products' => $check,
            'FROM sale_items WHERE product_id' => $sales,
            'FROM inventory_history WHERE product_id' => $history,
            'FROM product_batches WHERE product_id = :id' => $batches,
            'DELETE FROM products' => $delete,
        ]);

        $inventory = new Inventory($pdo);
        $result = $inventory->deleteProduct(1);

        self::assertTrue($result['success']);
        self::assertSame('Product deleted successfully.', $result['message']);
    }

    // ------------------------------------------------------------------
    // cleanupTestProduct()
    // ------------------------------------------------------------------

    public function testCleanupTestProductRejectsInvalidProductId(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('prepare');

        $inventory = new Inventory($pdo);
        $result = $inventory->cleanupTestProduct(0);

        self::assertFalse($result['success']);
        self::assertSame('Invalid test product.', $result['message']);
    }

    public function testCleanupTestProductFailsWhenNotFound(): void
    {
        $check = $this->createStatementMock();
        $check->method('execute')->willReturn(true);
        $check->method('fetch')->willReturn(false);

        $pdo = $this->pdoDispatching([
            'product_name, is_test_data' => $check,
        ]);
        $pdo->expects(self::once())->method('rollBack');

        $inventory = new Inventory($pdo);
        $result = $inventory->cleanupTestProduct(999);

        self::assertFalse($result['success']);
        self::assertSame('Test product not found.', $result['message']);
    }

    public function testCleanupTestProductRefusesNonTestProduct(): void
    {
        $check = $this->createStatementMock();
        $check->method('execute')->willReturn(true);
        $check->method('fetch')->willReturn(['product_id' => 1, 'product_name' => 'Real', 'is_test_data' => 0]);

        $pdo = $this->pdoDispatching([
            'product_name, is_test_data' => $check,
        ]);
        $pdo->expects(self::once())->method('rollBack');

        $inventory = new Inventory($pdo);
        $result = $inventory->cleanupTestProduct(1);

        self::assertFalse($result['success']);
        self::assertSame(
            'This product is not marked as test data. Normal product deletion is protected.',
            $result['message']
        );
    }

    public function testCleanupTestProductBlockedBySalesHistory(): void
    {
        $check = $this->createStatementMock();
        $check->method('execute')->willReturn(true);
        $check->method('fetch')->willReturn(['product_id' => 1, 'product_name' => 'Test', 'is_test_data' => 1]);

        $sales = $this->createStatementMock();
        $sales->method('execute')->willReturn(true);
        $sales->method('fetchColumn')->willReturn(1);

        $pdo = $this->pdoDispatching([
            'product_name, is_test_data' => $check,
            'FROM sale_items' => $sales,
        ]);
        $pdo->expects(self::once())->method('rollBack');

        $inventory = new Inventory($pdo);
        $result = $inventory->cleanupTestProduct(1);

        self::assertFalse($result['success']);
        self::assertStringContainsString('sales history', $result['message']);
    }

    public function testCleanupTestProductSucceedsAndRemovesRelatedRecords(): void
    {
        $check = $this->createStatementMock();
        $check->method('execute')->willReturn(true);
        $check->method('fetch')->willReturn(['product_id' => 1, 'product_name' => 'Test', 'is_test_data' => 1]);

        $sales = $this->createStatementMock();
        $sales->method('execute')->willReturn(true);
        $sales->method('fetchColumn')->willReturn(0);

        $adjustment = $this->createStatementMock();
        $adjustment->method('execute')->willReturn(true);
        $adjustment->method('fetchAll')->willReturn([]); // no adjustment ids -> orphan cleanup skipped

        $genericDelete = $this->createStatementMock();
        $genericDelete->method('execute')->willReturn(true);

        $finalDelete = $this->createStatementMock();
        $finalDelete->method('execute')->willReturn(true);
        $finalDelete->method('rowCount')->willReturn(1);

        $prepareCount = 0;
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(
            function (string $sql) use (
                &$prepareCount,
                $check,
                $sales,
                $adjustment,
                $genericDelete,
                $finalDelete
            ) {
                $prepareCount++;
                if (str_contains($sql, 'product_name, is_test_data')) {
                    return $check;
                }
                if (str_contains($sql, 'FROM sale_items')) {
                    return $sales;
                }
                if (str_contains($sql, 'DISTINCT adjustment_id')) {
                    return $adjustment;
                }
                if (str_contains($sql, 'is_test_data = 1')) {
                    return $finalDelete;
                }
                if (str_contains($sql, 'WHERE product_id = :id')) {
                    return $genericDelete;
                }
                self::fail('Unexpected SQL prepared: ' . $sql);
            }
        );
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        $pdo->method('inTransaction')->willReturn(true);

        $inventory = new Inventory($pdo);
        $result = $inventory->cleanupTestProduct(1);

        self::assertTrue($result['success']);
        // check + sales + adjustment + 6 table deletes + final delete = 10
        self::assertSame(10, $prepareCount);
    }

    public function testCleanupTestProductFailsWhenFinalDeleteAffectsNoRows(): void
    {
        $check = $this->createStatementMock();
        $check->method('execute')->willReturn(true);
        $check->method('fetch')->willReturn(['product_id' => 1, 'product_name' => 'Test', 'is_test_data' => 1]);

        $sales = $this->createStatementMock();
        $sales->method('execute')->willReturn(true);
        $sales->method('fetchColumn')->willReturn(0);

        $adjustment = $this->createStatementMock();
        $adjustment->method('execute')->willReturn(true);
        $adjustment->method('fetchAll')->willReturn([]);

        $genericDelete = $this->createStatementMock();
        $genericDelete->method('execute')->willReturn(true);

        $finalDelete = $this->createStatementMock();
        $finalDelete->method('execute')->willReturn(true);
        $finalDelete->method('rowCount')->willReturn(0);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(
            function (string $sql) use ($check, $sales, $adjustment, $genericDelete, $finalDelete) {
                if (str_contains($sql, 'product_name, is_test_data')) {
                    return $check;
                }
                if (str_contains($sql, 'FROM sale_items')) {
                    return $sales;
                }
                if (str_contains($sql, 'DISTINCT adjustment_id')) {
                    return $adjustment;
                }
                if (str_contains($sql, 'is_test_data = 1')) {
                    return $finalDelete;
                }
                return $genericDelete;
            }
        );
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        $pdo->expects(self::once())->method('rollBack');
        $pdo->method('inTransaction')->willReturn(true);

        $inventory = new Inventory($pdo);
        $result = $inventory->cleanupTestProduct(1);

        self::assertFalse($result['success']);
        self::assertSame('Test product cleanup could not remove the product.', $result['message']);
    }
}
