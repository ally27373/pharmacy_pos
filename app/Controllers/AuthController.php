<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../../config/session.php';

class AuthController
{
    private User $user;

    public function __construct(?User $user = null)
    {
        $this->user = $user ?? new User();
    }

    public function register(array $data): array
{
    // Remove unnecessary spaces
    $fullName = trim($data['full_name'] ?? '');
    $username = trim($data['username'] ?? '');
    $email    = trim($data['email'] ?? '');
    $password = trim($data['password'] ?? '');

    // Required field validation
    if (
        empty($fullName) ||
        empty($username) ||
        empty($email) ||
        empty($password)
    ) {
        return [
            'success' => false,
            'message' => 'All fields are required.'
        ];
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Invalid email format.'
        ];
    }

    // Check username
    if ($this->user->usernameExists($username)) {
        return [
            'success' => false,
            'message' => 'Username already exists.'
        ];
    }

    // Check email
    if ($this->user->emailExists($email)) {
        return [
            'success' => false,
            'message' => 'Email already exists.'
        ];
    }

    // Save user
    $registered = $this->user->register(
        $fullName,
        $username,
        $email,
        $password
    );

    if ($registered) {
        return [
            'success' => true,
            'message' => 'Registration successful!'
        ];
    }

    return [
        'success' => false,
        'message' => 'Registration failed.'
    ];
}

public function login(array $data): array
    {
        $login = trim((string)($data['login'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($login === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'Please enter your username/email and password.'
            ];
        }

        $user = $this->user->findByUsernameOrEmail($login);

        if (!$user || !password_verify($password, (string)$user['password'])) {
            return [
                'success' => false,
                'message' => 'Invalid username/email or password.'
            ];
        }

        // Only Active accounts may authenticate.
        if (($user['account_status'] ?? 'Inactive') !== 'Active') {
            return [
                'success' => false,
                'message' => 'Your account is currently ' .
                    strtolower((string)($user['account_status'] ?? 'Inactive')) .
                    '. Please contact an Administrator.'
            ];
        }

        // Record the successful authentication time for User Management.
        // This happens only after password and account-status validation pass.
        $this->user->recordLastLogin((int)$user['user_id']);

        // Prevent session fixation after successful authentication.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_id'] = (int)$user['role_id'];

        $redirect = ((int)$user['role_id'] === 2)
            ? '/app/dashboard/pos/index.php?page=terminal'
            : '/app/dashboard/analytics/index.php';

        return [
            'success' => true,
            'message' => 'Login successful.',
            'redirect' => $redirect
        ];
    }
}
