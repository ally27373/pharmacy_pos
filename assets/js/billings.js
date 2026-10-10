console.log("Billings JS Loaded");

/* ===========================
   PRINT RECEIPT
=========================== */
async function printReceipt(saleId) {
    const proceed = await NxSwal.confirm(
        "Print receipt?",
        "The receipt will open in a new window ready for printing.",
        { confirmText: "Open receipt", icon: "question" }
    );

    if (!proceed) return;

    window.open(
        "/app/dashboard/pos/receipt.php?sale_id=" + encodeURIComponent(saleId),
        "_blank"
    );

    if (typeof NxToast !== 'undefined') {
        NxToast.show("🖨 Receipt opened for printing.", "info");
    }
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
   ===========================
   The billings table is paginated, so searching submits the form and
   lets SQL search every row. The previous row filter read
   data-transaction / data-invoice off <tr class="billing-row">, but
   those rows never carried those attributes, so every value came back
   empty and each keystroke hid the entire table.
*/
if (typeof NxSearch !== "undefined") {
    NxSearch.submitOnType(document.getElementById("billing-search"));
}

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
