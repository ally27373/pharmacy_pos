/* =======================================================
   ARCHIVED PRODUCTS
   Unarchive + permanent delete, both behind a confirmation.
   Toast feedback on success, alert on failure.
   ======================================================= */

console.log("Archive JS Loaded");

function archiveShowAlert(message, type = "danger") {
    const container = document.getElementById("archive-alert");

    if (!container) return;

    container.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;

    setTimeout(() => { container.innerHTML = ""; }, 4000);
}

function archiveToast(message, type = "success") {
    if (typeof NxToast !== "undefined") {
        NxToast.show(message, type);
        return;
    }
    archiveShowAlert(message, type);
}

/**
 * Shared POST handler. `pendingLabel` swaps the button text so a
 * double-click can't fire the same request twice while it is in flight.
 */
async function archivePost(url, productId, button, pendingLabel, iconMarkup) {
    const originalHtml = button.innerHTML;

    button.disabled = true;
    button.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>${pendingLabel}`;

    const body = new URLSearchParams({ product_id: productId });

    try {
        const response = await fetch(url, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                "Accept": "application/json",
            },
            body,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok || data.status !== "success") {
            throw new Error(data.message || "The request could not be completed.");
        }

        return data;
    } finally {
        button.disabled = false;
        button.innerHTML = iconMarkup || originalHtml;
    }
}

/* ---------- UNARCHIVE ---------- */
document.querySelectorAll(".unarchive-product").forEach((button) => {
    button.addEventListener("click", async () => {
        const productId = button.dataset.product;
        const productName = button.dataset.name || "this product";

        if (!confirm(`Unarchive "${productName}"?\n\nThis will restore it to the active inventory list.`)) {
            return;
        }

        try {
            await archivePost(
                "ajax/unarchive.php",
                productId,
                button,
                "Restoring",
                '<i class="bi bi-arrow-counterclockwise"></i>'
            );

            archiveToast(`✅ "${productName}" restored to inventory.`, "success");
            setTimeout(() => { window.location.reload(); }, 700);
        } catch (error) {
            archiveToast(`❌ ${error.message}`, "danger");
        }
    });
});

/* ---------- PERMANENT DELETE ---------- */
document.querySelectorAll(".delete-archived-product").forEach((button) => {
    button.addEventListener("click", async () => {
        const productId = button.dataset.product;
        const productName = button.dataset.name || "this product";

        if (!confirm(`Permanently delete "${productName}"?\n\nThis cannot be undone. Only archived products with no sales or batch history can be deleted.`)) {
            return;
        }

        if (!confirm(`Last chance — are you sure you want to delete "${productName}" for good?`)) {
            return;
        }

        try {
            await archivePost(
                "ajax/delete_archived.php",
                productId,
                button,
                "Deleting",
                '<i class="bi bi-trash-fill"></i>'
            );

            archiveToast(`🗑 "${productName}" permanently deleted.`, "success");
            setTimeout(() => { window.location.reload(); }, 700);
        } catch (error) {
            archiveToast(`❌ ${error.message}`, "danger");
        }
    });
});