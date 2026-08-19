@extends('layouts.main')
@section('content')
<style>
    input[readonly], textarea[readonly], select[readonly] {
        background-color: #f5f5f5 !important;
        cursor: not-allowed;
    }
    .company-only { display: none; }
</style>
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Tenant - {{ $tenant->reference_number }}</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="unitTenantForm" class="btn btn-primary btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Update</span>
                            </button>
                            <a href="{{ route('property.tenants.show', Crypt::encrypt($tenant->id)) }}" class="btn btn-light btn-sm d-flex align-items-center">
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

                        <form action="{{ route('units.tenants.update', Crypt::encrypt($tenant->id)) }}" method="POST" id="unitTenantForm" enctype="multipart/form-data" novalidate>
                            @csrf
                            @method('PUT')

                            <!-- Hidden client ID field -->
                            <input type="hidden" name="client_id" value="{{ $client->id ?? $tenant->property->client_id }}">

                            <!-- Nav Tabs -->
                            <ul class="nav nav-tabs" id="unitTenantTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="unit-details-tab" data-bs-toggle="tab" data-bs-target="#unit-details" type="button" role="tab">
                                        <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">home</span>
                                        Unit Details
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="tenant-info-tab" data-bs-toggle="tab" data-bs-target="#tenant-info" type="button" role="tab">
                                        <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">person</span>
                                        Tenant Information
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="bank-accounts-tab" data-bs-toggle="tab" data-bs-target="#bank-accounts" type="button" role="tab">
                                        <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">account_balance</span>
                                        Bank Accounts
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="other-contacts-tab" data-bs-toggle="tab" data-bs-target="#other-contacts" type="button" role="tab">
                                        <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">contacts</span>
                                        Other Contacts
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="tenant-files-tab" data-bs-toggle="tab" data-bs-target="#tenant-files" type="button" role="tab">
                                        <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">folder</span>
                                        Tenant Files
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="next-of-kin-tab" data-bs-toggle="tab" data-bs-target="#next-of-kin" type="button" role="tab">
                                        <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">family_restroom</span>
                                        Next of Kin
                                    </button>
                                </li>
                            </ul>

                            <!-- Tab Content -->
                            <div class="tab-content mt-3" id="unitTenantTabsContent">
                                <!-- Unit Details Tab -->
                                <div class="tab-pane fade show active" id="unit-details" role="tabpanel">
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <h6 class="text-muted border-bottom pb-2">Unit Information</h6>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Property</label>
                                            <input type="text" class="form-control" value="{{ $tenant->property->property_name ?? '-' }}" readonly>
                                            <input type="hidden" name="property_id" value="{{ $tenant->property_id }}">
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Unit</label>
                                            <input type="text" class="form-control" value="{{ $tenant->unit->unit_name ?? $tenant->unit->unit_number ?? '-' }}" readonly>
                                            <input type="hidden" name="unit_id" value="{{ $tenant->unit_id }}">
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="lease_start_date" class="form-label">Lease Start Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control @error('lease_start_date') is-invalid @enderror" id="lease_start_date" name="lease_start_date" value="{{ old('lease_start_date', $tenant->lease_start_date ? $tenant->lease_start_date->format('Y-m-d') : '') }}" required>
                                            @error('lease_start_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="lease_years" class="form-label">Lease Years <span class="text-danger">*</span></label>
                                            <input type="number" step="1" class="form-control @error('lease_years') is-invalid @enderror" id="lease_years" name="lease_years" value="{{ old('lease_years', $tenant->lease_years ?? '1') }}" placeholder="e.g., 1" required>
                                            @error('lease_years')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="frequency" class="form-label">Frequency <span class="text-danger">*</span></label>
                                            <select class="form-select @error('frequency') is-invalid @enderror" id="frequency" name="frequency" required>
                                                <option value="Annually" {{ old('frequency', $tenant->frequency) == 'Annually' ? 'selected' : '' }}>Annually</option>
                                                <option value="Monthly" {{ old('frequency', $tenant->frequency) == 'Monthly' ? 'selected' : '' }}>Monthly</option>
                                                <option value="Quarterly" {{ old('frequency', $tenant->frequency) == 'Quarterly' ? 'selected' : '' }}>Quarterly</option>
                                                <option value="Biennially" {{ old('frequency', $tenant->frequency) == 'Biennially' ? 'selected' : '' }}>Biennially</option>
                                                <option value="Semi-Annually" {{ old('frequency', $tenant->frequency) == 'Semi-Annually' ? 'selected' : '' }}>Semi-Annually</option>
                                            </select>
                                            @error('frequency')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="escalation_rate" class="form-label">Escalation Rate(%)</label>
                                            <input type="number" step="0.01" class="form-control @error('escalation_rate') is-invalid @enderror" id="escalation_rate" name="escalation_rate" value="{{ old('escalation_rate', $tenant->escalation_rate ?? '0') }}" placeholder="0">
                                            @error('escalation_rate')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="address_2" class="form-label">Address 2</label>
                                            <input type="text" class="form-control @error('address_2') is-invalid @enderror" id="address_2" name="address_2" value="{{ old('address_2', $tenant->address_2) }}" placeholder="Address line 2">
                                            @error('address_2')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="address_3" class="form-label">Address 3</label>
                                            <input type="text" class="form-control @error('address_3') is-invalid @enderror" id="address_3" name="address_3" value="{{ old('address_3', $tenant->address_3) }}" placeholder="Address line 3">
                                            @error('address_3')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Tenants Tab -->
                                <div class="tab-pane fade" id="tenant-info" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="tenant_type" class="form-label">Tenant Type <span class="text-danger">*</span></label>
                                            <select class="form-select @error('tenant_type') is-invalid @enderror" id="tenant_type" name="tenant_type" required>
                                                <option value="1" {{ old('tenant_type', $tenant->client_type_id) == '1' ? 'selected' : '' }}>Individual</option>
                                                <option value="3" {{ old('tenant_type', $tenant->client_type_id) == '3' ? 'selected' : '' }}>Company</option>
                                            </select>
                                            @error('tenant_type')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <!-- Company Information Section -->
                                        <div class="col-12 mb-3 mt-3 company-only">
                                            <h6 class="text-muted border-bottom pb-2">Company Information</h6>
                                        </div>

                                        <div class="col-md-6 mb-3 company-only">
                                            <label for="company_name" class="form-label">Company Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('company_name') is-invalid @enderror" id="company_name" name="company_name" value="{{ old('company_name', $tenant->company_name ?? $tenant->names) }}" placeholder="Enter company name">
                                            @error('company_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-6 mb-3 company-only">
                                            <label for="company_registration_number" class="form-label">Registration Number</label>
                                            <input type="text" class="form-control @error('company_registration_number') is-invalid @enderror" id="company_registration_number" name="company_registration_number" value="{{ old('company_registration_number', $tenant->company_registration_number) }}" placeholder="Enter certificate of incorporation / registration number">
                                            @error('company_registration_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="text-danger d-none duplicate-check-message" id="company_registration_number_duplicate"></small>
                                        </div>

                                        <!-- Contact Person Section (Company only) -->
                                        <div class="col-12 mb-3 mt-3 company-only">
                                            <h6 class="text-muted border-bottom pb-2">Contact Person</h6>
                                            <small class="text-muted">The individual who deals with this account day-to-day</small>
                                        </div>

                                        <div class="col-md-3 mb-3 company-only">
                                            <label for="contact_person_name" class="form-label">Contact Person Name</label>
                                            <input type="text" class="form-control @error('contact_person_name') is-invalid @enderror" id="contact_person_name" name="contact_person_name" value="{{ old('contact_person_name', $tenant->contact_person_name) }}" placeholder="Enter contact person's name">
                                            @error('contact_person_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3 company-only">
                                            <label for="contact_person_title" class="form-label">Position / Title</label>
                                            <input type="text" class="form-control @error('contact_person_title') is-invalid @enderror" id="contact_person_title" name="contact_person_title" value="{{ old('contact_person_title', $tenant->contact_person_title) }}" placeholder="e.g. Director, Office Manager">
                                            @error('contact_person_title')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3 company-only">
                                            <label for="contact_person_mobile" class="form-label">Contact Person Mobile</label>
                                            <input type="text" class="form-control @error('contact_person_mobile') is-invalid @enderror" id="contact_person_mobile" name="contact_person_mobile" value="{{ old('contact_person_mobile', $tenant->contact_person_mobile) }}" placeholder="254XXXXXXXXX">
                                            @error('contact_person_mobile')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3 company-only">
                                            <label for="contact_person_email" class="form-label">Contact Person Email</label>
                                            <input type="email" class="form-control @error('contact_person_email') is-invalid @enderror" id="contact_person_email" name="contact_person_email" value="{{ old('contact_person_email', $tenant->contact_person_email) }}" placeholder="name@example.com">
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
                                                <option value="Mr" {{ old('title', $tenant->title) == 'Mr' ? 'selected' : '' }}>Mr</option>
                                                <option value="Mrs" {{ old('title', $tenant->title) == 'Mrs' ? 'selected' : '' }}>Mrs</option>
                                                <option value="Ms" {{ old('title', $tenant->title) == 'Ms' ? 'selected' : '' }}>Ms</option>
                                                <option value="Dr" {{ old('title', $tenant->title) == 'Dr' ? 'selected' : '' }}>Dr</option>
                                                <option value="Prof" {{ old('title', $tenant->title) == 'Prof' ? 'selected' : '' }}>Prof</option>
                                            </select>
                                            @error('title')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        @php
                                            $namesWithoutTitle = trim(\Illuminate\Support\Str::after($tenant->names ?? '', $tenant->title ?? ''));
                                        @endphp
                                        <div class="col-md-6 mb-3 individual-only">
                                            <label for="names" class="form-label">Names <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('names') is-invalid @enderror" id="names" name="names" value="{{ old('names', $namesWithoutTitle) }}" placeholder="Enter full name">
                                            @error('names')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3 individual-only">
                                            <label for="gender" class="form-label">Gender</label>
                                            <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender">
                                                <option value="">Please select</option>
                                                <option value="Male" {{ old('gender', $tenant->gender) == 'Male' ? 'selected' : '' }}>Male</option>
                                                <option value="Female" {{ old('gender', $tenant->gender) == 'Female' ? 'selected' : '' }}>Female</option>
                                                <option value="Other" {{ old('gender', $tenant->gender) == 'Other' ? 'selected' : '' }}>Other</option>
                                            </select>
                                            @error('gender')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="mobile_number" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('mobile_number') is-invalid @enderror" id="mobile_number" name="mobile_number" value="{{ old('mobile_number', $tenant->mobile_number) }}" placeholder="e.g., +254712345678" required>
                                            @error('mobile_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="registered_date" class="form-label">Registered Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control @error('registered_date') is-invalid @enderror" id="registered_date" name="registered_date" value="{{ old('registered_date', $tenant->registered_date ? \Carbon\Carbon::parse($tenant->registered_date)->format('Y-m-d') : '') }}" required>
                                            @error('registered_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3 individual-only">
                                            <label for="nationality_id" class="form-label">Nationality <span class="text-danger">*</span></label>
                                            <select class="form-select @error('nationality_id') is-invalid @enderror" id="nationality_id" name="nationality_id">
                                                <option value="">Please select</option>
                                                @foreach($countries as $country)
                                                    <option value="{{ $country->id }}" {{ old('nationality_id', $tenant->nationality_id) == $country->id ? 'selected' : '' }}>{{ $country->country_name }}</option>
                                                @endforeach
                                            </select>
                                            @error('nationality_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="phone" class="form-label">Phone</label>
                                            <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" placeholder="e.g., +254712345678">
                                            @error('phone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="email_address" class="form-label">Email Address</label>
                                            <input type="email" class="form-control @error('email_address') is-invalid @enderror" id="email_address" name="email_address" value="{{ old('email_address', $tenant->email_address) }}" placeholder="e.g., tenant@example.com">
                                            @error('email_address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="pin_number" class="form-label">PIN Number</label>
                                            <input type="text" class="form-control @error('pin_number') is-invalid @enderror" id="pin_number" name="pin_number" value="{{ old('pin_number', $tenant->pin_number) }}" placeholder="e.g., P123456789">
                                            @error('pin_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3 individual-only">
                                            <label for="passport_number" class="form-label">Passport Number</label>
                                            <input type="text" class="form-control @error('passport_number') is-invalid @enderror" id="passport_number" name="passport_number" value="{{ old('passport_number', $tenant->passport_number) }}" placeholder="e.g., AB123456">
                                            @error('passport_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3 individual-only">
                                            <label for="national_id" class="form-label">National ID <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('national_id') is-invalid @enderror" id="national_id" name="national_id" value="{{ old('national_id', $tenant->national_id) }}" placeholder="e.g., 12345678">
                                            @error('national_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="text-danger d-none duplicate-check-message" id="national_id_duplicate"></small>
                                        </div>

                                        <div class="col-md-12 mb-3">
                                            <label for="address" class="form-label">Address</label>
                                            <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2" placeholder="Enter full address">{{ old('address', $tenant->address) }}</textarea>
                                            @error('address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Bank Accounts Tab -->
                                <div class="tab-pane fade" id="bank-accounts" role="tabpanel">
                                    @php $bankAccount = $tenant->bankAccounts->first(); @endphp
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
                                                    <input type="text" class="form-control" id="account_name_0" name="bank_accounts[0][account_name]" value="{{ old('bank_accounts.0.account_name', $bankAccount->account_name ?? '') }}">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="account_number_0" class="form-label">Account Number</label>
                                                    <input type="text" class="form-control" id="account_number_0" name="bank_accounts[0][account_number]" value="{{ old('bank_accounts.0.account_number', $bankAccount->account_number ?? '') }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Other Contacts Tab -->
                                <div class="tab-pane fade" id="other-contacts" role="tabpanel">
                                    <div class="mb-3">
                                        <button type="button" class="btn btn-primary btn-sm" id="addOtherContact">
                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">add</span>
                                            Add Other Contact
                                        </button>
                                    </div>
                                    <div id="otherContactsContainer">
                                        @foreach($tenant->otherContacts as $index => $contact)
                                        <div class="other-contact-entry mb-3 p-3 border rounded">
                                            <div class="row">
                                                <div class="col-md-3 mb-3">
                                                    <label for="contact_name_{{ $index }}" class="form-label">Names</label>
                                                    <input type="text" class="form-control" id="contact_name_{{ $index }}" name="other_contacts[{{ $index }}][name]" value="{{ old('other_contacts.'.$index.'.name', $contact->contact_names) }}">
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label for="contact_email_{{ $index }}" class="form-label">Email</label>
                                                    <input type="email" class="form-control" id="contact_email_{{ $index }}" name="other_contacts[{{ $index }}][email]" value="{{ old('other_contacts.'.$index.'.email', $contact->other_email) }}">
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label for="contact_mobile_{{ $index }}" class="form-label">Mobile Number</label>
                                                    <input type="text" class="form-control" id="contact_mobile_{{ $index }}" name="other_contacts[{{ $index }}][mobile]" value="{{ old('other_contacts.'.$index.'.mobile', $contact->other_mobile_number) }}">
                                                </div>
                                                <div class="col-md-2 mb-3 d-flex align-items-end gap-1">
                                                    <button type="button" class="btn btn-outline-primary btn-sm remove-other-contact {{ $tenant->otherContacts->count() <= 1 ? 'd-none' : '' }}">
                                                        <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach

                                        @if($tenant->otherContacts->count() == 0)
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
                                                    <button type="button" class="btn btn-outline-primary btn-sm remove-other-contact d-none">
                                                        <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Tenant Files Tab -->
                                <div class="tab-pane fade" id="tenant-files" role="tabpanel">
                                    @if($tenant->documents && $tenant->documents->count() > 0)
                                    <div class="mb-3">
                                        <label class="form-label">Existing Documents</label>
                                        <div class="list-group">
                                            @foreach($tenant->documents as $document)
                                            <div class="list-group-item d-flex justify-content-between align-items-center existing-document" data-document-id="{{ $document->id }}">
                                                <span>{{ $document->file_type ?? 'Document' }} - {{ basename($document->documents) }}</span>
                                                <button type="button" class="btn btn-outline-primary btn-sm delete-existing-document" data-document-id="{{ $document->id }}">
                                                    <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                </button>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    <div class="mb-3">
                                        <button type="button" class="btn btn-primary btn-sm" id="addTenantFile">
                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">add</span>
                                            Add New Document
                                        </button>
                                    </div>
                                    <div id="tenantFilesContainer">
                                        <div class="tenant-file-entry mb-3 p-3 border rounded">
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label for="file_type_0" class="form-label">Document Type</label>
                                                    <select class="form-select" id="file_type_0" name="tenant_files[0][type]">
                                                        <option value="">Please select</option>
                                                        <option value="ID Copy">ID Copy</option>
                                                        <option value="Passport Copy">Passport Copy</option>
                                                        <option value="KRA Pin">KRA Pin</option>
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

                                <!-- Next of Kin Tab -->
                                <div class="tab-pane fade" id="next-of-kin" role="tabpanel">
                                    <div class="mb-3">
                                        <button type="button" class="btn btn-primary btn-sm" id="addNextOfKin">
                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">add</span>
                                            Add Next of Kin
                                        </button>
                                    </div>
                                    <div id="nextOfKinContainer">
                                        @foreach($tenant->nextOfKins as $index => $kin)
                                        <div class="nok-entry mb-4 p-3 border rounded">
                                            <div class="row">
                                                <div class="col-md-3 mb-3">
                                                    <label for="kin_names_{{ $index }}" class="form-label">Kin Names</label>
                                                    <input type="text" class="form-control" id="kin_names_{{ $index }}" name="next_of_kin[{{ $index }}][names]" value="{{ old('next_of_kin.'.$index.'.names', explode(' ', $kin->names)[0] ?? '') }}">
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label for="kin_mobile_{{ $index }}" class="form-label">Mobile Number</label>
                                                    <input type="text" class="form-control" id="kin_mobile_{{ $index }}" name="next_of_kin[{{ $index }}][mobile]" value="{{ old('next_of_kin.'.$index.'.mobile', $kin->mobile_number) }}">
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label for="kin_email_{{ $index }}" class="form-label">Email Address</label>
                                                    <input type="email" class="form-control" id="kin_email_{{ $index }}" name="next_of_kin[{{ $index }}][email]" value="{{ old('next_of_kin.'.$index.'.email', $kin->email_address) }}">
                                                </div>
                                                <div class="col-md-2 mb-3 d-flex align-items-end gap-1">
                                                    <button type="button" class="btn btn-outline-primary btn-sm remove-nok {{ $tenant->nextOfKins->count() <= 1 ? 'd-none' : '' }}">
                                                        <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                    </button>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label for="kin_relationship_{{ $index }}" class="form-label">Relationship</label>
                                                    <select class="form-select" id="kin_relationship_{{ $index }}" name="next_of_kin[{{ $index }}][relationship]">
                                                        <option value="">Please select</option>
                                                        <option value="Spouse" {{ old('next_of_kin.'.$index.'.relationship', $kin->relationship) == 'Spouse' ? 'selected' : '' }}>Spouse</option>
                                                        <option value="Parent" {{ old('next_of_kin.'.$index.'.relationship', $kin->relationship) == 'Parent' ? 'selected' : '' }}>Parent</option>
                                                        <option value="Sibling" {{ old('next_of_kin.'.$index.'.relationship', $kin->relationship) == 'Sibling' ? 'selected' : '' }}>Sibling</option>
                                                        <option value="Child" {{ old('next_of_kin.'.$index.'.relationship', $kin->relationship) == 'Child' ? 'selected' : '' }}>Child</option>
                                                        <option value="Other" {{ old('next_of_kin.'.$index.'.relationship', $kin->relationship) == 'Other' ? 'selected' : '' }}>Other</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label for="kin_national_id_{{ $index }}" class="form-label">National ID</label>
                                                    <input type="text" class="form-control" id="kin_national_id_{{ $index }}" name="next_of_kin[{{ $index }}][national_id]" value="{{ old('next_of_kin.'.$index.'.national_id', $kin->national_id) }}">
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label for="kin_second_name_{{ $index }}" class="form-label">Second Name</label>
                                                    <input type="text" class="form-control" id="kin_second_name_{{ $index }}" name="next_of_kin[{{ $index }}][second_name]" value="{{ old('next_of_kin.'.$index.'.second_name', explode(' ', $kin->names)[1] ?? '') }}">
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label for="kin_last_name_{{ $index }}" class="form-label">Last Name</label>
                                                    <input type="text" class="form-control" id="kin_last_name_{{ $index }}" name="next_of_kin[{{ $index }}][last_name]" value="{{ old('next_of_kin.'.$index.'.last_name', explode(' ', $kin->names)[2] ?? '') }}">
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach

                                        @if($tenant->nextOfKins->count() == 0)
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
                                                    <button type="button" class="btn btn-outline-primary btn-sm remove-nok d-none">
                                                        <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
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
                                        @endif
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
        
        // Store existing data for pre-population (single bank account only)
        let existingBankAccounts = [{
            bank_id: {{ old('bank_accounts.0.bank_id', optional($tenant->bankAccounts->first())->bank_id ?? 'null') }},
            branch_id: {{ old('bank_accounts.0.branch_id', optional($tenant->bankAccounts->first())->branch_id ?? 'null') }}
        }];

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
        // to a DIFFERENT tenant, and requires an explicit confirmation to proceed.
        // exclude_id keeps this tenant's own existing value from flagging itself.
        function checkDuplicateIdentifier(fieldId, tenantType, label) {
            var $field = $('#' + fieldId);
            var $msg = $('#' + fieldId + '_duplicate');
            var value = $field.val().trim();

            if (!value) {
                $msg.addClass('d-none').text('');
                $field.removeData('duplicate-flagged').removeData('duplicate-confirmed');
                return;
            }

            if ($field.data('duplicate-confirmed') === value) {
                return;
            }

            $.ajax({
                url: "{{ route('units.tenants.check-identifier') }}",
                type: 'GET',
                data: {
                    tenant_type: tenantType,
                    value: value,
                    exclude_id: '{{ Crypt::encrypt($tenant->id) }}'
                },
                success: function(response) {
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
        // the active tenant type.
        $('#unitTenantForm').on('submit', function(e) {
            var isCompany = $('#tenant_type').val() === '3';
            var $field = isCompany ? $('#company_registration_number') : $('#national_id');
            var value = $field.val().trim();

            if ($field.data('duplicate-flagged') === value && $field.data('duplicate-confirmed') !== value) {
                e.preventDefault();
                e.stopImmediatePropagation();
                var tabButton = document.getElementById('tenant-info-tab');
                if (tabButton) {
                    bootstrap.Tab.getOrCreateInstance(tabButton).show();
                }
                toastr.error('Please resolve the duplicate ' + (isCompany ? 'registration number' : 'National ID') + ' before submitting.');
                $field.trigger('blur');
            }
        });

        // Reset spinner on page load if there are errors
        if ($('.is-invalid').length > 0) {
            $('#submitSpinner').addClass('d-none');
            $('#saveButton').prop('disabled', false);
        }

        // Form submission with spinner
        $('#unitTenantForm').on('submit', function(e) {
            // Only show spinner if form is valid
            setTimeout(function() {
                $('#submitSpinner').removeClass('d-none');
                $('#saveButton').prop('disabled', true);
            }, 50);
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
                    // Pre-populate existing branch dropdowns
                    $('.bank-select').each(function(index) {
                        if (existingBankAccounts[index]) {
                            populateBranches(index, existingBankAccounts[index].bank_id, existingBankAccounts[index].branch_id);
                        }
                    });
                }
            },
            error: function(xhr) {
                console.error('Failed to load banks and branches:', xhr);
            }
        });

        // Load SLA for the current property on page load
        const propertyIdField = $('input[name="property_id"]');
        if (propertyIdField.length > 0) {
            const propertyId = propertyIdField.val();
            if (propertyId) {
                $.ajax({
                    url: `/property/${propertyId}/sla`,
                    method: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.sla && response.sla.length > 0) {
                            const slaRef = response.sla[0].reference_number;
                            // Display in a read-only field (SLA Ref field already has the value from Blade)
                            console.log('SLA loaded:', slaRef);
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading SLA:', xhr);
                    }
                });
            }
        }

        // Function to populate all bank dropdowns
        function populateAllBankDropdowns() {
            $('.bank-select').each(function(index) {
                let $select = $(this);
                let selectedValue = existingBankAccounts[index] ? existingBankAccounts[index].bank_id : null;
                $select.empty().append('<option value="">Please select</option>');
                banksData.forEach(function(bank) {
                    let selected = (selectedValue == bank.id) ? 'selected' : '';
                    $select.append(`<option value="${bank.id}" ${selected}>${bank.bank_name}</option>`);
                });
            });
        }

        // Function to populate branches for a specific index
        function populateBranches(index, bankId, selectedBranchId = null) {
            var branchSelect = $('#branch_id_' + index);
            branchSelect.html('<option value="">Please select</option>');

            if (bankId) {
                var filteredBranches = branchesData.filter(function(branch) {
                    return branch.bank_id == bankId;
                });

                if (filteredBranches.length > 0) {
                    filteredBranches.forEach(function(branch) {
                        let selected = (selectedBranchId == branch.id) ? 'selected' : '';
                        branchSelect.append(`<option value="${branch.id}" ${selected}>${branch.branch_name}</option>`);
                    });
                } else {
                    branchSelect.append('<option value="">No branches available</option>');
                }
            }
        }

        // Handle bank change to load branches (delegated event)
        $(document).on('change', '.bank-select', function() {
            var bankId = $(this).val();
            var index = $(this).data('index');
            populateBranches(index, bankId);
        });

        // Dynamic Other Contacts
        let contactCounter = {{ $tenant->otherContacts->count() > 0 ? $tenant->otherContacts->count() : 1 }};
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
                                <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
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
                                <option value="ID Copy">ID Copy</option>
                                <option value="Passport Copy">Passport Copy</option>
                                <option value="KRA Pin">KRA Pin</option>
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

        // Delete existing document AJAX
        $(document).on('click', '.delete-existing-document', function() {
            if (!confirm('Are you sure you want to delete this document?')) {
                return;
            }

            const documentId = $(this).data('document-id');
            const documentItem = $(this).closest('.existing-document');

            $.ajax({
                url: `/property/tenants/documents/${documentId}`,
                type: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    documentItem.fadeOut(300, function() {
                        $(this).remove();
                    });
                    toastr.success('Document deleted successfully');
                },
                error: function(xhr) {
                    console.error('Error deleting document:', xhr);
                    toastr.error('Failed to delete document');
                }
            });
        });

        // Dynamic Next of Kin
        let kinCounter = {{ $tenant->nextOfKins->count() > 0 ? $tenant->nextOfKins->count() : 1 }};
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
                                <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
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
