@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Staff - {{ $staff->full_names }}</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="staffEditForm" class="btn btn-primary btn-sm d-flex align-items-center" id="updateButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Update Staff</span>
                            </button>
                            <a href="{{ route('staff.show', $staff->id) }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to Details</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> Please correct the following errors:
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <strong>Success!</strong> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        <form action="{{ route('staff.update', $staff->id) }}" method="POST" id="staffEditForm" novalidate>
                            @csrf
                            @method('PUT')

                            <div id="validationSummary" class="alert alert-danger d-none mb-3" role="alert">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="material-symbols-outlined mt-1" style="font-size:18px;">error</span>
                                    <div>
                                        <strong>Please fill in all required fields before saving.</strong>
                                        <div id="validationSummaryBody" class="mt-1" style="font-size:0.88rem;"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Nav Tabs -->
                            <ul class="nav nav-tabs" id="staffEditTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab" aria-controls="personal" aria-selected="true">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">person</span>
                                        Personal Information
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="employment-tab" data-bs-toggle="tab" data-bs-target="#employment" type="button" role="tab" aria-controls="employment" aria-selected="false">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">work</span>
                                        Employment Details
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab" aria-controls="contact" aria-selected="false">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">contact_phone</span>
                                        Contact Information
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="banking-tab" data-bs-toggle="tab" data-bs-target="#banking" type="button" role="tab" aria-controls="banking" aria-selected="false">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">account_balance</span>
                                        Banking & Statutory
                                    </button>
                                </li>
                            </ul>

                            <!-- Tab Content -->
                            <div class="tab-content mt-3" id="staffEditTabsContent">
                                <!-- Personal Information Tab -->
                                <div class="tab-pane fade show active" id="personal" role="tabpanel" aria-labelledby="personal-tab">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="staff_number" class="form-label">Staff Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('staff_number') is-invalid @enderror" id="staff_number" name="staff_number" value="{{ old('staff_number', $staff->staff_number) }}" data-required="true" readonly>
                                            @error('staff_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="full_names" class="form-label">Full Names <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('full_names') is-invalid @enderror" id="full_names" name="full_names" value="{{ old('full_names', $staff->full_names) }}" data-required="true">
                                            @error('full_names')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                            <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" data-required="true">
                                                <option value="">Select Gender</option>
                                                <option value="Male" {{ old('gender', $staff->gender) == 'Male' ? 'selected' : '' }}>Male</option>
                                                <option value="Female" {{ old('gender', $staff->gender) == 'Female' ? 'selected' : '' }}>Female</option>
                                            </select>
                                            @error('gender')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="birth_date" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control @error('birth_date') is-invalid @enderror" id="birth_date" name="birth_date" value="{{ old('birth_date', $staff->birth_date ? $staff->birth_date->format('Y-m-d') : '') }}" data-required="true">
                                            @error('birth_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="national_id" class="form-label">National ID <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('national_id') is-invalid @enderror" id="national_id" name="national_id" value="{{ old('national_id', $staff->national_id) }}" readonly data-required="true">
                                            @error('national_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="passport" class="form-label">Passport Number</label>
                                            <input type="text" class="form-control @error('passport') is-invalid @enderror" id="passport" name="passport" value="{{ old('passport', $staff->passport) }}">
                                            @error('passport')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="marital" class="form-label">Marital Status <span class="text-danger">*</span></label>
                                            <select class="form-select @error('marital') is-invalid @enderror" id="marital" name="marital" data-required="true">
                                                <option value="">Select Status</option>
                                                <option value="Single" {{ old('marital', $staff->marital) == 'Single' ? 'selected' : '' }}>Single</option>
                                                <option value="Married" {{ old('marital', $staff->marital) == 'Married' ? 'selected' : '' }}>Married</option>
                                                <option value="Divorced" {{ old('marital', $staff->marital) == 'Divorced' ? 'selected' : '' }}>Divorced</option>
                                                <option value="Widowed" {{ old('marital', $staff->marital) == 'Widowed' ? 'selected' : '' }}>Widowed</option>
                                            </select>
                                            @error('marital')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="spouse_name" class="form-label">Spouse Name</label>
                                            <input type="text" class="form-control @error('spouse_name') is-invalid @enderror" id="spouse_name" name="spouse_name" value="{{ old('spouse_name', $staff->spouse_name) }}">
                                            @error('spouse_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="citizenship" class="form-label">Citizenship <span class="text-danger">*</span></label>
                                            <select class="form-select @error('citizenship') is-invalid @enderror" id="citizenship" name="citizenship" data-required="true">
                                                <option value="">Select Option</option>
                                                <option value="RESIDENT" {{ old('citizenship', $staff->citizenship) == 'RESIDENT' ? 'selected' : '' }}>RESIDENT</option>
                                                <option value="NO" {{ old('citizenship', $staff->citizenship) == 'NO' ? 'selected' : '' }}>NO</option>
                                            </select>
                                            @error('citizenship')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="disability" class="form-label">Disability <span class="text-danger">*</span></label>
                                            <select class="form-select @error('disability') is-invalid @enderror" id="disability" name="disability" data-required="true">
                                                <option value="">Select Option</option>
                                                <option value="YES" {{ old('disability', $staff->disability) == 'YES' ? 'selected' : '' }}>YES</option>
                                                <option value="NO" {{ old('disability', $staff->disability) == 'NO' ? 'selected' : '' }}>NO</option>
                                            </select>
                                            @error('disability')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Employment Details Tab -->
                                <div class="tab-pane fade" id="employment" role="tabpanel" aria-labelledby="employment-tab">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="department_id" class="form-label">Department</label>
                                            <select class="form-select @error('department_id') is-invalid @enderror" id="department_id" name="department_id">
                                                <option value="">Select Department</option>
                                                @foreach($departments as $dept)
                                                <option value="{{ $dept->id }}" {{ old('department_id', $staff->department_id) == $dept->id ? 'selected' : '' }}>
                                                    {{ $dept->department_name }}
                                                </option>
                                                @endforeach
                                            </select>
                                            @error('department_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="position_id" class="form-label">Position</label>
                                            <select class="form-select @error('position_id') is-invalid @enderror" id="position_id" name="position_id">
                                                <option value="">Select Position</option>
                                                @foreach($positions as $position)
                                                <option value="{{ $position->id }}" {{ old('position_id', $staff->position_id) == $position->id ? 'selected' : '' }}>
                                                    {{ $position->position_name }}
                                                </option>
                                                @endforeach
                                            </select>
                                            @error('position_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="hire_date" class="form-label">Hire Date</label>
                                            <input type="date" class="form-control @error('hire_date') is-invalid @enderror" id="hire_date" name="hire_date" value="{{ old('hire_date', $staff->hire_date ? $staff->hire_date->format('Y-m-d') : '') }}">
                                            @error('hire_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="zone_id" class="form-label">Zone</label>
                                            <select class="form-select @error('zone_id') is-invalid @enderror" id="zone_id" name="zone_id">
                                                <option value="">Select Zone</option>
                                                @foreach($zones as $zone)
                                                <option value="{{ $zone->id }}" {{ old('zone_id', $staff->zone_id) == $zone->id ? 'selected' : '' }}>
                                                    {{ $zone->zone_name }}
                                                </option>
                                                @endforeach
                                            </select>
                                            @error('zone_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="zone_area_id" class="form-label">Zone Area</label>
                                            <select class="form-select @error('zone_area_id') is-invalid @enderror" id="zone_area_id" name="zone_area_id">
                                                <option value="">Select Zone Area</option>
                                                @foreach($zoneAreas as $zoneArea)
                                                @if(old('zone_id', $staff->zone_id) == $zoneArea->zone_id)
                                                <option value="{{ $zoneArea->id }}" {{ old('zone_area_id', $staff->zone_area_id) == $zoneArea->id ? 'selected' : '' }}>
                                                    {{ $zoneArea->area_name }}
                                                </option>
                                                @endif
                                                @endforeach
                                            </select>
                                            @error('zone_area_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="managed_zone_ids" class="form-label">Zones Managed</label>
                                            <select class="form-select @error('managed_zone_ids') is-invalid @enderror @error('managed_zone_ids.*') is-invalid @enderror" id="managed_zone_ids" name="managed_zone_ids[]" multiple>
                                                @foreach($zones as $zone)
                                                <option value="{{ $zone->id }}" {{ collect(old('managed_zone_ids', $staff->zonesManaged->pluck('id')))->contains($zone->id) ? 'selected' : '' }}>
                                                    {{ $zone->zone_name }}
                                                </option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted">Zones this staff member manages</small>
                                            @error('managed_zone_ids')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            @error('managed_zone_ids.*')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="supervised_zone_ids" class="form-label">Zones Supervised</label>
                                            <select class="form-select @error('supervised_zone_ids') is-invalid @enderror @error('supervised_zone_ids.*') is-invalid @enderror" id="supervised_zone_ids" name="supervised_zone_ids[]" multiple>
                                                @foreach($zones as $zone)
                                                <option value="{{ $zone->id }}" {{ collect(old('supervised_zone_ids', $staff->zonesSupervised->pluck('id')))->contains($zone->id) ? 'selected' : '' }}>
                                                    {{ $zone->zone_name }}
                                                </option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted">Zones this staff member supervises</small>
                                            @error('supervised_zone_ids')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            @error('supervised_zone_ids.*')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Contact Information Tab -->
                                <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="mobile_number" class="form-label">Mobile Number</label>
                                            <input type="text" class="form-control @error('mobile_number') is-invalid @enderror" id="mobile_number" name="mobile_number" value="{{ old('mobile_number', $staff->mobile_number) }}">
                                            @error('mobile_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="email_address" class="form-label">Email Address <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control @error('email_address') is-invalid @enderror" id="email_address" name="email_address" value="{{ old('email_address', $staff->email_address) }}" @if( $staff->email_address) readonly @endif data-required="true">
                                            @error('email_address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="personal_email" class="form-label">Personal Email</label>
                                            <input type="email" class="form-control @error('personal_email') is-invalid @enderror" id="personal_email" name="personal_email" value="{{ old('personal_email', $staff->personal_email) }}">
                                            @error('personal_email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <label for="address" class="form-label">Address</label>
                                            <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2">{{ old('address', $staff->address) }}</textarea>
                                            @error('address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="district" class="form-label">District</label>
                                            <input type="text" class="form-control @error('district') is-invalid @enderror" id="district" name="district" value="{{ old('district', $staff->district) }}">
                                            @error('district')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="location" class="form-label">Location</label>
                                            <input type="text" class="form-control @error('location') is-invalid @enderror" id="location" name="location" value="{{ old('location', $staff->location) }}">
                                            @error('location')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="sub_location" class="form-label">Sub Location</label>
                                            <input type="text" class="form-control @error('sub_location') is-invalid @enderror" id="sub_location" name="sub_location" value="{{ old('sub_location', $staff->sub_location) }}">
                                            @error('sub_location')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <label for="kins_names" class="form-label">Next of Kin Names</label>
                                            <input type="text" class="form-control @error('kins_names') is-invalid @enderror" id="kins_names" name="kins_names" value="{{ old('kins_names', $staff->kins_names) }}">
                                            @error('kins_names')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Banking & Statutory Tab -->
                                <div class="tab-pane fade" id="banking" role="tabpanel" aria-labelledby="banking-tab">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="bank_name" class="form-label">Bank</label>
                                            <select class="form-select @error('bank_name') is-invalid @enderror" id="bank_name" name="bank_name">
                                                <option value="">Select Bank</option>
                                                @foreach($banks as $bank)
                                                <option value="{{ $bank->id }}" {{ old('bank_name', $staff->bank_name) == $bank->id ? 'selected' : '' }}>
                                                    {{ $bank->bank_name }}
                                                </option>
                                                @endforeach
                                            </select>
                                            @error('bank_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="account_name" class="form-label">Account Name</label>
                                            <input type="text" class="form-control @error('account_name') is-invalid @enderror" id="account_name" name="account_name" value="{{ old('account_name', $staff->account_name) }}">
                                            @error('account_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="account_number" class="form-label">Account Number</label>
                                            <input type="text" class="form-control @error('account_number') is-invalid @enderror" id="account_number" name="account_number" value="{{ old('account_number', $staff->account_number) }}">
                                            @error('account_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="pin_number" class="form-label">PIN Number</label>
                                            <input type="text" class="form-control @error('pin_number') is-invalid @enderror" id="pin_number" name="pin_number" value="{{ old('pin_number', $staff->pin_number) }}">
                                            @error('pin_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="nhif_number" class="form-label">NHIF Number</label>
                                            <input type="text" class="form-control @error('nhif_number') is-invalid @enderror" id="nhif_number" name="nhif_number" value="{{ old('nhif_number', $staff->nhif_number) }}">
                                            @error('nhif_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="nssf_number" class="form-label">NSSF Number</label>
                                            <input type="text" class="form-control @error('nssf_number') is-invalid @enderror" id="nssf_number" name="nssf_number" value="{{ old('nssf_number', $staff->nssf_number) }}">
                                            @error('nssf_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
    .nav-link .tab-error-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #dc3545;
        color: #fff;
        border-radius: 50%;
        font-size: 0.65rem;
        font-weight: 700;
        min-width: 16px;
        height: 16px;
        padding: 0 3px;
        margin-left: 5px;
        vertical-align: middle;
        line-height: 1;
    }
    @@keyframes btnShake {
        0%, 100% { transform: translateX(0); }
        20%       { transform: translateX(-4px); }
        40%       { transform: translateX(4px); }
        60%       { transform: translateX(-3px); }
        80%       { transform: translateX(3px); }
    }
    .btn-shake { animation: btnShake 0.4s ease; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {

    var TABS = [
        { pane: 'personal',   btn: 'personal-tab',   label: 'Personal Information' },
        { pane: 'employment', btn: 'employment-tab',  label: 'Employment Details'   },
        { pane: 'contact',    btn: 'contact-tab',     label: 'Contact Information'  },
        { pane: 'banking',    btn: 'banking-tab',     label: 'Banking & Statutory'  },
    ];

    function clearValidationState() {
        document.getElementById('validationSummary').classList.add('d-none');
        document.getElementById('validationSummaryBody').innerHTML = '';
        document.querySelectorAll('.tab-error-badge').forEach(function (el) { el.remove(); });
        document.querySelectorAll('#staffEditForm .is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        document.querySelectorAll('#staffEditForm .invalid-feedback.js-feedback').forEach(function (el) { el.remove(); });
    }

    function validateForm() {
        var errors = {};
        var firstErrorTab = null;

        document.querySelectorAll('#staffEditForm [data-required]').forEach(function (el) {
            var val = el.value;
            if (val !== null && val !== undefined && val.trim() !== '') return;

            var pane   = el.closest('.tab-pane');
            var paneId = pane ? pane.id : 'general';
            var tabMeta  = TABS.find(function (t) { return t.pane === paneId; });
            var tabLabel = tabMeta ? tabMeta.label : 'General';

            var labelEl    = document.querySelector('label[for="' + el.id + '"]');
            var fieldLabel = labelEl
                ? labelEl.textContent.replace(/[*]/g, '').trim()
                : (el.placeholder || el.name || 'Unknown field');

            if (!errors[tabLabel]) errors[tabLabel] = [];
            errors[tabLabel].push(fieldLabel);

            el.classList.add('is-invalid');
            if (!el.nextElementSibling || !el.nextElementSibling.classList.contains('invalid-feedback')) {
                el.insertAdjacentHTML('afterend', '<div class="invalid-feedback js-feedback">' + fieldLabel + ' is required</div>');
            }

            if (!firstErrorTab) firstErrorTab = tabMeta;
        });

        return { errors: errors, firstErrorTab: firstErrorTab };
    }

    function showValidationUI(errors, firstErrorTab) {
        var html = '<ul class="mb-0 ps-3">';
        Object.keys(errors).forEach(function (tabLabel) {
            html += '<li><strong>' + tabLabel + ':</strong> ' + errors[tabLabel].join(', ') + '</li>';
        });
        html += '</ul>';
        document.getElementById('validationSummaryBody').innerHTML = html;
        document.getElementById('validationSummary').classList.remove('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });

        Object.keys(errors).forEach(function (tabLabel) {
            var tabMeta = TABS.find(function (t) { return t.label === tabLabel; });
            if (!tabMeta) return;
            var btn = document.getElementById(tabMeta.btn);
            if (!btn) return;
            var existing = btn.querySelector('.tab-error-badge');
            if (existing) existing.remove();
            btn.insertAdjacentHTML('beforeend', '<span class="tab-error-badge">' + errors[tabLabel].length + '</span>');
        });

        if (firstErrorTab) {
            var btn = document.getElementById(firstErrorTab.btn);
            if (btn && window.bootstrap) { new bootstrap.Tab(btn).show(); }
        }

        var updateBtn = document.getElementById('updateButton');
        updateBtn.classList.add('btn-shake');
        updateBtn.addEventListener('animationend', function () {
            updateBtn.classList.remove('btn-shake');
        }, { once: true });
    }

    document.getElementById('staffEditForm').addEventListener('input',  function (e) { handleFieldFix(e.target); });
    document.getElementById('staffEditForm').addEventListener('change', function (e) { handleFieldFix(e.target); });

    function handleFieldFix(el) {
        if (!el.hasAttribute('data-required') || !el.value || el.value.trim() === '') return;
        el.classList.remove('is-invalid');
        var next = el.nextElementSibling;
        if (next && next.classList.contains('js-feedback')) next.remove();

        var pane = el.closest('.tab-pane');
        if (pane) {
            var tabMeta = TABS.find(function (t) { return t.pane === pane.id; });
            if (tabMeta) {
                var remaining = document.querySelectorAll('#' + pane.id + ' [data-required].is-invalid').length;
                var badge = document.querySelector('#' + tabMeta.btn + ' .tab-error-badge');
                if (remaining === 0) { if (badge) badge.remove(); }
                else { if (badge) badge.textContent = remaining; }
            }
        }
        if (document.querySelectorAll('#staffEditForm .is-invalid').length === 0) {
            document.getElementById('validationSummary').classList.add('d-none');
        }
    }

    document.getElementById('staffEditForm').addEventListener('submit', function (e) {
        clearValidationState();
        var result = validateForm();
        if (Object.keys(result.errors).length > 0) {
            e.preventDefault();
            showValidationUI(result.errors, result.firstErrorTab);
            return;
        }
        var btn = document.getElementById('updateButton');
        document.getElementById('submitSpinner').classList.remove('d-none');
        btn.disabled = true;
        btn.style.opacity = '0.6';
    });

    document.getElementById('zone_id').addEventListener('change', function () {
        var zoneId = this.value;
        var zoneArea = document.getElementById('zone_area_id');
        zoneArea.innerHTML = '<option value="">Select Zone Area</option>';
        if (!zoneId) return;
        fetch('/ajax/zone-areas/' + zoneId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                data.forEach(function (za) {
                    var opt = document.createElement('option');
                    opt.value = za.id;
                    opt.textContent = za.area_name;
                    zoneArea.appendChild(opt);
                });
            })
            .catch(function () { console.error('Failed to load zone areas'); });
    });

    $('#managed_zone_ids').select2({ placeholder: 'Select zones managed', width: '100%' });
    $('#supervised_zone_ids').select2({ placeholder: 'Select zones supervised', width: '100%' });

    var serverErrors = document.querySelectorAll('#staffEditForm .is-invalid');
    if (serverErrors.length) {
        var firstPane = serverErrors[0].closest('.tab-pane');
        if (firstPane) {
            var btn = document.getElementById(firstPane.id + '-tab');
            if (btn && window.bootstrap) { new bootstrap.Tab(btn).show(); }
        }
        TABS.forEach(function (t) {
            var count = document.querySelectorAll('#' + t.pane + ' .is-invalid').length;
            if (count > 0) {
                var btn = document.getElementById(t.btn);
                if (btn) btn.insertAdjacentHTML('beforeend', '<span class="tab-error-badge">' + count + '</span>');
            }
        });
    }

    var successAlert = document.querySelector('.alert-success');
    if (successAlert) {
        setTimeout(function () {
            successAlert.style.transition = 'opacity 0.5s';
            successAlert.style.opacity = '0';
            setTimeout(function () { successAlert.remove(); }, 500);
        }, 5000);
    }

});
</script>
@endsection