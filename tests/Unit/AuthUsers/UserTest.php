<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/Models/User.php';
require_once __DIR__ . '/../../Support/MocksPdo.php';

use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    use MocksPdo;

    public function test_testConnection_returns_true_for_pdo_instance(): void
    {
        $pdo = $this->createPdoMock();
        $user = new User($pdo);

        $this->assertTrue($user->testConnection());
    }

    public function test_usernameExists_returns_true_when_row_found(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('bindParam')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(1);

        $pdo = $this->pdoThatPrepares($stmt, 'FROM users');
        $user = new User($pdo);

        $this->assertTrue($user->usernameExists('alice'));
    }

    public function test_usernameExists_returns_false_when_no_row_found(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('bindParam')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);

        $pdo = $this->pdoThatPrepares($stmt);
        $user = new User($pdo);

        $this->assertFalse($user->usernameExists('nobody'));
    }

    public function test_emailExists_returns_true_when_row_found(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('bindParam')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(1);

        $pdo = $this->pdoThatPrepares($stmt, 'FROM users');
        $user = new User($pdo);

        $this->assertTrue($user->emailExists('alice@example.com'));
    }

    public function test_emailExists_returns_false_when_no_row_found(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('bindParam')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);

        $pdo = $this->pdoThatPrepares($stmt);
        $user = new User($pdo);

        $this->assertFalse($user->emailExists('nobody@example.com'));
    }

    public function test_register_fails_fast_when_username_already_exists(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('bindParam')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        // Only usernameExists() runs; it must short-circuit before any insert.
        $stmt->method('rowCount')->willReturn(1);

        $pdo = $this->createPdoMock();
        // Exactly one prepare() call: the username-existence check.
        $pdo->expects($this->once())->method('prepare')->willReturn($stmt);

        $user = new User($pdo);

        $this->assertFalse(
            $user->register('Alice A', 'alice', 'alice@example.com', 'Password1')
        );
    }

    public function test_register_fails_fast_when_email_already_exists(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('bindParam')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        // First call (usernameExists) -> 0 rows, second call (emailExists) -> 1 row.
        $stmt->method('rowCount')->willReturnOnConsecutiveCalls(0, 1);

        $pdo = $this->createPdoMock();
        // Exactly two prepare() calls: username check + email check, no insert.
        $pdo->expects($this->exactly(2))->method('prepare')->willReturn($stmt);

        $user = new User($pdo);

        $this->assertFalse(
            $user->register('Alice A', 'alice', 'alice@example.com', 'Password1')
        );
    }

    public function test_register_hashes_password_and_inserts_when_available(): void
    {
        $capturedInsertParams = null;

        $stmt = $this->createStatementMock();
        $stmt->method('bindParam')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);
        $stmt->method('execute')
            ->willReturnCallback(function ($args = null) use (&$capturedInsertParams) {
                if (is_array($args)) {
                    $capturedInsertParams = $args;
                }
                return true;
            });

        $pdo = $this->createPdoMock();
        $pdo->expects($this->exactly(3))->method('prepare')->willReturn($stmt);

        $user = new User($pdo);

        $result = $user->register('Alice A', 'alice', 'alice@example.com', 'Password1');

        $this->assertTrue($result);
        $this->assertIsArray($capturedInsertParams);
        $this->assertSame('Alice A', $capturedInsertParams[':full_name']);
        $this->assertSame('alice', $capturedInsertParams[':username']);
        $this->assertSame('alice@example.com', $capturedInsertParams[':email']);
        $this->assertSame(2, $capturedInsertParams[':role_id']); // default role

        // Password must never be stored/compared in plaintext.
        $this->assertNotSame('Password1', $capturedInsertParams[':password']);
        $this->assertTrue(password_verify('Password1', $capturedInsertParams[':password']));
    }

    public function test_register_accepts_custom_role_id(): void
    {
        $capturedInsertParams = null;

        $stmt = $this->createStatementMock();
        $stmt->method('bindParam')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);
        $stmt->method('execute')
            ->willReturnCallback(function ($args = null) use (&$capturedInsertParams) {
                if (is_array($args)) {
                    $capturedInsertParams = $args;
                }
                return true;
            });

        $pdo = $this->createPdoMock();
        $pdo->method('prepare')->willReturn($stmt);

        $user = new User($pdo);
        $user->register('Bob B', 'bob', 'bob@example.com', 'Password1', 1);

        $this->assertSame(1, $capturedInsertParams[':role_id']);
    }

    public function test_findByUsernameOrEmail_returns_row_when_found(): void
    {
        $row = [
            'user_id' => 5,
            'username' => 'alice',
            'email' => 'alice@example.com',
            'password' => password_hash('Password1', PASSWORD_DEFAULT),
            'role_id' => 2,
            'account_status' => 'Active',
        ];

        $stmt = $this->createStatementMock();
        $stmt->expects($this->once())->method('execute')->with(['alice', 'alice']);
        $stmt->method('fetch')->willReturn($row);

        $pdo = $this->pdoThatPrepares($stmt, 'FROM users');
        $user = new User($pdo);

        $this->assertSame($row, $user->findByUsernameOrEmail('alice'));
    }

    public function test_findByUsernameOrEmail_returns_false_when_not_found(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->pdoThatPrepares($stmt);
        $user = new User($pdo);

        $this->assertFalse($user->findByUsernameOrEmail('nobody'));
    }

    public function test_recordLastLogin_executes_update_with_user_id(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->expects($this->once())
            ->method('execute')
            ->with([':user_id' => 42])
            ->willReturn(true);

        $pdo = $this->pdoThatPrepares($stmt, 'UPDATE users');
        $user = new User($pdo);

        $this->assertTrue($user->recordLastLogin(42));
    }

    public function test_recordLastLogin_returns_false_when_execute_fails(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('execute')->willReturn(false);

        $pdo = $this->pdoThatPrepares($stmt);
        $user = new User($pdo);

        $this->assertFalse($user->recordLastLogin(42));
    }

    public function test_getById_returns_row_when_found(): void
    {
        $row = ['user_id' => 7, 'username' => 'carol'];

        $stmt = $this->createStatementMock();
        $stmt->expects($this->once())->method('execute')->with([':user_id' => 7]);
        $stmt->method('fetch')->willReturn($row);

        $pdo = $this->pdoThatPrepares($stmt, 'FROM users');
        $user = new User($pdo);

        $this->assertSame($row, $user->getById(7));
    }

    public function test_getById_returns_false_when_not_found(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->pdoThatPrepares($stmt);
        $user = new User($pdo);

        $this->assertFalse($user->getById(999));
    }
}
