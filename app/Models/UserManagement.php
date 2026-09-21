<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

class UserManagement
{
    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? (new Database())->connect();
    }

    public function getRoles(): array
    {
        $stmt = $this->conn->query(
            "SELECT role_id, role_name, role_description
             FROM roles
             ORDER BY role_id ASC"
        );

        return $stmt->fetchAll();
    }

    public function getUsers(array $filters, int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(u.username LIKE :search_username
                      OR u.full_name LIKE :search_full_name
                      OR u.email LIKE :search_email)';
            $value = '%' . trim((string)$filters['search']) . '%';
            $params[':search_username'] = $value;
            $params[':search_full_name'] = $value;
            $params[':search_email'] = $value;
        }

        if (!empty($filters['role_id']) && in_array((int)$filters['role_id'], [1, 2], true)) {
            $where[] = 'u.role_id = :role_id';
            $params[':role_id'] = (int)$filters['role_id'];
        }

        if (!empty($filters['account_status']) && in_array($filters['account_status'], ['Active', 'Inactive', 'Locked'], true)) {
            $where[] = 'u.account_status = :account_status';
            $params[':account_status'] = $filters['account_status'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->conn->prepare(
            "SELECT COUNT(*)
             FROM users u
             $whereSql"
        );
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT
                u.user_id,
                u.role_id,
                r.role_name,
                u.full_name,
                u.username,
                u.email,
                u.contact_number,
                u.gender,
                u.birth_date,
                u.address,
                u.account_status,
                u.failed_login_attempts,
                u.last_login,
                u.created_at,
                u.updated_at
            FROM users u
            INNER JOIN roles r ON r.role_id = u.role_id
            $whereSql
            ORDER BY u.user_id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'users' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    public function getById(int $userId): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT user_id, role_id, full_name, username, email, contact_number,
                    gender, birth_date, address, account_status
             FROM users
             WHERE user_id = :user_id
             LIMIT 1"
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch();
    }

    public function usernameExists(string $username, ?int $excludeUserId = null): bool
    {
        $sql = "SELECT user_id FROM users WHERE username = :username";
        $params = [':username' => $username];
        if ($excludeUserId !== null) {
            $sql .= ' AND user_id <> :exclude_user_id';
            $params[':exclude_user_id'] = $excludeUserId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }

    public function emailExists(string $email, ?int $excludeUserId = null): bool
    {
        $sql = "SELECT user_id FROM users WHERE email = :email";
        $params = [':email' => $email];
        if ($excludeUserId !== null) {
            $sql .= ' AND user_id <> :exclude_user_id';
            $params[':exclude_user_id'] = $excludeUserId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO users
                (role_id, full_name, username, email, password, contact_number,
                 gender, birth_date, address, account_status)
                VALUES
                (:role_id, :full_name, :username, :email, :password, :contact_number,
                 :gender, :birth_date, :address, 'Active')";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':role_id' => (int)$data['role_id'],
            ':full_name' => $data['full_name'],
            ':username' => $data['username'],
            ':email' => $data['email'] !== '' ? $data['email'] : null,
            ':password' => password_hash($data['password'], PASSWORD_DEFAULT),
            ':contact_number' => $data['contact_number'] !== '' ? $data['contact_number'] : null,
            ':gender' => $data['gender'] !== '' ? $data['gender'] : null,
            ':birth_date' => $data['birth_date'] !== '' ? $data['birth_date'] : null,
            ':address' => $data['address'] !== '' ? $data['address'] : null,
        ]);

        return (int)$this->conn->lastInsertId();
    }

    public function update(int $userId, array $data): bool
    {
        $fields = [
            'role_id = :role_id',
            'full_name = :full_name',
            'username = :username',
            'email = :email',
            'contact_number = :contact_number',
            'gender = :gender',
            'birth_date = :birth_date',
            'address = :address',
            'account_status = :account_status',
        ];

        $params = [
            ':role_id' => (int)$data['role_id'],
            ':full_name' => $data['full_name'],
            ':username' => $data['username'],
            ':email' => $data['email'] !== '' ? $data['email'] : null,
            ':contact_number' => $data['contact_number'] !== '' ? $data['contact_number'] : null,
            ':gender' => $data['gender'] !== '' ? $data['gender'] : null,
            ':birth_date' => $data['birth_date'] !== '' ? $data['birth_date'] : null,
            ':address' => $data['address'] !== '' ? $data['address'] : null,
            ':account_status' => $data['account_status'],
            ':user_id' => $userId,
        ];

        if ($data['password'] !== '') {
            $fields[] = 'password = :password';
            $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $stmt = $this->conn->prepare(
            'UPDATE users SET ' . implode(', ', $fields) . ' WHERE user_id = :user_id'
        );

        return $stmt->execute($params);
    }

    public function activeAdministratorCount(): int
    {
        $stmt = $this->conn->query(
            "SELECT COUNT(*) FROM users WHERE role_id = 1 AND account_status = 'Active'"
        );
        return (int)$stmt->fetchColumn();
    }
}
