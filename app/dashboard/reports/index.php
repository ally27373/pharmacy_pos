<?php

declare(strict_types=1);

require_once '../../../config/session.php';
require_once '../../Middleware/AuthMiddleware.php';

AuthMiddleware::check();

$menu = 'reports';
$page = 'reports';

require_once '../../../includes/header.php';
?>

<link rel="stylesheet" href="/pharmacy_pos/assets/css/dashboard.css">
<link rel="stylesheet" href="/pharmacy_pos/assets/css/sidebar.css">
<link rel="stylesheet" href="/pharmacy_pos/assets/css/navbar.css">
<link rel="stylesheet" href="/pharmacy_pos/assets/css/reports.css">

<div class="dashboard-wrapper">

    <?php include '../../../includes/sidebar.php'; ?>

    <div class="main-content">

        <?php include '../../../includes/navbar.php'; ?>

        <div class="dashboard-content records-page reports-page">

            <div class="page-header report-page-header">
                <div>
                    <h2>Reports</h2>
                    <p>Review sales and inventory activity with live filters and downloadable reports.</p>
                </div>
            </div>

            <section class="reports-card">

                <div class="reports-toolbar">

                    <div class="report-search-wrap">
                        <i class="bi bi-search"></i>
                        <input
                            type="search"
                            id="report-search"
                            class="report-search"
                            placeholder="Search product, barcode, transaction or invoice"
                            autocomplete="off">
                    </div>

                    <select id="report-period" class="report-select" aria-label="Report period">
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="custom">Custom</option>
                    </select>

                    <select id="report-source" class="report-select" aria-label="Report source">
                        <option value="All">Source: All</option>
                        <option value="Sales">Source: Sales</option>
                        <option value="Inventory">Source: Inventory</option>
                    </select>

                    <select id="report-category" class="report-select" aria-label="Category">
                        <option value="">Category: All</option>
                    </select>

                    <select id="report-type" class="report-select" aria-label="Product type">
                        <option value="">Type: All</option>
                    </select>

                    <div class="report-download dropdown">
                        <button
                            type="button"
                            class="btn btn-primary dropdown-toggle"
                            id="report-download-btn"
                            data-bs-toggle="dropdown"
                            aria-expanded="false">
                            <i class="bi bi-download me-1"></i>
                            Download Report
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="report-download-btn">
                            <li>
                                <button type="button" class="dropdown-item report-download-option" data-format="xlsx">
                                    <i class="bi bi-file-earmark-excel me-2"></i>Excel (.xlsx)
                                </button>
                            </li>
                            <li>
                                <button type="button" class="dropdown-item report-download-option" data-format="pdf">
                                    <i class="bi bi-file-earmark-pdf me-2"></i>PDF (.pdf)
                                </button>
                            </li>
                        </ul>
                    </div>

                </div>

                <div id="custom-date-range" class="custom-date-range" hidden>
                    <label>
                        From
                        <input type="date" id="report-from" class="form-control">
                    </label>
                    <label>
                        To
                        <input type="date" id="report-to" class="form-control">
                    </label>
                    <button type="button" id="apply-custom-date" class="btn btn-success">Apply</button>
                </div>

                <div class="report-summary" id="report-summary" aria-live="polite">
                    Loading report...
                </div>

                <div class="table-responsive report-table-wrap">
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Source</th>
                                <th>Product Name</th>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody id="reports-table-body">
                            <tr>
                                <td colspan="10" class="empty-state">Loading report data...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="records-pagination report-pagination" id="report-pagination">
                    <div class="pagination-info" id="report-pagination-info">Loading...</div>
                    <div class="pagination-controls">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="report-prev">Previous</button>
                        <span class="pagination-page" id="report-page-label">Page 1 of 1</span>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="report-next">Next</button>
                    </div>
                </div>

            </section>

            <div id="report-message" class="report-message" aria-live="polite"></div>

        </div>

    </div>
</div>

<script src="/pharmacy_pos/assets/js/reports.js"></script>

<?php require_once '../../../includes/footer.php'; ?>
