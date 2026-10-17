@extends('layouts.main')
@section('content')
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
    /* Chabrin staff editor refresh — visual styling only; Bootstrap and existing routes remain in use. */
    .staff-edit-page { --chabrin-green: #287653; --chabrin-green-dark: #1e5d40; --chabrin-green-soft: #eaf4ee; --chabrin-ink: #24352d; --chabrin-muted: #718078; background: #f5f7f6; padding: 24px 0 42px; }
    .staff-edit-page .container-fluid { max-width: 1500px; padding-left: clamp(14px, 2vw, 28px); padding-right: clamp(14px, 2vw, 28px); }
    .staff-edit-heading { display:flex; align-items:center; justify-content:space-between; gap:18px; flex-wrap:wrap; margin-bottom:22px; }
    .staff-edit-eyebrow { color:var(--chabrin-green); text-transform:uppercase; letter-spacing:.12em; font-size:.7rem; font-weight:800; margin-bottom:6px; }
    .staff-edit-title { color:var(--chabrin-ink); font-size:clamp(1.35rem,2vw,1.8rem); font-weight:750; letter-spacing:-.035em; margin:0; }
    .staff-edit-subtitle { color:var(--chabrin-muted); margin:6px 0 0; font-size:.91rem; }
    .staff-edit-page .staff-main-card { border:1px solid #e5ebe7; border-radius:16px; overflow:visible; box-shadow:0 8px 30px rgba(31,60,44,.055); background:#fff; }
    .staff-edit-page .staff-card-header { padding:18px 22px; border-bottom:1px solid #edf1ee; border-radius:16px 16px 0 0; background:#fff; }
    .staff-edit-page .staff-card-body { padding:22px; }
    .staff-edit-page .btn-primary { --bs-btn-bg:var(--chabrin-green); --bs-btn-border-color:var(--chabrin-green); --bs-btn-hover-bg:var(--chabrin-green-dark); --bs-btn-hover-border-color:var(--chabrin-green-dark); --bs-btn-active-bg:var(--chabrin-green-dark); --bs-btn-active-border-color:var(--chabrin-green-dark); }
    .staff-edit-page .btn { border-radius:9px; font-weight:650; padding:.58rem .9rem; }
    .staff-edit-page .btn-light { background:#f6f8f7; border:1px solid #e3e9e5; color:#42534a; }
    .staff-edit-page .nav-tabs { display:flex; flex-wrap:nowrap; gap:5px; overflow-x:auto; overflow-y:hidden; border-bottom:1px solid #e5ebe7; padding:0 0 10px; scrollbar-width:thin; }
    .staff-edit-page .nav-tabs .nav-item { flex:0 0 auto; }
    .staff-edit-page .nav-tabs .nav-link { display:flex; align-items:center; white-space:nowrap; border:1px solid transparent; border-radius:9px; color:#69786f; padding:11px 14px; font-size:.86rem; font-weight:650; transition:all .18s ease; }
    .staff-edit-page .nav-tabs .nav-link:hover { background:#f5f8f6; color:var(--chabrin-green); }
    .staff-edit-page .nav-tabs .nav-link.active { color:var(--chabrin-green-dark); background:var(--chabrin-green-soft); border-color:#d5e8dc; }
    .staff-edit-page .nav-tabs .nav-link .material-symbols-outlined { font-size:19px !important; }
    .staff-edit-page .tab-content { padding-top:22px !important; }
    .staff-edit-page .tab-pane > .row { --bs-gutter-x:1.2rem; }
    .staff-edit-page .form-label { color:#34473c; font-size:.83rem; font-weight:700; margin-bottom:7px; }
    .staff-edit-page .form-control, .staff-edit-page .form-select { min-height:43px; border-color:#dce5df; border-radius:9px; color:#2b3b32; background-color:#fff; font-size:.9rem; box-shadow:none; transition:border-color .15s ease,box-shadow .15s ease; }
    .staff-edit-page textarea.form-control { min-height:84px; }
    .staff-edit-page .form-control:focus, .staff-edit-page .form-select:focus { border-color:#75a88b; box-shadow:0 0 0 .22rem rgba(40,118,83,.12); }
    .staff-edit-page .form-control[readonly] { background:#f4f7f5; color:#68786e; }
    .staff-edit-page .text-muted, .staff-edit-page small { color:#7b8981 !important; }
    .staff-edit-page .alert { border-radius:11px; border-width:1px; }
    .staff-edit-page #photo-preview { width:88px; height:88px; border:3px solid #fff !important; box-shadow:0 0 0 1px #dce7df,0 5px 16px rgba(31,60,44,.1); }
    .staff-edit-page .staff-photo-panel { display:flex; align-items:center; gap:18px; padding:16px; border:1px dashed #cbdcd0; border-radius:12px; background:#f8fbf9; }
    .staff-edit-page .staff-document-card { border:1px solid #e2eae5 !important; border-radius:12px; background:#fbfcfb !important; padding:18px !important; transition:border-color .18s ease,transform .18s ease; }
    .staff-edit-page .staff-document-card:hover { border-color:#b9d4c2 !important; transform:translateY(-1px); }
    .staff-edit-page .staff-document-card .form-control { background:#fff; }
    .staff-edit-page .staff-document-card .bg-white { border-color:#e5ebe7 !important; border-radius:9px !important; }

    /* Prevent document names, upload hints and remove controls from colliding. */
    .staff-edit-page #documents .staff-document-card {
        display: flex;
        flex-direction: column;
        min-width: 0;
        height: 100%;
        box-sizing: border-box;
        overflow-wrap: anywhere;
    }
    .staff-edit-page #documents .staff-document-card > .d-flex {
        flex-wrap: wrap;
        align-items: flex-start !important;
        gap: 10px;
        min-width: 0;
    }
    .staff-edit-page #documents .staff-document-card .text-truncate {
        flex: 1 1 180px;
        min-width: 0;
        white-space: normal !important;
        overflow-wrap: anywhere;
        text-overflow: clip;
    }
    .staff-edit-page #documents .staff-document-card .d-flex.gap-1 {
        flex-wrap: wrap;
        margin-left: auto;
    }
    .staff-edit-page #documents .staff-document-card input[type="file"] {
        display: block;
        width: 100%;
        max-width: 100%;
        margin-top: 8px;
        box-sizing: border-box;
    }
    .staff-edit-page #documents .staff-document-card > small,
    .staff-edit-page #documents .staff-document-card .invalid-feedback {
        display: block;
        position: static;
        height: auto;
        margin-top: 7px;
        line-height: 1.5;
        white-space: normal;
        overflow-wrap: anywhere;
    }
    .staff-edit-page #documents .staff-document-card .form-check {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        margin-top: 14px !important;
        min-height: 24px;
    }
    .staff-edit-page #documents .staff-document-card .form-check-input {
        flex: 0 0 auto;
        margin: 3px 0 0;
    }
    .staff-edit-page #documents .staff-document-card .form-check-label {
        line-height: 1.5;
        white-space: normal;
    }
    .staff-edit-page #documents .row.g-3 > [class*="col-"] {
        min-width: 0;
    }
    .staff-edit-page .staff-form-footer {
        position: relative;
        clear: both;
        background: #fff;
    }
    @media (max-width: 575.98px) {
        .staff-edit-page #documents .staff-document-card { padding: 14px !important; }
        .staff-edit-page #documents .staff-document-card .d-flex.gap-1 { margin-left: 0; }
    }
    .staff-edit-page .badge.bg-warning { background:#fff2ce !important; color:#876117 !important; border:1px solid #f3df9e; padding:6px 9px; border-radius:20px; font-weight:700; }
    .staff-edit-page .tab-error-badge { flex:0 0 auto; }
    .staff-edit-page .select2-container--default .select2-selection--multiple,
    .staff-edit-page .select2-container--default .select2-selection--single { border-color:#dce5df; border-radius:9px; min-height:43px; }
    .staff-edit-page .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:32px; }
    .staff-edit-page .select2-container--default .select2-selection--single .select2-selection__arrow { height:41px; }
    .staff-edit-page .select2-container--default.select2-container--focus .select2-selection--multiple,
    .staff-edit-page .select2-container--default.select2-container--open .select2-selection--single { border-color:#75a88b; box-shadow:0 0 0 .22rem rgba(40,118,83,.12); }
    .staff-edit-page .select2-container--default .select2-selection--multiple .select2-selection__choice { background:#eaf4ee; border-color:#d0e5d7; color:#245d3f; border-radius:6px; }
    .staff-edit-page .staff-form-footer { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; border-top:1px solid #edf1ee; margin-top:24px; padding-top:18px; }
    .staff-edit-page .staff-footer-note { display:flex; align-items:center; gap:7px; color:#7b8981; font-size:.82rem; }
    .staff-edit-page .staff-footer-note .material-symbols-outlined { font-size:18px; }
    @media (max-width: 767.98px) { .staff-edit-page { padding-top:14px; } .staff-edit-page .staff-card-body { padding:15px; } .staff-edit-page .staff-card-header { padding:15px; } .staff-edit-page .nav-tabs .nav-link { padding:9px 11px; } .staff-edit-page .staff-photo-panel { align-items:flex-start; flex-direction:column; } .staff-edit-page .staff-form-footer { align-items:stretch; } .staff-edit-page .staff-form-footer .btn { width:100%; justify-content:center; } }
</style>
<div class="page-body staff-onboarding staff-edit-page">
    <div class="container-fluid">
        <div class="staff-edit-heading">
            <div>
                <div class="staff-edit-eyebrow">Staff Management</div>
                <h1 class="staff-edit-title">Edit Staff Profile</h1>
                <p class="staff-edit-subtitle">Update employee information, employment details and records.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('staff.show', \Illuminate\Support\Facades\Crypt::encrypt($staff->id)) }}" class="btn btn-light d-inline-flex align-items-center">
                    <span class="material-symbols-outlined me-1" style="font-size:19px;">arrow_back</span>
                    Back to Details
                </a>
                <button type="submit" form="staffEditForm" class="btn btn-primary d-inline-flex align-items-center" id="updateButton">
                    <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                    <span class="material-symbols-outlined me-1" style="font-size:19px;">save</span>
                    <span>Save Changes</span>
                </button>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <div class="card staff-main-card">
                    <div class="card-header staff-card-header d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:46px;height:46px;background:#eaf4ee;color:#287653;">
                            <span class="material-symbols-outlined" style="font-size:25px;">badge</span>
                        </div>
                        <div class="min-w-0">
                            <h5 class="mb-1 fw-bold" style="color:#24352d;">{{ $staff->full_names }}</h5>
                            <div class="small text-muted">Staff No. {{ $staff->staff_number }} <span class="mx-1">•</span> Review and maintain this employee's records</div>
                        </div>
                    </div>
                    <div class="card-body staff-card-body">
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

                        <form action="{{ route('staff.update', $staff->id) }}" method="POST" id="staffEditForm" enctype="multipart/form-data" novalidate>
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
                                    <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab" aria-controls="documents" aria-selected="false">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">upload_file</span>
                                        Documents
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
                                        <div class="col-md-4 mb-3">
                                            <label for="email_address" class="form-label">Official Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control @error('email_address') is-invalid @enderror" id="email_address" name="email_address" value="{{ old('email_address', $staff->email_address) }}" @if($staff->email_address) readonly @endif data-required="true">
                                            @error('email_address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-12 mb-3">
                                            @php
                                                $currentPhoto = ($staff->photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($staff->photo))
                                                    ? asset('storage/' . $staff->photo)
                                                    : asset('images/default-avatar.png');
                                            @endphp
                                            <div class="staff-photo-panel">
                                                <div class="col-auto p-0">
                                                    <img id="photo-preview" src="{{ $currentPhoto }}" class="rounded-circle border" width="90" height="90" style="object-fit: cover;">
                                                </div>
                                                <div class="col-md-5">
                                                    <label for="photo-input" class="form-label fw-semibold small mb-1">Staff Profile Photo</label>
                                                    <input type="file" name="photo" id="photo-input" class="form-control form-control-sm @error('photo') is-invalid @enderror" accept="image/png,image/jpeg">
                                                    <small class="text-muted">Square ratio (JPG, PNG), max 2MB. Choose a file only to replace the current photo.</small>
                                                    @error('photo')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
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
                                        <div class="row col-md-12 mb-3">
                                            <div class="col-md-4 mb-3 zone-role-field" id="zone_field_group" style="display:none;">
                                                <label for="zone_id" class="form-label">Zone</label>
                                                <select class="form-select @error('zone_id') is-invalid @enderror" id="zone_id" name="zone_id">
                                                    <option value="">Select Zone</option>
                                                    @foreach($zones as $zone)
                                                    <option value="{{ $zone->id }}" {{ old('zone_id', $staff->zone_id) == $zone->id ? 'selected' : '' }}>
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
                                            <div class="col-md-4 mb-3 zone-role-field" id="zone_supervised_group" style="display:none;">
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

                                <!-- Documents Tab -->
                                <div class="tab-pane fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
                                    @php $accept = ['contract' => '.pdf', 'cv' => '.pdf,.doc,.docx']; @endphp
                                    <div class="pt-3 pb-2 mb-3 border-bottom">
                                        <h6 class="fw-bold mb-1">Staff Documentation & Verification</h6>
                                        <p class="text-muted small mb-0">Upload missing documents, replace existing ones, or remove them. Changes are saved when you click <strong>Update Staff</strong> (max 5MB per file).</p>
                                    </div>

                                    <div class="row g-3">
                                        @foreach($documentSlots as $slot)
                                        @php $doc = $slot['doc']; $key = $slot['key']; @endphp
                                        <div class="col-md-6">
                                            <div class="staff-document-card h-100">
                                                <label class="form-label fw-semibold small mb-1">{{ $slot['label'] }}</label>

                                                @if($doc)
                                                <div class="d-flex align-items-center justify-content-between mb-2 p-2 bg-white border rounded">
                                                    <div class="text-truncate me-2" style="font-size:0.85rem;" title="{{ $doc->file_name }}">
                                                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">description</span>
                                                        {{ $doc->file_name }}
                                                    </div>
                                                    <div class="d-flex gap-1 flex-shrink-0">
                                                        <a href="{{ route('documents.view', $doc->id) }}" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                                                        <a href="{{ route('documents.download', $doc->id) }}" class="btn btn-sm btn-outline-success">Download</a>
                                                    </div>
                                                </div>
                                                @else
                                                <div class="mb-2"><span class="badge bg-warning text-dark">Not uploaded</span></div>
                                                @endif

                                                <input type="file" name="documents[{{ $key }}]"
                                                    class="form-control form-control-sm @error('documents.'.$key) is-invalid @enderror"
                                                    accept="{{ $accept[$key] ?? '.pdf,.png,.jpg,.jpeg' }}">
                                                <small class="text-muted">{{ $doc ? 'Choose a file only if you want to replace the current one.' : 'Choose a file to upload.' }}</small>
                                                @error('documents.'.$key)
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror

                                                @if($doc)
                                                <div class="form-check mt-2">
                                                    <input class="form-check-input" type="checkbox" name="remove_documents[]" value="{{ $key }}" id="remove_{{ $key }}">
                                                    <label class="form-check-label small text-danger" for="remove_{{ $key }}">Remove this document</label>
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Banking & Statutory Tab -->
                                <div class="tab-pane fade" id="banking" role="tabpanel" aria-labelledby="banking-tab">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="bank_name" class="form-label">Bank <span class="text-danger">*</span></label>
                                            <select class="form-select @error('bank_name') is-invalid @enderror" id="bank_name" name="bank_name" data-required="true">
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
                                            <label for="account_name" class="form-label">Account Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('account_name') is-invalid @enderror" id="account_name" name="account_name" value="{{ old('account_name', $staff->account_name) }}" data-required="true">
                                            @error('account_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="account_number" class="form-label">Account Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('account_number') is-invalid @enderror" id="account_number" name="account_number" value="{{ old('account_number', $staff->account_number) }}" data-required="true">
                                            @error('account_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="pin_number" class="form-label">KRA PIN Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('pin_number') is-invalid @enderror" id="pin_number" name="pin_number" value="{{ old('pin_number', $staff->pin_number) }}" data-required="true">
                                            @error('pin_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="nhif_number" class="form-label">SHA Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('nhif_number') is-invalid @enderror" id="nhif_number" name="nhif_number" value="{{ old('nhif_number', $staff->nhif_number) }}" data-required="true">
                                            @error('nhif_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="nssf_number" class="form-label">NSSF Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('nssf_number') is-invalid @enderror" id="nssf_number" name="nssf_number" value="{{ old('nssf_number', $staff->nssf_number) }}" data-required="true">
                                            @error('nssf_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="staff-form-footer">
                                <div class="staff-footer-note"><span class="material-symbols-outlined">info</span> Changes are saved when you select Save Changes.</div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="{{ route('staff.show', \Illuminate\Support\Facades\Crypt::encrypt($staff->id)) }}" class="btn btn-light">Cancel</a>
                                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center" id="updateButtonFooter">
                                        <span class="material-symbols-outlined me-1" style="font-size:19px;">save</span> Save Changes
                                    </button>
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

    // Account Name follows Full Names unless it has been changed to something else
    var fullNameInput = document.getElementById('full_names');
    var accountNameInput = document.getElementById('account_name');
    if (fullNameInput && accountNameInput) {
        if (accountNameInput.value.trim() === '') accountNameInput.value = fullNameInput.value;
        var lastFullName = fullNameInput.value;
        fullNameInput.addEventListener('input', function () {
            if (accountNameInput.value.trim() === '' || accountNameInput.value.toUpperCase() === lastFullName.toUpperCase()) {
                accountNameInput.value = this.value;
            }
            lastFullName = this.value;
        });
    }

    var TABS = [
        { pane: 'personal',   btn: 'personal-tab',   label: 'Personal Information' },
        { pane: 'employment', btn: 'employment-tab',  label: 'Employment Details'   },
        { pane: 'contact',    btn: 'contact-tab',     label: 'Contact Information'  },
        { pane: 'documents',  btn: 'documents-tab',   label: 'Documents'            },
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

        ['updateButton', 'updateButtonFooter'].forEach(function (id) {
            var updateBtn = document.getElementById(id);
            if (!updateBtn) return;
            updateBtn.classList.add('btn-shake');
            updateBtn.addEventListener('animationend', function () { updateBtn.classList.remove('btn-shake'); }, { once: true });
        });
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
        ['updateButton', 'updateButtonFooter'].forEach(function (id) {
            var btn = document.getElementById(id);
            if (btn) { btn.disabled = true; btn.style.opacity = '0.65'; }
        });
        var spinner = document.getElementById('submitSpinner');
        if (spinner) spinner.classList.remove('d-none');
    });

    var zoneSelect = document.getElementById('zone_id');
    if (zoneSelect) zoneSelect.addEventListener('change', function () {
        var zoneId = this.value;
        var zoneArea = document.getElementById('zone_area_id');
        // Some staff forms do not render a zone-area selector. Avoid stopping the rest of this script.
        if (!zoneArea) return;
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

    // Show only the zone field relevant to the selected position. Select2 is initialized
    // lazily, the first time each group becomes visible — initializing it while the group
    // is still display:none gives it a broken (zero) width.
    var positionSlugs = @json($positions->pluck('slug_name', 'id'));
    var select2Initialized = {};
    function ensureSelect2(selectId, options) {
        if (select2Initialized[selectId]) return;
        select2Initialized[selectId] = true;
        $(selectId).select2(options);
    }
    function toggleZoneFields() {
        var slug = positionSlugs[document.getElementById('position_id').value] || null;

        var showZone = slug === 'field_officer';
        $('#zone_field_group').toggle(showZone);
        if (showZone) ensureSelect2('#zone_id', { placeholder: 'Select Zone', width: '100%', allowClear: true });

        var showManaged = slug === 'zone_manager';
        $('#zone_managed_group').toggle(showManaged);
        if (showManaged) ensureSelect2('#managed_zone_ids', { placeholder: 'Select zones managed', width: '100%' });

        var showSupervised = slug === 'zone_supervisor';
        $('#zone_supervised_group').toggle(showSupervised);
        if (showSupervised) ensureSelect2('#supervised_zone_ids', { placeholder: 'Select zones supervised', width: '100%' });
    }
    document.getElementById('position_id').addEventListener('change', toggleZoneFields);
    toggleZoneFields();

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

    // Live preview of the selected staff photo
    var photoInput = document.getElementById('photo-input');
    if (photoInput) photoInput.addEventListener('change', function (e) {
        if (e.target.files && e.target.files[0]) {
            var reader = new FileReader();
            reader.onload = function (evt) { document.getElementById('photo-preview').src = evt.target.result; };
            reader.readAsDataURL(e.target.files[0]);
        }
    });

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