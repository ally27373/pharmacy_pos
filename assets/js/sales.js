console.log("Sales JS Loaded");

/* ===========================
   SEARCH
   ===========================
   The transactions table is paginated 20 rows at a time, so
   searching submits the form and lets SQL search every row.
   Filtering only the rows already on screen reports "no results"
   for a transaction sitting three pages away.
*/
if (typeof NxSearch !== "undefined") {
    NxSearch.submitOnType(document.getElementById("sales-search"));
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

function getSalesModal() {
    const element = document.getElementById("saleModal");
    if (!element || !window.bootstrap) return null;
    return bootstrap.Modal.getOrCreateInstance(element);
}

/* ===========================
   ESCAPING
   Server text is interpolated
   into innerHTML below, so it
   has to be escaped. A refusal
   body is JSON and a fatal body
   is HTML; neither is ours to
   trust.
=========================== */
function salesEscape(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
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

            /*
             * Read the body before deciding it is unusable. get_sale.php
             * answers a lapsed session with a JSON refusal from
             * AuthMiddleware::requireAuthenticatedApi(), and a PHP fatal
             * truncates the document, so both arrive as a body worth
             * showing. Reporting a bare "unable to load" made an
             * authorisation refusal and a server crash look identical, and
             * sent the fix after the wrong layer.
             */
            const html = await response.text();

            if (!response.ok) {
                throw new Error(
                    "The server refused this request (HTTP "
                    + response.status + "). "
                    + NxHttp.describe(html)
                );
            }

            body.innerHTML = html;

            const printBtn = document.getElementById("print-receipt");
            if (printBtn) {
                printBtn.addEventListener("click", function () {
                    printReceipt(this.dataset.sale);
                }, { once: true });
            }
        } catch (error) {
            console.error(error);
            body.innerHTML = `
                <div class="alert alert-danger mb-0">
                    <strong>Unable to load transaction details.</strong>
                    <div class="mt-1 small">${salesEscape(
                        error.message || "No further detail was reported."
                    )}</div>
                </div>
            `;
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

async function printReceipt(saleId) {
    const proceed = await NxSwal.confirm(
        "Print receipt?",
        "The receipt will open in a new window ready for printing.",
        { confirmText: "Open receipt", icon: "question" }
    );

    if (!proceed) return;

    // The dialog promises the receipt opens "ready for printing", so it
    // now actually does: auto=1 makes the page print itself on load.
    window.open(
        "/app/dashboard/pos/receipt.php?sale_id=" + encodeURIComponent(saleId) + "&auto=1",
        "_blank"
    );

    if (typeof NxToast !== 'undefined') {
        NxToast.show("🖨 Receipt window opened.", "info");
    }
}
