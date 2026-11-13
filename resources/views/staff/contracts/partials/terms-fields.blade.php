{{-- The two questions asked about each allowance or deduction on a contract: how often, and what a part month does to it.
     $index: the row's position in the form. $row: the saved contract row, or null for a new one.
     public/assets/js/contract-element-terms.js draws the same fields for a row added with the Add Element button, so keep them alike. --}}
@php
    $row = $row ?? null;
    $frequency = old("elements.$index.frequency", $row?->frequency ?: 'monthly');
    $onceMonth = old("elements.$index.once_month", $row?->once_month?->format('Y-m'));
    $prorate = old("elements.$index.prorate", $row ? ($row->prorates() ? '1' : '0') : '1');
    $once = $frequency === 'once';
@endphp
<div class="mb-3 col-md-4">
    <label class="form-label">How often?</label>
    <select class="form-select frequency-select" name="elements[{{ $index }}][frequency]">
        <option value="monthly" {{ !$once ? 'selected' : '' }}>Every month</option>
        <option value="once" {{ $once ? 'selected' : '' }}>Once only</option>
    </select>
</div>
<div class="mb-3 col-md-4 once-month-field {{ $once ? '' : 'd-none' }}">
    <label class="form-label">Which month?</label>
    <input type="month" class="form-control once-month-input" name="elements[{{ $index }}][once_month]" value="{{ $onceMonth }}" {{ $once ? 'required' : 'disabled' }}>
</div>
<div class="mb-3 col-md-4 part-month-field">
    <label class="form-label">Part month?</label>
    <select class="form-select prorate-select" name="elements[{{ $index }}][prorate]" {{ $row ? 'data-touched=1' : '' }}>
        <option value="1" {{ (string) $prorate === '1' ? 'selected' : '' }}>Reduced by the days worked (prorated)</option>
        <option value="0" {{ (string) $prorate === '0' ? 'selected' : '' }}>Full amount</option>
    </select>
    <small class="text-muted">For the month the contract starts or ends.</small>
</div>
