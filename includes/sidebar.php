<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$roleId = (int) ($_SESSION['role_id'] ?? 0);
$isAdmin = $roleId === 1;
$isCashier = $roleId === 2;
?>

<aside class="sidebar">

    <div class="sidebar-logo">

        <div class="logo-placeholder">
            <img
                src="/assets/image/nica-xandra-logo.png"
                alt="Nica Xandra logo"
            >
        </div>

        <h3>NICA XANDRA</h3>

        <small>Pharmacy POS</small>

    </div>

    <ul>

        <?php if ($isAdmin): ?>

            <!-- =====================================================
                 ADMIN DASHBOARD
            ====================================================== -->

            <li class="menu-item">

                <a href="#" class="submenu-toggle">

                    <span class="menu-left">

                        <i class="bi bi-speedometer2"></i>

                        <span>Dashboard</span>

                    </span>

                    <i class="bi bi-chevron-down dropdown-icon"></i>

                </a>

                <ul class="submenu <?= ($menu == 'dashboard') ? 'show' : ''; ?>">

                    <li>

                        <a
                            href="/app/dashboard/analytics/index.php"
                            class="<?= ($page == 'analytics') ? 'active' : ''; ?>"
                        >

                            <i class="bi bi-graph-up"></i>

                            Analytics Dashboard

                        </a>

                    </li>

                    <li>

                        <a
                            href="/app/dashboard/inventory/index.php"
                            class="<?= ($page == 'inventory_dashboard') ? 'active' : ''; ?>"
                        >

                            <i class="bi bi-box-seam"></i>

                            Inventory Dashboard

                        </a>

                    </li>

                </ul>

            </li>

        <?php endif; ?>


        <!-- =====================================================
             POS TERMINAL
             AVAILABLE TO ADMIN + CASHIER
        ====================================================== -->

        <li class="menu-item">

            <a
                href="/app/dashboard/pos/index.php?page=terminal"
                class="submenu-toggle <?= ($page == 'pos') ? 'active' : ''; ?>"
            >

                <span class="menu-left">

                    <i class="bi bi-cart-check"></i>

                    <span>POS Terminal</span>

                </span>

                <i class="bi bi-chevron-down dropdown-icon"></i>

            </a>

            <ul class="submenu <?= ($menu == 'pos') ? 'show' : ''; ?>">

                <li>

                    <a
                        href="/app/dashboard/pos/index.php?page=sales"
                        class="<?= ($page == 'sales') ? 'active' : ''; ?>"
                    >

                        <i class="bi bi-receipt"></i>

                        Sales

                    </a>

                </li>

                <li>

                    <a
                        href="/app/dashboard/pos/index.php?page=billings"
                        class="<?= ($page == 'billings') ? 'active' : ''; ?>"
                    >

                        <i class="bi bi-credit-card"></i>

                        Billings

                    </a>

                </li>

            </ul>

        </li>


        <?php if ($isAdmin): ?>

            <!-- =====================================================
                 ADMIN-ONLY MODULES
            ====================================================== -->

            <li class="menu-item">

                <a
                    href="#"
                    class="submenu-toggle <?= ($menu == 'inventory_management') ? 'active' : ''; ?>"
                >

                    <span class="menu-left">

                        <i class="bi bi-capsule"></i>

                        <span>Inventory Management</span>

                    </span>

                    <i class="bi bi-chevron-down dropdown-icon"></i>

                </a>

                <ul class="submenu <?= ($menu == 'inventory_management') ? 'show' : ''; ?>">

                    <li>

                        <a
                            href="/app/dashboard/inventory_management/index.php"
                            class="<?= ($page == 'inventory_management') ? 'active' : ''; ?>"
                        >

                            <i class="bi bi-box-seam"></i>

                            Inventory

                        </a>

                    </li>

                    <li>

                        <a
                            href="/app/dashboard/inventory_management/history/index.php"
                            class="<?= ($page == 'inventory_history') ? 'active' : ''; ?>"
                        >

                            <i class="bi bi-clock-history"></i>

                            Inventory History

                        </a>

                    </li>

                </ul>

            </li>


            <li>

                <a
                    href="/app/dashboard/data_management/index.php"
                    class="<?= ($menu == 'data_management') ? 'active' : ''; ?>"
                >

                    <i class="bi bi-database"></i>

                    Data Management

                </a>

            </li>


            <li>

                <a
                    href="/app/dashboard/reports/index.php"
                    class="<?= ($menu == 'reports') ? 'active' : ''; ?>"
                >

                    <i class="bi bi-bar-chart-line"></i>

                    Reports

                </a>

            </li>


            <li>

                <a
                    href="/app/dashboard/audit_logs/index.php"
                    class="<?= ($page == 'audit_logs') ? 'active' : ''; ?>"
                >

                    <i class="bi bi-shield-check"></i>

                    Audit Logs

                </a>

            </li>


            <li>

                <a
                    href="/app/dashboard/users/index.php"
                    class="<?= ($menu == 'users') ? 'active' : ''; ?>"
                >

                    <i class="bi bi-people"></i>

                    User Management

                </a>

            </li>

        <?php endif; ?>


        <!-- =====================================================
             LOGOUT
             AVAILABLE TO EVERY AUTHENTICATED USER
        ====================================================== -->

        <li>

            <a href="/app/auth/logout.php">

                <i class="bi bi-box-arrow-right"></i>

                Logout

            </a>

        </li>

    </ul>

</aside>