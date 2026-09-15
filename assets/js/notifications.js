document.addEventListener("DOMContentLoaded", () => {

    const button =
        document.getElementById("notification-button");

    const wrapper =
        document.getElementById("notification-wrapper");

    const panel =
        document.getElementById("notification-panel");

    const refreshButton =
        document.getElementById("notification-refresh");

    const list =
        document.getElementById("notification-list");

    const badge =
        document.getElementById("notification-badge");

    const updated =
        document.getElementById("notification-updated");


    const countAll =
        document.getElementById("notification-count-all");

    const countOut =
        document.getElementById("notification-count-out");

    const countLow =
        document.getElementById("notification-count-low");

    const countExpiry =
        document.getElementById("notification-count-expiry");


    if (!button || !panel || !list) {

        console.warn(
            "Notification elements were not found."
        );

        return;

    }


    let notifications = [];

    let currentFilter = "all";


    // =====================================================
    // OPEN / CLOSE PANEL
    // =====================================================

    button.addEventListener("click", (event) => {

        event.stopPropagation();

        const isOpen =
            !panel.hasAttribute("hidden");

        if (isOpen) {

            closePanel();

        } else {

            openPanel();

        }

    });


    function openPanel() {

        panel.removeAttribute("hidden");

        button.setAttribute(
            "aria-expanded",
            "true"
        );

        loadNotifications();

    }


    function closePanel() {

        panel.setAttribute(
            "hidden",
            ""
        );

        button.setAttribute(
            "aria-expanded",
            "false"
        );

    }


    document.addEventListener("click", (event) => {

        if (
            !wrapper.contains(event.target)
        ) {

            closePanel();

        }

    });


    // =====================================================
    // LOAD NOTIFICATIONS
    // =====================================================

    async function loadNotifications() {

        setLoading();


        try {

            const response = await fetch(
                "/app/Controllers/get_notifications.php",
                {
                    method: "GET",
                    headers: {
                        "Accept": "application/json"
                    },
                    cache: "no-store"
                }
            );


            if (!response.ok) {

                throw new Error(
                    "HTTP " + response.status
                );

            }


            const result =
                await response.json();


            if (
                !result.success
            ) {

                throw new Error(
                    result.message ||
                    "Unable to load notifications."
                );

            }


            notifications =
                Array.isArray(result.notifications)
                    ? result.notifications
                    : [];


            updateSummary(
                result.summary
            );


            renderNotifications();


            updateTimestamp();


        }
        catch (error) {

            console.error(
                "Notification error:",
                error
            );


            list.innerHTML = `

                <div class="notification-empty">

                    <i class="bi bi-exclamation-circle"></i>

                    <span>
                        Unable to load alerts.
                    </span>

                </div>

            `;


            if (updated) {

                updated.textContent =
                    "Unable to check alerts";

            }

        }

    }


    // =====================================================
    // SUMMARY
    // =====================================================

    function updateSummary(summary) {

        summary = summary || {};


        const all =
            Number(summary.all || 0);

        const out =
            Number(summary.out_of_stock || 0);

        const low =
            Number(summary.low_stock || 0);

        const expiry =
            Number(summary.near_expiry || 0);


        countAll.textContent =
            all;

        countOut.textContent =
            out;

        countLow.textContent =
            low;

        countExpiry.textContent =
            expiry;


        /*
         * Badge represents the number of
         * unique products requiring attention.
         *
         * If one product is both low stock
         * and near expiry, it is counted once.
         */

        if (all > 0) {

            badge.textContent = all;

            badge.hidden = false;

        } else {

            badge.textContent = "0";

            badge.hidden = true;

        }

    }


    // =====================================================
    // FILTERS
    // =====================================================

    document
        .querySelectorAll(
            "[data-notification-filter]"
        )
        .forEach(control => {

            control.addEventListener(
                "click",
                () => {

                    currentFilter =
                        control.dataset.notificationFilter;

                    updateFilterState();

                    renderNotifications();

                }
            );

        });


    function updateFilterState() {

        document
            .querySelectorAll(
                ".notification-tab"
            )
            .forEach(tab => {

                tab.classList.toggle(
                    "is-active",
                    tab.dataset.notificationFilter ===
                    currentFilter
                );

            });


        document
            .querySelectorAll(
                ".notification-summary-card"
            )
            .forEach(card => {

                card.classList.toggle(
                    "is-active",
                    card.dataset.notificationFilter ===
                    currentFilter
                );

            });

    }


    // =====================================================
    // RENDER
    // =====================================================

    function renderNotifications() {

        let filtered =
            notifications;


        if (
            currentFilter !== "all"
        ) {

            filtered =
                notifications.filter(
                    notification =>
                        notification.type ===
                        currentFilter
                );

        }


        if (
            filtered.length === 0
        ) {

            list.innerHTML = `

                <div class="notification-empty">

                    <i class="bi bi-check-circle"></i>

                    <span>
                        No alerts in this category.
                    </span>

                </div>

            `;

            return;

        }


        list.innerHTML =
            filtered
                .map(
                    createNotificationHTML
                )
                .join("");

    }


    // =====================================================
    // NOTIFICATION CARD
    // =====================================================

    function createNotificationHTML(notification) {

        const type =
            notification.type || "system";


        const productName =
            escapeHTML(
                notification.product_name ||
                "Unknown product"
            );


        const message =
            escapeHTML(
                notification.message ||
                ""
            );


        let icon =
            "bi-bell";


        let iconClass =
            "notification-icon";


        if (
            type === "out_of_stock"
        ) {

            icon =
                "bi-x-circle";

            iconClass +=
                " notification-icon-out";

        }
        else if (
            type === "low_stock"
        ) {

            icon =
                "bi-exclamation-triangle";

            iconClass +=
                " notification-icon-low";

        }
        else if (
            type === "near_expiry"
        ) {

            icon =
                "bi-clock-history";

            iconClass +=
                " notification-icon-expiry";

        }


        return `

            <div
                class="notification-item"
                data-notification-type="${escapeHTML(type)}"
            >

                <div class="${iconClass}">

                    <i class="bi ${icon}"></i>

                </div>

                <div class="notification-content">

                    <div class="notification-message">

                        ${message}

                    </div>

                    <strong>
                        ${productName}
                    </strong>

                    ${
                        notification.details
                            ? `
                                <small>
                                    ${escapeHTML(
                                        notification.details
                                    )}
                                </small>
                              `
                            : ""
                    }

                </div>

            </div>

        `;

    }


    // =====================================================
    // LOADING
    // =====================================================

    function setLoading() {

        list.innerHTML = `

            <div class="notification-loading">

                <i class="bi bi-arrow-repeat"></i>

                Loading alerts...

            </div>

        `;

    }


    // =====================================================
    // TIMESTAMP
    // =====================================================

    function updateTimestamp() {

        if (!updated) {
            return;
        }


        const now =
            new Date();


        updated.textContent =
            "Updated " +
            now.toLocaleTimeString(
                [],
                {
                    hour: "2-digit",
                    minute: "2-digit"
                }
            );

    }


    // =====================================================
    // REFRESH BUTTON
    // =====================================================

    if (refreshButton) {

        refreshButton.addEventListener(
            "click",
            (event) => {

                event.stopPropagation();

                loadNotifications();

            }
        );

    }


    // =====================================================
    // AUTO REFRESH
    // =====================================================

    /*
     * Keep the notification badge live.
     *
     * This checks every 30 seconds.
     */

    setInterval(
        loadNotifications,
        30000
    );


    // =====================================================
    // HTML ESCAPE
    // =====================================================

    function escapeHTML(value) {

        return String(value)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");

    }


    // =====================================================
    // INITIAL LOAD
    // =====================================================

    loadNotifications();

});