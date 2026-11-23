@php($returnsCombination = old('returns_combination', isset($sla) ? ($sla->returns_combination ?: 'each_separate') : 'each_separate'))
<section class="sla-section" aria-labelledby="sla-billing-title" id="sla-billing">
    <div class="sla-section-heading">
        <div><span class="sla-eyebrow">TENANT CHARGES</span><h2 id="sla-billing-title">Tenant billing items <span class="sla-count" id="slaItemCount">0</span></h2><p>Add the extra charges that should be available when setting up a tenant's lease.</p></div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addInvoiceableItemModal"><span class="material-symbols-outlined" aria-hidden="true">add</span> Add billing item</button>
    </div>
    <div id="invoiceableItemsHiddenInputs"></div>
    <div class="table-responsive sla-billing-table">
        <table class="table mb-0">
            <thead><tr><th scope="col">Charge</th><th scope="col">Billing code</th><th scope="col">Billing cycle</th><th scope="col">Advance payable</th><th scope="col">How it is charged</th><th scope="col" class="text-end">Value</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody id="selectedInvoiceableItemsBody"></tbody>
        </table>
    </div>
    <p class="sla-section-footnote">Rent comes from the unit. Add only the additional billing items you need.</p>
</section>

<section class="sla-section" aria-labelledby="sla-returns-title" id="sla-returns">
    <div class="sla-section-heading"><div><span class="sla-eyebrow">LANDLORD RETURNS</span><h2 id="sla-returns-title">Group charges on landlord returns</h2><p>Record how rent, electricity, water and service charges should be grouped.</p></div></div>
    <fieldset class="sla-return-options">
        <legend class="visually-hidden">Returns combination</legend>
        <label class="sla-choice-card" for="rc_rent_separate">
            <input type="radio" name="returns_combination" id="rc_rent_separate" value="rent_separate_rest_combined" {{ $returnsCombination === 'rent_separate_rest_combined' ? 'checked' : '' }}>
            <span><strong>Rent separate, other charges together</strong><span>Show rent on its own and group electricity, water and service charges.</span><small>Rent <b>+</b> Electricity, water &amp; service charges</small></span>
        </label>
        <label class="sla-choice-card" for="rc_each_separate">
            <input type="radio" name="returns_combination" id="rc_each_separate" value="each_separate" {{ $returnsCombination === 'each_separate' ? 'checked' : '' }}>
            <span><strong>Each charge separate</strong><span>Keep rent, electricity, water and service charges individually listed.</span><small>Rent <b>+</b> Electricity <b>+</b> Water <b>+</b> Service charges</small></span>
        </label>
        {{-- All combined is temporarily hidden; keep this option for future use.
        <label class="sla-choice-card" for="rc_all_combined">
            <input type="radio" name="returns_combination" id="rc_all_combined" value="all_combined" {{ $returnsCombination === 'all_combined' ? 'checked' : '' }}>
            <span><strong>All charges combined</strong><span>Rent, Electricity Bill, Water Bill, Service Charges (all combined)</span></span>
        </label>
        --}}
    </fieldset>
    @if(isset($sla) && $returnsCombination === 'all_combined')
    <p class="sla-legacy-note">This agreement currently uses All combined. The saved setting is kept unless you select another option above.</p>
    @endif
</section>
