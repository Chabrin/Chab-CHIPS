{{-- The contract's period, end date and probation, as columns of the contract form's row.
     $contract: the contract being edited (null when adding). $openEnded: the staff member's category has no contract end date
     (permanent staff), so only probation is asked. public/assets/js/contract-period.js fills the end dates in. --}}
@php
    $contract = $contract ?? null;
    $openEnded = $openEnded ?? false;
    $periodValue = old('period_value', $contract?->period_value);
    $periodUnit = old('period_unit', $contract?->period_unit ?: 'months');
    $dateEnd = old('date_end', $contract?->date_end?->format('Y-m-d'));
    $probationMonths = old('probation_months', $contract?->probation_months);
    $probationEnd = $contract?->probation_end?->format('Y-m-d');
@endphp
<div class="mb-3 col-6 {{ $openEnded ? 'd-none' : '' }}" data-end-date-field>
    <label for="period_value" class="form-label">Contract Period <span class="text-muted small">- optional</span></label>
    <div class="input-group">
        <input type="number" class="form-control @error('period_value') is-invalid @enderror" name="period_value" id="period_value" min="1" max="3650" step="1" value="{{ $periodValue }}" placeholder="e.g. 12" {{ $openEnded ? 'disabled' : '' }}>
        <select class="form-select @error('period_unit') is-invalid @enderror" name="period_unit" id="period_unit" style="max-width:8rem;" aria-label="Contract period unit" {{ $openEnded ? 'disabled' : '' }}>
            @foreach (['days' => 'Days', 'weeks' => 'Weeks', 'months' => 'Months', 'years' => 'Years'] as $unit => $label)
                <option value="{{ $unit }}" {{ $periodUnit === $unit ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('period_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @error('period_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <small class="text-muted">Enter the period and the end date fills in.</small>
</div>
<div class="mb-3 col-6 {{ $openEnded ? 'd-none' : '' }}" data-end-date-field>
    <label for="date_end" class="form-label"> Date End<span style="color:red">*</span></label>
    <input type="date" class="form-control @error('date_end') is-invalid @enderror" name="date_end" id="date_end" {{ $openEnded ? 'disabled' : 'required' }} value="{{ $dateEnd }}">
    @error('date_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <small class="text-muted" id="dateEndNote">The last day of the contract.</small>
</div>
<div class="mb-3 col-6">
    <label for="probation_months" class="form-label">Probation Period <span class="text-muted small">- optional</span></label>
    <div class="input-group">
        <input type="number" class="form-control @error('probation_months') is-invalid @enderror" name="probation_months" id="probation_months" min="1" max="60" step="1" value="{{ $probationMonths }}" placeholder="e.g. 3">
        <span class="input-group-text">months</span>
        @error('probation_months')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    @if ($openEnded)
        <small class="text-muted">This contract has no end date. Only the probation period is recorded.</small>
    @endif
</div>
<div class="mb-3 col-6">
    <label for="probation_end" class="form-label">Probation Ends</label>
    <input type="date" class="form-control" id="probation_end" value="{{ $probationEnd }}" readonly tabindex="-1" aria-describedby="probationEndNote">
    <small class="text-muted" id="probationEndNote">Worked out from the start date and the probation period.</small>
</div>
