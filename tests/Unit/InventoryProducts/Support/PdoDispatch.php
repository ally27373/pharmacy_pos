<?php

declare(strict_types=1);

/**
 * Domain-local helper (Inventory & Products test suite only) for building a
 * PDO mock whose prepare() call routes to a specific PDOStatement mock based
 * on a substring match against the SQL text.
 *
 * The Models in this domain (Inventory, InventoryHistory, DataManagement)
 * issue several different prepare()d statements per public method call
 * (validation lookups, the main query, history inserts, aggregate syncs,
 * etc). Matching by a distinguishing SQL fragment - rather than relying on
 * call order via willReturnOnConsecutiveCalls() - keeps the tests resilient
 * to incidental reordering of unrelated statements in the method body.
 */
trait PdoDispatch
{
    /**
     * @param array<string, PDOStatement&\PHPUnit\Framework\MockObject\MockObject> $sqlToStatement
     *   Map of "distinguishing SQL substring" => statement mock to return
     *   when PDO::prepare() is called with SQL containing that substring.
     *   Checked in array order; first match wins.
     */
    private function pdoDispatching(array $sqlToStatement): PDO&\PHPUnit\Framework\MockObject\MockObject
    {
        $pdo = $this->createMock(PDO::class);

        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($sqlToStatement) {
            foreach ($sqlToStatement as $needle => $stmt) {
                if (str_contains($sql, $needle)) {
                    return $stmt;
                }
            }

            self::fail('Unexpected SQL prepared, no matcher configured for: ' . $sql);
        });

        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        $pdo->method('inTransaction')->willReturn(true);

        return $pdo;
    }
}
