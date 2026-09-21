<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/Models/UserManagement.php';
require_once __DIR__ . '/../../Support/MocksPdo.php';

use PHPUnit\Framework\TestCase;

final class UserManagementTest extends TestCase
{
    use MocksPdo;

    public function test_getRoles_returns_all_rows(): void
    {
        $rows = [
            ['role_id' => 1, 'role_name' => 'Administrator', 'role_description' => 'Full access'],
            ['role_id' => 2, 'role_name' => 'Cashier', 'role_description' => 'POS access'],
        ];

        $stmt = $this->createStatementMock();
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createPdoMock();
        $pdo->expects($this->once())->method('query')->willReturn($stmt);

        $model = new UserManagement($pdo);

        $this->assertSame($rows, $model->getRoles());
    }

    public function test_getUsers_returns_paginated_result(): void
    {
        $rows = [['user_id' => 1, 'username' => 'admin']];

        $stmt = $this->createStatementMock();
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(1);
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->pdoThatPrepares($stmt);
        $model = new UserManagement($pdo);

        $result = $model->getUsers([], 1, 10);

        $this->assertSame($rows, $result['users']);
        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(10, $result['per_page']);
        $this->assertSame(1, $result['total_pages']);
    }

    public function test_getUsers_clamps_page_below_one_to_one(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(0);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoThatPrepares($stmt);
        $model = new UserManagement($pdo);

        $result = $model->getUsers([], 0, 10);

        $this->assertSame(1, $result['page']);
    }

    public function test_getUsers_clamps_perPage_to_range_1_to_50(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(0);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoThatPrepares($stmt);
        $model = new UserManagement($pdo);

        $result = $model->getUsers([], 1, 999);
        $this->assertSame(50, $result['per_page']);

        $stmt2 = $this->createStatementMock();
        $stmt2->method('bindValue')->willReturn(true);
        $stmt2->method('execute')->willReturn(true);
        $stmt2->method('fetchColumn')->willReturn(0);
        $stmt2->method('fetchAll')->willReturn([]);
        $pdo2 = $this->pdoThatPrepares($stmt2);
        $model2 = new UserManagement($pdo2);

        $result2 = $model2->getUsers([], 1, 0);
        $this->assertSame(1, $result2['per_page']);
    }

    public function test_getUsers_computes_total_pages_via_ceiling(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(25);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->pdoThatPrepares($stmt);
        $model = new UserManagement($pdo);

        $result = $model->getUsers([], 1, 10);

        $this->assertSame(3, $result['total_pages']);
    }

    public function test_getUsers_applies_search_filter_to_sql(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(0);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createPdoMock();
        $pdo->expects($this->exactly(2))
            ->method('prepare')
            ->with($this->stringContains('u.username LIKE :search_username'))
            ->willReturn($stmt);

        $model = new UserManagement($pdo);
        $model->getUsers(['search' => 'ali'], 1, 10);
    }

    public function test_getUsers_ignores_invalid_role_id_filter(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(0);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createPdoMock();
        // role_id = 3 is not a recognized role, so no WHERE clause should be added
        // (the column itself still appears in the SELECT list and JOIN, so we
        // assert the filter *condition* specifically is absent).
        $pdo->expects($this->exactly(2))
            ->method('prepare')
            ->with($this->logicalNot($this->stringContains('u.role_id = :role_id')))
            ->willReturn($stmt);

        $model = new UserManagement($pdo);
        $model->getUsers(['role_id' => 3], 1, 10);
    }

    public function test_getUsers_applies_valid_role_id_filter(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(0);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createPdoMock();
        $pdo->expects($this->exactly(2))
            ->method('prepare')
            ->with($this->stringContains('u.role_id = :role_id'))
            ->willReturn($stmt);

        $model = new UserManagement($pdo);
        $model->getUsers(['role_id' => 2], 1, 10);
    }

    public function test_getUsers_ignores_invalid_account_status_filter(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(0);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createPdoMock();
        // account_status is always a selected column, so assert the filter
        // *condition* specifically is absent rather than the bare word.
        $pdo->expects($this->exactly(2))
            ->method('prepare')
            ->with($this->logicalNot($this->stringContains('u.account_status = :account_status')))
            ->willReturn($stmt);

        $model = new UserManagement($pdo);
        $model->getUsers(['account_status' => 'Bogus'], 1, 10);
    }

    public function test_getById_returns_row_when_found(): void
    {
        $row = ['user_id' => 3, 'username' => 'dana'];

        $stmt = $this->createStatementMock();
        $stmt->expects($this->once())->method('execute')->with([':user_id' => 3]);
        $stmt->method('fetch')->willReturn($row);

        $pdo = $this->pdoThatPrepares($stmt, 'FROM users');
        $model = new UserManagement($pdo);

        $this->assertSame($row, $model->getById(3));
    }

    public function test_getById_returns_false_when_missing(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->pdoThatPrepares($stmt);
        $model = new UserManagement($pdo);

        $this->assertFalse($model->getById(404));
    }

    public function test_usernameExists_without_exclude(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->expects($this->once())->method('execute')->with([':username' => 'admin']);
        $stmt->method('fetchColumn')->willReturn('1');

        $pdo = $this->createPdoMock();
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->logicalNot($this->stringContains('exclude_user_id')))
            ->willReturn($stmt);

        $model = new UserManagement($pdo);

        $this->assertTrue($model->usernameExists('admin'));
    }

    public function test_usernameExists_with_exclude_adds_condition(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->expects($this->once())
            ->method('execute')
            ->with([':username' => 'admin', ':exclude_user_id' => 5]);
        $stmt->method('fetchColumn')->willReturn(false);

        $pdo = $this->createPdoMock();
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('user_id <> :exclude_user_id'))
            ->willReturn($stmt);

        $model = new UserManagement($pdo);

        $this->assertFalse($model->usernameExists('admin', 5));
    }

    public function test_emailExists_without_exclude(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->expects($this->once())->method('execute')->with([':email' => 'a@b.com']);
        $stmt->method('fetchColumn')->willReturn('1');

        $pdo = $this->createPdoMock();
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->logicalNot($this->stringContains('exclude_user_id')))
            ->willReturn($stmt);

        $model = new UserManagement($pdo);

        $this->assertTrue($model->emailExists('a@b.com'));
    }

    public function test_emailExists_with_exclude_adds_condition(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->expects($this->once())
            ->method('execute')
            ->with([':email' => 'a@b.com', ':exclude_user_id' => 9]);
        $stmt->method('fetchColumn')->willReturn(false);

        $pdo = $this->createPdoMock();
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('user_id <> :exclude_user_id'))
            ->willReturn($stmt);

        $model = new UserManagement($pdo);

        $this->assertFalse($model->emailExists('a@b.com', 9));
    }

    public function test_create_hashes_password_and_converts_empty_optional_fields_to_null(): void
    {
        $captured = null;

        $stmt = $this->createStatementMock();
        $stmt->method('execute')
            ->willReturnCallback(function ($args) use (&$captured) {
                $captured = $args;
                return true;
            });

        $pdo = $this->pdoThatPrepares($stmt, 'INSERT INTO users');
        $pdo->method('lastInsertId')->willReturn('17');

        $model = new UserManagement($pdo);

        $newId = $model->create([
            'role_id' => 2,
            'full_name' => 'New Person',
            'username' => 'newperson',
            'email' => '',
            'password' => 'Password1',
            'contact_number' => '',
            'gender' => '',
            'birth_date' => '',
            'address' => '',
        ]);

        $this->assertSame(17, $newId);
        $this->assertNull($captured[':email']);
        $this->assertNull($captured[':contact_number']);
        $this->assertNull($captured[':gender']);
        $this->assertNull($captured[':birth_date']);
        $this->assertNull($captured[':address']);
        $this->assertNotSame('Password1', $captured[':password']);
        $this->assertTrue(password_verify('Password1', $captured[':password']));
    }

    public function test_create_keeps_provided_optional_fields(): void
    {
        $captured = null;

        $stmt = $this->createStatementMock();
        $stmt->method('execute')
            ->willReturnCallback(function ($args) use (&$captured) {
                $captured = $args;
                return true;
            });

        $pdo = $this->pdoThatPrepares($stmt);
        $pdo->method('lastInsertId')->willReturn('18');

        $model = new UserManagement($pdo);

        $model->create([
            'role_id' => 2,
            'full_name' => 'New Person',
            'username' => 'newperson2',
            'email' => 'np2@example.com',
            'password' => 'Password1',
            'contact_number' => '0900',
            'gender' => 'Female',
            'birth_date' => '2000-01-01',
            'address' => 'Manila',
        ]);

        $this->assertSame('np2@example.com', $captured[':email']);
        $this->assertSame('0900', $captured[':contact_number']);
        $this->assertSame('Female', $captured[':gender']);
        $this->assertSame('2000-01-01', $captured[':birth_date']);
        $this->assertSame('Manila', $captured[':address']);
    }

    public function test_update_without_password_change_omits_password_field(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createPdoMock();
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->logicalNot($this->stringContains('password = :password')))
            ->willReturn($stmt);

        $model = new UserManagement($pdo);

        $result = $model->update(1, [
            'role_id' => 2,
            'full_name' => 'Name',
            'username' => 'uname',
            'email' => '',
            'contact_number' => '',
            'gender' => '',
            'birth_date' => '',
            'address' => '',
            'account_status' => 'Active',
            'password' => '',
        ]);

        $this->assertTrue($result);
    }

    public function test_update_with_password_change_hashes_and_includes_password_field(): void
    {
        $captured = null;

        $stmt = $this->createStatementMock();
        $stmt->method('execute')
            ->willReturnCallback(function ($args) use (&$captured) {
                $captured = $args;
                return true;
            });

        $pdo = $this->createPdoMock();
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('password = :password'))
            ->willReturn($stmt);

        $model = new UserManagement($pdo);

        $model->update(1, [
            'role_id' => 2,
            'full_name' => 'Name',
            'username' => 'uname',
            'email' => '',
            'contact_number' => '',
            'gender' => '',
            'birth_date' => '',
            'address' => '',
            'account_status' => 'Active',
            'password' => 'NewPassword1',
        ]);

        $this->assertNotSame('NewPassword1', $captured[':password']);
        $this->assertTrue(password_verify('NewPassword1', $captured[':password']));
    }

    public function test_activeAdministratorCount_returns_count(): void
    {
        $stmt = $this->createStatementMock();
        $stmt->method('fetchColumn')->willReturn('2');

        $pdo = $this->createPdoMock();
        $pdo->expects($this->once())->method('query')->willReturn($stmt);

        $model = new UserManagement($pdo);

        $this->assertSame(2, $model->activeAdministratorCount());
    }
}
