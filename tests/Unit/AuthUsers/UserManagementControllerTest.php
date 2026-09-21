<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/Controllers/UserManagementController.php';

use PHPUnit\Framework\TestCase;

final class UserManagementControllerTest extends TestCase
{
    protected function setUp(): void
    {
        // Keep $_SESSION empty so AuditLogger::log() (called internally on
        // successful save()) short-circuits without touching any model —
        // it requires a valid $_SESSION['user_id'] > 0 to do anything.
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function validNewUserData(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'New Person',
            'username' => 'newperson',
            'email' => 'newperson@example.com',
            'password' => 'Password1',
            'role_id' => '2',
            'account_status' => 'Active',
        ], $overrides);
    }

    private function validEditUserData(array $overrides = []): array
    {
        return array_merge([
            'user_id' => '5',
            'full_name' => 'Existing Person',
            'username' => 'existing',
            'email' => 'existing@example.com',
            'password' => '',
            'role_id' => '2',
            'account_status' => 'Active',
        ], $overrides);
    }

    // --------------------------------------------------------------
    // index()
    // --------------------------------------------------------------

    public function test_index_combines_roles_and_paginated_users(): void
    {
        $model = $this->createMock(UserManagement::class);
        $model->method('getRoles')->willReturn([['role_id' => 1, 'role_name' => 'Administrator']]);
        $model->method('getUsers')->willReturn(['users' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'total_pages' => 1]);

        $controller = new UserManagementController($model);

        $result = $controller->index([], 1);

        $this->assertArrayHasKey('roles', $result);
        $this->assertArrayHasKey('result', $result);
    }

    // --------------------------------------------------------------
    // save() - validation
    // --------------------------------------------------------------

    public function test_save_requires_full_name_username_and_role(): void
    {
        $model = $this->createMock(UserManagement::class);
        $controller = new UserManagementController($model);

        $result = $controller->save($this->validNewUserData(['full_name' => '']));

        $this->assertFalse($result['success']);
        $this->assertSame('Full name, username, and role are required.', $result['message']);
    }

    public function test_save_rejects_invalid_role(): void
    {
        $model = $this->createMock(UserManagement::class);
        $controller = new UserManagementController($model);

        $result = $controller->save($this->validNewUserData(['role_id' => '99']));

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid role selected.', $result['message']);
    }

    public function test_save_rejects_new_admin_account_creation(): void
    {
        // Comment: "New employee accounts created from User Management are
        // Cashier accounts only."
        $model = $this->createMock(UserManagement::class);
        $controller = new UserManagementController($model);

        $result = $controller->save($this->validNewUserData(['role_id' => '1']));

        $this->assertFalse($result['success']);
        $this->assertSame(
            'New employee accounts can only be created with the Cashier role.',
            $result['message']
        );
    }

    public function test_save_rejects_invalid_account_status(): void
    {
        $model = $this->createMock(UserManagement::class);
        $controller = new UserManagementController($model);

        $result = $controller->save($this->validNewUserData(['account_status' => 'Bogus']));

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid account status.', $result['message']);
    }

    public function test_save_rejects_invalid_email(): void
    {
        $model = $this->createMock(UserManagement::class);
        $controller = new UserManagementController($model);

        $result = $controller->save($this->validNewUserData(['email' => 'not-an-email']));

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid email address.', $result['message']);
    }

    public function test_save_allows_empty_email_for_new_user(): void
    {
        $model = $this->createMock(UserManagement::class);
        $model->method('usernameExists')->willReturn(false);
        $model->method('create')->willReturn(1);

        $controller = new UserManagementController($model);

        $result = $controller->save($this->validNewUserData(['email' => '']));

        $this->assertTrue($result['success']);
    }

    public function test_save_requires_minimum_password_length_for_new_users(): void
    {
        $model = $this->createMock(UserManagement::class);
        $controller = new UserManagementController($model);

        $result = $controller->save($this->validNewUserData(['password' => 'short']));

        $this->assertFalse($result['success']);
        $this->assertSame(
            'New users must have a password of at least 8 characters.',
            $result['message']
        );
    }

    public function test_save_rejects_existing_username(): void
    {
        $model = $this->createMock(UserManagement::class);
        $model->method('usernameExists')->willReturn(true);

        $controller = new UserManagementController($model);

        $result = $controller->save($this->validNewUserData());

        $this->assertFalse($result['success']);
        $this->assertSame('Username already exists.', $result['message']);
    }

    public function test_save_rejects_existing_email(): void
    {
        $model = $this->createMock(UserManagement::class);
        $model->method('usernameExists')->willReturn(false);
        $model->method('emailExists')->willReturn(true);

        $controller = new UserManagementController($model);

        $result = $controller->save($this->validNewUserData());

        $this->assertFalse($result['success']);
        $this->assertSame('Email already exists.', $result['message']);
    }

    // --------------------------------------------------------------
    // save() - create path
    // --------------------------------------------------------------

    public function test_save_creates_new_cashier_successfully(): void
    {
        $model = $this->createMock(UserManagement::class);
        $model->method('usernameExists')->willReturn(false);
        $model->method('emailExists')->willReturn(false);
        $model->expects($this->once())->method('create')->willReturn(42);

        $controller = new UserManagementController($model);

        $result = $controller->save($this->validNewUserData());

        $this->assertTrue($result['success']);
        $this->assertSame('User created successfully.', $result['message']);
    }

    public function test_save_handles_pdo_exception_during_create(): void
    {
        $model = $this->createMock(UserManagement::class);
        $model->method('usernameExists')->willReturn(false);
        $model->method('emailExists')->willReturn(false);
        $model->method('create')->willThrowException(new PDOException('duplicate key'));

        $controller = new UserManagementController($model);

        $result = $controller->save($this->validNewUserData());

        $this->assertFalse($result['success']);
        $this->assertSame('Unable to create the user account.', $result['message']);
    }

    // --------------------------------------------------------------
    // save() - edit path
    // --------------------------------------------------------------

    public function test_save_edit_requires_existing_user(): void
    {
        $model = $this->createMock(UserManagement::class);
        $model->method('usernameExists')->willReturn(false);
        $model->method('emailExists')->willReturn(false);
        $model->method('getById')->willReturn(false);

        $controller = new UserManagementController($model);

        $result = $controller->save($this->validEditUserData());

        $this->assertFalse($result['success']);
        $this->assertSame('User not found.', $result['message']);
    }

    public function test_save_edit_allows_admin_role_change_for_non_bootstrap_user(): void
    {
        $model = $this->createMock(UserManagement::class);
        $model->method('usernameExists')->willReturn(false);
        $model->method('emailExists')->willReturn(false);
        $model->method('getById')->willReturn([
            'user_id' => 5,
            'username' => 'existing',
            'role_id' => 2,
            'account_status' => 'Active',
        ]);
        $model->expects($this->once())->method('update')->willReturn(true);

        $controller = new UserManagementController($model);

        // Promoting a non-bootstrap cashier to admin is allowed.
        $result = $controller->save($this->validEditUserData(['role_id' => '1']));

        $this->assertTrue($result['success']);
        $this->assertSame('User updated successfully.', $result['message']);
    }

    public function test_save_edit_blocks_renaming_bootstrap_admin_account(): void
    {
        $model = $this->createMock(UserManagement::class);
        $model->method('usernameExists')->willReturn(false);
        $model->method('emailExists')->willReturn(false);
        $model->method('getById')->willReturn([
            'user_id' => 5,
            'username' => 'admin',
            'role_id' => 1,
            'account_status' => 'Active',
        ]);
        $model->expects($this->never())->method('update');

        $controller = new UserManagementController($model);

        $result = $controller->save($this->validEditUserData([
            'user_id' => '5',
            'username' => 'renamed_admin',
            'role_id' => '1',
        ]));

        $this->assertFalse($result['success']);
        $this->assertSame(
            'The permanent Administrator account cannot be renamed, demoted, or disabled.',
            $result['message']
        );
    }

    public function test_save_edit_blocks_demoting_bootstrap_admin_account(): void
    {
        $model = $this->createMock(UserManagement::class);
        $model->method('usernameExists')->willReturn(false);
        $model->method('emailExists')->willReturn(false);
        $model->method('getById')->willReturn([
            'user_id' => 5,
            'username' => 'admin',
            'role_id' => 1,
            'account_status' => 'Active',
        ]);
        $model->expects($this->never())->method('update');

        $controller = new UserManagementController($model);

        $result = $controller->save($this->validEditUserData([
            'user_id' => '5',
            'username' => 'admin',
            'role_id' => '2', // attempting to demote to Cashier
        ]));

        $this->assertFalse($result['success']);
        $this->assertSame(
            'The permanent Administrator account cannot be renamed, demoted, or disabled.',
            $result['message']
        );
    }

    public function test_save_edit_blocks_disabling_bootstrap_admin_account(): void
    {
        $model = $this->createMock(UserManagement::class);
        $model->method('usernameExists')->willReturn(false);
        $model->method('emailExists')->willReturn(false);
        $model->method('getById')->willReturn([
            'user_id' => 5,
            'username' => 'admin',
            'role_id' => 1,
            'account_status' => 'Active',
        ]);
        $model->expects($this->never())->method('update');

        $controller = new UserManagementController($model);

        $result = $controller->save($this->validEditUserData([
            'user_id' => '5',
            'username' => 'admin',
            'role_id' => '1',
            'account_status' => 'Inactive',
        ]));

        $this->assertFalse($result['success']);
        $this->assertSame(
            'The permanent Administrator account cannot be renamed, demoted, or disabled.',
            $result['message']
        );
    }

    public function test_save_edit_blocks_self_demotion(): void
    {
        $model = $this->createMock(UserManagement::class);
        $model->method('usernameExists')->willReturn(false);
        $model->method('emailExists')->willReturn(false);
        $model->method('getById')->willReturn([
            'user_id' => 5,
            'username' => 'someadmin',
            'role_id' => 1,
            'account_status' => 'Active',
        ]);
        $model->expects($this->never())->method('update');

        $controller = new UserManagementController($model);

        // currentUserId === edited user id, trying to remove own admin role.
        $result = $controller->save(
            $this->validEditUserData(['user_id' => '5', 'username' => 'someadmin', 'role_id' => '2']),
            5
        );

        $this->assertFalse($result['success']);
        $this->assertSame(
            'You cannot remove or disable your own Administrator access.',
            $result['message']
        );
    }

    public function test_save_edit_blocks_self_deactivation(): void
    {
        $model = $this->createMock(UserManagement::class);
        $model->method('usernameExists')->willReturn(false);
        $model->method('emailExists')->willReturn(false);
        $model->method('getById')->willReturn([
            'user_id' => 5,
            'username' => 'someadmin',
            'role_id' => 1,
            'account_status' => 'Active',
        ]);
        $model->expects($this->never())->method('update');

        $controller = new UserManagementController($model);

        $result = $controller->save(
            $this->validEditUserData([
                'user_id' => '5',
                'username' => 'someadmin',
                'role_id' => '1',
                'account_status' => 'Inactive',
            ]),
            5
        );

        $this->assertFalse($result['success']);
        $this->assertSame(
            'You cannot remove or disable your own Administrator access.',
            $result['message']
        );
    }

    public function test_save_edit_blocks_demoting_last_active_administrator(): void
    {
        $model = $this->createMock(UserManagement::class);
        $model->method('usernameExists')->willReturn(false);
        $model->method('emailExists')->willReturn(false);
        $model->method('getById')->willReturn([
            'user_id' => 5,
            'username' => 'secondadmin',
            'role_id' => 1,
            'account_status' => 'Active',
        ]);
        $model->method('activeAdministratorCount')->willReturn(1);
        $model->expects($this->never())->method('update');

        // Different current user (not self-demotion) demoting the last admin.
        $controller = new UserManagementController($model);

        $result = $controller->save(
            $this->validEditUserData(['user_id' => '5', 'username' => 'secondadmin', 'role_id' => '2']),
            99
        );

        $this->assertFalse($result['success']);
        $this->assertSame(
            'The system must always have at least one active Administrator.',
            $result['message']
        );
    }

    public function test_save_edit_allows_demoting_admin_when_other_active_admins_remain(): void
    {
        $model = $this->createMock(UserManagement::class);
        $model->method('usernameExists')->willReturn(false);
        $model->method('emailExists')->willReturn(false);
        $model->method('getById')->willReturn([
            'user_id' => 5,
            'username' => 'secondadmin',
            'role_id' => 1,
            'account_status' => 'Active',
        ]);
        $model->method('activeAdministratorCount')->willReturn(2);
        $model->expects($this->once())->method('update')->willReturn(true);

        $controller = new UserManagementController($model);

        $result = $controller->save(
            $this->validEditUserData(['user_id' => '5', 'username' => 'secondadmin', 'role_id' => '2']),
            99
        );

        $this->assertTrue($result['success']);
    }

    public function test_save_edit_reports_failure_when_update_fails(): void
    {
        $model = $this->createMock(UserManagement::class);
        $model->method('usernameExists')->willReturn(false);
        $model->method('emailExists')->willReturn(false);
        $model->method('getById')->willReturn([
            'user_id' => 5,
            'username' => 'existing',
            'role_id' => 2,
            'account_status' => 'Active',
        ]);
        $model->method('update')->willReturn(false);

        $controller = new UserManagementController($model);

        $result = $controller->save($this->validEditUserData());

        $this->assertFalse($result['success']);
        $this->assertSame('User update failed.', $result['message']);
    }
}
