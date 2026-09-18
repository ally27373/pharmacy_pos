<div class="modal fade" id="stockModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i>Batch Stock Management</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="stock_product_id">

                <div class="mb-3">
                    <label class="form-label">Product</label>
                    <input type="text" id="stock_product_name" class="form-control" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label">Action</label>
                    <select id="stock_action" class="form-select">
                        <option value="IN">Stock In — Receive Delivery</option>
                        <option value="OUT">Stock Out — Adjust Existing Batch</option>
                    </select>
                </div>

                <div class="mb-3" id="stock_batch_select_wrap">
                    <label class="form-label">Batch</label>
                    <select id="stock_batch_id" class="form-select">
                        <option value="">Select batch</option>
                    </select>
                    <div class="form-text">For Stock In, leave this blank to enter a new batch. For Stock Out, select the batch to deduct.</div>
                </div>

                <div class="row" id="new-batch-fields">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Batch Number</label>
                        <input type="text" id="stock_batch_number" class="form-control" placeholder="e.g. BATCH-001">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Expiration Date <span class="text-muted">(Optional)</span></label>
                        <input type="date" id="stock_expiration_date" class="form-control">
                        <div class="form-text">Leave blank for products/batches with no expiration date.</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Date Received</label>
                        <input type="date" id="stock_received_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" id="stock_quantity" class="form-control" min="1" step="1">
                    </div>
                    <div class="col-md-6 mb-3" id="stock-unit-cost-wrap">
                        <label class="form-label">Unit Cost</label>
                        <input type="number" id="stock_unit_cost" class="form-control" min="0" step="0.01">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Remarks</label>
                    <textarea id="stock_remarks" class="form-control" rows="3" placeholder="Optional remarks"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="save-stock">
                    <i class="bi bi-check-circle-fill me-2"></i>Save Adjustment
                </button>
            </div>
        </div>
    </div>
</div>
