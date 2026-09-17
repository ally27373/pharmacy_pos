<link rel="stylesheet" href="/assets/css/navbar.css">
<link rel="stylesheet" href="/assets/css/notifications.css">

<nav class="navbar-custom">

    <button
        type="button"
        class="navbar-hamburger"
        id="navbar-hamburger"
        aria-label="Open navigation menu"
        aria-expanded="false"
    >
        <i class="bi bi-list"></i>
    </button>

    <div class="navbar-title">
        <h4>Dashboard</h4>
    </div>

    <div class="navbar-datetime" id="navbar-datetime" aria-live="polite">
        <i class="bi bi-clock"></i>
        <span id="navbar-datetime-text">Loading date &amp; time...</span>
    </div>

    <div class="navbar-actions">

        <div class="notification-wrapper" id="notification-wrapper">

            <button
                type="button"
                class="notification-button"
                id="notification-button"
                aria-label="Open notifications"
                aria-expanded="false"
                aria-controls="notification-panel"
            >
                <i class="bi bi-bell"></i>
                <span
                    class="notification-badge"
                    id="notification-badge"
                    hidden
                >0</span>
            </button>

            <div
                class="notification-panel"
                id="notification-panel"
                hidden
            >
                <div class="notification-panel-header">
                    <div>
                        <h5>Notifications</h5>
                        <small id="notification-updated">Checking live alerts...</small>
                    </div>
                    <button
                        type="button"
                        class="notification-refresh"
                        id="notification-refresh"
                        title="Refresh notifications"
                        aria-label="Refresh notifications"
                    >
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>

                <div class="notification-summary">
                    <button type="button" class="notification-summary-card is-active" data-notification-filter="all">
                        <span class="summary-icon summary-icon-all"><i class="bi bi-bell-fill"></i></span>
                        <span class="summary-text"><strong id="notification-count-all">0</strong><small>All Alerts</small></span>
                    </button>

                    <button type="button" class="notification-summary-card" data-notification-filter="out_of_stock">
                        <span class="summary-icon summary-icon-out"><i class="bi bi-x-circle"></i></span>
                        <span class="summary-text"><strong id="notification-count-out">0</strong><small>Out of Stock</small></span>
                    </button>

                    <button type="button" class="notification-summary-card" data-notification-filter="low_stock">
                        <span class="summary-icon summary-icon-low"><i class="bi bi-exclamation-triangle"></i></span>
                        <span class="summary-text"><strong id="notification-count-low">0</strong><small>Low Stock</small></span>
                    </button>

                    <button type="button" class="notification-summary-card" data-notification-filter="near_expiry">
                        <span class="summary-icon summary-icon-expiry"><i class="bi bi-clock-history"></i></span>
                        <span class="summary-text"><strong id="notification-count-expiry">0</strong><small>Near Expiry</small></span>
                    </button>
                </div>

                <div class="notification-tabs" role="tablist" aria-label="Notification filters">
                    <button type="button" class="notification-tab is-active" data-notification-filter="all">All Alerts</button>
                    <button type="button" class="notification-tab" data-notification-filter="low_stock">Low Stock</button>
                    <button type="button" class="notification-tab" data-notification-filter="out_of_stock">Out of Stock</button>
                    <button type="button" class="notification-tab" data-notification-filter="near_expiry">Near Expiry</button>
                </div>

                <div class="notification-list" id="notification-list" aria-live="polite">
                    <div class="notification-loading">
                        <i class="bi bi-arrow-repeat"></i>
                        Loading alerts...
                    </div>
                </div>

                <div class="notification-panel-footer">
                    <a href="/app/dashboard/inventory_management/index.php" class="notification-inventory-link">
                        <i class="bi bi-box-seam me-1"></i>
                        Open Inventory Management
                    </a>
                </div>
            </div>

        </div>

        <div class="user-info">
            <?php if (isset($_SESSION['username'])): ?>
                <span><?= htmlspecialchars($_SESSION['username']); ?></span>
            <?php endif; ?>
        </div>

    </div>

</nav>

<script>
(function () {
    var target = document.getElementById('navbar-datetime-text');
    if (!target) return;

    function updateDateTime() {
        var now = new Date();
        var formatted = new Intl.DateTimeFormat('en-PH', {
            timeZone: 'Asia/Manila',
            month: 'short',
            day: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        }).format(now);
        target.textContent = formatted;
    }

    updateDateTime();
    setInterval(updateDateTime, 1000);
})();
</script>

<script src="/assets/js/notifications.js"></script>
