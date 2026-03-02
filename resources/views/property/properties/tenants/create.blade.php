@extends('layouts.main')
@section('content')
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

                            <!-- Tab Content -->
                            <div class="tab-content mt-3" id="unitTenantTabsContent">
                                <!-- Unit Details Tab -->
                                <div class="tab-pane fade show active" id="unit-details" role="tabpanel" aria-labelledby="unit-details-tab">
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <h6 class="text-muted border-bottom pb-2">Unit Information</h6>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                            <select class="form-select @error('property_id') is-invalid @enderror" id="property_id" name="property_id" required>
                                                <option value="">Loading properties...</option>
                                            </select>
                                            @error('property_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="sla_ref" class="form-label">SLA Ref <span class="text-danger">*</span></label>
                                            <select class="form-select @error('sla_ref') is-invalid @enderror" id="sla_ref" name="sla_ref" required>
                                                <option value="">Please select</option>
                                            </select>
                                            @error('sla_ref')
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
                                            <label for="rent_amount" class="form-label">Rent Amount <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" class="form-control @error('rent_amount') is-invalid @enderror" 
                                                   id="rent_amount" name="rent_amount" 
                                                   value="{{ old('rent_amount', '0.00') }}" required>
                                            @error('rent_amount')
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
                                            <label for="overcraft_penalty" class="form-label">Overcraft Penalty(%)</label>
                                            <input type="number" step="0.01" class="form-control @error('overcraft_penalty') is-invalid @enderror" 
                                                   id="overcraft_penalty" name="overcraft_penalty" 
                                                   value="{{ old('overcraft_penalty', '5') }}" placeholder="5">
                                            @error('overcraft_penalty')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Tenants Tab -->
                                <div class="tab-pane fade" id="tenants" role="tabpanel" aria-labelledby="tenants-tab">
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <h6 class="text-muted border-bottom pb-2">Tenant Information</h6>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                            <select class="form-select @error('title') is-invalid @enderror" id="title" name="title" required>
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

                                        <div class="col-md-3 mb-3">
                                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('first_name') is-invalid @enderror" 
                                                   id="first_name" name="first_name" 
                                                   value="{{ old('first_name') }}" required>
                                            @error('first_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="second_name" class="form-label">Second Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('second_name') is-invalid @enderror" 
                                                   id="second_name" name="second_name" 
                                                   value="{{ old('second_name') }}" required>
                                            @error('second_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="last_name" class="form-label">Last Name</label>
                                            <input type="text" class="form-control @error('last_name') is-invalid @enderror" 
                                                   id="last_name" name="last_name" 
                                                   value="{{ old('last_name') }}">
                                            @error('last_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                            <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required>
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
                                            <label for="preferred_language" class="form-label">Preferred Messages Language</label>
                                            <select class="form-select @error('preferred_language') is-invalid @enderror" id="preferred_language" name="preferred_language">
                                                <option value="1" {{ old('preferred_language') == '1' ? 'selected' : 'selected' }}>English</option>
                                                <option value="2" {{ old('preferred_language') == '2' ? 'selected' : '' }}>Swahili</option>
                                            </select>
                                            @error('preferred_language')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="tenant_type" class="form-label">Tenant Type <span class="text-danger">*</span></label>
                                            <select class="form-select @error('tenant_type') is-invalid @enderror" id="tenant_type" name="tenant_type" required>
                                                <option value="">Please select</option>
                                                <option value="1" {{ old('tenant_type') == '1' ? 'selected' : '' }}>Individual</option>
                                                <option value="2" {{ old('tenant_type') == '2' ? 'selected' : '' }}>SME</option>
                                                <option value="3" {{ old('tenant_type') == '3' ? 'selected' : '' }}>Corporate</option>
                                            </select>
                                            @error('tenant_type')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="phone" class="form-label">Phone</label>
                                            <input type="text" class="form-control @error('phone') is-invalid @enderror" 
                                                   id="phone" name="phone" 
                                                   value="{{ old('phone') }}" >
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
                                            <label for="email_address" class="form-label">Email Address <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control @error('email_address') is-invalid @enderror" 
                                                   id="email_address" name="email_address" 
                                                   value="{{ old('email_address') }}" required>
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
                                                   value="{{ old('registered_date', '2025-12-18') }}" required>
                                            @error('registered_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="nationality_id" class="form-label">Nationality <span class="text-danger">*</span></label>
                                            <select class="form-select @error('nationality_id') is-invalid @enderror" id="nationality_id" name="nationality_id" required>
                                                <option value="">Please select</option>
                                                @foreach($countries as $country)
                                                    <option value="{{ $country->id }}" {{ old('national_id') == $country->id ? 'selected' : '' }}>{{ $country->country_name }}</option>
                                                @endforeach
                                            </select>
                                            @error('nationality')
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

                                        <div class="col-md-4 mb-3">
                                            <label for="passport_number" class="form-label">Passport Number</label>
                                            <input type="text" class="form-control @error('passport_number') is-invalid @enderror" 
                                                   id="passport_number" name="passport_number" 
                                                   value="{{ old('passport_number') }}">
                                            @error('passport_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="national_id" class="form-label">National ID <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('national_id') is-invalid @enderror" 
                                                   id="national_id" name="national_id" 
                                                   value="{{ old('national_id') }}" required>
                                            @error('national_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
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
                                
                                        <div class="col-12 mb-3">
                                            <button type="button" class="btn btn-success btn-sm" id="addBankAccount">
                                                <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                                Add Bank Account
                                            </button>
                                        </div>
                                
                                        <div id="bankAccountsContainer">
                                            <div class="bank-account-entry mb-3 p-3 border rounded">
                                                <div class="row">
                                                    <div class="col-md-4 mb-3">
                                                        <label for="bank_id_0" class="form-label">Bank</label>
                                                        <select class="form-select bank-select" id="bank_id_0" name="bank_accounts[0][bank_id]" data-index="0">
                                                            <option value="">Please select</option>
                                                        </select>
                                                    </div>
                                
                                                    <div class="col-md-4 mb-3">
                                                        <label for="branch_id_0" class="form-label">Branch</label>
                                                        <select class="form-select branch-select" id="branch_id_0" name="bank_accounts[0][branch_id]" data-index="0">
                                                            <option value="">Please select</option>
                                                        </select>
                                                    </div>
                                
                                                    <div class="col-md-4 mb-3">
                                                        <label for="account_name_0" class="form-label">Account Name</label>
                                                        <input type="text" class="form-control" id="account_name_0" name="bank_accounts[0][account_name]">
                                                    </div>
                                
                                                    <div class="col-md-4 mb-3">
                                                        <label for="account_number_0" class="form-label">Account Number</label>
                                                        <input type="text" class="form-control" id="account_number_0" name="bank_accounts[0][account_number]">
                                                    </div>
                                
                                                    <div class="col-md-2 mb-3 d-flex align-items-end">
                                                        <button type="button" class="btn btn-danger btn-sm remove-bank-account d-none">
                                                            <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                        </button>
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
                                            <button type="button" class="btn btn-success btn-sm" id="addOtherContact">
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
                                                        <button type="button" class="btn btn-success btn-sm add-contact-inline d-none">
                                                            <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm remove-other-contact d-none">
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
                                            <button type="button" class="btn btn-success btn-sm" id="addTenantFile">
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
                                                        <button type="button" class="btn btn-danger btn-sm remove-tenant-file d-none">
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
                                            <button type="button" class="btn btn-success btn-sm" id="addNextOfKin">
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
                                                        <button type="button" class="btn btn-success btn-sm add-kin-inline d-none">
                                                            <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm remove-nok d-none">
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
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        let banksData = [];
        let branchesData = [];

        // Form submission with spinner
        $('#unitTenantForm').on('submit', function(e) {
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

        // Function to load properties for the client
        function loadProperties(clientId) {
            $('#property_id').html('<option value="">Loading properties...</option>').prop('disabled', true);
            
            $.ajax({
                url: `/client/${clientId}/properties`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Properties loaded:', response);
                    $('#property_id').empty().prop('disabled', false);
                    $('#property_id').append('<option value="">Please select</option>');
                    
                    if (response.properties && response.properties.length > 0) {
                        response.properties.forEach(function(property) {
                            $('#property_id').append(`<option value="${property.id}">${property.property_name}</option>`);
                        });
                    } else {
                        $('#property_id').append('<option value="">No properties found for this client</option>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading properties:', xhr.responseText);
                    $('#property_id').empty().prop('disabled', false);
                    $('#property_id').append('<option value="">Error loading properties</option>');
                }
            });
        }

        // Load units when property is selected
        $('#property_id').on('change', function() {
            var propertyId = $(this).val();
            var unitSelect = $('#unit_id');
            var slaSelect = $('#sla_ref');
        
            // Reset units and SLA
            unitSelect.html('<option value="">Please select</option>').prop('disabled', true);
            slaSelect.html('<option value="">Please select</option>').prop('disabled', true);
        
            if (propertyId) {
                // Load SLA for the property
                slaSelect.html('<option value="">Loading SLA...</option>');
                
                $.ajax({
                    url: `/property/${propertyId}/sla`,
                    method: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        console.log('SLA loaded:', response);
                        slaSelect.empty().prop('disabled', false);
                        slaSelect.append('<option value="">Please select</option>');
                        
                        if (response.sla && response.sla.length > 0) {
                            response.sla.forEach(function(sla) {
                                slaSelect.append(`<option value="${sla.id}">${sla.sla_reference || sla.reference_number}</option>`);
                            });
                        } else {
                            slaSelect.append('<option value="">No SLA found for this property</option>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading SLA:', xhr.responseText);
                        slaSelect.empty().prop('disabled', false);
                        slaSelect.append('<option value="">Error loading SLA</option>');
                    }
                });
        
                // Load units
                unitSelect.html('<option value="">Loading units...</option>');
                
                $.ajax({
                    url: `/property/${propertyId}/units`,
                    method: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        console.log('Units loaded:', response);
                        unitSelect.empty().prop('disabled', false);
                        unitSelect.append('<option value="">Please select</option>');
                        
                        if (response.units && response.units.length > 0) {
                            response.units.forEach(function(unit) {
                                let unitLabel = unit.unit_name || unit.unit_number;
                                let status = unit.occupancy_status_id == 4 ? ' (Occupied)' : '';
                                unitSelect.append(`<option value="${unit.id}">${unitLabel}${status}</option>`);
                            });
                        } else {
                            unitSelect.append('<option value="">No units available</option>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading units:', xhr.responseText);
                        unitSelect.empty().prop('disabled', false);
                        unitSelect.append('<option value="">Error loading units</option>');
                    }
                });
            }
        });

        // Dynamic Bank Accounts
        let bankCounter = 1;
        $('#addBankAccount').on('click', function() {
            const bankEntry = `
                <div class="bank-account-entry mb-3 p-3 border rounded">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="bank_id_${bankCounter}" class="form-label">Bank</label>
                            <select class="form-select bank-select" id="bank_id_${bankCounter}" name="bank_accounts[${bankCounter}][bank_id]" data-index="${bankCounter}">
                                <option value="">Please select</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="branch_id_${bankCounter}" class="form-label">Branch</label>
                            <select class="form-select branch-select" id="branch_id_${bankCounter}" name="bank_accounts[${bankCounter}][branch_id]" data-index="${bankCounter}">
                                <option value="">Please select</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="account_name_${bankCounter}" class="form-label">Account Name</label>
                            <input type="text" class="form-control" id="account_name_${bankCounter}" name="bank_accounts[${bankCounter}][account_name]">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="account_number_${bankCounter}" class="form-label">Account Number</label>
                            <input type="text" class="form-control" id="account_number_${bankCounter}" name="bank_accounts[${bankCounter}][account_number]">
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="button" class="btn btn-danger btn-sm remove-bank-account">
                                <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('#bankAccountsContainer').append(bankEntry);
            bankCounter++;
            
            // Populate bank dropdown for the new entry
            populateAllBankDropdowns();
            updateRemoveButtons('.bank-account-entry', '.remove-bank-account');
        });

        $(document).on('click', '.remove-bank-account', function() {
            $(this).closest('.bank-account-entry').remove();
            updateRemoveButtons('.bank-account-entry', '.remove-bank-account');
        });

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
                            <button type="button" class="btn btn-danger btn-sm remove-other-contact">
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
                            <button type="button" class="btn btn-danger btn-sm remove-tenant-file">
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
                            <button type="button" class="btn btn-danger btn-sm remove-nok">
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
        $('#first_name, #second_name, #last_name').on('blur', function() {
            let firstName = $('#first_name').val();
            let secondName = $('#second_name').val();
            let lastName = $('#last_name').val();
            let fullName = [firstName, secondName, lastName].filter(Boolean).join(' ');
            
            if (fullName && $('.bank-account-entry').length > 0) {
                $('.bank-account-entry').each(function(index) {
                    let accountNameField = $(this).find(`#account_name_${index}`);
                    if (!accountNameField.val()) {
                        accountNameField.val(fullName);
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
</script>
@endsection