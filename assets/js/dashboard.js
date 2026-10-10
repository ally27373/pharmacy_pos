console.log("Dashboard.js loaded successfully!");

function toggleChartMenu(button)
{
    const menu =
        button.nextElementSibling;

    document
        .querySelectorAll(
            ".chart-menu-dropdown.show"
        )
        .forEach(
            dropdown => {

                if (dropdown !== menu) {

                    dropdown.classList.remove("show");

                }

            }
        );

    menu.classList.toggle("show");
}


function closeAllChartMenus()
{
    document
        .querySelectorAll(
            ".chart-menu-dropdown.show"
        )
        .forEach(
            menu => {

                menu.classList.remove("show");

            }
        );
}


document.addEventListener(
    "click",
    function(event)
    {

        if (
            !event.target.closest(
                ".chart-menu"
            )
        ) {

            closeAllChartMenus();

        }

    }
);


// ================================
// Best Seller Controls
// ================================

function updateBestSeller() {

    const month =
        document.getElementById("bestMonth").value;

    const year =
        document.getElementById("bestYear").value;

    const url =
        new URL(window.location.href);

    url.searchParams.set("best_month", month);
    url.searchParams.set("best_year", year);

    window.location.href = url;
}

function showBestProducts() {

    const url =
        new URL(window.location.href);

    url.searchParams.set("mode", "best");

    window.location.href = url;
}

function showLeastProducts() {

    const url =
        new URL(window.location.href);

    url.searchParams.set("mode", "least");

    window.location.href = url;
}

function setTopProducts(limit) {

    const url =
        new URL(window.location.href);

    url.searchParams.set("best_limit", limit);

    window.location.href = url;
}

async function exportBestSellerCSV() {
    const proceed = await NxSwal.confirm(
        "Export best seller CSV?",
        "This will download the best seller data as a CSV file.",
        { confirmText: "Download CSV", icon: "question" }
    );

    if (!proceed) return;

    window.location.href =
        "/app/export/export_best_seller.php";

    if (typeof NxToast !== 'undefined') {
        NxToast.show("📥 Best seller CSV exported successfully.", "success");
    }
}

function updateFastMoving(){

    const month =
        document.getElementById("fastMonth").value;

    const year =
        document.getElementById("fastYear").value;

    const url =
        new URL(window.location.href);

    url.searchParams.set("fast_month", month);

    url.searchParams.set("fast_year", year);

    window.location.href = url.toString();

}

function setFastLimit(limit)
{
    const month =
        document.getElementById("fastMonth").value;

    const year =
        document.getElementById("fastYear").value;

    const url =
        new URL(window.location.href);

    url.searchParams.set(
        "fast_month",
        month
    );

    url.searchParams.set(
        "fast_year",
        year
    );

    url.searchParams.set(
        "fast_limit",
        limit
    );

    window.location.href = url;
}

// ================================
// KPI Card Drill-down
// ================================

const KPI_STATE = {
    metric: null,
    page: 1
};

/*
 * Move the modal to the end of <body> before Bootstrap ever sees it.
 *
 * Bootstrap documents modals as direct children of <body>, and metrics.php
 * emits this one from inside .dashboard-content. That is currently harmless
 * -- none of the ancestors open a stacking context -- but if one ever does
 * (transform, filter, will-change, isolation, contain), the modal is trapped
 * inside it and no z-index on .modal can lift it above a sibling navbar.
 * Relocating removes the possibility instead of relying on nobody adding one.
 */
(function relocateKpiModal() {
    const modal = document.getElementById("kpiDetailModal");

    if (modal && modal.parentNode !== document.body) {
        document.body.appendChild(modal);
    }
})();

/*
 * Values come from the database, so every one of them is escaped before it
 * reaches innerHTML. A product name containing markup must never be parsed
 * as markup.
 */
function kpiEscape(value)
{
    if (value === null || value === undefined) return "";

    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function kpiPeso(value)
{
    return "₱" + Number(value || 0).toLocaleString("en-PH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function kpiNumber(value)
{
    return Number(value || 0).toLocaleString("en-PH");
}

/*
 * The sales table stores Manila wall-clock time. Formatting the string
 * directly avoids reinterpreting it in the browser's timezone, which would
 * silently shift every timestamp for anyone outside Asia/Manila.
 */
function kpiDate(value)
{
    const raw = String(value || "").trim();

    const match = raw.match(
        /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/
    );

    if (!match) return kpiEscape(raw);

    const [, year, month, day, hours, minutes] = match;
    const hour = Number(hours) % 12 || 12;
    const meridiem = Number(hours) < 12 ? "AM" : "PM";

    return `${month}/${day}/${year} ${hour}:${minutes} ${meridiem}`;
}

function kpiCurrentPeriod()
{
    const filter = document.getElementById("dashboardFilter");
    return filter ? filter.value : "7days";
}

function kpiTable(columns, rows, cellFor)
{
    if (!rows.length) {
        return `
            <div class="alert alert-light border mb-0">
                No records for this period.
            </div>
        `;
    }

    const head = columns
        .map((column) => `<th>${kpiEscape(column)}</th>`)
        .join("");

    const body = rows
        .map((row) => `
            <tr>${cellFor(row).map(
                (cell) => `<td>${cell}</td>`
            ).join("")}</tr>
        `)
        .join("");

    return `
        <div class="table-responsive">
            <table class="table table-sm align-middle kpi-detail-table mb-0">
                <thead class="table-light">
                    <tr>${head}</tr>
                </thead>
                <tbody>${body}</tbody>
            </table>
        </div>
    `;
}

function kpiSalesCells(row)
{
    return [
        `<strong>${kpiEscape(row.transaction_number)}</strong>`,
        kpiDate(row.created_at),
        kpiEscape(row.cashier),
        kpiNumber(row.item_count),
        kpiEscape(row.payment_methods || "—"),
        kpiPeso(row.discount_amount),
        `<strong>${kpiPeso(row.total_amount)}</strong>`
    ];
}

function kpiTransactionCells(row)
{
    const status = String(row.payment_status || "");

    const badge = status === "Paid"
        ? "success"
        : (status === "Refunded" ? "danger" : "warning");

    return [
        `<strong>${kpiEscape(row.transaction_number)}</strong>`,
        kpiDate(row.created_at),
        kpiEscape(row.cashier),
        kpiNumber(row.item_count),
        kpiPeso(row.total_amount),
        `<span class="badge text-bg-${badge}">${kpiEscape(status || "—")}</span>`
    ];
}

function kpiItemCells(row)
{
    return [
        `<strong>${kpiEscape(row.product_name)}</strong>`,
        kpiEscape(row.category_name),
        kpiNumber(row.qty_sold),
        kpiNumber(row.times_sold),
        `<strong>${kpiPeso(row.revenue)}</strong>`
    ];
}

function kpiGrowthSummary(growth)
{
    if (!growth) return "";

    const current = Number(growth.current_sales || 0);
    const previous = Number(growth.previous_sales || 0);
    const change = current - previous;

    const percent = growth.growth_percent;

    const percentText = percent === null
        ? "No baseline"
        : `${Number(percent) > 0 ? "+" : ""}${Number(percent).toFixed(2)}%`;

    const changeClass = change > 0
        ? "text-success"
        : (change < 0 ? "text-danger" : "text-body-secondary");

    return `
        <div class="row g-3 mb-3">
            <div class="col-sm-4">
                <div class="kpi-summary-box">
                    <span>Current period</span>
                    <strong>${kpiPeso(current)}</strong>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="kpi-summary-box">
                    <span>${kpiEscape(growth.comparison_label || "Previous period")}</span>
                    <strong>${kpiPeso(previous)}</strong>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="kpi-summary-box">
                    <span>Change</span>
                    <strong class="${changeClass}">
                        ${kpiPeso(change)}
                        <small class="d-block">${kpiEscape(percentText)}</small>
                    </strong>
                </div>
            </div>
        </div>
    `;
}

function kpiPeriodLabel(period)
{
    const labels = {
        today: "Today",
        "7days": "Last 7 days",
        "30days": "Last 30 days",
        "3months": "Last 3 months",
        "1year": "This year",
        all: "All time"
    };

    return labels[period] || period;
}

function kpiRenderBody(payload)
{
    const detail = payload.detail || {};
    const rows = detail.rows || [];

    const body = document.getElementById("kpiDetailBody");

    const periodLine =
        `<p class="text-body-secondary small mb-3">${kpiEscape(kpiPeriodLabel(payload.period))}</p>`;

    if (payload.metric === "items") {

        body.innerHTML = periodLine + kpiTable(
            ["Product", "Category", "Qty Sold", "Times Sold", "Revenue"],
            rows,
            kpiItemCells
        );

        return;
    }

    /*
     * Transactions is backed by the same completed sales as Total Sales, so
     * it shows payment status rather than repeating the identical money
     * columns.
     */
    if (payload.metric === "transactions") {

        body.innerHTML = periodLine + kpiTable(
            ["Transaction", "Date", "Cashier", "Items", "Amount", "Status"],
            rows,
            kpiTransactionCells
        );

        return;
    }

    const isGrowth = payload.metric === "growth";

    body.innerHTML =
        (isGrowth ? kpiGrowthSummary(detail.growth) : "")
        + periodLine
        + kpiTable(
            ["Transaction", "Date", "Cashier", "Items", "Payment", "Discount", "Amount"],
            rows,
            kpiSalesCells
        );
}

function kpiRenderFooter(detail)
{
    const footer = document.getElementById("kpiDetailFooter");

    const page = Number(detail.page || 1);
    const pages = Number(detail.pages || 1);
    const total = Number(detail.total || 0);
    const perPage = Number(detail.per_page || 20);

    const first = total === 0 ? 0 : ((page - 1) * perPage) + 1;
    const last = Math.min(page * perPage, total);

    footer.innerHTML = `
        <button
            type="button"
            class="btn btn-outline-secondary btn-sm"
            data-kpi-page="prev"
            ${page <= 1 ? "disabled" : ""}
        >
            Previous
        </button>

        <span class="kpi-page-info">
            Page ${page} of ${pages} · Showing ${first}–${last} of ${total}
        </span>

        <button
            type="button"
            class="btn btn-outline-secondary btn-sm"
            data-kpi-page="next"
            ${page >= pages ? "disabled" : ""}
        >
            Next
        </button>
    `;
}

function kpiSetLoading()
{
    document.getElementById("kpiDetailBody").innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-success"></div>
            <p class="mt-3 mb-0">Loading real data…</p>
        </div>
    `;

    document.getElementById("kpiDetailFooter").innerHTML = "";
}

async function kpiLoad(page)
{
    const body = document.getElementById("kpiDetailBody");
    const footer = document.getElementById("kpiDetailFooter");

    KPI_STATE.page = page;
    kpiSetLoading();

    const url =
        "ajax/kpi_detail.php"
        + "?metric=" + encodeURIComponent(KPI_STATE.metric)
        + "&period=" + encodeURIComponent(kpiCurrentPeriod())
        + "&page=" + encodeURIComponent(String(page));

    try {
        const response = await fetch(url, {
            headers: { "Accept": "application/json" },
            cache: "no-store"
        });

        const payload = await response.json();

        if (!response.ok || payload.status !== "success") {
            throw new Error(
                payload.message || "Unable to load KPI details."
            );
        }

        kpiRenderBody(payload);
        kpiRenderFooter(payload.detail || {});
    } catch (error) {
        console.error(error);
        body.innerHTML = `
            <div class="alert alert-danger mb-0">
                ${kpiEscape(error.message || "Unable to load KPI details.")}
            </div>
        `;
        footer.innerHTML = "";
    }
}

function kpiOpen(metric)
{
    const titles = {
        sales: "Total Sales — transactions",
        items: "Items Sold — products",
        transactions: "Transactions",
        growth: "Sales Growth — comparison"
    };

    KPI_STATE.metric = metric;

    document.getElementById("kpiDetailTitle").textContent =
        titles[metric] || "KPI details";

    const modalElement = document.getElementById("kpiDetailModal");
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

    modal.show();

    kpiLoad(1);
}

document.querySelectorAll(".kpi-card").forEach((card) => {
    const open = () => kpiOpen(card.dataset.kpi);

    card.addEventListener("click", open);

    card.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            open();
        }
    });
});

/*
 * The modal is rendered by partials/metrics.php, which index.php includes
 * before this file loads. Guard anyway so a future script-order change
 * cannot throw here and take the rest of the file with it.
 */
const kpiFooter = document.getElementById("kpiDetailFooter");

if (kpiFooter) {

    kpiFooter.addEventListener("click", (event) => {
        const button = event.target.closest("[data-kpi-page]");

        if (!button || button.disabled) return;

        const next = button.dataset.kpiPage === "next"
            ? KPI_STATE.page + 1
            : KPI_STATE.page - 1;

        kpiLoad(Math.max(1, next));
    });

}

