{{-- "Recurring for all staff": the items the payroll run applies to everyone, and the choice to leave one out for this person.
     $exclusions: the items already left out on the contract being edited, element id => reason (none when adding).
     The table is drawn by the element scripts (partials/element-scripts.blade.php). What is left out is kept in the hidden inputs
     below, which are there from the start so the saved choices survive a form sent before the table has loaded. --}}
@php
    $leftOut = [];
    if (is_array(old('exclusions'))) {
        foreach (old('exclusions') as $elementId => $entry) {
            if (!empty($entry['excluded'])) {
                $leftOut[(int) $elementId] = (string) ($entry['reason'] ?? '');
            }
        }
    } else {
        foreach (($exclusions ?? []) as $elementId => $reason) {
            $leftOut[(int) $elementId] = (string) $reason;
        }
    }
@endphp
<div class="mt-4">
    <h3>Recurring for all staff</h3>
    <hr class="mb-3 mt-3">
    <p class="text-muted small mb-2">These apply to every staff member automatically when payroll runs, worked out from the pay on this contract. The amounts can't be edited here. To exclude an item for this person only, tick <strong>Leave out</strong> and give the reason; nobody else is affected. NSSF, SHA, Housing Levy and PAYE always apply and cannot be left out.</p>
    <div class="table-responsive">
        <table class="table table-sm mb-0" id="allStaffItems">
            <thead>
                <tr><th>Item</th><th>Type</th><th>How it is worked out</th><th class="text-end">Amount</th><th>For this person</th></tr>
            </thead>
            <tbody>
                <tr><td colspan="5" class="text-muted">Enter a basic salary to see the amounts.</td></tr>
            </tbody>
        </table>
    </div>
    <div id="exclusionInputs" data-left-out="{{ json_encode((object) $leftOut) }}">
        @foreach($leftOut as $elementId => $reason)
            <input type="hidden" name="exclusions[{{ $elementId }}][excluded]" value="1">
            <input type="hidden" name="exclusions[{{ $elementId }}][reason]" value="{{ $reason }}">
        @endforeach
    </div>
</div>
