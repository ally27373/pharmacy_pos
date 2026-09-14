<?php

declare(strict_types=1);

require_once '../../../config/session.php';
require_once '../../Middleware/AuthMiddleware.php';
require_once '../../Controllers/AuditLogController.php';

AuthMiddleware::admin();

$menu = 'audit_logs';
$page = 'audit_logs';

$controller = new AuditLogController();

$filters = [
    'action_type' => trim($_GET['action_type'] ?? ''),
    'module_name' => trim($_GET['module_name'] ?? ''),
    'date_from'   => trim($_GET['date_from'] ?? ''),
    'date_to'     => trim($_GET['date_to'] ?? ''),
    'search'      => trim($_GET['search'] ?? ''),
];

$perPage = 25;
$pageNumber = max(1, (int) ($_GET['page'] ?? 1));
$totalRecords = $controller->countLogs($filters);
$totalPages = max(1, (int) ceil($totalRecords / $perPage));

if ($pageNumber > $totalPages) {
    $pageNumber = $totalPages;
}

$logs = $controller->getLogs($filters, $pageNumber, $perPage);
$actionTypes = $controller->getActionTypes();
$modules = $controller->getModules();

$firstRecord = $totalRecords > 0 ? (($pageNumber - 1) * $perPage) + 1 : 0;
$lastRecord = min($pageNumber * $perPage, $totalRecords);

$queryForPagination = $filters;

require_once '../../../includes/header.php';
?>

<link rel="stylesheet" href="/pharmacy_pos/assets/css/dashboard.css">
<link rel="stylesheet" href="/pharmacy_pos/assets/css/sidebar.css">
<link rel="stylesheet" href="/pharmacy_pos/assets/css/navbar.css">
<link rel="stylesheet" href="/pharmacy_pos/assets/css/audit_logs.css">

<div class="dashboard-wrapper">

    <?php include '../../../includes/sidebar.php'; ?>

    <div class="main-content">

        <?php include '../../../includes/navbar.php'; ?>

        <div class="dashboard-content">

            <div class="page-header">
                <div>
                    <h2>
                        <i class="bi bi-shield-check me-2"></i>
                        Audit Logs
                    </h2>
                    <p>Review administrator and system activity for accountability and security.</p>
                </div>
            </div>

            <div class="audit-card">
                <form method="GET" class="audit-filters">

                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input
                            type="text"
                            id="search"
                            name="search"
                            value="<?= htmlspecialchars($filters['search']) ?>"
                            placeholder="User, module, or description"
                        >
                    </div>

                    <div class="filter-group">
                        <label for="action_type">Action</label>
                        <select id="action_type" name="action_type">
                            <option value="">All Actions</option>
                            <?php foreach ($actionTypes as $action): ?>
                                <option
                                    value="<?= htmlspecialchars($action) ?>"
                                    <?= $filters['action_type'] === $action ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($action) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="module_name">Module</label>
                        <select id="module_name" name="module_name">
                            <option value="">All Modules</option>
                            <?php foreach ($modules as $module): ?>
                                <option
                                    value="<?= htmlspecialchars((string) $module) ?>"
                                    <?= $filters['module_name'] === $module ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars((string) $module) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="date_from">From</label>
                        <input
                            type="date"
                            id="date_from"
                            name="date_from"
                            value="<?= htmlspecialchars($filters['date_from']) ?>"
                        >
                    </div>

                    <div class="filter-group">
                        <label for="date_to">To</label>
                        <input
                            type="date"
                            id="date_to"
                            name="date_to"
                            value="<?= htmlspecialchars($filters['date_to']) ?>"
                        >
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i>
                            Filter
                        </button>

                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise me-1"></i>
                            Reset
                        </a>
                    </div>

                </form>
            </div>

            <div class="audit-card">
                <div class="audit-table-header">
                    <div>
                        <h3>Activity History</h3>
                        <p>
                            <?php if ($totalRecords > 0): ?>
                                Showing <?= number_format($firstRecord) ?>–<?= number_format($lastRecord) ?>
                                of <?= number_format($totalRecords) ?> record(s).
                            <?php else: ?>
                                No records found.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table audit-table">
                        <thead>
                            <tr>
                                <th>Date / Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Module</th>
                                <th>Record</th>
                                <th>Description</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$logs): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <i class="bi bi-shield-check"></i>
                                        <div>No audit records found.</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars(date('M d, Y h:i A', strtotime($log['created_at']))) ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($log['username']) ?></strong>
                                            <small><?= htmlspecialchars($log['full_name']) ?></small>
                                        </td>
                                        <td>
                                            <span class="action-badge action-<?= strtolower(htmlspecialchars($log['action_type'])) ?>">
                                                <?= htmlspecialchars($log['action_type']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($log['module_name']) ?></td>
                                        <td><?= $log['record_id'] !== null ? htmlspecialchars((string) $log['record_id']) : '—' ?></td>
                                        <td><?= htmlspecialchars($log['description']) ?></td>
                                        <td><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="audit-pagination" aria-label="Audit log pagination">
                        <?php if ($pageNumber > 1): ?>
                            <?php $queryForPagination['page'] = $pageNumber - 1; ?>
                            <a class="pagination-link" href="?<?= htmlspecialchars(http_build_query($queryForPagination)) ?>">
                                <i class="bi bi-chevron-left"></i>
                                Previous
                            </a>
                        <?php else: ?>
                            <span class="pagination-link disabled">
                                <i class="bi bi-chevron-left"></i>
                                Previous
                            </span>
                        <?php endif; ?>

                        <div class="pagination-pages">
                            <?php
                            $startPage = max(1, $pageNumber - 2);
                            $endPage = min($totalPages, $pageNumber + 2);
                            ?>

                            <?php if ($startPage > 1): ?>
                                <?php $queryForPagination['page'] = 1; ?>
                                <a class="pagination-link" href="?<?= htmlspecialchars(http_build_query($queryForPagination)) ?>">1</a>
                                <?php if ($startPage > 2): ?>
                                    <span class="pagination-ellipsis">…</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                                <?php $queryForPagination['page'] = $p; ?>
                                <?php if ($p === $pageNumber): ?>
                                    <span class="pagination-link active"><?= $p ?></span>
                                <?php else: ?>
                                    <a class="pagination-link" href="?<?= htmlspecialchars(http_build_query($queryForPagination)) ?>"><?= $p ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <span class="pagination-ellipsis">…</span>
                                <?php endif; ?>
                                <?php $queryForPagination['page'] = $totalPages; ?>
                                <a class="pagination-link" href="?<?= htmlspecialchars(http_build_query($queryForPagination)) ?>"><?= $totalPages ?></a>
                            <?php endif; ?>
                        </div>

                        <?php if ($pageNumber < $totalPages): ?>
                            <?php $queryForPagination['page'] = $pageNumber + 1; ?>
                            <a class="pagination-link" href="?<?= htmlspecialchars(http_build_query($queryForPagination)) ?>">
                                Next
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-link disabled">
                                Next
                                <i class="bi bi-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>
