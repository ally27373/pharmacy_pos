<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/Controllers/AuthController.php';

use PHPUnit\Framework\TestCase;

final class AuthControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // --------------------------------------------------------------
    // register()
    // --------------------------------------------------------------

    public function test_register_requires_all_fields(): void
    {
        $user = $this->createMock(User::class);
        $controller = new AuthController($user);

        $result = $controller->register([
            'full_name' => '',
            'username' => 'alice',
            'email' => 'alice@example.com',
            'password' => 'Password1',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('All fields are required.', $result['message']);
    }

    public function test_register_rejects_invalid_email(): void
    {
        $user = $this->createMock(User::class);
        $controller = new AuthController($user);

        $result = $controller->register([
            'full_name' => 'Alice A',
            'username' => 'alice',
            'email' => 'not-an-email',
            'password' => 'Password1',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid email format.', $result['message']);
    }

    public function test_register_rejects_existing_username(): void
    {
        $user = $this->createMock(User::class);
        $user->method('usernameExists')->willReturn(true);
        $controller = new AuthController($user);

        $result = $controller->register([
            'full_name' => 'Alice A',
            'username' => 'alice',
            'email' => 'alice@example.com',
            'password' => 'Password1',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Username already exists.', $result['message']);
    }

    public function test_register_rejects_existing_email(): void
    {
        $user = $this->createMock(User::class);
        $user->method('usernameExists')->willReturn(false);
        $user->method('emailExists')->willReturn(true);
        $controller = new AuthController($user);

        $result = $controller->register([
            'full_name' => 'Alice A',
            'username' => 'alice',
            'email' => 'alice@example.com',
            'password' => 'Password1',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Email already exists.', $result['message']);
    }

    public function test_register_trims_whitespace_before_saving(): void
    {
        $user = $this->createMock(User::class);
        $user->method('usernameExists')->willReturn(false);
        $user->method('emailExists')->willReturn(false);
        $user->expects($this->once())
            ->method('register')
            ->with('Alice A', 'alice', 'alice@example.com', 'Password1')
            ->willReturn(true);

        $controller = new AuthController($user);

        $result = $controller->register([
            'full_name' => '  Alice A  ',
            'username' => '  alice  ',
            'email' => '  alice@example.com  ',
            'password' => '  Password1  ',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('Registration successful!', $result['message']);
    }

    public function test_register_reports_failure_when_save_fails(): void
    {
        $user = $this->createMock(User::class);
        $user->method('usernameExists')->willReturn(false);
        $user->method('emailExists')->willReturn(false);
        $user->method('register')->willReturn(false);

        $controller = new AuthController($user);

        $result = $controller->register([
            'full_name' => 'Alice A',
            'username' => 'alice',
            'email' => 'alice@example.com',
            'password' => 'Password1',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Registration failed.', $result['message']);
    }

    // --------------------------------------------------------------
    // login()
    // --------------------------------------------------------------

    public function test_login_requires_login_and_password(): void
    {
        $user = $this->createMock(User::class);
        $controller = new AuthController($user);

        $result = $controller->login(['login' => '', 'password' => '']);

        $this->assertFalse($result['success']);
        $this->assertSame('Please enter your username/email and password.', $result['message']);
    }

    public function test_login_fails_with_generic_message_when_user_not_found(): void
    {
        $user = $this->createMock(User::class);
        $user->method('findByUsernameOrEmail')->willReturn(false);

        $controller = new AuthController($user);

        $result = $controller->login(['login' => 'ghost', 'password' => 'whatever']);

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid username/email or password.', $result['message']);
    }

    public function test_login_fails_with_same_generic_message_when_password_is_wrong(): void
    {
        // The message for "user does not exist" and "wrong password" must be
        // identical, so a login attempt cannot be used to enumerate usernames.
        $hash = password_hash('CorrectPass1', PASSWORD_DEFAULT);
        $user = $this->createMock(User::class);
        $user->method('findByUsernameOrEmail')->willReturn([
            'user_id' => 1,
            'username' => 'alice',
            'password' => $hash,
            'role_id' => 2,
            'account_status' => 'Active',
        ]);

        $controller = new AuthController($user);

        $result = $controller->login(['login' => 'alice', 'password' => 'WrongPass1']);

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid username/email or password.', $result['message']);
    }

    public function test_login_never_compares_password_in_plaintext(): void
    {
        // Store the *plaintext* string in the "password" column to prove the
        // controller relies on password_verify() (hash comparison) and does
        // not fall back to a plain string comparison that would incorrectly
        // accept this as a match.
        $user = $this->createMock(User::class);
        $user->method('findByUsernameOrEmail')->willReturn([
            'user_id' => 1,
            'username' => 'alice',
            'password' => 'PlainPass1',
            'role_id' => 2,
            'account_status' => 'Active',
        ]);

        $controller = new AuthController($user);

        $result = $controller->login(['login' => 'alice', 'password' => 'PlainPass1']);

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid username/email or password.', $result['message']);
    }

    public function test_login_blocks_inactive_account_after_password_check(): void
    {
        $hash = password_hash('Password1', PASSWORD_DEFAULT);
        $user = $this->createMock(User::class);
        $user->method('findByUsernameOrEmail')->willReturn([
            'user_id' => 1,
            'username' => 'alice',
            'password' => $hash,
            'role_id' => 2,
            'account_status' => 'Inactive',
        ]);
        $user->expects($this->never())->method('recordLastLogin');

        $controller = new AuthController($user);

        $result = $controller->login(['login' => 'alice', 'password' => 'Password1']);

        $this->assertFalse($result['success']);
        $this->assertSame(
            'Your account is currently inactive. Please contact an Administrator.',
            $result['message']
        );
    }

    public function test_login_blocks_locked_account_with_specific_message(): void
    {
        $hash = password_hash('Password1', PASSWORD_DEFAULT);
        $user = $this->createMock(User::class);
        $user->method('findByUsernameOrEmail')->willReturn([
            'user_id' => 1,
            'username' => 'alice',
            'password' => $hash,
            'role_id' => 2,
            'account_status' => 'Locked',
        ]);

        $controller = new AuthController($user);

        $result = $controller->login(['login' => 'alice', 'password' => 'Password1']);

        $this->assertFalse($result['success']);
        $this->assertSame(
            'Your account is currently locked. Please contact an Administrator.',
            $result['message']
        );
    }

    public function test_login_succeeds_and_redirects_cashier_to_pos_terminal(): void
    {
        $hash = password_hash('Password1', PASSWORD_DEFAULT);
        $user = $this->createMock(User::class);
        $user->method('findByUsernameOrEmail')->willReturn([
            'user_id' => 10,
            'username' => 'cashier1',
            'password' => $hash,
            'role_id' => 2,
            'account_status' => 'Active',
        ]);
        $user->expects($this->once())->method('recordLastLogin')->with(10);

        $controller = new AuthController($user);

        $result = $controller->login(['login' => 'cashier1', 'password' => 'Password1']);

        $this->assertTrue($result['success']);
        $this->assertSame('Login successful.', $result['message']);
        $this->assertSame('/app/dashboard/pos/index.php?page=terminal', $result['redirect']);
        $this->assertSame(10, $_SESSION['user_id']);
        $this->assertSame('cashier1', $_SESSION['username']);
        $this->assertSame(2, $_SESSION['role_id']);
    }

    public function test_login_succeeds_and_redirects_admin_to_analytics(): void
    {
        $hash = password_hash('Password1', PASSWORD_DEFAULT);
        $user = $this->createMock(User::class);
        $user->method('findByUsernameOrEmail')->willReturn([
            'user_id' => 1,
            'username' => 'admin',
            'password' => $hash,
            'role_id' => 1,
            'account_status' => 'Active',
        ]);

        $controller = new AuthController($user);

        $result = $controller->login(['login' => 'admin', 'password' => 'Password1']);

        $this->assertTrue($result['success']);
        $this->assertSame('/app/dashboard/analytics/index.php', $result['redirect']);
    }

    public function test_login_regenerates_session_id_to_prevent_fixation(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $before = session_id();

        $hash = password_hash('Password1', PASSWORD_DEFAULT);
        $user = $this->createMock(User::class);
        $user->method('findByUsernameOrEmail')->willReturn([
            'user_id' => 1,
            'username' => 'admin',
            'password' => $hash,
            'role_id' => 1,
            'account_status' => 'Active',
        ]);

        $controller = new AuthController($user);
        $controller->login(['login' => 'admin', 'password' => 'Password1']);

        $after = session_id();

        $this->assertNotSame($before, $after);
    }

    public function test_login_treats_missing_account_status_as_inactive(): void
    {
        $hash = password_hash('Password1', PASSWORD_DEFAULT);
        $user = $this->createMock(User::class);
        $user->method('findByUsernameOrEmail')->willReturn([
            'user_id' => 1,
            'username' => 'alice',
            'password' => $hash,
            'role_id' => 2,
            // account_status intentionally omitted
        ]);

        $controller = new AuthController($user);

        $result = $controller->login(['login' => 'alice', 'password' => 'Password1']);

        $this->assertFalse($result['success']);
        $this->assertSame(
            'Your account is currently inactive. Please contact an Administrator.',
            $result['message']
        );
    }
}
