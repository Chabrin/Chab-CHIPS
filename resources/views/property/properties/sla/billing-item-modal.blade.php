<div class="modal fade sla-billing-modal" id="addInvoiceableItemModal" tabindex="-1" aria-labelledby="addInvoiceableItemModalLabel" aria-describedby="slaItemIntro" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <div class="modal-header"><div><span class="sla-eyebrow">TENANT CHARGES</span><h2 class="modal-title" id="addInvoiceableItemModalLabel">Add billing item</h2><p id="slaItemIntro">Choose a charge and how its value should be set.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <label for="modal_item_select" class="form-label">Billing item <span class="text-danger">*</span></label>
            <select class="form-select" id="modal_item_select" aria-describedby="modal_item_select_error" disabled><option value="">Loading billing items...</option></select>
            <div class="invalid-feedback" id="modal_item_select_error" role="alert"></div>
            <button type="button" class="btn btn-link p-0 mt-2 d-none" id="slaRetryItems">Try loading items again</button>
            <dl class="sla-item-metadata"><div><dt>Billing code</dt><dd id="modal_item_billing_code">Select an item</dd></div><div><dt>Billing cycle</dt><dd id="modal_item_cycle">Select an item</dd></div><div><dt>Advance payable</dt><dd id="modal_item_advance">Select an item</dd></div></dl>
            <fieldset><legend class="sla-field-legend">How is this charge calculated?</legend><p class="sla-muted">Choose one method. You can also leave the value unset.</p>
                <div class="sla-methods">
                    <label class="sla-method-card" for="modal_choice_amount"><input type="radio" name="modal_item_choice" id="modal_choice_amount" value="amount"><span><strong>Fixed amount</strong><small>The same amount each billing cycle.</small></span></label>
                    <label class="sla-method-card" for="modal_choice_pct"><input type="radio" name="modal_item_choice" id="modal_choice_pct" value="percentage"><span><strong>Percentage of rent</strong><small>Calculated from the tenant's rent.</small></span></label>
                    <label class="sla-method-card" for="modal_choice_unset"><input type="radio" name="modal_item_choice" id="modal_choice_unset" value="unset" checked><span><strong>Leave value unset</strong><small>Add this item without a preset value.</small></span></label>
                </div>
            </fieldset>
            <div id="slaAmountField" class="sla-value-field" hidden><label for="modal_item_amount" class="form-label">Amount per billing cycle</label><input type="number" step="0.01" min="0" class="form-control" id="modal_item_amount" placeholder="e.g. 1,500" inputmode="decimal" aria-describedby="modal_item_value_error" disabled></div>
            <div id="slaPercentageField" class="sla-value-field" hidden><label for="modal_item_pct" class="form-label">Percentage of monthly rent</label><div class="input-group"><input type="number" step="0.01" min="0" class="form-control" id="modal_item_pct" placeholder="e.g. 5" inputmode="decimal" aria-describedby="slaPercentageExample modal_item_value_error" disabled><span class="input-group-text">%</span></div><p class="form-text" id="slaPercentageExample">Example: 5% of a rent of 20,000 is 1,000 per billing cycle.</p></div>
            <div class="invalid-feedback" id="modal_item_value_error" role="alert"></div>
            <p class="sla-item-preview" id="slaItemPreview" aria-live="polite">Select a billing item to see a summary.</p>
        </div>
        <div class="modal-footer"><span class="sla-muted me-auto">Added items are saved when you save the SLA.</span><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="addInvoiceableItemBtn" disabled>Add billing item</button></div>
    </div></div>
</div>
