console.log("Sales JS Loaded");

/* ===========================
   SEARCH
=========================== */
const searchInput = document.getElementById("sales-search");
const rows = document.querySelectorAll(".sales-row");

function filterSales() {
    if (!searchInput) return;
    const keyword = searchInput.value.toLowerCase().trim();
    rows.forEach(row => {
        const transaction = row.dataset.transaction || "";
        const invoice = row.dataset.invoice || "";
        const sale = row.dataset.sale || "";
        const payment = row.dataset.payment || "";
        const match = transaction.includes(keyword) || invoice.includes(keyword) || sale.includes(keyword) || payment.includes(keyword);
        row.style.display = match ? "" : "none";
    });
}

if (searchInput) searchInput.addEventListener("input", filterSales);

/* ===========================
   BOOTSTRAP MODAL HELPERS
=========================== */
function clearOrphanedModalState() {
    document.querySelectorAll(".modal-backdrop").forEach(backdrop => backdrop.remove());
    document.body.classList.remove("modal-open");
    document.body.style.removeProperty("overflow");
    document.body.style.removeProperty("padding-right");
}

function getSalesModal() {
    const element = document.getElementById("saleModal");
    if (!element || !window.bootstrap) return null;
    return bootstrap.Modal.getOrCreateInstance(element);
}

/* ===========================
   VIEW TRANSACTION
=========================== */
document.querySelectorAll(".view-sale").forEach(button => {
    button.addEventListener("click", async () => {
        const saleId = button.dataset.sale;
        const modalElement = document.getElementById("saleModal");
        const body = document.getElementById("saleModalBody");
        if (!modalElement || !body) return;

        // Remove a leftover backdrop before opening a new transaction.
        clearOrphanedModalState();

        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        modal.show();

        body.innerHTML = `
            <div class="text-center p-5">
                <div class="spinner-border text-success"></div>
                <p class="mt-3">Loading transaction...</p>
            </div>
        `;

        try {
            const response = await fetch(
                "ajax/get_sale.php?id=" + encodeURIComponent(saleId),
                { headers: { "Accept": "text/html" }, cache: "no-store" }
            );
            if (!response.ok) throw new Error("Unable to load transaction.");

            body.innerHTML = await response.text();

            const printBtn = document.getElementById("print-receipt");
            if (printBtn) {
                printBtn.addEventListener("click", function () {
                    printReceipt(this.dataset.sale);
                }, { once: true });
            }
        } catch (error) {
            console.error(error);
            body.innerHTML = `<div class="alert alert-danger mb-0">Unable to load transaction details.</div>`;
        }
    });
});

/* ===========================
   MODAL CLOSE HARDENING
   Bootstrap normally removes its own backdrop. This handler also
   removes any stale backdrop after the modal transition completes.
=========================== */
const saleModalElement = document.getElementById("saleModal");

if (saleModalElement) {
    saleModalElement.addEventListener("hidden.bs.modal", () => {
        const instance = bootstrap.Modal.getInstance(saleModalElement);
        if (instance) instance.dispose();

        // Run after Bootstrap finishes its DOM cleanup.
        requestAnimationFrame(() => {
            clearOrphanedModalState();
            setTimeout(clearOrphanedModalState, 50);
        });
    });
}

function printReceipt(saleId) {
    window.open(
        "/pharmacy_pos/app/dashboard/pos/receipt.php?sale_id=" + encodeURIComponent(saleId),
        "_blank"
    );
}
