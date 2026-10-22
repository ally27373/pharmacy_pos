<?php

declare(strict_types=1);

require_once '../../../config/session.php';
require_once '../../Middleware/AuthMiddleware.php';

AuthMiddleware::dataManagement();

$menu = 'data_management';
$page = 'data_management';

require_once '../../../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/dashboard-shell.css">
<link rel="stylesheet" href="/assets/css/sidebar.css">
<link rel="stylesheet" href="/assets/css/navbar.css">
<link rel="stylesheet" href="/assets/css/data_management.css">

<div class="dashboard-wrapper">

    <?php include '../../../includes/sidebar.php'; ?>

    <div class="main-content">

        <?php include '../../../includes/navbar.php'; ?>

        <div class="dashboard-content">

            <div class="page-header data-management-header">
                <div>
                    <h2>
                        <i class="bi bi-database me-2"></i>
                        Data Management
                    </h2>
                    <p>Export pharmacy datasets.</p>
                </div>
            </div>

            <div class="data-management-card export-card">

                <div class="data-card-icon export-icon">
                    <i class="bi bi-database-down"></i>
                </div>

                <div class="data-card-content">
                    <h3>Dataset Export</h3>
                    <p>
                        Export clean pharmacy datasets for backup, reporting,
                        analysis, and forecasting.
                    </p>

                    <div class="export-buttons">
                        <button
                            type="button"
                            id="export-inventory-btn"
                            class="btn export-button"
                        >
                            <i class="bi bi-box-seam me-2"></i>
                            Export Inventory
                        </button>

                        <button
                            type="button"
                            id="export-sales-btn"
                            class="btn export-button"
                        >
                            <i class="bi bi-receipt me-2"></i>
                            Export Sales
                        </button>
                    </div>

                    <div id="export-message" class="mt-3" aria-live="polite"></div>
                </div>
            </div>

            <div class="data-management-card import-card">

                <div class="data-card-icon import-icon">
                    <i class="bi bi-database-up"></i>
                </div>

                <div class="data-card-content">
                    <h3>Historical Sales Import</h3>
                    <p>
                        Import historical sales data (CSV) for forecasting and reporting.
                        <strong>Note:</strong> imported data is recorded as completed historical
                        sales and does not change current product stock. Admin only.
                    </p>

                    <div class="import-form">
                        <div class="mb-3">
                            <label for="import-file" class="form-label">CSV file</label>
                            <input type="file" class="form-control" id="import-file" accept=".csv,text/csv">
                            <div class="form-text">
                                Required columns: <code>date, product_name, quantity, unit_price</code>
                            </div>
                        </div>

                        <div class="import-actions">
                            <button type="button" id="import-preview-btn" class="btn btn-outline-primary">
                                <i class="bi bi-search me-2"></i>Preview
                            </button>
                            <button type="button" id="import-confirm-btn" class="btn btn-success" disabled>
                                <i class="bi bi-check2-circle me-2"></i>Confirm Import
                            </button>
                        </div>

                        <div id="import-message" class="mt-3" aria-live="polite"></div>
                        <div id="import-preview" class="mt-3"></div>

                        <div class="alert alert-warning small mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <strong>Backup first:</strong> before a large import, back up the database.
                            Re-importing replaces previously imported historical sales.
                        </div>
                    </div>
                </div>
            </div>

            <div class="data-management-card import-history-card">

                <div class="data-card-icon history-icon">
                    <i class="bi bi-clock-history"></i>
                </div>

                <div class="data-card-content">
                    <h3>Import History</h3>
                    <p class="text-muted small mb-2">
                        Recent import actions recorded in the audit log.
                    </p>

                    <div id="import-history-message" class="mb-2" aria-live="polite"></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped" id="import-history-table">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>User</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="4" class="text-muted text-center">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
window.CSRF_TOKEN = <?php
require_once '../../Helpers/Csrf.php';
echo json_encode(Csrf::token());
?>;
</script>
<script src="/assets/js/data_management.js"></script>

<?php require_once '../../../includes/footer.php'; ?>