console.log("Billings JS Loaded");

/* ===========================
   PRINT RECEIPT
=========================== */
function printReceipt(saleId) {
    window.open(
        "/pharmacy_pos/app/dashboard/pos/receipt.php?sale_id=" + encodeURIComponent(saleId),
        "_blank"
    );
}

/* ===========================
   BOOTSTRAP MODAL HELPERS
=========================== */
function clearOrphanedModalState() {
    document.querySelectorAll(".modal-backdrop").forEach(backdrop => backdrop.remove());
    document.body.classList.remove("modal-open");
    document.body.style.removeProperty("overflow");
    document.body.style.removeProperty("padding-right");
}

/* ===========================
   VIEW BILLING
=========================== */
document.querySelectorAll(".view-billing").forEach(button => {
    button.addEventListener("click", async () => {
        const paymentId = button.dataset.payment;
        const modalElement = document.getElementById("billingModal");
        const body = document.getElementById("billingModalBody");
        if (!modalElement || !body) return;

        // Remove a leftover backdrop before opening a new billing record.
        clearOrphanedModalState();

        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        modal.show();

        body.innerHTML = `
            <div class="text-center p-5">
                <div class="spinner-border text-success"></div>
                <p class="mt-3">Loading billing...</p>
            </div>
        `;

        try {
            const response = await fetch(
                "ajax/get_billing.php?id=" + encodeURIComponent(paymentId),
                { headers: { "Accept": "text/html" }, cache: "no-store" }
            );
            if (!response.ok) throw new Error("Unable to load billing.");

            body.innerHTML = await response.text();

            const printBtn = document.getElementById("print-billing-receipt");
            if (printBtn) {
                printBtn.addEventListener("click", function () {
                    printReceipt(this.dataset.sale);
                }, { once: true });
            }
        } catch (error) {
            console.error(error);
            body.innerHTML = `<div class="alert alert-danger mb-0">Unable to load billing details.</div>`;
        }
    });
});

/* ===========================
   SEARCH BILLINGS
=========================== */
const billingSearch = document.getElementById("billing-search");
const billingRows = document.querySelectorAll(".billing-row");

function filterBillings() {
    if (!billingSearch) return;
    const keyword = billingSearch.value.toLowerCase().trim();

    billingRows.forEach(row => {
        const transaction = row.dataset.transaction || "";
        const invoice = row.dataset.invoice || "";
        const payment = row.dataset.payment || "";
        const match = transaction.includes(keyword) || invoice.includes(keyword) || payment.includes(keyword);
        row.style.display = match ? "" : "none";
    });
}

if (billingSearch) billingSearch.addEventListener("input", filterBillings);

/* ===========================
   MODAL CLOSE HARDENING
=========================== */
const billingModalElement = document.getElementById("billingModal");

if (billingModalElement) {
    billingModalElement.addEventListener("hidden.bs.modal", () => {
        const instance = bootstrap.Modal.getInstance(billingModalElement);
        if (instance) instance.dispose();

        requestAnimationFrame(() => {
            clearOrphanedModalState();
            setTimeout(clearOrphanedModalState, 50);
        });
    });
}
