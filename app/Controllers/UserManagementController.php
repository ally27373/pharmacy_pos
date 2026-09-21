<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/UserManagement.php';
require_once __DIR__ . '/../Services/AuditLogger.php';

class UserManagementController
{
    private UserManagement $users;

    public function __construct(?UserManagement $users = null)
    {
        $this->users = $users ?? new UserManagement();
    }

    public function index(array $filters, int $page = 1): array
    {
        return [
            'roles' => $this->users->getRoles(),
            'result' => $this->users->getUsers($filters, $page, 10),
        ];
    }

    public function save(array $data, ?int $currentUserId = null): array
    {
        $id = isset($data['user_id']) && $data['user_id'] !== '' ? (int)$data['user_id'] : null;
        $isEdit = $id !== null;

        $fullName = trim((string)($data['full_name'] ?? ''));
        $username = trim((string)($data['username'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $roleId = (int)($data['role_id'] ?? 0);
        $status = (string)($data['account_status'] ?? 'Active');

        if ($fullName === '' || $username === '' || $roleId < 1) {
            return ['success' => false, 'message' => 'Full name, username, and role are required.'];
        }

        if (!in_array($roleId, [1, 2], true)) {
            return ['success' => false, 'message' => 'Invalid role selected.'];
        }

        // New employee accounts created from User Management are Cashier accounts only.
        if (!$isEdit && $roleId !== 2) {
            return ['success' => false, 'message' => 'New employee accounts can only be created with the Cashier role.'];
        }

        if (!in_array($status, ['Active', 'Inactive', 'Locked'], true)) {
            return ['success' => false, 'message' => 'Invalid account status.'];
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address.'];
        }

        if (!$isEdit && strlen($password) < 8) {
            return ['success' => false, 'message' => 'New users must have a password of at least 8 characters.'];
        }

        if ($this->users->usernameExists($username, $id)) {
            return ['success' => false, 'message' => 'Username already exists.'];
        }

        if ($email !== '' && $this->users->emailExists($email, $id)) {
            return ['success' => false, 'message' => 'Email already exists.'];
        }

        if ($isEdit) {
            $existing = $this->users->getById($id);
            if (!$existing) {
                return ['success' => false, 'message' => 'User not found.'];
            }

            // Permanent bootstrap Administrator protection.
            // The account named 'admin' must remain an Active Administrator.
            if (strcasecmp((string)$existing['username'], 'admin') === 0) {
                if (strcasecmp($username, 'admin') !== 0 || $roleId !== 1 || $status !== 'Active') {
                    return ['success' => false, 'message' => 'The permanent Administrator account cannot be renamed, demoted, or disabled.'];
                }
            }

            // Never allow the currently logged-in administrator to remove their own admin access.
            if ($currentUserId !== null && $id === $currentUserId) {
                if ($roleId !== 1 || $status !== 'Active') {
                    return ['success' => false, 'message' => 'You cannot remove or disable your own Administrator access.'];
                }
            }

            // Never allow the last active administrator to be demoted/deactivated.
            if ((int)$existing['role_id'] === 1 && $existing['account_status'] === 'Active'
                && ($roleId !== 1 || $status !== 'Active')
                && $this->users->activeAdministratorCount() <= 1) {
                return ['success' => false, 'message' => 'The system must always have at least one active Administrator.'];
            }

            $ok = $this->users->update($id, [
                'role_id' => $roleId,
                'full_name' => $fullName,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'contact_number' => trim((string)($data['contact_number'] ?? '')),
                'gender' => trim((string)($data['gender'] ?? '')),
                'birth_date' => trim((string)($data['birth_date'] ?? '')),
                'address' => trim((string)($data['address'] ?? '')),
                'account_status' => $status,
            ]);

            if ($ok) {
                AuditLogger::log('UPDATE', 'USER_MANAGEMENT', "Updated user account #{$id}", $id);
            }

            return ['success' => $ok, 'message' => $ok ? 'User updated successfully.' : 'User update failed.'];
        }

        try {
            $newId = $this->users->create([
                'role_id' => $roleId,
                'full_name' => $fullName,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'contact_number' => trim((string)($data['contact_number'] ?? '')),
                'gender' => trim((string)($data['gender'] ?? '')),
                'birth_date' => trim((string)($data['birth_date'] ?? '')),
                'address' => trim((string)($data['address'] ?? '')),
            ]);

            AuditLogger::log('CREATE', 'USER_MANAGEMENT', "Created user account {$username}", $newId);

            return ['success' => true, 'message' => 'User created successfully.'];
        } catch (PDOException $e) {
            error_log('User creation error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to create the user account.'];
        }
    }
}
