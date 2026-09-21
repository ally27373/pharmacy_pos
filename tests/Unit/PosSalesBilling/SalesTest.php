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
     * Defect: when the requested page is beyond the last page, the code
     * clamps the *reported* `page` value down to `total_pages` AFTER the
     * paginated query already ran with the offset computed from the
     * original, out-of-range page. This produces a response that claims
     * to be showing a valid earlier page while actually returning the
     * (empty) results of the out-of-range offset.
     *
     * See tests/Reports/DEFECTS_pos-sales-billing.md.
     */
    public function testGetAllSalesOutOfRangePageReportsClampedPageButOffsetIsStillWrong(): void
    {
        $countStmt = $this->createStatementMock();
        $countStmt->method('fetchColumn')->willReturn(5); // only 5 rows total

        $bound = [];
        $dataStmt = $this->createStatementMock();
        $dataStmt->method('bindValue')->willReturnCallback(function ($k, $v) use (&$bound) {
            $bound[$k] = $v;
            return true;
        });
        // The real DB would return 0 rows for an out-of-range OFFSET.
        $dataStmt->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoDispatching([
            'SELECT COUNT(*)' => $countStmt,
            'invoice_number' => $dataStmt,
        ]);

        $sales = new Sales($pdo);
        // total=5, limit=20 => total_pages=1, but we ask for page 5.
        $result = $sales->getAllSales(5, 20);

        // offset was computed from the ORIGINAL page (5-1)*20 = 80, before
        // the page value below was clamped back down to 1.
        $this->assertSame(80, $bound[':offset']);

        // The pagination metadata now (misleadingly) claims we're on
        // page 1 of a data set that has rows...
        $this->assertSame(1, $result['pagination']['page']);
        // ...yet no transactions are returned, because the query used the
        // stale, unclamped offset (80) instead of the offset for page 1.
        $this->assertSame([], $result['transactions']);
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
