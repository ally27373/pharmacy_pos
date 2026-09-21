<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/Models/InventoryHistory.php';
require_once __DIR__ . '/../../Support/MocksPdo.php';

use PHPUnit\Framework\TestCase;

final class InventoryHistoryTest extends TestCase
{
    use MocksPdo;

    private function stubbedPdo(int $total, array $rows): PDO&\PHPUnit\Framework\MockObject\MockObject
    {
        $countStmt = $this->createStatementMock();
        $countStmt->method('bindValue')->willReturn(true);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn($total);

        $mainStmt = $this->createStatementMock();
        $mainStmt->method('bindValue')->willReturn(true);
        $mainStmt->method('execute')->willReturn(true);
        $mainStmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(
            function (string $sql) use ($countStmt, $mainStmt) {
                return str_contains($sql, 'SELECT COUNT(*)') ? $countStmt : $mainStmt;
            }
        );

        return $pdo;
    }

    public function testGetHistoryDefaultsHaveNoWhereClause(): void
    {
        $capturedSql = [];
        $countStmt = $this->createStatementMock();
        $countStmt->method('bindValue')->willReturn(true);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(0);

        $mainStmt = $this->createStatementMock();
        $mainStmt->method('bindValue')->willReturn(true);
        $mainStmt->method('execute')->willReturn(true);
        $mainStmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(
            function (string $sql) use (&$capturedSql, $countStmt, $mainStmt) {
                $capturedSql[] = $sql;
                return str_contains($sql, 'SELECT COUNT(*)') ? $countStmt : $mainStmt;
            }
        );

        $history = new InventoryHistory($pdo);
        $history->getHistory();

        foreach ($capturedSql as $sql) {
            self::assertStringNotContainsString('WHERE', $sql);
        }
    }

    public function testGetHistoryClampsLimitAboveHundred(): void
    {
        $pdo = $this->stubbedPdo(0, []);

        $history = new InventoryHistory($pdo);
        $result = $history->getHistory(1, 999);

        self::assertSame(100, $result['pagination']['limit']);
    }

    public function testGetHistoryClampsLimitBelowOne(): void
    {
        $pdo = $this->stubbedPdo(0, []);

        $history = new InventoryHistory($pdo);
        $result = $history->getHistory(1, -3);

        self::assertSame(1, $result['pagination']['limit']);
    }

    public function testGetHistoryClampsPageDownToLastPageWhenBeyondTotal(): void
    {
        // total=5, limit=2 -> totalPages=3; requesting page 50 clamps to 3.
        $pdo = $this->stubbedPdo(5, []);

        $history = new InventoryHistory($pdo);
        $result = $history->getHistory(50, 2);

        self::assertSame(3, $result['pagination']['page']);
        self::assertSame(3, $result['pagination']['total_pages']);
    }

    public function testGetHistoryIgnoresInvalidActionFilter(): void
    {
        $capturedSql = [];
        $countStmt = $this->createStatementMock();
        $countStmt->method('bindValue')->willReturn(true);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(0);

        $mainStmt = $this->createStatementMock();
        $mainStmt->method('bindValue')->willReturn(true);
        $mainStmt->method('execute')->willReturn(true);
        $mainStmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(
            function (string $sql) use (&$capturedSql, $countStmt, $mainStmt) {
                $capturedSql[] = $sql;
                return str_contains($sql, 'SELECT COUNT(*)') ? $countStmt : $mainStmt;
            }
        );

        $history = new InventoryHistory($pdo);
        // 'SIDEWAYS' is neither IN nor OUT — must be treated as "no filter".
        $history->getHistory(1, 20, '', 'SIDEWAYS');

        foreach ($capturedSql as $sql) {
            // "ih.action_type" alone is a normal SELECT column and always
            // present; only the WHERE-clause comparison must be absent.
            self::assertStringNotContainsString('ih.action_type = :action_type', $sql);
        }
    }

    public function testGetHistoryAcceptsLowercaseActionAndNormalizesToUppercase(): void
    {
        $capturedSql = [];
        $capturedParams = [];
        $countStmt = $this->createStatementMock();
        $countStmt->method('bindValue')->willReturnCallback(function ($k, $v) use (&$capturedParams) {
            $capturedParams[$k] = $v;
            return true;
        });
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(0);

        $mainStmt = $this->createStatementMock();
        $mainStmt->method('bindValue')->willReturn(true);
        $mainStmt->method('execute')->willReturn(true);
        $mainStmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(
            function (string $sql) use (&$capturedSql, $countStmt, $mainStmt) {
                $capturedSql[] = $sql;
                return str_contains($sql, 'SELECT COUNT(*)') ? $countStmt : $mainStmt;
            }
        );

        $history = new InventoryHistory($pdo);
        $history->getHistory(1, 20, '', 'in');

        self::assertSame('IN', $capturedParams[':action_type']);
        foreach ($capturedSql as $sql) {
            self::assertStringContainsString('ih.action_type = :action_type', $sql);
        }
    }

    public function testGetHistoryBuildsWhereClauseForSearchAndDateRange(): void
    {
        $capturedSql = [];
        $countStmt = $this->createStatementMock();
        $countStmt->method('bindValue')->willReturn(true);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(0);

        $mainStmt = $this->createStatementMock();
        $mainStmt->method('bindValue')->willReturn(true);
        $mainStmt->method('execute')->willReturn(true);
        $mainStmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(
            function (string $sql) use (&$capturedSql, $countStmt, $mainStmt) {
                $capturedSql[] = $sql;
                return str_contains($sql, 'SELECT COUNT(*)') ? $countStmt : $mainStmt;
            }
        );

        $history = new InventoryHistory($pdo);
        $history->getHistory(1, 20, 'paracetamol', '', '2026-01-01', '2026-01-31');

        self::assertNotEmpty($capturedSql);
        foreach ($capturedSql as $sql) {
            self::assertStringContainsString('p.product_name LIKE :search_product', $sql);
            self::assertStringContainsString('DATE(ih.created_at) >= :from_date', $sql);
            self::assertStringContainsString('DATE(ih.created_at) <= :to_date', $sql);
        }
    }

    public function testGetHistoryReturnsRowsAndPaginationShape(): void
    {
        $rows = [
            ['history_id' => 1, 'product_id' => 1, 'action_type' => 'IN', 'quantity' => 5],
        ];
        $pdo = $this->stubbedPdo(1, $rows);

        $history = new InventoryHistory($pdo);
        $result = $history->getHistory();

        self::assertSame($rows, $result['history']);
        self::assertSame(1, $result['pagination']['total']);
        self::assertSame(1, $result['pagination']['total_pages']);
        self::assertFalse($result['pagination']['has_next']);
        self::assertFalse($result['pagination']['has_previous']);
    }
}
