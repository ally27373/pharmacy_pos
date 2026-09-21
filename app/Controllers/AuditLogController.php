<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/AuditLog.php';

class AuditLogController
{
    private AuditLog $auditLog;

    public function __construct(?AuditLog $auditLog = null)
    {
        $this->auditLog = $auditLog ?? new AuditLog();
    }

    public function getLogs(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        return $this->auditLog->getLogs($filters, $page, $perPage);
    }

    public function countLogs(array $filters = []): int
    {
        return $this->auditLog->countLogs($filters);
    }

    public function getActionTypes(): array
    {
        return $this->auditLog->getActionTypes();
    }

    public function getModules(): array
    {
        return $this->auditLog->getModules();
    }
}
