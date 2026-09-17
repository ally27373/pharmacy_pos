<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

class AuditLog
{
    private PDO $conn;

    private const ACTION_TYPES = [
        'CREATE', 'UPDATE', 'DELETE', 'IMPORT', 'EXPORT'
    ];

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    public function create(
        int $userId,
        string $actionType,
        string $moduleName,
        string $description,
        ?int $recordId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): bool {
        if (!in_array($actionType, self::ACTION_TYPES, true)) {
            throw new InvalidArgumentException('Invalid audit action type.');
        }

        $sql = "
            INSERT INTO audit_logs
            (
                user_id,
                action_type,
                module_name,
                record_id,
                description,
                ip_address,
                user_agent
            )
            VALUES
            (
                :user_id,
                :action_type,
                :module_name,
                :record_id,
                :description,
                :ip_address,
                :user_agent
            )
        ";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':user_id'     => $userId,
            ':action_type' => $actionType,
            ':module_name' => $moduleName,
            ':record_id'   => $recordId,
            ':description' => $description,
            ':ip_address'  => $ipAddress,
            ':user_agent'  => $userAgent,
        ]);
    }

    /**
     * Get paginated audit records with optional filters.
     */
    public function getLogs(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        [$whereSql, $params] = $this->buildFilterQuery($filters);

        $sql = "
            SELECT
                a.audit_id,
                a.user_id,
                COALESCE(u.username, 'Unknown / Deleted User') AS username,
                COALESCE(u.full_name, '—') AS full_name,
                a.action_type,
                a.module_name,
                a.record_id,
                a.description,
                a.ip_address,
                a.user_agent,
                a.created_at
            FROM audit_logs a
            LEFT JOIN users u
                ON u.user_id = a.user_id
            {$whereSql}
            ORDER BY a.created_at DESC, a.audit_id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->conn->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count audit records matching the current filters.
     */
    public function countLogs(array $filters = []): int
    {
        [$whereSql, $params] = $this->buildFilterQuery($filters);

        $sql = "
            SELECT COUNT(*)
            FROM audit_logs a
            LEFT JOIN users u
                ON u.user_id = a.user_id
            {$whereSql}
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function buildFilterQuery(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['action_type'])) {
            $where[] = 'a.action_type = :action_type';
            $params[':action_type'] = $filters['action_type'];
        }

        if (!empty($filters['module_name'])) {
            $where[] = 'a.module_name = :module_name';
            $params[':module_name'] = $filters['module_name'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(a.created_at) >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(a.created_at) <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(
                a.description LIKE :search_description
                OR a.module_name LIKE :search_module
                OR u.username LIKE :search_username
                OR u.full_name LIKE :search_full_name
            )';

            $searchValue = '%' . $filters['search'] . '%';
            $params[':search_description'] = $searchValue;
            $params[':search_module'] = $searchValue;
            $params[':search_username'] = $searchValue;
            $params[':search_full_name'] = $searchValue;
        }

        return [
            $where ? 'WHERE ' . implode(' AND ', $where) : '',
            $params,
        ];
    }

    public function getActionTypes(): array
    {
        return self::ACTION_TYPES;
    }

    public function getModules(): array
    {
        $sql = "
            SELECT DISTINCT module_name
            FROM audit_logs
            ORDER BY module_name ASC
        ";

        return $this->conn->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    }
}
