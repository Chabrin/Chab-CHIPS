@extends('layouts.main')
@section('content')
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
                                                <option value="">Please select</option>
                                                <option value="1" {{ old('client_type_id', $client->client_type_id) == 1 ? 'selected' : '' }}>Landlord</option>
                                                <option value="2" {{ old('client_type_id', $client->client_type_id) == 2 ? 'selected' : '' }}>Tenant</option>
                                                <option value="3" {{ old('client_type_id', $client->client_type_id) == 3 ? 'selected' : '' }}>Both</option>
                                            </select>
                                            @error('client_type_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-2 mb-3">
                                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                            <select class="form-select @error('title') is-invalid @enderror" id="title" name="title" required>
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

                                        <div class="col-md-4 mb-3">
                                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('first_name') is-invalid @enderror" 
                                                   id="first_name" name="first_name" 
                                                   value="{{ old('first_name', $firstName) }}" required>
                                            @error('first_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="middle_name" class="form-label">Middle Name</label>
                                            <input type="text" class="form-control @error('middle_name') is-invalid @enderror" 
                                                   id="middle_name" name="middle_name" 
                                                   value="{{ old('middle_name', $middleName) }}">
                                            @error('middle_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('last_name') is-invalid @enderror" 
                                                   id="last_name" name="last_name" 
                                                   value="{{ old('last_name', $lastName) }}" required>
                                            @error('last_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                            <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required>
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

                                        <div class="col-md-4 mb-3">
                                            <label for="national_id" class="form-label">National ID</label>
                                            <input type="text" class="form-control @error('national_id') is-invalid @enderror" 
                                                   id="national_id" name="national_id" 
                                                   value="{{ old('national_id', $client->national_id) }}">
                                            @error('national_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
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

                                        <div class="col-md-4 mb-3">
                                            <label for="overdraft_penalty" class="form-label">Overdraft Penalty (%)</label>
                                            <input type="number" step="0.01" class="form-control @error('overdraft_penalty') is-invalid @enderror"
                                                   id="overdraft_penalty" name="overdraft_penalty"
                                                   value="{{ old('overdraft_penalty', $client->overdraft_penalty) }}" placeholder="0.00">
                                            @error('overdraft_penalty')
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
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <h6 class="text-muted border-bottom pb-2">Bank Account Information</h6>
                                        </div>

                                        <div class="col-12 mb-3 text-end">
                                            <button type="button" class="btn btn-success btn-sm" id="addBank">
                                                <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                                Add Bank Account
                                            </button>
                                        </div>

                                        <div id="bankAccountsContainer">
                                            @foreach($client->bankAccounts as $index => $bankAccount)
                                            <div class="bank-entry mb-4 p-3 border rounded">
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label for="bank_id_{{ $index }}" class="form-label">Bank</label>
                                                        <select class="form-select bank-select" id="bank_id_{{ $index }}" name="banks[{{ $index }}][bank_id]" data-index="{{ $index }}">
                                                            <option value="">Please select</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label for="branch_id_{{ $index }}" class="form-label">Branch</label>
                                                        <select class="form-select branch-select" id="branch_id_{{ $index }}" name="banks[{{ $index }}][branch_id]" data-index="{{ $index }}">
                                                            <option value="">Please select</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-5 mb-3">
                                                        <label for="account_name_{{ $index }}" class="form-label">Account Name</label>
                                                        <input type="text" class="form-control" id="account_name_{{ $index }}" name="banks[{{ $index }}][account_name]" value="{{ $bankAccount->account_name }}">
                                                    </div>
                                                    <div class="col-md-5 mb-3">
                                                        <label for="account_number_{{ $index }}" class="form-label">Account Number</label>
                                                        <input type="text" class="form-control" id="account_number_{{ $index }}" name="banks[{{ $index }}][account_number]" value="{{ $bankAccount->account_number }}">
                                                    </div>
                                                    <div class="col-md-5 mb-3">
                                                        <label for="return_percentage_{{ $index }}" class="form-label">Return Percentage</label>
                                                        <input type="text" class="form-control" id="return_percentage_{{ $index }}" name="banks[{{ $index }}][return_percentage]" value="{{ $bankAccount->return_percentage }}">
                                                    </div>
                                                    <div class="col-md-2 mb-3 d-flex align-items-end">
                                                        <button type="button" class="btn btn-danger btn-sm remove-bank" style="{{ $loop->first && $client->bankAccounts->count() == 1 ? 'display:none;' : '' }}">
                                                            <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            @endforeach

                                            @if($client->bankAccounts->count() == 0)
                                            <div class="bank-entry mb-4 p-3 border rounded">
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label for="bank_id_0" class="form-label">Bank</label>
                                                        <select class="form-select bank-select" id="bank_id_0" name="banks[0][bank_id]" data-index="0">
                                                            <option value="">Please select</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label for="branch_id_0" class="form-label">Branch</label>
                                                        <select class="form-select branch-select" id="branch_id_0" name="banks[0][branch_id]" data-index="0">
                                                            <option value="">Please select</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-5 mb-3">
                                                        <label for="account_name_0" class="form-label">Account Name</label>
                                                        <input type="text" class="form-control" id="account_name_0" name="banks[0][account_name]">
                                                    </div>
                                                    <div class="col-md-5 mb-3">
                                                        <label for="account_number_0" class="form-label">Account Number</label>
                                                        <input type="text" class="form-control" id="account_number_0" name="banks[0][account_number]">
                                                    </div>
                                                    <div class="col-md-5 mb-3">
                                                        <label for="return_percentage_0" class="form-label">Return Percentage</label>
                                                        <input type="text" class="form-control" id="return_percentage_0" name="banks[0][return_percentage]">
                                                    </div>
                                                    <div class="col-md-2 mb-3 d-flex align-items-end">
                                                        <button type="button" class="btn btn-danger btn-sm remove-bank" style="display:none;">
                                                            <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Other Contacts Tab -->
                                <div class="tab-pane fade" id="contacts" role="tabpanel" aria-labelledby="contacts-tab">
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <h6 class="text-muted border-bottom pb-2">Other Contact Persons</h6>
                                        </div>

                                        <div class="col-12 mb-3 text-end">
                                            <button type="button" class="btn btn-success btn-sm" id="addContact">
                                                <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                                Add Contact
                                            </button>
                                        </div>

                                        <div id="contactsContainer">
                                            @foreach($client->otherContacts as $index => $contact)
                                            <div class="contact-entry mb-4 p-3 border rounded">
                                                <div class="row">
                                                    <div class="col-md-4 mb-3">
                                                        <label for="contact_names_{{ $index }}" class="form-label">Names</label>
                                                        <input type="text" class="form-control" id="contact_names_{{ $index }}" name="contacts[{{ $index }}][names]" value="{{ $contact->contact_names }}">
                                                    </div>
                                                    <div class="col-md-3 mb-3">
                                                        <label for="contact_email_{{ $index }}" class="form-label">Email</label>
                                                        <input type="email" class="form-control" id="contact_email_{{ $index }}" name="contacts[{{ $index }}][email]" value="{{ $contact->other_email }}">
                                                    </div>
                                                    <div class="col-md-3 mb-3">
                                                        <label for="contact_mobile_{{ $index }}" class="form-label">Mobile Number</label>
                                                        <input type="text" class="form-control" id="contact_mobile_{{ $index }}" name="contacts[{{ $index }}][mobile]" value="{{ $contact->other_mobile_number }}">
                                                    </div>
                                                    <div class="col-md-2 mb-3 d-flex align-items-end">
                                                        <button type="button" class="btn btn-danger btn-sm remove-contact" style="{{ $loop->first && $client->otherContacts->count() == 1 ? 'display:none;' : '' }}">
                                                            <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            @endforeach

                                            @if($client->otherContacts->count() == 0)
                                            <div class="contact-entry mb-4 p-3 border rounded">
                                                <div class="row">
                                                    <div class="col-md-4 mb-3">
                                                        <label for="contact_names_0" class="form-label">Names</label>
                                                        <input type="text" class="form-control" id="contact_names_0" name="contacts[0][names]">
                                                    </div>
                                                    <div class="col-md-3 mb-3">
                                                        <label for="contact_email_0" class="form-label">Email</label>
                                                        <input type="email" class="form-control" id="contact_email_0" name="contacts[0][email]">
                                                    </div>
                                                    <div class="col-md-3 mb-3">
                                                        <label for="contact_mobile_0" class="form-label">Mobile Number</label>
                                                        <input type="text" class="form-control" id="contact_mobile_0" name="contacts[0][mobile]">
                                                    </div>
                                                    <div class="col-md-2 mb-3 d-flex align-items-end">
                                                        <button type="button" class="btn btn-danger btn-sm remove-contact" style="display:none;">
                                                            <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif
                                        </div>
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
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <h6 class="text-muted border-bottom pb-2">Next of Kin Information</h6>
                                        </div>

                                        <div class="col-12 mb-3 text-end">
                                            <button type="button" class="btn btn-success btn-sm" id="addNextOfKin">
                                                <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                                Add Next of Kin
                                            </button>
                                        </div>

                                        <div id="nextOfKinContainer">
                                            @foreach($client->nextOfKins as $index => $kin)
                                            <div class="nok-entry mb-4 p-3 border rounded">
                                                <div class="row">
                                                    <div class="col-md-4 mb-3">
                                                        <label for="nok_first_name_{{ $index }}" class="form-label">First Name</label>
                                                        <input type="text" class="form-control" id="nok_first_name_{{ $index }}" name="next_of_kin[{{ $index }}][first_name]" value="{{ $kin->kin_first_name }}">
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="nok_second_name_{{ $index }}" class="form-label">Second Name</label>
                                                        <input type="text" class="form-control" id="nok_second_name_{{ $index }}" name="next_of_kin[{{ $index }}][second_name]" value="{{ $kin->kin_second_name }}">
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="nok_last_name_{{ $index }}" class="form-label">Last Name</label>
                                                        <input type="text" class="form-control" id="nok_last_name_{{ $index }}" name="next_of_kin[{{ $index }}][last_name]" value="{{ $kin->kin_last_name }}">
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="nok_mobile_{{ $index }}" class="form-label">Mobile Number</label>
                                                        <input type="text" class="form-control" id="nok_mobile_{{ $index }}" name="next_of_kin[{{ $index }}][mobile]" value="{{ $kin->mobile_number }}">
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="nok_email_{{ $index }}" class="form-label">Email Address</label>
                                                        <input type="email" class="form-control" id="nok_email_{{ $index }}" name="next_of_kin[{{ $index }}][email]" value="{{ $kin->email_address }}">
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="nok_relation_{{ $index }}" class="form-label">Relation</label>
                                                        <select class="form-select" id="nok_relation_{{ $index }}" name="next_of_kin[{{ $index }}][relation]">
                                                            <option value="">Please select</option>
                                                            <option value="Spouse" {{ $kin->relationship == 'Spouse' ? 'selected' : '' }}>Spouse</option>
                                                            <option value="Parent" {{ $kin->relationship == 'Parent' ? 'selected' : '' }}>Parent</option>
                                                            <option value="Sibling" {{ $kin->relationship == 'Sibling' ? 'selected' : '' }}>Sibling</option>
                                                            <option value="Child" {{ $kin->relationship == 'Child' ? 'selected' : '' }}>Child</option>
                                                            <option value="Other" {{ $kin->relationship == 'Other' ? 'selected' : '' }}>Other</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-10 mb-3">
                                                        <label for="nok_national_id_{{ $index }}" class="form-label">National ID</label>
                                                        <input type="text" class="form-control" id="nok_national_id_{{ $index }}" name="next_of_kin[{{ $index }}][national_id]" value="{{ $kin->national_id }}">
                                                    </div>
                                                    <div class="col-md-2 mb-3 d-flex align-items-end">
                                                        <button type="button" class="btn btn-danger btn-sm remove-nok" style="{{ $loop->first && $client->nextOfKins->count() == 1 ? 'display:none;' : '' }}">
                                                            <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            @endforeach

                                            @if($client->nextOfKins->count() == 0)
                                            <div class="nok-entry mb-4 p-3 border rounded">
                                                <div class="row">
                                                    <div class="col-md-4 mb-3">
                                                        <label for="nok_first_name_0" class="form-label">First Name</label>
                                                        <input type="text" class="form-control" id="nok_first_name_0" name="next_of_kin[0][first_name]">
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="nok_second_name_0" class="form-label">Second Name</label>
                                                        <input type="text" class="form-control" id="nok_second_name_0" name="next_of_kin[0][second_name]">
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="nok_last_name_0" class="form-label">Last Name</label>
                                                        <input type="text" class="form-control" id="nok_last_name_0" name="next_of_kin[0][last_name]">
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="nok_mobile_0" class="form-label">Mobile Number</label>
                                                        <input type="text" class="form-control" id="nok_mobile_0" name="next_of_kin[0][mobile]">
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="nok_email_0" class="form-label">Email Address</label>
                                                        <input type="email" class="form-control" id="nok_email_0" name="next_of_kin[0][email]">
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="nok_relation_0" class="form-label">Relation</label>
                                                        <select class="form-select" id="nok_relation_0" name="next_of_kin[0][relation]">
                                                            <option value="">Please select</option>
                                                            <option value="Spouse">Spouse</option>
                                                            <option value="Parent">Parent</option>
                                                            <option value="Sibling">Sibling</option>
                                                            <option value="Child">Child</option>
                                                            <option value="Other">Other</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-10 mb-3">
                                                        <label for="nok_national_id_0" class="form-label">National ID</label>
                                                        <input type="text" class="form-control" id="nok_national_id_0" name="next_of_kin[0][national_id]">
                                                    </div>
                                                    <div class="col-md-2 mb-3 d-flex align-items-end">
                                                        <button type="button" class="btn btn-danger btn-sm remove-nok" style="display:none;">
                                                            <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif
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
        let existingBankData = @json($client->bankAccounts);

        // Handle form submission
        $('#clientEditForm').on('submit', function() {
            var submitButton = $('#updateButton');
            var spinner = $('#submitSpinner');

            // Show spinner and disable button
            spinner.removeClass('d-none');
            submitButton.prop('disabled', true);
            submitButton.css('opacity', '0.6');
        });

        // Load banks and branches
        $.ajax({
            url: "{{ url('/ajax/banks-and-branches') }}",
            type: 'GET',
            success: function(response) {
                if (response.banks) {
                    banksData = response.banks;
                    populateBankDropdowns();
                    
                    // Set existing bank values
                    existingBankData.forEach(function(bankAccount, index) {
                        $(`#bank_id_${index}`).val(bankAccount.bank_id).trigger('change');
                    });
                }
                if (response.branches) {
                    branchesData = response.branches;
                    
                    // Set existing branch values after loading branches
                    setTimeout(function() {
                        existingBankData.forEach(function(bankAccount, index) {
                            if (bankAccount.bank_id) {
                                loadBranchesForBank(bankAccount.bank_id, index, bankAccount.branch);
                            }
                        });
                    }, 100);
                }
            },
            error: function(xhr) {
                console.error('Failed to load banks and branches:', xhr);
            }
        });

        // Function to populate all bank dropdowns
        function populateBankDropdowns() {
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

        // Function to load branches for a specific bank
        function loadBranchesForBank(bankId, index, selectedBranchId = null) {
            var branchSelect = $('#branch_id_' + index);
            branchSelect.html('<option value="">Please select</option>');

            if (bankId) {
                var filteredBranches = branchesData.filter(function(branch) {
                    return branch.bank_id == bankId;
                });

                if (filteredBranches.length > 0) {
                    filteredBranches.forEach(function(branch) {
                        let selected = (selectedBranchId && selectedBranchId == branch.id) ? 'selected' : '';
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
            loadBranchesForBank(bankId, index);
        });

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

        // Add Bank Account functionality
        let bankCounter = {{ $client->bankAccounts->count() > 0 ? $client->bankAccounts->count() : 1 }};
        $('#addBank').on('click', function() {
            const bankEntry = `
                <div class="bank-entry mb-4 p-3 border rounded">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="bank_id_${bankCounter}" class="form-label">Bank</label>
                            <select class="form-select bank-select" id="bank_id_${bankCounter}" name="banks[${bankCounter}][bank_id]" data-index="${bankCounter}">
                                <option value="">Please select</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="branch_id_${bankCounter}" class="form-label">Branch</label>
                            <select class="form-select branch-select" id="branch_id_${bankCounter}" name="banks[${bankCounter}][branch_id]" data-index="${bankCounter}">
                                <option value="">Please select</option>
                            </select>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label for="account_name_${bankCounter}" class="form-label">Account Name</label>
                            <input type="text" class="form-control" id="account_name_${bankCounter}" name="banks[${bankCounter}][account_name]">
                        </div>
                        <div class="col-md-5 mb-3">
                            <label for="account_number_${bankCounter}" class="form-label">Account Number</label>
                            <input type="text" class="form-control" id="account_number_${bankCounter}" name="banks[${bankCounter}][account_number]">
                        </div>
                        <div class="col-md-5 mb-3">
                            <label for="return_percentage_${bankCounter}" class="form-label">Return Percentage</label>
                            <input type="text" class="form-control" id="return_percentage_${bankCounter}" name="banks[${bankCounter}][return_percentage]">
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="button" class="btn btn-danger btn-sm remove-bank">
                                <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('#bankAccountsContainer').append(bankEntry);
            bankCounter++;

            populateBankDropdowns();

            if ($('.bank-entry').length > 1) {
                $('.remove-bank').show();
            }
        });

        // Remove Bank Account
        $(document).on('click', '.remove-bank', function() {
            $(this).closest('.bank-entry').remove();
            if ($('.bank-entry').length === 1) {
                $('.remove-bank').hide();
            }
        });

        // Add Contact functionality
        let contactCounter = {{ $client->otherContacts->count() > 0 ? $client->otherContacts->count() : 1 }};
        $('#addContact').on('click', function() {
            const contactEntry = `
                <div class="contact-entry mb-4 p-3 border rounded">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="contact_names_${contactCounter}" class="form-label">Names</label>
                            <input type="text" class="form-control" id="contact_names_${contactCounter}" name="contacts[${contactCounter}][names]">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="contact_email_${contactCounter}" class="form-label">Email</label>
                            <input type="email" class="form-control" id="contact_email_${contactCounter}" name="contacts[${contactCounter}][email]">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="contact_mobile_${contactCounter}" class="form-label">Mobile Number</label>
                            <input type="text" class="form-control" id="contact_mobile_${contactCounter}" name="contacts[${contactCounter}][mobile]">
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="button" class="btn btn-danger btn-sm remove-contact">
                                <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('#contactsContainer').append(contactEntry);
            contactCounter++;

            if ($('.contact-entry').length > 1) {
                $('.remove-contact').show();
            }
        });

        // Remove Contact
        $(document).on('click', '.remove-contact', function() {
            $(this).closest('.contact-entry').remove();
            if ($('.contact-entry').length === 1) {
                $('.remove-contact').hide();
            }
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

        // Add Next of Kin functionality
        let nokCounter = {{ $client->nextOfKins->count() > 0 ? $client->nextOfKins->count() : 1 }};
        $('#addNextOfKin').on('click', function() {
            const nokEntry = `
                <div class="nok-entry mb-4 p-3 border rounded">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="nok_first_name_${nokCounter}" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="nok_first_name_${nokCounter}" name="next_of_kin[${nokCounter}][first_name]">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nok_second_name_${nokCounter}" class="form-label">Second Name</label>
                            <input type="text" class="form-control" id="nok_second_name_${nokCounter}" name="next_of_kin[${nokCounter}][second_name]">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nok_last_name_${nokCounter}" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="nok_last_name_${nokCounter}" name="next_of_kin[${nokCounter}][last_name]">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nok_mobile_${nokCounter}" class="form-label">Mobile Number</label>
                            <input type="text" class="form-control" id="nok_mobile_${nokCounter}" name="next_of_kin[${nokCounter}][mobile]">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nok_email_${nokCounter}" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="nok_email_${nokCounter}" name="next_of_kin[${nokCounter}][email]">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nok_relation_${nokCounter}" class="form-label">Relation</label>
                            <select class="form-select" id="nok_relation_${nokCounter}" name="next_of_kin[${nokCounter}][relation]">
                                <option value="">Please select</option>
                                <option value="Spouse">Spouse</option>
                                <option value="Parent">Parent</option>
                                <option value="Sibling">Sibling</option>
                                <option value="Child">Child</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-10 mb-3">
                            <label for="nok_national_id_${nokCounter}" class="form-label">National ID</label>
                            <input type="text" class="form-control" id="nok_national_id_${nokCounter}" name="next_of_kin[${nokCounter}][national_id]">
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="button" class="btn btn-danger btn-sm remove-nok">
                                <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('#nextOfKinContainer').append(nokEntry);
            nokCounter++;

            if ($('.nok-entry').length > 1) {
                $('.remove-nok').show();
            }
        });

        // Remove Next of Kin
        $(document).on('click', '.remove-nok', function() {
            $(this).closest('.nok-entry').remove();
            if ($('.nok-entry').length === 1) {
                $('.remove-nok').hide();
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
                                   