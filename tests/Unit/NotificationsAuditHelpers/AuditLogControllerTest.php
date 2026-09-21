<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../app/Models/AuditLog.php';
require_once __DIR__ . '/../../../app/Controllers/AuditLogController.php';

final class AuditLogControllerTest extends TestCase
{
    public function test_get_logs_delegates_filters_page_and_per_page_to_the_model(): void
    {
        $filters = ['action_type' => 'CREATE'];
        $expected = [['audit_id' => 1]];

        $auditLog = $this->createMock(AuditLog::class);
        $auditLog->expects($this->once())
            ->method('getLogs')
            ->with($filters, 2, 50)
            ->willReturn($expected);

        $controller = new AuditLogController($auditLog);

        $this->assertSame($expected, $controller->getLogs($filters, 2, 50));
    }

    public function test_get_logs_uses_documented_defaults_when_called_with_no_arguments(): void
    {
        $auditLog = $this->createMock(AuditLog::class);
        $auditLog->expects($this->once())
            ->method('getLogs')
            ->with([], 1, 25)
            ->willReturn([]);

        $controller = new AuditLogController($auditLog);
        $controller->getLogs();
    }

    public function test_count_logs_delegates_filters_to_the_model(): void
    {
        $filters = ['module_name' => 'Sales'];

        $auditLog = $this->createMock(AuditLog::class);
        $auditLog->expects($this->once())
            ->method('countLogs')
            ->with($filters)
            ->willReturn(9);

        $controller = new AuditLogController($auditLog);

        $this->assertSame(9, $controller->countLogs($filters));
    }

    public function test_get_action_types_delegates_to_the_model(): void
    {
        $auditLog = $this->createMock(AuditLog::class);
        $auditLog->expects($this->once())
            ->method('getActionTypes')
            ->willReturn(['CREATE', 'UPDATE', 'DELETE', 'IMPORT', 'EXPORT']);

        $controller = new AuditLogController($auditLog);

        $this->assertSame(
            ['CREATE', 'UPDATE', 'DELETE', 'IMPORT', 'EXPORT'],
            $controller->getActionTypes()
        );
    }

    public function test_get_modules_delegates_to_the_model(): void
    {
        $auditLog = $this->createMock(AuditLog::class);
        $auditLog->expects($this->once())
            ->method('getModules')
            ->willReturn(['Inventory', 'Sales']);

        $controller = new AuditLogController($auditLog);

        $this->assertSame(['Inventory', 'Sales'], $controller->getModules());
    }
}
