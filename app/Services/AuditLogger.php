<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/AuditLog.php';

class AuditLogger
{
    public static function log(
        string $actionType,
        string $moduleName,
        string $description,
        ?int $recordId = null
    ): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);

        // Audit records require a valid user_id because audit_logs.user_id is NOT NULL.
        if ($userId <= 0) {
            return false;
        }

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        try {
            $auditLog = new AuditLog();

            return $auditLog->create(
                $userId,
                $actionType,
                $moduleName,
                $description,
                $recordId,
                $ipAddress,
                $userAgent
            );
        } catch (Throwable $e) {
            // Audit logging must not break the main transaction.
            error_log('AuditLogger error: ' . $e->getMessage());
            return false;
        }
    }
}
