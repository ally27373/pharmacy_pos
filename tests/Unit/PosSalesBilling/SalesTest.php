<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Support/MocksPdo.php';
require_once __DIR__ . '/../../../app/Models/Sales.php';

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for app/Models/Sales.php.
 */
final class SalesTest extends TestCase
{
    use MocksPdo;

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

    public function testGetAllSalesNormalizesPaginationAndReturnsMeta(): void
    {
        $countStmt = $this->createStatementMock();
        $countStmt->method('fetchColumn')->willReturn(12);

        $bound = [];
        $dataStmt = $this->createStatementMock();
        $dataStmt->method('bindValue')->willReturnCallback(function ($k, $v) use (&$bound) {
            $bound[$k] = $v;
            return true;
        });
        $dataStmt->method('fetchAll')->willReturn([
            ['sale_id' => 1, 'transaction_number' => 'TID-1'],
        ]);

        $pdo = $this->pdoDispatching([
            'SELECT COUNT(*)' => $countStmt,
            'invoice_number' => $dataStmt,
        ]);

        $sales = new Sales($pdo);
        $result = $sales->getAllSales(1, 5);

        $this->assertSame([['sale_id' => 1, 'transaction_number' => 'TID-1']], $result['transactions']);
        $this->assertSame(1, $result['pagination']['page']);
        $this->assertSame(5, $result['pagination']['limit']);
        $this->assertSame(12, $result['pagination']['total']);
        $this->assertSame(3, $result['pagination']['total_pages']);
        $this->assertFalse($result['pagination']['has_previous']);
        $this->assertTrue($result['pagination']['has_next']);
        $this->assertSame(5, $bound[':limit']);
        $this->assertSame(0, $bound[':offset']);
    }

    public function testGetAllSalesClampsLimitAbove100(): void
    {
        $countStmt = $this->createStatementMock();
        $countStmt->method('fetchColumn')->willReturn(0);

        $bound = [];
        $dataStmt = $this->createStatementMock();
        $dataStmt->method('bindValue')->willReturnCallback(function ($k, $v) use (&$bound) {
            $bound[$k] = $v;
            return true;
        });
        $dataStmt->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoDispatching([
            'SELECT COUNT(*)' => $countStmt,
            'invoice_number' => $dataStmt,
        ]);

        $sales = new Sales($pdo);
        $result = $sales->getAllSales(1, 999);

        $this->assertSame(100, $result['pagination']['limit']);
        $this->assertSame(100, $bound[':limit']);
    }

    public function testGetAllSalesBindsSearchAcrossTransactionAndPaymentFields(): void
    {
        $bound = [];

        $countStmt = $this->createStatementMock();
        $countStmt->method('bindValue')->willReturnCallback(function ($k, $v) use (&$bound) {
            $bound[$k] = $v;
            return true;
        });
        $countStmt->method('fetchColumn')->willReturn(0);

        $dataStmt = $this->createStatementMock();
        $dataStmt->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoDispatching([
            'SELECT COUNT(*)' => $countStmt,
            'invoice_number' => $dataStmt,
        ]);

        $sales = new Sales($pdo);
        $sales->getAllSales(1, 20, '  TID-2026  ');

        $this->assertSame('%TID-2026%', $bound[':search']);
    }

    public function testGetAllSalesWithZeroResultsReturnsZeroPages(): void
    {
        $countStmt = $this->createStatementMock();
        $countStmt->method('fetchColumn')->willReturn(0);

        $dataStmt = $this->createStatementMock();
        $dataStmt->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoDispatching([
            'SELECT COUNT(*)' => $countStmt,
            'invoice_number' => $dataStmt,
        ]);

        $sales = new Sales($pdo);
        $result = $sales->getAllSales();

        $this->assertSame(0, $result['pagination']['total']);
        $this->assertSame(0, $result['pagination']['total_pages']);
        $this->assertFalse($result['pagination']['has_previous']);
        $this->assertFalse($result['pagination']['has_next']);
    }

    /**
     * Regression pin for DEFECT-2 (tests/Reports/DEFECTS_pos-sales-billing.md):
     * when the requested page is beyond the last page, the page must be
     * clamped to `total_pages` BEFORE the offset is computed/the paginated
     * query runs, so the reported `page` and the actually-queried rows
     * agree (mirrors the pattern in Reports::getReportData()).
     */
    public function testGetAllSalesOutOfRangePageClampsOffsetBeforeQuerying(): void
    {
        $countStmt = $this->createStatementMock();
        $countStmt->method('fetchColumn')->willReturn(5); // only 5 rows total

        $bound = [];
        $dataStmt = $this->createStatementMock();
        $dataStmt->method('bindValue')->willReturnCallback(function ($k, $v) use (&$bound) {
            $bound[$k] = $v;
            return true;
        });
        // With the offset correctly clamped, the DB would return page 1's
        // rows (here just simulated as non-empty to show they'd be used).
        $dataStmt->method('fetchAll')->willReturn([
            ['sale_id' => 1, 'transaction_number' => 'TID-1'],
        ]);

        $pdo = $this->pdoDispatching([
            'SELECT COUNT(*)' => $countStmt,
            'invoice_number' => $dataStmt,
        ]);

        $sales = new Sales($pdo);
        // total=5, limit=20 => total_pages=1, but we ask for page 5.
        $result = $sales->getAllSales(5, 20);

        // The offset must be recomputed from the CLAMPED page (1), i.e.
        // (1-1)*20 = 0 — not the stale (5-1)*20 = 80 from the original
        // out-of-range page.
        $this->assertSame(0, $bound[':offset']);

        // The reported page and the actually-queried page now agree.
        $this->assertSame(1, $result['pagination']['page']);
        $this->assertSame([
            ['sale_id' => 1, 'transaction_number' => 'TID-1'],
        ], $result['transactions']);
    }

    public function testGetSaleDetailsBindsSaleIdAndReturnsRows(): void
    {
        $bound = [];
        $stmt = $this->createStatementMock();
        $stmt->method('execute')->willReturnCallback(function ($params) use (&$bound) {
            $bound = $params;
            return true;
        });
        $stmt->method('fetchAll')->willReturn([
            ['sale_id' => 42, 'product_name' => 'Amoxicillin', 'quantity' => 2],
        ]);

        $pdo = $this->pdoDispatching([
            'FROM sales s' => $stmt,
        ]);

        $sales = new Sales($pdo);
        $result = $sales->getSaleDetails(42);

        $this->assertSame(42, $bound[':sale_id']);
        $this->assertSame([
            ['sale_id' => 42, 'product_name' => 'Amoxicillin', 'quantity' => 2],
        ], $result);
    }
}
