@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Property Details - {{ $property->property_name }}</h5>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('property.clients.show', Crypt::encrypt($property->client->id)) }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to Client</span>
                            </a>
                            <a href="/property/properties/{{ Crypt::encrypt($property->id) }}/edit" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">edit_document</span>
                                <span class="ms-1">Edit Property</span>
                            </a>
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

                        <!-- Summary Header -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="mb-3">
                                <p class="text-muted mb-0 d-flex align-items-center flex-wrap" style="font-size: 0.875rem;">
                                    <strong class="me-1">Property Reference:</strong>
                                    <span id="propRefSummary">{{ $property->reference_number ?? 'N/A' }}</span>
                                    @if($property->reference_number)
                                    <span class="material-symbols-outlined copy-icon ms-1 me-2" data-copy="{{ $property->reference_number }}" title="Copy">content_copy</span>
                                    @endif
                                    <span class="mx-1">|</span>
                                    <strong class="ms-1 me-1">Client:</strong> {{ $property->client->names ?? '-' }}
                                </p>
                            </div>

                            <div class="d-flex flex-row">
                                <a class="btn btn-sm btn-primary d-flex align-items-center me-2" href="{{ route('units.tenants.create', ['client' => Crypt::encrypt($property->client->id)]) }}" target="_blank" rel="noopener noreferrer">
                                    <span class="material-symbols-outlined me-2" style="font-size:18px;">person</span>
                                    Add Tenant
                                </a>
                                <a class="btn btn-sm btn-primary d-flex align-items-center" href="{{ route('tenant.lease.create', ['client' => Crypt::encrypt($property->client->id)]) }}" target="_blank" rel="noopener noreferrer">
                                    <span class="material-symbols-outlined me-2" style="font-size:18px;">document_scanner</span>
                                    Add Lease
                                </a>
                            </div>
                        </div>


                        <!-- Tabs Navigation -->
                        <ul class="nav nav-tabs mb-4" id="propertyTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">home</span>
                                    Property Information
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">analytics</span>
                                    Statistics
                                </button>
                            </li>

                             <li class="nav-item" role="presentation">
                                <button class="nav-link" id="units-tab" data-bs-toggle="tab" data-bs-target="#units" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">apartment</span>
                                    Units
                                </button>
                            </li>

                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tenants-tab" data-bs-toggle="tab" data-bs-target="#tenants" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">groups</span>
                                    Tenants
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="leases-tab" data-bs-toggle="tab" data-bs-target="#leases" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">assignment</span>
                                    Leases
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="repairs-tab" data-bs-toggle="tab" data-bs-target="#repairs" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">build</span>
                                    Repairs & Maintenance
                                </button>
                            </li>
                        </ul>

                        <!-- Tabs Content -->
                        <div class="tab-content" id="propertyTabsContent">
                            <!-- Property Information Tab -->
                            <div class="tab-pane fade show active" id="info" role="tabpanel" aria-labelledby="info-tab">
                                <div class="info-panel">
                                    <div class="info-panel-header">
                                        <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">home_work</span>
                                        Property Information
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="info-row">
                                                <span class="info-label">Property Name</span>
                                                <span class="info-value">{{ $property->property_name ?? '-' }}</span>
                                            </div>

                                            <div class="info-row">
                                                <span class="info-label">Reference Number</span>
                                                <span class="info-value d-flex align-items-center">
                                                    {{ $property->reference_number ?? '-' }}
                                                    @if($property->reference_number)
                                                    <span class="material-symbols-outlined copy-icon ms-2" data-copy="{{ $property->reference_number }}" title="Copy">content_copy</span>
                                                    @endif
                                                </span>
                                            </div>

                                            <div class="info-row">
                                                <span class="info-label">LR Number</span>
                                                <span class="info-value">{{ $property->lr_number ?? '-' }}</span>
                                            </div>

                                            <div class="info-row">
                                                <span class="info-label">Acquisition Date</span>
                                                <span class="info-value">{{ $property->acquisition_date ? $property->acquisition_date->format('d-m-Y') : '-' }}</span>
                                            </div>

                                            <div class="info-row">
                                                <span class="info-label">Owner/Client</span>
                                                <span class="info-value">{{ $property->client->names ?? '-' }}</span>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="info-row">
                                                <span class="info-label">Zone</span>
                                                <span class="info-value">{{ $property->zone->zone_name ?? '-' }}</span>
                                            </div>

                                            <div class="info-row">
                                                <span class="info-label">Zone Area</span>
                                                <span class="info-value">{{ $property->zoneArea->area_name ?? '-' }}</span>
                                            </div>

                                            <div class="info-row">
                                                <span class="info-label">Field Officer</span>
                                                <span class="info-value">{{ $property->fieldOfficer ? strtoupper($property->fieldOfficer->full_names) : '-' }}</span>
                                            </div>

                                            <div class="info-row">
                                                <span class="info-label">Zone Supervisor</span>
                                                <span class="info-value">{{ $property->zoneSupervisor ? strtoupper($property->zoneSupervisor->full_names) : '-' }}</span>
                                            </div>

                                            <div class="info-row">
                                                <span class="info-label">Zone Manager</span>
                                                <span class="info-value">{{ $property->zoneManager ? strtoupper($property->zoneManager->full_names) : '-' }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="info-row">
                                        <span class="info-label">Coordinates</span>
                                        <span class="info-value">{{ $property->lat_long ?? '-' }}</span>
                                    </div>

                                    <div class="info-row description-row">
                                        <span class="info-label">Description</span>
                                        <span class="info-value">{{ $property->description ?? '-' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Statistics Tab -->
                            <div class="tab-pane fade" id="stats" role="tabpanel" aria-labelledby="stats-tab">
                                @php
                                    $totalUnits    = $property->units->count();
                                    $occupiedUnits = $property->units->where('occupancy_status_id', 1)->count();
                                    $vacantUnits   = $property->units->where('occupancy_status_id', 3)->count();
                                    $occupancyRate = $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100, 1) : 0;

                                    $today             = now()->startOfDay();
                                    $threeMonthsLater  = now()->addMonths(3)->endOfDay();
                                    $activeLeases      = $property->leaseAgreements
                                        ->where('current_status_id', 5)
                                        ->filter(fn($l) => $l->date_end && \Carbon\Carbon::parse($l->date_end)->endOfDay()->isFuture())
                                        ->count();
                                    $totalTenants      = $property->leaseAgreements
                                        ->where('current_status_id', 5)
                                        ->filter(fn($l) => $l->date_end && \Carbon\Carbon::parse($l->date_end)->endOfDay()->isFuture())
                                        ->unique('tenant_id')
                                        ->count();
                                    $expiringLeases    = $property->leaseAgreements
                                        ->where('current_status_id', 5)
                                        ->filter(function($l) use ($today, $threeMonthsLater) {
                                            if (!$l->date_end) return false;
                                            $end = \Carbon\Carbon::parse($l->date_end)->endOfDay();
                                            return $end->greaterThan($today) && $end->lessThanOrEqualTo($threeMonthsLater);
                                        })
                                        ->count();
                                @endphp

                                <style>
                                .ps-card {
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
                                .ps-card::before {
                                    content: '';
                                    position: absolute;
                                    top: 0; left: 0; bottom: 0;
                                    width: 3px;
                                    background: var(--ps-accent, #b7d7f0);
                                    border-radius: 8px 0 0 8px;
                                }
                                .ps-icon {
                                    width: 30px;
                                    height: 30px;
                                    border-radius: 7px;
                                    background: var(--ps-icon-bg, #d9ebf7);
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    flex-shrink: 0;
                                }
                                .ps-icon .material-symbols-outlined {
                                    font-size: 15px !important;
                                    color: #0a0a0a !important;
                                    font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
                                }
                                .ps-body { flex: 1; min-width: 0; }
                                .ps-value {
                                    font-size: 18px;
                                    font-weight: 400;
                                    color: #0a0a0a;
                                    line-height: 1.1;
                                    margin-bottom: 1px;
                                }
                                .ps-label {
                                    font-size: 10px;
                                    font-weight: 600;
                                    color: #6b7280;
                                    text-transform: uppercase;
                                    letter-spacing: 0.05em;
                                }
                                </style>

                                <div class="row g-3">
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="ps-card" style="--ps-accent:#5b9bd5; --ps-icon-bg:#b7d7f0;">
                                            <div class="ps-icon"><span class="material-symbols-outlined">domain</span></div>
                                            <div class="ps-body">
                                                <div class="ps-value">{{ $totalUnits }}</div>
                                                <div class="ps-label">Total Units</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="ps-card" style="--ps-accent:#b7d7f0; --ps-icon-bg:#d9ebf7;">
                                            <div class="ps-icon"><span class="material-symbols-outlined">check_circle</span></div>
                                            <div class="ps-body">
                                                <div class="ps-value">{{ $occupiedUnits }}</div>
                                                <div class="ps-label">Occupied</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="ps-card" style="--ps-accent:#8ab4cc; --ps-icon-bg:#d9ebf7;">
                                            <div class="ps-icon"><span class="material-symbols-outlined">radio_button_unchecked</span></div>
                                            <div class="ps-body">
                                                <div class="ps-value">{{ $vacantUnits }}</div>
                                                <div class="ps-label">Vacant</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="ps-card" style="--ps-accent:#5b9bd5; background:#5b9bd5; border-color:#5b9bd5;">
                                            <div class="ps-icon" style="background:rgba(255,255,255,0.25);"><span class="material-symbols-outlined" style="color:#fff !important;">percent</span></div>
                                            <div class="ps-body">
                                                <div class="ps-value" style="color:#fff;">{{ $occupancyRate }}%</div>
                                                <div class="ps-label" style="color:rgba(255,255,255,0.8);">Occupancy Rate</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="ps-card" style="--ps-accent:#5b9bd5; --ps-icon-bg:#b7d7f0;">
                                            <div class="ps-icon"><span class="material-symbols-outlined">description</span></div>
                                            <div class="ps-body">
                                                <div class="ps-value">{{ $activeLeases }}</div>
                                                <div class="ps-label">Active Leases</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="ps-card" style="--ps-accent:#b7d7f0; --ps-icon-bg:#d9ebf7;">
                                            <div class="ps-icon"><span class="material-symbols-outlined">group</span></div>
                                            <div class="ps-body">
                                                <div class="ps-value">{{ $totalTenants }}</div>
                                                <div class="ps-label">Total Tenants</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="ps-card" style="--ps-accent:#8ab4cc; --ps-icon-bg:#d9ebf7;">
                                            <div class="ps-icon"><span class="material-symbols-outlined">schedule</span></div>
                                            <div class="ps-body">
                                                <div class="ps-value">{{ $expiringLeases }}</div>
                                                <div class="ps-label">Expiring in 3 Months</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Units Tab (AJAX Load) -->
                            <div class="tab-pane fade" id="units" role="tabpanel" aria-labelledby="units-tab">
                                <div class="row mb-3 align-items-end">
                                    <input type="hidden" id="propertySelect" value="{{ $property->id }}">
                                    <div class="col-auto">
                                        <div id="propertySpinner" class="spinner-border text-primary" role="status" style="display: none;">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                    <div class="col-auto ms-auto">
                                        @permission('create_units')
                                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="addUnitBtn">
                                            <span class="material-symbols-outlined me-1" style="font-size:18px;">add_circle</span>
                                            Add Unit
                                        </button>
                                        @endpermission
                                    </div>
                                </div>
                                <div class="table-responsive custom-scrollbar">
                                    <table id="unitsTable" class="display" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Unit Code</th>
                                                <th>Unit Name</th>
                                                <th>Unit Number</th>
                                                <th>Description</th>
                                                <th>Rent Amount</th>
                                                <th>Zone</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Tenants Tab -->
                            <div class="tab-pane fade" id="tenants" role="tabpanel" aria-labelledby="tenants-tab">
                                <div class="mb-3">
                                    <div id="property-tenants-preloader" class="text-center my-4">
                                        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2 text-muted">Loading tenants...</p>
                                    </div>

                                    <div class="table-responsive" style="visibility: hidden;" id="property-tenants-table-wrapper">
                                        <table class="display" id="property-tenants-table" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Names</th>
                                                    <th>Phone</th>
                                                    <th>ID Number</th>
                                                    <th>Unit</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Leases Tab -->
                            <div class="tab-pane fade" id="leases" role="tabpanel" aria-labelledby="leases-tab">
                                <div class="mb-3">
                                    <div id="property-leases-preloader" class="text-center my-4">
                                        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2 text-muted">Loading leases...</p>
                                    </div>

                                    <div class="table-responsive" style="visibility: hidden;" id="property-leases-table-wrapper">
                                        <table class="display" id="property-leases-table" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Ref</th>
                                                    <th>Tenant</th>
                                                    <th>Unit</th>
                                                    <th>Start</th>
                                                    <th>End</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Repairs & Maintenance Tab -->
                            <div class="tab-pane fade" id="repairs" role="tabpanel" aria-labelledby="repairs-tab">
                                <div class="mb-3">
                                    <div id="property-repairs-preloader" class="text-center my-4">
                                        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2 text-muted">Loading repairs & maintenance...</p>
                                    </div>

                                    <div class="table-responsive" style="visibility: hidden;" id="property-repairs-table-wrapper">
                                        <table class="display" id="property-repairs-table" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Ref Number</th>
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
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Unit Modal -->
<div class="modal fade" id="addUnitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Unit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addUnitForm">
                    <input type="hidden" name="property_id" value="{{ $property->id }}">
                    <input type="hidden" name="client_id" value="{{ $property->client_id }}">

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="unit_number" class="form-label">Unit Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="unit_number" name="unit_number" placeholder="e.g. Shop 1" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="unit_name" class="form-label">Unit Name</label>
                            <input type="text" class="form-control" id="unit_name" name="unit_name" placeholder="e.g. Block A - Shop 1">
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
                                <option value="1">Topology 1</option>
                                <option value="2">Topology 2</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select class="form-select" id="category_id" name="category_id">
                                <option value="">Please select</option>
                                <option value="1">For Rent</option>
                                <option value="2">Other</option>
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

<!-- Edit Unit Modal -->
<div class="modal fade" id="editUnitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Unit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editUnitForm">
                    <input type="hidden" name="property_id" value="{{ $property->id }}">
                    <input type="hidden" id="edit_unit_id" name="unit_id">

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="edit_unit_number" class="form-label">Unit Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_unit_number" name="unit_number" placeholder="e.g. Shop 1" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="edit_unit_name" class="form-label">Unit Name</label>
                            <input type="text" class="form-control" id="edit_unit_name" name="unit_name" placeholder="e.g. Block A - Shop 1">
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="edit_unit_type_id" class="form-label">Unit Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_unit_type_id" name="unit_type_id" required>
                                <option value="">Please select</option>
                                <option value="1">Normal</option>
                                <option value="2">Owner Occupied</option>
                                <option value="3">Caretaker</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="edit_usage_type_id" class="form-label">Usage Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_usage_type_id" name="usage_type_id" required>
                                <option value="">Please select</option>
                                <option value="1">Commercial</option>
                                <option value="3">Hybrid</option>
                                <option value="2">Residential</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="edit_unit_condition_id" class="form-label">Unit Condition <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_unit_condition_id" name="unit_condition_id" placeholder="e.g. Good, Fair, Needs repair" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="edit_occupancy_status_id" class="form-label">Occupation Status</label>
                            <select class="form-select" id="edit_occupancy_status_id" name="occupancy_status_id">
                                <option value="31">Vacant</option>
                                <option value="38">Occupied</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="edit_topology_id" class="form-label">Topology</label>
                            <select class="form-select" id="edit_topology_id" name="topology_id">
                                <option value="">Please select</option>
                                <option value="1">Topology 1</option>
                                <option value="2">Topology 2</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="edit_category_id" class="form-label">Category</label>
                            <select class="form-select" id="edit_category_id" name="category_id">
                                <option value="">Please select</option>
                                <option value="1">For Rent</option>
                                <option value="2">Other</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="edit_vat_able" class="form-label">VAT</label>
                            <select class="form-select" id="edit_vat_able" name="vat_able">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="edit_rent_amount" class="form-label">Rent Amount</label>
                            <input type="number" step="0.01" class="form-control" id="edit_rent_amount" name="rent_amount" placeholder="0.00">
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="edit_initial_water_meter_reading" class="form-label">Initial Water Meter Reading</label>
                            <input type="number" step="0.01" class="form-control" id="edit_initial_water_meter_reading" name="initial_water_meter_reading" placeholder="0">
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3" placeholder="Optional notes about the unit"></textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-primary d-flex align-items-center" id="updateUnitBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="updateUnitSpinner"></span>
                            <span class="material-symbols-outlined" id="updateUnitIcon">check_circle</span>
                            <span class="ms-1" id="updateUnitText">Update</span>
                        </button>
                    </div>
                </form>
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

    .info-panel {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 16px 20px;
        background: #fff;
    }

    .info-panel-header {
        font-size: 0.95rem;
        font-weight: 600;
        color: #333;
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e0e0e0;
    }

    .info-row {
        display: flex;
        flex-wrap: wrap;
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
        font-size: 0.85rem;
    }

    .info-row:last-child {
        border-bottom: none;
    }

    .info-row.description-row {
        align-items: flex-start;
    }

    #unitsTable td,
    #unitsTable th {
        padding-top: 0.45rem;
        padding-bottom: 0.45rem;
        vertical-align: middle;
    }

    .edit-unit-btn {
        width: 24px;
        height: 24px;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        color: #6c757d;
        transition: background-color 0.15s ease, color 0.15s ease;
    }

    .edit-unit-btn:hover {
        background-color: #eef3fb;
        color: #3598db;
    }

    .edit-unit-btn .material-symbols-outlined {
        font-size: 15px;
    }

    .info-label {
        flex: 0 0 45%;
        max-width: 45%;
        color: #6b7280;
    }

    .info-value {
        flex: 1;
        min-width: 0;
        color: #212529;
        font-weight: 500;
        word-break: break-word;
    }

    .copy-icon {
        cursor: pointer;
        color: #5aa9e0;
        font-size: 16px;
        line-height: 1;
        transition: color 0.15s ease;
    }

    .copy-icon:hover {
        color: #3598db;
    }

    .copy-icon.copied {
        color: #3598db;
    }
</style>
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(function() {
        var unitsTable = null;
        var unitsLoaded = false;

        // Copy-to-clipboard for info fields
        $(document).on('click', '.copy-icon', function() {
            var $icon = $(this);
            var text = $icon.data('copy');
            if (!text) return;

            function showCopied() {
                var original = $icon.text();
                $icon.addClass('copied').text('check');
                setTimeout(function() {
                    $icon.removeClass('copied').text(original);
                }, 1200);
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(String(text)).then(showCopied);
            } else {
                var $tmp = $('<textarea>').val(text).appendTo('body').select();
                document.execCommand('copy');
                $tmp.remove();
                showCopied();
            }
        });
        
        // format ISO date to dd/mm/yy
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

        var leasesTable = $('#property-leases-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: '/property/listings/{{ Crypt::encrypt($property->id) }}/leases',
                dataSrc: 'data'
            },
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
            order: [
                [4, 'desc']
            ],
            initComplete: function() {
                $('#property-leases-preloader').hide();
                $('#property-leases-table-wrapper').css('visibility', 'visible');
            }
        });

        // Tenants DataTable for this property
        var tenantsTable = $('#property-tenants-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: '/property/listings/{{ Crypt::encrypt($property->id) }}/tenants',
                dataSrc: 'data'
            },
            columns: [{
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'names',
                    render: function(data, type, row) {
                        return `<a href="/tenants/${row.encrypted_id}">${data}</a>`;
                    }
                },
                {
                    data: 'mobile_number'
                },
                {
                    data: 'national_id'
                },
                {
                    data: 'unit_name'
                }
            ],
            order: [
                [1, 'asc']
            ],
            initComplete: function() {
                $('#property-tenants-preloader').hide();
                $('#property-tenants-table-wrapper').css('visibility', 'visible');
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
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        if (!row.can_edit) {
                            return '';
                        }
                        return `
                            <button type="button" class="btn btn-link p-0 edit-unit-btn" title="Edit Unit" data-row='${JSON.stringify(row)}'>
                                <span class="material-symbols-outlined">edit</span>
                            </button>
                        `;
                    }
                }
            ],
            pageLength: 10,
            responsive: true,
            language: {
                emptyTable: "No units found for this property"
            }
        });

        // Load units when units tab is clicked
        $('#units-tab').on('click', function() {
            if (!unitsLoaded) {
                var propertyId = $('#propertySelect').val();
                if (propertyId) {
                    loadUnits(propertyId);
                    unitsLoaded = true;
                }
            }
        });

        function loadUnits(propertyId) {
            // Show spinner
            $('#propertySpinner').show();

            $.ajax({
                url: "{{ route('property.units.get') }}",
                type: 'GET',
                data: {
                    property_id: propertyId
                },
                dataType: 'json',
                success: function(response) {
                    unitsTable.clear();
                    if (response.data && response.data.length > 0) {
                        unitsTable.rows.add(response.data);
                        unitsTable.draw();
                    } else {
                        unitsTable.draw();
                        toastr.info('No units found for this property.');
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

        // ── Add Unit Modal ──────────────────────────────────────────────────────
        $('#addUnitBtn').on('click', function() {
            $('#addUnitForm')[0].reset();
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

        // ── Edit Unit Modal ─────────────────────────────────────────────────────
        $(document).on('click', '.edit-unit-btn', function() {
            var row = JSON.parse($(this).attr('data-row'));

            $('#editUnitForm .is-invalid').removeClass('is-invalid');
            $('#edit_unit_id').val(row.encrypted_id);
            $('#edit_unit_number').val(row.unit_number_raw || '');
            $('#edit_unit_name').val(row.unit_name_raw || '');
            $('#edit_unit_type_id').val(row.unit_type_id || '');
            $('#edit_usage_type_id').val(row.usage_type_id || '');
            $('#edit_unit_condition_id').val(row.unit_condition_id || '');
            $('#edit_occupancy_status_id').val(row.occupancy_status_id || '31');
            $('#edit_topology_id').val(row.topology_id || '');
            $('#edit_category_id').val(row.category_id || '');
            $('#edit_vat_able').val(row.vat_able == 1 ? 'Yes' : 'No');
            $('#edit_rent_amount').val(row.rent_amount_raw || '');
            $('#edit_initial_water_meter_reading').val(row.initial_water_meter_reading || '');
            $('#edit_description').val(row.description_raw || '');

            $('#editUnitModal').modal('show');
        });

        $('#editUnitForm').on('submit', function(e) {
            e.preventDefault();

            var $btn = $('#updateUnitBtn');
            var $spinner = $('#updateUnitSpinner');
            var $icon = $('#updateUnitIcon');
            var $text = $('#updateUnitText');
            var unitId = $('#edit_unit_id').val();

            $('#editUnitForm .is-invalid').removeClass('is-invalid');
            $spinner.removeClass('d-none');
            $icon.addClass('d-none');
            $text.text('Updating...');
            $btn.prop('disabled', true);

            $.ajax({
                url: "{{ url('/property/units') }}/" + unitId,
                type: 'PUT',
                data: $(this).serialize() + '&_token={{ csrf_token() }}',
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message || 'Unit updated successfully');
                        $('#editUnitModal').modal('hide');
                        loadUnits($('#propertySelect').val());
                    } else {
                        toastr.error(res.error || 'Failed to update unit');
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        var errors = xhr.responseJSON.errors;
                        Object.keys(errors).forEach(function(field) {
                            var $field = $('#editUnitForm [name="' + field + '"]');
                            $field.addClass('is-invalid');
                            $field.siblings('.invalid-feedback').text(errors[field][0]);
                        });
                        toastr.error('Please correct the highlighted fields');
                    } else {
                        var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Failed to update unit';
                        toastr.error(msg);
                    }
                },
                complete: function() {
                    $spinner.addClass('d-none');
                    $icon.removeClass('d-none');
                    $text.text('Update');
                    $btn.prop('disabled', false);
                }
            });
        });

        $('#editUnitModal').on('hidden.bs.modal', function() {
            $('#editUnitForm')[0].reset();
            $('#editUnitForm .is-invalid').removeClass('is-invalid');
        });

        // Repairs DataTable for this property
        var repairsTable = $('#property-repairs-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: '/property/listings/{{ Crypt::encrypt($property->id) }}/repairs',
                dataSrc: 'data'
            },
            columns: [
                {
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'ref_number'
                },
                {
                    data: 'visited_date_time',
                    render: function(data) {
                        if (!data) return '-';
                        const date = new Date(data.replace(' ', 'T'));
                        if (isNaN(date.getTime())) return data;
                        const dateOptions = { year: 'numeric', month: 'short', day: '2-digit' };
                        const timeOptions = { hour: '2-digit', minute: '2-digit', hour12: true };
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
            order: [[2, 'desc']],
            initComplete: function() {
                $('#property-repairs-preloader').hide();
                $('#property-repairs-table-wrapper').css('visibility', 'visible');
            }
        });
    });
</script>
@endsection