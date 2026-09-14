
console.log("POS JS Loaded");

const cart = [];

const cartBody = document.getElementById("cart-items");


function addToCart(product)
{
    const existing=cart.find(item=>item.id===product.id);

    if(existing){

        existing.qty++;

    }

    else{

        cart.push(product);

    }

    renderCart();
}

function renderCart()
{
    cartBody.innerHTML="";

    let total=0;

    cart.forEach(item=>{

        const subtotal=item.qty*item.price;

        total+=subtotal;

        cartBody.innerHTML += `

<tr>

    <td>

        <strong>${item.name}</strong><br>

        <small>${item.barcode}</small>

    </td>

    <td>

        ₱${item.price.toFixed(2)}

    </td>

<td>

    <div class="qty-controls">

        <button class="qty-btn decrease-qty"
            data-id="${item.id}">
            −
        </button>

        <span class="qty-value">
            ${item.qty}
        </span>

        <button class="qty-btn increase-qty"
            data-id="${item.id}">
            +
        </button>

    </div>

</td>

    <td>

        ₱${subtotal.toFixed(2)}

    </td>

<td>

    <button
        class="remove-btn remove-item"
        data-id="${item.id}">

        <i class="bi bi-trash"></i>

    </button>

</td>

</tr>

`;

    });

   document.getElementById("subtotal").innerText =
"₱"+total.toFixed(2);

document.getElementById("grand-total").innerText =
"₱"+total.toFixed(2);

if(cart.length===0){

    cartBody.innerHTML = `
    <tr class="cart-placeholder">
        <td colspan="5">
            🛒
            <p>No products added.</p>
            <small>Select medicines below.</small>
        </td>
    </tr>
    `;

}

calculatePayment();

attachCartEvents();

}

function attachCartEvents()
{
    document.querySelectorAll(".increase-qty").forEach(button=>{

        button.onclick=()=>{

            const item=cart.find(p=>p.id===button.dataset.id);

            item.qty++;

            renderCart();

        };

    });

    document.querySelectorAll(".decrease-qty").forEach(button=>{

        button.onclick=()=>{

            const item=cart.find(p=>p.id===button.dataset.id);

            item.qty--;

            if(item.qty<=0){

                removeItem(item.id);

            }

            renderCart();

        };

    });

    document.querySelectorAll(".remove-item").forEach(button=>{

        button.onclick=()=>{

            removeItem(button.dataset.id);

        };

    });

}

function removeItem(id)
{
    const index=cart.findIndex(item=>item.id===id);

    if(index>-1){

        cart.splice(index,1);

    }

    renderCart();

}


const cashInput = document.getElementById("cash-input");
const paymentMethod = document.getElementById("payment-method");
const referenceInput = document.getElementById("reference-number");
const discountType = document.getElementById("discount-type");
const discountInput = document.getElementById("discount-input");
const discountUnit = document.getElementById("discount-unit");
const discountMessage = document.getElementById("discount-message");

function getCartSubtotal()
{
    return cart.reduce((sum, item) => {
        return sum + (item.qty * item.price);
    }, 0);
}

function getDiscount()
{
    const subtotal = getCartSubtotal();
    const rawValue = parseFloat(discountInput?.value) || 0;
    const type = discountType?.value || "percent";

    if (rawValue <= 0 || subtotal <= 0) {
        return 0;
    }

    if (type === "percent") {
        const percent = Math.min(rawValue, 100);
        return Math.min(subtotal, subtotal * (percent / 100));
    }

    return Math.min(rawValue, subtotal);
}

function updateDiscountUI()
{
    if (!discountType || !discountUnit || !discountInput) return;

    const isPercent = discountType.value === "percent";

    discountUnit.textContent = isPercent ? "%" : "₱";
    discountInput.max = isPercent ? "100" : String(getCartSubtotal().toFixed(2));
    discountInput.placeholder = isPercent ? "0–100" : "0.00";

    const subtotal = getCartSubtotal();
    const entered = parseFloat(discountInput.value) || 0;

    if (isPercent && entered > 100) {
        discountMessage.textContent = "Percentage discount cannot exceed 100%.";
    }
    else if (!isPercent && entered > subtotal) {
        discountMessage.textContent = "Peso discount cannot exceed the subtotal.";
    }
    else {
        discountMessage.textContent = "";
    }
}

function calculatePayment()
{
    const subtotal = getCartSubtotal();
    const discount = getDiscount();
    const total = Math.max(subtotal - discount, 0);
    const cash = parseFloat(cashInput?.value) || 0;
    const change = cash - total;

    const subtotalElement = document.getElementById("subtotal");
    const discountElement = document.getElementById("discount");
    const totalElement = document.getElementById("grand-total");
    const cashDisplay = document.getElementById("cash-display");
    const changeDisplay = document.getElementById("change-display");

    if (subtotalElement) subtotalElement.innerText = "₱" + subtotal.toFixed(2);
    if (discountElement) discountElement.innerText = "₱" + discount.toFixed(2);
    if (totalElement) totalElement.innerText = "₱" + total.toFixed(2);
    if (cashDisplay) cashDisplay.innerText = "₱" + cash.toFixed(2);
    if (changeDisplay) changeDisplay.innerText = "₱" + Math.max(change, 0).toFixed(2);

    updateDiscountUI();
}

if (cashInput) {
    cashInput.addEventListener("input", calculatePayment);
}

if (discountInput) {
    discountInput.addEventListener("input", calculatePayment);
}

if (discountType) {
    discountType.addEventListener("change", () => {
        discountInput.value = "0";
        calculatePayment();
    });
}

function updatePaymentFields()
{
    if (!paymentMethod || !cashInput || !referenceInput) return;

    const method = paymentMethod.value;

    if (method === "Cash")
    {
        cashInput.disabled = false;
        referenceInput.disabled = true;
        referenceInput.value = "";
        cashInput.placeholder = "Enter Cash Amount";
        referenceInput.placeholder = "Not required";
    }
    else if (method === "GCash")
    {
        cashInput.disabled = true;
        cashInput.value = "";
        referenceInput.disabled = false;
        referenceInput.placeholder = "Enter GCash Reference Number";
    }
    else
    {
        cashInput.disabled = true;
        cashInput.value = "";
        referenceInput.disabled = false;
        referenceInput.placeholder = "Enter Maya Reference Number";
    }

    calculatePayment();
}

if (paymentMethod) {
    paymentMethod.addEventListener("change", updatePaymentFields);
    updatePaymentFields();
}

async function completeSale()
{
    if (cart.length === 0)
    {
        alert("Please add at least one product.");
        return;
    }

    const method = paymentMethod ? paymentMethod.value : "Cash";
    const subtotal = getCartSubtotal();
    const discount = getDiscount();
    const total = Math.max(subtotal - discount, 0);

    const enteredDiscount = parseFloat(discountInput?.value) || 0;

    if (discountType?.value === "percent" && enteredDiscount > 100)
    {
        alert("Percentage discount cannot exceed 100%.");
        return;
    }

    if (discountType?.value === "peso" && enteredDiscount > subtotal)
    {
        alert("Peso discount cannot exceed the subtotal.");
        return;
    }

    if (method === "Cash")
    {
        const cash = parseFloat(cashInput.value) || 0;

        if (cash < total)
        {
            alert("Insufficient cash.");
            return;
        }
    }

    if (method !== "Cash" && referenceInput.value.trim() === "")
    {
        alert("Please enter the Reference Number.");
        return;
    }

    /*
     * Open the receipt window while the cashier's click is still active.
     * This prevents browsers from treating the receipt as a blocked popup
     * after the asynchronous sale request completes.
     *
     * The receipt itself is responsible for triggering window.print().
     */
    let receiptWindow = null;

    try {
        receiptWindow = window.open(
            "about:blank",
            "posReceiptWindow",
            "width=420,height=720,scrollbars=yes,resizable=yes"
        );
    }
    catch (error) {
        console.warn("Receipt window could not be opened:", error);
    }

    const payload = {
        cart,
        paymentMethod: method,
        cash: parseFloat(cashInput.value) || 0,
        reference: referenceInput.value.trim(),
        discountType: discountType?.value || "percent",
        discountValue: enteredDiscount,
        discountAmount: discount
    };

    try {
        const response = await fetch("/pharmacy_pos/app/controllers/process_sale.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
        console.log(result);

        if (!result.success)
        {
            if (receiptWindow && !receiptWindow.closed) {
                receiptWindow.close();
            }

            alert(result.message);
            return;
        }

        /*
         * The backend commits the sale before returning success and provides
         * the new sale_id. Only then do we navigate the receipt window.
         */
        const saleId = Number(result.sale_id || 0);

        if (!saleId)
        {
            if (receiptWindow && !receiptWindow.closed) {
                receiptWindow.close();
            }

            alert("Sale completed, but the receipt could not be opened because the sale ID was not returned.");
            window.location.reload();
            return;
        }

        const receiptUrl =
            "receipt.php?sale_id=" + encodeURIComponent(String(saleId));

        if (receiptWindow && !receiptWindow.closed)
        {
            receiptWindow.location = receiptUrl;
            receiptWindow.focus();
        }
        else
        {
            /*
             * Popup was blocked. Fall back to the same tab so the cashier
             * can still print the receipt instead of losing it.
             */
            window.location.href = receiptUrl;
            return;
        }

        /*
         * Reset the POS for the next customer. The receipt is already open
         * in its own window, so the cashier can print it while this POS
         * screen becomes ready for the next transaction.
         */
        cart.length = 0;

        if (cashInput) cashInput.value = "";
        if (referenceInput) referenceInput.value = "";
        if (discountInput) discountInput.value = "0";

        renderCart();
        updatePaymentFields();

        alert(result.message);

    }
    catch(error)
    {
        console.error(error);

        if (receiptWindow && !receiptWindow.closed) {
            receiptWindow.close();
        }

        alert("Unable to connect to the server.");
    }
}

const completeSaleBtn = document.getElementById("complete-sale");

if (completeSaleBtn) {
    completeSaleBtn.addEventListener("click", completeSale);
}

// =====================================
// PRODUCT SEARCH, FILTER & PAGINATION
// =====================================

const productsTableBody =
    document.getElementById("products-table-body");

const productSearch =
    document.getElementById("product-search");

const categoryFilter =
    document.getElementById("category-filter");

const typeFilter =
    document.getElementById("type-filter");

const productsPagination =
    document.getElementById("products-pagination");

let productPage = productsPagination
    ? Number(productsPagination.dataset.page || 1)
    : 1;

let productRequest = 0;
let productSearchTimer = null;


function escapeHtml(value)
{
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}


function renderProductRows(products)
{
    if (!productsTableBody) return;

    if (!products.length)
    {
        productsTableBody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4">
                    No products found.
                </td>
            </tr>
        `;
        return;
    }

    productsTableBody.innerHTML = products.map(product => `
        <tr class="product-row">
            <td>${escapeHtml(product.barcode)}</td>
            <td>${escapeHtml(product.product_name)}</td>
            <td>${escapeHtml(product.type_name)}</td>
            <td>${escapeHtml(product.category_name)}</td>
            <td>${escapeHtml(product.quantity)}</td>
            <td>₱${Number(product.selling_price || 0).toFixed(2)}</td>
            <td>${escapeHtml(product.product_status)}</td>
            <td>
                <button
                    type="button"
                    class="btn btn-success btn-sm add-product"
                    data-id="${escapeHtml(product.product_id)}"
                    data-name="${escapeHtml(product.product_name)}"
                    data-price="${escapeHtml(product.selling_price)}"
                    data-barcode="${escapeHtml(product.barcode)}">
                    <i class="bi bi-cart-plus"></i>
                    Add
                </button>
            </td>
        </tr>
    `).join("");
}


function renderProductPagination(pagination)
{
    if (!productsPagination) return;

    const page = Number(pagination.page || 1);
    const limit = Number(pagination.limit || 20);
    const total = Number(pagination.total || 0);
    const totalPages = Number(pagination.total_pages || 0);

    productPage = page;

    productsPagination.dataset.page = page;
    productsPagination.dataset.limit = limit;

    const start = total > 0
        ? ((page - 1) * limit) + 1
        : 0;

    const end = total > 0
        ? Math.min(page * limit, total)
        : 0;

    const info = productsPagination.querySelector(".pagination-info");
    const controls = productsPagination.querySelector(".pagination-controls");

    if (info)
    {
        info.innerHTML = total > 0
            ? `Showing <strong>${start}</strong>–<strong>${end}</strong> of <strong>${total}</strong> products`
            : "No products found.";
    }

    if (controls)
    {
        controls.innerHTML = `
            <button
                type="button"
                id="products-prev"
                class="btn btn-outline-secondary btn-sm"
                ${pagination.has_previous ? "" : "disabled"}>
                Previous
            </button>

            <span class="pagination-page">
                Page <strong>${page}</strong> of <strong>${Math.max(1, totalPages)}</strong>
            </span>

            <button
                type="button"
                id="products-next"
                class="btn btn-outline-secondary btn-sm"
                ${pagination.has_next ? "" : "disabled"}>
                Next
            </button>
        `;
    }
}


async function loadProducts(page = 1)
{
    if (!productsTableBody) return;

    const requestId = ++productRequest;

    const search = productSearch
        ? productSearch.value.trim()
        : "";

    const category = categoryFilter
        ? categoryFilter.value
        : "";

    const type = typeFilter
        ? typeFilter.value
        : "";

    const params = new URLSearchParams({
        page: String(Math.max(1, page)),
        limit: "20",
        search,
        category,
        type
    });

    productsTableBody.innerHTML = `
        <tr>
            <td colspan="8" class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-success" role="status"></div>
                <span class="ms-2">Loading products...</span>
            </td>
        </tr>
    `;

    try
    {
        const response = await fetch(
            "ajax/get_products.php?" + params.toString(),
            {
                headers: {
                    "Accept": "application/json"
                },
                cache: "no-store"
            }
        );

        if (!response.ok)
        {
            throw new Error("Product request failed.");
        }

        const result = await response.json();

        if (requestId !== productRequest) return;

        if (!result.success)
        {
            throw new Error(result.message || "Unable to load products.");
        }

        renderProductRows(result.products || []);
        renderProductPagination(result.pagination || {});
    }
    catch (error)
    {
        if (requestId !== productRequest) return;

        console.error(error);

        productsTableBody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-danger py-4">
                    Unable to load products. Please try again.
                </td>
            </tr>
        `;
    }
}


// Event delegation: Add buttons continue working after AJAX pagination.
if (productsTableBody)
{
    productsTableBody.addEventListener("click", event =>
    {
        const button = event.target.closest(".add-product");

        if (!button) return;

        const product = {
            id: button.dataset.id,
            name: button.dataset.name,
            price: parseFloat(button.dataset.price) || 0,
            barcode: button.dataset.barcode,
            qty: 1
        };

        addToCart(product);
    });
}


function queueProductSearch()
{
    clearTimeout(productSearchTimer);

    productSearchTimer = setTimeout(() => {
        loadProducts(1);
    }, 300);
}


if (productSearch)
{
    productSearch.addEventListener("input", queueProductSearch);
}


if (categoryFilter)
{
    categoryFilter.addEventListener("change", () => loadProducts(1));
}


if (typeFilter)
{
    typeFilter.addEventListener("change", () => loadProducts(1));
}


if (productsPagination)
{
    productsPagination.addEventListener("click", event =>
    {
        const button = event.target.closest("button");

        if (!button || button.disabled) return;

        if (button.id === "products-prev")
        {
            loadProducts(productPage - 1);
        }
        else if (button.id === "products-next")
        {
            loadProducts(productPage + 1);
        }
    });
}
