<?php

declare(strict_types=1);

/**
 * Shared helper for unit-testing classes that talk to the database
 * through a PDO connection, without touching a real MySQL instance.
 *
 * Use together with the constructor-injection seam described in
 * tests/TESTING.md: production classes accept an optional PDO/model
 * argument that defaults to the real dependency, and tests pass a
 * mock built with these helpers instead.
 */
trait MocksPdo
{
    protected function createPdoMock(): PDO&\PHPUnit\Framework\MockObject\MockObject
    {
        return $this->createMock(PDO::class);
    }

    protected function createStatementMock(): PDOStatement&\PHPUnit\Framework\MockObject\MockObject
    {
        return $this->createMock(PDOStatement::class);
    }

    /**
     * Wires a PDO mock so ->prepare() returns the given statement mock,
     * covering the most common "prepare then execute" path used across
     * the app's Models.
     */
    protected function pdoThatPrepares(
        PDOStatement&\PHPUnit\Framework\MockObject\MockObject $statement,
        ?string $expectedSqlContains = null
    ): PDO&\PHPUnit\Framework\MockObject\MockObject {
        $pdo = $this->createPdoMock();

        $prepare = $pdo->expects($this->atLeastOnce())->method('prepare');

        if ($expectedSqlContains !== null) {
            $prepare->with($this->stringContains($expectedSqlContains));
        }

        $prepare->willReturn($statement);

        return $pdo;
    }
}
