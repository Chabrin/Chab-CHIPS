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
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">person</span> Overview
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="bank-tab" data-bs-toggle="tab" data-bs-target="#bank" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">account_balance</span> Bank
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
                                <button class="nav-link" id="invoicing-tab" data-bs-toggle="tab" data-bs-target="#invoicing" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">receipt_long</span> Invoicing
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="generated-invoices-tab" data-bs-toggle="tab" data-bs-target="#generated-invoices" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">request_quote</span> Generated Invoices
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tenant-history-tab" data-bs-toggle="tab" data-bs-target="#tenant-history" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">history</span> Tenant History
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

                            <!-- Overview Tab -->
                            <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                                <div class="row g-3">
                                    <div class="col-lg-4 col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-light py-2">
                                                <h6 class="mb-0" style="font-size:0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">badge</span>
                                                    Personal & Tax Information
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-1">
                                                    <div class="col-5"><small class="text-muted">Client Type:</small></div>
                                                    <div class="col-7"><small><strong>
                                                        {{ $client->client_type_id == 3 ? 'Company' : ($client->client_type_id == 4 ? 'Partnership and Trust' : 'Individual') }}
                                                    </strong></small></div>
                                                    <div class="col-5"><small class="text-muted">{{ $client->client_type_id == 3 ? 'Company Name:' : ($client->client_type_id == 4 ? 'Partnership Name:' : 'Full Names:') }}</small></div>
                                                    <div class="col-7"><small><strong>{{ $client->names ?? '-' }}</strong></small></div>
                                                    @if($client->client_type_id == 3)
                                                    <div class="col-5"><small class="text-muted">Registration No:</small></div>
                                                    <div class="col-7"><small><strong>{{ $client->company_registration_number ?? '-' }}</strong></small></div>
                                                    @elseif($client->client_type_id != 4)
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

                                    <div class="col-lg-8 col-md-6">
                                        <div class="row g-3">
                                            @if($client->client_type_id == 4)
                                            <div class="col-12">
                                                <div class="card">
                                                    <div class="card-header bg-light py-2">
                                                        <h6 class="mb-0" style="font-size:0.9rem;">
                                                            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">groups</span>
                                                            Partners
                                                        </h6>
                                                    </div>
                                                    <div class="card-body py-2">
                                                        <div class="d-flex flex-wrap gap-2">
                                                            @forelse($client->partners as $partner)
                                                            <div class="border rounded-3 p-2" style="min-width:190px;">
                                                                <div class="d-flex align-items-center gap-1 mb-1">
                                                                    <span class="material-symbols-outlined text-primary" style="font-size:16px;">person</span>
                                                                    <small><strong>{{ $partner->name }}</strong></small>
                                                                </div>
                                                                <div class="small text-muted">ID: {{ $partner->national_id ?? '-' }}</div>
                                                                <div class="small text-muted">KRA PIN: {{ $partner->kra_pin ?? '-' }}</div>
                                                                <div class="small text-muted">{{ $partner->phone ?? '-' }}</div>
                                                            </div>
                                                            @empty
                                                            <small class="text-muted">No partners recorded.</small>
                                                            @endforelse
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif

                                            <div class="col-12 col-xl-6">
                                                <div class="card h-100">
                                                    <div class="card-header bg-light py-2">
                                                        <h6 class="mb-0" style="font-size:0.9rem;">
                                                            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">family_restroom</span>
                                                            Next of Kin
                                                        </h6>
                                                    </div>
                                                    <div class="card-body py-2">
                                                        <div class="d-flex flex-wrap gap-2">
                                                            @forelse($client->nextOfKins as $kin)
                                                            <div class="border rounded-3 p-2" style="min-width:170px;">
                                                                <div class="d-flex align-items-center gap-1 mb-1">
                                                                    <span class="material-symbols-outlined text-primary" style="font-size:16px;">diversity_1</span>
                                                                    <small><strong>{{ $kin->names ?: trim(($kin->kin_first_name ?? '') . ' ' . ($kin->kin_last_name ?? '')) ?: '-' }}</strong></small>
                                                                </div>
                                                                <div class="small text-muted">{{ $kin->relationship ?? '-' }}</div>
                                                                <div class="small text-muted">{{ $kin->mobile_number ?? '-' }}</div>
                                                                <div class="small text-muted">{{ $kin->email_address ?? '-' }}</div>
                                                            </div>
                                                            @empty
                                                            <small class="text-muted">No next of kin recorded.</small>
                                                            @endforelse
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 col-xl-6">
                                                <div class="card h-100">
                                                    <div class="card-header bg-light py-2">
                                                        <h6 class="mb-0" style="font-size:0.9rem;">
                                                            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">contact_phone</span>
                                                            Other Contacts
                                                        </h6>
                                                    </div>
                                                    <div class="card-body py-2">
                                                        <div class="d-flex flex-wrap gap-2">
                                                            @forelse($client->otherContacts as $contact)
                                                            <div class="border rounded-3 p-2" style="min-width:170px;">
                                                                <div class="d-flex align-items-center gap-1 mb-1">
                                                                    <span class="material-symbols-outlined text-primary" style="font-size:16px;">person</span>
                                                                    <small><strong>{{ $contact->contact_names ?? '-' }}</strong></small>
                                                                </div>
                                                                <div class="small text-muted">{{ $contact->other_mobile_number ?? '-' }}</div>
                                                                <div class="small text-muted">{{ $contact->other_email ?? '-' }}</div>
                                                            </div>
                                                            @empty
                                                            <small class="text-muted">No other contacts recorded.</small>
                                                            @endforelse
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bank Tab -->
                            <div class="tab-pane fade" id="bank" role="tabpanel" aria-labelledby="bank-tab">
                                <div class="card">
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
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="cs-card" id="cs-sla-card" style="--cs-accent:#0ca30c; --cs-icon-bg:#dcf3dc;">
                                            <div class="cs-icon"><span class="material-symbols-outlined">verified</span></div>
                                            <div class="cs-body">
                                                <div class="cs-value" id="cs-sla-coverage"><span class="cs-skeleton" style="width:28px;height:18px;"></span></div>
                                                <div class="cs-label">Properties with SLA</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div id="cs-sla-gap-wrap" class="mb-4" style="display:none;">
                                    <div class="alert alert-light-warning py-2 px-3 mb-0">
                                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">warning</span>
                                        <strong>No current SLA:</strong>
                                        <span id="cs-sla-gap-list"></span>
                                    </div>
                                </div>

                            </div>

                            <!-- Properties Tab -->
                            <div class="tab-pane fade" id="properties" role="tabpanel" aria-labelledby="properties-tab">
                                <div class="d-flex justify-content-end mb-3">
                                    <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addPropertyModal">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">add_home</span> Add Property
                                    </button>
                                </div>
                                <div class="table-responsive custom-scrollbar">
                                    <table id="propertiesTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Ref No</th>
                                                <th>Status</th>
                                                <th>Name</th>
                                                <th>Zone</th>
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
                                <div class="d-flex justify-content-end mb-3">
                                    <a href="{{ route('service.agreements.create', ['client' => Crypt::encrypt($client->id)]) }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">approval_delegation</span> Add SLA
                                    </a>
                                </div>
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

                                <ul class="nav nav-tabs mb-3" id="unitsSubTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="units-listing-subtab" data-bs-toggle="tab" data-bs-target="#units-listing-pane" type="button" role="tab">
                                            Units Listing
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="units-uploads-subtab" data-bs-toggle="tab" data-bs-target="#units-uploads-pane" type="button" role="tab">
                                            Units Uploads
                                        </button>
                                    </li>
                                </ul>

                                <div class="tab-content" id="unitsSubTabsContent">
                                    <!-- Units Listing sub-tab -->
                                    <div class="tab-pane fade show active" id="units-listing-pane" role="tabpanel">
                                        <div class="d-flex justify-content-end mb-2">
                                            @permission('create_units')
                                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="addUnitBtn" disabled title="Select a specific property first">
                                                <span class="material-symbols-outlined me-1" style="font-size:18px;">add_circle</span>
                                                Add Unit
                                            </button>
                                            @endpermission
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

                                    <!-- Units Uploads sub-tab — staged rows from bulk upload, awaiting review/push -->
                                    <div class="tab-pane fade" id="units-uploads-pane" role="tabpanel">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <p class="text-muted small mb-0">Staged units from bulk uploads. Review, edit, or delete a row, then complete the upload to push them into the main unit records. Select a specific property above to use this.</p>
                                            <div class="d-flex flex-shrink-0 ms-2 gap-2">
                                                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="openUnitBulkUploadBtn" data-bs-toggle="modal" data-bs-target="#unitBulkUploadModal" disabled>
                                                    <span class="material-symbols-outlined me-1" style="font-size:18px;">upload_file</span>
                                                    Bulk Upload Units
                                                </button>
                                                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="deleteSelectedUnitUploadsBtn" disabled>
                                                    <span class="spinner-border spinner-border-sm me-2 d-none" id="deleteSelectedUnitUploadsSpinner"></span>
                                                    <span class="material-symbols-outlined me-1" style="font-size:18px;">delete</span>
                                                    Delete Selected
                                                </button>
                                                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="pushUnitUploadsToMainBtn" disabled>
                                                    <span class="spinner-border spinner-border-sm me-2 d-none" id="pushUnitUploadsSpinner"></span>
                                                    <span class="material-symbols-outlined me-1" style="font-size:18px;">publish</span>
                                                    Complete Upload
                                                </button>
                                            </div>
                                        </div>
                                        <div class="table-responsive custom-scrollbar">
                                            <table id="unitUploadsTable" class="display" style="width:100%;">
                                                <thead>
                                                    <tr>
                                                        <th><input type="checkbox" class="form-check-input" id="unitUploadsSelectAll"></th>
                                                        <th>#</th>
                                                        <th>Unit Number</th>
                                                        <th>Unit Name</th>
                                                        <th>Usage Type</th>
                                                        <th>Rent Amount</th>
                                                        <th>Topology</th>
                                                        <th>Category</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
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

                                <ul class="nav nav-tabs mb-3" id="tenantsSubTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="tenants-listing-subtab" data-bs-toggle="tab" data-bs-target="#tenants-listing-pane" type="button" role="tab">
                                            Tenants Listing
                                        </button>
                                    </li>
                                    @permission('create_units')
                                    @permission('create_tenants')
                                    @permission('create_lease_agreements')
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="tenants-bulk-upload-subtab" data-bs-toggle="tab" data-bs-target="#tenants-bulk-upload-pane" type="button" role="tab">
                                            Bulk Upload
                                        </button>
                                    </li>
                                    @endpermission
                                    @endpermission
                                    @endpermission
                                </ul>

                                <div class="tab-content" id="tenantsSubTabsContent">
                                    <!-- Tenants Listing sub-tab -->
                                    <div class="tab-pane fade show active" id="tenants-listing-pane" role="tabpanel">
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

                                    @permission('create_units')
                                    @permission('create_tenants')
                                    @permission('create_lease_agreements')
                                    <!-- Bulk Upload sub-tab (units & tenants, staged) -->
                                    <div class="tab-pane fade" id="tenants-bulk-upload-pane" role="tabpanel">
                                        @include('property.clients.include.unit-tenant-upload')
                                    </div>
                                    @endpermission
                                    @endpermission
                                    @endpermission
                                </div>
                            </div>

                            <!-- Invoicing Tab -->
                            <div class="tab-pane fade" id="invoicing" role="tabpanel" aria-labelledby="invoicing-tab">
                                <ul class="nav nav-tabs mb-3" id="invoicingSubTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="one-off-invoicing-subtab" data-bs-toggle="tab" data-bs-target="#one-off-invoicing-pane" type="button" role="tab">
                                            One Off
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="recurring-invoicing-subtab" data-bs-toggle="tab" data-bs-target="#recurring-invoicing-pane" type="button" role="tab">
                                            Recurring
                                        </button>
                                    </li>
                                </ul>

                                <div class="tab-content" id="invoicingSubTabsContent">
                                    <!-- One Off sub-tab: items from the SLA's One-Time cycle, posts directly (no staging/proration) -->
                                    <div class="tab-pane fade show active" id="one-off-invoicing-pane" role="tabpanel">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="row g-3 align-items-end">
                                                    <div class="col-md-4">
                                                        <label class="form-label">Select Property</label>
                                                        <select id="oneOffPropertySelect" class="form-select">
                                                            <option value="">-- Select Property --</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Item</label>
                                                        <select id="oneOffItemSelect" class="form-select" disabled>
                                                            <option value="">-- Select Property First --</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Due Date</label>
                                                        <input type="date" id="oneOffDueDate" class="form-control">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <div id="oneOffSpinnerInline" class="spinner-border spinner-border-sm text-primary d-none" role="status"></div>
                                                    </div>
                                                </div>

                                                <div class="row g-3 mt-1">
                                                    <div class="col-md-6">
                                                        <label class="form-label d-block">Tenants</label>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio" name="oneOffScope" id="oneOffScopeAll" value="all" checked>
                                                            <label class="form-check-label" for="oneOffScopeAll">All Tenants</label>
                                                        </div>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio" name="oneOffScope" id="oneOffScopeSelected" value="selected">
                                                            <label class="form-check-label" for="oneOffScopeSelected">Selected Tenants</label>
                                                        </div>
                                                        <div id="oneOffTenantList" class="border rounded p-2 mt-2 custom-scrollbar" style="max-height:150px; overflow-y:auto; display:none;"></div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Amount <span class="text-muted small">(optional override)</span></label>
                                                        <input type="number" step="0.01" min="0" id="oneOffAmount" class="form-control" placeholder="Leave blank to use the SLA's configured amount">
                                                        <p class="text-muted small fst-italic mb-0 mt-1" id="oneOffAmountHint"></p>
                                                    </div>
                                                </div>

                                                <div class="mt-3">
                                                    <button type="button" class="btn btn-primary d-flex align-items-center" id="oneOffCreateBtn" disabled>
                                                        <span class="spinner-border spinner-border-sm me-2 d-none" id="oneOffCreateSpinner"></span>
                                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">receipt</span>
                                                        Create Invoice(s)
                                                    </button>
                                                </div>

                                                <div id="oneOffResults" class="mt-3" style="display:none;"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Recurring sub-tab: Rent (staged+prorated) and Other Items (direct post) -->
                                    <div class="tab-pane fade" id="recurring-invoicing-pane" role="tabpanel">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <ul class="nav nav-tabs mb-0" id="recurringSubTabs" role="tablist">
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link active" id="recurring-rent-subtab" data-bs-toggle="tab" data-bs-target="#recurring-rent-pane" type="button" role="tab">
                                                        Rent
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" id="recurring-other-subtab" data-bs-toggle="tab" data-bs-target="#recurring-other-pane" type="button" role="tab">
                                                        Other Items
                                                    </button>
                                                </li>
                                            </ul>
                                            <span id="rentInvGenerateBtnWrap">
                                                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="rentInvGenerateBtn" disabled>
                                                    <span class="spinner-border spinner-border-sm me-2 d-none" id="rentInvGenerateSpinner"></span>
                                                    <span class="material-symbols-outlined me-1" style="font-size:18px;">calculate</span>
                                                    Generate to Staging
                                                </button>
                                            </span>
                                        </div>

                                    <div class="tab-content" id="recurringSubTabsContent">
                                    <!-- Rent (staged) -->
                                    <div class="tab-pane fade show active" id="recurring-rent-pane" role="tabpanel">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="row g-3 align-items-end">
                                                    <div class="col-md-5">
                                                        <label class="form-label">Select Property</label>
                                                        <select id="rentInvPropertySelect" class="form-select">
                                                            <option value="">-- Select Property --</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-5">
                                                        <label class="form-label">Due Date</label>
                                                        <input type="date" id="rentInvDueDate" class="form-control">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <div id="rentInvSpinnerInline" class="spinner-border spinner-border-sm text-primary d-none" role="status"></div>
                                                    </div>
                                                </div>

                                                <div class="row g-3 mt-1">
                                                    <div class="col-md-6">
                                                        <label class="form-label d-block">Tenants</label>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio" name="rentInvScope" id="rentInvScopeAll" value="all" checked>
                                                            <label class="form-check-label" for="rentInvScopeAll">All Tenants</label>
                                                        </div>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio" name="rentInvScope" id="rentInvScopeSelected" value="selected">
                                                            <label class="form-check-label" for="rentInvScopeSelected">Selected Tenants</label>
                                                        </div>
                                                        <div id="rentInvTenantList" class="border rounded p-2 mt-2 custom-scrollbar" style="max-height:150px; overflow-y:auto; display:none;"></div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label d-block">Proration Basis</label>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio" name="rentInvDateMode" id="rentInvModeLeaseStart" value="lease_start" checked>
                                                            <label class="form-check-label" for="rentInvModeLeaseStart">Use Lease Start Date</label>
                                                        </div>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio" name="rentInvDateMode" id="rentInvModeSelectedDate" value="selected_date">
                                                            <label class="form-check-label" for="rentInvModeSelectedDate">Selected Date</label>
                                                        </div>
                                                        <input type="date" id="rentInvSelectedDate" class="form-control mt-2" style="display:none; max-width:220px;">
                                                        <p class="text-muted small fst-italic mb-0 mt-2">Rent is prorated from the effective date to the end of the billing month, based on each unit's rent amount.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <p class="text-muted small mb-0">Staged rent invoices. Review or delete a row, then push them into real invoices.</p>
                                            <div class="d-flex flex-shrink-0 ms-2 gap-2">
                                                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="deleteSelectedRentInvBtn" disabled>
                                                    <span class="spinner-border spinner-border-sm me-2 d-none" id="deleteSelectedRentInvSpinner"></span>
                                                    <span class="material-symbols-outlined me-1" style="font-size:18px;">delete</span>
                                                    Delete Selected
                                                </button>
                                                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="pushRentInvBtn" disabled>
                                                    <span class="spinner-border spinner-border-sm me-2 d-none" id="pushRentInvSpinner"></span>
                                                    <span class="material-symbols-outlined me-1" style="font-size:18px;">publish</span>
                                                    Push to Invoices
                                                </button>
                                            </div>
                                        </div>
                                        <div class="table-responsive custom-scrollbar">
                                            <table id="rentInvStagedTable" class="display" style="width:100%;">
                                                <thead>
                                                    <tr>
                                                        <th><input type="checkbox" class="form-check-input" id="rentInvSelectAll"></th>
                                                        <th>#</th>
                                                        <th>Tenant</th>
                                                        <th>Unit</th>
                                                        <th>Base Rent</th>
                                                        <th>Prorated Amount</th>
                                                        <th>Days</th>
                                                        <th>Due Date</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Other Items (direct post, no staging — same rationale as One Off) -->
                                    <div class="tab-pane fade" id="recurring-other-pane" role="tabpanel">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="row g-3 align-items-end">
                                                    <div class="col-md-4">
                                                        <label class="form-label">Select Property</label>
                                                        <select id="recurringOtherPropertySelect" class="form-select">
                                                            <option value="">-- Select Property --</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Item</label>
                                                        <select id="recurringOtherItemSelect" class="form-select" disabled>
                                                            <option value="">-- Select Property First --</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Due Date</label>
                                                        <input type="date" id="recurringOtherDueDate" class="form-control">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <div id="recurringOtherSpinnerInline" class="spinner-border spinner-border-sm text-primary d-none" role="status"></div>
                                                    </div>
                                                </div>

                                                <div class="row g-3 mt-1">
                                                    <div class="col-md-6">
                                                        <label class="form-label d-block">Tenants</label>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio" name="recurringOtherScope" id="recurringOtherScopeAll" value="all" checked>
                                                            <label class="form-check-label" for="recurringOtherScopeAll">All Tenants</label>
                                                        </div>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio" name="recurringOtherScope" id="recurringOtherScopeSelected" value="selected">
                                                            <label class="form-check-label" for="recurringOtherScopeSelected">Selected Tenants</label>
                                                        </div>
                                                        <div id="recurringOtherTenantList" class="border rounded p-2 mt-2 custom-scrollbar" style="max-height:150px; overflow-y:auto; display:none;"></div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Amount <span class="text-muted small">(optional override)</span></label>
                                                        <input type="number" step="0.01" min="0" id="recurringOtherAmount" class="form-control" placeholder="Leave blank to use the SLA's configured amount">
                                                        <p class="text-muted small fst-italic mb-0 mt-1" id="recurringOtherAmountHint"></p>
                                                    </div>
                                                </div>

                                                <div class="mt-3">
                                                    <button type="button" class="btn btn-primary d-flex align-items-center" id="recurringOtherCreateBtn" disabled>
                                                        <span class="spinner-border spinner-border-sm me-2 d-none" id="recurringOtherCreateSpinner"></span>
                                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">receipt</span>
                                                        Create Invoice(s)
                                                    </button>
                                                </div>

                                                <div id="recurringOtherResults" class="mt-3" style="display:none;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Generated Invoices Tab -->
                            <div class="tab-pane fade" id="generated-invoices" role="tabpanel" aria-labelledby="generated-invoices-tab">
                                <div class="row mb-3 align-items-end">
                                    <div class="col-5">
                                        <label for="invListPropertySelect" class="form-label">Select Property</label>
                                        <select id="invListPropertySelect" class="form-select">
                                            <option value="">-- Select Property --</option>
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <div id="invListSpinner" class="spinner-border text-primary" role="status" style="display:none;">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="table-responsive custom-scrollbar">
                                    <table id="generatedInvoicesTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Tenant</th>
                                                <th>Unit</th>
                                                <th>Item</th>
                                                <th>Period</th>
                                                <th>Due Date</th>
                                                <th>Amount</th>
                                                <th>Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Tenant History Tab — every tenant (current or past) across every
                                 property/unit this landlord owns, in one flat list. Not scoped to a
                                 single property/unit like the Units tab's per-row expand — this is
                                 the portfolio-wide view. -->
                            <div class="tab-pane fade" id="tenant-history" role="tabpanel" aria-labelledby="tenant-history-tab">
                                <div class="table-responsive custom-scrollbar">
                                    <table id="tenantHistoryTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Tenant</th>
                                                <th>Property</th>
                                                <th>Unit</th>
                                                <th>Lease Ref</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Status</th>
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
                                                <th>Service Charges</th>
                                                <th>Status</th>
                                                <th>Actions</th>
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

                        <!-- Add Unit Modal (reuses the same property.units.store backend as the property details page) -->
                        <div class="modal fade" id="addUnitModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Add Unit</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="addUnitForm">
                                            <input type="hidden" id="addUnitPropertyId" name="property_id" value="">
                                            <input type="hidden" name="client_id" value="{{ $client->id }}">

                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label for="unit_number" class="form-label">Unit Number <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="unit_number" name="unit_number" placeholder="e.g. Shop 1" required>
                                                    <small class="text-muted">Unit code = property code + unit number.</small>
                                                    <div class="invalid-feedback"></div>
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label for="unit_type_id" class="form-label">Unit Type <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="unit_type_id" name="unit_type_id" required>
                                                        <option value="">Please select</option>
                                                        <option value="1">Normal</option>
                                                        <option value="2">Owner Occupied</option>
                                                        <option value="3">Caretaker</option>
                                                    </select>
                                                    <div class="invalid-feedback"></div>
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label for="usage_type_id" class="form-label">Usage Type <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="usage_type_id" name="usage_type_id" required>
                                                        <option value="">Please select</option>
                                                        <option value="1">Commercial</option>
                                                        <option value="3">Hybrid</option>
                                                        <option value="2">Residential</option>
                                                    </select>
                                                    <div class="invalid-feedback"></div>
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label for="unit_condition_id" class="form-label">Unit Condition <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="unit_condition_id" name="unit_condition_id" placeholder="e.g. Good, Fair, Needs repair" required>
                                                    <div class="invalid-feedback"></div>
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label for="occupancy_status_id" class="form-label">Occupation Status</label>
                                                    <select class="form-select" id="occupancy_status_id" name="occupancy_status_id">
                                                        <option value="31" selected>Vacant</option>
                                                        <option value="38">Occupied</option>
                                                    </select>
                                                    <div class="invalid-feedback"></div>
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label for="topology_id" class="form-label">Topology</label>
                                                    <select class="form-select" id="topology_id" name="topology_id">
                                                        <option value="">Please select</option>
                                                        @foreach($unitTopologies as $topology)
                                                        <option value="{{ $topology->id }}">{{ $topology->topology_name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <div class="invalid-feedback"></div>
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label for="category_id" class="form-label">Category</label>
                                                    <select class="form-select" id="category_id" name="category_id">
                                                        <option value="">Please select</option>
                                                        @foreach($unitCategories as $category)
                                                        <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <div class="invalid-feedback"></div>
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label for="vat_able" class="form-label">VAT</label>
                                                    <select class="form-select" id="vat_able" name="vat_able">
                                                        <option value="No">No</option>
                                                        <option value="Yes">Yes</option>
                                                    </select>
                                                    <div class="invalid-feedback"></div>
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label for="rent_amount" class="form-label">Rent Amount</label>
                                                    <input type="number" step="0.01" class="form-control" id="rent_amount" name="rent_amount" placeholder="0.00">
                                                    <div class="invalid-feedback"></div>
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label for="initial_water_meter_reading" class="form-label">Initial Water Meter Reading</label>
                                                    <input type="number" step="0.01" class="form-control" id="initial_water_meter_reading" name="initial_water_meter_reading" placeholder="0">
                                                    <div class="invalid-feedback"></div>
                                                </div>

                                                <div class="col-md-12 mb-3">
                                                    <label for="description" class="form-label">Description</label>
                                                    <textarea class="form-control" id="description" name="description" rows="3" placeholder="Optional notes about the unit"></textarea>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-end gap-2">
                                                <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                                                    <span class="material-symbols-outlined">close</span>
                                                    <span class="ms-1">Cancel</span>
                                                </button>
                                                <button type="submit" class="btn btn-primary d-flex align-items-center" id="saveUnitBtn">
                                                    <span class="spinner-border spinner-border-sm me-2 d-none" id="saveUnitSpinner"></span>
                                                    <span class="material-symbols-outlined" id="saveUnitIcon">check_circle</span>
                                                    <span class="ms-1" id="saveUnitText">Save</span>
                                                </button>
                                            </div>
                                        </form>
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

<!-- Upload Issue Modal — shows a staged row's push_error in full -->
<div class="modal fade" id="uploadErrorModal" tabindex="-1" aria-labelledby="uploadErrorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadErrorModalLabel">
                    <span class="material-symbols-outlined" style="font-size:20px; vertical-align:middle;">info</span>
                    Upload Issue
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="uploadErrorModalText"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Unit Bulk Upload Modal — scoped to whichever property is selected in the
     Units tab. No pre-filled data (unlike the tenant template's vacant-unit
     rows) since these are brand new units, not assignments. -->
<div class="modal fade" id="unitBulkUploadModal" tabindex="-1" aria-labelledby="unitBulkUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="unitBulkUploadModalLabel">
                    <span class="material-symbols-outlined" style="font-size:20px; vertical-align:middle;">upload_file</span>
                    Bulk Upload Units
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="unitBulkUploadForm">
                <div class="modal-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="small text-muted">Property: <strong id="unitBulkUploadPropertyName"></strong></span>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="downloadUnitTemplateBtn">
                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">download</span>
                            Download Template
                        </button>
                    </div>

                    <div class="mb-2">
                        <label for="unitBulkFile" class="form-label fw-bold">
                            <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">attach_file</span>
                            Filled-in Template <span class="text-danger">*</span>
                        </label>
                        <input type="file" class="form-control" id="unitBulkFile" name="bulk_file" accept=".xlsx,.xls" required>
                    </div>

                    <p class="text-muted small fst-italic mb-0">
                        Required: Unit Number, Usage Type. Max per file: 100.
                    </p>

                    <div id="unitBulkUploadResults" class="mt-3" style="display:none;"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="unitBulkUploadSubmitBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="unitBulkUploadSpinner"></span>
                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">upload</span>
                        Upload & Process
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Uploaded Unit Modal — staged row (temp_uploaded_units), editable until pushed -->
<div class="modal fade" id="editUnitUploadModal" tabindex="-1" aria-labelledby="editUnitUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUnitUploadModalLabel">
                    <span class="material-symbols-outlined" style="font-size:20px; vertical-align:middle;">edit</span>
                    Edit Uploaded Unit
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editUnitUploadForm">
                <input type="hidden" id="editUnitUploadId">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Unit Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="editUnitNumber">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Unit Name</label>
                            <input type="text" class="form-control form-control-sm" id="editUnitName">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Usage Type <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="editUsageType">
                                <option value="">Select</option>
                                <option value="Commercial">Commercial</option>
                                <option value="Residential">Residential</option>
                                <option value="Hybrid">Hybrid</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Unit Condition</label>
                            <input type="text" class="form-control form-control-sm" id="editUnitCondition">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Topology</label>
                            <input type="text" class="form-control form-control-sm" id="editTopology">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Category</label>
                            <input type="text" class="form-control form-control-sm" id="editCategory">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Rent Amount</label>
                            <input type="text" class="form-control form-control-sm" id="editRentAmount">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">VAT</label>
                            <select class="form-select form-select-sm" id="editVatAble">
                                <option value="">Select</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Initial Water Meter Reading</label>
                            <input type="text" class="form-control form-control-sm" id="editWaterMeterReading">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small mb-1">Description</label>
                            <textarea class="form-control form-control-sm" id="editDescription" rows="2"></textarea>
                        </div>
                    </div>
                    <div id="editUnitUploadError" class="alert alert-danger small mt-3 mb-0" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="editUnitUploadSubmitBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="editUnitUploadSpinner"></span>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Invoice Details Modal — Generated Invoices tab -->
<div class="modal fade" id="invoiceDetailsModal" tabindex="-1" aria-labelledby="invoiceDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoiceDetailsModalLabel">
                    <span class="material-symbols-outlined" style="font-size:20px; vertical-align:middle;">receipt_long</span>
                    Invoice Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="invoiceDetailsBody">
                <div class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Close</button>
                <a href="#" class="btn btn-primary d-flex align-items-center" id="invoiceDetailsDownloadBtn" target="_blank">
                    <span class="material-symbols-outlined me-1" style="font-size:18px;">download</span>
                    Download PDF
                </a>
            </div>
        </div>
    </div>
</div>

@include('property.properties.partials.add-property-modal', ['presetClient' => $client])

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

<div class="modal fade" id="leaseDetailsModal" tabindex="-1" aria-labelledby="leaseDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="leaseDetailsModalLabel">
                    <span class="material-symbols-outlined" style="font-size:20px; vertical-align:middle;">description</span>
                    Lease Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="leaseDetailsModalBody"></div>
        </div>
    </div>
</div>

<!-- Unit Lease History Modal -->
<div class="modal fade" id="unitLeaseModal" tabindex="-1" aria-labelledby="unitLeaseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="unitLeaseModalLabel">
                    <span class="material-symbols-outlined" style="font-size:20px; vertical-align:middle;">home</span>
                    <span id="unitLeaseModalUnitLabel"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="unitLeaseTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="unit-current-lease-tab" data-bs-toggle="tab" data-bs-target="#unit-current-lease" type="button" role="tab">Current Lease</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="unit-previous-leases-tab" data-bs-toggle="tab" data-bs-target="#unit-previous-leases" type="button" role="tab">Previous Leases</button>
                    </li>
                </ul>
                <div class="tab-content pt-3" id="unitLeaseTabsContent">
                    <div class="tab-pane fade show active" id="unit-current-lease" role="tabpanel"></div>
                    <div class="tab-pane fade" id="unit-previous-leases" role="tabpanel"></div>
                </div>
            </div>
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

        $(document).on('propertyCreated', function(e, res) {
            loadProperties();
        });

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
                    data: 'active',
                    render: function(data, type) {
                        if (type !== 'display') {
                            return data ? 1 : 0;
                        }
                        if (data) {
                            return '<span class="badge badge-light-success" style="font-size:13px; font-weight:700; padding:6px 10px;">ACTIVE</span>';
                        }
                        return '<span class="badge badge-light-danger" style="font-size:13px; font-weight:700; padding:6px 10px;">INACTIVE</span>';
                    }
                },
                {
                    data: 'property_name',
                    render: function(data, type, row) {
                        if (!row.active) {
                            return data;
                        }
                        return `<a href="/property/listings/${row.encrypted_id}">${data}</a>`;
                    }
                },
                {
                    data: 'zone'
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
                        if (!row.active) {
                            return '-';
                        }

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
            order: [
                [2, 'desc']
            ],
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
                            '<span class="material-symbols-outlined text-primary" style="font-size:18px;">visibility</span>' +
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

        // Units Uploads — staged rows from bulk upload (temp_uploaded_units)
        var unitUploadsTable = $('#unitUploadsTable').DataTable({
            data: [],
            columns: [{
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id) {
                        return '<input type="checkbox" class="form-check-input unit-upload-row-check" value="' + id + '">';
                    }
                },
                {
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                { data: 'unit_number', defaultContent: '-' },
                { data: 'unit_name', defaultContent: '-' },
                { data: 'usage_type', defaultContent: '-' },
                { data: 'rent_amount', defaultContent: '-' },
                { data: 'topology', defaultContent: '-' },
                { data: 'category', defaultContent: '-' },
                {
                    data: 'push_error',
                    render: function(pushError) {
                        if (!pushError) {
                            return '<span class="badge bg-secondary">Pending push</span>';
                        }
                        return '<a href="#" class="text-danger view-upload-error-btn" data-error="' + $('<div>').text(pushError).html() + '" title="View issue">' +
                            '<span class="material-symbols-outlined" style="font-size:20px; vertical-align:middle;">info</span></a>';
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id) {
                        return '<div class="d-flex flex-row gap-2">' +
                            '<a href="#" class="text-primary edit-unit-upload-btn" data-id="' + id + '" title="Edit">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">edit</span></a>' +
                            '<a href="#" class="text-primary delete-unit-upload-btn" data-id="' + id + '" title="Delete">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">delete</span></a>' +
                            '</div>';
                    }
                }
            ],
            pageLength: 10,
            responsive: true,
            language: {
                emptyTable: "No staged uploads for this property"
            }
        });

        // Show a unit's lease history in a modal — data is already embedded per
        // row (getUnits()'s `tenants` array); rich per-lease detail is fetched
        // on demand from the same endpoint/renderer the Leases tab's info
        // button uses (renderLeaseDetails(), defined below).
        function renderCurrentLeaseTab(unitRow) {
            var current = (unitRow.tenants || []).find(function(t) {
                return t.is_current;
            });
            var $pane = $('#unit-current-lease');

            if (!current) {
                $pane.html('<div class="text-muted small py-4 text-center">This unit is vacant — no current lease.</div>');
                return;
            }

            $pane.html('<div class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span></div>');
            $.ajax({
                url: '/property/leases/' + current.encrypted_id + '/details',
                method: 'GET',
                dataType: 'json',
                success: function(lease) {
                    $pane.html(renderLeaseDetails(lease));
                },
                error: function() {
                    $pane.html('<div class="text-danger small">Failed to load lease details.</div>');
                }
            });
        }

        function renderPreviousLeasesTab(unitRow) {
            var previous = (unitRow.tenants || []).filter(function(t) {
                return !t.is_current;
            });
            var $pane = $('#unit-previous-leases');

            if (previous.length === 0) {
                $pane.html('<div class="text-muted small py-4 text-center">No previous leases recorded for this unit.</div>');
                return;
            }

            var rows = '';
            previous.forEach(function(t, idx) {
                rows += '<tr class="previous-lease-row" data-encrypted-id="' + t.encrypted_id + '" data-detail-row="prev-lease-detail-' + idx + '" style="cursor:pointer;">' +
                    '<td class="small fw-semibold">' + t.tenant_name + '</td>' +
                    '<td class="small">' + t.ref_number + '</td>' +
                    '<td class="small">' + t.date_start + '</td>' +
                    '<td class="small">' + t.date_end + '</td>' +
                    '<td class="small"><span class="badge bg-secondary">' + t.status + '</span></td>' +
                    '</tr>' +
                    '<tr class="prev-lease-detail-row" id="prev-lease-detail-' + idx + '" style="display:none;"><td colspan="5" class="p-0"></td></tr>';
            });

            $pane.html(
                '<table class="table table-sm table-bordered table-hover mb-0">' +
                '<thead><tr>' +
                '<th class="bg-light text-secondary small">Tenant</th>' +
                '<th class="bg-light text-secondary small">Lease Ref</th>' +
                '<th class="bg-light text-secondary small">Start</th>' +
                '<th class="bg-light text-secondary small">End</th>' +
                '<th class="bg-light text-secondary small">Status</th>' +
                '</tr></thead><tbody>' + rows + '</tbody></table>'
            );
        }

        $(document).on('click', '#unit-previous-leases tr.previous-lease-row', function() {
            var $row = $(this);
            var $detailRow = $('#' + $row.data('detail-row'));
            var $td = $detailRow.find('td');

            if ($detailRow.is(':visible')) {
                $detailRow.hide();
                return;
            }

            if (!$td.data('loaded')) {
                $td.html('<div class="text-center py-2"><span class="spinner-border spinner-border-sm text-primary"></span></div>');
                $.ajax({
                    url: '/property/leases/' + $row.data('encrypted-id') + '/details',
                    method: 'GET',
                    dataType: 'json',
                    success: function(lease) {
                        $td.html(renderLeaseDetails(lease)).data('loaded', true);
                    },
                    error: function() {
                        $td.html('<div class="text-danger small p-2">Failed to load lease details.</div>');
                    }
                });
            }
            $detailRow.show();
        });

        $('#unitsTable').on('click', 'tr.group-parent-row', function() {
            var dtRow = unitsTable.row(this);
            var data = dtRow.data();
            if (!data) return;

            var unitLabel = (data.unit_name && data.unit_name !== '-') ? data.unit_name : data.unit_code;
            $('#unitLeaseModalUnitLabel').text(unitLabel + ' — Lease History');
            renderCurrentLeaseTab(data);
            renderPreviousLeasesTab(data);
            $('#unit-current-lease-tab').tab('show');
            $('#unitLeaseModal').modal('show');
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

        // Tenant History — every tenant across every property this landlord owns,
        // loaded once on first click of the tab (same lazy-load pattern as every
        // other tab here)
        var tenantHistoryLoaded = false;
        var tenantHistoryTable = $('#tenantHistoryTable').DataTable({
            data: [],
            columns: [
                { data: null, orderable: false, searchable: false, render: (d, t, r, m) => m.row + 1 },
                { data: 'tenant_name' },
                { data: 'property_name' },
                { data: 'unit_code' },
                { data: 'ref_number' },
                { data: 'date_start' },
                { data: 'date_end' },
                {
                    data: 'status',
                    render: function(status) {
                        var badgeClass = status === 'Active' ? 'bg-success-light text-success'
                            : status === 'Terminated' ? 'bg-danger-light text-danger'
                            : 'bg-secondary-light text-secondary';
                        return '<span class="badge ' + badgeClass + ' fw-normal">' + status + '</span>';
                    }
                }
            ],
            pageLength: 25,
            responsive: true,
            language: { emptyTable: 'No tenant history found for this landlord.' }
        });

        $('#tenant-history-tab').on('click', function() {
            if (tenantHistoryLoaded) return;
            tenantHistoryLoaded = true;

            $.ajax({
                url: '{{ route("property.clients.tenant-history", $client->encrypted_id) }}',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        tenantHistoryTable.rows.add(response.data).draw();
                    }
                },
                error: function() {
                    toastr.error('Failed to load tenant history.');
                }
            });
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

            function infoRow(label1, value1, label2, value2) {
                return `
                    <tr>
                        <td class="text-muted" style="width:20%;font-size:12px;">${label1}</td>
                        <td class="fw-semibold" style="width:30%;font-size:12px;">${value1}</td>
                        <td class="text-muted" style="width:20%;font-size:12px;">${label2}</td>
                        <td class="fw-semibold" style="width:30%;font-size:12px;">${value2}</td>
                    </tr>
                `;
            }

            function sectionLabel(title) {
                return `<div class="text-muted text-uppercase" style="font-size:10px; letter-spacing:.5px;">${title}</div>`;
            }

            var itemsRows = (sla.tenant_invoiceable_items || []).map(function(item) {
                var value = (item.amount !== null && item.amount !== undefined)
                    ? Number(item.amount).toFixed(2)
                    : ((item.percentage_of_rent !== null && item.percentage_of_rent !== undefined)
                        ? Number(item.percentage_of_rent).toFixed(2) + '% of Rent'
                        : '-');
                return `<tr><td style="font-size:12px;">${item.name}</td><td style="font-size:12px;" class="fw-semibold">${value}</td></tr>`;
            }).join('');

            var itemsTable = itemsRows
                ? `<table class="table table-sm mb-0"><tbody>${itemsRows}</tbody></table>`
                : '<span class="text-muted" style="font-size:12px;">No items added</span>';

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
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless mb-0">
                                <tbody>
                                    ${infoRow('Reference Number', sla.reference_number, 'Status', statusBadge)}
                                    ${infoRow('Start Date', sla.date_start, 'End Date', sla.date_end)}
                                    ${infoRow('Duration (Years)', sla.years, 'Auto Renewal', sla.auto_renewal)}
                                    ${infoRow('Client Name', sla.client_name, 'Client Reference', sla.client_reference)}
                                    ${infoRow('Property', sla.property_name, 'Property Reference', sla.property_reference)}
                                    ${infoRow('Zone', sla.zone_name, 'Commission', sla.commission_percentage + '%')}
                                    ${infoRow('VAT', sla.vat, 'VAT Percentage', sla.vat_per + '%')}
                                    ${infoRow('Flat Rate VAT', sla.flat_rate_vat, 'Registration Fee', sla.charge_registration_fee ? 'Yes' : 'No')}
                                    ${infoRow('Created On', sla.date_time, 'Created By', sla.created_by)}
                                </tbody>
                            </table>
                        </div>

                        <hr class="my-2">

                        <div class="row g-2">
                            <div class="col-md-6">
                                ${sectionLabel('Tenant Invoiceable Items')}
                                <div class="mt-1">${itemsTable}</div>
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
                    $('#cs-sla-coverage').text(r.propertiesWithSla + '/' + r.totalProperties);

                    if (r.propertiesMissingSla && r.propertiesMissingSla.length > 0) {
                        $('#cs-sla-card').css('--cs-accent', '#fab219').css('--cs-icon-bg', '#fef3da');
                        $('#cs-sla-gap-list').text(r.propertiesMissingSla.join(', '));
                        $('#cs-sla-gap-wrap').show();
                    } else {
                        $('#cs-sla-gap-wrap').hide();
                    }
                },
                error: function() {
                    ['cs-total-properties','cs-total-units','cs-vacant-units','cs-occupancy-rate',
                     'cs-active-leases','cs-total-tenants','cs-sla-coverage'
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
        var unitUploadsLoadedForProperty = null;
        $('#propertySelect').on('change', function() {
            var propertyId = $(this).val();
            var propertyName = $(this).find('option:selected').text();
            loadUnits(propertyId);

            // Bulk upload needs one specific property — "All Properties" can't be uploaded to
            if (propertyId) {
                unitUploadsLoadedForProperty = null;
                if ($('#units-uploads-subtab').hasClass('active')) {
                    loadUnitUploads(propertyId);
                }
                $('#openUnitBulkUploadBtn').prop('disabled', false);
                $('#pushUnitUploadsToMainBtn').prop('disabled', false);
                $('#unitBulkUploadPropertyName').text(propertyName);
            } else {
                unitUploadsTable.clear().draw();
                $('#openUnitBulkUploadBtn').prop('disabled', true);
                $('#pushUnitUploadsToMainBtn').prop('disabled', true);
                $('#unitBulkUploadPropertyName').text('');
            }

            // Add Unit needs one specific property too — a unit can't belong to "All Properties"
            $('#addUnitBtn').prop('disabled', !propertyId)
                .attr('title', propertyId ? '' : 'Select a specific property first');
        });

        // ── Add Unit Modal (client details page) ──────────────────────────────
        $('#addUnitBtn').on('click', function() {
            var propertyId = $('#propertySelect').val();
            if (!propertyId) return;

            $('#addUnitForm')[0].reset();
            $('#addUnitPropertyId').val(propertyId);
            $('#addUnitForm .is-invalid').removeClass('is-invalid');
            $('#addUnitModal').modal('show');
        });

        $('#addUnitForm').on('submit', function(e) {
            e.preventDefault();

            var $btn = $('#saveUnitBtn');
            var $spinner = $('#saveUnitSpinner');
            var $icon = $('#saveUnitIcon');
            var $text = $('#saveUnitText');

            $('#addUnitForm .is-invalid').removeClass('is-invalid');
            $spinner.removeClass('d-none');
            $icon.addClass('d-none');
            $text.text('Saving...');
            $btn.prop('disabled', true);

            $.ajax({
                url: "{{ route('property.units.store') }}",
                type: 'POST',
                data: $(this).serialize() + '&_token={{ csrf_token() }}',
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message || 'Unit added successfully');
                        $('#addUnitModal').modal('hide');
                        $('#addUnitForm')[0].reset();
                        loadUnits($('#propertySelect').val());
                    } else {
                        toastr.error(res.error || 'Failed to save unit');
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        var errors = xhr.responseJSON.errors;
                        Object.keys(errors).forEach(function(field) {
                            var $field = $('#addUnitForm [name="' + field + '"]');
                            $field.addClass('is-invalid');
                            $field.siblings('.invalid-feedback').text(errors[field][0]);
                        });
                        toastr.error('Please correct the highlighted fields');
                    } else {
                        var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Failed to save unit';
                        toastr.error(msg);
                    }
                },
                complete: function() {
                    $spinner.addClass('d-none');
                    $icon.removeClass('d-none');
                    $text.text('Save');
                    $btn.prop('disabled', false);
                }
            });
        });

        $('#addUnitModal').on('hidden.bs.modal', function() {
            $('#addUnitForm')[0].reset();
            $('#addUnitForm .is-invalid').removeClass('is-invalid');
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

        // Lazy-load the Units Uploads sub-tab the first time it's opened for the current property
        $('#units-uploads-subtab').on('click', function() {
            var propertyId = $('#propertySelect').val();
            if (propertyId && unitUploadsLoadedForProperty !== propertyId) {
                loadUnitUploads(propertyId);
            }
        });

        function loadUnitUploads(propertyId) {
            unitUploadsLoadedForProperty = propertyId;
            $.ajax({
                url: "{{ route('property.units.uploads.data') }}",
                type: 'GET',
                data: { property_id: propertyId },
                dataType: 'json',
                success: function(response) {
                    unitUploadsTable.clear();
                    if (response.data && response.data.length) {
                        unitUploadsTable.rows.add(response.data);
                    }
                    unitUploadsTable.draw();
                    $('#unitUploadsSelectAll').prop('checked', false);
                    $('#deleteSelectedUnitUploadsBtn').prop('disabled', true);
                },
                error: function() {
                    toastr.error('Failed to load uploaded units.');
                }
            });
        }

        // Select all / individual row checkboxes for the Units Uploads table
        $('#unitUploadsSelectAll').on('change', function() {
            var checked = $(this).is(':checked');
            $('#unitUploadsTable .unit-upload-row-check').prop('checked', checked);
            $('#deleteSelectedUnitUploadsBtn').prop('disabled', $('#unitUploadsTable .unit-upload-row-check:checked').length === 0);
        });

        $('#unitUploadsTable').on('change', '.unit-upload-row-check', function() {
            var totalBoxes = $('#unitUploadsTable .unit-upload-row-check').length;
            var checkedBoxes = $('#unitUploadsTable .unit-upload-row-check:checked').length;
            $('#unitUploadsSelectAll').prop('checked', totalBoxes > 0 && totalBoxes === checkedBoxes);
            $('#deleteSelectedUnitUploadsBtn').prop('disabled', checkedBoxes === 0);
        });

        // Bulk delete selected staged rows
        $('#deleteSelectedUnitUploadsBtn').on('click', function() {
            var ids = $('#unitUploadsTable .unit-upload-row-check:checked').map(function() {
                return $(this).val();
            }).get();
            if (!ids.length) return;

            Swal.fire({
                title: 'Delete ' + ids.length + ' selected row(s)?',
                text: 'This only removes the staged upload rows — nothing else is affected.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3598db',
                cancelButtonColor: '#8a94a3',
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (!result.isConfirmed) return;

                var btn = $('#deleteSelectedUnitUploadsBtn');
                var spinner = $('#deleteSelectedUnitUploadsSpinner');
                btn.prop('disabled', true);
                spinner.removeClass('d-none');

                $.ajax({
                    url: "{{ route('property.units.uploads.bulk-delete') }}",
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}', ids: ids },
                    dataType: 'json',
                    success: function(response) {
                        toastr.success(response.message);
                        loadUnitUploads($('#propertySelect').val());
                    },
                    error: function(xhr) {
                        var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to delete selected rows.';
                        toastr.error(message);
                    },
                    complete: function() {
                        spinner.addClass('d-none');
                    }
                });
            });
        });

        // Bulk Upload Units — download template scoped to the selected property
        $('#downloadUnitTemplateBtn').on('click', function() {
            var propertyId = $('#propertySelect').val();
            if (!propertyId) return;
            window.location.href = "{{ route('property.units.bulk-upload.template') }}?property_id=" + propertyId;
        });

        // Bulk Upload Units — submit via AJAX, no page reload. Rows land in the
        // staging table (temp_uploaded_units) — nothing becomes a real unit
        // until Complete Upload runs.
        $('#unitBulkUploadForm').on('submit', function(e) {
            e.preventDefault();

            var propertyId = $('#propertySelect').val();
            var fileInput = $('#unitBulkFile')[0];
            if (!propertyId || !fileInput.files.length) {
                toastr.error('Please select a property and choose a file to upload.');
                return;
            }

            var formData = new FormData();
            formData.append('property_id', propertyId);
            formData.append('bulk_file', fileInput.files[0]);
            formData.append('_token', '{{ csrf_token() }}');

            var submitBtn = $('#unitBulkUploadSubmitBtn');
            var spinner = $('#unitBulkUploadSpinner');
            var resultsBox = $('#unitBulkUploadResults');

            submitBtn.prop('disabled', true);
            spinner.removeClass('d-none');
            resultsBox.hide().empty();

            $.ajax({
                url: "{{ route('property.units.bulk-upload.import') }}",
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(response) {
                    toastr.success(response.message);
                    loadUnitUploads(propertyId);
                    $('#unitsSubTabs button[data-bs-target="#units-uploads-pane"]').tab('show');
                    $('#unitBulkUploadForm')[0].reset();
                    $('#unitBulkUploadModal').modal('hide');
                },
                error: function(xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to process upload.';
                    resultsBox.html('<div class="alert alert-danger mb-0">' + $('<div>').text(message).html() + '</div>').show();
                    toastr.error(message);
                },
                complete: function() {
                    submitBtn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });

        // Reset bulk upload modal on close
        $('#unitBulkUploadModal').on('hidden.bs.modal', function() {
            $('#unitBulkUploadForm')[0].reset();
            $('#unitBulkUploadResults').hide().empty();
        });

        // Complete Upload — turns every staged row for this property into a real unit
        $('#pushUnitUploadsToMainBtn').on('click', function() {
            var propertyId = $('#propertySelect').val();
            if (!propertyId) return;

            var btn = $(this);
            var spinner = $('#pushUnitUploadsSpinner');

            btn.prop('disabled', true);
            spinner.removeClass('d-none');

            $.ajax({
                url: "{{ route('property.units.uploads.push') }}",
                type: 'POST',
                data: { property_id: propertyId, _token: '{{ csrf_token() }}' },
                dataType: 'json',
                success: function(response) {
                    if (response.failed > 0) {
                        toastr.warning(response.message);
                    } else {
                        toastr.success(response.message);
                    }
                    loadUnitUploads(propertyId);
                    loadUnits(propertyId);
                },
                error: function(xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to push uploads.';
                    toastr.error(message);
                },
                complete: function() {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });

        // Show a staged row's push_error in a modal (shared by the staged uploads tables)
        $('#unitUploadsTable').on('click', '.view-upload-error-btn', function(e) {
            e.preventDefault();
            $('#uploadErrorModalText').text($(this).data('error'));
            new bootstrap.Modal(document.getElementById('uploadErrorModal')).show();
        });

        // Edit a staged row
        $('#unitUploadsTable').on('click', '.edit-unit-upload-btn', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var row = unitUploadsTable.row($(this).closest('tr')).data();
            if (!row) return;

            $('#editUnitUploadId').val(id);
            $('#editUnitNumber').val(row.unit_number);
            $('#editUnitName').val(row.unit_name);
            $('#editUsageType').val(row.usage_type || '');
            $('#editUnitCondition').val(row.unit_condition);
            $('#editTopology').val(row.topology);
            $('#editCategory').val(row.category);
            $('#editRentAmount').val(row.rent_amount);
            $('#editVatAble').val(row.vat_able || '');
            $('#editWaterMeterReading').val(row.initial_water_meter_reading);
            $('#editDescription').val(row.description);
            $('#editUnitUploadError').hide().empty();

            new bootstrap.Modal(document.getElementById('editUnitUploadModal')).show();
        });

        $('#editUnitUploadForm').on('submit', function(e) {
            e.preventDefault();

            var id = $('#editUnitUploadId').val();
            var submitBtn = $('#editUnitUploadSubmitBtn');
            var spinner = $('#editUnitUploadSpinner');
            var errorBox = $('#editUnitUploadError');

            submitBtn.prop('disabled', true);
            spinner.removeClass('d-none');
            errorBox.hide().empty();

            $.ajax({
                url: "{{ url('property/units/uploads') }}/" + id,
                type: 'PUT',
                data: {
                    _token: '{{ csrf_token() }}',
                    unit_number: $('#editUnitNumber').val(),
                    unit_name: $('#editUnitName').val(),
                    usage_type: $('#editUsageType').val(),
                    unit_condition: $('#editUnitCondition').val(),
                    topology: $('#editTopology').val(),
                    category: $('#editCategory').val(),
                    rent_amount: $('#editRentAmount').val(),
                    vat_able: $('#editVatAble').val(),
                    initial_water_meter_reading: $('#editWaterMeterReading').val(),
                    description: $('#editDescription').val()
                },
                dataType: 'json',
                success: function(response) {
                    toastr.success(response.message);
                    $('#editUnitUploadModal').modal('hide');
                    loadUnitUploads($('#propertySelect').val());
                },
                error: function(xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to update row.';
                    errorBox.text(message).show();
                },
                complete: function() {
                    submitBtn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });

        // Delete a staged row
        $('#unitUploadsTable').on('click', '.delete-unit-upload-btn', function(e) {
            e.preventDefault();
            var id = $(this).data('id');

            Swal.fire({
                title: 'Delete this staged unit?',
                text: 'This only removes the staged upload row — nothing else is affected.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3598db',
                cancelButtonColor: '#8a94a3',
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (!result.isConfirmed) return;

                $.ajax({
                    url: "{{ url('property/units/uploads') }}/" + id,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    dataType: 'json',
                    success: function(response) {
                        toastr.success(response.message);
                        loadUnitUploads($('#propertySelect').val());
                    },
                    error: function(xhr) {
                        var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to delete row.';
                        toastr.error(message);
                    }
                });
            });
        });

        // ===================== Invoicing Tab =====================

        // Generic property-dropdown loader, reused by Rent/Utility Invoicing and Generated Invoices
        function loadPropertiesIntoSelect(selectId) {
            $.ajax({
                url: "{{ route('property.clients.properties.dropdown', $client->encrypted_id) }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    var $select = $('#' + selectId);
                    $select.empty().append('<option value="">-- Select Property --</option>');
                    $.each(response, function(index, property) {
                        $select.append('<option value="' + property.id + '">' + property.text + '</option>');
                    });
                },
                error: function() {
                    toastr.error('Failed to load properties. Please try again.');
                }
            });
        }

        // Renders a checkbox list of a property's tenants into a container
        function loadTenantCheckboxList(propertyId, containerId) {
            var $container = $('#' + containerId);
            $container.html('<div class="text-muted small">Loading tenants...</div>');
            $.ajax({
                url: "{{ route('ajax.property.tenants.get') }}",
                type: 'GET',
                data: { property_id: propertyId },
                dataType: 'json',
                success: function(response) {
                    var rows = response.data || [];
                    if (!rows.length) {
                        $container.html('<div class="text-muted small">No tenants found for this property.</div>');
                        return;
                    }
                    var html = '';
                    rows.forEach(function(t) {
                        html += '<div class="form-check">' +
                            '<input class="form-check-input tenant-checkbox" type="checkbox" value="' + t.id + '" id="' + containerId + '-t' + t.id + '">' +
                            '<label class="form-check-label small" for="' + containerId + '-t' + t.id + '">' + t.names + '</label></div>';
                    });
                    $container.html(html);
                },
                error: function() {
                    $container.html('<div class="text-danger small">Failed to load tenants.</div>');
                }
            });
        }

        var invoicingPropertiesLoaded = false;
        $('#invoicing-tab').on('click', function() {
            if (!invoicingPropertiesLoaded) {
                loadPropertiesIntoSelect('rentInvPropertySelect');
                loadPropertiesIntoSelect('oneOffPropertySelect');
                loadPropertiesIntoSelect('recurringOtherPropertySelect');
                invoicingPropertiesLoaded = true;
            }
        });

        // "Generate to Staging" lives in the Recurring sub-tab row (next to its
        // own Rent/Other Items nav) — only show it while the nested Rent tab is
        // active. Note: this app's global CSS forces `.btn { display: inline-flex
        // !important; }`, which beats jQuery's show()/hide()/toggle() on the
        // button itself, so the WRAPPER span is what gets toggled instead.
        function syncRentInvGenerateBtnVisibility() {
            $('#rentInvGenerateBtnWrap').toggle($('#recurring-rent-subtab').hasClass('active'));
        }
        $('#invoicingSubTabs button[data-bs-toggle="tab"], #recurringSubTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', syncRentInvGenerateBtnVisibility);
        syncRentInvGenerateBtnVisibility();

        // ---------- Rent Invoicing ----------

        var rentInvStagedTable = $('#rentInvStagedTable').DataTable({
            data: [],
            columns: [{
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id) {
                        return '<input type="checkbox" class="form-check-input rent-inv-row-check" value="' + id + '">';
                    }
                },
                {
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                { data: 'tenant_name', defaultContent: '-' },
                { data: 'unit_code', defaultContent: '-' },
                {
                    data: 'base_rent',
                    render: function(v) {
                        return v !== null ? Number(v).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) : '-';
                    }
                },
                {
                    data: 'prorated_amount',
                    render: function(v) {
                        return v !== null ? Number(v).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) : '-';
                    }
                },
                {
                    data: 'proration_days',
                    render: function(v, type, row) {
                        return v !== null ? (v + ' / ' + row.month_days) : 'Full month';
                    }
                },
                {
                    data: 'date_due',
                    render: function(v) {
                        return v ? v.substring(0, 10) : '-';
                    }
                },
                {
                    data: 'push_error',
                    render: function(pushError) {
                        if (!pushError) {
                            return '<span class="badge bg-secondary">Pending push</span>';
                        }
                        return '<a href="#" class="text-danger view-upload-error-btn" data-error="' + $('<div>').text(pushError).html() + '" title="View issue">' +
                            '<span class="material-symbols-outlined" style="font-size:20px; vertical-align:middle;">info</span></a>';
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id) {
                        return '<a href="#" class="text-primary delete-rent-inv-btn" data-id="' + id + '" title="Delete">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">delete</span></a>';
                    }
                }
            ],
            pageLength: 10,
            responsive: true,
            language: { emptyTable: "No staged rent invoices for this selection" }
        });

        function loadRentInvStaged(propertyId, billingMonth) {
            $.ajax({
                url: "{{ route('property.rent-invoicing.staged') }}",
                type: 'GET',
                data: { property_id: propertyId, billing_month: billingMonth },
                dataType: 'json',
                success: function(response) {
                    rentInvStagedTable.clear();
                    if (response.data && response.data.length) {
                        rentInvStagedTable.rows.add(response.data);
                    }
                    rentInvStagedTable.draw();
                    $('#rentInvSelectAll').prop('checked', false);
                    $('#deleteSelectedRentInvBtn').prop('disabled', true);
                },
                error: function() {
                    toastr.error('Failed to load staged rent invoices.');
                }
            });
        }

        // No separate Billing Month field — it's derived from the Due Date's
        // own month (they're always the same billing period in practice).
        function rentInvBillingMonth() {
            var dueDate = $('#rentInvDueDate').val();
            return dueDate ? dueDate.substring(0, 7) : '';
        }

        function rentInvReady() {
            var propertyId = $('#rentInvPropertySelect').val();
            var ready = !!propertyId && !!$('#rentInvDueDate').val();
            $('#rentInvGenerateBtn').prop('disabled', !ready);
            $('#pushRentInvBtn').prop('disabled', !propertyId);
        }

        $('#rentInvPropertySelect, #rentInvDueDate').on('change', function() {
            rentInvReady();
            var propertyId = $('#rentInvPropertySelect').val();
            if (propertyId) {
                loadRentInvStaged(propertyId, rentInvBillingMonth());
                if ($('#rentInvScopeSelected').is(':checked')) {
                    loadTenantCheckboxList(propertyId, 'rentInvTenantList');
                }
            } else {
                rentInvStagedTable.clear().draw();
            }
        });

        $('input[name="rentInvScope"]').on('change', function() {
            var isSelected = $('#rentInvScopeSelected').is(':checked');
            $('#rentInvTenantList').toggle(isSelected);
            if (isSelected && $('#rentInvPropertySelect').val()) {
                loadTenantCheckboxList($('#rentInvPropertySelect').val(), 'rentInvTenantList');
            }
        });

        $('input[name="rentInvDateMode"]').on('change', function() {
            $('#rentInvSelectedDate').toggle($('#rentInvModeSelectedDate').is(':checked'));
        });

        $('#rentInvGenerateBtn').on('click', function() {
            var propertyId = $('#rentInvPropertySelect').val();
            var scope = $('input[name="rentInvScope"]:checked').val();
            var dateMode = $('input[name="rentInvDateMode"]:checked').val();

            var payload = {
                _token: '{{ csrf_token() }}',
                property_id: propertyId,
                billing_month: rentInvBillingMonth(),
                due_date: $('#rentInvDueDate').val(),
                scope: scope,
                date_mode: dateMode,
            };

            if (scope === 'selected') {
                var ids = $('#rentInvTenantList .tenant-checkbox:checked').map(function() { return $(this).val(); }).get();
                if (!ids.length) {
                    toastr.error('Select at least one tenant.');
                    return;
                }
                payload.tenant_ids = ids;
            }

            if (dateMode === 'selected_date') {
                if (!$('#rentInvSelectedDate').val()) {
                    toastr.error('Choose a date.');
                    return;
                }
                payload.selected_date = $('#rentInvSelectedDate').val();
            }

            var btn = $('#rentInvGenerateBtn');
            var spinner = $('#rentInvGenerateSpinner');
            btn.prop('disabled', true);
            spinner.removeClass('d-none');

            $.ajax({
                url: "{{ route('property.rent-invoicing.generate') }}",
                type: 'POST',
                data: payload,
                dataType: 'json',
                success: function(response) {
                    toastr.success(response.message);
                    loadRentInvStaged(propertyId, rentInvBillingMonth());
                },
                error: function(xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to generate invoices.';
                    toastr.error(message);
                },
                complete: function() {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });

        $('#pushRentInvBtn').on('click', function() {
            var propertyId = $('#rentInvPropertySelect').val();
            if (!propertyId) return;

            var btn = $(this);
            var spinner = $('#pushRentInvSpinner');
            btn.prop('disabled', true);
            spinner.removeClass('d-none');

            $.ajax({
                url: "{{ route('property.rent-invoicing.push') }}",
                type: 'POST',
                data: { property_id: propertyId, billing_month: rentInvBillingMonth(), _token: '{{ csrf_token() }}' },
                dataType: 'json',
                success: function(response) {
                    if (response.failed > 0) {
                        toastr.warning(response.message);
                    } else {
                        toastr.success(response.message);
                    }
                    loadRentInvStaged(propertyId, rentInvBillingMonth());
                },
                error: function(xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to push invoices.';
                    toastr.error(message);
                },
                complete: function() {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });

        $('#rentInvSelectAll').on('change', function() {
            var checked = $(this).is(':checked');
            $('#rentInvStagedTable .rent-inv-row-check').prop('checked', checked);
            $('#deleteSelectedRentInvBtn').prop('disabled', $('#rentInvStagedTable .rent-inv-row-check:checked').length === 0);
        });

        $('#rentInvStagedTable').on('change', '.rent-inv-row-check', function() {
            var total = $('#rentInvStagedTable .rent-inv-row-check').length;
            var checked = $('#rentInvStagedTable .rent-inv-row-check:checked').length;
            $('#rentInvSelectAll').prop('checked', total > 0 && total === checked);
            $('#deleteSelectedRentInvBtn').prop('disabled', checked === 0);
        });

        $('#rentInvStagedTable').on('click', '.view-upload-error-btn', function(e) {
            e.preventDefault();
            $('#uploadErrorModalText').text($(this).data('error'));
            new bootstrap.Modal(document.getElementById('uploadErrorModal')).show();
        });

        $('#rentInvStagedTable').on('click', '.delete-rent-inv-btn', function(e) {
            e.preventDefault();
            var id = $(this).data('id');

            Swal.fire({
                title: 'Delete this staged invoice?',
                text: 'This only removes the staged row — nothing else is affected.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3598db',
                cancelButtonColor: '#8a94a3',
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (!result.isConfirmed) return;

                $.ajax({
                    url: "{{ url('property/rent-invoicing/staged') }}/" + id,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    dataType: 'json',
                    success: function(response) {
                        toastr.success(response.message);
                        loadRentInvStaged($('#rentInvPropertySelect').val(), rentInvBillingMonth());
                    },
                    error: function(xhr) {
                        var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to delete row.';
                        toastr.error(message);
                    }
                });
            });
        });

        $('#deleteSelectedRentInvBtn').on('click', function() {
            var ids = $('#rentInvStagedTable .rent-inv-row-check:checked').map(function() { return $(this).val(); }).get();
            if (!ids.length) return;

            Swal.fire({
                title: 'Delete ' + ids.length + ' selected row(s)?',
                text: 'This only removes the staged rows — nothing else is affected.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3598db',
                cancelButtonColor: '#8a94a3',
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (!result.isConfirmed) return;

                var btn = $('#deleteSelectedRentInvBtn');
                var spinner = $('#deleteSelectedRentInvSpinner');
                btn.prop('disabled', true);
                spinner.removeClass('d-none');

                $.ajax({
                    url: "{{ route('property.rent-invoicing.staged.bulk-delete') }}",
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}', ids: ids },
                    dataType: 'json',
                    success: function(response) {
                        toastr.success(response.message);
                        loadRentInvStaged($('#rentInvPropertySelect').val(), rentInvBillingMonth());
                    },
                    error: function(xhr) {
                        var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to delete selected rows.';
                        toastr.error(message);
                    },
                    complete: function() {
                        spinner.addClass('d-none');
                    }
                });
            });
        });

        // ---------- One Off & Recurring "Other Items" ----------
        // Both tabs share the same shape: items come from the property's SLA
        // (cb_sla_invoiceable_items), split server-side by billing cycle —
        // One Off gets the One-Time cycle, Other Items gets everything else.
        // Neither has a staging step: there's no proration math to review,
        // so both post straight to the real tables (same rationale the old
        // Utility Invoicing tab used for its own direct-post paths).

        var oneOffItemsById = {};
        var recurringOtherItemsById = {};

        function loadItemSelect(url, propertyId, selectId, itemsMapSetter) {
            var $select = $('#' + selectId);
            $select.empty().append('<option value="">Loading items...</option>').prop('disabled', true);

            $.ajax({
                url: url,
                type: 'GET',
                data: { property_id: propertyId },
                dataType: 'json',
                success: function(response) {
                    var items = response.items || [];
                    var map = {};
                    items.forEach(function(item) { map[item.item_id] = item; });
                    itemsMapSetter(map);

                    if (!response.has_sla) {
                        $select.html('<option value="">This property has no service agreement</option>');
                        return;
                    }
                    if (!items.length) {
                        $select.html('<option value="">No items configured for this cycle</option>');
                        return;
                    }

                    $select.empty().append('<option value="">-- Select Item --</option>').prop('disabled', false);
                    items.forEach(function(item) {
                        var label = $('<div>').text(item.item_name).html() + (item.billing_code ? ' (' + item.billing_code + ')' : '');
                        $select.append('<option value="' + item.item_id + '">' + label + '</option>');
                    });
                },
                error: function() {
                    $select.html('<option value="">Failed to load items</option>');
                }
            });
        }

        function itemAmountHint(item) {
            if (!item) return '';
            if (item.amount !== null && item.amount !== undefined) {
                return 'SLA default: ' + Number(item.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
            if (item.percentage_of_rent !== null && item.percentage_of_rent !== undefined) {
                return 'SLA default: ' + Number(item.percentage_of_rent) + "% of the unit's rent amount";
            }
            return 'No amount configured on the SLA — enter one below.';
        }

        // ---------- One Off ----------

        function oneOffReady() {
            var ready = !!$('#oneOffPropertySelect').val() && !!$('#oneOffItemSelect').val() && !!$('#oneOffDueDate').val();
            $('#oneOffCreateBtn').prop('disabled', !ready);
        }

        $('#oneOffPropertySelect').on('change', function() {
            var propertyId = $(this).val();
            $('#oneOffAmountHint').text('');
            oneOffReady();

            if (!propertyId) {
                $('#oneOffItemSelect').html('<option value="">-- Select Property First --</option>').prop('disabled', true);
                return;
            }

            loadItemSelect("{{ route('invoicing.one-off-items') }}", propertyId, 'oneOffItemSelect', function(map) { oneOffItemsById = map; });

            if ($('#oneOffScopeSelected').is(':checked')) {
                loadTenantCheckboxList(propertyId, 'oneOffTenantList');
            }
        });

        $('#oneOffItemSelect').on('change', function() {
            $('#oneOffAmountHint').text(itemAmountHint(oneOffItemsById[$(this).val()]));
            oneOffReady();
        });

        $('#oneOffDueDate').on('change', oneOffReady);

        $('input[name="oneOffScope"]').on('change', function() {
            var isSelected = $('#oneOffScopeSelected').is(':checked');
            $('#oneOffTenantList').toggle(isSelected);
            if (isSelected && $('#oneOffPropertySelect').val()) {
                loadTenantCheckboxList($('#oneOffPropertySelect').val(), 'oneOffTenantList');
            }
        });

        $('#oneOffCreateBtn').on('click', function() {
            var scope = $('input[name="oneOffScope"]:checked').val();
            var payload = {
                _token: '{{ csrf_token() }}',
                property_id: $('#oneOffPropertySelect').val(),
                item_id: $('#oneOffItemSelect').val(),
                due_date: $('#oneOffDueDate').val(),
                scope: scope,
            };

            if (scope === 'selected') {
                var ids = $('#oneOffTenantList .tenant-checkbox:checked').map(function() { return $(this).val(); }).get();
                if (!ids.length) {
                    toastr.error('Select at least one tenant.');
                    return;
                }
                payload.tenant_ids = ids;
            }

            var amountOverride = $('#oneOffAmount').val();
            if (amountOverride !== '') {
                payload.amount_override = amountOverride;
            }

            var btn = $('#oneOffCreateBtn');
            var spinner = $('#oneOffCreateSpinner');
            var resultsBox = $('#oneOffResults');
            btn.prop('disabled', true);
            spinner.removeClass('d-none');
            resultsBox.hide().empty();

            $.ajax({
                url: "{{ route('invoicing.one-off.create') }}",
                type: 'POST',
                data: payload,
                dataType: 'json',
                success: function(response) {
                    var html = '<div class="alert ' + (response.failed > 0 ? 'alert-warning' : 'alert-success') + ' mb-2">' + response.message + '</div>';
                    if (response.errors && response.errors.length) {
                        html += '<ul class="mb-0 small text-danger">';
                        response.errors.forEach(function(err) {
                            html += '<li>' + $('<div>').text(err).html() + '</li>';
                        });
                        html += '</ul>';
                    }
                    resultsBox.html(html).show();
                    if (response.created > 0) {
                        toastr.success(response.created + ' invoice(s) created.');
                    }
                },
                error: function(xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to create invoices.';
                    resultsBox.html('<div class="alert alert-danger mb-0">' + $('<div>').text(message).html() + '</div>').show();
                    toastr.error(message);
                },
                complete: function() {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });

        // ---------- Recurring: Other Items ----------

        // No separate Billing Month field — it's derived from the Due Date's
        // own month (they're always the same billing period in practice).
        function recurringOtherBillingMonth() {
            var dueDate = $('#recurringOtherDueDate').val();
            return dueDate ? dueDate.substring(0, 7) : '';
        }

        function recurringOtherReady() {
            var ready = !!$('#recurringOtherPropertySelect').val() && !!$('#recurringOtherItemSelect').val() &&
                !!$('#recurringOtherDueDate').val();
            $('#recurringOtherCreateBtn').prop('disabled', !ready);
        }

        $('#recurringOtherPropertySelect').on('change', function() {
            var propertyId = $(this).val();
            $('#recurringOtherAmountHint').text('');
            recurringOtherReady();

            if (!propertyId) {
                $('#recurringOtherItemSelect').html('<option value="">-- Select Property First --</option>').prop('disabled', true);
                return;
            }

            loadItemSelect("{{ route('invoicing.recurring-items') }}", propertyId, 'recurringOtherItemSelect', function(map) { recurringOtherItemsById = map; });

            if ($('#recurringOtherScopeSelected').is(':checked')) {
                loadTenantCheckboxList(propertyId, 'recurringOtherTenantList');
            }
        });

        $('#recurringOtherItemSelect').on('change', function() {
            $('#recurringOtherAmountHint').text(itemAmountHint(recurringOtherItemsById[$(this).val()]));
            recurringOtherReady();
        });

        $('#recurringOtherDueDate').on('change', recurringOtherReady);

        $('input[name="recurringOtherScope"]').on('change', function() {
            var isSelected = $('#recurringOtherScopeSelected').is(':checked');
            $('#recurringOtherTenantList').toggle(isSelected);
            if (isSelected && $('#recurringOtherPropertySelect').val()) {
                loadTenantCheckboxList($('#recurringOtherPropertySelect').val(), 'recurringOtherTenantList');
            }
        });

        $('#recurringOtherCreateBtn').on('click', function() {
            var scope = $('input[name="recurringOtherScope"]:checked').val();
            var payload = {
                _token: '{{ csrf_token() }}',
                property_id: $('#recurringOtherPropertySelect').val(),
                item_id: $('#recurringOtherItemSelect').val(),
                billing_month: recurringOtherBillingMonth(),
                due_date: $('#recurringOtherDueDate').val(),
                scope: scope,
            };

            if (scope === 'selected') {
                var ids = $('#recurringOtherTenantList .tenant-checkbox:checked').map(function() { return $(this).val(); }).get();
                if (!ids.length) {
                    toastr.error('Select at least one tenant.');
                    return;
                }
                payload.tenant_ids = ids;
            }

            var amountOverride = $('#recurringOtherAmount').val();
            if (amountOverride !== '') {
                payload.amount_override = amountOverride;
            }

            var btn = $('#recurringOtherCreateBtn');
            var spinner = $('#recurringOtherCreateSpinner');
            var resultsBox = $('#recurringOtherResults');
            btn.prop('disabled', true);
            spinner.removeClass('d-none');
            resultsBox.hide().empty();

            $.ajax({
                url: "{{ route('invoicing.recurring-item.create') }}",
                type: 'POST',
                data: payload,
                dataType: 'json',
                success: function(response) {
                    var html = '<div class="alert ' + (response.failed > 0 ? 'alert-warning' : 'alert-success') + ' mb-2">' + response.message + '</div>';
                    if (response.errors && response.errors.length) {
                        html += '<ul class="mb-0 small text-danger">';
                        response.errors.forEach(function(err) {
                            html += '<li>' + $('<div>').text(err).html() + '</li>';
                        });
                        html += '</ul>';
                    }
                    resultsBox.html(html).show();
                    if (response.created > 0) {
                        toastr.success(response.created + ' invoice(s) created.');
                    }
                },
                error: function(xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to create invoices.';
                    resultsBox.html('<div class="alert alert-danger mb-0">' + $('<div>').text(message).html() + '</div>').show();
                    toastr.error(message);
                },
                complete: function() {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });

        // ---------- Generated Invoices ----------

        var generatedInvoicesTable = $('#generatedInvoicesTable').DataTable({
            data: [],
            columns: [{
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                { data: 'tenant_name', defaultContent: '-' },
                { data: 'unit_code', defaultContent: '-' },
                { data: 'return_type_id', defaultContent: '-' },
                { data: 'returns_period', defaultContent: '-' },
                { data: 'date_due', defaultContent: '-' },
                { data: 'total_amount', defaultContent: '-' },
                { data: 'current_balance', defaultContent: '-' }
            ],
            pageLength: 10,
            responsive: true,
            language: { emptyTable: "Please select a property to view invoices" }
        });

        var invListLoaded = false;
        $('#generated-invoices-tab').on('click', function() {
            if (!invListLoaded) {
                loadPropertiesIntoSelect('invListPropertySelect');
                invListLoaded = true;
            }
        });

        $('#invListPropertySelect').on('change', function() {
            var propertyId = $(this).val();
            if (!propertyId) {
                generatedInvoicesTable.clear().draw();
                return;
            }
            $('#invListSpinner').show();
            $.ajax({
                url: "{{ route('property.client-invoices.data') }}",
                type: 'GET',
                data: { property_id: propertyId },
                dataType: 'json',
                success: function(response) {
                    generatedInvoicesTable.clear();
                    if (response.data && response.data.length) {
                        generatedInvoicesTable.rows.add(response.data);
                    }
                    generatedInvoicesTable.draw();
                },
                error: function() {
                    toastr.error('Failed to load invoices.');
                },
                complete: function() {
                    $('#invListSpinner').hide();
                }
            });
        });

        $('#generatedInvoicesTable').on('click', '.view-invoice-btn', function(e) {
            e.preventDefault();
            var encId = $(this).data('id');
            var $body = $('#invoiceDetailsBody');
            $body.html('<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>');
            $('#invoiceDetailsDownloadBtn').attr('href', "{{ url('property/client-invoices') }}/" + encId + "/pdf");
            new bootstrap.Modal(document.getElementById('invoiceDetailsModal')).show();

            $.ajax({
                url: "{{ url('property/client-invoices') }}/" + encId + "/details",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    var inv = response.invoice;
                    var html = '<table class="table table-sm table-borderless mb-0">' +
                        '<tr><td class="text-muted small">Reference</td><td class="fw-semibold">' + inv.reference_number + '</td></tr>' +
                        '<tr><td class="text-muted small">Tenant</td><td>' + inv.tenant_name + '</td></tr>' +
                        '<tr><td class="text-muted small">Property</td><td>' + inv.property_name + '</td></tr>' +
                        '<tr><td class="text-muted small">Unit</td><td>' + inv.unit_code + '</td></tr>' +
                        '<tr><td class="text-muted small">Item</td><td>' + inv.return_type_id + '</td></tr>' +
                        '<tr><td class="text-muted small">Period</td><td>' + inv.returns_period + '</td></tr>' +
                        '<tr><td class="text-muted small">Due Date</td><td>' + inv.date_due + '</td></tr>' +
                        '<tr><td class="text-muted small">Amount</td><td>' + inv.total_amount + '</td></tr>' +
                        '<tr><td class="text-muted small">VAT</td><td>' + inv.vat_ammount + '</td></tr>' +
                        '<tr><td class="text-muted small">Commission</td><td>' + inv.comm_ammount + '</td></tr>' +
                        '<tr><td class="text-muted small">Payable</td><td>' + inv.payable_amount + '</td></tr>' +
                        '<tr><td class="text-muted small">Balance</td><td class="fw-semibold">' + inv.current_balance + '</td></tr>' +
                        '<tr><td class="text-muted small">Status</td><td><span class="badge bg-secondary">' + inv.status_name + '</span></td></tr>' +
                        '</table>';
                    $body.html(html);
                },
                error: function() {
                    $body.html('<div class="alert alert-danger mb-0">Failed to load invoice details.</div>');
                }
            });
        });

        // ===================== End Invoicing Tab =====================

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
                },
                {
                    data: 'service_charges_amount',
                    render: function(data) {
                        return data !== null && data !== undefined ? Number(data).toFixed(2) : '-';
                    }
                },
                {
                    data: 'status',
                    render: function(status, type, row) {
                        var badgeClass = {
                            1: 'bg-warning',
                            5: 'bg-success',
                            2: 'bg-info',
                            28: 'bg-danger',
                            29: 'bg-secondary'
                        } [row.current_status_id] || 'bg-secondary';
                        return '<span class="badge ' + badgeClass + '">' + (status || 'Unknown') + '</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return `<a href="#" class="view-lease-details-btn" data-id="${row.encrypted_id}" title="View Lease Details">
                            <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">info</span>
                        </a>`;
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

        // View lease details modal
        $(document).on('click', '.view-lease-details-btn', function(e) {
            e.preventDefault();
            var encryptedId = $(this).data('id');
            var $body = $('#leaseDetailsModalBody');

            $body.html('<div class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span></div>');
            $('#leaseDetailsModal').modal('show');

            $.ajax({
                url: '/property/leases/' + encryptedId + '/details',
                method: 'GET',
                dataType: 'json',
                success: function(lease) {
                    $body.html(renderLeaseDetails(lease));
                },
                error: function() {
                    $body.html('<div class="text-danger">Failed to load lease details.</div>');
                }
            });
        });

        function renderLeaseDetails(lease) {
            function field(label, value) {
                return `
                    <div class="col-6 col-md-3">
                        <div class="text-muted" style="font-size:10px; line-height:1.2;">${label}</div>
                        <div class="fw-semibold" style="font-size:12px; line-height:1.3;">${value !== null && value !== undefined && value !== '' ? value : '-'}</div>
                    </div>
                `;
            }

            var statusBadge = lease.lease_status === 'Active'
                ? '<span class="badge badge-light-success">Active</span>'
                : (lease.lease_status === 'Terminated'
                    ? '<span class="badge badge-light-danger">Terminated</span>'
                    : '<span class="badge badge-light-secondary">' + (lease.lease_status || '-') + '</span>');

            var contractBadge = lease.contract_status === 'signed'
                ? '<span class="badge badge-light-success">Signed</span>'
                : (lease.contract_status === 'sent'
                    ? '<span class="badge badge-light-warning">Sent</span>'
                    : '<span class="badge badge-light-secondary">Not Sent</span>');

            return `
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row g-2">
                            ${field('Ref Number', lease.ref_number)}
                            ${field('Status', statusBadge)}
                            ${field('Start Date', lease.date_start)}
                            ${field('End Date', lease.date_end)}
                            ${field('Duration (Years)', lease.years)}
                            ${field('Auto Renewal', lease.auto_renewal)}
                            ${field('Contract', contractBadge)}
                            ${field('Documents', lease.documents_count)}
                        </div>

                        <hr class="my-2">

                        <div class="row g-2">
                            ${field('Tenant', lease.tenant_name)}
                            ${field('Tenant Mobile', lease.tenant_mobile)}
                            ${field('Tenant Email', lease.tenant_email)}
                            ${field('Landlord', lease.landlord_name)}
                            ${field('Property', lease.property_name)}
                            ${field('Property Ref', lease.property_reference)}
                            ${field('Unit Code', lease.unit_code)}
                            ${field('Unit Name', lease.unit_name)}
                        </div>

                        <hr class="my-2">

                        <div class="row g-2">
                            ${field('Rent Amount', lease.rent_amount !== null ? Number(lease.rent_amount).toFixed(2) : '-')}
                            ${field('VAT-able', lease.vat_able)}
                            ${field('Escalation Rate', lease.escalation_rate !== null ? lease.escalation_rate + '%' : '-')}
                            ${field('Escalation Frequency', lease.escalation_frequency)}
                            ${field('Service Charges Amount', lease.service_charges_amount !== null ? Number(lease.service_charges_amount).toFixed(2) : '-')}
                            ${field('Water Meter Reading', lease.initial_water_meter_reading)}
                        </div>
                    </div>
                </div>
            `;
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