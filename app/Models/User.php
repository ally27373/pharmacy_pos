<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

class User
{
    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    public function testConnection(): bool
{
    return $this->conn instanceof PDO;
}

public function usernameExists(string $username): bool
{
    $sql = "
        SELECT user_id
        FROM users
        WHERE username = :username
        LIMIT 1
    ";

    $stmt = $this->conn->prepare($sql);

    $stmt->bindParam(
        ':username',
        $username,
        PDO::PARAM_STR
    );

    $stmt->execute();

    return $stmt->rowCount() > 0;
}

public function emailExists(string $email): bool
{
    $sql = "
        SELECT user_id
        FROM users
        WHERE email = :email
        LIMIT 1
    ";

    $stmt = $this->conn->prepare($sql);

    $stmt->bindParam(
        ':email',
        $email,
        PDO::PARAM_STR
    );

    $stmt->execute();

    return $stmt->rowCount() > 0;
}

public function register(
    string $fullName,
    string $username,
    string $email,
    string $password,
    int $roleId = 2
): bool {

    // Check username
    if ($this->usernameExists($username)) {
        return false;
    }

    // Check email
    if ($this->emailExists($email)) {
        return false;
    }

    // Hash password
    $hashedPassword = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    $sql = "
        INSERT INTO users
        (
            full_name,
            username,
            email,
            password,
            role_id
        )

        VALUES
        (
            :full_name,
            :username,
            :email,
            :password,
            :role_id
        )
    ";

    $stmt = $this->conn->prepare($sql);

    return $stmt->execute([

        ':full_name' => $fullName,

        ':username' => $username,

        ':email' => $email,

        ':password' => $hashedPassword,

        ':role_id' => $roleId

    ]);
}

public function findByUsernameOrEmail(string $login): array|false
{
    $sql = "
        SELECT *
        FROM users
        WHERE username = ?
           OR email = ?
        LIMIT 1
    ";

    $stmt = $this->conn->prepare($sql);

    $stmt->execute([$login, $login]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function recordLastLogin(int $userId): bool
{
    $stmt = $this->conn->prepare("
        UPDATE users
        SET last_login = NOW()
        WHERE user_id = :user_id
        LIMIT 1
    ");

    return $stmt->execute([
        ':user_id' => $userId
    ]);
}

public function getById(int $userId): array|false
{
    $sql = "
        SELECT *
        FROM users
        WHERE user_id = :user_id
        LIMIT 1
    ";

    $stmt = $this->conn->prepare($sql);

    $stmt->execute([
        ':user_id' => $userId
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

}

