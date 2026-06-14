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
                                <p class="text-muted mb-0" style="font-size: 0.875rem;">
                                    <strong>Ref #:</strong> {{ $property->reference_number ?? 'N/A' }} |
                                    <strong>Property ID:</strong> {{ $property->id }} |
                                    <strong>Client:</strong> {{ $property->client->names ?? '-' }} 
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
                                <div class="row g-3">
                                    <!-- Basic Information Card -->
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-light py-2">
                                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">info</span>
                                                    Basic Information
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-1">
                                                    <div class="col-5"><small class="text-muted">Property ID:</small></div>
                                                    <div class="col-7"><small><strong>{{ $property->id }}</strong></small></div>

                                                    <div class="col-5"><small class="text-muted">Property Name:</small></div>
                                                    <div class="col-7"><small><strong>{{ $property->property_name ?? '-' }}</strong></small></div>

                                                    <div class="col-5"><small class="text-muted">Reference Number:</small></div>
                                                    <div class="col-7"><small><strong>{{ $property->reference_number ?? '-' }}</strong></small></div>

                                                    <div class="col-5"><small class="text-muted">LR Number:</small></div>
                                                    <div class="col-7"><small>{{ $property->lr_number ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Acquisition Date:</small></div>
                                                    <div class="col-7"><small>{{ $property->acquisition_date ? $property->acquisition_date->format('d-m-Y') : '-' }}</small></div>

                                                    <div class="col-12 mt-2"><small class="text-muted">Description:</small></div>
                                                    <div class="col-12"><small>{{ $property->description ?? '-' }}</small></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Location & Management Card -->
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-light py-2">
                                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">location_on</span>
                                                    Location & Management
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-1">
                                                    <div class="col-5"><small class="text-muted">Owner/Client:</small></div>
                                                    <div class="col-7"><small><strong>{{ $property->client->names ?? '-' }}</strong></small></div>

                                                    <div class="col-5"><small class="text-muted">Zone:</small></div>
                                                    <div class="col-7"><small>{{ $property->zone->zone_name ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Zone Area:</small></div>
                                                    <div class="col-7"><small>{{ $property->zoneArea->area_name ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Field Officer:</small></div>
                                                    <div class="col-7"><small>{{ $property->fieldOfficer ? strtoupper($property->fieldOfficer->full_names) : '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Zone Supervisor:</small></div>
                                                    <div class="col-7"><small>{{ $property->zoneSupervisor ? strtoupper($property->zoneSupervisor->full_names) : '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Zone Manager:</small></div>
                                                    <div class="col-7"><small>{{ $property->zoneManager ? strtoupper($property->zoneManager->full_names) : '-' }}</small></div>

                                                    <div class="col-12 mt-2"><small class="text-muted">Coordinates:</small></div>
                                                    <div class="col-12"><small>{{ $property->lat_long ?? '-' }}</small></div>
                                                </div>
                                            </div>
                                        </div>
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
                                        <div class="ps-card" style="--ps-accent:#5b9bd5; --ps-icon-bg:#b7d7f0;">
                                            <div class="ps-icon"><span class="material-symbols-outlined">percent</span></div>
                                            <div class="ps-body">
                                                <div class="ps-value">{{ $occupancyRate }}%</div>
                                                <div class="ps-label">Occupancy Rate</div>
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
</style>
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(function() {
        var unitsTable = null;
        var unitsLoaded = false;
        
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
                        return `
                            <a href="/property/units/${row.encrypted_id}/edit" title="Edit Unit">
                                <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
                            </a>
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