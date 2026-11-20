{{--
    Country of citizenship first, then Resident or Non-resident, then (for anyone who is not Kenyan) the work permit or Alien ID and
    its expiry date. public/assets/js/staff-citizenship.js sets Resident for Kenya, and makes the National ID or the passport and
    permit fields required for the country chosen. The server makes the same rules (App\Services\StaffResidency).

    $nationality, $residency, $permitNumber, $permitExpiry   the values to show
    $requiredAttribute   'required' on Add Staff, 'data-required' on Edit Staff (the way each form marks a required field)
--}}
@php($requiredAttribute = $requiredAttribute ?? 'required')
<div class="col-md-4 mb-3">
    <label for="nationality" class="form-label">Country of citizenship <span class="text-danger">*</span></label>
    <input type="text" list="countryList" class="form-control @error('nationality') is-invalid @enderror" id="nationality" name="nationality"
           value="{{ $nationality }}" placeholder="Type to search" autocomplete="off" data-required-attribute="{{ $requiredAttribute }}" {{ $requiredAttribute === 'required' ? 'required' : 'data-required=true' }}>
    <datalist id="countryList">
        @foreach(\App\Services\StaffResidency::countries() as $country)
        <option value="{{ $country }}"></option>
        @endforeach
    </datalist>
    @error('nationality')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
<div class="col-md-4 mb-3">
    <label for="residency_status" class="form-label">Residency <span class="text-danger">*</span></label>
    <select class="form-select @error('residency_status') is-invalid @enderror" id="residency_status" name="residency_status" {{ $requiredAttribute === 'required' ? 'required' : 'data-required=true' }}>
        <option value="">Select</option>
        @foreach(\App\Services\StaffResidency::LABELS as $value => $label)
        <option value="{{ $value }}" {{ $residency === $value ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    <small class="text-muted d-block" id="residencyNote">Kenya is set to Resident. You can change it.</small>
    @error('residency_status')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
<div class="col-12" id="permitFields">
    <div class="row">
        <div class="col-md-4 mb-3">
            <label for="work_permit_number" class="form-label">Work permit or Alien ID number</label>
            <input type="text" class="form-control @error('work_permit_number') is-invalid @enderror" id="work_permit_number" name="work_permit_number" value="{{ $permitNumber }}" autocomplete="off">
            @error('work_permit_number')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-4 mb-3">
            <label for="work_permit_expiry" class="form-label">Permit expiry date</label>
            <input type="date" class="form-control @error('work_permit_expiry') is-invalid @enderror" id="work_permit_expiry" name="work_permit_expiry" value="{{ $permitExpiry }}">
            <small class="text-muted d-block">HR is reminded before it runs out.</small>
            @error('work_permit_expiry')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>
