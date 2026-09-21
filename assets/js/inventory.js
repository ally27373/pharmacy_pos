console.log("Inventory JS Loaded");

const searchInput = document.getElementById("inventory-search");
const categoryFilter = document.getElementById("category-filter");
const typeFilter = document.getElementById("type-filter");
const resetFilterButton = document.getElementById("inventory-reset");
let inventorySearchTimer = null;

function applyInventoryFilters(resetPage = true) {
    const params = new URLSearchParams(window.location.search);
    const search = searchInput?.value.trim() || "";
    const category = categoryFilter?.value || "";
    const type = typeFilter?.value || "";

    search ? params.set("search", search) : params.delete("search");
    category ? params.set("category", category) : params.delete("category");
    type ? params.set("type", type) : params.delete("type");
    if (resetPage) params.set("page", "1");
    if (!params.get("limit")) params.set("limit", "20");

    window.location.search = params.toString();
}

searchInput?.addEventListener("input", () => {
    clearTimeout(inventorySearchTimer);
    inventorySearchTimer = setTimeout(() => {
        try { sessionStorage.setItem("inventory_search_focus", "1"); } catch (e) {}
        applyInventoryFilters(true);
    }, 700);
});
categoryFilter?.addEventListener("change", () => applyInventoryFilters(true));
typeFilter?.addEventListener("change", () => applyInventoryFilters(true));
resetFilterButton?.addEventListener("click", () => {
    window.location.href = window.location.pathname + "?page=1&limit=20";
});

// After a filter reload triggered from the search box, restore focus
// (and caret at end) so typing can continue without re-clicking.
(function restoreInventorySearchFocus() {
    let restore = null;
    try { restore = sessionStorage.getItem("inventory_search_focus"); } catch (e) {}
    if (!restore || !searchInput) return;
    try { sessionStorage.removeItem("inventory_search_focus"); } catch (e) {}
    searchInput.focus();
    const end = searchInput.value.length;
    try { searchInput.setSelectionRange(end, end); } catch (e) {}
})();

function showAlert(message, type = "success") {
    const container = document.getElementById("inventory-alert");
    if (!container) return;

    container.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;

    setTimeout(() => { container.innerHTML = ""; }, 3500);
}

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
        credentials: "same-origin",
        cache: "no-store",
        ...options,
    });
    const data = await response.json();
    if (!response.ok || data.status === "error") {
        throw new Error(data.message || "Request failed.");
    }
    return data;
}

/* =========================
   VIEW PRODUCT / BATCHES
========================= */
document.querySelectorAll(".view-product").forEach(button => {
    button.addEventListener("click", async () => {
        const productId = button.dataset.product;
        const modalElement = document.getElementById("productModal");
        const body = document.getElementById("productModalBody");
        const modal = new bootstrap.Modal(modalElement);
        modal.show();

        body.innerHTML = `
            <div class="text-center p-5">
                <div class="spinner-border text-success"></div>
                <p class="mt-3">Loading product and batch details...</p>
            </div>`;

        try {
            const response = await fetch(`ajax/get_product.php?id=${encodeURIComponent(productId)}`, {
                credentials: "same-origin",
                cache: "no-store"
            });
            body.innerHTML = await response.text();
        } catch (error) {
            body.innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`;
        }
    });
});

/* =========================
   ADD PRODUCT
========================= */
const addModal = document.getElementById("addProductModal");
addModal?.addEventListener("shown.bs.modal", () => {
    const receivedDate = document.getElementById("received_date");
    if (receivedDate && !receivedDate.value) {
        receivedDate.value = new Date().toISOString().slice(0, 10);
    }
});

const saveProductButton = document.getElementById("save-product");
saveProductButton?.addEventListener("click", async () => {
    const button = saveProductButton;
    button.disabled = true;
    button.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Saving...`;

    try {
        const get = id => document.getElementById(id);
        const quantity = Number(get("quantity")?.value || 0);
const fields = [
    ["product_name", "Product Name"],
    ["category_id", "Category"],
    ["type_id", "Product Type"],
    ["supplier_name", "Supplier"],
    ["quantity", "Quantity"],
    ["unit_cost", "Unit Cost"],
    ["selling_price", "Selling Price"],
    ["received_date", "Date Received"]
];

        let valid = true;
        fields.forEach(([id]) => {
            const input = get(id);
            input?.classList.remove("is-invalid");
            if (!input || input.value.trim() === "") {
                input?.classList.add("is-invalid");
                valid = false;
            }
        });

        if (quantity > 0) {
            const batchInput = get("batch_number");
            batchInput?.classList.remove("is-invalid");
            if (!batchInput || batchInput.value.trim() === "") {
                batchInput?.classList.add("is-invalid");
                valid = false;
            }
        }

        if (!valid) {
            throw new Error("Please complete all required product and initial batch fields.");
        }

        const formData = new FormData();
        [
            "barcode", "product_name", "generic_name", "brand_name", "category_id", "type_id",
            "supplier_name", "dosage", "strength", "unit", "quantity", "unit_cost",
            "selling_price", "batch_number", "expiration_date", "received_date", "description"
        ].forEach(id => formData.append(id, get(id)?.value || ""));
        formData.append("is_test_data", get("is_test_data")?.checked ? "1" : "0");

        const typedBarcode = get("barcode")?.value.trim() || "";
        const result = await fetchJson("ajax/save_product.php", { method: "POST", body: formData });
        bootstrap.Modal.getInstance(addModal)?.hide();
        const barcodeNote = (!typedBarcode && result.barcode) ? ` No barcode entered — assigned "${result.barcode}".` : "";
        NxToast.flash(`✅ Added "${get("product_name")?.value || "product"}" — ${result.message || "Product added successfully!"}${barcodeNote}`, "success");
        window.location.href = window.location.pathname + "?page=1&limit=20";
    } catch (error) {
        showAlert(error.message, "danger");
    } finally {
        button.disabled = false;
        button.innerHTML = `<i class="bi bi-check-circle-fill me-2"></i>Save Product`;
    }
});

/* =========================
   EDIT PRODUCT MASTER DATA
========================= */
async function openEditProduct(productId) {
    try {
        const product = await fetchJson(`ajax/edit_product.php?id=${encodeURIComponent(productId)}`);
        const set = (id, value) => { const el = document.getElementById(id); if (el) el.value = value ?? ""; };

        set("edit_product_id", product.product_id);
        set("edit_product_name", product.product_name);
        set("edit_barcode", product.barcode);
        set("edit_generic_name", product.generic_name);
        set("edit_brand_name", product.brand_name);
        set("edit_dosage", product.dosage);
        set("edit_strength", product.strength);
        set("edit_unit", product.unit);
        set("edit_unit_cost", product.unit_cost);
        set("edit_selling_price", product.selling_price);
        set("edit_supplier_name", product.supplier_name);
        set("edit_description", product.description);
        const testDataCheckbox = document.getElementById("edit_is_test_data");
        if (testDataCheckbox) testDataCheckbox.checked = Number(product.is_test_data || 0) === 1;

        // Category and Product Type options are rendered server-side in index.php.
        // Do not call a separate load_dropdowns.php endpoint here.
        const category = document.getElementById("edit_category_id");
        const type = document.getElementById("edit_type_id");

        if (category) category.value = product.category_id ?? "";
        if (type) type.value = product.type_id ?? "";

        new bootstrap.Modal(document.getElementById("editProductModal")).show();
    } catch (error) {
        showAlert(error.message, "danger");
    }
}

document.querySelectorAll(".edit-product").forEach(button => {
    button.addEventListener("click", () => openEditProduct(button.dataset.product));
});

const updateProductButton = document.getElementById("update-product");
updateProductButton?.addEventListener("click", async function () {
    const button = this;
    button.disabled = true;
    button.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Updating...`;

    try {
        const get = id => document.getElementById(id);
        const formData = new FormData();
        [
            "edit_product_id", "edit_barcode", "edit_product_name", "edit_generic_name", "edit_brand_name",
            "edit_category_id", "edit_type_id", "edit_supplier_name", "edit_dosage", "edit_strength",
            "edit_unit", "edit_selling_price", "edit_description"
        ].forEach(id => {
            const field = get(id);
            const name = id.replace(/^edit_/, "");
            formData.append(name === "product_id" ? "product_id" : name, field?.value || "");
        });
        formData.append("is_test_data", get("edit_is_test_data")?.checked ? "1" : "0");

        const result = await fetchJson("ajax/update_product.php", { method: "POST", body: formData });
        NxToast.flash(`✅ Updated "${get("edit_product_name")?.value || "product"}" — ${result.message || "Product updated successfully!"}`, "success");
        window.location.reload();
    } catch (error) {
        // Hide the modal first so the error is visible instead of
        // being trapped behind the modal backdrop.
        bootstrap.Modal.getInstance(document.getElementById("editProductModal"))?.hide();
        showAlert(error.message, "danger");
    } finally {
        button.disabled = false;
        button.innerHTML = `<i class="bi bi-floppy-fill me-2"></i>Update Product`;
    }
});

/* =========================
   DELETE PRODUCT
========================= */
let deleteProductId = null;
let deleteProductName = "";

document.querySelectorAll(".delete-product").forEach(button => {
    button.addEventListener("click", () => {
        deleteProductId = button.dataset.product;
        deleteProductName = button.dataset.name || "";
        document.getElementById("delete-product-name").textContent = button.dataset.name || "";
        new bootstrap.Modal(document.getElementById("deleteProductModal")).show();
    });
});

document.getElementById("confirm-delete-product")?.addEventListener("click", async function () {
    if (!deleteProductId) return;
    const button = this;
    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Deleting...`;
    const formData = new FormData();
    formData.append("product_id", deleteProductId);

    try {
        const result = await fetchJson("ajax/delete_product.php", { method: "POST", body: formData });
        bootstrap.Modal.getInstance(document.getElementById("deleteProductModal"))?.hide();
        NxToast.flash(`🗑 Deleted "${deleteProductName || "product"}" — ${result.message || "Product deleted successfully!"}`, "success");
        window.location.reload();
    } catch (error) {
        // Hide the modal first so the error is visible instead of
        // being trapped behind the modal backdrop.
        bootstrap.Modal.getInstance(document.getElementById("deleteProductModal"))?.hide();
        showAlert(error.message, "danger");
    } finally {
        button.disabled = false;
        button.innerHTML = originalHtml;
    }
});

let cleanupTestProductId = null;

document.querySelectorAll(".cleanup-test-product").forEach(button => {
    button.addEventListener("click", () => {
        cleanupTestProductId = button.dataset.product || null;
        const name = document.getElementById("cleanup-test-product-name");
        if (name) name.textContent = button.dataset.name || "Selected test product";

        new bootstrap.Modal(document.getElementById("testDataCleanupModal")).show();
    });
});

document.getElementById("confirm-cleanup-test-product")?.addEventListener("click", async () => {
    if (!cleanupTestProductId) return;

    const button = document.getElementById("confirm-cleanup-test-product");
    button.disabled = true;
    button.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Cleaning...`;

    try {
        const formData = new FormData();
        formData.append("product_id", cleanupTestProductId);

        const result = await fetchJson("ajax/cleanup_test_product.php", {
            method: "POST",
            body: formData
        });

        bootstrap.Modal.getInstance(document.getElementById("testDataCleanupModal"))?.hide();
        NxToast.flash(`🧹 Cleaned up "${document.getElementById("cleanup-test-product-name")?.textContent || "test product"}" — ${result.message || "Test data cleaned up successfully!"}`, "success");
        window.location.reload();
    } catch (error) {
        showAlert(error.message, "danger");
    } finally {
        button.disabled = false;
        button.innerHTML = `<i class="bi bi-trash3-fill me-2"></i>Clean Up Test Data`;
    }
});

/* =========================
   BATCH STOCK ADJUSTMENT
========================= */
const stockModal = document.getElementById("stockModal");
const stockAction = document.getElementById("stock_action");
const stockBatchId = document.getElementById("stock_batch_id");
const newBatchFields = document.getElementById("new-batch-fields");
const stockUnitCostWrap = document.getElementById("stock-unit-cost-wrap");
let stockProductId = null;

async function loadStockBatches(productId) {
    stockBatchId.innerHTML = `<option value="">Loading batches...</option>`;
    try {
        const data = await fetchJson(`ajax/get_batches.php?product_id=${encodeURIComponent(productId)}`);
        stockBatchId.innerHTML = `<option value="">${stockAction.value === "IN" ? "New batch / enter below" : "Select batch"}</option>`;

        (data.batches || []).forEach(batch => {
            const label = `${batch.batch_number || "N/A"} — ${batch.quantity} qty — ${batch.expiration_date || "No expiry"} (${batch.batch_status})`;
            const option = document.createElement("option");
            option.value = batch.batch_id;
            option.textContent = label;
            option.dataset.quantity = batch.quantity;
            option.dataset.expiration = batch.expiration_date || "";
            option.dataset.unitCost = batch.unit_cost || "";
            option.dataset.batchNumber = batch.batch_number || "";
            stockBatchId.appendChild(option);
        });
    } catch (error) {
        stockBatchId.innerHTML = `<option value="">Unable to load batches</option>`;
        showAlert(error.message, "danger");
    }
}

function updateStockFields() {
    const isIn = stockAction.value === "IN";
    newBatchFields.hidden = !isIn || Number(stockBatchId.value) > 0;
    stockUnitCostWrap.hidden = !isIn;
    stockBatchId.required = !isIn;

    if (isIn && Number(stockBatchId.value) > 0) {
        const option = stockBatchId.selectedOptions[0];
        document.getElementById("stock_batch_number").value = option.dataset.batchNumber || "";
        document.getElementById("stock_expiration_date").value = option.dataset.expiration || "";
        document.getElementById("stock_unit_cost").value = option.dataset.unitCost || "";
    }
}

stockAction?.addEventListener("change", updateStockFields);
stockBatchId?.addEventListener("change", updateStockFields);

stockModal?.addEventListener("hidden.bs.modal", () => {
    document.getElementById("stock_quantity").value = "";
    document.getElementById("stock_batch_id").value = "";
    document.getElementById("stock_batch_number").value = "";
    document.getElementById("stock_expiration_date").value = "";
    document.getElementById("stock_unit_cost").value = "";
    document.getElementById("stock_remarks").value = "";
});

document.querySelectorAll(".stock-product").forEach(button => {
    button.addEventListener("click", async () => {
        stockProductId = button.dataset.product;
        document.getElementById("stock_product_id").value = stockProductId;
        document.getElementById("stock_product_name").value = button.dataset.name || "";
        stockAction.value = "IN";
        await loadStockBatches(stockProductId);
        updateStockFields();
        new bootstrap.Modal(stockModal).show();
    });
});

document.getElementById("save-stock")?.addEventListener("click", async function () {
    const button = this;
    const action = stockAction.value;
    const quantity = Number(document.getElementById("stock_quantity").value || 0);
    const batchId = Number(stockBatchId.value || 0);

    if (!stockProductId || quantity <= 0) {
        showAlert("Please enter a valid quantity.", "danger");
        return;
    }
    if (action === "OUT" && batchId <= 0) {
        showAlert("Please select the batch to deduct.", "danger");
        return;
    }
    if (action === "IN" && batchId <= 0 && !document.getElementById("stock_batch_number").value.trim()) {
        showAlert("Batch number is required for a new stock-in batch.", "danger");
        return;
    }

    button.disabled = true;
    button.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Saving...`;

    try {
        const formData = new FormData();
        formData.append("product_id", stockProductId);
        formData.append("action", action);
        formData.append("batch_id", batchId || "");
        formData.append("quantity", quantity);
        formData.append("batch_number", document.getElementById("stock_batch_number").value);
        formData.append("expiration_date", document.getElementById("stock_expiration_date").value);
        formData.append("received_date", document.getElementById("stock_received_date").value);
        formData.append("unit_cost", document.getElementById("stock_unit_cost").value);
        formData.append("remarks", document.getElementById("stock_remarks").value);

        const result = await fetchJson("ajax/save_stock.php", { method: "POST", body: formData });
        bootstrap.Modal.getInstance(stockModal)?.hide();
        NxToast.flash(`📦 ${result.message || "Stock adjustment saved successfully."}`, "success");
        setTimeout(() => window.location.reload(), 700);
    } catch (error) {
        showAlert(error.message, "danger");
    } finally {
        button.disabled = false;
        button.innerHTML = `<i class="bi bi-check-circle-fill me-2"></i>Save Adjustment`;
    }
});
