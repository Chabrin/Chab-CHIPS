@extends('layouts.main')
@section('content')
<style>
    /* Hidden immediately if the record is Individual (or by default); toggleClientTypeFields() controls it from there. */
    .company-only, .partnership-only { display: none; }

    /* Banks/Other Contacts/Next of Kin/Partners — compact, line-free rows
       rather than a bordered table (table-borderless alone gets overridden by
       the theme's own table styling, so these are explicit + !important). */
    .repeater-table { margin-bottom: 0; width: auto; }
    .repeater-table,
    .repeater-table tr,
    .repeater-table td,
    .repeater-table th {
        border: none !important;
        box-shadow: none !important;
    }
    .repeater-table td {
        padding: 12px 22px 12px 0 !important;
        vertical-align: middle;
        white-space: nowrap;
        font-size: 16px;
    }
    .repeater-table td:last-child {
        padding-right: 0 !important;
    }
    .repeater-table tbody tr:hover {
        background-color: transparent !important;
    }
</style>
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Client - {{ $client->reference_number }}</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="clientEditForm" class="btn btn-primary btn-sm d-flex align-items-center" id="updateButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Update Client</span>
                            </button>
                            <a href="{{ route('property.clients.show', Crypt::encrypt($client->id)) }}" class="btn btn-light btn-sm d-flex align-items-center">
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

                        <form action="{{ route('property.clients.update', Crypt::encrypt($client->id)) }}" method="POST" id="clientEditForm" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            <!-- Nav Tabs -->
                            <ul class="nav nav-tabs" id="clientEditTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab" aria-controls="personal" aria-selected="true">
                                        <span class="material-symbols-outlined">person</span>
                                        Personal Details
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="banking-tab" data-bs-toggle="tab" data-bs-target="#banking" type="button" role="tab" aria-controls="banking" aria-selected="false">
                                        <span class="material-symbols-outlined">account_balance</span>
                                        Bank Details
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts" type="button" role="tab" aria-controls="contacts" aria-selected="false">
                                        <span class="material-symbols-outlined">contact_phone</span>
                                        Other Contacts
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab" aria-controls="documents" aria-selected="false">
                                        <span class="material-symbols-outlined">folder</span>
                                        Files & Documents
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="nextofkin-tab" data-bs-toggle="tab" data-bs-target="#nextofkin" type="button" role="tab" aria-controls="nextofkin" aria-selected="false">
                                        <span class="material-symbols-outlined">family_restroom</span>
                                        Next of Kin
                                    </button>
                                </li>
                                <li class="nav-item partnership-only" role="presentation">
                                    <button class="nav-link" id="partners-tab" data-bs-toggle="tab" data-bs-target="#partners" type="button" role="tab" aria-controls="partners" aria-selected="false">
                                        <span class="material-symbols-outlined">groups</span>
                                        Partners
                                    </button>
                                </li>
                            </ul>

                            <!-- Tab Content -->
                            <div class="tab-content mt-3" id="clientEditTabsContent">
                                <!-- Personal Details Tab -->
                                <div class="tab-pane fade show active" id="personal" role="tabpanel" aria-labelledby="personal-tab">
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <h6 class="text-muted border-bottom pb-2">Personal Information</h6>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="select_time" class="form-label">Registered Date</label>
                                            <input type="datetime-local" class="form-control @error('select_time') is-invalid @enderror" 
                                                   id="select_time" name="select_time" 
                                                   value="{{ old('select_time', $client->registered_date ? \Carbon\Carbon::parse($client->registered_date)->format('Y-m-d\TH:i') : '') }}">
                                            @error('select_time')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="client_type_id" class="form-label">Client Type <span class="text-danger">*</span></label>
                                            <select class="form-select @error('client_type_id') is-invalid @enderror" id="client_type_id" name="client_type_id" required>
                                                <option value="1" {{ old('client_type_id', $client->client_type_id) == 1 ? 'selected' : '' }}>Individual</option>
                                                <option value="3" {{ old('client_type_id', $client->client_type_id) == 3 ? 'selected' : '' }}>Company</option>
                                                <option value="4" {{ old('client_type_id', $client->client_type_id) == 4 ? 'selected' : '' }}>Partnership and Trust</option>
                                            </select>
                                            @error('client_type_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <!-- Company / Partnership Information Section -->
                                        <div class="col-12 mb-3 mt-3 company-only partnership-only" id="companyHeading">
                                            <h6 class="text-muted border-bottom pb-2" id="entityInfoHeading">Company Information</h6>
                                        </div>

                                        <div class="col-md-6 mb-3 company-only partnership-only" id="companyFields">
                                            <label for="company_name" class="form-label" id="entityNameLabel">Company Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('company_name') is-invalid @enderror" id="company_name" name="company_name" value="{{ old('company_name', $client->company_name) }}" placeholder="Enter company name">
                                            @error('company_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-6 mb-3 company-only">
                                            <label for="company_registration_number" class="form-label">Registration Number</label>
                                            <input type="text" class="form-control @error('company_registration_number') is-invalid @enderror" id="company_registration_number" name="company_registration_number" value="{{ old('company_registration_number', $client->company_registration_number) }}" placeholder="Enter certificate of incorporation / registration number">
                                            @error('company_registration_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="text-danger d-none duplicate-check-message" id="company_registration_number_duplicate"></small>
                                        </div>

                                        <!-- Contact Person Section (Company only) -->
                                        <div class="col-12 mb-3 mt-3 company-only" id="contactPersonHeading">
                                            <h6 class="text-muted border-bottom pb-2">Contact Person</h6>
                                            <small class="text-muted">The individual who deals with this account day-to-day</small>
                                        </div>

                                        <div class="col-md-3 mb-3 company-only" id="companyContactFields">
                                            <label for="contact_person_name" class="form-label">Contact Person Name</label>
                                            <input type="text" class="form-control @error('contact_person_name') is-invalid @enderror" id="contact_person_name" name="contact_person_name" value="{{ old('contact_person_name', $client->contact_person_name) }}" placeholder="Enter contact person's name">
                                            @error('contact_person_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3 company-only">
                                            <label for="contact_person_title" class="form-label">Position / Title</label>
                                            <input type="text" class="form-control @error('contact_person_title') is-invalid @enderror" id="contact_person_title" name="contact_person_title" value="{{ old('contact_person_title', $client->contact_person_title) }}" placeholder="e.g. Director, Property Manager">
                                            @error('contact_person_title')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3 company-only">
                                            <label for="contact_person_mobile" class="form-label">Contact Person Mobile</label>
                                            <input type="text" class="form-control @error('contact_person_mobile') is-invalid @enderror" id="contact_person_mobile" name="contact_person_mobile" value="{{ old('contact_person_mobile', $client->contact_person_mobile) }}" placeholder="254XXXXXXXXX">
                                            @error('contact_person_mobile')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3 company-only">
                                            <label for="contact_person_email" class="form-label">Contact Person Email</label>
                                            <input type="email" class="form-control @error('contact_person_email') is-invalid @enderror" id="contact_person_email" name="contact_person_email" value="{{ old('contact_person_email', $client->contact_person_email) }}" placeholder="name@example.com">
                                            @error('contact_person_email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <!-- Personal Information Section -->
                                        <div class="col-12 mb-3 mt-3 individual-only">
                                            <h6 class="text-muted border-bottom pb-2">Personal Information</h6>
                                        </div>

                                        <div class="col-md-2 mb-3 individual-only" id="individualFields">
                                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                            <select class="form-select @error('title') is-invalid @enderror" id="title" name="title">
                                                <option value="">Select</option>
                                                <option value="Mr" {{ old('title', $client->title) == 'Mr' ? 'selected' : '' }}>Mr</option>
                                                <option value="Mrs" {{ old('title', $client->title) == 'Mrs' ? 'selected' : '' }}>Mrs</option>
                                                <option value="Ms" {{ old('title', $client->title) == 'Ms' ? 'selected' : '' }}>Ms</option>
                                                <option value="Dr" {{ old('title', $client->title) == 'Dr' ? 'selected' : '' }}>Dr</option>
                                                <option value="Prof" {{ old('title', $client->title) == 'Prof' ? 'selected' : '' }}>Prof</option>
                                            </select>
                                            @error('title')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        @php
                                            // Split the names for editing
                                            $nameParts = explode(' ', $client->names);
                                            $firstName = $nameParts[0] ?? '';
                                            $middleName = $client->second_name ?? '';
                                            $lastName = $client->last_name ?? '';
                                        @endphp

                                        <div class="col-md-4 mb-3 individual-only">
                                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('first_name') is-invalid @enderror"
                                                   id="first_name" name="first_name"
                                                   value="{{ old('first_name', $firstName) }}">
                                            @error('first_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3 individual-only">
                                            <label for="middle_name" class="form-label">Middle Name</label>
                                            <input type="text" class="form-control @error('middle_name') is-invalid @enderror"
                                                   id="middle_name" name="middle_name"
                                                   value="{{ old('middle_name', $middleName) }}">
                                            @error('middle_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3 individual-only">
                                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('last_name') is-invalid @enderror"
                                                   id="last_name" name="last_name"
                                                   value="{{ old('last_name', $lastName) }}">
                                            @error('last_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3 individual-only">
                                            <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                            <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender">
                                                <option value="">Please select</option>
                                                <option value="Male" {{ old('gender', $client->gender) == 'Male' ? 'selected' : '' }}>Male</option>
                                                <option value="Female" {{ old('gender', $client->gender) == 'Female' ? 'selected' : '' }}>Female</option>
                                                <option value="Other" {{ old('gender', $client->gender) == 'Other' ? 'selected' : '' }}>Other</option>
                                            </select>
                                            @error('gender')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="nationality_id" class="form-label">Nationality</label>
                                            <select class="form-select @error('nationality_id') is-invalid @enderror" id="nationality_id" name="nationality_id">
                                                @include('property.clients.partials.nationality-options', ['selectedNationality' => old('nationality_id', $client->nationality_id)])
                                            </select>
                                            @error('nationality_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="preferred_language_id" class="form-label">Preferred Language</label>
                                            <select class="form-select @error('preferred_language_id') is-invalid @enderror" id="preferred_language_id" name="preferred_language_id">
                                                <option value="">Select Language</option>
                                                <option value="1" {{ old('preferred_language_id', $client->prefered_messages_language_id) == '1' ? 'selected' : '' }}>English</option>
                                                <option value="2" {{ old('preferred_language_id', $client->prefered_messages_language_id) == '2' ? 'selected' : '' }}>Swahili</option>
                                            </select>
                                            @error('preferred_language_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3 individual-only" id="individualIdFields">
                                            <label for="national_id" class="form-label">National ID</label>
                                            <input type="text" class="form-control @error('national_id') is-invalid @enderror"
                                                   id="national_id" name="national_id"
                                                   value="{{ old('national_id', $client->national_id) }}">
                                            @error('national_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="text-danger d-none duplicate-check-message" id="national_id_duplicate"></small>
                                        </div>

                                        <div class="col-md-4 mb-3 individual-only">
                                            <label for="passport_number" class="form-label">Passport Number</label>
                                            <input type="text" class="form-control @error('passport_number') is-invalid @enderror"
                                                   id="passport_number" name="passport_number"
                                                   value="{{ old('passport_number', $client->passport_number) }}">
                                            @error('passport_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="pin_number" class="form-label">PIN Number</label>
                                            <input type="text" class="form-control @error('pin_number') is-invalid @enderror" 
                                                   id="pin_number" name="pin_number" 
                                                   value="{{ old('pin_number', $client->pin_number) }}">
                                            @error('pin_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="vat_number" class="form-label">VAT Number</label>
                                            <input type="text" class="form-control @error('vat_number') is-invalid @enderror" 
                                                   id="vat_number" name="vat_number" 
                                                   value="{{ old('vat_number', $client->vat_number) }}">
                                            @error('vat_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="mobile_number" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('mobile_number') is-invalid @enderror" 
                                                   id="mobile_number" name="mobile_number" 
                                                   value="{{ old('mobile_number', $client->mobile_number) }}" 
                                                   placeholder="254XXXXXXXXX" required>
                                            @error('mobile_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="email_address" class="form-label">Email Address</label>
                                            <input type="email" class="form-control @error('email_address') is-invalid @enderror"
                                                   id="email_address" name="email_address"
                                                   value="{{ old('email_address', $client->email_address) }}">
                                            @error('email_address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="photo" class="form-label">Profile Photo</label>
                                            @if($client->photo)
                                            <div class="mb-2">
                                                <img src="{{ Storage::url($client->photo) }}" alt="Current Photo" class="img-thumbnail" style="max-height: 100px;">
                                                <small class="d-block text-muted">Current photo (upload new to replace)</small>
                                            </div>
                                            @endif
                                            <input type="file" class="form-control @error('photo') is-invalid @enderror" 
                                                   id="photo" name="photo" accept="image/*">
                                            @error('photo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-12 mb-3">
                                            <label for="address" class="form-label">Address Line 1</label>
                                            <textarea class="form-control @error('address') is-invalid @enderror" 
                                                      id="address" name="address" rows="2">{{ old('address', $client->address) }}</textarea>
                                            @error('address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="address_2" class="form-label">Address Line 2</label>
                                            <input type="text" class="form-control @error('address_2') is-invalid @enderror" 
                                                   id="address_2" name="address_2" 
                                                   value="{{ old('address_2', $client->address_2) }}">
                                            @error('address_2')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="address_3" class="form-label">Address Line 3</label>
                                            <input type="text" class="form-control @error('address_3') is-invalid @enderror" 
                                                   id="address_3" name="address_3" 
                                                   value="{{ old('address_3', $client->address_3) }}">
                                            @error('address_3')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Bank Details Tab -->
                                <div class="tab-pane fade" id="banking" role="tabpanel" aria-labelledby="banking-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">Bank Accounts</h6>
                                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addBankModal">
                                            <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                            Add
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table repeater-table">
                                            <tbody id="bankAccountsTableBody">
                                                <tr><td colspan="6" class="text-center text-muted">No bank accounts added yet</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Other Contacts Tab -->
                                <div class="tab-pane fade" id="contacts" role="tabpanel" aria-labelledby="contacts-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">Other Contacts</h6>
                                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addContactModal">
                                            <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                            Add
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table repeater-table">
                                            <tbody id="contactsTableBody">
                                                <tr><td colspan="4" class="text-center text-muted">No contacts added yet</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Files & Documents Tab -->
                                <div class="tab-pane fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <h6 class="text-muted border-bottom pb-2">Upload Documents</h6>
                                        </div>

                                        @if($client->documents && $client->documents->count() > 0)
                                        <div class="col-12 mb-4">
                                            <h6 class="text-muted">Existing Documents</h6>
                                            <div class="list-group">
                                                @foreach($client->documents as $document)
                                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong>{{ $document->file_type }}</strong>
                                                        <br>
                                                        <small class="text-muted">{{ basename($document->documents) }}</small>
                                                    </div>
                                                    <a href="{{ Storage::url($document->documents) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <span class="material-symbols-outlined" style="font-size:16px;">download</span>
                                                        Download
                                                    </a>
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        @endif

                                        <div class="col-12 mb-3">
                                            <h6 class="text-muted">Add New Documents</h6>
                                        </div>

                                        <div class="col-12 mb-3 text-end">
                                            <button type="button" class="btn btn-success btn-sm" id="addDocument">
                                                <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                                Add Document
                                            </button>
                                        </div>

                                        <div id="documentsContainer">
                                            <div class="document-entry mb-4 p-3 border rounded">
                                                <div class="row">
                                                    <div class="col-md-4 mb-3">
                                                        <label for="file_type_0" class="form-label">File Type</label>
                                                        <select class="form-select" id="file_type_0" name="documents[0][file_type]">
                                                            <option value="">Please select</option>
                                                            <option value="National ID">National ID</option>
                                                            <option value="Passport">Passport</option>
                                                            <option value="PIN Certificate">PIN Certificate</option>
                                                            <option value="Contract">Contract</option>
                                                            <option value="Other">Other</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label for="document_file_0" class="form-label">Documents</label>
                                                        <input type="file" class="form-control" id="document_file_0" name="documents[0][file]">
                                                    </div>
                                                    <div class="col-md-2 mb-3 d-flex align-items-end">
                                                        <button type="button" class="btn btn-danger btn-sm remove-document" style="display:none;">
                                                            <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Next of Kin Tab -->
                                <div class="tab-pane fade" id="nextofkin" role="tabpanel" aria-labelledby="nextofkin-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">Next of Kin</h6>
                                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addNextOfKinModal">
                                            <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                            Add
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table repeater-table">
                                            <tbody id="nextOfKinTableBody">
                                                <tr><td colspan="6" class="text-center text-muted">No next of kin added yet</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Partners Tab (Partnership and Trust only) -->
                                <div class="tab-pane fade partnership-only" id="partners" role="tabpanel" aria-labelledby="partners-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">Partners</h6>
                                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addPartnerModal">
                                            <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                            Add
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table repeater-table">
                                            <tbody id="partnersTableBody">
                                                <tr><td colspan="5" class="text-center text-muted">No partners added yet</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <input type="hidden" id="client_encrypted_id" value="{{ Crypt::encrypt($client->id) }}">

                        <!-- Add Bank Account Modal -->
                        <div class="modal fade" id="addBankModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Add Bank Account</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Bank</label>
                                            <select class="form-select" id="modal_bank_id">
                                                <option value="">Please select</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Branch</label>
                                            <select class="form-select" id="modal_branch_id">
                                                <option value="">Please select</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Account Name</label>
                                            <input type="text" class="form-control" id="modal_account_name" placeholder="Enter account name">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Account Number</label>
                                            <input type="text" class="form-control" id="modal_account_number" placeholder="Enter account number">
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="modal_is_default">
                                            <label class="form-check-label" for="modal_is_default">Set as default account</label>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary d-flex align-items-center" id="saveBankBtn">
                                            <span class="spinner-border spinner-border-sm me-2 d-none"></span>
                                            Save
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Add Contact Modal -->
                        <div class="modal fade" id="addContactModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Add Contact</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Names</label>
                                            <input type="text" class="form-control" id="modal_contact_names" placeholder="Enter contact name">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" id="modal_contact_email" placeholder="name@example.com">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Mobile Number</label>
                                            <input type="text" class="form-control" id="modal_contact_mobile" placeholder="254XXXXXXXXX">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary d-flex align-items-center" id="saveContactBtn">
                                            <span class="spinner-border spinner-border-sm me-2 d-none"></span>
                                            Save
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Add Next of Kin Modal -->
                        <div class="modal fade" id="addNextOfKinModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Add Next of Kin</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">First Name</label>
                                                <input type="text" class="form-control" id="modal_nok_first_name" placeholder="Enter first name">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Second Name</label>
                                                <input type="text" class="form-control" id="modal_nok_second_name" placeholder="Enter second name">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Last Name</label>
                                                <input type="text" class="form-control" id="modal_nok_last_name" placeholder="Enter last name">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Mobile Number</label>
                                                <input type="text" class="form-control" id="modal_nok_mobile" placeholder="254XXXXXXXXX">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" class="form-control" id="modal_nok_email" placeholder="name@example.com">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Relation</label>
                                                <select class="form-select" id="modal_nok_relation">
                                                    <option value="">Please select</option>
                                                    <option value="Spouse">Spouse</option>
                                                    <option value="Parent">Parent</option>
                                                    <option value="Sibling">Sibling</option>
                                                    <option value="Child">Child</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">National ID</label>
                                                <input type="text" class="form-control" id="modal_nok_national_id" placeholder="Enter national ID number">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary d-flex align-items-center" id="saveNextOfKinBtn">
                                            <span class="spinner-border spinner-border-sm me-2 d-none"></span>
                                            Save
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Add Partner Modal -->
                        <div class="modal fade" id="addPartnerModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Add Partner</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Name</label>
                                            <input type="text" class="form-control" id="modal_partner_name" placeholder="Enter partner's name">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">National ID</label>
                                            <input type="text" class="form-control" id="modal_partner_national_id" placeholder="Enter national ID number">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">KRA PIN</label>
                                            <input type="text" class="form-control" id="modal_partner_kra_pin" placeholder="Enter KRA PIN">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Phone</label>
                                            <input type="text" class="form-control" id="modal_partner_phone" placeholder="254XXXXXXXXX">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary d-flex align-items-center" id="savePartnerBtn">
                                            <span class="spinner-border spinner-border-sm me-2 d-none"></span>
                                            Save
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
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

        // Native HTML5 validation can't focus a field inside a hidden (inactive)
        // Bootstrap tab-pane — the browser just silently aborts the submit with no
        // visible feedback. Switch to the offending field's tab as soon as it's
        // flagged invalid, so the browser can focus it and show its error bubble.
        document.querySelectorAll('#clientEditForm [required], #clientEditForm [pattern]').forEach(function(field) {
            field.addEventListener('invalid', function() {
                var pane = this.closest('.tab-pane');
                if (pane && !pane.classList.contains('active')) {
                    var tabButton = document.getElementById(pane.id + '-tab');
                    if (tabButton) {
                        bootstrap.Tab.getOrCreateInstance(tabButton).show();
                    }
                }
            });
        });

        // Toggle Individual vs Company vs Partnership/Trust fields based on Client Type
        function toggleClientTypeFields() {
            var typeId = $('#client_type_id').val();
            var isIndividual = typeId === '1';
            var isCompany = typeId === '3';
            var isPartnership = typeId === '4';
            var isEntity = isCompany || isPartnership;

            $('.individual-only').toggle(isIndividual);
            // .company-only also covers the shared entity-name field (used by both
            // Company and Partnership) — company_registration_number/contact-person
            // fields inside it are further scoped to Company alone, below.
            $('.company-only').toggle(isEntity);
            $('.partnership-only').toggle(isPartnership);

            $('#entityInfoHeading').text(isPartnership ? 'Partnership / Trust Information' : 'Company Information');
            $('#entityNameLabel').html(isPartnership ? 'Partnership/Trust Name <span class="text-danger">*</span>' : 'Company Name <span class="text-danger">*</span>');
            $('#company_name').attr('placeholder', isPartnership ? 'Enter partnership/trust name' : 'Enter company name');

            // Registration number + contact person are Company-only, not shared with Partnership.
            $('.company-only:not(.partnership-only)').toggle(isCompany);

            // First/Last Name/Gender are required for Individual; entity name for Company/Partnership.
            $('#first_name, #last_name, #gender').prop('required', isIndividual);
            $('#company_name').prop('required', isEntity);
        }

        $('#client_type_id').on('change', toggleClientTypeFields);
        toggleClientTypeFields();

        // Live duplicate-identifier checker — a landlord can only be registered once,
        // by National ID (Individual) or Registration Number (Company). Excludes this
        // client's own record so its existing identifier isn't flagged against itself.
        function checkDuplicateIdentifier(fieldId, clientTypeId, label) {
            var $field = $('#' + fieldId);
            var $msg = $('#' + fieldId + '_duplicate');
            var value = $field.val().trim();

            $msg.addClass('d-none').text('');
            $field.removeClass('is-invalid').removeData('duplicate-flagged');

            if (!value) {
                return;
            }

            $.ajax({
                url: "{{ route('property.clients.check-identifier') }}",
                type: 'GET',
                data: {
                    client_type_id: clientTypeId,
                    value: value,
                    exclude_id: '{{ Crypt::encrypt($client->id) }}'
                },
                success: function(response) {
                    if ($field.val().trim() !== value) {
                        return;
                    }
                    if (response.exists) {
                        var details = [response.name, response.reference_number].filter(Boolean).join(' — ');
                        $msg.removeClass('d-none').text('A landlord with this ' + label + ' already exists' + (details ? ': ' + details : '') + '.');
                        $field.addClass('is-invalid').data('duplicate-flagged', true);
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

        // Block submission while a flagged duplicate is showing for the active client
        // type — runs before the spinner handler below so a blocked submit doesn't
        // leave the Update button stuck disabled.
        $('#clientEditForm').on('submit', function(e) {
            var isCompany = $('#client_type_id').val() === '3';
            var $flagged = isCompany ? $('#company_registration_number') : $('#national_id');

            if ($flagged.data('duplicate-flagged')) {
                e.preventDefault();
                e.stopImmediatePropagation();
                var tabButton = document.getElementById('personal-tab');
                if (tabButton) {
                    bootstrap.Tab.getOrCreateInstance(tabButton).show();
                }
                toastr.error('Please resolve the duplicate ' + (isCompany ? 'registration number' : 'National ID') + ' before submitting.');
            }
        });

        // Handle form submission
        $('#clientEditForm').on('submit', function() {
            var submitButton = $('#updateButton');
            var spinner = $('#submitSpinner');

            // Show spinner and disable button
            spinner.removeClass('d-none');
            submitButton.prop('disabled', true);
            submitButton.css('opacity', '0.6');
        });

        // Load banks and branches (for the Add Bank Account modal)
        $.ajax({
            url: "{{ url('/ajax/banks-and-branches') }}",
            type: 'GET',
            success: function(response) {
                if (response.banks) {
                    banksData = response.banks;
                    populateBankDropdowns();
                }
                if (response.branches) {
                    branchesData = response.branches;
                }
            },
            error: function(xhr) {
                console.error('Failed to load banks and branches:', xhr);
            }
        });

        function populateBankDropdowns() {
            let $modalBank = $('#modal_bank_id');
            $modalBank.empty().append('<option value="">Please select</option>');
            banksData.forEach(function(bank) {
                $modalBank.append(`<option value="${bank.id}">${bank.bank_name}</option>`);
            });
        }

        $(document).on('change', '#modal_bank_id', function() {
            var bankId = $(this).val();
            var $branch = $('#modal_branch_id');
            $branch.html('<option value="">Please select</option>');
            if (bankId) {
                branchesData.filter(function(b) { return b.bank_id == bankId; }).forEach(function(b) {
                    $branch.append('<option value="' + b.id + '">' + b.branch_name + '</option>');
                });
            }
        });

        // ─────────────────────────────────────────────────────────────────
        // Banks / Other Contacts / Next of Kin / Partners — modal + AJAX
        // table, hitting the real tables directly (this client already has
        // a real id). See ClientBankDetailsController / ClientOtherContactsController /
        // ClientNextOfKinController / ClientPartnersController.
        // ─────────────────────────────────────────────────────────────────
        var clientEncryptedId = $('#client_encrypted_id').val();

        function removeIcon(url, reloadKey) {
            return '<td class="text-end"><span class="material-symbols-outlined remove-real-row text-primary" role="button" title="Remove" style="font-size:20px;cursor:pointer;" data-url="' + url + '" data-reload="' + reloadKey + '">delete</span></td>';
        }

        function reloadBanksTable() {
            $.get("{{ url('/ajax/clients') }}/" + clientEncryptedId + "/bank-details").done(function(res) {
                var rows = (res && res.data) ? res.data : [];
                if (!rows.length) {
                    $('#bankAccountsTableBody').html('<tr><td colspan="6" class="text-center text-muted">No bank accounts added yet</td></tr>');
                    return;
                }
                var html = '';
                rows.forEach(function(row) {
                    html += '<tr>' +
                        '<td>' + (row.bank_name || '-') + '</td>' +
                        '<td>' + (row.branch_name || '-') + '</td>' +
                        '<td>' + (row.account_name || '-') + '</td>' +
                        '<td>' + (row.account_number || '-') + '</td>' +
                        '<td>' + (row.is_default ? '<span class="badge bg-primary">Default</span>' : '-') + '</td>' +
                        removeIcon("{{ url('/ajax/clients/bank-details') }}/" + row.encrypted_id, 'banks') +
                        '</tr>';
                });
                $('#bankAccountsTableBody').html(html);
            });
        }

        function reloadContactsTable() {
            $.get("{{ url('/ajax/clients') }}/" + clientEncryptedId + "/other-contacts").done(function(res) {
                var rows = (res && res.data) ? res.data : [];
                if (!rows.length) {
                    $('#contactsTableBody').html('<tr><td colspan="4" class="text-center text-muted">No contacts added yet</td></tr>');
                    return;
                }
                var html = '';
                rows.forEach(function(row) {
                    html += '<tr>' +
                        '<td>' + (row.names || '-') + '</td>' +
                        '<td>' + (row.email || '-') + '</td>' +
                        '<td>' + (row.mobile || '-') + '</td>' +
                        removeIcon("{{ url('/ajax/clients/other-contacts') }}/" + row.encrypted_id, 'contacts') +
                        '</tr>';
                });
                $('#contactsTableBody').html(html);
            });
        }

        function reloadNextOfKinTable() {
            $.get("{{ url('/ajax/clients') }}/" + clientEncryptedId + "/next-of-kin").done(function(res) {
                var rows = (res && res.data) ? res.data : [];
                if (!rows.length) {
                    $('#nextOfKinTableBody').html('<tr><td colspan="6" class="text-center text-muted">No next of kin added yet</td></tr>');
                    return;
                }
                var html = '';
                rows.forEach(function(row) {
                    var name = [row.first_name, row.second_name, row.last_name].filter(Boolean).join(' ');
                    html += '<tr>' +
                        '<td>' + (name || '-') + '</td>' +
                        '<td>' + (row.mobile || '-') + '</td>' +
                        '<td>' + (row.email || '-') + '</td>' +
                        '<td>' + (row.relation || '-') + '</td>' +
                        '<td>' + (row.national_id || '-') + '</td>' +
                        removeIcon("{{ url('/ajax/clients/next-of-kin') }}/" + row.encrypted_id, 'nextofkin') +
                        '</tr>';
                });
                $('#nextOfKinTableBody').html(html);
            });
        }

        function reloadPartnersTable() {
            $.get("{{ url('/ajax/clients') }}/" + clientEncryptedId + "/partners").done(function(res) {
                var rows = (res && res.data) ? res.data : [];
                if (!rows.length) {
                    $('#partnersTableBody').html('<tr><td colspan="5" class="text-center text-muted">No partners added yet</td></tr>');
                    return;
                }
                var html = '';
                rows.forEach(function(row) {
                    html += '<tr>' +
                        '<td>' + (row.name || '-') + '</td>' +
                        '<td>' + (row.national_id || '-') + '</td>' +
                        '<td>' + (row.kra_pin || '-') + '</td>' +
                        '<td>' + (row.phone || '-') + '</td>' +
                        removeIcon("{{ url('/ajax/clients/partners') }}/" + row.encrypted_id, 'partner') +
                        '</tr>';
                });
                $('#partnersTableBody').html(html);
            });
        }

        var reloadFnsReal = {
            banks: reloadBanksTable,
            contacts: reloadContactsTable,
            nextofkin: reloadNextOfKinTable,
            partner: reloadPartnersTable
        };

        $(document).on('click', '.remove-real-row', function() {
            var url = $(this).data('url');
            var reload = reloadFnsReal[$(this).data('reload')];
            $.ajax({
                url: url,
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function() {
                    toastr.success('Removed successfully');
                    reload();
                },
                error: function() {
                    toastr.error('Failed to remove entry.');
                }
            });
        });

        function saveRealRow(url, payload, modalId, resetFn, reloadFn, saveBtnId) {
            var $btn = $('#' + saveBtnId);
            var $spinner = $btn.find('.spinner-border');
            $spinner.removeClass('d-none');
            $btn.prop('disabled', true);

            $.ajax({
                url: url,
                type: 'POST',
                data: Object.assign({ _token: '{{ csrf_token() }}' }, payload),
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message || 'Added successfully');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById(modalId)).hide();
                        resetFn();
                        reloadFn();
                    } else {
                        toastr.error(res.message || 'Failed to add entry.');
                    }
                },
                error: function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to add entry.';
                    toastr.error(msg);
                },
                complete: function() {
                    $spinner.addClass('d-none');
                    $btn.prop('disabled', false);
                }
            });
        }

        $('#saveBankBtn').on('click', function() {
            saveRealRow("{{ route('ajax.clients.bank-details.store') }}", {
                client_id: clientEncryptedId,
                bank_id: $('#modal_bank_id').val(),
                branch_id: $('#modal_branch_id').val(),
                account_name: $('#modal_account_name').val(),
                account_number: $('#modal_account_number').val(),
                is_default: $('#modal_is_default').is(':checked') ? 1 : 0
            }, 'addBankModal', function() {
                $('#modal_bank_id, #modal_branch_id').val('');
                $('#modal_account_name, #modal_account_number').val('');
                $('#modal_is_default').prop('checked', false);
            }, reloadBanksTable, 'saveBankBtn');
        });

        $('#saveContactBtn').on('click', function() {
            saveRealRow("{{ route('ajax.clients.other-contacts.store') }}", {
                client_id: clientEncryptedId,
                names: $('#modal_contact_names').val(),
                email: $('#modal_contact_email').val(),
                mobile: $('#modal_contact_mobile').val()
            }, 'addContactModal', function() {
                $('#modal_contact_names, #modal_contact_email, #modal_contact_mobile').val('');
            }, reloadContactsTable, 'saveContactBtn');
        });

        $('#saveNextOfKinBtn').on('click', function() {
            saveRealRow("{{ route('ajax.clients.next-of-kin.store') }}", {
                client_id: clientEncryptedId,
                first_name: $('#modal_nok_first_name').val(),
                second_name: $('#modal_nok_second_name').val(),
                last_name: $('#modal_nok_last_name').val(),
                mobile: $('#modal_nok_mobile').val(),
                email: $('#modal_nok_email').val(),
                relation: $('#modal_nok_relation').val(),
                national_id: $('#modal_nok_national_id').val()
            }, 'addNextOfKinModal', function() {
                $('#modal_nok_first_name, #modal_nok_second_name, #modal_nok_last_name, #modal_nok_mobile, #modal_nok_email, #modal_nok_national_id').val('');
                $('#modal_nok_relation').val('');
            }, reloadNextOfKinTable, 'saveNextOfKinBtn');
        });

        $('#savePartnerBtn').on('click', function() {
            saveRealRow("{{ route('ajax.clients.partners.store') }}", {
                client_id: clientEncryptedId,
                name: $('#modal_partner_name').val(),
                national_id: $('#modal_partner_national_id').val(),
                kra_pin: $('#modal_partner_kra_pin').val(),
                phone: $('#modal_partner_phone').val()
            }, 'addPartnerModal', function() {
                $('#modal_partner_name, #modal_partner_national_id, #modal_partner_kra_pin, #modal_partner_phone').val('');
            }, reloadPartnersTable, 'savePartnerBtn');
        });

        reloadBanksTable();
        reloadContactsTable();
        reloadNextOfKinTable();
        reloadPartnersTable();

        // Mobile number validation
        $('#mobile_number').on('input', function() {
            let value = $(this).val();
            value = value.replace(/[^0-9]/g, '');
            
            if (value.length > 0 && !value.startsWith('254')) {
                value = '254' + value.replace(/^254/, '');
            }
            
            if (value.length > 12) {
                value = value.substring(0, 12);
            }
            
            $(this).val(value);
        });

        // Add Document functionality
        let documentCounter = 1;
        $('#addDocument').on('click', function() {
            const documentEntry = `
                <div class="document-entry mb-4 p-3 border rounded">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="file_type_${documentCounter}" class="form-label">File Type</label>
                            <select class="form-select" id="file_type_${documentCounter}" name="documents[${documentCounter}][file_type]">
                                <option value="">Please select</option>
                                <option value="National ID">National ID</option>
                                <option value="Passport">Passport</option>
                                <option value="PIN Certificate">PIN Certificate</option>
                                <option value="Contract">Contract</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="document_file_${documentCounter}" class="form-label">Documents</label>
                            <input type="file" class="form-control" id="document_file_${documentCounter}" name="documents[${documentCounter}][file]">
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="button" class="btn btn-danger btn-sm remove-document">
                                <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('#documentsContainer').append(documentEntry);
            documentCounter++;

            if ($('.document-entry').length > 1) {
                $('.remove-document').show();
            }
        });

        // Remove Document
        $(document).on('click', '.remove-document', function() {
            $(this).closest('.document-entry').remove();
            if ($('.document-entry').length === 1) {
                $('.remove-document').hide();
            }
        });

        // Delete existing document
        $(document).on('click', '.delete-existing-document', function() {
            if (confirm('Are you sure you want to delete this document?')) {
                const documentId = $(this).data('id');
                const row = $(this).closest('tr');
                
                $.ajax({
                    url: `/property/clients/documents/${documentId}`,
                    type: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        row.fadeOut(300, function() {
                            $(this).remove();
                            if ($('#existing-documents-table tbody tr').length === 0) {
                                $('#existing-documents-table').parent().remove();
                            }
                        });
                        alert('Document deleted successfully');
                    },
                    error: function(xhr) {
                        alert('Error deleting document');
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
                                   