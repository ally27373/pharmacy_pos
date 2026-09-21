<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Support/MocksPdo.php';
require_once __DIR__ . '/../../../app/Models/Billings.php';

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for app/Models/Billings.php (class Billing).
 */
final class BillingTest extends TestCase
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

    public function testGetAllBillingsNormalizesPaginationAndReturnsMeta(): void
    {
        $countStmt = $this->createStatementMock();
        $countStmt->method('fetchColumn')->willReturn(8);

        $bound = [];
        $dataStmt = $this->createStatementMock();
        $dataStmt->method('bindValue')->willReturnCallback(function ($k, $v) use (&$bound) {
            $bound[$k] = $v;
            return true;
        });
        $dataStmt->method('fetchAll')->willReturn([
            ['payment_id' => 1, 'amount_paid' => 100.0],
        ]);

        $pdo = $this->pdoDispatching([
            'SELECT COUNT(*)' => $countStmt,
            'invoice_number' => $dataStmt,
        ]);

        $billing = new Billing($pdo);
        $result = $billing->getAllBillings(1, 5);

        $this->assertSame([['payment_id' => 1, 'amount_paid' => 100.0]], $result['billings']);
        $this->assertSame(8, $result['pagination']['total']);
        $this->assertSame(2, $result['pagination']['total_pages']);
        $this->assertSame(5, $bound[':limit']);
        $this->assertSame(0, $bound[':offset']);
    }

    public function testGetAllBillingsWithNoSearchOmitsWhereClauseEntirely(): void
    {
        // Unlike Sales/POS, Billing has no base filter — with no search
        // term it should query with no WHERE clause at all (i.e. every
        // payment record, regardless of status).
        $capturedSql = null;

        $countStmt = $this->createStatementMock();
        $countStmt->method('fetchColumn')->willReturn(0);

        $dataStmt = $this->createStatementMock();
        $dataStmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createPdoMock();
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use (&$capturedSql, $countStmt, $dataStmt) {
            if (str_contains($sql, 'invoice_number')) {
                $capturedSql = $sql;
                return $dataStmt;
            }
            return $countStmt;
        });

        $billing = new Billing($pdo);
        $billing->getAllBillings();

        $this->assertStringNotContainsString('WHERE', $capturedSql);
    }

    public function testGetAllBillingsClampsLimitAbove100(): void
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

        $billing = new Billing($pdo);
        $result = $billing->getAllBillings(1, 250);

        $this->assertSame(100, $result['pagination']['limit']);
        $this->assertSame(100, $bound[':limit']);
    }

    public function testGetAllBillingsBindsSearchAcrossFields(): void
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

        $billing = new Billing($pdo);
        $billing->getAllBillings(1, 20, '  GCash  ');

        $this->assertSame('%GCash%', $bound[':search']);
    }

    /**
     * Regression pin for DEFECT-3 (tests/Reports/DEFECTS_pos-sales-billing.md):
     * same fix pattern as Sales::getAllSales() — the page must be clamped
     * to the last valid page BEFORE the offset is computed/the paginated
     * query runs, so the reported page and the actually-queried rows
     * agree.
     */
    public function testGetAllBillingsOutOfRangePageClampsOffsetBeforeQuerying(): void
    {
        $countStmt = $this->createStatementMock();
        $countStmt->method('fetchColumn')->willReturn(3);

        $bound = [];
        $dataStmt = $this->createStatementMock();
        $dataStmt->method('bindValue')->willReturnCallback(function ($k, $v) use (&$bound) {
            $bound[$k] = $v;
            return true;
        });
        // With the offset correctly clamped, the DB would return page 1's
        // rows (simulated here as non-empty to show they'd be used).
        $dataStmt->method('fetchAll')->willReturn([
            ['payment_id' => 1, 'amount_paid' => 100.0],
        ]);

        $pdo = $this->pdoDispatching([
            'SELECT COUNT(*)' => $countStmt,
            'invoice_number' => $dataStmt,
        ]);

        $billing = new Billing($pdo);
        // total=3, limit=20 => total_pages=1, but page 4 is requested.
        $result = $billing->getAllBillings(4, 20);

        // The offset must be recomputed from the CLAMPED page (1), i.e.
        // (1-1)*20 = 0 — not the stale (4-1)*20 = 60 from the original
        // out-of-range page.
        $this->assertSame(0, $bound[':offset']);
        $this->assertSame(1, $result['pagination']['page']);
        $this->assertSame([
            ['payment_id' => 1, 'amount_paid' => 100.0],
        ], $result['billings']);
    }

    public function testGetBillingDetailsBindsPaymentIdAndReturnsRow(): void
    {
        $bound = [];
        $stmt = $this->createStatementMock();
        $stmt->method('execute')->willReturnCallback(function ($params) use (&$bound) {
            $bound = $params;
            return true;
        });
        $stmt->method('fetch')->willReturn([
            'payment_id' => 7,
            'amount_paid' => 250.0,
        ]);

        $pdo = $this->pdoDispatching([
            'FROM payments p' => $stmt,
        ]);

        $billing = new Billing($pdo);
        $result = $billing->getBillingDetails(7);

        $this->assertSame(7, $bound[':payment_id']);
        $this->assertSame(['payment_id' => 7, 'amount_paid' => 250.0], $result);
    }
}
