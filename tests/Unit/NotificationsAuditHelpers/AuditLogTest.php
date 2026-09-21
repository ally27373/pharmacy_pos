<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../Support/MocksPdo.php';
require_once __DIR__ . '/../../../app/Models/AuditLog.php';

final class AuditLogTest extends TestCase
{
    use MocksPdo;

    // ---------------------------------------------------------------
    // create()
    // ---------------------------------------------------------------

    public function test_create_with_valid_action_type_executes_insert_with_all_fields(): void
    {
        $captured = null;

        $statement = $this->createStatementMock();
        $statement->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params) use (&$captured) {
                $captured = $params;
                return true;
            }))
            ->willReturn(true);

        $pdo = $this->pdoThatPrepares($statement, 'INSERT INTO audit_logs');

        $auditLog = new AuditLog($pdo);

        $result = $auditLog->create(
            5,
            'CREATE',
            'Products',
            'Created product Paracetamol',
            42,
            '127.0.0.1',
            'PHPUnit-Agent/1.0'
        );

        $this->assertTrue($result);
        $this->assertSame([
            ':user_id' => 5,
            ':action_type' => 'CREATE',
            ':module_name' => 'Products',
            ':record_id' => 42,
            ':description' => 'Created product Paracetamol',
            ':ip_address' => '127.0.0.1',
            ':user_agent' => 'PHPUnit-Agent/1.0',
        ], $captured);
    }

    public function test_create_defaults_optional_fields_to_null(): void
    {
        $captured = null;

        $statement = $this->createStatementMock();
        $statement->method('execute')
            ->with($this->callback(function (array $params) use (&$captured) {
                $captured = $params;
                return true;
            }))
            ->willReturn(true);

        $pdo = $this->pdoThatPrepares($statement);

        $auditLog = new AuditLog($pdo);
        $auditLog->create(1, 'DELETE', 'Inventory', 'Deleted a record');

        $this->assertNull($captured[':record_id']);
        $this->assertNull($captured[':ip_address']);
        $this->assertNull($captured[':user_agent']);
    }

    #[DataProvider('validActionTypeProvider')]
    public function test_create_accepts_every_documented_action_type(string $actionType): void
    {
        $statement = $this->createStatementMock();
        $statement->method('execute')->willReturn(true);

        $pdo = $this->pdoThatPrepares($statement);

        $auditLog = new AuditLog($pdo);

        $this->assertTrue($auditLog->create(1, $actionType, 'Products', 'desc'));
    }

    public static function validActionTypeProvider(): array
    {
        return [
            ['CREATE'],
            ['UPDATE'],
            ['DELETE'],
            ['IMPORT'],
            ['EXPORT'],
        ];
    }

    public function test_create_rejects_an_action_type_outside_the_allow_list(): void
    {
        $pdo = $this->createPdoMock();
        $pdo->expects($this->never())->method('prepare');

        $auditLog = new AuditLog($pdo);

        $this->expectException(InvalidArgumentException::class);
        $auditLog->create(1, 'READ', 'Products', 'desc');
    }

    public function test_create_rejects_lowercase_action_type_because_comparison_is_case_sensitive(): void
    {
        $pdo = $this->createPdoMock();
        $pdo->expects($this->never())->method('prepare');

        $auditLog = new AuditLog($pdo);

        $this->expectException(InvalidArgumentException::class);
        $auditLog->create(1, 'create', 'Products', 'desc');
    }

    // ---------------------------------------------------------------
    // getLogs()
    // ---------------------------------------------------------------

    public function test_get_logs_default_paging_binds_limit_25_and_offset_0(): void
    {
        $bound = [];

        $statement = $this->createStatementMock();
        $statement->method('bindValue')
            ->willReturnCallback(function (string $key, $value, int $type = PDO::PARAM_STR) use (&$bound) {
                $bound[$key] = [$value, $type];
                return true;
            });
        $statement->expects($this->once())->method('execute');
        $statement->method('fetchAll')->willReturn([['audit_id' => 1]]);

        $pdo = $this->pdoThatPrepares($statement, 'LIMIT :limit OFFSET :offset');

        $auditLog = new AuditLog($pdo);
        $result = $auditLog->getLogs();

        $this->assertSame([25, PDO::PARAM_INT], $bound[':limit']);
        $this->assertSame([0, PDO::PARAM_INT], $bound[':offset']);
        $this->assertSame([['audit_id' => 1]], $result);
    }

    public function test_get_logs_computes_offset_from_page_and_per_page(): void
    {
        $bound = [];

        $statement = $this->createStatementMock();
        $statement->method('bindValue')
            ->willReturnCallback(function (string $key, $value, int $type = PDO::PARAM_STR) use (&$bound) {
                $bound[$key] = $value;
                return true;
            });
        $statement->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoThatPrepares($statement);

        $auditLog = new AuditLog($pdo);
        $auditLog->getLogs([], 3, 10);

        $this->assertSame(10, $bound[':limit']);
        $this->assertSame(20, $bound[':offset']);
    }

    public function test_get_logs_clamps_non_positive_page_up_to_one(): void
    {
        $bound = [];

        $statement = $this->createStatementMock();
        $statement->method('bindValue')
            ->willReturnCallback(function (string $key, $value) use (&$bound) {
                $bound[$key] = $value;
                return true;
            });
        $statement->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoThatPrepares($statement);

        $auditLog = new AuditLog($pdo);
        $auditLog->getLogs([], -5, 10);

        // page clamped to 1 => offset = (1-1)*10 = 0
        $this->assertSame(0, $bound[':offset']);
    }

    public function test_get_logs_clamps_per_page_between_one_and_one_hundred(): void
    {
        $bound = [];
        $statement = $this->createStatementMock();
        $statement->method('bindValue')
            ->willReturnCallback(function (string $key, $value) use (&$bound) {
                $bound[$key] = $value;
                return true;
            });
        $statement->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoThatPrepares($statement);

        $auditLog = new AuditLog($pdo);
        $auditLog->getLogs([], 1, 500);

        $this->assertSame(100, $bound[':limit']);
    }

    public function test_get_logs_clamps_per_page_up_to_one_when_zero_or_negative(): void
    {
        $bound = [];
        $statement = $this->createStatementMock();
        $statement->method('bindValue')
            ->willReturnCallback(function (string $key, $value) use (&$bound) {
                $bound[$key] = $value;
                return true;
            });
        $statement->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoThatPrepares($statement);

        $auditLog = new AuditLog($pdo);
        $auditLog->getLogs([], 1, -10);

        $this->assertSame(1, $bound[':limit']);
    }

    public function test_get_logs_builds_where_clause_and_binds_every_filter(): void
    {
        $bound = [];
        $capturedSql = null;

        $statement = $this->createStatementMock();
        $statement->method('bindValue')
            ->willReturnCallback(function (string $key, $value) use (&$bound) {
                $bound[$key] = $value;
                return true;
            });
        $statement->method('fetchAll')->willReturn([]);

        $pdo = $this->createPdoMock();
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function (string $sql) use (&$capturedSql) {
                $capturedSql = $sql;
                return true;
            }))
            ->willReturn($statement);

        $auditLog = new AuditLog($pdo);
        $auditLog->getLogs([
            'action_type' => 'CREATE',
            'module_name' => 'Products',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'search' => 'paracetamol',
        ]);

        $this->assertStringContainsString('a.action_type = :action_type', $capturedSql);
        $this->assertStringContainsString('a.module_name = :module_name', $capturedSql);
        $this->assertStringContainsString('DATE(a.created_at) >= :date_from', $capturedSql);
        $this->assertStringContainsString('DATE(a.created_at) <= :date_to', $capturedSql);
        $this->assertStringContainsString('a.description LIKE :search_description', $capturedSql);

        $this->assertSame('CREATE', $bound[':action_type']);
        $this->assertSame('Products', $bound[':module_name']);
        $this->assertSame('2026-01-01', $bound[':date_from']);
        $this->assertSame('2026-01-31', $bound[':date_to']);
        $this->assertSame('%paracetamol%', $bound[':search_description']);
        $this->assertSame('%paracetamol%', $bound[':search_module']);
        $this->assertSame('%paracetamol%', $bound[':search_username']);
        $this->assertSame('%paracetamol%', $bound[':search_full_name']);
    }

    public function test_get_logs_omits_where_clause_when_no_filters_given(): void
    {
        $capturedSql = null;

        $statement = $this->createStatementMock();
        $statement->method('bindValue')->willReturn(true);
        $statement->method('fetchAll')->willReturn([]);

        $pdo = $this->createPdoMock();
        $pdo->method('prepare')
            ->with($this->callback(function (string $sql) use (&$capturedSql) {
                $capturedSql = $sql;
                return true;
            }))
            ->willReturn($statement);

        $auditLog = new AuditLog($pdo);
        $auditLog->getLogs();

        $this->assertStringNotContainsString('WHERE', $capturedSql);
    }

    // ---------------------------------------------------------------
    // countLogs()
    // ---------------------------------------------------------------

    public function test_count_logs_returns_integer_cast_of_fetch_column(): void
    {
        $statement = $this->createStatementMock();
        $statement->expects($this->once())->method('execute')->with([]);
        $statement->method('fetchColumn')->willReturn('7');

        $pdo = $this->pdoThatPrepares($statement, 'SELECT COUNT(*)');

        $auditLog = new AuditLog($pdo);

        $this->assertSame(7, $auditLog->countLogs());
    }

    public function test_count_logs_passes_filter_params_directly_to_execute(): void
    {
        $statement = $this->createStatementMock();
        $statement->expects($this->once())
            ->method('execute')
            ->with([':action_type' => 'DELETE']);
        $statement->method('fetchColumn')->willReturn('3');

        $pdo = $this->pdoThatPrepares($statement);

        $auditLog = new AuditLog($pdo);

        $this->assertSame(3, $auditLog->countLogs(['action_type' => 'DELETE']));
    }

    // ---------------------------------------------------------------
    // getActionTypes() / getModules()
    // ---------------------------------------------------------------

    public function test_get_action_types_returns_the_fixed_allow_list_in_order(): void
    {
        $pdo = $this->createPdoMock();
        $auditLog = new AuditLog($pdo);

        $this->assertSame(
            ['CREATE', 'UPDATE', 'DELETE', 'IMPORT', 'EXPORT'],
            $auditLog->getActionTypes()
        );
    }

    public function test_get_modules_queries_distinct_module_names(): void
    {
        $statement = $this->createStatementMock();
        $statement->method('fetchAll')
            ->with(PDO::FETCH_COLUMN)
            ->willReturn(['Inventory', 'Products', 'Sales']);

        $pdo = $this->createPdoMock();
        $pdo->expects($this->once())
            ->method('query')
            ->with($this->stringContains('DISTINCT module_name'))
            ->willReturn($statement);

        $auditLog = new AuditLog($pdo);

        $this->assertSame(['Inventory', 'Products', 'Sales'], $auditLog->getModules());
    }
}
