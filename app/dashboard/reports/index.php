<?php

declare(strict_types=1);

require_once '../../../config/session.php';
require_once '../../Middleware/AuthMiddleware.php';

AuthMiddleware::admin();

$menu = 'users';
$page = 'users';

require_once '../../Controllers/UserManagementController.php';

$controller = new UserManagementController();

$filters = [
    'search' => trim((string)($_GET['search'] ?? '')),
    'role_id' => $_GET['role_id'] ?? '',
    'account_status' => $_GET['account_status'] ?? '',
];

$currentPage = max(1, (int)($_GET['page'] ?? 1));

$message = '';
$messageType = 'success';

/*
|--------------------------------------------------------------------------
| Handle Create / Update
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $result = $controller->save(
        $_POST,
        (int)($_SESSION['user_id'] ?? 0)
    );

    $message = $result['message'];

    $messageType = $result['success']
        ? 'success'
        : 'danger';
}

/*
|--------------------------------------------------------------------------
| Load User Data
|--------------------------------------------------------------------------
*/

$data = $controller->index(
    $filters,
    $currentPage
);

$roles = $data['roles'];
$result = $data['result'];

$users = $result['users'];

/*
|--------------------------------------------------------------------------
| Load User For Editing
|--------------------------------------------------------------------------
*/

$user = null;

if (isset($_GET['edit'])) {

    require_once '../../Models/UserManagement.php';

    $userManagement = new UserManagement();

    $user = $userManagement->getById(
        (int)$_GET['edit']
    );
}

/*
|--------------------------------------------------------------------------
| Dashboard Header
|--------------------------------------------------------------------------
*/

require_once '../../../includes/header.php';

?>

<link
    rel="stylesheet"
    href="/pharmacy_pos/assets/css/dashboard.css"
>

<link
    rel="stylesheet"
    href="/pharmacy_pos/assets/css/sidebar.css"
>

<link
    rel="stylesheet"
    href="/pharmacy_pos/assets/css/navbar.css"
>

<link
    rel="stylesheet"
    href="/pharmacy_pos/assets/css/user_management.css"
>


<div class="dashboard-wrapper">

    <!-- SIDEBAR -->

    <?php include '../../../includes/sidebar.php'; ?>


    <!-- MAIN CONTENT -->

    <div class="main-content">

        <!-- NAVBAR -->

        <?php include '../../../includes/navbar.php'; ?>


        <!-- PAGE CONTENT -->

        <div class="dashboard-content">


            <!-- PAGE HEADER -->

            <div class="page-header user-management-header">

                <div>

                    <h2>
                        <i class="bi bi-people me-2"></i>
                        User Management
                    </h2>

                    <p>
                        Manage employee accounts, roles, and account status.
                    </p>

                </div>


                <div>

                    <a
                        href="?create=1"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-person-plus me-2"></i>

                        Add User

                    </a>

                </div>

            </div>


            <!-- MESSAGE -->

            <?php if ($message !== ''): ?>

                <div
                    class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show"
                    role="alert"
                >

                    <?= htmlspecialchars($message) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close"
                    ></button>

                </div>

            <?php endif; ?>


            <!-- CREATE / EDIT FORM -->

            <?php if (isset($_GET['create']) || isset($_GET['edit'])): ?>

                <div class="user-management-card mb-4">

                    <div class="user-card-header">

                        <div>

                            <h3>

                                <i class="bi bi-person-gear me-2"></i>

                                <?= $user
                                    ? 'Edit User'
                                    : 'Create User'
                                ?>

                            </h3>

                            <p>

                                <?= $user
                                    ? 'Update employee account information.'
                                    : 'Create a new employee account.'
                                ?>

                            </p>

                        </div>

                    </div>


                    <div class="user-card-body">

                        <form
                            method="POST"
                            class="row g-3"
                        >

                            <input
                                type="hidden"
                                name="user_id"
                                value="<?= htmlspecialchars(
                                    (string)($user['user_id'] ?? '')
                                ) ?>"
                            >


                            <!-- FULL NAME -->

                            <div class="col-md-6">

                                <label
                                    for="full_name"
                                    class="form-label"
                                >
                                    Full Name
                                </label>

                                <input
                                    type="text"
                                    id="full_name"
                                    name="full_name"
                                    class="form-control"
                                    required
                                    value="<?= htmlspecialchars(
                                        $user['full_name'] ?? ''
                                    ) ?>"
                                >

                            </div>


                            <!-- USERNAME -->

                            <div class="col-md-6">

                                <label
                                    for="username"
                                    class="form-label"
                                >
                                    Username
                                </label>

                                <input
                                    type="text"
                                    id="username"
                                    name="username"
                                    class="form-control"
                                    required
                                    value="<?= htmlspecialchars(
                                        $user['username'] ?? ''
                                    ) ?>"
                                >

                            </div>


                            <!-- EMAIL -->

                            <div class="col-md-6">

                                <label
                                    for="email"
                                    class="form-label"
                                >
                                    Email
                                </label>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $user['email'] ?? ''
                                    ) ?>"
                                >

                            </div>


                            <!-- CONTACT -->

                            <div class="col-md-6">

                                <label
                                    for="contact_number"
                                    class="form-label"
                                >
                                    Contact Number
                                </label>

                                <input
                                    type="text"
                                    id="contact_number"
                                    name="contact_number"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $user['contact_number'] ?? ''
                                    ) ?>"
                                >

                            </div>


                            <!-- ROLE -->

                            <div class="col-md-4">

                                <label
                                    for="role_id"
                                    class="form-label"
                                >
                                    Role
                                </label>

                                <select
                                    id="role_id"
                                    name="role_id"
                                    class="form-select"
                                    required
                                >

                                    <?php foreach ($roles as $role): ?>

                                        <?php if (!$user && (int)$role['role_id'] !== 2) { continue; } ?>

                                        <option
                                            value="<?= (int)$role['role_id'] ?>"
                                            <?= (
                                                (int)($user['role_id'] ?? 2)
                                                ===
                                                (int)$role['role_id']
                                            )
                                                ? 'selected'
                                                : ''
                                            ?>
                                        >

                                            <?= htmlspecialchars(
                                                $role['role_name']
                                            ) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- STATUS -->

                            <div class="col-md-4">

                                <label
                                    for="account_status"
                                    class="form-label"
                                >
                                    Account Status
                                </label>

                                <select
                                    id="account_status"
                                    name="account_status"
                                    class="form-select"
                                >

                                    <?php
                                    $statuses = [
                                        'Active',
                                        'Inactive',
                                        'Locked'
                                    ];
                                    ?>

                                    <?php foreach ($statuses as $status): ?>

                                        <option
                                            value="<?= htmlspecialchars($status) ?>"
                                            <?= (
                                                ($user['account_status']
                                                    ?? 'Active')
                                                ===
                                                $status
                                            )
                                                ? 'selected'
                                                : ''
                                            ?>
                                        >

                                            <?= htmlspecialchars($status) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- PASSWORD -->

                            <div class="col-md-4">

                                <label
                                    for="password"
                                    class="form-label"
                                >

                                    Password

                                    <?php if ($user): ?>

                                        <small class="text-muted">
                                            (leave blank to keep current)
                                        </small>

                                    <?php endif; ?>

                                </label>

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control"
                                    <?= $user ? '' : 'required' ?>
                                    minlength="8"
                                >

                            </div>


                            <!-- GENDER -->

                            <div class="col-md-4">

                                <label
                                    for="gender"
                                    class="form-label"
                                >
                                    Gender
                                </label>

                                <select
                                    id="gender"
                                    name="gender"
                                    class="form-select"
                                >

                                    <option value="">
                                        Not specified
                                    </option>

                                    <?php
                                    $genders = [
                                        'Male',
                                        'Female',
                                        'Prefer not to say'
                                    ];
                                    ?>

                                    <?php foreach ($genders as $gender): ?>

                                        <option
                                            value="<?= htmlspecialchars($gender) ?>"
                                            <?= (
                                                ($user['gender'] ?? '')
                                                ===
                                                $gender
                                            )
                                                ? 'selected'
                                                : ''
                                            ?>
                                        >

                                            <?= htmlspecialchars($gender) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- BIRTH DATE -->

                            <div class="col-md-4">

                                <label
                                    for="birth_date"
                                    class="form-label"
                                >
                                    Birth Date
                                </label>

                                <input
                                    type="date"
                                    id="birth_date"
                                    name="birth_date"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $user['birth_date'] ?? ''
                                    ) ?>"
                                >

                            </div>


                            <!-- ADDRESS -->

                            <div class="col-md-4">

                                <label
                                    for="address"
                                    class="form-label"
                                >
                                    Address
                                </label>

                                <input
                                    type="text"
                                    id="address"
                                    name="address"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $user['address'] ?? ''
                                    ) ?>"
                                >

                            </div>


                            <!-- BUTTONS -->

                            <div class="col-12">

                                <div class="user-form-actions">

                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                    >

                                        <i class="bi bi-check-circle me-2"></i>

                                        <?= $user
                                            ? 'Save Changes'
                                            : 'Create User'
                                        ?>

                                    </button>


                                    <a
                                        href="<?= htmlspecialchars(
                                            $_SERVER['PHP_SELF']
                                        ) ?>"
                                        class="btn btn-outline-secondary"
                                    >

                                        <i class="bi bi-x-circle me-2"></i>

                                        Cancel

                                    </a>

                                </div>

                            </div>

                        </form>

                    </div>

                </div>

            <?php endif; ?>


            <!-- USER LIST -->

            <div class="user-management-card">

                <div class="user-card-header">

                    <div>

                        <h3>

                            <i class="bi bi-people me-2"></i>

                            Employee Accounts

                        </h3>

                        <p>

                            <?= number_format(
                                (int)$result['total']
                            ) ?>

                            record(s) found.

                        </p>

                    </div>

                </div>


                <!-- FILTERS -->

                <div class="user-card-body">

                    <form
                        method="GET"
                        class="user-filter-form"
                    >

                        <!-- SEARCH -->

                        <div class="filter-field search-field">

                            <label
                                for="search"
                                class="form-label"
                            >
                                Search
                            </label>

                            <input
                                type="text"
                                id="search"
                                name="search"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $filters['search']
                                ) ?>"
                                placeholder="Name, username, or email"
                            >

                        </div>


                        <!-- ROLE -->

                        <div class="filter-field">

                            <label
                                for="filter_role"
                                class="form-label"
                            >
                                Role
                            </label>

                            <select
                                id="filter_role"
                                name="role_id"
                                class="form-select"
                            >

                                <option value="">
                                    All Roles
                                </option>

                                <?php foreach ($roles as $role): ?>

                                    <option
                                        value="<?= (int)$role['role_id'] ?>"
                                        <?= (
                                            (string)$filters['role_id']
                                            ===
                                            (string)$role['role_id']
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >

                                        <?= htmlspecialchars(
                                            $role['role_name']
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- STATUS -->

                        <div class="filter-field">

                            <label
                                for="filter_status"
                                class="form-label"
                            >
                                Status
                            </label>

                            <select
                                id="filter_status"
                                name="account_status"
                                class="form-select"
                            >

                                <option value="">
                                    All Statuses
                                </option>

                                <?php foreach ($statuses as $status): ?>

                                    <option
                                        value="<?= htmlspecialchars($status) ?>"
                                        <?= (
                                            $filters['account_status']
                                            ===
                                            $status
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >

                                        <?= htmlspecialchars($status) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- ACTIONS -->

                        <div class="filter-actions">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >

                                <i class="bi bi-search me-2"></i>

                                Filter

                            </button>


                            <a
                                href="<?= htmlspecialchars(
                                    $_SERVER['PHP_SELF']
                                ) ?>"
                                class="btn btn-outline-secondary"
                            >

                                <i class="bi bi-arrow-clockwise me-2"></i>

                                Reset

                            </a>

                        </div>

                    </form>


                    <!-- TABLE -->

                    <div class="table-responsive mt-4">

                        <table class="table user-table align-middle">

                            <thead>

                                <tr>

                                    <th>User</th>

                                    <th>Contact</th>

                                    <th>Role</th>

                                    <th>Status</th>

                                    <th>Last Login</th>

                                    <th class="text-end">
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                            <?php if (!$users): ?>

                                <tr>

                                    <td
                                        colspan="6"
                                        class="text-center py-5 text-muted"
                                    >

                                        <i
                                            class="bi bi-people fs-2 d-block mb-2"
                                        ></i>

                                        No users found.

                                    </td>

                                </tr>

                            <?php else: ?>

                                <?php foreach ($users as $u): ?>

                                    <tr>

                                        <!-- USER -->

                                        <td>

                                            <div class="user-name">

                                                <?= htmlspecialchars(
                                                    $u['full_name']
                                                ) ?>

                                            </div>

                                            <div class="user-username">

                                                @<?= htmlspecialchars(
                                                    $u['username']
                                                ) ?>

                                                <?php if (!empty($u['email'])): ?>

                                                    ·
                                                    <?= htmlspecialchars(
                                                        $u['email']
                                                    ) ?>

                                                <?php endif; ?>

                                            </div>

                                        </td>


                                        <!-- CONTACT -->

                                        <td>

                                            <?= htmlspecialchars(
                                                (string)(
                                                    $u['contact_number']
                                                    ?? '—'
                                                )
                                            ) ?>

                                        </td>


                                        <!-- ROLE -->

                                        <td>

                                            <span class="role-badge">

                                                <?= htmlspecialchars(
                                                    $u['role_name']
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- STATUS -->

                                        <td>

                                            <span
                                                class="status-badge status-<?= strtolower(
                                                    htmlspecialchars(
                                                        $u['account_status']
                                                    )
                                                ) ?>"
                                            >

                                                <?= htmlspecialchars(
                                                    $u['account_status']
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- LAST LOGIN -->

                                        <td>

                                            <?php if ($u['last_login']): ?>

                                                <?= htmlspecialchars(
                                                    $u['last_login']
                                                ) ?>

                                            <?php else: ?>

                                                Never

                                            <?php endif; ?>

                                        </td>


                                        <!-- ACTION -->

                                        <td class="text-end">

                                            <a
                                                href="?edit=<?= (int)$u['user_id'] ?>"
                                                class="btn btn-sm btn-outline-primary"
                                            >

                                                <i class="bi bi-pencil-square me-1"></i>

                                                Edit

                                            </a>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>


                    <!-- PAGINATION -->

                    <?php if ($result['total_pages'] > 1): ?>

                        <?php
                        $paginationQuery = $filters;
                        ?>

                        <div class="user-pagination">

                            <?php for (
                                $p = 1;
                                $p <= $result['total_pages'];
                                $p++
                            ): ?>

                                <?php
                                $paginationQuery['page'] = $p;
                                ?>

                                <a
                                    href="?<?= htmlspecialchars(
                                        http_build_query(
                                            $paginationQuery
                                        )
                                    ) ?>"
                                    class="pagination-link <?= (
                                        $p === $result['page']
                                    )
                                        ? 'active'
                                        : '' ?>"
                                >

                                    <?= $p ?>

                                </a>

                            <?php endfor; ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


        </div>

    </div>

</div>


<?php require_once '../../../includes/footer.php'; ?>