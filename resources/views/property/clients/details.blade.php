@php
use Illuminate\Support\Facades\Crypt;
@endphp
{{--
COPY PERMISSIONS (paste when re-enabling checks):
@permission('update_clients') ... @endpermission
@permission('create_clients') ... @endpermission
@permission('create_properties') ... @endpermission
@permission('create_units') ... @endpermission
@permission('create_service_agreements') ... @endpermission
@permission('create_tenants') ... @endpermission
@permission('create_lease_agreements') ... @endpermission
@permission('read_clients') ... @endpermission
@permission('read_properties') ... @endpermission
@permission('read_service_agreements') ... @endpermission
@permission('read_units') ... @endpermission
@permission('read_tenants') ... @endpermission
@permission('read_lease_agreements') ... @endpermission
@permission('read_recoveries') ... @endpermission
@permission('read_inspections') ... @endpermission
@permission('read_repair_requests') ... @endpermission
--}}
@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Landlord/Client - {{ $client->names }}</h5>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('property.clients.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
                            </a>
                            <a href="/property/clients/{{ Crypt::encrypt($client->id) }}/edit" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">edit_document</span>
                                <span class="ms-1">Edit Client</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Summary Header -->
                        <div class="mb-3 d-flex flex-row justify-content-between align-items-center">
                            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                                <strong>Ref #:</strong> {{ $client->reference_number ?? 'N/A' }} |
                                @if($client->client_type_id == 3)
                                <strong>Reg No:</strong> {{ $client->company_registration_number ?? '-' }} |
                                @else
                                <strong>National ID:</strong> {{ $client->national_id ?? '-' }} |
                                @endif
                                <strong>Email:</strong> {{ $client->email_address ?? '-' }} |
                                <strong>Phone:</strong> {{ $client->mobile_number ?? '-' }}
                            </p>
                            <!-- In the Properties tab section, add this button -->
                            <div class="mb-3">
                                <div class="dropdown">
                                    <button class="btn btn-primary btn-sm dropdown-toggle d-flex align-items-center" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">more_vert</span>
                                        <span>Additional Actions</span>
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                        <li>
                                            <a class="dropdown-item d-flex align-items-center" href="#" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">
                                                <span class="material-symbols-outlined me-2" style="font-size:18px;">upload_file</span> Bulk Upload Data
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item d-flex align-items-center" href="{{ route('property.properties.create', ['client' => Crypt::encrypt($client->id)]) }}">
                                                <span class="material-symbols-outlined me-2" style="font-size:18px;">add_home</span> Add Property
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item d-flex align-items-center" href="{{ route('service.agreements.create', ['client' => Crypt::encrypt($client->id)]) }}">
                                                <span class="material-symbols-outlined me-2" style="font-size:18px;">approval_delegation</span> Add SLA
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item d-flex align-items-center" href="{{ route('units.tenants.create', ['client' => Crypt::encrypt($client->id)]) }}">
                                                <span class="material-symbols-outlined me-2" style="font-size:18px;">person</span> Add Tenants
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item d-flex align-items-center" href="{{ route('tenant.lease.create', ['client' => Crypt::encrypt($client->id)]) }}">
                                                <span class="material-symbols-outlined me-2" style="font-size:18px;">document_scanner</span> Add Lease
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Tabs Navigation -->
                        <ul class="nav nav-tabs mb-4" id="clientTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">person</span> Profile
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">analytics</span> Stats
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="properties-tab" data-bs-toggle="tab" data-bs-target="#properties" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">home</span> Properties
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="related-properties-tab" data-bs-toggle="tab" data-bs-target="#related-properties" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">real_estate_agent</span> Related Properties
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="sla-tab" data-bs-toggle="tab" data-bs-target="#sla" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">approval_delegation</span> SLA
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="units-tab" data-bs-toggle="tab" data-bs-target="#units" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">apartment</span> Units
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tenants-tab" data-bs-toggle="tab" data-bs-target="#tenants" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">group</span> Tenants
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="leases-tab" data-bs-toggle="tab" data-bs-target="#leases" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">description</span> Leases
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="recoveries-tab" data-bs-toggle="tab" data-bs-target="#recoveries" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">payments</span> Recoveries
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="inspections-tab" data-bs-toggle="tab" data-bs-target="#inspections" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">fact_check</span> Inspections
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="repairs-tab" data-bs-toggle="tab" data-bs-target="#repairs" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">build</span> Repairs &amp; Maintenance
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="returns-config-tab" data-bs-toggle="tab" data-bs-target="#returns-config" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">settings</span> Returns Config
                                </button>
                            </li>
                        </ul>

                        <!-- Tabs Content -->
                        <div class="tab-content" id="clientTabsContent">

                            <!-- Profile Information Tab -->
                            <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                                <div class="row g-3">
                                    <div class="col-lg-3 col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-light py-2">
                                                <h6 class="mb-0" style="font-size:0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">badge</span>
                                                    Personal & Tax Information
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-1">
                                                    <div class="col-5"><small class="text-muted">{{ $client->client_type_id == 3 ? 'Company Name:' : 'Full Names:' }}</small></div>
                                                    <div class="col-7"><small><strong>{{ $client->names ?? '-' }}</strong></small></div>
                                                    @if($client->client_type_id == 3)
                                                    <div class="col-5"><small class="text-muted">Registration No:</small></div>
                                                    <div class="col-7"><small><strong>{{ $client->company_registration_number ?? '-' }}</strong></small></div>
                                                    @else
                                                    <div class="col-5"><small class="text-muted">National ID:</small></div>
                                                    <div class="col-7"><small><strong>{{ $client->national_id ?? '-' }}</strong></small></div>
                                                    @endif
                                                    <div class="col-5"><small class="text-muted">Reference No:</small></div>
                                                    <div class="col-7"><small><strong>{{ $client->reference_number ?? '-' }}</strong></small></div>
                                                    <div class="col-5"><small class="text-muted">Mobile Number:</small></div>
                                                    <div class="col-7"><small><strong>{{ $client->mobile_number ?? '-' }}</strong></small></div>
                                                    <div class="col-5"><small class="text-muted">Email Address:</small></div>
                                                    <div class="col-7"><small><strong>{{ $client->email_address ?? '-' }}</strong></small></div>
                                                    <div class="col-5"><small class="text-muted">Registered:</small></div>
                                                    <div class="col-7"><small><strong>{{ $client->registered_date ? $client->registered_date->format('d-m-Y') : '-' }}</strong></small></div>
                                                    <div class="col-12 mt-2"><small class="text-muted">Address:</small></div>
                                                    <div class="col-12"><small><strong>{{ $client->address ?? '-' }}</strong></small></div>
                                                    <div class="col-12"><hr class="my-2"></div>
                                                    <div class="col-5"><small class="text-muted">VAT Number:</small></div>
                                                    <div class="col-7"><small><strong>{{ $client->vat_number ?? '-' }}</strong></small></div>
                                                    <div class="col-5"><small class="text-muted">PIN Number:</small></div>
                                                    <div class="col-7"><small><strong>{{ $client->pin_number ?? '-' }}</strong></small></div>
                                                    @if($client->client_type_id == 3)
                                                    <div class="col-12"><hr class="my-2"></div>
                                                    <div class="col-12 mb-1"><small class="text-muted fw-bold">Contact Person</small></div>
                                                    <div class="col-5"><small class="text-muted">Name:</small></div>
                                                    <div class="col-7"><small><strong>{{ $client->contact_person_name ?? '-' }}</strong></small></div>
                                                    <div class="col-5"><small class="text-muted">Title:</small></div>
                                                    <div class="col-7"><small><strong>{{ $client->contact_person_title ?? '-' }}</strong></small></div>
                                                    <div class="col-5"><small class="text-muted">Mobile:</small></div>
                                                    <div class="col-7"><small><strong>{{ $client->contact_person_mobile ?? '-' }}</strong></small></div>
                                                    <div class="col-5"><small class="text-muted">Email:</small></div>
                                                    <div class="col-7"><small><strong>{{ $client->contact_person_email ?? '-' }}</strong></small></div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-9 col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0" style="font-size:0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">account_balance</span>
                                                    Bank Details
                                                </h6>
                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#bankDetailModal" id="addBankDetailBtn">
                                                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">add</span> Add Bank
                                                </button>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="table-responsive">
                                                    <table id="bankDetailsTable" class="table table-sm table-hover" style="width:100%;font-size:0.85rem;">
                                                        <thead>
                                                            <tr>
                                                                <th>#</th>
                                                                <th>Bank</th>
                                                                <th>Branch</th>
                                                                <th>Account Name</th>
                                                                <th>Account Number</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="bankDetailsTableBody">
                                                            <tr>
                                                                <td colspan="6" class="text-center text-muted">Loading...</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Statistics Tab -->
                            <div class="tab-pane fade" id="stats" role="tabpanel" aria-labelledby="stats-tab">

                                <style>
                                .cs-card {
                                    background: #fff;
                                    border: 1px solid #d9ebf7;
                                    border-radius: 8px;
                                    padding: 12px 14px;
                                    position: relative;
                                    overflow: hidden;
                                    display: flex;
                                    align-items: center;
                                    gap: 10px;
                                    height: 100%;
                                }
                                .cs-card::before {
                                    content: '';
                                    position: absolute;
                                    top: 0; left: 0; bottom: 0;
                                    width: 3px;
                                    background: var(--cs-accent, #b7d7f0);
                                    border-radius: 8px 0 0 8px;
                                }
                                .cs-icon {
                                    width: 30px;
                                    height: 30px;
                                    border-radius: 7px;
                                    background: var(--cs-icon-bg, #d9ebf7);
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    flex-shrink: 0;
                                }
                                .cs-icon .material-symbols-outlined {
                                    font-size: 15px !important;
                                    color: #0a0a0a !important;
                                    font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
                                }
                                .cs-body { flex: 1; min-width: 0; }
                                .cs-value {
                                    font-size: 18px;
                                    font-weight: 400;
                                    color: #0a0a0a;
                                    line-height: 1.1;
                                    margin-bottom: 1px;
                                }
                                .cs-label {
                                    font-size: 10px;
                                    font-weight: 600;
                                    color: #6b7280;
                                    text-transform: uppercase;
                                    letter-spacing: 0.05em;
                                }
                                .cs-section-title {
                                    font-size: 10px;
                                    font-weight: 700;
                                    text-transform: uppercase;
                                    letter-spacing: 0.08em;
                                    color: #9ca3af;
                                    margin: 0 0 10px 0;
                                    display: flex;
                                    align-items: center;
                                    gap: 5px;
                                }
                                .cs-skeleton {
                                    display: inline-block;
                                    background: linear-gradient(90deg, #d9ebf7 25%, #b7d7f0 50%, #d9ebf7 75%);
                                    background-size: 200% 100%;
                                    animation: cs-pulse 1.4s ease infinite;
                                    border-radius: 4px;
                                    vertical-align: bottom;
                                }
                                @keyframes cs-pulse {
                                    0%   { background-position: 200% 0; }
                                    100% { background-position: -200% 0; }
                                }
                                </style>

                                {{-- Portfolio Overview --}}
                                <p class="cs-section-title">
                                    <span class="material-symbols-outlined" style="font-size:13px;font-variation-settings:'FILL' 1,'wght' 400,'GRAD' 0,'opsz' 24;">home</span>
                                    Portfolio Overview
                                </p>
                                <div class="row g-3 mb-4">
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="cs-card" style="--cs-accent:#5b9bd5; --cs-icon-bg:#b7d7f0;">
                                            <div class="cs-icon"><span class="material-symbols-outlined">home_work</span></div>
                                            <div class="cs-body">
                                                <div class="cs-value" id="cs-total-properties"><span class="cs-skeleton" style="width:28px;height:18px;"></span></div>
                                                <div class="cs-label">Total Properties</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="cs-card" style="--cs-accent:#b7d7f0; --cs-icon-bg:#d9ebf7;">
                                            <div class="cs-icon"><span class="material-symbols-outlined">domain</span></div>
                                            <div class="cs-body">
                                                <div class="cs-value" id="cs-total-units"><span class="cs-skeleton" style="width:28px;height:18px;"></span></div>
                                                <div class="cs-label">Total Units</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="cs-card" style="--cs-accent:#8ab4cc; --cs-icon-bg:#d9ebf7;">
                                            <div class="cs-icon"><span class="material-symbols-outlined">radio_button_unchecked</span></div>
                                            <div class="cs-body">
                                                <div class="cs-value" id="cs-vacant-units"><span class="cs-skeleton" style="width:28px;height:18px;"></span></div>
                                                <div class="cs-label">Vacant Units</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="cs-card" style="--cs-accent:#5b9bd5; --cs-icon-bg:rgba(255,255,255,0.25); background:#5b9bd5; border-color:#5b9bd5;">
                                            <div class="cs-icon" style="background:rgba(255,255,255,0.25);"><span class="material-symbols-outlined" style="color:#fff !important;">percent</span></div>
                                            <div class="cs-body">
                                                <div class="cs-value" id="cs-occupancy-rate" style="color:#fff;"><span class="cs-skeleton" style="width:28px;height:18px;"></span></div>
                                                <div class="cs-label" style="color:rgba(255,255,255,0.8);">Occupancy Rate</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="cs-card" style="--cs-accent:#5b9bd5; --cs-icon-bg:#b7d7f0;">
                                            <div class="cs-icon"><span class="material-symbols-outlined">description</span></div>
                                            <div class="cs-body">
                                                <div class="cs-value" id="cs-active-leases"><span class="cs-skeleton" style="width:28px;height:18px;"></span></div>
                                                <div class="cs-label">Active Leases</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="cs-card" style="--cs-accent:#b7d7f0; --cs-icon-bg:#d9ebf7;">
                                            <div class="cs-icon"><span class="material-symbols-outlined">group</span></div>
                                            <div class="cs-body">
                                                <div class="cs-value" id="cs-total-tenants"><span class="cs-skeleton" style="width:28px;height:18px;"></span></div>
                                                <div class="cs-label">Total Tenants</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>


                            </div>

                            <!-- Properties Tab -->
                            <div class="tab-pane fade" id="properties" role="tabpanel" aria-labelledby="properties-tab">
                                <div class="table-responsive custom-scrollbar">
                                    <table id="propertiesTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Reference Number</th>
                                                <th>Property Name</th>
                                                <th>Description</th>
                                                <th>Zone</th>
                                                <th>Zone Area</th>
                                                <th>Field Officer</th>
                                                <th>Bank Name</th>
                                                <th>Account Number</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Related Properties Tab -->
                            <div class="tab-pane fade" id="related-properties" role="tabpanel" aria-labelledby="related-properties-tab">
                                <div class="table-responsive custom-scrollbar">
                                    <table id="relatedPropertiesTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Property Ref</th>
                                                <th>Description</th>
                                                <th>Property Name</th>
                                                <th>LAN Ref</th>
                                                <th>Landlord Name</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- SLA Tab -->
                            <div class="tab-pane fade" id="sla" role="tabpanel" aria-labelledby="sla-tab">
                                <div class="table-responsive custom-scrollbar">
                                    <table id="slaTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Reference Number</th>
                                                <th>Property Name</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Commission %</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Units Tab -->
                            <div class="tab-pane fade" id="units" role="tabpanel" aria-labelledby="units-tab">
                                <div class="row mb-3 align-items-end">
                                    <div class="col-5">
                                        <label for="propertySelect" class="form-label">Select Property</label>
                                        <select id="propertySelect" class="form-select">
                                            <option value="">All Properties</option>
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <div id="propertySpinner" class="spinner-border text-primary" role="status" style="display:none;">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="table-responsive custom-scrollbar">
                                    <table id="unitsTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Property</th>
                                                <th>Unit Code</th>
                                                <th>Unit Name</th>
                                                <th>Unit Number</th>
                                                <th>Description</th>
                                                <th>Rent Amount</th>
                                                <th>Zone</th>
                                                <th>Status</th>
                                                <th>Tenants</th>
                                                {{-- <th>Action</th> --}}
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Tenants Tab -->
                            <div class="tab-pane fade" id="tenants" role="tabpanel" aria-labelledby="tenants-tab">
                                <div class="row mb-3 align-items-end">
                                    <div class="col-5">
                                        <label for="tenantPropertySelect" class="form-label">Select Property</label>
                                        <select id="tenantPropertySelect" class="form-select">
                                            <option value="">-- Select Property --</option>
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <div id="tenantSpinner" class="spinner-border text-primary" role="status" style="display:none;">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="table-responsive custom-scrollbar">
                                    <table id="tenantsTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Names</th>
                                                <th>Mobile Number</th>
                                                <th>Email Address</th>
                                                <th>Unit Name</th>
                                                <th>Reference Number</th>
                                                <th>Registered Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Leases Tab -->
                            <div class="tab-pane fade" id="leases" role="tabpanel" aria-labelledby="leases-tab">
                                <div class="row mb-3 align-items-end">
                                    <div class="col-5">
                                        <label for="leasesPropertySelect" class="form-label">Select Property</label>
                                        <select id="leasesPropertySelect" class="form-select">
                                            <option value="">-- Select Property --</option>
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <div id="leasesSpinner" class="spinner-border text-primary" role="status" style="display:none;">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="table-responsive custom-scrollbar">
                                    <table id="leasesTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Ref</th>
                                                <th>Tenant</th>
                                                <th>Property</th>
                                                <th>Unit</th>
                                                <th>Start</th>
                                                <th>End</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Recoveries Tab -->
                            <div class="tab-pane fade" id="recoveries" role="tabpanel" aria-labelledby="recoveries-tab">
                                <div class="table-responsive custom-scrollbar">
                                    <table id="recoveriesTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Ref Number</th>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Property</th>
                                                <th>Zone</th>
                                                <th>Type</th>
                                                <th>Commissionable</th>
                                                <th>Created By</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Inspections Tab -->
                            <div class="tab-pane fade" id="inspections" role="tabpanel" aria-labelledby="inspections-tab">
                                <div class="table-responsive custom-scrollbar">
                                    <table id="inspectionsTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Property</th>
                                                <th>Zone</th>
                                                <th>Visited</th>
                                                <th>Left</th>
                                                <th>Duration</th>
                                                <th>Units</th>
                                                <th>Created By</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Repairs & Maintenance Tab -->
                            <div class="tab-pane fade" id="repairs" role="tabpanel" aria-labelledby="repairs-tab">
                                <div class="table-responsive custom-scrollbar">
                                    <table id="repairsTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Ref Number</th>
                                                <th>Property</th>
                                                <th>Visited Date</th>
                                                <th>Created By</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Returns Config Tab -->
                            <div class="tab-pane fade" id="returns-config" role="tabpanel" aria-labelledby="returns-config-tab">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-lg-7">
                                                <div class="p-2 h-100">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <h6 class="mb-0">
                                                            <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">home_work</span>
                                                            Unbatched Active Properties
                                                        </h6>
                                                        <span id="returnsSelectedCount" class="badge bg-primary text-white">0 selected</span>
                                                    </div>

                                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                                        <button type="button" id="btnCreateReturnsBatchInline" class="btn btn-primary btn-sm">
                                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">add_circle</span>
                                                            <span class="btn-text">Create Batch</span>
                                                            <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                                                        </button>
                                                        <button type="button" id="btnUpdateReturnsBatchInline" class="btn btn-warning btn-sm d-none">
                                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">edit</span>
                                                            <span class="btn-text">Update Batch</span>
                                                            <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                                                        </button>
                                                        <button type="button" id="btnCancelReturnsBatchEdit" class="btn btn-light btn-sm d-none">
                                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">close</span>
                                                            Cancel
                                                        </button>
                                                    </div>

                                                    <table class="table table-sm table-hover table-bordered small w-100" id="returnsPropertiesTable">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th style="width:30px;"><input type="checkbox" id="returnsCheckAll" title="Select/Deselect All"></th>
                                                                <th>Property Ref</th>
                                                                <th>Property Name</th>
                                                                <th>Landlord</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="returnsPropertiesTableBody"></tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            <div class="col-lg-5">
                                                <div class="p-2 h-100">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <h6 class="mb-0">
                                                            <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">folder_shared</span>
                                                            Configured Batches
                                                        </h6>
                                                        <button type="button" id="btnDisbandAllReturnsBatches" class="btn btn-outline-danger btn-sm">
                                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">delete_sweep</span>
                                                            <span class="btn-text">Disband All</span>
                                                            <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                                                        </button>
                                                    </div>
                                                    <small class="text-muted d-block mb-2">Each property can exist in only one batch.</small>
                                                    <div id="returnsBatchCards" style="max-height: 500px; overflow:auto;"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- Bulk Upload Modal -->
                        <div class="modal fade" id="bulkUploadModal" tabindex="-1" aria-labelledby="bulkUploadModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="bulkUploadModalLabel">
                                            <span class="material-symbols-outlined" style="font-size:20px; vertical-align: middle;">upload_file</span>
                                            Bulk Upload Data
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form action="{{ route('property.bulk.upload') }}" method="POST" enctype="multipart/form-data" id="bulkUploadForm">
                                        @csrf
                                        <input type="hidden" name="client_id" value="{{ $client->id }}">

                                        <div class="modal-body">
                                            <!-- Upload Type Selection -->
                                            <div class="mb-4">
                                                <label for="upload_type" class="form-label fw-bold">
                                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">category</span>
                                                    Select Upload Type <span class="text-danger">*</span>
                                                </label>
                                                <select class="form-select" id="upload_type" name="upload_type" required>
                                                    <option value="">-- Select Type --</option>
                                                    <option value="properties">Properties</option>
                                                    <option value="units">Units</option>
                                                </select>
                                                <small class="form-text text-muted">Choose what type of data you want to upload</small>
                                            </div>

                                            <!-- Sample File Download Section -->
                                            <div class="mb-4 p-3 bg-light rounded" id="sampleDownloadSection" style="display: none;">
                                                <div class="d-flex align-items-start">
                                                    <span class="material-symbols-outlined text-primary me-2" style="font-size:24px;">info</span>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-2">Download Sample Template</h6>
                                                        <p class="mb-2 text-muted small">Download the sample file to see the required format and structure for your data.</p>
                                                        <button type="button" class="btn btn-outline-primary btn-sm" id="downloadSampleBtn">
                                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">download</span>
                                                            Download Sample File
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- File Upload Section -->
                                            <div class="mb-3" id="fileUploadSection" style="display: none;">
                                                <label for="bulk_file" class="form-label fw-bold">
                                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">attach_file</span>
                                                    Upload File <span class="text-danger">*</span>
                                                </label>
                                                <input type="file" class="form-control" id="bulk_file" name="bulk_file" accept=".xlsx,.xls,.csv" required>
                                                <small class="form-text text-muted">Accepted formats: Excel (.xlsx, .xls) or CSV (.csv)</small>
                                            </div>

                                            <!-- Instructions -->
                                            <div class="alert alert-info" id="instructionsSection" style="display: none;">
                                                <h6 class="alert-heading">
                                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">help</span>
                                                    Instructions
                                                </h6>
                                                <ol class="mb-0 small" id="instructionsList">
                                                    <!-- Instructions will be populated based on selection -->
                                                </ol>
                                            </div>
                                        </div>

                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary" id="uploadBtn" disabled>
                                                <span class="spinner-border spinner-border-sm me-2 d-none" id="uploadSpinner"></span>
                                                <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">upload</span>
                                                Upload & Process
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bank Detail Modal -->
<div class="modal fade" id="bankDetailModal" tabindex="-1" aria-labelledby="bankDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bankDetailModalLabel">
                    <span class="material-symbols-outlined" style="font-size:20px; vertical-align:middle;">account_balance</span>
                    <span id="bankDetailModalTitle">Add Bank Detail</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="bankDetailForm">
                    <input type="hidden" id="bankDetailId" name="bank_detail_id" value="">
                    <input type="hidden" id="bankDetailClientId" name="client_id" value="{{ $client->encrypted_id }}">
                    <div class="mb-3">
                        <label for="bankSelect" class="form-label">Bank <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="bankSelect" name="bank_id" required>
                            <option value="">Select Bank</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="branchSelect" class="form-label">Branch</label>
                        <select class="form-select form-select-sm" id="branchSelect" name="branch_id">
                            <option value="">Select Branch (Optional)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="accountName" class="form-label">Account Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="accountName" name="account_name" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label for="accountNumber" class="form-label">Account Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="accountNumber" name="account_number" required maxlength="50">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isDefault" name="is_default" value="1">
                            <label class="form-check-label" for="isDefault">Set as default bank account</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="saveBankDetailBtn">
                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">save</span>
                    <span id="saveBankDetailBtnText">Save</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Bank Detail Confirmation Modal -->
<div class="modal fade" id="deleteBankDetailModal" tabindex="-1" aria-labelledby="deleteBankDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteBankDetailModalLabel">
                    <span class="material-symbols-outlined text-danger" style="font-size:20px; vertical-align:middle;">warning</span>
                    Confirm Delete
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this bank detail?</p>
                <p class="mb-0"><strong id="deleteBankDetailInfo"></strong></p>
            </div>
            <div class="modal-footer">
                <input type="hidden" id="deleteBankDetailId" value="">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteBankDetailBtn">
                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">delete</span>
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Assign Property Bank Modal -->
<div class="modal fade" id="assignPropertyBankModal" tabindex="-1" aria-labelledby="assignPropertyBankModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignPropertyBankModalLabel">
                    <span class="material-symbols-outlined" style="font-size:20px; vertical-align:middle;">account_balance</span>
                    Assign Bank to Property
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="assignPropertyBankForm">
                    <input type="hidden" id="assignPropertyId" name="property_id" value="">
                    <input type="hidden" id="assignClientId" name="client_id" value="">
                    <div class="mb-3">
                        <label for="clientBankSelect" class="form-label">Select Bank Account <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="clientBankSelect" name="client_bank_id" required>
                            <option value="">Loading...</option>
                        </select>
                        <small class="text-muted">Only bank accounts set up for this client are available</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="savePropertyBankBtn">
                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">save</span>
                    Assign
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Related Property Details Modal -->
<div class="modal fade" id="relatedPropertyModal" tabindex="-1" aria-labelledby="relatedPropertyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="relatedPropertyModalLabel">
                    <span class="material-symbols-outlined" style="font-size:20px; vertical-align:middle;">real_estate_agent</span>
                    Property Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 class="text-muted fw-semibold mb-2">Property Information</h6>
                <table class="table table-sm table-borderless mb-3">
                    <tr>
                        <td class="fw-semibold text-muted" style="width:40%">Reference No.</td>
                        <td id="rpModalRef">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold text-muted">Property Name</td>
                        <td id="rpModalPropertyName">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold text-muted">Description</td>
                        <td id="rpModalDescription">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold text-muted">Status</td>
                        <td id="rpModalStatus">-</td>
                    </tr>
                </table>
                <hr class="my-2">
                <h6 class="text-muted fw-semibold mb-2">Landlord Information</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="fw-semibold text-muted" style="width:40%">Names</td>
                        <td id="rpModalLandlordName">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold text-muted">LAN Ref</td>
                        <td id="rpModalLandlordRef">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold text-muted">Phone</td>
                        <td id="rpModalLandlordPhone">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold text-muted">Email</td>
                        <td id="rpModalLandlordEmail">-</td>
                    </tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="slaDetailsModal" tabindex="-1" aria-labelledby="slaDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="slaDetailsModalLabel">
                    <span class="material-symbols-outlined" style="font-size:20px; vertical-align:middle;">description</span>
                    Service Agreement Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="slaDetailsModalBody"></div>
        </div>
    </div>
</div>

<style>
    .card-header {
        border-bottom: 1px solid #e0e0e0;
    }

    .card-body .row.g-1>div {
        padding-top: 0.25rem;
        padding-bottom: 0.25rem;
        line-height: 1.5;
    }

    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 20;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .card {
        border: 1px solid #e0e0e0;
    }

    /* Leases: allow responsive wrapping (removed forced nowrap) */
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        var propertiesTable = null;
        var relatedPropertiesTable = null;
        var unitsTable = null;
        var tenantsTable = null;
        var leasesTable = null;
        var returnsConfigOptions = {
            all_eligible: [],
            unbatched: []
        };
        var returnsConfigBatches = [];
        var editingReturnsBatchId = null;
        var propertiesLoaded = false;
        var relatedPropertiesLoaded = false;
        var returnsConfigLoaded = false;
        var unitsLoaded = false;
        var tenantsLoaded = false;
        var propertiesDropdownLoaded = false;
        var tenantPropertiesDropdownLoaded = false;
        var leasesPropertiesDropdownLoaded = false;

        // Initialize Properties DataTable immediately but with empty data
        propertiesTable = $('#propertiesTable').DataTable({
            data: [],
            columns: [{
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'reference_number'
                },
                {
                    data: 'property_name',
                    render: function(data, type, row) {
                        return `<a href="/property/listings/${row.encrypted_id}">${data}</a>`;
                    }
                },
                {
                    data: 'description'
                },
                {
                    data: 'zone'
                },
                {
                    data: 'zone_area'
                },
                {
                    data: 'field_officer'
                },
                {
                    data: 'bank_name'
                },
                {
                    data: 'account_number'
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        buttons += `
                            <a href="/property/properties/${row.encrypted_id}/edit" title="Edit">
                                <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                            </a>
                            <a href="#" class="assign-bank-btn" data-property-id="${row.id}" data-client-id="${row.client_id}" title="Assign Bank">
                                <span class="material-symbols-outlined" style="font-size:18px;">account_balance</span>
                            </a>
                        `;

                        buttons += '</div>';
                        return buttons;
                    }
                }

            ],
            pageLength: 10,
            responsive: true,
            language: {
                emptyTable: "No properties found for this client"
            }
        });

        relatedPropertiesTable = $('#relatedPropertiesTable').DataTable({
            data: [],
            columns: [{
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'reference_number'
                },
                {
                    data: 'description'
                },
                {
                    data: 'property_name',
                    render: function(data, type, row) {
                        if (type === 'display' && row.property_url) {
                            return '<a href="' + row.property_url + '" target="_blank" style="text-decoration:underline;">' + (data || '-') + '</a>';
                        }
                        return data || '-';
                    }
                },
                {
                    data: 'landlord_ref'
                },
                {
                    data: 'landlord_name',
                    render: function(data, type, row) {
                        if (type === 'display' && row.client_url) {
                            return '<a href="' + row.client_url + '" target="_blank" style="text-decoration:underline;">' + (data || '-') + '</a>';
                        }
                        return data || '-';
                    }
                },
                {
                    data: 'status',
                    render: function(data, type, row) {
                        if (row.active == 1) {
                            return '<span class="badge badge-light-success">Active</span>';
                        }
                        return '<span class="badge badge-light-danger">Inactive</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return '<button class="btn btn-sm btn-icon btn-view-related-property" title="View Details"><span class="material-symbols-outlined" style="font-size:18px;">visibility</span></button>';
                    }
                }
            ],
            pageLength: 10,
            responsive: true,
            language: {
                emptyTable: "No related properties found"
            }
        });

        // Initialize Units DataTable immediately but with empty data
        unitsTable = $('#unitsTable').DataTable({
            data: [],
            columns: [{
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'property'
                },
                {
                    data: 'unit_code'
                },
                {
                    data: 'unit_name'
                },
                {
                    data: 'unit_number'
                },
                {
                    data: 'description'
                },
                {
                    data: 'rent_amount'
                },
                {
                    data: 'zone'
                },
                {
                    data: 'occupancy_status'
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(d, type, row) {
                        var count = (row.tenants || []).length;
                        return '<span class="d-flex align-items-center gap-1">' +
                            '<span class="material-symbols-outlined text-primary toggle-chevron" style="font-size:18px;">chevron_right</span>' +
                            '<span class="small text-muted">' + count + ' tenant' + (count === 1 ? '' : 's') + '</span>' +
                            '</span>';
                    }
                }
                // {
                //     data: 'id',
                //     orderable: false,
                //     searchable: false,
                //     render: function(id, type, row) {
                //         return `
                //             <a href="/property/units/${row.encrypted_id}/edit" title="Edit Unit">
                //                 <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
                //             </a>
                //         `;
                //     }
                // }
            ],
            createdRow: function(row) {
                $(row).addClass('group-parent-row table-light').css('cursor', 'pointer');
            },
            pageLength: 10,
            responsive: true,
            language: {
                emptyTable: "No units found"
            }
        });

        // Expand/collapse a unit row to show who has ever leased it — data is
        // already embedded per row (getUnits()'s `tenants` array), no extra AJAX.
        function buildUnitTenantsTable(tenants) {
            if (!tenants || tenants.length === 0) {
                return '<div class="ps-4 py-2 border-start border-2 text-muted small">No tenants recorded for this unit.</div>';
            }
            var rows = '';
            tenants.forEach(function(t) {
                rows += '<tr>' +
                    '<td class="small fw-semibold">' + t.tenant_name + '</td>' +
                    '<td class="small">' + t.ref_number + '</td>' +
                    '<td class="small">' + t.date_start + '</td>' +
                    '<td class="small">' + t.date_end + '</td>' +
                    '<td class="small"><span class="badge bg-secondary">' + t.status + '</span></td>' +
                    '</tr>';
            });
            return '<div class="ps-4 py-2 border-start border-2">' +
                '<table class="table table-sm table-bordered table-hover mb-0">' +
                '<thead><tr>' +
                '<th class="bg-light text-secondary small">Tenant</th>' +
                '<th class="bg-light text-secondary small">Lease Ref</th>' +
                '<th class="bg-light text-secondary small">Start</th>' +
                '<th class="bg-light text-secondary small">End</th>' +
                '<th class="bg-light text-secondary small">Status</th>' +
                '</tr></thead><tbody>' + rows + '</tbody></table></div>';
        }

        $('#unitsTable').on('click', 'tr.group-parent-row', function() {
            var tr = $(this);
            var dtRow = unitsTable.row(this);
            var data = dtRow.data();
            if (!data) return;

            var icon = tr.find('.toggle-chevron');
            if (dtRow.child.isShown()) {
                dtRow.child.hide();
                icon.text('chevron_right');
                tr.removeClass('table-primary').addClass('table-light');
            } else {
                dtRow.child(buildUnitTenantsTable(data.tenants)).show();
                icon.text('expand_more');
                tr.removeClass('table-light').addClass('table-primary');
            }
        });

        // Initialize Tenants DataTable immediately but with empty data
        tenantsTable = $('#tenantsTable').DataTable({
            data: [],
            columns: [{
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'names'
                },
                {
                    data: 'mobile_number'
                },
                {
                    data: 'email_address'
                },
                {
                    data: 'unit_name'
                },
                {
                    data: 'reference_number'
                },
                {
                    data: 'registered_date'
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        // View Profile button
                        buttons += `
                            <a href="/tenants/${row.encrypted_id}" title="View Profile">
                                <span class="material-symbols-outlined" style="font-size:18px;">visibility</span>
                            </a>
                        `;
                        // Edit button
                        buttons += `
                            <a href="/property/tenants/${row.encrypted_id}/edit" title="Edit Tenant">
                                <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
                            </a>
                        `;
                        buttons += '</div>';
                        return buttons;
                    }
                }
            ],
            pageLength: 10,
            responsive: true,
            language: {
                emptyTable: "Please select a property to view tenants"
            }
        });

        // Initialize SLA DataTable immediately but with empty data
        var slaTable = $('#slaTable').DataTable({
            data: [],
            columns: [{
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'reference_number'
                },
                {
                    data: 'property_name'
                },
                {
                    data: 'date_start',
                    render: function(data) {
                        return formatDateShort(data);
                    }
                },
                {
                    data: 'date_end',
                    render: function(data) {
                        return formatDateShort(data);
                    }
                },
                {
                    data: 'commission_percentage',
                    render: function(data) {
                        return data ? data + '%' : '-';
                    }
                },
                {
                    data: 'current_status_id',
                    render: function(data) {
                        if (data == 5) {
                            return '<span class="badge bg-success">Active</span>';
                        } else if (data == 1) {
                            return '<span class="badge bg-warning">Draft</span>';
                        } else if (data == 6) {
                            return '<span class="badge bg-danger">Expired</span>';
                        } else {
                            return '<span class="badge bg-secondary">Unknown</span>';
                        }
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        // View button
                        buttons += `
                            <a href="#" class="sla-view-link" data-id="${row.encrypted_id}" title="View Details">
                                <span class="material-symbols-outlined" style="font-size:18px;">visibility</span>
                            </a>
                        `;
                        // Edit button (only for draft status)
                        // if (row.current_status_id == 1) {
                        buttons += `
                            <a href="/property/sla/${row.encrypted_id}/edit" title="Edit">
                                <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
                            </a>
                        `;
                        // }
                        buttons += '</div>';
                        return buttons;
                    }
                }
            ],
            pageLength: 10,
            responsive: true,
            language: {
                emptyTable: "No service agreements found for this client"
            }
        });

        var slaLoaded = false;

        // Load SLA when tab is clicked
        $('#sla-tab').on('click', function() {
            if (!slaLoaded) {
                loadSLA();
                slaLoaded = true;
            }
        });

        // Function to load SLA
        function loadSLA() {
            $.ajax({
                url: "{{ route('property.clients.sla', $client->encrypted_id) }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.data) {
                        slaTable.clear();
                        slaTable.rows.add(response.data);
                        slaTable.draw();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading SLA:', error);
                    toastr.error('Failed to load service agreements. Please try again.');
                }
            });
        }

        // Handle clicks on the SLA view link - load details into a modal
        $(document).on('click', '.sla-view-link', function(e) {
            e.preventDefault();
            var encryptedId = $(this).data('id');
            var $modal = $('#slaDetailsModal');
            var $body = $('#slaDetailsModalBody');

            $body.html('<div class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span></div>');
            $modal.modal('show');

            $.ajax({
                url: `/property/sla/${encryptedId}/show`,
                method: 'GET',
                dataType: 'json',
                success: function(sla) {
                    $body.html(renderSlaDetails(sla));
                },
                error: function() {
                    $body.html('<div class="text-danger">Failed to load service agreement details.</div>');
                }
            });
        });

        function renderSlaDetails(sla) {
            var statusBadge;
            if (sla.is_expired) {
                statusBadge = '<span class="badge bg-danger">Expired</span>';
            } else if (sla.current_status_id == 5) {
                statusBadge = '<span class="badge bg-success">Active</span>';
            } else {
                statusBadge = '<span class="badge bg-secondary">Inactive</span>';
            }

            function field(label, value) {
                return `
                    <div class="col-6 col-md-3">
                        <div class="text-muted" style="font-size:10px; line-height:1.2;">${label}</div>
                        <div class="fw-semibold" style="font-size:12px; line-height:1.3;">${value}</div>
                    </div>
                `;
            }

            function sectionLabel(title) {
                return `<div class="text-muted text-uppercase" style="font-size:10px; letter-spacing:.5px;">${title}</div>`;
            }

            var billableItemsBadges = sla.billable_items.map(function(item) {
                if (!item.selected) return '';
                var cost = (item.cost !== null && item.cost !== undefined) ? ' &mdash; ' + Number(item.cost).toFixed(2) : '';
                return `<span class="badge bg-light text-dark border me-1">${item.name}${cost}</span>`;
            }).join('') || '<span class="text-muted" style="font-size:12px;">None selected</span>';

            var documentsList = sla.documents.length ? sla.documents.map(function(doc) {
                return `
                    <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                        <span style="font-size:12px;">${doc.name}</span>
                        ${doc.url ? `<a href="${doc.url}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2">Download</a>` : '<span class="text-muted" style="font-size:11px;">Not available</span>'}
                    </div>
                `;
            }).join('') : '<div class="text-muted" style="font-size:12px;">No documents uploaded</div>';

            return `
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row g-2">
                            ${field('Reference Number', sla.reference_number)}
                            ${field('Status', statusBadge)}
                            ${field('Start Date', sla.date_start)}
                            ${field('End Date', sla.date_end)}
                            ${field('Duration (Years)', sla.years)}
                            ${field('Auto Renewal', sla.auto_renewal)}
                            ${field('Client Name', sla.client_name)}
                            ${field('Client Reference', sla.client_reference)}
                            ${field('Property', sla.property_name)}
                            ${field('Property Reference', sla.property_reference)}
                            ${field('Zone', sla.zone_name)}
                            ${field('Commission', sla.commission_percentage + '%')}
                            ${field('VAT', sla.vat)}
                            ${field('VAT Percentage', sla.vat_per + '%')}
                            ${field('Flat Rate VAT', sla.flat_rate_vat)}
                            ${field('Registration Fee', sla.charge_registration_fee ? 'Yes' : 'No')}
                            ${field('Created On', sla.date_time)}
                            ${field('Created By', sla.created_by)}
                        </div>

                        <hr class="my-2">

                        <div class="row g-2">
                            <div class="col-md-6">
                                ${sectionLabel('Billable Items')}
                                <div class="mt-1">${billableItemsBadges}</div>
                            </div>
                            <div class="col-md-6">
                                ${sectionLabel('Returns Combination')}
                                <div style="font-size:12px;" class="mt-1">${sla.returns_combination_label}</div>
                            </div>
                        </div>

                        <hr class="my-2">

                        ${sectionLabel('Documents')}
                        <div class="mt-1">${documentsList}</div>
                    </div>
                </div>
            `;
        }

        // Load stats when tab is clicked
        var statsLoaded = false;
        $('#stats-tab').on('click', function() {
            if (!statsLoaded) {
                loadClientStats();
                statsLoaded = true;
            }
        });

        function loadClientStats() {
            $.ajax({
                url: '{{ route("property.clients.stats", $client->encrypted_id) }}',
                type: 'GET',
                dataType: 'json',
                success: function(r) {
                    if (!r.success) return;
                    $('#cs-total-properties').text(r.totalProperties);
                    $('#cs-total-units').text(r.totalUnits);
                    $('#cs-vacant-units').text(r.vacantUnits);
                    $('#cs-occupancy-rate').text(r.occupancyRate + '%');
                    $('#cs-active-leases').text(r.activeLeases);
                    $('#cs-total-tenants').text(r.totalTenants);
                },
                error: function() {
                    ['cs-total-properties','cs-total-units','cs-vacant-units','cs-occupancy-rate',
                     'cs-active-leases','cs-total-tenants'
                    ].forEach(function(id) { $('#' + id).html('<span style="color:#dc3545;font-size:12px;">—</span>'); });
                }
            });
        }

        // Load properties when tab is clicked
        $('#properties-tab').on('click', function() {
            if (!propertiesLoaded) {
                loadProperties();
                propertiesLoaded = true;
            }
        });

        $('#related-properties-tab').on('click', function() {
            if (!relatedPropertiesLoaded) {
                loadRelatedProperties();
                relatedPropertiesLoaded = true;
            }
        });

        $('#returns-config-tab').on('click', function() {
            if (!returnsConfigLoaded) {
                loadReturnsConfig();
                returnsConfigLoaded = true;
            }
        });

        $('#btnCreateReturnsBatchInline').on('click', function() {
            saveReturnsConfigInline();
        });

        $('#btnUpdateReturnsBatchInline').on('click', function() {
            saveReturnsConfigInline();
        });

        $('#btnCancelReturnsBatchEdit').on('click', function() {
            resetReturnsConfigEditState();
            renderReturnsAvailableProperties();
        });

        $('#btnDisbandAllReturnsBatches').on('click', function() {
            Swal.fire({
                title: 'Disband All Batches?',
                text: 'This will remove all configured batches for this landlord profile. This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">delete_sweep</span> Yes, Disband All',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (result.isConfirmed) {
                    disbandAllReturnsConfig();
                }
            });
        });

        $(document).on('change', '.returns-prop-check', function() {
            refreshReturnsSelectedCount();
            updateCheckAllState();
        });

        // Check All functionality for returns properties table
        $(document).on('change', '#returnsCheckAll', function() {
            var isChecked = $(this).prop('checked');
            $('#returnsPropertiesTable .returns-prop-check:not(:disabled)').prop('checked', isChecked);
            refreshReturnsSelectedCount();
        });

        function updateCheckAllState() {
            var total = $('#returnsPropertiesTable .returns-prop-check:not(:disabled)').length;
            var checked = $('#returnsPropertiesTable .returns-prop-check:not(:disabled):checked').length;
            $('#returnsCheckAll').prop('checked', total > 0 && total === checked);
        }

        $(document).on('click', '.btn-edit-returns-config-inline', function() {
            var batchId = $(this).data('id');
            openReturnsConfigInlineEdit(batchId);
        });

        $(document).on('click', '.btn-delete-returns-config-inline', function() {
            var btn = $(this);
            var batchId = btn.data('id');
            Swal.fire({
                title: 'Delete This Batch?',
                text: 'This will remove all linked properties from this batch configuration.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">delete</span> Yes, Delete',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (result.isConfirmed) {
                    deleteReturnsConfig(batchId, btn);
                }
            });
        });

        // Related Property details modal
        $(document).on('click', '#relatedPropertiesTable .btn-view-related-property', function() {
            var rowData = relatedPropertiesTable.row($(this).closest('tr')).data();
            $('#rpModalRef').text(rowData.reference_number || '-');
            $('#rpModalPropertyName').text(rowData.property_name || '-');
            $('#rpModalDescription').text(rowData.description || '-');
            if (rowData.active == 1) {
                $('#rpModalStatus').html('<span class="badge badge-light-success">Active</span>');
            } else {
                $('#rpModalStatus').html('<span class="badge badge-light-danger">Inactive</span>');
            }
            $('#rpModalLandlordName').text(rowData.landlord_name || '-');
            $('#rpModalLandlordRef').text(rowData.landlord_ref || '-');
            $('#rpModalLandlordPhone').text(rowData.landlord_phone || '-');
            $('#rpModalLandlordEmail').text(rowData.landlord_email || '-');
            $('#relatedPropertyModal').modal('show');
        });

        // Load properties dropdown when units tab is clicked, and default to
        // "All Properties" (loadUnits('') below resolves that to client_id-scoped)
        // rather than leaving the table empty until a property is picked.
        $('#units-tab').on('click', function() {
            if (!propertiesDropdownLoaded) {
                loadPropertiesDropdown();
                loadUnits('');
                propertiesDropdownLoaded = true;
            }
        });

        // Load units when property is selected — "All Properties" (empty value)
        // loads every unit across the landlord's properties, same as the initial load
        $('#propertySelect').on('change', function() {
            loadUnits($(this).val());
        });

        // Initialize Bootstrap dropdowns (if needed)
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'))
        var dropdownList = dropdownElementList.map(function(dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl)
        });

        // Load properties dropdown when tenants tab is clicked
        $('#tenants-tab').on('click', function() {
            if (!tenantPropertiesDropdownLoaded) {
                loadTenantPropertiesDropdown();
                tenantPropertiesDropdownLoaded = true;
            }
        });

        // Load tenants when property is selected
        $('#tenantPropertySelect').on('change', function() {
            var propertyId = $(this).val();
            if (propertyId) {
                loadTenants(propertyId);
            } else {
                tenantsTable.clear().draw();
            }
        });

        function loadProperties() {
            $.ajax({
                url: "{{ route('property.clients.properties', $client->encrypted_id) }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.data) {
                        propertiesTable.clear();
                        propertiesTable.rows.add(response.data);
                        propertiesTable.draw();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading properties:', error);
                    toastr.error('Failed to load properties. Please try again.');
                }
            });
        }

        function loadRelatedProperties() {
            $.ajax({
                url: "{{ route('property.clients.related-properties', $client->encrypted_id) }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.data) {
                        relatedPropertiesTable.clear();
                        relatedPropertiesTable.rows.add(response.data);
                        relatedPropertiesTable.draw();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading related properties:', error);
                    toastr.error('Failed to load related properties. Please try again.');
                }
            });
        }

        function loadReturnsConfig() {
            $.ajax({
                url: "{{ route('property.clients.returns-config', $client->encrypted_id) }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    returnsConfigBatches = response.data || [];
                    loadReturnsConfigOptions();
                    renderReturnsBatchCards();
                },
                error: function(xhr) {
                    console.error('Error loading returns config:', xhr.responseText);
                    toastr.error('Failed to load returns merge configuration.');
                }
            });
        }

        function loadReturnsConfigOptions() {
            $.ajax({
                url: "{{ route('property.clients.returns-config.options', $client->encrypted_id) }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    returnsConfigOptions = {
                        all_eligible: response.all_eligible || [],
                        unbatched: response.unbatched || []
                    };
                    renderReturnsAvailableProperties();
                },
                error: function(xhr) {
                    console.error('Error loading returns config options:', xhr.responseText);
                    toastr.error('Failed to load available properties for merge configuration.');
                }
            });
        }

        var returnsPropertiesDataTable = null;

        function renderReturnsAvailableProperties() {
            // Destroy existing DataTable if present
            if (returnsPropertiesDataTable) {
                returnsPropertiesDataTable.destroy();
                returnsPropertiesDataTable = null;
            }

            var tbody = $('#returnsPropertiesTableBody');
            tbody.empty();
            $('#returnsCheckAll').prop('checked', false);

            var source = editingReturnsBatchId ? returnsConfigOptions.all_eligible : returnsConfigOptions.unbatched;
            var currentBatch = returnsConfigBatches.find(function(b) {
                return b.id == editingReturnsBatchId;
            });

            if (!source || source.length === 0) {
                tbody.html('<tr><td colspan="4" class="text-muted text-center py-3">No eligible unbatched active properties.</td></tr>');
                refreshReturnsSelectedCount();
                return;
            }

            var currentIds = currentBatch ? (currentBatch.items || []).map(function(i) {
                return Number(i.property_id);
            }) : [];

            source.forEach(function(prop) {
                var propId = Number(prop.id);
                var assignedBatchId = prop.assigned_batch_id ? Number(prop.assigned_batch_id) : null;
                var isChecked = currentIds.indexOf(propId) !== -1;
                var disabled = assignedBatchId && (!editingReturnsBatchId || assignedBatchId !== Number(editingReturnsBatchId));
                var disabledAttr = disabled ? 'disabled' : '';
                var rowClass = disabled ? 'table-secondary text-muted' : '';
                var landlordDisplay = (prop.landlord_ref || '-') + ' - ' + (prop.landlord_name || '-');
                if (disabled) {
                    landlordDisplay += ' <span class="text-danger small">(in batch)</span>';
                }

                tbody.append(
                    '<tr class="' + rowClass + '">' +
                    '<td class="text-center"><input class="form-check-input returns-prop-check" type="checkbox" value="' + prop.id + '" ' + (isChecked ? 'checked' : '') + ' ' + disabledAttr + '></td>' +
                    '<td>' + (prop.reference_number || '-') + '</td>' +
                    '<td>' + (prop.property_name || '-') + '</td>' +
                    '<td>' + landlordDisplay + '</td>' +
                    '</tr>'
                );
            });

            // Initialize DataTable
            returnsPropertiesDataTable = $('#returnsPropertiesTable').DataTable({
                paging: true,
                pageLength: 10,
                lengthMenu: [
                    [10, 25, 50, -1],
                    [10, 25, 50, "All"]
                ],
                searching: true,
                ordering: true,
                info: true,
                autoWidth: false,
                columnDefs: [{
                    orderable: false,
                    targets: 0
                }],
                language: {
                    search: "",
                    searchPlaceholder: "Search properties...",
                    emptyTable: "No eligible unbatched active properties."
                }
            });

            refreshReturnsSelectedCount();
            updateCheckAllState();
        }

        function renderReturnsBatchCards() {
            var container = $('#returnsBatchCards');
            container.empty();

            if (!returnsConfigBatches || returnsConfigBatches.length === 0) {
                container.html('<div class="text-muted small py-2">No batches configured yet.</div>');
                return;
            }

            returnsConfigBatches.forEach(function(batch) {
                var itemsHtml = '';
                (batch.items || []).forEach(function(item) {
                    itemsHtml += '<li class="small">' + (item.property_ref || '-') + ' - ' + (item.property_name || '-') + ' <span class="text-muted">(' + (item.landlord_ref || '-') + ')</span></li>';
                });

                container.append(
                    '<div class="border rounded p-2 mb-2">' +
                    '<div class="d-flex justify-content-between align-items-start mb-1">' +
                    '<div>' +
                    '<div class="fw-semibold">' + (batch.batch_reference || '-') + '</div>' +
                    '<div class="small text-muted">' + (batch.landlord_name || '-') + ' | LAN ' + (batch.landlord_ref || '-') + '</div>' +
                    '</div>' +
                    '<div class="d-flex gap-1">' +
                    '<button class="btn btn-sm btn-link text-primary p-0 btn-edit-returns-config-inline" data-id="' + batch.id + '" title="Edit Batch">' +
                    '<span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">edit_square</span>' +
                    '</button>' +
                    '<button class="btn btn-sm btn-link text-danger p-0 btn-delete-returns-config-inline" data-id="' + batch.id + '" title="Delete Batch">' +
                    '<span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">delete</span>' +
                    '<span class="spinner-border spinner-border-sm d-none" role="status"></span>' +
                    '</button>' +
                    '</div>' +
                    '</div>' +
                    '<ul class="mb-0 ps-3">' + itemsHtml + '</ul>' +
                    '</div>'
                );
            });
        }

        function openReturnsConfigInlineEdit(batchId) {
            editingReturnsBatchId = Number(batchId);
            $('#btnCreateReturnsBatchInline').addClass('d-none');
            $('#btnUpdateReturnsBatchInline').removeClass('d-none');
            $('#btnCancelReturnsBatchEdit').removeClass('d-none');
            renderReturnsAvailableProperties();
        }

        function resetReturnsConfigEditState() {
            editingReturnsBatchId = null;
            $('#btnCreateReturnsBatchInline').removeClass('d-none');
            $('#btnUpdateReturnsBatchInline').addClass('d-none');
            $('#btnCancelReturnsBatchEdit').addClass('d-none');
        }

        function getSelectedReturnsPropertyIds() {
            var selected = [];
            // Use DataTable API to get checkboxes from all pages
            if (returnsPropertiesDataTable) {
                returnsPropertiesDataTable.rows().nodes().each(function(row) {
                    var checkbox = $(row).find('.returns-prop-check:checked');
                    if (checkbox.length) {
                        selected.push(checkbox.val());
                    }
                });
            } else {
                $('.returns-prop-check:checked').each(function() {
                    selected.push($(this).val());
                });
            }
            return selected;
        }

        function refreshReturnsSelectedCount() {
            var count = getSelectedReturnsPropertyIds().length;
            $('#returnsSelectedCount').text(count + ' selected');
        }

        function saveReturnsConfigInline() {
            var propertyIds = getSelectedReturnsPropertyIds();

            if (propertyIds.length < 2) {
                toastr.error('Select at least two properties to create/update a batch.');
                return;
            }

            var btn = editingReturnsBatchId ? $('#btnUpdateReturnsBatchInline') : $('#btnCreateReturnsBatchInline');
            btn.prop('disabled', true);
            btn.find('.btn-text').addClass('d-none');
            btn.find('.spinner-border').removeClass('d-none');

            var url = "{{ route('property.clients.returns-config.store', $client->encrypted_id) }}";
            var payload = {
                _token: "{{ csrf_token() }}",
                property_ids: propertyIds
            };

            if (editingReturnsBatchId) {
                url = "{{ route('property.clients.returns-config.update', ['encryptedId' => $client->encrypted_id, 'batchId' => '__BATCH__']) }}".replace('__BATCH__', editingReturnsBatchId);
                payload._method = 'PUT';
            }

            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: payload,
                success: function(response) {
                    resetReturnsConfigEditState();
                    loadReturnsConfig();
                    toastr.success(response.message || 'Returns batch saved.');
                },
                error: function(xhr) {
                    var errorMessage = 'Failed to save returns batch.';
                    if (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) {
                        errorMessage = xhr.responseJSON.error || xhr.responseJSON.message;
                    }
                    toastr.error(errorMessage);
                },
                complete: function() {
                    btn.prop('disabled', false);
                    btn.find('.btn-text').removeClass('d-none');
                    btn.find('.spinner-border').addClass('d-none');
                }
            });
        }

        function deleteReturnsConfig(batchId, btn) {
            if (btn) {
                btn.prop('disabled', true);
                btn.find('.material-symbols-outlined').addClass('d-none');
                btn.find('.spinner-border').removeClass('d-none');
            }

            $.ajax({
                url: "{{ route('property.clients.returns-config.delete', ['encryptedId' => $client->encrypted_id, 'batchId' => '__BATCH__']) }}".replace('__BATCH__', batchId),
                type: 'POST',
                dataType: 'json',
                data: {
                    _token: "{{ csrf_token() }}",
                    _method: 'DELETE'
                },
                success: function(response) {
                    if (editingReturnsBatchId && Number(editingReturnsBatchId) === Number(batchId)) {
                        resetReturnsConfigEditState();
                    }
                    loadReturnsConfig();
                    toastr.success(response.message || 'Returns batch deleted.');
                },
                error: function(xhr) {
                    var errorMessage = 'Failed to delete returns config.';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage = xhr.responseJSON.error;
                    }
                    toastr.error(errorMessage);
                },
                complete: function() {
                    if (btn) {
                        btn.prop('disabled', false);
                        btn.find('.material-symbols-outlined').removeClass('d-none');
                        btn.find('.spinner-border').addClass('d-none');
                    }
                }
            });
        }

        function disbandAllReturnsConfig() {
            var btn = $('#btnDisbandAllReturnsBatches');
            btn.prop('disabled', true);
            btn.find('.btn-text').addClass('d-none');
            btn.find('.spinner-border').removeClass('d-none');

            $.ajax({
                url: "{{ route('property.clients.returns-config.disband-all', $client->encrypted_id) }}",
                type: 'POST',
                dataType: 'json',
                data: {
                    _token: "{{ csrf_token() }}",
                    _method: 'DELETE'
                },
                success: function(response) {
                    resetReturnsConfigEditState();
                    loadReturnsConfig();
                    toastr.success(response.message || 'All batches disbanded.');
                },
                error: function(xhr) {
                    var errorMessage = 'Failed to disband all batches.';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage = xhr.responseJSON.error;
                    }
                    toastr.error(errorMessage);
                },
                complete: function() {
                    btn.prop('disabled', false);
                    btn.find('.btn-text').removeClass('d-none');
                    btn.find('.spinner-border').addClass('d-none');
                }
            });
        }

        function loadPropertiesDropdown() {
            $.ajax({
                url: "{{ route('property.clients.properties.dropdown', $client->encrypted_id) }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#propertySelect').empty().append('<option value="">-- Select Property --</option>');
                    $.each(response, function(index, property) {
                        $('#propertySelect').append('<option value="' + property.id + '">' + property.text + '</option>');
                    });
                },
                error: function(xhr, status, error) {
                    console.error('Error loading properties dropdown:', error);
                    toastr.error('Failed to load properties. Please try again.');
                }
            });
        }

        function loadTenantPropertiesDropdown() {
            $.ajax({
                url: "{{ route('property.clients.properties.dropdown', $client->encrypted_id) }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#tenantPropertySelect').empty().append('<option value="">-- Select Property --</option>');
                    $.each(response, function(index, property) {
                        $('#tenantPropertySelect').append('<option value="' + property.id + '">' + property.text + '</option>');
                    });
                },
                error: function(xhr, status, error) {
                    console.error('Error loading properties dropdown:', error);
                    toastr.error('Failed to load properties. Please try again.');
                }
            });
        }

        function loadUnits(propertyId) {
            // Show spinner
            $('#propertySpinner').show();

            // Empty propertyId = "All Properties" — scope by landlord instead
            var params = propertyId ? { property_id: propertyId } : { client_id: '{{ $client->id }}' };

            $.ajax({
                url: "{{ route('property.units.get') }}",
                type: 'GET',
                data: params,
                dataType: 'json',
                success: function(response) {
                    unitsTable.clear();
                    if (response.data && response.data.length > 0) {
                        unitsTable.rows.add(response.data);
                        unitsTable.draw();
                    } else {
                        unitsTable.draw();
                        toastr.info('No units found.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading units:', error);
                    toastr.error('Failed to load units. Please try again.');
                },
                complete: function() {
                    // Hide spinner
                    $('#propertySpinner').hide();
                }
            });
        }

        function loadTenants(propertyId) {
            // Show spinner
            $('#tenantSpinner').show();

            $.ajax({
                url: "{{ route('ajax.property.tenants.get') }}",
                type: 'GET',
                data: {
                    property_id: propertyId
                },
                dataType: 'json',
                success: function(response) {
                    tenantsTable.clear();
                    if (response.data && response.data.length > 0) {
                        tenantsTable.rows.add(response.data);
                        tenantsTable.draw();
                    } else {
                        tenantsTable.draw();
                        toastr.info('No tenants found for this property.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading tenants:', error);
                    toastr.error('Failed to load tenants. Please try again.');
                },
                complete: function() {
                    // Hide spinner
                    $('#tenantSpinner').hide();
                }
            });
        }

        // Helper: format date to dd/mm/yy
        function formatDateShort(dateStr) {
            if (!dateStr) return '';
            var d = new Date(dateStr);
            if (isNaN(d.getTime())) {
                var parts = dateStr.split(' ');
                d = new Date(parts[0]);
                if (isNaN(d.getTime())) return dateStr;
            }
            var dd = ('0' + d.getDate()).slice(-2);
            var mm = ('0' + (d.getMonth() + 1)).slice(-2);
            var yy = String(d.getFullYear()).slice(-2);
            return dd + '/' + mm + '/' + yy;
        }

        // Initialize Leases DataTable (empty initially) - responsive columns
        leasesTable = $('#leasesTable').DataTable({
            data: [],
            columns: [{
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'ref_number'
                },
                {
                    data: 'tenant'
                },
                {
                    data: 'property'
                },
                {
                    data: 'unit'
                },
                {
                    data: 'date_start',
                    render: function(data) {
                        return formatDateShort(data);
                    }
                },
                {
                    data: 'date_end',
                    render: function(data) {
                        return formatDateShort(data);
                    }
                }
            ],
            pageLength: 10,
            responsive: true,
            language: {
                emptyTable: "Please select a property to view leases"
            }
        });

        // Load leases when property is selected in Leases tab
        $('#leasesPropertySelect').on('change', function() {
            var propertyId = $(this).val();
            if (propertyId) {
                loadLeases(propertyId);
            } else {
                leasesTable.clear().draw();
            }
        });

        // Populate leases property dropdown when leases tab is clicked
        $('#leases-tab').on('click', function() {
            if (!leasesPropertiesDropdownLoaded) {
                loadLeasesPropertiesDropdown();
                leasesPropertiesDropdownLoaded = true;
            }
        });

        function loadLeases(propertyId) {
            // Show spinner
            $('#leasesSpinner').show();

            $.ajax({
                url: "{{ route('property.clients.leases', $client->encrypted_id) }}",
                type: 'GET',
                data: {
                    property_id: propertyId
                },
                dataType: 'json',
                success: function(response) {
                    leasesTable.clear();
                    if (response.data && response.data.length > 0) {
                        leasesTable.rows.add(response.data);
                        leasesTable.draw();
                    } else {
                        leasesTable.draw();
                        toastr.info('No leases found for this property.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading leases:', error);
                    toastr.error('Failed to load leases. Please try again.');
                },
                complete: function() {
                    // Hide spinner
                    $('#leasesSpinner').hide();
                }
            });
        }

        function loadLeasesPropertiesDropdown() {
            $.ajax({
                url: "{{ route('property.clients.properties.dropdown', $client->encrypted_id) }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#leasesPropertySelect').empty().append('<option value="">-- Select Property --</option>');
                    $.each(response, function(index, property) {
                        $('#leasesPropertySelect').append('<option value="' + property.id + '">' + property.text + '</option>');
                    });
                },
                error: function(xhr, status, error) {
                    console.error('Error loading leases properties dropdown:', error);
                    toastr.error('Failed to load properties. Please try again.');
                }
            });
        }

        // Initialize Recoveries DataTable (empty initially)
        var recoveriesTable = $('#recoveriesTable').DataTable({
            data: [],
            columns: [{
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'ref_number'
                },
                {
                    data: 'recovery_date'
                },
                {
                    data: 'amount'
                },
                {
                    data: 'property'
                },
                {
                    data: 'zone'
                },
                {
                    data: 'transaction_type'
                },
                {
                    data: 'commisionable',
                    render: function(data) {
                        if (data === 'Yes') {
                            return '<span class="badge bg-success">Yes</span>';
                        } else {
                            return '<span class="badge bg-secondary">No</span>';
                        }
                    }
                },
                {
                    data: 'created_by'
                },
                {
                    data: 'encrypted_id',
                    orderable: false,
                    searchable: false,
                    render: function(encryptedId, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        // View button
                        buttons += `
                            <a href="/property/recovery/${encryptedId}" title="View Details">
                                <span class="material-symbols-outlined" style="font-size:18px;">visibility</span>
                            </a>
                        `;
                        // Edit button
                        buttons += `
                            <a href="/property/recovery/${encryptedId}/edit" title="Edit">
                                <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
                            </a>
                        `;
                        buttons += '</div>';
                        return buttons;
                    }
                }
            ],
            pageLength: 10,
            responsive: true,
            language: {
                emptyTable: "No recoveries found for this client"
            }
        });

        var recoveriesLoaded = false;

        // Load recoveries when tab is clicked
        $('#recoveries-tab').on('click', function() {
            if (!recoveriesLoaded) {
                loadRecoveries();
                recoveriesLoaded = true;
            }
        });

        // Function to load recoveries
        function loadRecoveries() {
            $.ajax({
                url: "{{ route('property.clients.recoveries', $client->encrypted_id) }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.data) {
                        recoveriesTable.clear();
                        recoveriesTable.rows.add(response.data);
                        recoveriesTable.draw();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading recoveries:', error);
                    toastr.error('Failed to load recoveries. Please try again.');
                }
            });
        }

        // Initialize Inspections DataTable (empty initially)
        var inspectionsTable = $('#inspectionsTable').DataTable({
            data: [],
            columns: [{
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'property'
                },
                {
                    data: 'zone'
                },
                {
                    data: 'visited_date'
                },
                {
                    data: 'left_date'
                },
                {
                    data: 'duration'
                },
                {
                    data: 'units_count'
                },
                {
                    data: 'created_by'
                },
                {
                    data: 'encrypted_id',
                    orderable: false,
                    searchable: false,
                    render: function(encryptedId, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        // View button
                        buttons += `
                            <a href="/property/inspections/${encryptedId}" title="View Details">
                                <span class="material-symbols-outlined" style="font-size:18px;">visibility</span>
                            </a>
                        `;
                        // Edit button
                        buttons += `
                            <a href="/property/inspections/${encryptedId}/edit" title="Edit">
                                <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
                            </a>
                        `;
                        buttons += '</div>';
                        return buttons;
                    }
                }
            ],
            pageLength: 10,
            responsive: true,
            language: {
                emptyTable: "No inspections found for this client"
            }
        });

        var inspectionsLoaded = false;

        // Load inspections when tab is clicked
        $('#inspections-tab').on('click', function() {
            if (!inspectionsLoaded) {
                loadInspections();
                inspectionsLoaded = true;
            }
        });

        // Function to load inspections
        function loadInspections() {
            $.ajax({
                url: "{{ route('property.clients.inspections', $client->encrypted_id) }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.data) {
                        inspectionsTable.clear();
                        inspectionsTable.rows.add(response.data);
                        inspectionsTable.draw();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading inspections:', error);
                    toastr.error('Failed to load inspections. Please try again.');
                }
            });
        }

        // Bulk Upload Modal Logic
        $('#upload_type').on('change', function() {
            var uploadType = $(this).val();

            if (uploadType) {
                $('#sampleDownloadSection').show();
                $('#fileUploadSection').show();
                $('#instructionsSection').show();
                $('#uploadBtn').prop('disabled', false);

                // Update instructions based on type
                var instructions = '';
                if (uploadType === 'properties') {
                    instructions = `
                        <li>Download the sample properties template file</li>
                        <li>Fill in the required columns: Property Name, Zone, Zone Area, LR Number, Property Type, Acquisition Date</li>
                        <li>Optional columns: Description, Latitude, Longitude</li>
                        <li>Save your file and upload it using the form above</li>
                        <li>The system will validate and create properties automatically</li>
                    `;
                } else if (uploadType === 'units') {
                    instructions = `
                        <li>Download the sample units template file</li>
                        <li>Fill in the required columns: Property Reference, Unit Number, Unit Type, Usage Type, Unit Condition</li>
                        <li>Optional columns: Unit Name, Rent Amount, Description, VAT, Water Meter Reading</li>
                        <li>Ensure the Property Reference matches an existing property</li>
                        <li>Save your file and upload it using the form above</li>
                        <li>The system will validate and create units automatically</li>
                    `;
                } else if (uploadType === 'tenants') {
                    instructions = `
                        <li>Download the sample tenants template file</li>
                        <li>Fill in the required columns: Property Reference, Unit Number, Title, First Name, Second Name, Gender, Mobile Number (254XXXXXXXXX)</li>
                        <li>Required columns: Email Address, Tenant Type, Lease Start Date, Lease Years, Rent Amount, Frequency</li>
                        <li>Optional columns: Last Name, National ID, Passport Number</li>
                        <li>Ensure the Property Reference and Unit Number match existing available units</li>
                        <li>Mobile number must be in format: 254XXXXXXXXX (12 digits)</li>
                        <li>Valid Tenant Types: Individual, Corporate, Government</li>
                        <li>Valid Frequencies: Monthly, Quarterly, Semi-Annually, Annually</li>
                        <li>The system will automatically generate tenant reference numbers (TEN-XXXX)</li>
                        <li>Units will be marked as occupied after successful tenant creation</li>
                    `;
                }
                $('#instructionsList').html(instructions);
            } else {
                $('#sampleDownloadSection').hide();
                $('#fileUploadSection').hide();
                $('#instructionsSection').hide();
                $('#uploadBtn').prop('disabled', true);
            }
        });

        // Download Sample File
        $('#downloadSampleBtn').on('click', function() {
            var uploadType = $('#upload_type').val();
            if (uploadType) {
                window.location.href = "{{ route('property.bulk.sample') }}?type=" + uploadType + "&client_id={{ $client->id }}";
            }
        });

        // Handle form submission
        $('#bulkUploadForm').on('submit', function(e) {
            var uploadBtn = $('#uploadBtn');
            var spinner = $('#uploadSpinner');

            spinner.removeClass('d-none');
            uploadBtn.prop('disabled', true);
            uploadBtn.find('span:last').text('Processing...');
        });

        // Reset modal on close
        $('#bulkUploadModal').on('hidden.bs.modal', function() {
            $('#bulkUploadForm')[0].reset();
            $('#sampleDownloadSection').hide();
            $('#fileUploadSection').hide();
            $('#instructionsSection').hide();
            $('#uploadBtn').prop('disabled', true);
            $('#uploadSpinner').addClass('d-none');
            $('#uploadBtn').find('span:last').text('Upload & Process');
        });

        // Initialize Repairs DataTable (empty initially)
        var repairsTable = $('#repairsTable').DataTable({
            data: [],
            columns: [{
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'ref_number'
                },
                {
                    data: 'property_name'
                },
                {
                    data: 'visited_date_time',
                    render: function(data) {
                        if (!data) return '-';
                        const date = new Date(data.replace(' ', 'T'));
                        if (isNaN(date.getTime())) return data;
                        const dateOptions = {
                            year: 'numeric',
                            month: 'short',
                            day: '2-digit'
                        };
                        const timeOptions = {
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: true
                        };
                        const formattedDate = date.toLocaleDateString('en-GB', dateOptions);
                        const formattedTime = date.toLocaleTimeString('en-GB', timeOptions);
                        return formattedDate + ' ' + formattedTime;
                    }
                },
                {
                    data: 'created_by_name'
                },
                {
                    data: 'current_status_id',
                    render: function(data) {
                        if (data == 1) {
                            return '<span class="badge bg-warning">Pending</span>';
                        } else if (data == 5) {
                            return '<span class="badge bg-success">Approved</span>';
                        } else if (data == 41) {
                            return '<span class="badge bg-danger">Rejected</span>';
                        }
                        return '<span class="badge bg-secondary">Unknown</span>';
                    }
                },
                {
                    data: 'encrypted_id',
                    orderable: false,
                    searchable: false,
                    render: function(encryptedId, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        buttons += `
                            <a href="/property/repairs/${encryptedId}" class="text-primary" title="View">
                                <span class="material-symbols-outlined" style="font-size: 20px;">visibility</span>
                            </a>
                        `;
                        if (row.current_status_id == 1) {
                            buttons += `
                                <a href="/property/repairs/${encryptedId}/edit" class="text-success" title="Edit">
                                    <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                                </a>
                            `;
                        }
                        buttons += '</div>';
                        return buttons;
                    }
                }
            ],
            pageLength: 10,
            responsive: true,
            language: {
                emptyTable: "No repairs & maintenance requests found for this client"
            }
        });

        var repairsLoaded = false;

        // Load repairs when tab is clicked
        $('#repairs-tab').on('click', function() {
            if (!repairsLoaded) {
                loadRepairs();
                repairsLoaded = true;
            }
        });

        // Function to load repairs
        function loadRepairs() {
            $.ajax({
                url: "{{ route('property.clients.repairs', $client->encrypted_id) }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.data) {
                        repairsTable.clear();
                        repairsTable.rows.add(response.data);
                        repairsTable.draw();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading repairs:', error);
                    toastr.error('Failed to load repairs. Please try again.');
                }
            });
        }

        // =====================================================
        // Bank Details CRUD Functions
        // =====================================================

        var banksList = [];
        var branchesByBank = {};

        // Load bank details on page load
        loadBankDetails();
        loadBanksDropdown();

        // Load all bank details for the client
        function loadBankDetails() {
            $.ajax({
                url: "{{ route('ajax.clients.bank-details', $client->encrypted_id) }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        renderBankDetailsTable(response.data);
                    } else {
                        $('#bankDetailsTableBody').html('<tr><td colspan="7" class="text-center text-muted">No bank details found</td></tr>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading bank details:', error);
                    $('#bankDetailsTableBody').html('<tr><td colspan="7" class="text-center text-danger">Failed to load bank details</td></tr>');
                }
            });
        }

        // Render the bank details table
        function renderBankDetailsTable(data) {
            if (data.length === 0) {
                $('#bankDetailsTableBody').html('<tr><td colspan="6" class="text-center text-muted">No bank details found. Click "Add Bank" to add one.</td></tr>');
                return;
            }

            var html = '';
            data.forEach(function(item, index) {
                html += '<tr>';
                html += '<td>' + (index + 1) + '</td>';
                html += '<td>' + item.bank_name + '</td>';
                html += '<td>' + item.branch_name + '</td>';
                html += '<td>' + item.account_name + '</td>';
                html += '<td>' + item.account_number + '</td>';
                html += '<td>';
                html += '<div class="d-flex gap-1">';
                html += '<a href="#" class="text-primary btn-edit-bank" title="Edit" data-id="' + item.encrypted_id + '">';
                html += '<span class="material-symbols-outlined" style="font-size:18px;">edit</span>';
                html += '</a>';
                html += '<a href="#" class="text-danger btn-delete-bank" title="Delete" data-id="' + item.encrypted_id + '" data-info="' + item.bank_name + ' - ' + item.account_number + '">';
                html += '<span class="material-symbols-outlined" style="font-size:18px;">delete</span>';
                html += '</a>';
                html += '</div>';
                html += '</td>';
                html += '</tr>';
            });
            $('#bankDetailsTableBody').html(html);
        }

        // Load banks dropdown
        function loadBanksDropdown() {
            $.ajax({
                url: "{{ route('ajax.client-bank-details.banks') }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        banksList = response.data;
                        var options = '<option value="">Select Bank</option>';
                        response.data.forEach(function(bank) {
                            options += '<option value="' + bank.id + '">' + bank.bank_name + '</option>';
                        });
                        $('#bankSelect').html(options);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading banks:', error);
                }
            });
        }

        // Load branches when bank is selected
        $('#bankSelect').on('change', function() {
            var bankId = $(this).val();
            if (!bankId) {
                $('#branchSelect').html('<option value="">Select Branch (Optional)</option>');
                return;
            }

            // Check if we already have branches cached
            if (branchesByBank[bankId]) {
                populateBranches(branchesByBank[bankId]);
                return;
            }

            $.ajax({
                url: "{{ url('ajax/client-bank-details/branches') }}/" + bankId,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        branchesByBank[bankId] = response.data;
                        populateBranches(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading branches:', error);
                    $('#branchSelect').html('<option value="">Select Branch (Optional)</option>');
                }
            });
        });

        function populateBranches(branches) {
            var options = '<option value="">Select Branch (Optional)</option>';
            branches.forEach(function(branch) {
                options += '<option value="' + branch.id + '">' + branch.branch_name + '</option>';
            });
            $('#branchSelect').html(options);
        }

        // Add Bank button click handler
        $('#addBankDetailBtn').on('click', function() {
            $('#bankDetailId').val('');
            $('#bankDetailForm')[0].reset();
            $('#bankDetailModalTitle').text('Add Bank Detail');
            $('#saveBankDetailBtnText').text('Save');
            $('#branchSelect').html('<option value="">Select Branch (Optional)</option>');
        });

        // Edit bank detail - event delegation
        $(document).on('click', '.btn-edit-bank', function(e) {
            e.preventDefault();
            var encryptedId = $(this).data('id');
            
            $.ajax({
                url: "{{ url('ajax/clients/bank-details') }}/" + encryptedId,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        var data = response.data;
                        $('#bankDetailId').val(encryptedId);
                        $('#bankSelect').val(data.bank_id);
                        $('#accountName').val(data.account_name);
                        $('#accountNumber').val(data.account_number);
                        $('#isDefault').prop('checked', data.is_default == 1);
                        $('#bankDetailModalTitle').text('Edit Bank Detail');
                        $('#saveBankDetailBtnText').text('Update');

                        // Load branches for the selected bank
                        if (data.bank_id) {
                            $.ajax({
                                url: "{{ url('ajax/client-bank-details/branches') }}/" + data.bank_id,
                                type: 'GET',
                                dataType: 'json',
                                success: function(branchResponse) {
                                    if (branchResponse.success && branchResponse.data) {
                                        branchesByBank[data.bank_id] = branchResponse.data;
                                        populateBranches(branchResponse.data);
                                        if (data.branch_id) {
                                            $('#branchSelect').val(data.branch_id);
                                        }
                                    }
                                }
                            });
                        }

                        $('#bankDetailModal').modal('show');
                    } else {
                        toastr.error('Failed to load bank detail');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading bank detail:', error);
                    toastr.error('Failed to load bank detail');
                }
            });
        });

        // Save bank detail button click handler
        $('#saveBankDetailBtn').on('click', function() {
            var encryptedId = $('#bankDetailId').val();
            var isEdit = encryptedId !== '';

            var formData = {
                client_id: $('#bankDetailClientId').val(),
                bank_id: $('#bankSelect').val(),
                branch_id: $('#branchSelect').val() || null,
                account_name: $('#accountName').val(),
                account_number: $('#accountNumber').val(),
                is_default: $('#isDefault').is(':checked') ? 1 : 0,
                _token: '{{ csrf_token() }}'
            };

            // Validate required fields
            if (!formData.bank_id) {
                toastr.error('Please select a bank');
                return;
            }
            if (!formData.account_name) {
                toastr.error('Please enter account name');
                return;
            }
            if (!formData.account_number) {
                toastr.error('Please enter account number');
                return;
            }

            $('#saveBankDetailBtn').prop('disabled', true);
            $('#saveBankDetailBtnText').text(isEdit ? 'Updating...' : 'Saving...');

            var url = isEdit 
                ? "{{ url('ajax/clients/bank-details') }}/" + encryptedId
                : "{{ route('ajax.clients.bank-details.store') }}";

            var method = isEdit ? 'PUT' : 'POST';

            $.ajax({
                url: url,
                type: method,
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message || (isEdit ? 'Bank detail updated successfully' : 'Bank detail added successfully'));
                        $('#bankDetailModal').modal('hide');
                        loadBankDetails();
                    } else {
                        var errorMsg = response.message || 'Failed to save bank detail';
                        if (response.errors) {
                            errorMsg = Object.values(response.errors).flat().join('<br>');
                        }
                        toastr.error(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error saving bank detail:', error);
                    var errorMsg = 'Failed to save bank detail';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        errorMsg = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                    }
                    toastr.error(errorMsg);
                },
                complete: function() {
                    $('#saveBankDetailBtn').prop('disabled', false);
                    $('#saveBankDetailBtnText').text(isEdit ? 'Update' : 'Save');
                }
            });
        });

        // Delete bank detail - event delegation
        $(document).on('click', '.btn-delete-bank', function(e) {
            e.preventDefault();
            var encryptedId = $(this).data('id');
            var info = $(this).data('info');
            $('#deleteBankDetailId').val(encryptedId);
            $('#deleteBankDetailInfo').text(info);
            $('#deleteBankDetailModal').modal('show');
        });

        // Confirm delete button click handler
        $('#confirmDeleteBankDetailBtn').on('click', function() {
            var encryptedId = $('#deleteBankDetailId').val();

            $.ajax({
                url: "{{ url('ajax/clients/bank-details') }}/" + encryptedId,
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message || 'Bank detail deleted successfully');
                        $('#deleteBankDetailModal').modal('hide');
                        loadBankDetails();
                    } else {
                        toastr.error(response.message || 'Failed to delete bank detail');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error deleting bank detail:', error);
                    toastr.error('Failed to delete bank detail');
                }
            });
        });

        // Reset modal on close
        $('#bankDetailModal').on('hidden.bs.modal', function() {
            $('#bankDetailForm')[0].reset();
            $('#bankDetailId').val('');
            $('#branchSelect').html('<option value="">Select Branch (Optional)</option>');
        });

        // Property Bank Assignment - Assign bank button click handler (event delegation)
        $(document).on('click', '.assign-bank-btn', function(e) {
            e.preventDefault();
            var propertyId = $(this).data('property-id');
            var clientId = $(this).data('client-id');
            
            $('#assignPropertyId').val(propertyId);
            $('#assignClientId').val(clientId);
            $('#clientBankSelect').html('<option value="">Loading...</option>');
            
            $.ajax({
                url: "{{ route('property.banks.available') }}",
                type: 'GET',
                data: {
                    property_id: propertyId,
                    client_id: clientId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        var options = '<option value="">Select Bank Account</option>';
                        response.data.forEach(function(bank) {
                            options += `<option value="${bank.id}">
                                ${bank.bank_name} - ${bank.account_number} (${bank.account_name})
                            </option>`;
                        });
                        $('#clientBankSelect').html(options);
                        
                        if (response.data.length === 0) {
                            $('#clientBankSelect').html('<option value="">No bank accounts available</option>');
                            toastr.info('No bank accounts set up for this client. Please add bank details first.');
                        }
                    } else {
                        $('#clientBankSelect').html('<option value="">Error loading banks</option>');
                        toastr.error('Failed to load available banks');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading banks:', error);
                    $('#clientBankSelect').html('<option value="">Error loading banks</option>');
                    toastr.error('Failed to load available banks');
                }
            });
            
            $('#assignPropertyBankModal').modal('show');
        });

        // Save property bank button click handler
        $('#savePropertyBankBtn').on('click', function() {
            var propertyId = $('#assignPropertyId').val();
            var clientId = $('#assignClientId').val();
            var clientBankId = $('#clientBankSelect').val();

            if (!clientBankId) {
                toastr.error('Please select a bank account');
                return;
            }

            $('#savePropertyBankBtn').prop('disabled', true);

            $.ajax({
                url: "{{ route('property.banks.assign') }}",
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    property_id: propertyId,
                    client_id: clientId,
                    client_bank_id: clientBankId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message || 'Bank assigned successfully');
                        $('#assignPropertyBankModal').modal('hide');
                        loadProperties();
                    } else {
                        toastr.error(response.message || 'Failed to assign bank');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error assigning bank:', error);
                    var errorMsg = 'Failed to assign bank';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        errorMsg = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                    }
                    toastr.error(errorMsg);
                },
                complete: function() {
                    $('#savePropertyBankBtn').prop('disabled', false);
                }
            });
        });

        // Reset assign property bank modal on close
        $('#assignPropertyBankModal').on('hidden.bs.modal', function() {
            $('#assignPropertyBankForm')[0].reset();
            $('#assignPropertyId').val('');
            $('#assignClientId').val('');
            $('#clientBankSelect').html('<option value="">Select Bank Account</option>');
        });
    });
</script>
@endsection