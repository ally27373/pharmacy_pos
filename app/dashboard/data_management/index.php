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

        </div>
    </div>
</div>

<script src="/assets/js/data_management.js"></script>

<?php require_once '../../../includes/footer.php'; ?>
