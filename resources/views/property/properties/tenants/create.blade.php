
@extends('layouts.main')
@section('content')
<style>
    /* Individual is the default selection, so hide Company-only fields until
       toggleTenantTypeFields() runs (or immediately, if old('tenant_type') was 3). */
    .company-only { display: none; }

    /* Move-in Inspection accordion — same condensed look as the Permissions
       accordion (resources/views/roles/permissionsaccess.blade.php). */
    .condensed-accordion .accordion-item {
        border: 1px solid #e9ecef;
        border-radius: 4px;
        background: white;
    }

    .condensed-accordion .accordion-header {
        display: flex;
        align-items: center;
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        border-radius: 4px 4px 0 0;
    }

    .condensed-accordion .accordion-button {
        padding: 6px 10px;
        background-color: transparent;
        box-shadow: none;
    }

    .condensed-accordion .accordion-button:not(.collapsed) {
        background-color: transparent;
        color: #495057;
        box-shadow: none;
    }

    .condensed-accordion .accordion-button:focus {
        box-shadow: none;
        border-color: transparent;
    }

    .condensed-accordion .accordion-button::after {
        display: none;
    }

    .condensed-accordion .accordion-arrow {
        margin-left: auto;
        color: #6c757d;
        font-size: 20px;
        transition: transform 0.2s ease-in-out;
    }

    .condensed-accordion .accordion-button:not(.collapsed) .accordion-arrow {
        transform: rotate(180deg);
    }

    .condensed-accordion .accordion-body {
        padding: 10px 12px;
    }
</style>
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Add New Tenant</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="unitTenantForm" class="btn btn-primary btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Save</span>
                            </button>
                            <a href="{{ $client ? route('property.clients.show', $client->encrypted_id) : route('property.properties.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back</span>
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

                        <form action="{{ route('units.tenants.store') }}" method="POST" id="unitTenantForm" enctype="multipart/form-data">
                            @csrf

                            <!-- Hidden client ID field -->
                            <input type="hidden" name="client_id" value="{{ $client->id ?? old('client_id') }}">

                            <!-- Nav Tabs -->
                            <ul class="nav nav-tabs" id="unitTenantTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="unit-details-tab" data-bs-toggle="tab" data-bs-target="#unit-details" type="button" role="tab" aria-controls="unit-details" aria-selected="true">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">home</span>
                                        Unit Details
                                        <span id="inspectionBadge" class="badge bg-secondary ms-1" style="font-size:0.65rem;display:none;">Optional</span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="tenants-tab" data-bs-toggle="tab" data-bs-target="#tenants" type="button" role="tab" aria-controls="tenants" aria-selected="false">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">person</span>
                                        Tenants
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="bank-details-tab" data-bs-toggle="tab" data-bs-target="#bank-details" type="button" role="tab" aria-controls="bank-details" aria-selected="false">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">account_balance</span>
                                        Bank Details
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="other-contacts-tab" data-bs-toggle="tab" data-bs-target="#other-contacts" type="button" role="tab" aria-controls="other-contacts" aria-selected="false">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">contact_phone</span>
                                        Other Contacts
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="tenant-files-tab" data-bs-toggle="tab" data-bs-target="#tenant-files" type="button" role="tab" aria-controls="tenant-files" aria-selected="false">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">folder</span>
                                        Tenants Files
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="next-of-kin-tab" data-bs-toggle="tab" data-bs-target="#next-of-kin" type="button" role="tab" aria-controls="next-of-kin" aria-selected="false">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">family_restroom</span>
                                        Next of Kin
                                    </button>
                                </li>
                            </ul>
                            <input type="hidden" name="inspection_required" id="inspectionRequired" value="0">

                            <!-- Tab Content -->
                            <div class="tab-content mt-3" id="unitTenantTabsContent">
                                <!-- Unit Details Tab -->
                                <div class="tab-pane fade show active" id="unit-details" role="tabpanel" aria-labelledby="unit-details-tab">
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <h6 class="text-muted border-bottom pb-2">Unit Information</h6>
                                        </div>

                                        @if(!$client)
                                        {{-- Landlord search — only shown when not coming from a client profile --}}
                                        <div class="col-md-4 mb-3" id="landlordSearchWrapper">
                                            <label class="form-label">Landlord <span class="text-danger">*</span></label>
                                            <div class="position-relative">
                                                <input type="text" class="form-control" id="landlordSearch"
                                                       placeholder="Search by name, ref or mobile..."
                                                       autocomplete="off">
                                                <div id="landlordResults"
                                                     class="list-group shadow position-absolute w-100"
                                                     style="z-index:1050;top:100%;left:0;display:none;max-height:240px;overflow-y:auto;background:#fff;border:1px solid rgba(0,0,0,.15);border-radius:4px;"></div>
                                            </div>
                                            <div id="landlordSelected" class="mt-1" style="display:none;">
                                                <span class="badge bg-primary fs-6 fw-normal" id="landlordSelectedName"></span>
                                                <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1" id="clearLandlord">clear</button>
                                            </div>
                                            <input type="hidden" id="selectedLandlordId">
                                        </div>
                                        @endif

                                        <div class="col-md-4 mb-3">
                                            <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                            <select class="form-select @error('property_id') is-invalid @enderror" id="property_id" name="property_id" required
                                                    @if(!$client) disabled @endif>
                                                <option value="">@if(!$client) Select a landlord first @else Loading... @endif</option>
                                            </select>
                                            @error('property_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="unit_id" class="form-label">Unit <span class="text-danger">*</span></label>
                                            <select class="form-select @error('unit_id') is-invalid @enderror" id="unit_id" name="unit_id" required>
                                                <option value="">Please select</option>
                                                
                                            </select>
                                            @error('unit_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="lease_start_date" class="form-label">Lease Start Date  <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control @error('lease_start_date') is-invalid @enderror" 
                                                   id="lease_start_date" name="lease_start_date" required> 
                                            @error('lease_start_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="lease_years" class="form-label">Lease Years</label>
                                            <input type="number" step="1" class="form-control @error('lease_years') is-invalid @enderror" 
                                                   id="lease_years" name="lease_years" 
                                                   value="{{ old('lease_years', '1') }}">
                                            @error('lease_years')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="escalation_rate" class="form-label">Escalation Rate(%)</label>
                                            <input type="number" step="0.01" class="form-control @error('escalation_rate') is-invalid @enderror" 
                                                   id="escalation_rate" name="escalation_rate" 
                                                   value="{{ old('escalation_rate', '0') }}" placeholder="0">
                                            @error('escalation_rate')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="frequency" class="form-label">Frequency</label>
                                            <select class="form-select @error('frequency') is-invalid @enderror" id="frequency" name="frequency">
                                                <option value="Annually" {{ old('frequency') == 'Annually' ? 'selected' : '' }}>Annually</option>
                                                <option value="Monthly" {{ old('frequency') == 'Monthly' ? 'selected' : '' }}>Monthly</option>
                                                <option value="Quarterly" {{ old('frequency') == 'Quarterly' ? 'selected' : '' }}>Quarterly</option>
                                                <option value="Biennially" {{ old('frequency') == 'Biennially' ? 'selected' : '' }}>Biennially</option>
                                                <option value="Semi-Annually" {{ old('frequency') == 'Semi-Annually' ? 'selected' : '' }}>Semi-Annually</option>
                                            </select>
                                            @error('frequency')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="address_2" class="form-label">Address 2</label>
                                            <input type="text" class="form-control @error('address_2') is-invalid @enderror" 
                                                   id="address_2" name="address_2" 
                                                   value="{{ old('address_2') }}">
                                            @error('address_2')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="address_3" class="form-label">Address 3</label>
                                            <input type="text" class="form-control @error('address_3') is-invalid @enderror" 
                                                   id="address_3" name="address_3" 
                                                   value="{{ old('address_3') }}">
                                            @error('address_3')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="deposit_months" class="form-label">Deposit — Number of Months</label>
                                            <input type="number" step="0.5" min="0" class="form-control @error('deposit_months') is-invalid @enderror"
                                                   id="deposit_months" name="deposit_months"
                                                   value="{{ old('deposit_months') }}" placeholder="e.g. 2">
                                            @error('deposit_months')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="deposit_amount_preview" class="form-label">Deposit Amount</label>
                                            <input type="text" class="form-control" id="deposit_amount_preview" readonly
                                                   placeholder="Select a unit and enter months">
                                            <div class="form-text text-muted" style="font-size:0.75rem;">Unit rent × months — computed automatically, not editable. The amount actually recorded is always computed on the server the same way.</div>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="deposit_held_by" class="form-label">Deposit Held By</label>
                                            <select class="form-select @error('deposit_held_by') is-invalid @enderror"
                                                    id="deposit_held_by" name="deposit_held_by">
                                                <option value="">Select if deposit entered</option>
                                                <option value="Company" {{ old('deposit_held_by') == 'Company' ? 'selected' : '' }}>Chabrin (Company)</option>
                                                <option value="Landlord" {{ old('deposit_held_by') == 'Landlord' ? 'selected' : '' }}>Landlord</option>
                                            </select>
                                            <div class="form-text text-muted" style="font-size:0.75rem;">Who actually holds this deposit — required whenever a number of months is entered, to avoid disputes at move-out.</div>
                                            @error('deposit_held_by')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        {{-- Move-in Inspection Section (inline in Unit Details tab), collapsed by
                                             default like the Permissions accordion (roles/permissionsaccess.blade.php) --}}
                                        <div class="col-12 mt-2 mb-3">
                                            <div class="accordion condensed-accordion" id="moveInInspectionAccordion">
                                                <div class="accordion-item">
                                                    <h2 class="accordion-header">
                                                        <button class="accordion-button collapsed" type="button"
                                                                data-bs-toggle="collapse" data-bs-target="#moveInInspectionCollapse"
                                                                aria-expanded="false" aria-controls="moveInInspectionCollapse">
                                                            <span class="material-symbols-outlined me-2">search_check</span>
                                                            Move-in Inspection
                                                            <span class="material-symbols-outlined accordion-arrow">expand_more</span>
                                                        </button>
                                                    </h2>
                                                    <div id="moveInInspectionCollapse" class="accordion-collapse collapse" data-bs-parent="#moveInInspectionAccordion">
                                                        <div class="accordion-body" id="inspectionSection">
                                                            <div id="inspectionRequiredAlert" class="alert alert-warning d-none" role="alert" style="font-size:0.83rem;">
                                                                <span class="material-symbols-outlined me-1" style="font-size:16px;vertical-align:middle;">warning</span>
                                                                A move-in inspection is <strong>mandatory</strong> for this property. Please complete the form below.
                                                            </div>
                                                            <div id="inspectionOptionalAlert" class="alert alert-info d-none" role="alert" style="font-size:0.83rem;">
                                                                <span class="material-symbols-outlined me-1" style="font-size:16px;vertical-align:middle;">info</span>
                                                                A move-in inspection is <strong>optional</strong> for this property. You may complete it now or skip.
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-4 mb-3">
                                                                    <label for="inspection_date" class="form-label">Inspection Date <span id="inspectionDateRequired" class="text-danger" style="display:none;">*</span></label>
                                                                    <input type="date" class="form-control" id="inspection_date" name="inspection_date">
                                                                </div>
                                                                <div class="col-md-4 mb-3">
                                                                    <label for="inspector_name" class="form-label">Inspector Name</label>
                                                                    <input type="text" class="form-control" id="inspector_name" name="inspector_name" placeholder="Name of inspector">
                                                                </div>
                                                                <div class="col-md-4 mb-3">
                                                                    <label for="overall_condition" class="form-label">Overall Condition</label>
                                                                    <select class="form-select" id="overall_condition" name="overall_condition">
                                                                        <option value="">— Select —</option>
                                                                        <option value="Excellent">Excellent</option>
                                                                        <option value="Good">Good</option>
                                                                        <option value="Fair">Fair</option>
                                                                        <option value="Poor">Poor</option>
                                                                    </select>
                                                                </div>

                                                                @foreach([['walls','Walls'],['floors','Floors'],['ceiling','Ceiling'],['doors','Doors'],['windows','Windows'],['plumbing','Plumbing'],['electrical','Electrical']] as [$key, $label])
                                                                <div class="col-md-3 mb-3">
                                                                    <label class="form-label">{{ $label }}</label>
                                                                    <select class="form-select" name="{{ $key }}_condition">
                                                                        <option value="">— Select —</option>
                                                                        <option value="Excellent">Excellent</option>
                                                                        <option value="Good">Good</option>
                                                                        <option value="Fair">Fair</option>
                                                                        <option value="Poor">Poor</option>
                                                                    </select>
                                                                </div>
                                                                @endforeach

                                                                <div class="col-12 mb-3">
                                                                    <label for="inspection_notes" class="form-label">Notes</label>
                                                                    <textarea class="form-control" id="inspection_notes" name="inspection_notes" rows="3" placeholder="Any observations or notes about the unit condition..."></textarea>
                                                                </div>

                                                                <div class="col-12 mb-1">
                                                                    <hr class="mt-0 mb-2">
                                                                    <p class="text-muted mb-2" style="font-size:0.8rem;">
                                                                        <span class="material-symbols-outlined align-middle" style="font-size:15px;">photo_camera</span>
                                                                        Upload photos and/or videos documenting the unit condition at move-in.
                                                                    </p>
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Photos</label>
                                                                    <input type="file" name="inspection_photos[]" class="form-control"
                                                                           accept="image/jpeg,image/png,image/webp,image/heic,image/heif"
                                                                           multiple id="moveInPhotos">
                                                                    <div class="form-text" style="font-size:0.72rem;">JPG, PNG, WEBP, HEIC — multiple allowed</div>
                                                                    <div id="moveInPhotoPreview" class="d-flex flex-wrap gap-1 mt-2"></div>
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Videos</label>
                                                                    <input type="file" name="inspection_videos[]" class="form-control"
                                                                           accept="video/mp4,video/quicktime,video/x-msvideo,video/x-matroska"
                                                                           multiple id="moveInVideos">
                                                                    <div class="form-text" style="font-size:0.72rem;">MP4, MOV, AVI, MKV — multiple allowed</div>
                                                                    <div id="moveInVideoPreview" class="d-flex flex-wrap gap-1 mt-2"></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <!-- Tenants Tab -->
                                <div class="tab-pane fade" id="tenants" role="tabpanel" aria-labelledby="tenants-tab">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="tenant_type" class="form-label">Tenant Type <span class="text-danger">*</span></label>
                                            <select class="form-select @error('tenant_type') is-invalid @enderror" id="tenant_type" name="tenant_type" required>
                                                <option value="1" {{ old('tenant_type', '1') == '1' ? 'selected' : '' }}>Individual</option>
                                                <option value="3" {{ old('tenant_type') == '3' ? 'selected' : '' }}>Company</option>
                                            </select>
                                            @error('tenant_type')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <!-- Company Information Section -->
                                        <div class="col-12 mb-3 mt-3 company-only" id="tenantCompanyHeading">
                                            <h6 class="text-muted border-bottom pb-2">Company Information</h6>
                                        </div>

                                        <div class="col-md-6 mb-3 company-only">
                                            <label for="company_name" class="form-label">Company Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('company_name') is-invalid @enderror" id="company_name" name="company_name" value="{{ old('company_name') }}" placeholder="Enter company name">
                                            @error('company_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-6 mb-3 company-only">
                                            <label for="company_registration_number" class="form-label">Registration Number</label>
                                            <input type="text" class="form-control @error('company_registration_number') is-invalid @enderror" id="company_registration_number" name="company_registration_number" value="{{ old('company_registration_number') }}" placeholder="Enter certificate of incorporation / registration number">
                                            @error('company_registration_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="text-danger d-none duplicate-check-message" id="company_registration_number_duplicate"></small>
                                        </div>

                                        <!-- Contact Person Section (Company only) -->
                                        <div class="col-12 mb-3 mt-3 company-only" id="tenantContactPersonHeading">
                                            <h6 class="text-muted border-bottom pb-2">Contact Person</h6>
                                            <small class="text-muted">The individual who deals with this account day-to-day</small>
                                        </div>

                                        <div class="col-md-3 mb-3 company-only">
                                            <label for="contact_person_name" class="form-label">Contact Person Name</label>
                                            <input type="text" class="form-control @error('contact_person_name') is-invalid @enderror" id="contact_person_name" name="contact_person_name" value="{{ old('contact_person_name') }}" placeholder="Enter contact person's name">
                                            @error('contact_person_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3 company-only">
                                            <label for="contact_person_title" class="form-label">Position / Title</label>
                                            <input type="text" class="form-control @error('contact_person_title') is-invalid @enderror" id="contact_person_title" name="contact_person_title" value="{{ old('contact_person_title') }}" placeholder="e.g. Director, Office Manager">
                                            @error('contact_person_title')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3 company-only">
                                            <label for="contact_person_mobile" class="form-label">Contact Person Mobile</label>
                                            <input type="text" class="form-control @error('contact_person_mobile') is-invalid @enderror" id="contact_person_mobile" name="contact_person_mobile" value="{{ old('contact_person_mobile') }}" placeholder="254XXXXXXXXX">
                                            @error('contact_person_mobile')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3 company-only">
                                            <label for="contact_person_email" class="form-label">Contact Person Email</label>
                                            <input type="email" class="form-control @error('contact_person_email') is-invalid @enderror" id="contact_person_email" name="contact_person_email" value="{{ old('contact_person_email') }}" placeholder="name@example.com">
                                            @error('contact_person_email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <!-- Personal Information Section -->
                                        <div class="col-12 mb-3 mt-3 individual-only">
                                            <h6 class="text-muted border-bottom pb-2">Personal Information</h6>
                                        </div>

                                        <div class="col-md-3 mb-3 individual-only">
                                            <label for="title" class="form-label">Title</label>
                                            <select class="form-select @error('title') is-invalid @enderror" id="title" name="title">
                                                <option value="">Select</option>
                                                <option value="Mr" {{ old('title') == 'Mr' ? 'selected' : '' }}>Mr</option>
                                                <option value="Mrs" {{ old('title') == 'Mrs' ? 'selected' : '' }}>Mrs</option>
                                                <option value="Ms" {{ old('title') == 'Ms' ? 'selected' : '' }}>Ms</option>
                                                <option value="Dr" {{ old('title') == 'Dr' ? 'selected' : '' }}>Dr</option>
                                                <option value="Prof" {{ old('title') == 'Prof' ? 'selected' : '' }}>Prof</option>
                                            </select>
                                            @error('title')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-6 mb-3 individual-only" id="tenantIndividualNameField">
                                            <label for="names" class="form-label">Names <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('names') is-invalid @enderror"
                                                   id="names" name="names"
                                                   value="{{ old('names') }}" placeholder="Enter full name">
                                            @error('names')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3 individual-only">
                                            <label for="gender" class="form-label">Gender</label>
                                            <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender">
                                                <option value="">Please select</option>
                                                <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                                                <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                                                <option value="Other" {{ old('gender') == 'Other' ? 'selected' : '' }}>Other</option>
                                            </select>
                                            @error('gender')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="phone" class="form-label">Phone</label>
                                            <input type="text" class="form-control @error('phone') is-invalid @enderror"
                                                   id="phone" name="phone"
                                                   value="{{ old('phone') }}" placeholder="e.g., +254712345678">
                                            @error('phone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="mobile_number" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('mobile_number') is-invalid @enderror" 
                                                   id="mobile_number" name="mobile_number" 
                                                   value="{{ old('mobile_number') }}" required>
                                            @error('mobile_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="email_address" class="form-label">Email Address</label>
                                            <input type="email" class="form-control @error('email_address') is-invalid @enderror"
                                                   id="email_address" name="email_address"
                                                   value="{{ old('email_address') }}">
                                            @error('email_address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="pin_number" class="form-label">PIN Number</label>
                                            <input type="text" class="form-control @error('pin_number') is-invalid @enderror"
                                                   id="pin_number" name="pin_number"
                                                   value="{{ old('pin_number') }}">
                                            @error('pin_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="registered_date" class="form-label">Registered Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control @error('registered_date') is-invalid @enderror"
                                                   id="registered_date" name="registered_date"
                                                   value="{{ old('registered_date', now()->format('Y-m-d')) }}" required>
                                            @error('registered_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3 individual-only">
                                            <label for="nationality_id" class="form-label">Nationality <span class="text-danger">*</span></label>
                                            <select class="form-select @error('nationality_id') is-invalid @enderror" id="nationality_id" name="nationality_id">
                                                <option value="">Please select</option>
                                                @foreach($countries as $country)
                                                    <option value="{{ $country->id }}" {{ old('nationality_id') == $country->id ? 'selected' : '' }}>{{ $country->country_name }}</option>
                                                @endforeach
                                            </select>
                                            @error('nationality_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="tenant_photo" class="form-label">Choose File (No ...ent)</label>
                                            <input type="file" class="form-control @error('tenant_photo') is-invalid @enderror"
                                                   id="tenant_photo" name="tenant_photo" accept="image/*">
                                            @error('tenant_photo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3 individual-only">
                                            <label for="passport_number" class="form-label">Passport Number</label>
                                            <input type="text" class="form-control @error('passport_number') is-invalid @enderror"
                                                   id="passport_number" name="passport_number"
                                                   value="{{ old('passport_number') }}">
                                            @error('passport_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3 individual-only">
                                            <label for="national_id" class="form-label">National ID <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('national_id') is-invalid @enderror"
                                                   id="national_id" name="national_id"
                                                   value="{{ old('national_id') }}">
                                            @error('national_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="text-danger d-none duplicate-check-message" id="national_id_duplicate"></small>
                                        </div>

                                        <div class="col-md-12 mb-3">
                                            <label for="address" class="form-label">Address</label>
                                            <textarea class="form-control @error('address') is-invalid @enderror" 
                                                      id="address" name="address" rows="4">{{ old('address') }}</textarea>
                                            @error('address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        
                                    </div>
                                </div>

                                <!-- Bank Details Tab -->
                                <div class="tab-pane fade" id="bank-details" role="tabpanel" aria-labelledby="bank-details-tab">
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <h6 class="text-muted border-bottom pb-2">Bank Account Information</h6>
                                        </div>

                                        <div id="bankAccountsContainer">
                                            <div class="bank-account-entry mb-3 p-3 border rounded">
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label for="bank_id_0" class="form-label">Bank</label>
                                                        <select class="form-select bank-select" id="bank_id_0" name="bank_accounts[0][bank_id]" data-index="0">
                                                            <option value="">Please select</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-6 mb-3">
                                                        <label for="branch_id_0" class="form-label">Branch</label>
                                                        <select class="form-select branch-select" id="branch_id_0" name="bank_accounts[0][branch_id]" data-index="0">
                                                            <option value="">Please select</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-6 mb-3">
                                                        <label for="account_name_0" class="form-label">Account Name</label>
                                                        <input type="text" class="form-control" id="account_name_0" name="bank_accounts[0][account_name]">
                                                    </div>

                                                    <div class="col-md-6 mb-3">
                                                        <label for="account_number_0" class="form-label">Account Number</label>
                                                        <input type="text" class="form-control" id="account_number_0" name="bank_accounts[0][account_number]">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Other Contacts Tab -->
                                <div class="tab-pane fade" id="other-contacts" role="tabpanel" aria-labelledby="other-contacts-tab">
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <h6 class="text-muted border-bottom pb-2">Additional Contact Information</h6>
                                        </div>

                                        <div class="col-12 mb-3">
                                            <button type="button" class="btn btn-primary btn-sm" id="addOtherContact">
                                                <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                                Add Contact
                                            </button>
                                        </div>

                                        <div id="otherContactsContainer">
                                            <div class="other-contact-entry mb-3 p-3 border rounded">
                                                <div class="row">
                                                    <div class="col-md-3 mb-3">
                                                        <label for="contact_name_0" class="form-label">Names</label>
                                                        <input type="text" class="form-control" id="contact_name_0" name="other_contacts[0][name]">
                                                    </div>

                                                    <div class="col-md-3 mb-3">
                                                        <label for="contact_email_0" class="form-label">Email</label>
                                                        <input type="email" class="form-control" id="contact_email_0" name="other_contacts[0][email]">
                                                    </div>

                                                    <div class="col-md-4 mb-3">
                                                        <label for="contact_mobile_0" class="form-label">Mobile Number</label>
                                                        <input type="text" class="form-control" id="contact_mobile_0" name="other_contacts[0][mobile]">
                                                    </div>

                                                    <div class="col-md-2 mb-3 d-flex align-items-end gap-1">
                                                        <button type="button" class="btn btn-primary btn-sm add-contact-inline d-none">
                                                            <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-primary btn-sm remove-other-contact d-none">
                                                            <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tenants Files Tab -->
                                <div class="tab-pane fade" id="tenant-files" role="tabpanel" aria-labelledby="tenant-files-tab">
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <h6 class="text-muted border-bottom pb-2">Upload Tenant Documents</h6>
                                        </div>

                                        <div class="col-12 mb-3">
                                            <button type="button" class="btn btn-primary btn-sm" id="addTenantFile">
                                                <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                                Add Document
                                            </button>
                                        </div>

                                        <div id="tenantFilesContainer">
                                            <div class="tenant-file-entry mb-3 p-3 border rounded">
                                                <div class="row">
                                                    <div class="col-md-4 mb-3">
                                                        <label for="file_type_0" class="form-label">Document Type</label>
                                                        <select class="form-select" id="file_type_0" name="tenant_files[0][type]">
                                                            <option value="">Please select</option>
                                                            <option value="National ID">National ID</option>
                                                            <option value="Passport">Passport</option>
                                                            <option value="Contract">Contract</option>
                                                            <option value="Lease Agreement">Lease Agreement</option>
                                                            <option value="Other">Other</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-6 mb-3">
                                                        <label for="file_upload_0" class="form-label">Documents</label>
                                                        <input type="file" class="form-control" id="file_upload_0" name="tenant_files[0][file]" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                                    </div>

                                                    <div class="col-md-2 mb-3 d-flex align-items-end">
                                                        <button type="button" class="btn btn-outline-primary btn-sm remove-tenant-file d-none">
                                                            <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Next of Kin Tab -->
                                <div class="tab-pane fade" id="next-of-kin" role="tabpanel" aria-labelledby="next-of-kin-tab">
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <h6 class="text-muted border-bottom pb-2">Emergency Contact Information</h6>
                                        </div>

                                        <div class="col-12 mb-3">
                                            <button type="button" class="btn btn-primary btn-sm" id="addNextOfKin">
                                                <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                                Add Next of Kin
                                            </button>
                                        </div>

                                        <div id="nextOfKinContainer">
                                            <div class="nok-entry mb-4 p-3 border rounded">
                                                <div class="row">
                                                    <div class="col-md-3 mb-3">
                                                        <label for="kin_names_0" class="form-label">Kin Names</label>
                                                        <input type="text" class="form-control" id="kin_names_0" name="next_of_kin[0][names]">
                                                    </div>

                                                    <div class="col-md-3 mb-3">
                                                        <label for="kin_mobile_0" class="form-label">Mobile Number</label>
                                                        <input type="text" class="form-control" id="kin_mobile_0" name="next_of_kin[0][mobile]">
                                                    </div>

                                                    <div class="col-md-3 mb-3">
                                                        <label for="kin_email_0" class="form-label">Email Address</label>
                                                        <input type="email" class="form-control" id="kin_email_0" name="next_of_kin[0][email]">
                                                    </div>

                                                    <div class="col-md-2 mb-3 d-flex align-items-end gap-1">
                                                        <button type="button" class="btn btn-primary btn-sm add-kin-inline d-none">
                                                            <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-primary btn-sm remove-nok d-none">
                                                            <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                                                        </button>
                                                    </div>

                                                    <div class="col-md-3 mb-3">
                                                        <label for="kin_relationship_0" class="form-label">Relationship</label>
                                                        <select class="form-select" id="kin_relationship_0" name="next_of_kin[0][relationship]">
                                                            <option value="">Please select</option>
                                                            <option value="Spouse">Spouse</option>
                                                            <option value="Parent">Parent</option>
                                                            <option value="Sibling">Sibling</option>
                                                            <option value="Child">Child</option>
                                                            <option value="Other">Other</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-3 mb-3">
                                                        <label for="kin_national_id_0" class="form-label">National ID</label>
                                                        <input type="text" class="form-control" id="kin_national_id_0" name="next_of_kin[0][national_id]">
                                                    </div>

                                                    <div class="col-md-3 mb-3">
                                                        <label for="kin_second_name_0" class="form-label">Second Name</label>
                                                        <input type="text" class="form-control" id="kin_second_name_0" name="next_of_kin[0][second_name]">
                                                    </div>

                                                    <div class="col-md-3 mb-3">
                                                        <label for="kin_last_name_0" class="form-label">Last Name</label>
                                                        <input type="text" class="form-control" id="kin_last_name_0" name="next_of_kin[0][last_name]">
                                                    </div>
                                                </div>
                                            </div>
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
<script>
    $(document).ready(function() {
        let banksData = [];
        let branchesData = [];

        // Toggle Individual vs Company fields based on Tenant Type
        function toggleTenantTypeFields() {
            var isCompany = $('#tenant_type').val() === '3';

            $('.individual-only').toggle(!isCompany);
            $('.company-only').toggle(isCompany);

            $('#names, #nationality_id, #national_id').prop('required', !isCompany);
            $('#company_name').prop('required', isCompany);
        }

        $('#tenant_type').on('change', toggleTenantTypeFields);
        toggleTenantTypeFields();

        // Live duplicate-identifier checker — warns (rather than blocks outright) when
        // a PIN Number (Individual) or Registration Number (Company) already belongs
        // to another tenant, and requires an explicit confirmation to proceed.
        function checkDuplicateIdentifier(fieldId, tenantType, label) {
            var $field = $('#' + fieldId);
            var $msg = $('#' + fieldId + '_duplicate');
            var value = $field.val().trim();

            if (!value) {
                $msg.addClass('d-none').text('');
                $field.removeData('duplicate-flagged').removeData('duplicate-confirmed');
                return;
            }

            // Already confirmed for this exact value — nothing to re-prompt.
            if ($field.data('duplicate-confirmed') === value) {
                return;
            }

            $.ajax({
                url: "{{ route('units.tenants.check-identifier') }}",
                type: 'GET',
                data: {
                    tenant_type: tenantType,
                    value: value
                },
                success: function(response) {
                    // The field may have changed while the request was in flight.
                    if ($field.val().trim() !== value) {
                        return;
                    }
                    if (response.exists) {
                        var details = [response.name, response.reference_number].filter(Boolean).join(' — ');
                        var text = 'A tenant with this ' + label + ' already exists' + (details ? ': ' + details : '') + '.';
                        $msg.removeClass('d-none').text(text);
                        $field.data('duplicate-flagged', value);

                        Swal.fire({
                            icon: 'warning',
                            title: 'Duplicate ' + label,
                            text: text + ' Do you want to continue anyway?',
                            showCancelButton: true,
                            confirmButtonText: 'Continue Anyway',
                            cancelButtonText: 'Change Value'
                        }).then(function(result) {
                            if (result.isConfirmed) {
                                $field.data('duplicate-confirmed', value);
                            } else {
                                $field.focus();
                            }
                        });
                    } else {
                        $msg.addClass('d-none').text('');
                        $field.removeData('duplicate-flagged').removeData('duplicate-confirmed');
                    }
                },
                error: function(xhr) {
                    console.error('Duplicate identifier check failed:', xhr);
                }
            });
        }

        $('#national_id').on('blur', function() {
            checkDuplicateIdentifier('national_id', 1, 'National ID');
        });

        $('#company_registration_number').on('blur', function() {
            checkDuplicateIdentifier('company_registration_number', 3, 'Registration Number');
        });

        // Live check as they type — debounced so it fires ~500ms after they pause,
        // not on every keystroke.
        var duplicateCheckTimers = {};
        function liveDuplicateCheck(fieldId, tenantType, label) {
            clearTimeout(duplicateCheckTimers[fieldId]);
            duplicateCheckTimers[fieldId] = setTimeout(function() {
                checkDuplicateIdentifier(fieldId, tenantType, label);
            }, 500);
        }

        $('#national_id').on('input', function() {
            liveDuplicateCheck('national_id', 1, 'National ID');
        });

        $('#company_registration_number').on('input', function() {
            liveDuplicateCheck('company_registration_number', 3, 'Registration Number');
        });

        // Block submission while a flagged-but-unconfirmed duplicate is showing for
        // the active tenant type — runs before the spinner handler below so a
        // blocked submit doesn't leave the Save button stuck disabled.
        $('#unitTenantForm').on('submit', function(e) {
            var isCompany = $('#tenant_type').val() === '3';
            var $field = isCompany ? $('#company_registration_number') : $('#national_id');
            var value = $field.val().trim();

            if ($field.data('duplicate-flagged') === value && $field.data('duplicate-confirmed') !== value) {
                e.preventDefault();
                e.stopImmediatePropagation();
                var tabButton = document.getElementById('tenants-tab');
                if (tabButton) {
                    bootstrap.Tab.getOrCreateInstance(tabButton).show();
                }
                toastr.error('Please resolve the duplicate ' + (isCompany ? 'registration number' : 'National ID') + ' before submitting.');
                $field.trigger('blur');
            }
        });

        // Move-in inspection state management
        function updateInspectionState(required) {
            $('#inspectionRequired').val(required);
            const $badge  = $('#inspectionBadge');
            const $date   = $('#inspection_date');
            const $reqMark = $('#inspectionDateRequired');

            $badge.show();
            if (required) {
                $badge.removeClass('bg-secondary bg-info').addClass('bg-danger').text('Required');
                $('#inspectionRequiredAlert').removeClass('d-none');
                $('#inspectionOptionalAlert').addClass('d-none');
                $date.prop('required', true);
                $reqMark.show();
                // Mandatory — auto-expand the collapsed accordion so the warning
                // and required fields aren't hidden from the tenant.
                var collapseEl = document.getElementById('moveInInspectionCollapse');
                if (collapseEl && !collapseEl.classList.contains('show')) {
                    bootstrap.Collapse.getOrCreateInstance(collapseEl).show();
                }
            } else {
                $badge.removeClass('bg-danger').addClass('bg-secondary').text('Optional');
                $('#inspectionRequiredAlert').addClass('d-none');
                $('#inspectionOptionalAlert').removeClass('d-none');
                $date.prop('required', false);
                $reqMark.hide();
            }
        }

        // Form submission with spinner + inspection gate
        $('#unitTenantForm').on('submit', function(e) {
            const inspRequired = parseInt($('#inspectionRequired').val());
            const inspDate = $('#inspection_date').val();
            if (inspRequired && !inspDate) {
                e.preventDefault();
                $('#inspectionRequiredAlert').removeClass('d-none');
                var collapseEl = document.getElementById('moveInInspectionCollapse');
                if (collapseEl && !collapseEl.classList.contains('show')) {
                    bootstrap.Collapse.getOrCreateInstance(collapseEl).show();
                }
                $('html, body').animate({ scrollTop: $('#inspectionSection').offset().top - 100 }, 300);
                return false;
            }
            $('#submitSpinner').removeClass('d-none');
            $('#saveButton').prop('disabled', true);
        });

        // Load banks and branches
        $.ajax({
            url: "{{ url('/ajax/banks-and-branches') }}",
            type: 'GET',
            success: function(response) {
                if (response.banks) {
                    banksData = response.banks;
                    populateAllBankDropdowns();
                }
                if (response.branches) {
                    branchesData = response.branches;
                }
            },
            error: function(xhr) {
                console.error('Failed to load banks and branches:', xhr);
            }
        });

        // Function to populate all bank dropdowns
        function populateAllBankDropdowns() {
            $('.bank-select').each(function() {
                let $select = $(this);
                let selectedValue = $select.val();
                $select.empty().append('<option value="">Please select</option>');
                banksData.forEach(function(bank) {
                    let selected = (selectedValue == bank.id) ? 'selected' : '';
                    $select.append(`<option value="${bank.id}" ${selected}>${bank.bank_name}</option>`);
                });
            });
        }

        // Handle bank change to load branches (delegated event)
        $(document).on('change', '.bank-select', function() {
            var bankId = $(this).val();
            var index = $(this).data('index');
            var branchSelect = $('#branch_id_' + index);

            branchSelect.html('<option value="">Please select</option>');

            if (bankId) {
                // Filter branches by selected bank
                var filteredBranches = branchesData.filter(function(branch) {
                    return branch.bank_id == bankId;
                });

                if (filteredBranches.length > 0) {
                    filteredBranches.forEach(function(branch) {
                        branchSelect.append(`<option value="${branch.id}">${branch.branch_name}</option>`);
                    });
                } else {
                    branchSelect.append('<option value="">No branches available</option>');
                }
            }
        });

        // Load properties via AJAX when page loads
        const clientId = $('input[name="client_id"]').val();
        if (clientId) {
            loadProperties(clientId);
        }
        // If no clientId, property dropdown stays disabled until a landlord is picked via search.

        // Function to load properties for a given landlord/client ID
        function loadProperties(ownerId) {
            $('#property_id').html('<option value="">Loading properties...</option>').prop('disabled', true);

            $.ajax({
                url: `/client/${ownerId}/properties`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#property_id').empty().prop('disabled', false);
                    $('#property_id').append('<option value="">Please select</option>');
                    if (response.properties && response.properties.length > 0) {
                        response.properties.forEach(function(property) {
                            $('#property_id').append(`<option value="${property.id}">${property.property_name}</option>`);
                        });
                    } else {
                        $('#property_id').append('<option value="">No properties found for this landlord</option>');
                    }
                },
                error: function() {
                    $('#property_id').empty().prop('disabled', false);
                    $('#property_id').append('<option value="">Error loading properties</option>');
                }
            });
        }

        /* ── Landlord search (only rendered when no client_id) ── */
        let landlordSearchTimer = null;

        $('#landlordSearch').on('input', function() {
            const q = $(this).val().trim();
            clearTimeout(landlordSearchTimer);
            $('#landlordResults').hide().empty();

            if (q.length < 2) return;

            landlordSearchTimer = setTimeout(function() {
                $.ajax({
                    url: '/ajax/landlord/search-landlords',
                    data: { search: q },
                    success: function(res) {
                        const $results = $('#landlordResults').empty();
                        if (res.landlords && res.landlords.length) {
                            res.landlords.forEach(function(l) {
                                $results.append(
                                    `<button type="button" class="list-group-item list-group-item-action landlord-result-item"
                                             data-id="${l.id}" data-name="${l.names}" style="font-size:0.83rem;background:#fff;border:0;border-bottom:1px solid #f0f0f0;text-align:left;width:100%;padding:8px 12px;">
                                        <span class="fw-semibold">${l.names}</span>
                                        <span class="text-muted ms-2" style="font-size:0.75rem;">${l.reference_number || ''}</span>
                                        ${l.mobile_number ? `<span class="text-muted ms-1" style="font-size:0.75rem;">· ${l.mobile_number}</span>` : ''}
                                    </button>`
                                );
                            });
                            $results.show();
                        } else {
                            $results.append('<div class="list-group-item text-muted" style="font-size:0.82rem;">No landlords found.</div>').show();
                        }
                    }
                });
            }, 280);
        });

        $(document).on('click', '.landlord-result-item', function() {
            const id   = $(this).data('id');
            const name = $(this).data('name');
            $('#selectedLandlordId').val(id);
            $('#landlordSearch').val('').hide();
            $('#landlordResults').hide().empty();
            $('#landlordSelectedName').text(name);
            $('#landlordSelected').show();
            loadProperties(id);
        });

        $('#clearLandlord').on('click', function() {
            $('#selectedLandlordId').val('');
            $('#landlordSelectedName').text('');
            $('#landlordSelected').hide();
            $('#landlordSearch').val('').show().focus();
            // Reset property and downstream
            $('#property_id').html('<option value="">Select a landlord first</option>').prop('disabled', true);
            $('#unit_id').html('<option value="">Please select</option>').prop('disabled', true);
            updateInspectionState(0);
        });

        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#landlordSearchWrapper').length) {
                $('#landlordResults').hide();
            }
        });

        // Move-in inspection media previews
        document.getElementById('moveInPhotos').addEventListener('change', function() {
            const preview = document.getElementById('moveInPhotoPreview');
            preview.innerHTML = '';
            Array.from(this.files).forEach(file => {
                const url = URL.createObjectURL(file);
                preview.insertAdjacentHTML('beforeend',
                    `<div class="position-relative" style="width:72px;height:72px;">
                        <img src="${url}" style="width:72px;height:72px;object-fit:cover;border-radius:4px;border:1px solid #dee2e6;">
                        <span class="position-absolute bottom-0 start-0 w-100 text-truncate px-1" style="font-size:0.6rem;background:rgba(0,0,0,.45);color:#fff;border-radius:0 0 4px 4px;">${file.name}</span>
                     </div>`
                );
            });
        });
        document.getElementById('moveInVideos').addEventListener('change', function() {
            const preview = document.getElementById('moveInVideoPreview');
            preview.innerHTML = '';
            Array.from(this.files).forEach(file => {
                preview.insertAdjacentHTML('beforeend',
                    `<div class="d-flex align-items-center gap-1 border rounded px-2 py-1" style="font-size:0.75rem;max-width:200px;">
                        <span class="material-symbols-outlined" style="font-size:16px;color:#6c757d;">videocam</span>
                        <span class="text-truncate">${file.name}</span>
                     </div>`
                );
            });
        });


        // Loads units for a property; preserveSelected keeps the current
        // unit chosen on a background refresh instead of resetting it.
        function loadUnitsForProperty(propertyId, preserveSelected) {
            var unitSelect = $('#unit_id');
            var previouslySelected = preserveSelected ? unitSelect.val() : null;
            unitSelect.html('<option value="">Loading units...</option>');

            $.ajax({
                url: `/property/${propertyId}/units?vacant_only=1`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Units loaded:', response);
                    unitSelect.empty().prop('disabled', false);
                    unitSelect.append('<option value="">Please select</option>');

                    if (response.units && response.units.length > 0) {
                        response.units.forEach(function(unit) {
                            let unitLabel = unit.unit_name || unit.unit_number;
                            let status = unit.status == 'Occupied' ? ' (Occupied)' : '';
                            let rentRaw = String(unit.rent_amount || '0').replace(/,/g, '');
                            unitSelect.append(`<option value="${unit.id}" data-rent="${rentRaw}">${unitLabel}${status}</option>`);
                        });
                    } else {
                        unitSelect.append('<option value="">No units available</option>');
                    }
                    if (previouslySelected && unitSelect.find(`option[value="${previouslySelected}"]`).length) {
                        unitSelect.val(previouslySelected);
                    }
                    updateDepositPreview();
                },
                error: function(xhr, status, error) {
                    console.error('Error loading units:', xhr.responseText);
                    unitSelect.empty().prop('disabled', false);
                    unitSelect.append('<option value="">Error loading units</option>');
                }
            });
        }

        // Load units when property is selected
        $('#property_id').on('change', function() {
            var propertyId = $(this).val();
            var unitSelect = $('#unit_id');

            // Reset units
            unitSelect.html('<option value="">Please select</option>').prop('disabled', true);
            updateInspectionState(0);

            if (propertyId) {
                // The property's SLA is resolved server-side on save (no manual picker
                // here) — still fetched client-side so the move-in-inspection-required
                // alert reflects the same SLA the server will use (most recent by start
                // date, matching resolveSlaId()).
                $.ajax({
                    url: `/property/${propertyId}/sla`,
                    method: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.sla && response.sla.length > 0) {
                            updateInspectionState(response.sla[0].move_in_inspection_required ? 1 : 0);
                        } else {
                            updateInspectionState(0);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading SLA:', xhr.responseText);
                        updateInspectionState(0);
                    }
                });

                loadUnitsForProperty(propertyId);
            }
        });

        // Refresh units on refocus, so one created in another tab shows up
        // without requiring a manual page reload.
        $(window).on('focus', function() {
            var propertyId = $('#property_id').val();
            if (propertyId) {
                loadUnitsForProperty(propertyId, true);
            }
        });

        // Deposit Amount preview — unit rent × months. Display only; the real
        // amount is always computed server-side the same way on submit.
        function updateDepositPreview() {
            var rent = parseFloat($('#unit_id option:selected').data('rent')) || 0;
            var months = parseFloat($('#deposit_months').val()) || 0;
            var preview = $('#deposit_amount_preview');
            if (rent > 0 && months > 0) {
                preview.val((rent * months).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            } else {
                preview.val('');
            }
        }
        $(document).on('change', '#unit_id', updateDepositPreview);
        $(document).on('input', '#deposit_months', updateDepositPreview);

        // Dynamic Other Contacts
        let contactCounter = 1;
        $('#addOtherContact').on('click', function() {
            const contactEntry = `
                <div class="other-contact-entry mb-3 p-3 border rounded">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="contact_name_${contactCounter}" class="form-label">Names</label>
                            <input type="text" class="form-control" id="contact_name_${contactCounter}" name="other_contacts[${contactCounter}][name]">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="contact_email_${contactCounter}" class="form-label">Email</label>
                            <input type="email" class="form-control" id="contact_email_${contactCounter}" name="other_contacts[${contactCounter}][email]">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="contact_mobile_${contactCounter}" class="form-label">Mobile Number</label>
                            <input type="text" class="form-control" id="contact_mobile_${contactCounter}" name="other_contacts[${contactCounter}][mobile]">
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end gap-1">
                            <button type="button" class="btn btn-outline-primary btn-sm remove-other-contact">
                                <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('#otherContactsContainer').append(contactEntry);
            contactCounter++;
            updateRemoveButtons('.other-contact-entry', '.remove-other-contact');
        });

        $(document).on('click', '.remove-other-contact', function() {
            $(this).closest('.other-contact-entry').remove();
            updateRemoveButtons('.other-contact-entry', '.remove-other-contact');
        });

        // Dynamic Tenant Files
        let fileCounter = 1;
        $('#addTenantFile').on('click', function() {
            const fileEntry = `
                <div class="tenant-file-entry mb-3 p-3 border rounded">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="file_type_${fileCounter}" class="form-label">Document Type</label>
                            <select class="form-select" id="file_type_${fileCounter}" name="tenant_files[${fileCounter}][type]">
                                <option value="">Please select</option>
                                <option value="National ID">National ID</option>
                                <option value="Passport">Passport</option>
                                <option value="Contract">Contract</option>
                                <option value="Lease Agreement">Lease Agreement</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="file_upload_${fileCounter}" class="form-label">Documents</label>
                            <input type="file" class="form-control" id="file_upload_${fileCounter}" name="tenant_files[${fileCounter}][file]" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-primary btn-sm remove-tenant-file">
                                <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('#tenantFilesContainer').append(fileEntry);
            fileCounter++;
            updateRemoveButtons('.tenant-file-entry', '.remove-tenant-file');
        });

        $(document).on('click', '.remove-tenant-file', function() {
            $(this).closest('.tenant-file-entry').remove();
            updateRemoveButtons('.tenant-file-entry', '.remove-tenant-file');
        });

        // Dynamic Next of Kin
        let kinCounter = 1;
        $('#addNextOfKin').on('click', function() {
            const kinEntry = `
                <div class="nok-entry mb-4 p-3 border rounded">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="kin_names_${kinCounter}" class="form-label">Kin Names</label>
                            <input type="text" class="form-control" id="kin_names_${kinCounter}" name="next_of_kin[${kinCounter}][names]">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="kin_mobile_${kinCounter}" class="form-label">Mobile Number</label>
                            <input type="text" class="form-control" id="kin_mobile_${kinCounter}" name="next_of_kin[${kinCounter}][mobile]">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="kin_email_${kinCounter}" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="kin_email_${kinCounter}" name="next_of_kin[${kinCounter}][email]">
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end gap-1">
                            <button type="button" class="btn btn-outline-primary btn-sm remove-nok">
                                <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                            </button>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="kin_relationship_${kinCounter}" class="form-label">Relationship</label>
                            <select class="form-select" id="kin_relationship_${kinCounter}" name="next_of_kin[${kinCounter}][relationship]">
                                <option value="">Please select</option>
                                <option value="Spouse">Spouse</option>
                                <option value="Parent">Parent</option>
                                <option value="Sibling">Sibling</option>
                                <option value="Child">Child</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="kin_national_id_${kinCounter}" class="form-label">National ID</label>
                            <input type="text" class="form-control" id="kin_national_id_${kinCounter}" name="next_of_kin[${kinCounter}][national_id]">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="kin_second_name_${kinCounter}" class="form-label">Second Name</label>
                            <input type="text" class="form-control" id="kin_second_name_${kinCounter}" name="next_of_kin[${kinCounter}][second_name]">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="kin_last_name_${kinCounter}" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="kin_last_name_${kinCounter}" name="next_of_kin[${kinCounter}][last_name]">
                        </div>
                    </div>
                </div>
            `;
            $('#nextOfKinContainer').append(kinEntry);
            kinCounter++;
            updateRemoveButtons('.nok-entry', '.remove-nok');
        });

        $(document).on('click', '.remove-nok', function() {
            $(this).closest('.nok-entry').remove();
            updateRemoveButtons('.nok-entry', '.remove-nok');
        });

        // Helper function to show/hide remove buttons
        function updateRemoveButtons(containerClass, buttonClass) {
            if ($(containerClass).length > 1) {
                $(buttonClass).removeClass('d-none');
            } else {
                $(buttonClass).addClass('d-none');
            }
        }

        // Bulk Upload Button Handler
        $('#bulkUploadBtn').on('click', function() {
            alert('Bulk upload functionality - integrate with your spreadsheet upload system');
        });

        // Search Tenant Button Handler
        $('#searchTenantBtn').on('click', function() {
            alert('Search existing tenant functionality - integrate with your tenant search system');
        });

        // Auto-populate account name from tenant name
        // $('#names').on('blur', function() {
        //     let fullName = $('#names').val();

        //     if (fullName && $('.bank-account-entry').length > 0) {
        //         $('.bank-account-entry').each(function(index) {
        //             let accountNameField = $(this).find(`#account_name_${index}`);
        //             if (!accountNameField.val()) {
        //                 accountNameField.val(fullName);
        //             }
        //         });
        //     }
        // });

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
</script>
@endsection