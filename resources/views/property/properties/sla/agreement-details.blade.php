@php
    $isEditing = isset($sla);
    $vatChoice = old('vat_able', $isEditing ? $sla->vat : 'Yes');
    $renewalChoice = old('auto_renewal', $isEditing ? $sla->auto_renewal : '1');
    $renewalEnabled = in_array((string) $renewalChoice, ['1', 'Yes'], true);
    $startDate = $isEditing && $sla->date_start ? \Illuminate\Support\Carbon::parse($sla->date_start)->format('Y-m-d') : ($isEditing ? '' : now()->format('Y-m-d'));
    $registrationFee = session()->hasOldInput('client_id') ? (bool) old('charge_registration_fee', false) : ($isEditing ? $sla->charge_registration_fee : true);
@endphp
<section class="sla-section sla-details-section" id="sla-property" aria-labelledby="sla-property-title">
    <div class="sla-section-heading"><div class="sla-heading-with-icon"><span class="sla-section-icon material-symbols-outlined" aria-hidden="true">apartment</span><div><span class="sla-eyebrow">AGREEMENT DETAILS</span><h2 id="sla-property-title">Client &amp; property</h2><p>Choose who the agreement is for and the property it covers.</p></div></div><span class="sla-required-note"><span aria-hidden="true">*</span> Required fields</span></div>
    <div class="sla-section-content">
        <div class="sla-form-grid">
            <div class="sla-field">
                @if($client)
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <span class="form-label" id="sla-client-label">Landlord / client</span>
                <div class="sla-client-summary" aria-labelledby="sla-client-label"><span class="sla-client-avatar material-symbols-outlined" aria-hidden="true">person</span><div><strong>{{ $client->names }}</strong><small>{{ $client->reference_number ?: 'Client on this agreement' }}</small></div><span class="material-symbols-outlined sla-client-lock" aria-label="Client is fixed for this agreement">lock</span></div>
                @else
                <label for="client_id" class="form-label">Landlord / client <span class="text-danger">*</span></label>
                <select name="client_id" id="client_id" class="form-select @error('client_id') is-invalid @enderror" required aria-describedby="sla-client-help"><option value="">Search by client name...</option></select>
                <p class="form-text" id="sla-client-help">Type at least two characters to find a client.</p>
                @error('client_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                @endif
            </div>
            <div class="sla-field">
                <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                <select class="form-select @error('property_id') is-invalid @enderror" id="property_id" name="property_id" required aria-describedby="sla-property-help currentSlaWarning" data-selected-property="{{ old('property_id', $isEditing ? $sla->property_id : '') }}" @disabled(!$isEditing && !$client)>
                    <option value="">{{ $isEditing ? 'Select a property' : ($client ? 'Loading properties...' : 'Select a client first') }}</option>
                    @if($isEditing)
                    @foreach($properties as $property)
                    <option value="{{ $property->id }}" @selected((string) old('property_id', $sla->property_id) === (string) $property->id)>{{ $property->property_name }}{{ $property->reference_number ? ' (' . $property->reference_number . ')' : '' }}</option>
                    @endforeach
                    @endif
                </select>
                <p class="form-text" id="sla-property-help">Only properties belonging to the selected client are listed.</p>
                @error('property_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <p class="sla-inline-warning d-none" id="currentSlaWarning" role="alert"></p>
            </div>
        </div>
        @unless($isEditing)
        <div class="sla-copy-terms d-none" id="cloneSlaBlock">
            <div><span class="material-symbols-outlined" aria-hidden="true">content_copy</span><strong>Start with an existing agreement</strong><span class="sla-optional">Optional</span></div>
            <label for="clone_sla_id" class="form-label">Copy terms from</label>
            <select class="form-select" id="clone_sla_id" aria-describedby="sla-clone-help cloneSlaNote cloneSlaError"><option value="">Start from a blank form</option></select>
            <p class="form-text" id="sla-clone-help">Reuse fees, tax settings, payment day, duration and billing items. Set the property, dates and documents for this agreement.</p>
            <p class="text-success small mb-0" id="cloneSlaNote" role="status"></p><p class="text-danger small mb-0" id="cloneSlaError" role="alert"></p>
        </div>
        @endunless
    </div>
</section>

<section class="sla-section sla-details-section" id="sla-fees" aria-labelledby="sla-fees-title">
    <div class="sla-section-heading"><div class="sla-heading-with-icon"><span class="sla-section-icon material-symbols-outlined" aria-hidden="true">percent</span><div><span class="sla-eyebrow">COMMERCIAL TERMS</span><h2 id="sla-fees-title">Commission &amp; VAT</h2><p>Set the management commission and the agreement's VAT treatment.</p></div></div></div>
    <div class="sla-section-content">
        <div class="sla-form-grid">
            <div class="sla-field">
                <label for="commission_percentage" class="form-label">Commission rate <span class="text-danger">*</span></label>
                <div class="input-group"><input type="number" min="0" max="100" step="0.01" class="form-control @error('commission_percentage') is-invalid @enderror" id="commission_percentage" name="commission_percentage" value="{{ old('commission_percentage', $isEditing ? $sla->commission_percentage : '') }}" placeholder="e.g. 6" required aria-describedby="sla-commission-help"><span class="input-group-text">%</span></div>
                <p class="form-text" id="sla-commission-help">The management commission percentage for this agreement.</p>
                @error('commission_percentage')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="sla-field">
                <label for="vat_able" class="form-label">VAT treatment <span class="text-danger">*</span></label>
                <select class="form-select @error('vat_able') is-invalid @enderror" id="vat_able" name="vat_able" required aria-describedby="sla-vat-help"><option value="Yes" @selected($vatChoice === 'Yes')>Percentage VAT</option><option value="No" @selected($vatChoice === 'No')>No VAT</option><option value="Flat Rate" @selected($vatChoice === 'Flat Rate')>Flat rate VAT</option></select>
                <p class="form-text" id="sla-vat-help">Choose whether VAT applies as a percentage or a fixed value.</p>
                @error('vat_able')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="sla-field" id="slaVatPercentageField" @if($vatChoice !== 'Yes') hidden @endif>
                <label for="vat_percentage" class="form-label">VAT rate</label>
                <div class="input-group"><input type="number" min="0" max="100" step="0.01" class="form-control @error('vat_percentage') is-invalid @enderror" id="vat_percentage" name="vat_percentage" value="{{ old('vat_percentage', $isEditing ? $sla->vat_per : 16) }}" aria-describedby="sla-vat-rate-help"><span class="input-group-text">%</span></div>
                <p class="form-text" id="sla-vat-rate-help">Confirm the percentage that applies to this agreement.</p>
                @error('vat_percentage')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="sla-field" id="slaFlatVatField" @if($vatChoice !== 'Flat Rate') hidden @endif>
                <label for="flat_rate_vat" class="form-label">Flat rate VAT amount</label>
                <input type="number" min="0" step="0.01" class="form-control @error('flat_rate_vat') is-invalid @enderror" id="flat_rate_vat" name="flat_rate_vat" value="{{ old('flat_rate_vat', $isEditing ? $sla->flat_rate_vat : '') }}" placeholder="Enter the fixed VAT amount">
                @error('flat_rate_vat')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</section>

<section class="sla-section sla-details-section" id="sla-dates" aria-labelledby="sla-dates-title">
    <div class="sla-section-heading"><div class="sla-heading-with-icon"><span class="sla-section-icon material-symbols-outlined" aria-hidden="true">event</span><div><span class="sla-eyebrow">SCHEDULE &amp; REQUIREMENTS</span><h2 id="sla-dates-title">Dates &amp; onboarding</h2><p>Set the agreement period, payment day and onboarding requirements.</p></div></div></div>
    <div class="sla-section-content">
        <div class="sla-form-grid sla-form-grid-three">
            <div class="sla-field">
                <label for="date_start" class="form-label">Agreement start date</label>
                <input type="date" class="form-control @error('date_start') is-invalid @enderror" id="date_start" name="date_start" value="{{ old('date_start', $startDate) }}">
                @error('date_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="sla-field">
                <label for="duration_years" class="form-label">Duration</label>
                <div class="input-group"><input type="number" min="1" step="1" class="form-control @error('duration_years') is-invalid @enderror" id="duration_years" name="duration_years" value="{{ old('duration_years', $isEditing ? $sla->years : '') }}" placeholder="e.g. 1"><span class="input-group-text">years</span></div>
                @error('duration_years')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="sla-field">
                <label for="payment_date" class="form-label">Landlord payment day <span class="text-danger">*</span></label>
                <select class="form-select @error('payment_date') is-invalid @enderror" id="payment_date" name="payment_date" required aria-describedby="sla-payment-help"><option value="">Select a day</option>@for($day = 1; $day <= 31; $day++)<option value="{{ $day }}" @selected((string) old('payment_date', $isEditing ? $sla->landlord_payment_date : '') === (string) $day)>Day {{ $day }} of each month</option>@endfor</select>
                <p class="form-text" id="sla-payment-help">The agreed monthly payment day.</p>
                @error('payment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <p class="sla-term-preview" id="slaTermPreview" aria-live="polite"><span class="material-symbols-outlined" aria-hidden="true">date_range</span><span>Select a start date and duration to preview the end date.</span></p>
        <div class="sla-form-grid">
            <div class="sla-field">
                <label for="auto_renewal" class="form-label">Renew automatically <span class="text-danger">*</span></label>
                <select class="form-select @error('auto_renewal') is-invalid @enderror" id="auto_renewal" name="auto_renewal" required><option value="{{ $isEditing ? 'Yes' : '1' }}" @selected($renewalEnabled)>Yes, renew when the term ends</option><option value="{{ $isEditing ? 'No' : '2' }}" @selected(!$renewalEnabled)>No, review before renewing</option></select>
                @error('auto_renewal')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="sla-field">
                <label for="move_in_inspection_required" class="form-label">Move-in inspection</label>
                <select class="form-select" id="move_in_inspection_required" name="move_in_inspection_required"><option value="0" @selected(!old('move_in_inspection_required', $isEditing ? $sla->move_in_inspection_required : false))>Optional</option><option value="1" @selected(old('move_in_inspection_required', $isEditing ? $sla->move_in_inspection_required : false))>Required before tenant onboarding is completed</option></select>
                <p class="form-text">Require an inspection when a new tenant moves in.</p>
            </div>
        </div>
        <div class="sla-registration-setting"><div><label for="charge_registration_fee">Charge a registration fee</label><p>Include the registration fee for this agreement.</p><small id="registrationFeeHint" class="text-muted" role="status"></small></div><input class="sla-setting-checkbox" type="checkbox" role="switch" id="charge_registration_fee" name="charge_registration_fee" value="1" @checked($registrationFee)></div>
    </div>
</section>
