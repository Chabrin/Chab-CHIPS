@extends('layouts.main')
@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/property-details.css') }}?v={{ filemtime(public_path('assets/css/property-details.css')) }}">
@endsection

@section('content')
<div class="page-body" id="property-details">
    @include('property.properties.partials.detail-icons')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card property-detail-card">
                    <header class="property-detail-header">
                        <div class="property-detail-heading">
                            <p class="property-detail-eyebrow">Management / Property details</p>
                            <h1>{{ $property->property_name }}</h1>
                            @unless($property->active)
                                <span class="property-unit-status property-unit-status--vacant">Inactive</span>
                            @endunless
                        </div>
                        <div class="property-detail-actions">
                            @if($property->active)
                            <a href="{{ route('property.clients.show', Crypt::encrypt($property->client->id)) }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-arrow-left"></use></svg>
                                <span class="ms-1">Back to Client</span>
                            </a>
                            @else
                            <a href="{{ route('property.properties.inactive.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-arrow-left"></use></svg>
                                <span class="ms-1">Back to Listing</span>
                            </a>
                            @endif
                            @if($property->active)
                            <a href="/property/properties/{{ Crypt::encrypt($property->id) }}/edit" class="btn btn-primary btn-sm d-flex align-items-center">
                                <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-edit"></use></svg>
                                <span class="ms-1">Edit Property</span>
                            </a>
                            @endif
                        </div>
                    </header>
                    <div class="card-body property-detail-body">
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

                        <div class="property-detail-summary">
                            <dl class="property-detail-facts">
                                <div>
                                    <dt>Property reference</dt>
                                    <dd>
                                        <span id="propRefSummary">{{ $property->reference_number ?? '—' }}</span>
                                        @if($property->reference_number)
                                            <button type="button" class="copy-icon" data-copy="{{ $property->reference_number }}" title="Copy reference" aria-label="Copy property reference"><svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-copy"></use></svg></button>
                                        @endif
                                    </dd>
                                </div>
                                <div><dt>Client</dt><dd>{{ $property->client->names ?? '—' }}</dd></div>
                                <div><dt>Zone</dt><dd>{{ $property->zone->zone_name ?? '—' }}</dd></div>
                            </dl>

                            @if($property->active)
                            <div class="property-detail-actions">
                                <a class="btn btn-sm btn-outline-primary d-flex align-items-center" href="{{ route('units.tenants.create', ['client' => Crypt::encrypt($property->client->id)]) }}" target="_blank" rel="noopener noreferrer">
                                    <svg class="property-detail-icon me-2" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-user"></use></svg>
                                    Add Tenant
                                </a>
                                <a class="btn btn-sm btn-outline-primary d-flex align-items-center" href="{{ route('tenant.lease.create', ['client' => Crypt::encrypt($property->client->id)]) }}" target="_blank" rel="noopener noreferrer">
                                    <svg class="property-detail-icon me-2" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-file-text"></use></svg>
                                    Add Lease
                                </a>
                            </div>
                            @endif
                        </div>


                        <!-- Tabs Navigation -->
                        <ul class="nav nav-tabs mb-4" id="propertyTabs" role="tablist" aria-label="Property sections">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab" aria-controls="info" aria-selected="true">
                                    <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-home"></use></svg>
                                    Property Information
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab" aria-controls="stats" aria-selected="false">
                                    <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-bar-chart-2"></use></svg>
                                    Statistics
                                </button>
                            </li>

                             <li class="nav-item" role="presentation">
                                <button class="nav-link" id="units-tab" data-bs-toggle="tab" data-bs-target="#units" type="button" role="tab" aria-controls="units" aria-selected="false">
                                    <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-grid"></use></svg>
                                    Units
                                </button>
                            </li>

                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tenants-tab" data-bs-toggle="tab" data-bs-target="#tenants" type="button" role="tab" aria-controls="tenants" aria-selected="false">
                                    <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-users"></use></svg>
                                    Tenants
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="leases-tab" data-bs-toggle="tab" data-bs-target="#leases" type="button" role="tab" aria-controls="leases" aria-selected="false">
                                    <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-clipboard"></use></svg>
                                    Leases
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="repairs-tab" data-bs-toggle="tab" data-bs-target="#repairs" type="button" role="tab" aria-controls="repairs" aria-selected="false">
                                    <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-settings"></use></svg>
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
                                        <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-home"></use></svg>
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
                                                    <button type="button" class="copy-icon ms-2" data-copy="{{ $property->reference_number }}" title="Copy reference" aria-label="Copy property reference"><svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-copy"></use></svg></button>
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
                                        ->filter(fn($l) => $l->open_ended || ($l->date_end && \Carbon\Carbon::parse($l->date_end)->endOfDay()->isFuture()))
                                        ->count();
                                    $totalTenants      = $property->leaseAgreements
                                        ->where('current_status_id', 5)
                                        ->filter(fn($l) => $l->open_ended || ($l->date_end && \Carbon\Carbon::parse($l->date_end)->endOfDay()->isFuture()))
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
                                #property-details .ps-card {
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
                                #property-details .ps-card::before {
                                    content: '';
                                    position: absolute;
                                    top: 0; left: 0; bottom: 0;
                                    width: 3px;
                                    background: var(--ps-accent, #b7d7f0);
                                    border-radius: 8px 0 0 8px;
                                }
                                #property-details .ps-icon {
                                    width: 30px;
                                    height: 30px;
                                    border-radius: 7px;
                                    background: var(--ps-icon-bg, #d9ebf7);
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    flex-shrink: 0;
                                }
                                #property-details .ps-icon .property-detail-icon {
                                    font-size: 15px !important;
                                    color: #0a0a0a !important;
                                    font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
                                }
                                #property-details .ps-body { flex: 1; min-width: 0; }
                                #property-details .ps-value {
                                    font-size: 18px;
                                    font-weight: 400;
                                    color: #0a0a0a;
                                    line-height: 1.1;
                                    margin-bottom: 1px;
                                }
                                #property-details .ps-label {
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
                                            <div class="ps-icon"><svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-grid"></use></svg></div>
                                            <div class="ps-body">
                                                <div class="ps-value">{{ $totalUnits }}</div>
                                                <div class="ps-label">Total Units</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="ps-card" style="--ps-accent:#b7d7f0; --ps-icon-bg:#d9ebf7;">
                                            <div class="ps-icon"><svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-check-circle"></use></svg></div>
                                            <div class="ps-body">
                                                <div class="ps-value">{{ $occupiedUnits }}</div>
                                                <div class="ps-label">Occupied</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="ps-card" style="--ps-accent:#8ab4cc; --ps-icon-bg:#d9ebf7;">
                                            <div class="ps-icon"><svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-circle"></use></svg></div>
                                            <div class="ps-body">
                                                <div class="ps-value">{{ $vacantUnits }}</div>
                                                <div class="ps-label">Vacant</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="ps-card" style="--ps-accent:#5b9bd5; background:#5b9bd5; border-color:#5b9bd5;">
                                            <div class="ps-icon" style="background:rgba(255,255,255,0.25);"><svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-percent"></use></svg></div>
                                            <div class="ps-body">
                                                <div class="ps-value" style="color:#fff;">{{ $occupancyRate }}%</div>
                                                <div class="ps-label" style="color:rgba(255,255,255,0.8);">Occupancy Rate</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="ps-card" style="--ps-accent:#5b9bd5; --ps-icon-bg:#b7d7f0;">
                                            <div class="ps-icon"><svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-file-text"></use></svg></div>
                                            <div class="ps-body">
                                                <div class="ps-value">{{ $activeLeases }}</div>
                                                <div class="ps-label">Active Leases</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="ps-card" style="--ps-accent:#b7d7f0; --ps-icon-bg:#d9ebf7;">
                                            <div class="ps-icon"><svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-users"></use></svg></div>
                                            <div class="ps-body">
                                                <div class="ps-value">{{ $totalTenants }}</div>
                                                <div class="ps-label">Total Tenants</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-sm-6">
                                        <div class="ps-card" style="--ps-accent:#8ab4cc; --ps-icon-bg:#d9ebf7;">
                                            <div class="ps-icon"><svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-clock"></use></svg></div>
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
                                <div class="property-section-toolbar">
                                    <div><h2>Units</h2><p>Review rent, occupancy, and tenants by unit.</p></div>
                                    <input type="hidden" id="propertySelect" value="{{ $property->id }}">
                                    <div class="col-auto">
                                        <div id="propertySpinner" class="spinner-border text-primary" role="status" style="display: none;">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                    <div class="property-detail-actions">
                                        @if($property->active)
                                        @permission('create_units')
                                        <button type="button" class="btn btn-outline-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#bulkUploadUnitsModal">
                                            <svg class="property-detail-icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-upload"></use></svg>
                                            Bulk Upload Units
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="addUnitBtn">
                                            <svg class="property-detail-icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-plus-circle"></use></svg>
                                            Add Unit
                                        </button>
                                        @endpermission
                                        @endif
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
                                                <th>Tenants</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Tenants Tab -->
                            <div class="tab-pane fade" id="tenants" role="tabpanel" aria-labelledby="tenants-tab">
                                <div class="property-section-toolbar"><div><h2>Tenants</h2><p>Find tenants and their unit assignments.</p></div></div>
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
                                                    <th>Tenant</th>
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
                                <div class="property-section-toolbar"><div><h2>Leases</h2><p>Review lease dates and service charges.</p></div></div>
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
                                                    <th>Service Charges</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Repairs & Maintenance Tab -->
                            <div class="tab-pane fade" id="repairs" role="tabpanel" aria-labelledby="repairs-tab">
                                <div class="property-section-toolbar"><div><h2>Repairs &amp; Maintenance</h2><p>Track repair visits and their status.</p></div></div>
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

<div class="modal fade" id="leaseDetailsModal" tabindex="-1" aria-labelledby="leaseDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="leaseDetailsModalLabel">
                    <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-file-text"></use></svg>
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
                    <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-home"></use></svg>
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
                            <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-x"></use></svg>
                            <span class="ms-1">Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-primary d-flex align-items-center" id="saveUnitBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="saveUnitSpinner"></span>
                            <svg class="property-detail-icon" id="saveUnitIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-check-circle"></use></svg>
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
                                @foreach($unitTopologies as $topology)
                                <option value="{{ $topology->id }}">{{ $topology->topology_name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="edit_category_id" class="form-label">Category</label>
                            <select class="form-select" id="edit_category_id" name="category_id">
                                <option value="">Please select</option>
                                @foreach($unitCategories as $category)
                                <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                                @endforeach
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
                            <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-x"></use></svg>
                            <span class="ms-1">Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-primary d-flex align-items-center" id="updateUnitBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="updateUnitSpinner"></span>
                            <svg class="property-detail-icon" id="updateUnitIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-check-circle"></use></svg>
                            <span class="ms-1" id="updateUnitText">Update</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Upload Units Modal -->
<div class="modal fade" id="bulkUploadUnitsModal" tabindex="-1" aria-labelledby="bulkUploadUnitsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkUploadUnitsModalLabel">
                    <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-upload"></use></svg>
                    Bulk Upload Units
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('property.bulk.upload') }}" method="POST" enctype="multipart/form-data" id="bulkUploadUnitsForm">
                @csrf
                <input type="hidden" name="upload_type" value="units">
                <input type="hidden" name="client_id" value="{{ $property->client_id }}">
                <input type="hidden" name="property_id" value="{{ $property->id }}">

                <div class="modal-body">
                    <div class="alert alert-info d-flex align-items-start">
                        <svg class="property-detail-icon text-primary me-2" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-info"></use></svg>
                        <div class="flex-grow-1">
                            <p class="mb-2 small">Every unit in this file will be added to <strong>{{ $property->property_name }} ({{ $property->reference_number }})</strong>, regardless of what's in the "Property Reference" column of the sample.</p>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="downloadUnitsSampleBtn">
                                <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-download"></use></svg>
                                Download Sample File
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="bulk_units_file" class="form-label fw-bold">
                            <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-paperclip"></use></svg>
                            Upload File <span class="text-danger">*</span>
                        </label>
                        <input type="file" class="form-control" id="bulk_units_file" name="bulk_file" accept=".xlsx,.xls,.csv" required>
                        <small class="form-text text-muted">Accepted formats: Excel (.xlsx, .xls) or CSV (.csv)</small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="uploadUnitsBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="uploadUnitsSpinner"></span>
                        <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-upload"></use></svg>
                        Upload &amp; Process
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(function() {
        var unitsTable = null;
        var unitsLoaded = false;

        // Copy-to-clipboard for info fields
        $('#property-details').on('click', '.copy-icon', function() {
            var $icon = $(this);
            var text = $icon.data('copy');
            if (!text) return;

            function showCopied() {
                var $glyph = $icon.find('use');
                $glyph.attr('href', '#property-icon-check');
                setTimeout(function() {
                    $glyph.attr('href', '#property-icon-copy');
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
                },
                {
                    data: 'service_charges_amount',
                    render: function(data) {
                        return data !== null && data !== undefined ? Number(data).toFixed(2) : '-';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return `<a href="#" class="view-lease-details-btn" data-id="${row.encrypted_id}" title="View Lease Details">
                            <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-info"></use></svg>
                        </a>`;
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
                    data: 'occupancy_status',
                    render: function(value, type) {
                        if (type !== 'display') return value;
                        var label = String(value || 'Unknown').trim();
                        var status = label.toLowerCase();
                        var variant = ['vacant', 'occupied'].includes(status) ? status : 'neutral';
                        return $('<span>').addClass('property-unit-status property-unit-status--' + variant)
                            .text(label).prop('outerHTML');
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(d, type, row) {
                        var count = (row.tenants || []).length;
                        return '<span class="d-flex align-items-center gap-1">' +
                            '<svg class="property-detail-icon text-primary" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-eye"></use></svg>' +
                            '<span class="small text-muted">' + count + ' tenant' + (count === 1 ? '' : 's') + '</span>' +
                            '</span>';
                    }
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
                            <button type="button" class="btn btn-link p-0 edit-unit-btn" title="Edit Unit" aria-label="Edit unit" data-row='${JSON.stringify(row)}'>
                                <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-edit-2"></use></svg>
                            </button>
                        `;
                    }
                }
            ],
            createdRow: function(row) {
                $(row).addClass('group-parent-row table-light').css('cursor', 'pointer');
            },
            pageLength: 10,
            responsive: true,
            language: {
                emptyTable: "No units found for this property"
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

        $('#unitsTable').on('click', 'tr.group-parent-row', function(e) {
            if ($(e.target).closest('.edit-unit-btn').length) return; // don't open when the edit button was clicked

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

        // ── Bulk Upload Units Modal ────────────────────────────────────────────
        $('#downloadUnitsSampleBtn').on('click', function() {
            window.location.href = "{{ route('property.bulk.sample') }}?type=units&client_id={{ $property->client_id }}&property_id={{ $property->id }}";
        });

        $('#bulkUploadUnitsForm').on('submit', function() {
            $('#uploadUnitsSpinner').removeClass('d-none');
            $('#uploadUnitsBtn').prop('disabled', true);
        });

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
                                <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-eye"></use></svg>
                            </a>
                        `;
                        if (row.current_status_id == 1) {
                            buttons += `
                                <a href="/property/repairs/${encryptedId}/edit" class="text-success" title="Edit">
                                    <svg class="property-detail-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#property-icon-edit"></use></svg>
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