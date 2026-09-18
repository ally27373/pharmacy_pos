<h2>Payment Details</h2>

<div class="payment-form">

    <label for="payment-method">Mode of Payment</label>

    <select id="payment-method" class="form-select">
        <option value="Cash">Cash</option>
        <option value="GCash">GCash</option>
        <option value="Maya">Maya</option>
    </select>

    <label for="cash-input">Cash Tendered</label>

    <input
        id="cash-input"
        type="number"
        inputmode="decimal"
        min="0"
        step="0.01"
        class="form-control"
        placeholder="Enter Cash Amount">

    <label for="reference-number">Reference Number</label>

    <input
        id="reference-number"
        type="text"
        class="form-control"
        placeholder="Not Required">

    <div class="discount-section">
        <div class="discount-label-row">
            <label for="discount-input">Discount <span>(optional)</span></label>
            <span class="discount-hint">PWD / Senior / other approved discount</span>
        </div>

        <div class="discount-controls">
            <select id="discount-type" class="form-select" aria-label="Discount type">
                <option value="percent">Percentage (%)</option>
                <option value="peso">Peso (₱)</option>
            </select>

            <div class="discount-input-wrap">
                <input
                    id="discount-input"
                    type="number"
                    inputmode="decimal"
                    min="0"
                    step="0.01"
                    class="form-control"
                    value="0"
                    placeholder="0">
                <span id="discount-unit" class="discount-unit">%</span>
            </div>
        </div>

        <small id="discount-message" class="discount-message"></small>
    </div>

    <hr>

    <div class="summary">

        <div>
            <span>Subtotal</span>
            <strong id="subtotal">₱0.00</strong>
        </div>

        <div>
            <span>Discount</span>
            <strong id="discount">₱0.00</strong>
        </div>

        <div class="total-row">
            <span>Total</span>
            <strong id="grand-total">₱0.00</strong>
        </div>

        <div>
            <span>Cash</span>
            <strong id="cash-display">₱0.00</strong>
        </div>

        <div>
            <span>Change</span>
            <strong id="change-display">₱0.00</strong>
        </div>

    </div>

    <button
        id="complete-sale"
        class="btn btn-success complete-sale">
        Complete Sale
    </button>

</div>
