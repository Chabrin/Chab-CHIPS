@php
use Illuminate\Support\Facades\Crypt;
@endphp
@extends('layouts.main')
@section('content')
<div class="page-body staff-onboarding staff-details-page" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Staff Details</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('read_staff')
                            <a href="{{ route('staff.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
                            </a>
                            @endpermission
                            @permission('update_staff')
                            <a href="{{ route('staff.edit', Crypt::encrypt($staff->id)) }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">edit_document</span>
                                <span class="ms-1">Edit Staff</span>
                            </a>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                        <div class="alert alert-light-success alert-dismissible fade show" role="alert">
                            <strong>Success!</strong> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif
                        @if(session('error'))
                        <div class="alert alert-light-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif
                        @if(session('generated_password'))
                        <div class="alert alert-light-warning alert-dismissible fade show" role="alert">
                            <strong>System login created.</strong> Temporary password:
                            <code style="font-size:1.05em;">{{ session('generated_password') }}</code>
                            — this will not be shown again, copy it now. The staff member will be required to change it on first login.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif
                        <!-- Summary Header -->
                        @php
                            $photoUrl = ($staff->photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($staff->photo))
                                ? asset('storage/' . $staff->photo)
                                : null;

                            // Initials: first letter of the first and last name
                            $nameParts = preg_split('/\s+/', trim($staff->full_names ?? ''));
                            $initials = strtoupper(
                                mb_substr($nameParts[0] ?? '', 0, 1) .
                                (count($nameParts) > 1 ? mb_substr(end($nameParts), 0, 1) : '')
                            );
                        @endphp
                        <div class="staff-profile-summary mb-4 d-flex align-items-center">
                            <div class="me-3 flex-shrink-0">
                                @if($photoUrl)
                                <img src="{{ $photoUrl }}" alt="" 
                                     class="rounded-circle border"
                                     style="width:70px; height:70px; object-fit:cover;"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                @endif
                                <div class="rounded-circle align-items-center justify-content-center fw-bold text-white"
                                     style="width:70px; height:70px; background:#1976d2; font-size:1.5rem; display:{{ $photoUrl ? 'none' : 'flex' }};">
                                    {{ $initials ?: '?' }}
                                </div>
                            </div>
                            <div>
                                <h5 class="mb-1 d-inline"><b>{{ $staff->full_names }}</b></h5>
                                <span class="text-muted ms-2" style="font-size: 0.875rem;">
                                    <strong>Staff #:</strong> {{ $staff->staff_number }} |
                                    <strong>Dept:</strong> <span class="badge bg-primary">
                                        {{ $staff->department->department_name ?? 'N/A' }}</span>
                                </span>
                                <p class="text-muted mb-0" style="font-size: 0.8rem;">
                                    <strong>ID:</strong> {{ $staff->national_id ?? '-' }} |
                                    <strong>Email:</strong> <span style="text-decoration:underline; color: #1976d2;">{{ $staff->email_address ?? '-' }}</span> |
                                    <strong>Phone:</strong> {{ $staff->mobile_number ?? '-' }}
                                </p>
                            </div>
                        </div>

                        <!-- Tabs Navigation -->
                        <ul class="nav nav-tabs staff-details-tabs mb-4" id="staffTabs" role="tablist" aria-label="Staff information sections">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">person</span>
                                    Personal
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="employment-tab" data-bs-toggle="tab" data-bs-target="#employment" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">work</span>
                                    Work
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="banking-tab" data-bs-toggle="tab" data-bs-target="#banking" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">account_balance</span>
                                    Banking
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">contacts</span>
                                    Contacts
                                </button>
                            </li>
                              <li class="nav-item" role="presentation">
                                <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">folder_open</span>
                                    Documents
                                    <span class="badge bg-secondary ms-1">{{ $staff->employeeDocuments->count() }}</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="contract-tab" data-bs-toggle="tab" data-bs-target="#contract" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">contract</span>
                                    Contract
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="discipline-tab" data-bs-toggle="tab" data-bs-target="#discipline" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">record_voice_over</span>
                                    Disciplinary
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">punch_clock</span>
                                    Attendance
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="payslip-tab" data-bs-toggle="tab" data-bs-target="#payslip" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">receipt_long</span>
                                    Payslip
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="leave-tab" data-bs-toggle="tab" data-bs-target="#leave" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">event_available</span>
                                    Leave
                                </button>
                            </li>
                        </ul>

                        <!-- Tabs Content -->
                        <div class="tab-content" id="staffTabsContent">
                            <!-- Personal Information Tab -->
                            <div class="tab-pane fade show active" id="personal" role="tabpanel" aria-labelledby="personal-tab">
                                <div class="row g-3">
                                    <!-- Basic Information Card -->
                                    <div class="col-md-4">
                                        <div class="card h-100">
                                            <div class="card-header bg-light py-2">
                                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">badge</span>
                                                    Basic Information
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-1">
                                                    <div class="col-5"><small class="text-muted">Full Names:</small></div>
                                                    <div class="col-7"><small><strong>{{ $staff->full_names ?? '-' }}</strong></small></div>

                                                    <div class="col-5"><small class="text-muted">Staff Number:</small></div>
                                                    <div class="col-7"><small>{{ $staff->staff_number ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Gender:</small></div>
                                                    <div class="col-7"><small>{{ $staff->gender ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Date of Birth:</small></div>
                                                    <div class="col-7"><small>{{ $staff->birth_date ? $staff->birth_date->format('d M Y') : '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Birth Place:</small></div>
                                                    <div class="col-7"><small>{{ $staff->birth_place ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">National ID:</small></div>
                                                    <div class="col-7"><small>{{ $staff->national_id ?? '-' }}</small></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Identity & Status Card -->
                                    <div class="col-md-4">
                                        <div class="card h-100">
                                            <div class="card-header bg-light py-2">
                                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">fingerprint</span>
                                                    Identity & Status
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-1">
                                                    <div class="col-5"><small class="text-muted">Passport:</small></div>
                                                    <div class="col-7"><small>{{ $staff->passport ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Citizenship:</small></div>
                                                    <div class="col-7"><small>{{ $staff->citizenship ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Marital Status:</small></div>
                                                    <div class="col-7"><small>{{ $staff->marital ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Spouse Name:</small></div>
                                                    <div class="col-7"><small>{{ $staff->spouse_name ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Disability:</small></div>
                                                    <div class="col-7"><small>{{ $staff->disability ?? '-' }}</small></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Next of Kin Card -->
                                    <div class="col-md-4">
                                        <div class="card h-100">
                                            <div class="card-header bg-light py-2">
                                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">family_restroom</span>
                                                    Emergency Contact
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-1">
                                                    <div class="col-5"><small class="text-muted">Next of Kin:</small></div>
                                                    <div class="col-7"><small><strong>{{ $staff->kins_names ?? '-' }}</strong></small></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Employment Details Tab -->
                            <div class="tab-pane fade" id="employment" role="tabpanel" aria-labelledby="employment-tab">
                                <div class="row g-3">
                                    <!-- Employment Info Card -->
                                    <div class="col-md-4">
                                        <div class="card h-100">
                                            <div class="card-header bg-light py-2">
                                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">work</span>
                                                    Employment Info
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-1">
                                                    <div class="col-5"><small class="text-muted">Department:</small></div>
                                                    <div class="col-7"><small><strong>{{ $staff->department->department_name ?? '-' }}</strong></small></div>

                                                    <div class="col-5"><small class="text-muted">Position:</small></div>
                                                    <div class="col-7"><small><strong>{{ $staff->position->position_name ?? '-' }}</strong></small></div>

                                                    <div class="col-5"><small class="text-muted">Hire Date:</small></div>
                                                    <div class="col-7"><small>{{ $staff->hire_date ? $staff->hire_date->format('d M Y') : '-' }}</small></div>

                                                    <!-- <div class="col-5"><small class="text-muted">Status:</small></div>
                                                    <div class="col-7"><small><span class="badge bg-success">{{ $staff->current_status_id ?? 'Active' }}</span></small></div> -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Location & Access Card -->
                                    <div class="col-md-4">
                                        <div class="card h-100">
                                            <div class="card-header bg-light py-2">
                                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">location_on</span>
                                                    Location & Access
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-1">
                                                    <div class="col-5"><small class="text-muted">Branch ID:</small></div>
                                                    <div class="col-7"><small>{{ $staff->branch_id ?? '-' }}</small></div>

                                                    @php
                                                    $positionSlug = $staff->position->slug_name ?? null;
                                                    @endphp
                                                    @if($positionSlug === 'field_officer')
                                                    <div class="col-5"><small class="text-muted">Zone:</small></div>
                                                    <div class="col-7"><small>{{ $staff->zone->zone_name ?? '-' }}</small></div>
                                                    @elseif($positionSlug === 'zone_manager')
                                                    <div class="col-5"><small class="text-muted">Zones Managed:</small></div>
                                                    <div class="col-7">
                                                        @forelse($staff->zonesManaged as $zone)
                                                        <span class="badge badge-light-success me-1 mb-1">{{ $zone->zone_name }}</span>
                                                        @empty
                                                        <small>-</small>
                                                        @endforelse
                                                    </div>
                                                    @elseif($positionSlug === 'zone_supervisor')
                                                    <div class="col-5"><small class="text-muted">Zones Supervised:</small></div>
                                                    <div class="col-7">
                                                        @forelse($staff->zonesSupervised as $zone)
                                                        <span class="badge badge-light-warning me-1 mb-1">{{ $zone->zone_name }}</span>
                                                        @empty
                                                        <small>-</small>
                                                        @endforelse
                                                    </div>
                                                    @endif

                                                    <div class="col-5"><small class="text-muted">Zone Area:</small></div>
                                                    <div class="col-7"><small>{{ $staff->zoneArea->area_name ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Username:</small></div>
                                                    <div class="col-7"><small>{{ $staff->username ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Permission Group:</small></div>
                                                    <div class="col-7"><small>{{ $staff->permission_group_id ?? '-' }}</small></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Registration Card -->
                                    <div class="col-md-4">
                                        <div class="card h-100">
                                            <div class="card-header bg-light py-2">
                                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">event</span>
                                                    Registration
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-1">
                                                    <div class="col-5"><small class="text-muted">Date Registered:</small></div>
                                                    <div class="col-7"><small>{{ $staff->date_time ? $staff->date_time->format('d M Y H:i') : '-' }}</small></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Banking & Statutory Tab -->
                            <div class="tab-pane fade" id="banking" role="tabpanel" aria-labelledby="banking-tab">
                                <div class="row g-3">
                                    <!-- Bank Details Card -->
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-light py-2">
                                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">account_balance</span>
                                                    Bank Details
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-1">
                                                    <div class="col-5"><small class="text-muted">Bank Name:</small></div>
                                                    <div class="col-7"><small><strong>{{ $staff->bank->bank_name ?? '-' }}</strong></small></div>

                                                    <div class="col-5"><small class="text-muted">Account Name:</small></div>
                                                    <div class="col-7"><small>{{ $staff->account_name ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Account Number:</small></div>
                                                    <div class="col-7"><small><strong>{{ $staff->account_number ?? '-' }}</strong></small></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Statutory Numbers Card -->
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-light py-2">
                                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">quick_reference_all</span>
                                                    Statutory Numbers
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-1">
                                                    <div class="col-5"><small class="text-muted">PIN Number:</small></div>
                                                    <div class="col-7"><small>{{ $staff->pin_number ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">SHA Number:</small></div>
                                                    <div class="col-7"><small>{{ $staff->nhif_number ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">NSSF Number:</small></div>
                                                    <div class="col-7"><small>{{ $staff->nssf_number ?? '-' }}</small></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact & Location Tab -->
                            <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">
                                <div class="row g-3">
                                    <!-- Contact Information Card -->
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-light py-2">
                                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">contact_phone</span>
                                                    Contact Information
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-1">
                                                    <div class="col-5"><small class="text-muted">Mobile Number:</small></div>
                                                    <div class="col-7"><small><strong>{{ $staff->mobile_number ?? '-' }}</strong></small></div>

                                                    <div class="col-5"><small class="text-muted">Email Address:</small></div>
                                                    <div class="col-7"><small>{{ $staff->email_address ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Personal Email:</small></div>
                                                    <div class="col-7"><small>{{ $staff->personal_email ?? '-' }}</small></div>

                                                    <div class="col-12 mt-2"><small class="text-muted">Address:</small></div>
                                                    <div class="col-12"><small>{{ $staff->address ?? '-' }}</small></div>

                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Location Details Card -->
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-light py-2">
                                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">location_on</span>
                                                    Location Details
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-1">
                                                    <div class="col-5"><small class="text-muted">District:</small></div>
                                                    <div class="col-7"><small>{{ $staff->district ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Location:</small></div>
                                                    <div class="col-7"><small>{{ $staff->location ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Sub Location:</small></div>
                                                    <div class="col-7"><small>{{ $staff->sub_location ?? '-' }}</small></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Documents Tab -->
                            <div class="tab-pane fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
                                <div class="card">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0" style="font-size: 0.9rem;">
                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">folder_open</span>
                                            Staff Documents
                                        </h6>
                                    </div>
                                    <div class="card-body py-2">
                                        @if($staff->employeeDocuments->count())
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover table-bordered mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width:50px;">#</th>
                                                        <th>Document Type</th>
                                                        <th>File Name</th>
                                                        <th style="width:90px;">Size</th>
                                                        <th>Uploaded By</th>
                                                        <th style="width:130px;">Date Uploaded</th>
                                                        <th style="width:150px;">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($staff->employeeDocuments as $i => $doc)
                                                    <tr>
                                                        <td>{{ $i + 1 }}</td>
                                                        <td>
                                                            <strong>{{ $doc->documentType->name ?? $doc->title }}</strong>
                                                            @if($doc->documentType && $doc->documentType->is_mandatory)
                                                            <span class="badge bg-danger ms-1">Required</span>
                                                            @endif
                                                        </td>
                                                        <td>{{ $doc->file_name }}</td>
                                                        <td>
                                                            {{ $doc->file_size >= 1024
                                                                ? number_format($doc->file_size / 1024, 1) . ' MB'
                                                                : $doc->file_size . ' KB' }}
                                                        </td>
                                                        <td>{{ $doc->uploader->name ?? '-' }}</td>
                                                        <td>{{ $doc->created_at ? $doc->created_at->format('d M Y') : '-' }}</td>
                                                        <td>
                                                            <a href="{{ route('documents.view', $doc->id) }}" target="_blank"
                                                               class="btn btn-sm btn-outline-primary">
                                                                <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">visibility</span> View
                                                            </a>
                                                            <a href="{{ route('documents.download', $doc->id) }}"
                                                               class="btn btn-sm btn-outline-success">
                                                                <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">download</span> Download
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        @else
                                        <div class="text-muted" style="font-size: 0.875rem;">No documents have been uploaded for this staff member.</div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Contract Tab -->
                            <div class="tab-pane fade" id="contract" role="tabpanel" aria-labelledby="contract-tab">
                                @if($staff->contract)
                                @if($staff->contract->current_status_id != 5)
                                <div class="alert alert-warning d-flex align-items-center justify-content-between">
                                    <span>This contract is inactive.</span>
                                    <a href="{{ route('contract.create', ['staff_id' => $staff->id, 'position_id' => $staff->position_id]) }}"
                                       class="btn btn-primary btn-sm">Add New Contract</a>
                                </div>
                                @endif
                                <div class="card mb-3">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0" style="font-size: 0.9rem;">
                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">contract</span>
                                            Contract Details
                                        </h6>
                                    </div>
                                    <div class="card-body py-2">
                                        <div class="row g-2">
                                            <div class="col-md-6"><small class="text-muted">Staff Number:</small> <strong>{{ $staff->contract->staff_number ?? '-' }}</strong></div>
                                            <div class="col-md-6"><small class="text-muted">Position:</small> <strong>{{ $staff->contract->position->position_name ?? '-' }}</strong></div>
                                            <div class="col-md-6"><small class="text-muted">Date Start:</small> <strong>{{ $staff->contract->date_start ? \Carbon\Carbon::parse($staff->contract->date_start)->format('d M Y') : '-' }}</strong></div>
                                            <div class="col-md-6"><small class="text-muted">Date End:</small> <strong>{{ $staff->contract->date_end ? \Carbon\Carbon::parse($staff->contract->date_end)->format('d M Y') : '-' }}</strong></div>
                                            <div class="col-md-6"><small class="text-muted">Basic Salary:</small> <strong>{{ number_format($staff->contract->basic_salary, 2) }}</strong></div>
                                            <div class="col-md-6"><small class="text-muted">Status:</small> <strong>{{ $staff->contract->current_status_id == 5 ? 'Active' : 'Inactive' }}</strong></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0" style="font-size: 0.9rem;">
                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">list_alt</span>
                                            Contract Elements
                                        </h6>
                                    </div>
                                    <div class="card-body py-2">
                                        @if($staff->contract->elements && $staff->contract->elements->count())
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Element Name</th>
                                                    <th>Entry Type</th>
                                                    <th>Amount</th>
                                                </tr>
                                            </thead>
                                           <tbody>
                                            @php
                                                $nssfFormulaId = (int) config('nssf.formula_id');
                                            @endphp

                                            @foreach($staff->contract->elements as $i => $element)
                                            @php
                                                $isNssf = $element->payrollElement
                                                    && (int) $element->payrollElement->formula_id === $nssfFormulaId;
                                            @endphp
                                            <tr>
                                                <td>{{ $i + 1 }}</td>
                                                <td>{{ $element->payrollElement->element_name ?? '-' }}</td>
                                                <td>
                                                    @if($element->entry_type_id == 2)
                                                    Allowance
                                                    @elseif($element->entry_type_id == 1)
                                                    Deduction
                                                    @else
                                                    -
                                                    @endif
                                                </td>
                                                <td>{{ number_format($element->amount, 2) }}</td>
                                            </tr>

                                            @if($isNssf)
                                                @php $nssf = $nssfBreakdown; @endphp
                                                @foreach($nssf['tiers'] as $tier)
                                                <tr class="table-light">
                                                    <td></td>
                                                    <td class="ps-4"><small class="text-muted">{{ $tier['name'] }}</small></td>
                                                    <td></td>
                                                    <td><small class="text-muted">{{ number_format($tier['amount'], 2) }}</small></td>
                                                </tr>
                                                @endforeach
                                                @if(abs($nssf['total'] - (float) $element->amount) > 0.01)
                                                <tr>
                                                    <td></td>
                                                    <td colspan="3">
                                                        <small class="text-warning">
                                                            The saved NSSF amount differs from the current calculation ({{ number_format($nssf['total'], 2) }}). Re-save the contract to update it.
                                                        </small>
                                                    </td>
                                                </tr>
                                                @endif
                                            @endif
                                            @endforeach
                                        </tbody>
                                        </table>
                                        @else
                                        <div class="text-muted">No contract elements found.</div>
                                        @endif
                                    </div>
                                </div>
                                @else
                                {{-- No contract at all: offer a direct link to create one for this staff member --}}
                                <div class="card">
                                    <div class="card-body text-center py-5">
                                        <span class="material-symbols-outlined" style="font-size:48px; color:var(--chabrin-green);">contract</span>
                                        <h6 class="mt-2 mb-1">No contract found for this staff member</h6>
                                        <p class="text-muted small mb-3">A contract is required before payroll can be processed for this staff member.</p>
                                        <a href="{{ route('contract.create', ['staff_id' => $staff->id, 'position_id' => $staff->position_id]) }}"
                                           class="btn btn-primary btn-sm d-inline-flex align-items-center">
                                            <span class="material-symbols-outlined me-1" style="font-size:18px;">add_circle</span>
                                            Add Contract
                                        </a>
                                    </div>
                                </div>
                                @endif
                            </div>

                            <!-- Disciplinary Records Tab -->
                            <div class="tab-pane fade" id="discipline" role="tabpanel" aria-labelledby="discipline-tab">
                                
                                @php
                                    // Only show cases that have reached step 7 or beyond (employee is formally notified)
                                    $visibleCases = $staff->disciplineCases->filter(function($case) {
                                        return $case->current_step >= 7;
                                    });
                            
                                    // Map internal steps to employee-facing steps
                                    function getEmployeeStep($internalStep) {
                                        $stepMapping = [
                                            7 => 1,  // Show Cause Notice Issued
                                            8 => 2,  // Employee Response
                                            9 => 3,  // Disciplinary Hearing
                                            10 => 4, // Hearing Minutes
                                            11 => 5, // Outcome Decision
                                            12 => 6, // Training Assignment (if applicable)
                                            13 => 7, // History Update
                                            14 => 8, // Case Closure
                                        ];
                                        return $stepMapping[$internalStep] ?? 1;
                                    }
                            
                                    function getEmployeeStepName($internalStep) {
                                        $stepNames = [
                                            7 => 'Show Cause Notice Received',
                                            8 => 'Your Response Required',
                                            9 => 'Hearing Scheduled',
                                            10 => 'Hearing Completed',
                                            11 => 'Decision Issued',
                                            12 => 'Training Assignment',
                                            13 => 'Record Updated',
                                            14 => 'Case Closed',
                                        ];
                                        return $stepNames[$internalStep] ?? 'In Progress';
                                    }
                                @endphp
                                
                                <!-- Cases Against This Staff -->
                                <div class="card mb-3">
                                    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0" style="font-size: 0.9rem;">
                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">gavel</span>
                                            My Discipline Cases
                                        </h6>
                                        <span class="badge bg-danger text-white">{{ $visibleCases->count() }}</span>
                                    </div>
                                    <div class="card-body py-2">
                                        @if($visibleCases->count() > 0)
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover table-bordered mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width: 120px;">Case #</th>
                                                        <th style="width: 150px;">Date Received</th>
                                                        <th>Allegation</th>
                                                        <th style="width: 100px;">Severity</th>
                                                        <th style="width: 150px;">Current Stage</th>
                                                        <th style="width: 120px;">Status</th>
                                                        <th style="width: 200px;">Actions Required</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($visibleCases as $case)
                                                    <tr>
                                                        <td><strong>{{ $case->case_number }}</strong></td>
                                                        <td>
                                                            @if($case->showCauseNotice)
                                                                {{ $case->showCauseNotice->issue_date ? \Carbon\Carbon::parse($case->showCauseNotice->issue_date)->format('d M Y') : '-' }}
                                                            @else
                                                                {{ $case->date_time ? $case->date_time->format('d M Y') : '-' }}
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($case->showCauseNotice)
                                                                {{ Str::limit($case->showCauseNotice->allegations, 60) }}
                                                            @elseif($case->incidentReports->first())
                                                                {{ Str::limit($case->incidentReports->first()->incident_description, 60) }}
                                                            @else
                                                                -
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($case->severity_level == 'Minor')
                                                                <span class="badge bg-warning">Minor</span>
                                                            @elseif($case->severity_level == 'Major')
                                                                <span class="badge bg-danger">Major</span>
                                                            @elseif($case->severity_level == 'Gross')
                                                                <span class="badge bg-dark">Gross</span>
                                                            @else
                                                                <span class="badge bg-secondary">{{ $case->severity_level }}</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <div><small class="text-muted">{{ getEmployeeStepName($case->current_step) }}</small></div>
                                                            <span class="badge bg-info">Step {{ getEmployeeStep($case->current_step) }} of 8</span>
                                                        </td>
                                                        <td>
                                                            @if($case->closed_at)
                                                                <span class="badge bg-secondary">Closed</span>
                                                            @else
                                                                <span class="badge bg-success">Active</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @php
                                                                $pendingActions = [];
                                                                
                                                                // Calculate deadline status
                                                                if($case->showCauseNotice && $case->showCauseNotice->response_deadline) {
                                                                    $originalDeadline = \Carbon\Carbon::parse($case->showCauseNotice->response_deadline);
                                                                    $currentDeadline = $originalDeadline;
                                                                    
                                                                    // Check for approved extensions (use collection, not query)
                                                                    $latestExtension = $case->deadlineExtensions
                                                                        ->where('request_status', 'Approved')
                                                                        ->whereNotNull('new_deadline')
                                                                        ->sortByDesc('extended_at')
                                                                        ->first();
                                                                    
                                                                    if ($latestExtension) {
                                                                        $currentDeadline = \Carbon\Carbon::parse($latestExtension->new_deadline);
                                                                    }
                                                                    
                                                                    $now = \Carbon\Carbon::now();
                                                                    $deadlineExpired = $now->greaterThan($currentDeadline);
                                                                    $hoursRemaining = $now->diffInHours($currentDeadline, false);
                                                                    
                                                                    // Check for pending extension request by this employee (use collection, not query)
                                                                    $pendingRequest = $case->deadlineExtensions
                                                                        // ->where('requested_by', $staff->id)
                                                                        ->where('request_status', 'Pending')
                                                                        ->whereNull('reviewed_at')
                                                                        ->first();
                                                                    
                                                                    // Step 7 or 8: Employee Response needed
                                                                    if(($case->current_step == 7 || $case->current_step == 8) && !$case->employeeResponse) {
                                                                        if($deadlineExpired) {
                                                                            // Deadline expired
                                                                            $pendingActions[] = '<div class="mb-2"><span class="badge bg-danger">Deadline Expired: '.$currentDeadline->format('d M Y H:i').'</span></div>';
                                                                            
                                                                            if($pendingRequest) {
                                                                                // Show pending request badge
                                                                                $pendingActions[] = '<div><span class="badge bg-warning text-dark">
                                                                                    <span class="material-symbols-outlined" style="font-size:12px; vertical-align:middle;">pending</span>
                                                                                    Extension Request Pending
                                                                                </span></div>';
                                                                            } else {
                                                                                // Show request extension button
                                                                                $pendingActions[] = '<button type="button" class="btn btn-sm btn-info" onclick="openExtensionRequestModal(\''.$case->id.'\', \''.$case->case_number.'\', \''.$currentDeadline->format('d M Y H:i').'\')">
                                                                                    <span class="material-symbols-outlined" style="font-size:14px;">schedule_send</span>
                                                                                    Request Extension
                                                                                </button>';
                                                                            }
                                                                        } elseif($hoursRemaining > 0 && $hoursRemaining <= 24) {
                                                                            // Urgent - less than 24 hours
                                                                            $pendingActions[] = '<div class="mb-2"><span class="badge bg-warning text-dark">Urgent: '.round($hoursRemaining).'h remaining</span></div>';
                                                                            
                                                                            if($pendingRequest) {
                                                                                $pendingActions[] = '<div class="mb-2"><span class="badge bg-info text-dark" style="font-size:0.7rem;">Extension Request Pending</span></div>';
                                                                            }
                                                                            
                                                                            $pendingActions[] = '<button type="button" class="btn btn-sm btn-warning" onclick="openResponseModal(\''.$case->id.'\', \''.$case->case_number.'\', \''.$currentDeadline->format('d M Y H:i').'\')">
                                                                                <span class="material-symbols-outlined" style="font-size:14px;">edit_note</span>
                                                                                Respond Now
                                                                            </button>';
                                                                        } else {
                                                                            // Normal - deadline not yet expired
                                                                            $pendingActions[] = '<div class="mb-2"><span class="badge bg-info">Due: '.$currentDeadline->format('d M Y H:i').'</span></div>';
                                                                            
                                                                            if($pendingRequest) {
                                                                                $pendingActions[] = '<div class="mb-2"><span class="badge bg-info text-dark" style="font-size:0.7rem;">Extension Request Pending</span></div>';
                                                                            }
                                                                            
                                                                            $pendingActions[] = '<button type="button" class="btn btn-sm btn-primary d-flex align-items-center" onclick="openResponseModal(\''.$case->id.'\', \''.$case->case_number.'\', \''.$currentDeadline->format('d M Y H:i').'\')">
                                                                                <span class="material-symbols-outlined" style="font-size:14px;">edit_note</span>
                                                                                Respond
                                                                            </button>';
                                                                        }
                                                                    }
                                                                }
                                                                
                                                                // Step 9-10: Hearing Attendance
                                                                if(in_array($case->current_step, [9, 10]) && $case->hearings->count()) {
                                                                    $hearing = $case->hearings->first();
                                                                    if($hearing) {
                                                                        $hearingDate = \Carbon\Carbon::parse($hearing->hearing_date)->format('d M Y H:i');
                                                                        $pendingActions[] = '<span class="badge bg-primary">Hearing: '.$hearingDate.'</span>';
                                                                    }
                                                                }
                                                            @endphp
                                                            @if(count($pendingActions))
                                                                {!! implode(' ', $pendingActions) !!}
                                                            @else
                                                                <span class="text-muted" style="font-size: 0.75rem;">No action needed</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        @else
                                        <div class="text-muted" style="font-size: 0.875rem;">No formal discipline notices have been issued to you.</div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Cases Where Staff is Investigator -->
                                @if($staff->investigationsAssigned && $staff->investigationsAssigned->count())
                                <div class="card mb-3">
                                    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0" style="font-size: 0.9rem;">
                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">search</span>
                                            Assigned Investigations
                                        </h6>
                                        <span class="badge bg-primary text-white">{{ $staff->investigationsAssigned->count() }}</span>
                                    </div>
                                    <div class="card-body py-2">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover table-bordered mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Case #</th>
                                                        <th>Subject</th>
                                                        <th>Assigned Date</th>
                                                        <th>Status</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($staff->investigationsAssigned as $investigation)
                                                    @php $case = $investigation->case; @endphp
                                                    <tr>
                                                        <td><strong>{{ $case->case_number }}</strong></td>
                                                        <td>{{ $case->staff->full_names ?? '-' }}</td>
                                                        <td>{{ $investigation->assigned_at ? \Carbon\Carbon::parse($investigation->assigned_at)->format('d M Y') : '-' }}</td>
                                                        <td>
                                                            @if($investigation->status == 'Completed')
                                                                <span class="badge bg-success">Completed</span>
                                                            @elseif($investigation->status == 'In Progress')
                                                                <span class="badge bg-warning text-dark">In Progress</span>
                                                            @else
                                                                <span class="badge bg-secondary">{{ $investigation->status }}</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <a href="{{ route('discipline.tracker.show', Crypt::encrypt($case->id)) }}" 
                                                            class="btn btn-sm btn-outline-primary d-flex align-items-center">
                                                                <span class="material-symbols-outlined" style="font-size:16px;">visibility</span>
                                                                View
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <!-- Cases Where Staff Reported Incident -->
                                @if($staff->incidentReports && $staff->incidentReports->count())
                                <div class="card mb-3">
                                    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0" style="font-size: 0.9rem;">
                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">report</span>
                                            Reported Incidents
                                        </h6>
                                        <span class="badge bg-info text-white">{{ $staff->incidentReports->count() }}</span>
                                    </div>
                                    <div class="card-body py-2">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover table-bordered mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Case #</th>
                                                        <th>Incident Date</th>
                                                        <th>Description</th>
                                                        <th>Subject</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($staff->incidentReports as $report)
                                                    @php $case = $report->case; @endphp
                                                    <tr>
                                                        <td><strong>{{ $case->case_number }}</strong></td>
                                                        <td>{{ $report->incident_date_time ? \Carbon\Carbon::parse($report->incident_date_time)->format('d M Y') : '-' }}</td>
                                                        <td>{{ Str::limit($report->incident_description, 60) }}</td>
                                                        <td>{{ $case->staff->full_names ?? '-' }}</td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <!-- Cases Where Staff is Witness -->
                                @if($staff->witnessStatements && $staff->witnessStatements->count())
                                <div class="card mb-3">
                                    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0" style="font-size: 0.9rem;">
                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">contact_support</span>
                                            Witness Statements Provided
                                        </h6>
                                        <span class="badge bg-secondary text-white">{{ $staff->witnessStatements->count() }}</span>
                                    </div>
                                    <div class="card-body py-2">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover table-bordered mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Case #</th>
                                                        <th>Statement Date</th>
                                                        <th>Subject</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($staff->witnessStatements as $statement)
                                                    @php $case = $statement->case; @endphp
                                                    <tr>
                                                        <td><strong>{{ $case->case_number }}</strong></td>
                                                        <td>{{ $statement->statement_date ? \Carbon\Carbon::parse($statement->statement_date)->format('d M Y') : '-' }}</td>
                                                        <td>{{ $case->staff->full_names ?? '-' }}</td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                @endif
                                <!-- Rest of sections... -->
                            </div>

                            <!-- Attendance Records Tab -->
                            <div class="tab-pane fade" id="attendance" role="tabpanel" aria-labelledby="attendance-tab">
                                <div class="card mb-3">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0" style="font-size: 0.9rem;">
                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">punch_clock</span>
                                            Attendance Records
                                        </h6>
                                    </div>
                                    <div class="card-body py-2">
                                        @if($staff->employee_attendances && $staff->employee_attendances->count())
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Date/Time</th>
                                                    <th>Month/Year</th>
                                                    <th>Time In</th>
                                                    <th>Time Out</th>
                                                    <th>Hours</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($staff->employee_attendances as $i => $attendance)
                                                @php
                                                $timed = $attendance->employee_attendance_timed->first();
                                                @endphp
                                                <tr>
                                                    <td>{{ $i + 1 }}</td>
                                                    <td>{{ $attendance->date_time ? \Carbon\Carbon::parse($attendance->date_time)->format('d M Y H:i') : '-' }}</td>
                                                    <td>{{ $attendance->month_year ?? '-' }}</td>
                                                    <td>{{ $timed ? \Carbon\Carbon::parse($timed->time_in)->format('H:i') : '-' }}</td>
                                                    <td>{{ $timed ? \Carbon\Carbon::parse($timed->time_out)->format('H:i') : '-' }}</td>
                                                    <td>{{ $timed ? $timed->hours : '-' }}</td>
                                                    <td>
                                                        @if($attendance->current_status_id == 1)
                                                        Pending
                                                        @else
                                                        {{ $attendance->current_status_id }}
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                        @else
                                        <div class="text-muted">No attendance records found for this staff member.</div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Payslip Tab -->
                            <div class="tab-pane fade" id="payslip" role="tabpanel" aria-labelledby="payslip-tab">
                                <div class="row mb-3 align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label">Select Month</label>
                                        <input type="month" class="form-control" id="payslip_month" value="{{ date('Y-m') }}">
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-primary w-100" id="viewPayslipBtn">
                                            <span class="spinner-border spinner-border-sm me-1 d-none" id="payslipSpinner"></span>
                                            View Payslip
                                        </button>
                                    </div>
                                    <div class="col-md-3 d-none" id="payslipDownloadWrapper">
                                        <a href="#" class="btn btn-outline-success w-100" id="payslipDownloadLink" target="_blank">
                                            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">download</span>
                                            Download PDF
                                        </a>
                                    </div>
                                </div>
                                <div id="payslipPreviewWrapper" class="d-none">
                                    <div id="payslipPreviewContent"></div>
                                </div>
                                <div id="payslipNoResult" class="alert alert-info d-none">No payslip found for the selected period.</div>
                            </div>

                            <!-- Leave Tab -->
                            <div class="tab-pane fade" id="leave" role="tabpanel" aria-labelledby="leave-tab">
                                <div class="mb-3 d-flex justify-content-end">
                                    <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#leaveApplicationModal">
                                        <span class="material-symbols-outlined me-1">add_circle</span>
                                        New Leave Application
                                    </button>
                                </div>
                                <div class="table-responsive">
                                    <table class="display" id="staffLeaveTable" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Leave Type</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Days</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leave Application Modal -->
<div class="modal fade" id="leaveApplicationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Leave Application — {{ $staff->full_names }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="staffLeaveForm">
                    <input type="hidden" name="staff_id" value="{{ $staff->id }}">
                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <label class="form-label">Leave Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="leave_type_id" id="sl_leave_type_id" required>
                                <option value="">Select leave type...</option>
                            </select>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label class="form-label">Available Days</label>
                            <input type="number" class="form-control" name="available_days" id="sl_available_days" readonly>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label">Leave Duration <span class="text-danger">*</span></label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="leave_duration_type" id="sl_full_day" value="full_day" checked>
                                <label class="btn btn-outline-primary" for="sl_full_day">Full Day(s)</label>
                                <input type="radio" class="btn-check" name="leave_duration_type" id="sl_partial_day" value="partial_day">
                                <label class="btn btn-outline-primary" for="sl_partial_day">Hours Only</label>
                            </div>
                        </div>
                    </div>
                    <div id="sl_full_days_section">
                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <label class="form-label">Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="start_date" id="sl_start_date">
                            </div>
                            <div class="col-lg-6 mb-3">
                                <label class="form-label">Requested Days <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="requested_days" id="sl_requested_days" min="0.5" step="0.5">
                            </div>
                        </div>
                    </div>
                    <div id="sl_hours_section" style="display:none;">
                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <label class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="leave_date_hours" id="sl_leave_date_hours">
                            </div>
                            <div class="col-lg-6 mb-3">
                                <label class="form-label">Time Range</label>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="time" class="form-control" name="start_time_only" id="sl_start_time_only" value="08:00">
                                    <span>to</span>
                                    <input type="time" class="form-control" name="end_time_only" id="sl_end_time_only" value="17:00">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="staffLeaveSubmitBtn">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="staffLeaveSpinner"></span>
                    Submit Application
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Chabrin staff profile: scoped styles so other pages remain unchanged. */
    .staff-details-page {
        --chabrin-green: #477b63;
        --chabrin-green-dark: #315b48;
        --chabrin-green-soft: #edf5f0;
        --chabrin-border: #e3eae6;
        --chabrin-muted: #6b7780;
        padding-bottom: 28px;
        color: #26332d;
    }
    .staff-details-page > .container-fluid > .row > .col-sm-12 > .card {
        border: 1px solid var(--chabrin-border);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 8px 28px rgba(28, 56, 41, .055);
    }
    .staff-details-page > .container-fluid > .row > .col-sm-12 > .card > .card-header {
        padding: 18px 22px;
        background: #fff;
        border-bottom: 1px solid var(--chabrin-border);
    }
    .staff-details-page > .container-fluid > .row > .col-sm-12 > .card > .card-header h5 {
        font-size: 1.12rem;
        font-weight: 700;
        color: #263b30;
        letter-spacing: -.02em;
    }
    .staff-details-page > .container-fluid > .row > .col-sm-12 > .card > .card-body {
        padding: 22px;
        background: #fbfcfb;
    }
    .staff-details-page .btn {
        border-radius: 8px;
        font-weight: 600;
        transition: transform .15s ease, box-shadow .15s ease, background-color .15s ease;
    }
    .staff-details-page .btn:hover { transform: translateY(-1px); }
    .staff-details-page .btn-primary {
        background: var(--chabrin-green);
        border-color: var(--chabrin-green);
    }
    .staff-details-page .btn-primary:hover,
    .staff-details-page .btn-primary:focus {
        background: var(--chabrin-green-dark);
        border-color: var(--chabrin-green-dark);
        box-shadow: 0 4px 12px rgba(71, 123, 99, .18);
    }
    .staff-profile-summary {
        gap: 16px;
        padding: 20px 22px;
        background: linear-gradient(115deg, #f0f7f2 0%, #ffffff 78%);
        border: 1px solid #dce9df;
        border-radius: 14px;
    }
    .staff-profile-summary img,
    .staff-profile-summary > div:first-child > div {
        width: 82px !important;
        height: 82px !important;
        border: 3px solid #fff;
        box-shadow: 0 2px 9px rgba(30, 70, 46, .12);
    }
    .staff-profile-summary > div:last-child { min-width: 0; }
    .staff-profile-summary h5 {
        display: block !important;
        margin-bottom: 7px !important;
        color: #263b30;
        font-size: 1.3rem;
        font-weight: 750;
        letter-spacing: -.025em;
    }
    .staff-profile-summary > div:last-child > span {
        display: inline-flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 5px;
        margin: 0 0 7px !important;
        color: #53635a !important;
    }
    .staff-profile-summary .badge.bg-primary {
        color: #315b48;
        background: #dcece1 !important;
        font-weight: 600;
    }
    .staff-profile-summary p {
        line-height: 1.8;
        overflow-wrap: anywhere;
    }
    .staff-profile-summary p span { color: var(--chabrin-green) !important; }
    .staff-details-tabs {
        display: flex;
        flex-wrap: nowrap;
        gap: 5px;
        overflow-x: auto;
        overflow-y: hidden;
        padding: 6px 6px 0;
        border-bottom: 1px solid var(--chabrin-border);
        scrollbar-width: thin;
        scrollbar-color: #c4d8ca transparent;
    }
    .staff-details-tabs .nav-item { flex: 0 0 auto; }
    .staff-details-tabs .nav-link {
        display: flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        padding: 11px 13px;
        color: #68766e;
        border: 1px solid transparent;
        border-radius: 9px 9px 0 0;
        font-size: .84rem;
        font-weight: 600;
    }
    .staff-details-tabs .nav-link:hover {
        color: var(--chabrin-green-dark);
        background: #f1f6f2;
        border-color: #e7eee9;
    }
    .staff-details-tabs .nav-link.active {
        color: var(--chabrin-green-dark);
        background: #fff;
        border-color: var(--chabrin-border) var(--chabrin-border) #fff;
        box-shadow: inset 0 3px 0 var(--chabrin-green);
    }
    .staff-details-tabs .material-symbols-outlined { font-size: 19px !important; }
    .staff-details-tabs .badge { background: var(--chabrin-green) !important; font-weight: 600; }
    .staff-details-page .tab-content { padding-top: 2px; }
    .staff-details-page .tab-content .card {
        height: 100%;
        border: 1px solid var(--chabrin-border);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 3px 12px rgba(30, 60, 43, .035);
        background: #fff;
    }
    .staff-details-page .tab-content .card-header {
        padding: 13px 15px !important;
        background: #f5f8f6 !important;
        border-bottom: 1px solid var(--chabrin-border);
    }
    .staff-details-page .tab-content .card-header h6 {
        display: flex;
        align-items: center;
        gap: 7px;
        color: #334c3d;
        font-size: .9rem !important;
        font-weight: 700;
    }
    .staff-details-page .tab-content .card-header .material-symbols-outlined {
        color: var(--chabrin-green);
        font-size: 19px !important;
    }
    .staff-details-page .tab-content .card-body { padding: 15px !important; }
    .staff-details-page .tab-content .row.g-1 > div {
        padding-top: 7px;
        padding-bottom: 7px;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }
    .staff-details-page .tab-content .row.g-1 > div:nth-child(odd) {
        color: var(--chabrin-muted);
    }
    .staff-details-page .tab-content .row.g-1 > div:nth-child(even) {
        color: #2d3c33;
    }
    .staff-details-page .tab-content .row.g-1 > div:nth-child(n+3) {
        border-top: 1px solid #f0f3f1;
    }
    .staff-details-page .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 20;
        vertical-align: middle;
    }
    .staff-details-page .alert { border-radius: 10px; }
    .staff-details-page .table { --bs-table-bg: transparent; }
    .staff-details-page .table thead th {
        color: #53665a;
        background: #f4f8f5;
        font-size: .78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .035em;
        border-bottom-color: var(--chabrin-border);
    }
    .staff-details-page .table td { vertical-align: middle; }
    @media (max-width: 767.98px) {
        .staff-details-page > .container-fluid > .row > .col-sm-12 > .card > .card-header {
            align-items: flex-start !important;
            flex-direction: column;
            gap: 12px;
        }
        .staff-details-page > .container-fluid > .row > .col-sm-12 > .card > .card-header .ms-auto {
            margin-left: 0 !important;
            width: 100%;
            flex-wrap: wrap;
        }
        .staff-details-page > .container-fluid > .row > .col-sm-12 > .card > .card-body { padding: 14px; }
        .staff-profile-summary { align-items: flex-start !important; padding: 15px; gap: 12px; }
        .staff-profile-summary img,
        .staff-profile-summary > div:first-child > div {
            width: 62px !important;
            height: 62px !important;
        }
        .staff-profile-summary h5 { font-size: 1.05rem; }
        .staff-profile-summary > div:last-child > span { margin-left: 0 !important; }
        .staff-profile-summary p { font-size: .78rem !important; }
        .staff-details-tabs .nav-link { padding: 9px 11px; }
    }
</style>

<!-- Employee Response Modal -->
<div class="modal fade" id="employeeResponseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Submit Response to Show Cause Notice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="employeeResponseForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="case_id" id="response_case_id">
                
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <strong>Case #:</strong> <span id="response_case_number"></span><br>
                        <strong>Deadline:</strong> <span id="response_deadline"></span>
                    </div>

                    <div class="mb-3">
                        <label for="response_date" class="form-label">Response Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="response_date" id="response_date" value="{{ date('Y-m-d') }}" required>
                    </div>

                    <div class="mb-3">
                        <label for="admits_allegations" class="form-label">Do you admit to the allegations? <span class="text-danger">*</span></label>
                        <select class="form-select" name="admits_allegations" id="admits_allegations" required>
                            <option value="">Select...</option>
                            <option value="1">Yes, I admit</option>
                            <option value="0">No, I deny</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="response_text" class="form-label">Your Response <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="response_text" id="response_text" rows="6" required placeholder="Provide your detailed response to the allegations..."></textarea>
                        <small class="text-muted">Explain your position clearly and provide any relevant context.</small>
                    </div>

                    <div class="mb-3">
                        <label for="mitigating_circumstances" class="form-label">Mitigating Circumstances</label>
                        <textarea class="form-control" name="mitigating_circumstances" id="mitigating_circumstances" rows="4" placeholder="Any factors that should be considered in your defense..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="response_documents" class="form-label">Supporting Documents</label>
                        <input type="file" class="form-control" name="documents[]" id="response_documents" multiple>
                        <small class="text-muted">Upload any evidence or supporting documents (PDF, DOC, images)</small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined">close</span>
                        <span class="ms-1">Cancel</span>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">send</span>
                        Submit Response
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Extension Request Modal -->
<div class="modal fade" id="extensionRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Request Deadline Extension</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="extensionRequestForm">
                @csrf
                <input type="hidden" name="case_id" id="ext_case_id">
                
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <strong>Case #:</strong> <span id="ext_case_number"></span><br>
                        <strong>Current Deadline:</strong> <span id="ext_current_deadline"></span>
                    </div>

                    <div class="alert alert-warning mb-3">
                        <span class="material-symbols-outlined" style="vertical-align:middle;">info</span>
                        <strong>Note:</strong> Your extension request will be reviewed by HR. You will be notified of the decision.
                    </div>

                    <div class="mb-3">
                        <label for="ext_reason" class="form-label">Reason for Extension Request <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="extension_reason" id="ext_reason" rows="4" required placeholder="Explain why you need more time to respond (e.g., gathering evidence, consulting with legal counsel, medical reasons...)"></textarea>
                        <small class="text-muted">Be specific and provide valid justification.</small>
                    </div>

                    <div class="mb-3">
                        <label for="ext_requested_days" class="form-label">Additional Days Requested <span class="text-danger">*</span></label>
                        <select class="form-select" name="requested_days" id="ext_requested_days" required>
                            <option value="">Select...</option>
                            <option value="1">1 Day</option>
                            <option value="2">2 Days</option>
                            <option value="3">3 Days</option>
                            <option value="5">5 Days</option>
                            <option value="7">7 Days</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined">close</span>
                        <span class="ms-1">Cancel</span>
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">schedule_send</span>
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openResponseModal(caseId, caseNumber, deadline) {
    $('#response_case_id').val(caseId);
    $('#response_case_number').text(caseNumber);
    $('#response_deadline').text(deadline);
    $('#employeeResponseModal').modal('show');
}

function openExtensionRequestModal(caseId, caseNumber, currentDeadline) {
    $('#ext_case_id').val(caseId);
    $('#ext_case_number').text(caseNumber);
    $('#ext_current_deadline').text(currentDeadline);
    $('#extensionRequestModal').modal('show');
}

$(document).ready(function() {
    // Submit Employee Response
    $('#employeeResponseForm').on('submit', function(e) {
        e.preventDefault();
        
        let formData = new FormData(this);
        let caseId = $('#response_case_id').val();
        
        $.ajax({
            url: "{{ url('/discipline/tracker/case') }}/" + caseId + "/submit-response",
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if(response.success) {
                    $('#employeeResponseModal').modal('hide');
                    toastr.success(response.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                }
            },
            error: function(xhr) {
                if(xhr.status === 403 && xhr.responseJSON?.error) {
                    toastr.error(xhr.responseJSON.error);
                } else {
                    toastr.error('Failed to submit response. Please try again.');
                }
            }
        });
    });

    // Submit Extension Request
    $('#extensionRequestForm').on('submit', function(e) {
        e.preventDefault();
        
        let formData = {
            _token: '{{ csrf_token() }}',
            case_id: $('#ext_case_id').val(),
            extension_reason: $('#ext_reason').val(),
            requested_days: $('#ext_requested_days').val()
        };
        
        $.ajax({
            url: "{{ route('discipline.tracker.request.extension') }}",
            type: 'POST',
            data: formData,
            success: function(response) {
                if(response.success) {
                    $('#extensionRequestModal').modal('hide');
                    toastr.success(response.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.error || 'Failed to submit extension request. Please try again.');
            }
        });
    });

    // ── Payslip Tab ──────────────────────────────────────────────────────────
    $('#viewPayslipBtn').on('click', function() {
        var month = $('#payslip_month').val();
        if (!month) { toastr.warning('Please select a month.'); return; }

        $('#payslipSpinner').removeClass('d-none');
        $('#viewPayslipBtn').prop('disabled', true);
        $('#payslipPreviewWrapper, #payslipNoResult, #payslipDownloadWrapper').addClass('d-none');

        $.ajax({
            url: "{{ route('ajax.payslips.view') }}",
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { staff_id: {{ $staff->id }}, payroll_month: month },
            success: function(res) {
                if (res.success) {
                    $('#payslipPreviewContent').html(res.html);
                    $('#payslipPreviewWrapper').removeClass('d-none');
                    $('#payslipDownloadLink').attr('href', '/payslips/download/{{ $staff->id }}/' + month);
                    $('#payslipDownloadWrapper').removeClass('d-none');
                } else {
                    $('#payslipNoResult').removeClass('d-none');
                }
            },
            error: function() { toastr.error('Failed to load payslip.'); },
            complete: function() {
                $('#payslipSpinner').addClass('d-none');
                $('#viewPayslipBtn').prop('disabled', false);
            }
        });
    });

    // ── Leave Tab ────────────────────────────────────────────────────────────
    var staffLeaveTable = null;
    var leaveTypesLoaded = false;

    $('#leave-tab').one('click', function() {
        staffLeaveTable = $('#staffLeaveTable').DataTable({
            processing: true,
            ajax: {
                url: "{{ route('ajax.leaveApplications.list') }}",
                dataSrc: function(json) {
                    return (json.leave_applications || []).filter(function(a) {
                        return a.staff_id === {{ $staff->id }};
                    });
                }
            },
            columns: [
                { data: null, orderable: false, render: function(d, t, r, m) { return m.row + 1; } },
                { data: 'leave_type', render: function(d) { return d ? d.type_name : '-'; } },
                { data: 'start_date', render: function(d) { return d ? d.substring(0, 10) : '-'; } },
                { data: 'end_date',   render: function(d) { return d ? d.substring(0, 10) : '-'; } },
                { data: 'display_days' },
                {
                    data: 'current_status_id',
                    render: function(s) {
                        var map = {
                            1:  '<span class="badge bg-secondary">Pending</span>',
                            5:  '<span class="badge bg-success">Approved</span>',
                            28: '<span class="badge bg-danger">Rejected</span>'
                        };
                        return map[s] || '<span class="badge bg-light text-dark">' + s + '</span>';
                    }
                }
            ],
            order: [[2, 'desc']],
            pageLength: 25
        });

        if (!leaveTypesLoaded) {
            leaveTypesLoaded = true;
            $.get("{{ route('ajax.leaveTypes.list') }}", function(res) {
                if (res.success) {
                    var opts = '<option value="">Select leave type...</option>';
                    res.leave_types.forEach(function(t) {
                        opts += '<option value="' + t.id + '">' + t.type_name + ' (' + t.entitled_days + ' days)</option>';
                    });
                    $('#sl_leave_type_id').html(opts);
                }
            });
        }
    });

    $('input[name="leave_duration_type"]').on('change', function() {
        if ($(this).val() === 'full_day') {
            $('#sl_full_days_section').show();
            $('#sl_hours_section').hide();
        } else {
            $('#sl_full_days_section').hide();
            $('#sl_hours_section').show();
        }
    });

    $('#sl_leave_type_id').on('change', function() {
        var leaveTypeId = $(this).val();
        if (!leaveTypeId) { $('#sl_available_days').val(''); return; }
        $.get('/ajax/leave-management/calendar-plan/available-leave-days/{{ $staff->id }}/' + leaveTypeId, function(res) {
            $('#sl_available_days').val(res.available_days ?? 0);
        });
    });

    $('#staffLeaveSubmitBtn').on('click', function() {
        var durationType = $('input[name="leave_duration_type"]:checked').val();
        var data = {
            staff_id: {{ $staff->id }},
            leave_type_id: $('#sl_leave_type_id').val(),
            available_days: $('#sl_available_days').val() || 0,
            leave_duration_type: durationType,
            _token: $('meta[name="csrf-token"]').attr('content')
        };

        if (!data.leave_type_id) { toastr.warning('Please select a leave type.'); return; }

        if (durationType === 'full_day') {
            data.start_date = $('#sl_start_date').val();
            data.requested_days = $('#sl_requested_days').val();
            if (!data.start_date || !data.requested_days) {
                toastr.warning('Please fill in start date and number of days.');
                return;
            }
        } else {
            var date = $('#sl_leave_date_hours').val();
            if (!date) { toastr.warning('Please fill in the date.'); return; }
            data.start_date = date + ' ' + $('#sl_start_time_only').val() + ':00';
            data.end_date   = date + ' ' + $('#sl_end_time_only').val() + ':00';
        }

        $('#staffLeaveSpinner').removeClass('d-none');
        $('#staffLeaveSubmitBtn').prop('disabled', true);

        $.ajax({
            url: "{{ route('ajax.leaveApplications.store') }}",
            method: 'POST',
            data: data,
            success: function(res) {
                if (res.success) {
                    $('#leaveApplicationModal').modal('hide');
                    toastr.success('Leave application submitted successfully.');
                    if (staffLeaveTable) staffLeaveTable.ajax.reload(null, false);
                } else {
                    toastr.error(res.error || 'Failed to submit application.');
                }
            },
            error: function(xhr) {
                var errs = xhr.responseJSON && xhr.responseJSON.errors;
                if (errs) {
                    toastr.error(Object.values(errs).flat().join('<br>'));
                } else {
                    toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to submit application.');
                }
            },
            complete: function() {
                $('#staffLeaveSpinner').addClass('d-none');
                $('#staffLeaveSubmitBtn').prop('disabled', false);
            }
        });
    });

    $('#leaveApplicationModal').on('hidden.bs.modal', function() {
        $('#staffLeaveForm')[0].reset();
        $('#sl_available_days').val('');
        $('#sl_full_days_section').show();
        $('#sl_hours_section').hide();
    });
});
</script>

@endsection