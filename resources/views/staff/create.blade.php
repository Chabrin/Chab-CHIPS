@extends('layouts.main')
@section('content')
@php
$canCreateUsers = auth()->user()->hasPermission('create_users');
// When finishing an approved onboarding submission, fields start with what the new staff member entered
$onboarding = $onboarding ?? null;
$prefill = $prefill ?? [];
$onboardingFiles = $onboardingFiles ?? [];
$val = fn ($field, $default = null) => old($field, $prefill[$field] ?? $default);
$fileUrl = fn ($field) => route('staff.onboarding.document', [$onboarding, hash('sha256', $onboardingFiles[$field]['path'])]);
@endphp
<style>
    /* Match select2 to Bootstrap's .form-select styling — the stock select2 theme
       otherwise ships with the wrong height, border-radius, and focus ring. */
    .select2-container--default .select2-selection--multiple,
    .select2-container--default .select2-selection--single {
        min-height: 38px;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
    }
    .select2-container--default .select2-selection--multiple {
        padding: 0.25rem;
    }
    .select2-container--default .select2-selection--single {
        padding: 0.25rem 0.5rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 28px;
        padding-left: 0;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }
    .select2-container--default.select2-container--focus .select2-selection--multiple,
    .select2-container--default.select2-container--open .select2-selection--single {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #e7f1ff;
        border: 1px solid #b6d4fe;
        border-radius: 0.375rem;
        color: #084298;
        padding: 2px 8px;
        margin: 3px 4px 0 0;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #084298;
        margin-right: 6px;
        border: none;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: #052c65;
        background: transparent;
    }
    /* The inline search box in multi-select mode is a <textarea> (for paste support), which
       wraps its placeholder onto multiple lines instead of showing it on one line like a
       normal input — force single-line rendering so the empty field doesn't look broken. */
    .select2-container--default .select2-search--inline .select2-search__field {
        min-height: 28px;
        line-height: 28px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-top: 3px;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__rendered {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
    }
</style>
<div class="page-body staff-onboarding" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Add New Staff</h4>
                        <div class="ms-auto d-flex gap-2">
                            {{-- <button type="submit" form="staffCreateForm" class="btn btn-primary btn-sm d-flex align-items-center" id="createButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Save Staff</span>
                            </button> --}}
                            <a href="{{ route('staff.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
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
                        @if($onboarding)
                        <div class="alert alert-info">
                            <strong>Completing onboarding for {{ $onboarding->recipient_email }}.</strong>
                            Personal, contact, banking and document details below were filled in by the new staff member. Check them, then complete the
                            Employment Category, Official Email, Employment Details and System User before saving.
                        </div>
                        @endif
                        @if(!$canCreateUsers)
                        <div class="alert alert-warning">You need the "create users" permission to add staff, because every staff member gets a system login.</div>
                        @endif

                       <form action="{{ route('staff.store') }}" method="POST" id="staffCreateForm" enctype="multipart/form-data">
                            @csrf
                            @if($onboarding)
                            <input type="hidden" name="onboarding_invitation_id" value="{{ $onboarding->id }}">
                            @endif

                            <!-- Nav Tabs -->
                            <ul class="nav nav-tabs" id="staffCreateTabs" role="tablist">
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
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab" aria-controls="documents" aria-selected="false">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">upload_file</span>
                                        Documents
                                    </button>
                                </li>
                                @if($canCreateUsers)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="user-tab" data-bs-toggle="tab" data-bs-target="#user" type="button" role="tab" aria-controls="user" aria-selected="false">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">person_add</span>
                                        System User
                                    </button>
                                </li>
                                @endif
                            </ul>
                            <!-- Tab Content -->
                            <div class="tab-content mt-3" id="staffCreateTabsContent">
                                <!-- Personal Information Tab -->
                                <div class="tab-pane fade show active" id="personal" role="tabpanel" aria-labelledby="personal-tab">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            {{-- added on 27th --}}
                                        <label for="employment_type" class="form-label">Employment Category <span class="text-danger">*</span></label>
                                        <select class="form-select @error('employment_type') is-invalid @enderror" id="employment_type" name="employment_type" required>
                                            <option value="Permanent" {{ $val('employment_type', 'Permanent') == 'Permanent' ? 'selected' : '' }}>Permanent</option>
                                            <option value="Temporary" {{ $val('employment_type') == 'Temporary' ? 'selected' : '' }}>Temporary</option>
                                            <option value="Casual" {{ $val('employment_type') == 'Casual' ? 'selected' : '' }}>Casual</option>
                                            <option value="Intern" {{ $val('employment_type') == 'Intern' ? 'selected' : '' }}>Intern</option>
                                        </select>
                                        @error('employment_type')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                       <div class="col-md-4 mb-3">
                                            <label for="staff_number" class="form-label">Staff Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('staff_number') is-invalid @enderror"
                                                id="staff_number" name="staff_number"
                                                value="{{ old('staff_number', $nextStaffNumber) }}" readonly required>
                                            
                                            @error('staff_number')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="full_names" class="form-label">Full Names <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('full_names') is-invalid @enderror" id="full_names" name="full_names" value="{{ $val('full_names') }}" required>
                                            @error('full_names')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                            <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required>
                                                <option value="">Select Gender</option>
                                                <option value="Male" {{ $val('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                                                <option value="Female" {{ $val('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                                            </select>
                                            @error('gender')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="birth_date" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control @error('birth_date') is-invalid @enderror" id="birth_date" name="birth_date" value="{{ $val('birth_date') }}" required>
                                            @error('birth_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="birth_place" class="form-label">Birth Place</label>
                                            <input type="text" class="form-control @error('birth_place') is-invalid @enderror" id="birth_place" name="birth_place" value="{{ $val('birth_place') }}">
                                            @error('birth_place')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="national_id" class="form-label">National ID <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('national_id') is-invalid @enderror" id="national_id" name="national_id" value="{{ $val('national_id') }}" required>
                                            @error('national_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="passport" class="form-label">Passport Number</label>
                                            <input type="text" class="form-control @error('passport') is-invalid @enderror" id="passport" name="passport" value="{{ $val('passport') }}">
                                            @error('passport')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="marital" class="form-label">Marital Status <span class="text-danger">*</span></label>
                                            <select class="form-select @error('marital') is-invalid @enderror" id="marital" name="marital" required>
                                                <option value="">Select Status</option>
                                                <option value="Single" {{ $val('marital') == 'Single' ? 'selected' : '' }}>Single</option>
                                                <option value="Married" {{ $val('marital') == 'Married' ? 'selected' : '' }}>Married</option>
                                                <option value="Divorced" {{ $val('marital') == 'Divorced' ? 'selected' : '' }}>Divorced</option>
                                                <option value="Widowed" {{ $val('marital') == 'Widowed' ? 'selected' : '' }}>Widowed</option>
                                            </select>
                                            @error('marital')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="spouse_name" class="form-label">Spouse Name</label>
                                            <input type="text" class="form-control @error('spouse_name') is-invalid @enderror" id="spouse_name" name="spouse_name" value="{{ $val('spouse_name') }}">
                                            @error('spouse_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="citizenship" class="form-label">Citizenship <span class="text-danger">*</span></label>
                                            <select class="form-select @error('citizenship') is-invalid @enderror" id="citizenship" name="citizenship" required>
                                                <option value="">Select Option</option>
                                                <option value="RESIDENT" {{ $val('citizenship') == 'RESIDENT' ? 'selected' : '' }}>RESIDENT</option>
                                                <option value="NO" {{ $val('citizenship') == 'NO' ? 'selected' : '' }}>NO</option>
                                            </select>
                                            @error('citizenship')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="disability" class="form-label">Disability <span class="text-danger">*</span></label>
                                            <select class="form-select @error('disability') is-invalid @enderror" id="disability" name="disability" required>
                                                <option value="">Select Option</option>
                                                <option value="YES" {{ $val('disability') == 'YES' ? 'selected' : '' }}>YES</option>
                                                <option value="NO" {{ $val('disability') == 'NO' ? 'selected' : '' }}>NO</option>
                                            </select>
                                            @error('disability')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                         <div class="col-md-4 mb-3">
                                            <label for="personal_email_address" class="form-label">Official Email <span class="text-danger">*</span></label>
                                            <input type="email" 
                                                class="form-control @error('email_address') is-invalid @enderror" 
                                                id="personal_email_address" 
                                                name="email_address" 
                                                value="{{ old('email_address') }}" 
                                                required>
                                            @error('email_address')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="row mb-4 align-items-center">
                                            <div class="col-auto">
                                                <img id="photo-preview" src="{{ isset($onboardingFiles['photo']) ? $fileUrl('photo') : asset('images/default-avatar.svg') }}" 
                                                    class="rounded-circle border" width="90" height="90" style="object-fit: cover;">
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label fw-semibold small mb-1">Staff Profile Photo</label>
                                                <input type="file" name="photo" id="photo-input" class="form-control form-control-sm" accept="image/*">
                                                <small class="text-muted">Square ratio (JPG, PNG). Check the current upload limit above.</small>
                                                @if(isset($onboardingFiles['photo']))<small class="d-block text-success">Photo received from onboarding. Upload a new one only to replace it.</small>@endif
                                            </div>
                                        </div>
                                    </div>
                                    @include('staff.partials.step-footer')
                                </div>

                                <!-- Employment Details Tab -->
                                <div class="tab-pane fade" id="employment" role="tabpanel" aria-labelledby="employment-tab">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="department_id" class="form-label">Department</label>
                                            <select class="form-select @error('department_id') is-invalid @enderror" id="department_id" name="department_id">
                                                <option value="">Select Department</option>
                                                @foreach($departments as $dept)
                                                <option value="{{ $dept->id }}" {{ $val('department_id') == $dept->id ? 'selected' : '' }}>
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
                                            <select class="form-select @error('position_id') is-invalid @enderror" id="position_id" name="position_id" {{ $val('department_id') ? '' : 'disabled' }}>
                                                <option value="">Select Position</option>
                                                @foreach($positions as $position)
                                                <option value="{{ $position->id }}" 
                                                        data-department="{{ $position->department_id }}"
                                                        {{ $val('position_id') == $position->id ? 'selected' : '' }}>
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
                                            <input type="date" class="form-control @error('hire_date') is-invalid @enderror" id="hire_date" name="hire_date" value="{{ $val('hire_date') }}">
                                            @error('hire_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="row col-md-12 mb-3">
                                            <div class="col-md-4 mb-3 zone-role-field" id="zone_field_group" style="display:none;">
                                                <label for="zone_id" class="form-label">Zone</label>
                                                <select class="form-select @error('zone_id') is-invalid @enderror" id="zone_id" name="zone_id">
                                                    <option value="">Select Zone</option>
                                                    @foreach($zones as $zone)
                                                    <option value="{{ $zone->id }}" {{ $val('zone_id') == $zone->id ? 'selected' : '' }}>
                                                        {{ $zone->zone_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                                <small class="text-muted">Zone this staff member is a member of</small>
                                                @error('zone_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="col-md-4 mb-3 zone-role-field" id="zone_managed_group" style="display:none;">
                                                <label for="managed_zone_ids" class="form-label">Zones Managed</label>
                                                <select class="form-select @error('managed_zone_ids') is-invalid @enderror @error('managed_zone_ids.*') is-invalid @enderror" id="managed_zone_ids" name="managed_zone_ids[]" multiple>
                                                    @foreach($zones as $zone)
                                                    <option value="{{ $zone->id }}" {{ collect(old('managed_zone_ids', []))->contains($zone->id) ? 'selected' : '' }}>
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

                                            <div class="col-md-4 mb-3 zone-role-field" id="zone_supervised_group" style="display:none;">
                                                <label for="supervised_zone_ids" class="form-label">Zones Supervised</label>
                                                <select class="form-select @error('supervised_zone_ids') is-invalid @enderror @error('supervised_zone_ids.*') is-invalid @enderror" id="supervised_zone_ids" name="supervised_zone_ids[]" multiple>
                                                    @foreach($zones as $zone)
                                                    <option value="{{ $zone->id }}" {{ collect(old('supervised_zone_ids', []))->contains($zone->id) ? 'selected' : '' }}>
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
                                    @include('staff.partials.step-footer')
                                </div>

                                <!-- Contact Information Tab -->
                                <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="mobile_number" class="form-label">Mobile Number</label>
                                            <input type="text" class="form-control @error('mobile_number') is-invalid @enderror" id="mobile_number" name="mobile_number" value="{{ $val('mobile_number') }}">
                                            @error('mobile_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="contact_email_address" class="form-label">Official Email <span class="text-danger">*</span></label>
                                            {{-- No name attribute: the submitted value comes from the Personal Information tab input.
                                                 The two are kept in sync by JS and both are editable. --}}
                                            <input type="email" 
                                                class="form-control @error('email_address') is-invalid @enderror" 
                                                id="contact_email_address" 
                                                value="{{ old('email_address') }}">
                                            @error('email_address')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="personal_email" class="form-label">Personal Email</label>
                                            <input type="email" class="form-control @error('personal_email') is-invalid @enderror" id="personal_email" name="personal_email" value="{{ $val('personal_email') }}">
                                            @error('personal_email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <label for="address" class="form-label">Address</label>
                                            <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2">{{ $val('address') }}</textarea>
                                            @error('address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="district" class="form-label">District</label>
                                            <input type="text" class="form-control @error('district') is-invalid @enderror" id="district" name="district" value="{{ $val('district') }}">
                                            @error('district')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="location" class="form-label">Location</label>
                                            <input type="text" class="form-control @error('location') is-invalid @enderror" id="location" name="location" value="{{ $val('location') }}">
                                            @error('location')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="sub_location" class="form-label">Sub Location</label>
                                            <input type="text" class="form-control @error('sub_location') is-invalid @enderror" id="sub_location" name="sub_location" value="{{ $val('sub_location') }}">
                                            @error('sub_location')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-8 mb-3">
                                            <label for="kins_names" class="form-label">Next of Kin Names</label>
                                            <input type="text" class="form-control @error('kins_names') is-invalid @enderror" id="kins_names" name="kins_names" value="{{ $val('kins_names') }}">
                                            @error('kins_names')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="kins_relationship" class="form-label">Relationship to Staff</label>
                                            <select class="form-select @error('kins_relationship') is-invalid @enderror" id="kins_relationship" name="kins_relationship">
                                                <option value="">Select Relationship</option>
                                                @foreach(\App\Models\Staff::KIN_RELATIONSHIPS as $relationship)
                                                <option value="{{ $relationship }}" {{ $val('kins_relationship') == $relationship ? 'selected' : '' }}>{{ $relationship }}</option>
                                                @endforeach
                                            </select>
                                            @error('kins_relationship')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    @include('staff.partials.step-footer')
                                </div>

                                <!-- Banking & Statutory Tab -->
                                <div class="tab-pane fade" id="banking" role="tabpanel" aria-labelledby="banking-tab">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="bank_name" class="form-label">Bank <span class="text-danger">*</span></label>
                                            <select class="form-select @error('bank_name') is-invalid @enderror" id="bank_name" name="bank_name" required>
                                                <option value="">Select Bank</option>
                                                @foreach($banks as $bank)
                                                <option value="{{ $bank->id }}" {{ $val('bank_name') == $bank->id ? 'selected' : '' }}>
                                                    {{ $bank->bank_name }}
                                                </option>
                                                @endforeach
                                            </select>
                                            @error('bank_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="account_name" class="form-label">Account Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('account_name') is-invalid @enderror" id="account_name" name="account_name" value="{{ $val('account_name') }}" required>
                                            @error('account_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="account_number" class="form-label">Account Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('account_number') is-invalid @enderror" id="account_number" name="account_number" value="{{ $val('account_number') }}" required>
                                            @error('account_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="pin_number" class="form-label">KRA PIN Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('pin_number') is-invalid @enderror" id="pin_number" name="pin_number" value="{{ $val('pin_number') }}" required>
                                            @error('pin_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="nhif_number" class="form-label">SHA Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('nhif_number') is-invalid @enderror" id="nhif_number" name="nhif_number" value="{{ $val('nhif_number') }}" required>
                                            @error('nhif_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="nssf_number" class="form-label">NSSF Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('nssf_number') is-invalid @enderror" id="nssf_number" name="nssf_number" value="{{ $val('nssf_number') }}" required>
                                            @error('nssf_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    @include('staff.partials.step-footer')
                                </div>
                                <!-- Documentation section  -->
                                <div class="tab-pane fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
                                    <div class="pt-3 pb-2 mb-3 border-bottom">
                                        <h6 class="fw-bold mb-1">Staff Documentation & Verification</h6>
                                        <p class="text-muted small mb-0">Upload statutory documents, contracts, and certifications. Current upload limits are shown beside each field.</p>
                                    </div>

                                    <div class="row g-3">
                                        <!-- National ID / Passport -->
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 bg-light">
                                                <label class="form-label fw-semibold small mb-1">
                                                    National ID / Passport Copy <span class="text-danger">*</span>
                                                </label>
                                                <input type="file" name="documents[national_id]" class="form-control @error('documents.national_id') is-invalid @enderror" @unless(isset($onboardingFiles['documents.national_id'])) required @endunless accept=".pdf,.png,.jpg,.jpeg">
                                                @if(isset($onboardingFiles['documents.national_id']))<small class="d-block text-success">Received from onboarding: {{ $onboardingFiles['documents.national_id']['name'] }} <a href="{{ $fileUrl('documents.national_id') }}" target="_blank" rel="noopener">Preview</a>. Upload a file only to replace it.</small>@endif
                                                <small class="text-muted">Scanned front/back copy or passport bio-page</small>
                                            </div>
                                        </div>

                                        <!-- KRA PIN Certificate -->
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 bg-light">
                                                <label class="form-label fw-semibold small mb-1">
                                                    KRA PIN Certificate <span class="text-danger">*</span>
                                                </label>
                                                <input type="file" name="documents[kra_pin]" class="form-control @error('documents.kra_pin') is-invalid @enderror" @unless(isset($onboardingFiles['documents.kra_pin'])) required @endunless accept=".pdf,.png,.jpg,.jpeg">
                                                @if(isset($onboardingFiles['documents.kra_pin']))<small class="d-block text-success">Received from onboarding: {{ $onboardingFiles['documents.kra_pin']['name'] }} <a href="{{ $fileUrl('documents.kra_pin') }}" target="_blank" rel="noopener">Preview</a>. Upload a file only to replace it.</small>@endif
                                                <small class="text-muted">KRA PIN registration certificate</small>
                                            </div>
                                        </div>

                                        <!-- Signed Employment Contract (REQ-14) -->
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 bg-light">
                                                <label class="form-label fw-semibold small mb-1">
                                                    Signed Employment Contract
                                                </label>
                                                <input type="file" name="documents[contract]" class="form-control" accept=".pdf">
                                                @if(isset($onboardingFiles['documents.contract']))<small class="d-block text-success">Received from onboarding: {{ $onboardingFiles['documents.contract']['name'] }} <a href="{{ $fileUrl('documents.contract') }}" target="_blank" rel="noopener">Preview</a>. Upload a file only to replace it.</small>@endif
                                                <small class="text-muted">Physical/counter-signed contract in PDF format</small>
                                            </div>
                                        </div>
                                        <!-- Curriculum Vitae (CV / Resume) -->
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 bg-light">
                                                <label class="form-label fw-semibold small mb-1">
                                                    Curriculum Vitae (CV / Resume)
                                                </label>
                                                <input type="file" name="documents[cv]" class="form-control" accept=".pdf,.doc,.docx">
                                                @if(isset($onboardingFiles['documents.cv']))<small class="d-block text-success">Received from onboarding: {{ $onboardingFiles['documents.cv']['name'] }} <a href="{{ $fileUrl('documents.cv') }}" target="_blank" rel="noopener">Preview</a>. Upload a file only to replace it.</small>@endif
                                                <small class="text-muted">Updated staff resume or CV (PDF or Word). Check the current upload limit above.</small>
                                            </div>
                                        </div>
                                        <!-- Academic / Professional Certificates -->
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 bg-light">
                                                <label class="form-label fw-semibold small mb-1">
                                                    Academic & Professional Certificates
                                                </label>
                                                <input type="file" name="documents[academic]" class="form-control" accept=".pdf,.png,.jpg,.jpeg">
                                                @if(isset($onboardingFiles['documents.academic']))<small class="d-block text-success">Received from onboarding: {{ $onboardingFiles['documents.academic']['name'] }} <a href="{{ $fileUrl('documents.academic') }}" target="_blank" rel="noopener">Preview</a>. Upload a file only to replace it.</small>@endif
                                                <small class="text-muted">Degrees, diplomas, transcripts, or certifications</small>
                                            </div>
                                        </div>
                                        <!-- Staff signature -->
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small mb-1">Staff Signature</label>
                                            @include('staff.partials.signature-input', [
                                                'currentSignatureUrl' => isset($onboardingFiles['signature']) ? $fileUrl('signature') : null,
                                                'currentSignatureNote' => 'Received from onboarding. Draw or upload only to replace it.',
                                            ])
                                        </div>
                                    </div>
                                    @include('staff.partials.step-footer')
                                </div>

                                @if($canCreateUsers)
                                <!-- System User Tab (mandatory) -->
                                <div class="tab-pane fade" id="user" role="tabpanel" aria-labelledby="user-tab">
                                    <p class="text-muted small">Required — a system login will be created for this staff member.</p>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="login_email" class="form-label">Login Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control @error('login_email') is-invalid @enderror"
                                                id="login_email" name="login_email"
                                                value="{{ old('login_email', old('email_address')) }}" required>
                                            <small class="text-muted">Defaults to the Official Email, but you can change it.</small>
                                            @error('login_email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="role_id" class="form-label">Role <span class="text-danger">*</span></label>
                                            <select class="form-select @error('role_id') is-invalid @enderror" id="role_id" name="role_id" required>
                                                <option value="">Select Role</option>
                                                @foreach($roles as $role)
                                                <option value="{{ $role->id }}" {{ $val('role_id') == $role->id ? 'selected' : '' }}>{{ $role->display_name }}</option>
                                                @endforeach
                                            </select>
                                            @error('role_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="password" class="form-label">Password <span class="text-muted small">(optional — leave blank to auto-generate)</span></label>
                                            <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" minlength="8" autocomplete="new-password">
                                            @error('password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    @include('staff.partials.step-footer')
                                </div>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {
    var $departmentSelect =$('#department_id');
    var $positionSelect =$('#position_id');

    // Cache all position options on page load
    var allPositionOptions = $positionSelect.find('option').clone();

    function filterPositions(departmentId, preserveValue = false) {
        var currentSelected = preserveValue ? $positionSelect.val() : '';

        // Reset and disable if no department is picked
        if (!departmentId) {
            $positionSelect.html('<option value="">Select Position</option>').prop('disabled', true).trigger('change');
            return;
        }

        $positionSelect.empty().prop('disabled', false);$positionSelect.append('<option value="">Select Position</option>');

        // Positions of the chosen department, plus positions not assigned to any department yet (so they stay
        // selectable until HR places them); if that leaves nothing (e.g. the hierarchy is not loaded), fall back
        // to every position so onboarding is not blocked.
        var realOptions = allPositionOptions.filter(function() {
            return $(this).val();
        });
        var matchingOptions = realOptions.filter(function() {
            var optDept = $(this).attr('data-department');
            return !optDept || optDept == departmentId;
        });
        var visibleOptions = matchingOptions.length ? matchingOptions : realOptions;

        visibleOptions.each(function() {
            var $opt =$(this);
            var optVal = $opt.val();
            var $newOpt =$('<option>').val(optVal).attr('data-department', $opt.attr('data-department')).text($opt.text().trim());
            if (currentSelected && optVal == currentSelected) {
                $newOpt.prop('selected', true);
            }
            $positionSelect.append($newOpt);
        });

        // Let dependent UI (zone fields) re-evaluate against the rebuilt list
        $positionSelect.trigger('change');
    }

    // Trigger on department dropdown change
    $departmentSelect.on('change', function() {
        filterPositions($(this).val(), false);
    });

    // Run on initial load to handle page reloads or validation redirects
    if ($departmentSelect.val()) {
        filterPositions($departmentSelect.val(), true);
    }
});
    $(document).ready(function() {
        // Show only the zone field relevant to the selected position. Select2 is initialized
        // lazily, the first time each group becomes visible — initializing it while the group
        // is still display:none gives it a broken (zero) width.
        var positionSlugs = @json($positions->pluck('slug_name', 'id'));
        var select2Initialized = {};
        function ensureSelect2(selectId, groupId, options) {
            if (select2Initialized[selectId]) return;
            select2Initialized[selectId] = true;
            $(selectId).select2(options);
        }
        function toggleZoneFields() {
            var slug = positionSlugs[$('#position_id').val()] || null;

            var showZone = slug === 'field_officer';
            $('#zone_field_group').toggle(showZone);
            if (showZone) ensureSelect2('#zone_id', '#zone_field_group', { placeholder: 'Select Zone', width: '100%', allowClear: true });

            var showManaged = slug === 'zone_manager';
            $('#zone_managed_group').toggle(showManaged);
            if (showManaged) ensureSelect2('#managed_zone_ids', '#zone_managed_group', { placeholder: 'Select zones managed', width: '100%' });

            var showSupervised = slug === 'zone_supervisor';
            $('#zone_supervised_group').toggle(showSupervised);
            if (showSupervised) ensureSelect2('#supervised_zone_ids', '#zone_supervised_group', { placeholder: 'Select zones supervised', width: '100%' });
        }
        $('#position_id').on('change', toggleZoneFields);
        toggleZoneFields();

        // Email sync: Official Email (Personal tab) <-> Official Email (Contact tab) -> Login Email (System User tab).
        // All three are editable. The login email follows the official email until the user
        // types their own login email, after which it is left alone.
        var loginEmailEdited = false;
        var $officialEmails = $('#personal_email_address, #contact_email_address');

        // If the form re-rendered after a validation error with a different login email, don't overwrite it
        if ($('#login_email').length && $('#login_email').val() !== '' &&
            $('#login_email').val() !== $('#personal_email_address').val()) {
            loginEmailEdited = true;
        }

        $('#login_email').on('input', function() {
            loginEmailEdited = true;
        });

        $officialEmails.on('input', function() {
            var val = $(this).val();
            $officialEmails.not(this).val(val);
            if (!loginEmailEdited) {
                $('#login_email').val(val);
            }
        });

        // Handle form submission
        $('#staffCreateForm').on('submit', function(event) {
            event.preventDefault();
            var submitButton = $('#createButton');
            var spinner = $('#submitSpinner');

            // Show spinner and disable button
            spinner.removeClass('d-none');
            submitButton.prop('disabled', true);
            submitButton.css('opacity', '0.6');
            window.EmployeeStaffForm.save();
        });

        // A required field inside a hidden tab blocks submit silently, so jump to the tab that has it.
        // 'invalid' doesn't bubble, so listen in the capture phase on the form.
        document.getElementById('staffCreateForm').addEventListener('invalid', function(e) {
            var pane = $(e.target).closest('.tab-pane');
            if (pane.length && !pane.hasClass('active')) {
                var tabBtn = document.getElementById(pane.attr('id') + '-tab');
                if (tabBtn) {
                    new bootstrap.Tab(tabBtn).show();
                }
            }
            // Re-enable the submit button, since the browser blocked the submission
            $('#submitSpinner').addClass('d-none');
            $('#createButton').prop('disabled', false).css('opacity', '1');
        }, true);

        // Handle zone change to filter zone areas
        $('#zone_id').on('change', function() {
            var zoneId = $(this).val();
            var zoneAreaSelect = $('#zone_area_id');

            // Clear zone area dropdown
            zoneAreaSelect.html('<option value="">Select Zone Area</option>');

            if (zoneId) {
                // Fetch zone areas for selected zone
                $.ajax({
                    url: '/ajax/zone-areas/' + zoneId,
                    type: 'GET',
                    success: function(data) {
                        $.each(data, function(index, zoneArea) {
                            zoneAreaSelect.append(
                                $('<option></option>')
                                .attr('value', zoneArea.id)
                                .text(zoneArea.area_name)
                            );
                        });
                    },
                    error: function() {
                        console.error('Failed to load zone areas');
                    }
                });
            }
        });

        // If there are validation errors, find which tab has errors and activate it
        var firstErrorField = $('.is-invalid').first();
        if (firstErrorField.length) {
            var tabPane = firstErrorField.closest('.tab-pane');
            if (tabPane.length) {
                var tabId = tabPane.attr('id');
                var tabButton = document.getElementById(tabId + '-tab');
                if (tabButton) {
                    var tab = new bootstrap.Tab(tabButton);
                    tab.show();
                }
            }
        }
    });

    // The relationship is needed as soon as a next of kin name is typed
    (function () {
        var kinName = document.getElementById('kins_names');
        var kinRelationship = document.getElementById('kins_relationship');
        function syncKinRelationship() { kinRelationship.required = kinName.value.trim() !== ''; }
        kinName.addEventListener('input', syncKinRelationship);
        syncKinRelationship();
    })();

    // Live preview of the selected staff photo
    document.getElementById('photo-input').addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = function(evt) {
                document.getElementById('photo-preview').src = evt.target.result;
            }
            reader.readAsDataURL(e.target.files[0]);
        }
    });
    document.addEventListener('DOMContentLoaded', function () {
        // Name sync (Full Names -> Account Name)
        const fullNameInput = document.getElementById('full_names');
        const accountNameInput = document.getElementById('account_name');

        if (fullNameInput && accountNameInput) {
            fullNameInput.addEventListener('input', function () {
                accountNameInput.value = this.value;
            });
        }
    });
// added on 27th
   document.addEventListener('DOMContentLoaded', function () {
    const employmentTypeSelect = document.getElementById('employment_type');
    const staffNumberInput = document.getElementById('staff_number');
    
    const userTabLi = document.getElementById('user-tab')?.closest('li'); // The tab header item
    const userTabPane = document.getElementById('user'); // The tab content pane
    const loginEmailInput = document.getElementById('login_email');
    const roleIdSelect = document.getElementById('role_id');

    function handleEmploymentTypeChanges() {
        if (!employmentTypeSelect) return;
        let category = employmentTypeSelect.value;
        let isCasual = category === 'Casual';

        // 1. Handle System User Tab Visibility for Casual Staff
        if (isCasual) {
            if (userTabLi) userTabLi.style.display = 'none';
            if (userTabPane) {
                userTabPane.classList.remove('show', 'active');
                userTabPane.style.display = 'none';
            }
            if (loginEmailInput) loginEmailInput.required = false;
            if (roleIdSelect) roleIdSelect.required = false;

            const personalTabBtn = document.getElementById('personal-tab');
            if (personalTabBtn) {
                new bootstrap.Tab(personalTabBtn).show();
            }
        } else {
            if (userTabLi) userTabLi.style.display = '';
            if (userTabPane) userTabPane.style.display = '';
            if (loginEmailInput) loginEmailInput.required = true;
            if (roleIdSelect) roleIdSelect.required = true;
        }

        // 2. Fetch Category-Specific Staff Number via AJAX
        if (staffNumberInput) {
            fetch(`{{ route('staff.get-staff-number') }}?category=${encodeURIComponent(category)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        staffNumberInput.value = data.staff_number;
                    }
                })
                .catch(error => console.error('Error fetching dynamic staff number:', error));
        }
    }

    if (employmentTypeSelect) {
        employmentTypeSelect.addEventListener('change', handleEmploymentTypeChanges);
        // Run on initial load just in case old values or defaults are set
        handleEmploymentTypeChanges();
    }
});
</script>
<script src="{{ asset('assets/js/staff-form-steps.js') }}?v={{ filemtime(public_path('assets/js/staff-form-steps.js')) }}"></script>
@include('staff.partials.upload-duplicate-check')
@endsection
