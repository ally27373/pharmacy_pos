console.log("Inventory History JS Loaded");

const searchInput = document.getElementById("history-search");
const actionFilter = document.getElementById("history-action-filter");
const dateFrom = document.getElementById("history-date-from");
const dateTo = document.getElementById("history-date-to");
const clearButton = document.getElementById("clear-history-filters");

let historySearchTimer = null;

function applyHistoryFilters() {
    const params = new URLSearchParams(window.location.search);

    const search = searchInput ? searchInput.value.trim() : "";
    const action = actionFilter ? actionFilter.value : "";
    const from = dateFrom ? dateFrom.value : "";
    const to = dateTo ? dateTo.value : "";

    if (search) params.set("search", search);
    else params.delete("search");

    if (action) params.set("action", action);
    else params.delete("action");

    if (from) params.set("from", from);
    else params.delete("from");

    if (to) params.set("to", to);
    else params.delete("to");

    params.set("page", "1");
    if (!params.get("limit")) params.set("limit", "20");

    window.location.search = params.toString();
}

if (searchInput) {
    searchInput.addEventListener("input", () => {
        clearTimeout(historySearchTimer);
        historySearchTimer = setTimeout(applyHistoryFilters, 350);
    });
}

if (actionFilter) {
    actionFilter.addEventListener("change", applyHistoryFilters);
}

if (dateFrom) {
    dateFrom.addEventListener("change", applyHistoryFilters);
}

if (dateTo) {
    dateTo.addEventListener("change", applyHistoryFilters);
}

if (clearButton) {
    clearButton.addEventListener("click", () => {
        window.location.href = window.location.pathname + "?page=1&limit=20";
    });
}
