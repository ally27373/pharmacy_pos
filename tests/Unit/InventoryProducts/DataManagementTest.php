<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/Models/DataManagement.php';
require_once __DIR__ . '/../../Support/MocksPdo.php';

use PHPUnit\Framework\TestCase;

final class DataManagementTest extends TestCase
{
    use MocksPdo;

    public function testGetInventoryForExportReturnsQueryResultUnmodified(): void
    {
        $rows = [
            ['product_id' => 1, 'product_name' => 'Amoxicillin', 'quantity' => 10],
            ['product_id' => 2, 'product_name' => 'Paracetamol', 'quantity' => 0],
        ];

        $stmt = $this->createStatementMock();
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createPdoMock();
        $pdo->expects(self::once())
            ->method('query')
            ->with(self::stringContains('FROM products p'))
            ->willReturn($stmt);

        $dataManagement = new DataManagement($pdo);
        self::assertSame($rows, $dataManagement->getInventoryForExport());
    }

    public function testGetSalesForExportOnlyQueriesCompletedTransactions(): void
    {
        $rows = [
            ['sale_id' => 1, 'transaction_status' => 'Completed'],
        ];

        $capturedSql = null;
        $stmt = $this->createStatementMock();
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createPdoMock();
        $pdo->method('query')->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
            $capturedSql = $sql;
            return $stmt;
        });

        $dataManagement = new DataManagement($pdo);
        $result = $dataManagement->getSalesForExport();

        self::assertSame($rows, $result);
        self::assertStringContainsString("s.transaction_status = 'Completed'", $capturedSql);
    }

    public function testCountInventoryReturnsIntCastColumn(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('fetchColumn')->willReturn('42');

        $pdo = $this->createPdoMock();
        $pdo->method('query')->willReturn($stmt);

        $dataManagement = new DataManagement($pdo);
        self::assertSame(42, $dataManagement->countInventory());
    }

    public function testCountInventoryReturnsZeroWhenTableEmpty(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('fetchColumn')->willReturn(false);

        $pdo = $this->createPdoMock();
        $pdo->method('query')->willReturn($stmt);

        $dataManagement = new DataManagement($pdo);
        self::assertSame(0, $dataManagement->countInventory());
    }

    public function testCountSalesRowsOnlyCountsCompletedTransactions(): void
    {
        $capturedSql = null;
        $stmt = $this->createStatementMock();
        $stmt->method('fetchColumn')->willReturn('7');

        $pdo = $this->createPdoMock();
        $pdo->method('query')->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
            $capturedSql = $sql;
            return $stmt;
        });

        $dataManagement = new DataManagement($pdo);
        self::assertSame(7, $dataManagement->countSalesRows());
        self::assertStringContainsString("s.transaction_status = 'Completed'", $capturedSql);
    }
}
