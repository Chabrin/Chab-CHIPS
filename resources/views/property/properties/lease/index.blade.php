@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Lease Management</h5>
                        {{-- @permission('create_lease_agreements') --}}
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('property.leases.setup') }}" class="btn btn-outline-primary btn-sm d-flex align-items-center" title="Clean up and activate migrated leases">
                                <span class="material-symbols-outlined">build</span>
                                <span class="ms-1">Lease Setup</span>
                            </a>
                            <a href="{{ route('property.lease-settings.contract-templates.index') }}" class="btn btn-outline-primary btn-sm d-flex align-items-center" title="Manage contract templates">
                                <span class="material-symbols-outlined">article</span>
                                <span class="ms-1">Contract Templates</span>
                            </a>
                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#renewalModal">
                                <span class="material-symbols-outlined">autorenew</span>
                                <span class="ms-1">Bulk Renewal</span>
                            </button>
                            <a href="{{ route('tenant.lease.create') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">New Lease</span>
                            </a>
                        </div>
                        {{-- @endpermission --}}
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

                        <style>
                            .ps-card {
                                background: #fff;
                                border: none;
                                border-bottom: 1px solid #e9edf1;
                                border-radius: 0;
                                padding: 7px 10px;
                                position: relative;
                                overflow: hidden;
                                display: flex;
                                align-items: center;
                                gap: 8px;
                                height: 100%;
                            }

                            .ps-icon {
                                width: 26px;
                                height: 26px;
                                border-radius: 6px;
                                background: var(--ps-icon-bg, #d9ebf7);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                flex-shrink: 0;
                            }

                            .ps-icon .material-symbols-outlined {
                                font-size: 14px !important;
                                color: var(--ps-accent, #0a0a0a) !important;
                                font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 20;
                            }

                            .ps-body {
                                flex: 1;
                                min-width: 0;
                                display: flex;
                                align-items: baseline;
                                gap: 6px;
                                flex-wrap: wrap;
                            }

                            .ps-value {
                                font-size: 16px;
                                font-weight: 700;
                                color: #0a0a0a;
                                line-height: 1;
                            }

                            .ps-value .spinner-border {
                                width: 0.8rem;
                                height: 0.8rem;
                            }

                            .ps-label {
                                font-size: 10px;
                                font-weight: 600;
                                color: #6b7280;
                                text-transform: uppercase;
                                letter-spacing: 0.03em;
                                white-space: nowrap;
                            }

                            .ps-card.ps-clickable {
                                cursor: pointer;
                                transition: box-shadow .15s ease;
                            }

                            .ps-card.ps-clickable:hover {
                                box-shadow: 0 2px 8px rgba(53, 152, 219, 0.18);
                            }

                            .ps-action {
                                font-size: 10px;
                                line-height: 1;
                                padding: 2px 6px;
                                border-radius: 4px;
                                flex-shrink: 0;
                            }

                            .ps-action .material-symbols-outlined {
                                font-size: 12px !important;
                                vertical-align: -2px;
                                color: inherit !important;
                            }

                            .lease-pills {
                                background: #f3f7fb;
                                border-radius: 8px;
                                padding: 4px;
                                display: inline-flex;
                                flex-wrap: wrap;
                                gap: 4px;
                            }

                            .lease-pills .nav-link {
                                display: flex;
                                align-items: center;
                                gap: 6px;
                                font-size: 12.5px;
                                font-weight: 500;
                                color: #52667a;
                                padding: 6px 12px;
                                border-radius: 6px;
                                transition: background .15s ease, color .15s ease;
                            }

                            .lease-pills .nav-link .material-symbols-outlined {
                                font-size: 16px !important;
                            }

                            .lease-pills .nav-link:hover {
                                background: #e3edf6;
                                color: var(--theme-default);
                            }

                            .lease-pills .nav-link.active {
                                background: var(--theme-default);
                                color: #fff;
                                box-shadow: 0 1px 4px rgba(53, 152, 219, 0.35);
                            }
                        </style>

                        <!-- Lease Stats -->
                        <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-6 g-2 mb-3" id="leaseStatsRow">
                            <div class="col">
                                <div class="ps-card" style="--ps-accent:#fab219; --ps-icon-bg:#fef3da;">
                                    <div class="ps-icon"><span class="material-symbols-outlined">schedule</span></div>
                                    <div class="ps-body">
                                        <span class="ps-value stat-value" id="stat-expiring"><span class="spinner-border spinner-border-sm"></span></span>
                                        <span class="ps-label">Expiring Soon</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="ps-card" style="--ps-accent:#d03b3b; --ps-icon-bg:#fbe1e1;">
                                    <div class="ps-icon"><span class="material-symbols-outlined">error</span></div>
                                    <div class="ps-body">
                                        <span class="ps-value stat-value" id="stat-expired"><span class="spinner-border spinner-border-sm"></span></span>
                                        <span class="ps-label">Expired</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="ps-card" style="--ps-accent:#0ca30c; --ps-icon-bg:#dcf3dc;">
                                    <div class="ps-icon"><span class="material-symbols-outlined">verified</span></div>
                                    <div class="ps-body">
                                        <span class="ps-value stat-value" id="stat-signed"><span class="spinner-border spinner-border-sm"></span></span>
                                        <span class="ps-label">Signed</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="ps-card" style="--ps-accent:#3598db; --ps-icon-bg:#d9ebf7;">
                                    <div class="ps-icon"><span class="material-symbols-outlined">pending</span></div>
                                    <div class="ps-body">
                                        <span class="ps-value stat-value" id="stat-sent-not-signed"><span class="spinner-border spinner-border-sm"></span></span>
                                        <span class="ps-label">Awaiting Sig.</span>
                                    </div>
                                    <button class="btn btn-outline-primary ps-action" data-bs-toggle="modal" data-bs-target="#bulkSendModal" data-type="sent_not_signed" title="Resend All">
                                        <span class="material-symbols-outlined">refresh</span>
                                    </button>
                                </div>
                            </div>
                            <div class="col">
                                <div class="ps-card" style="--ps-accent:#5b9bd5; --ps-icon-bg:#e6f0fa;">
                                    <div class="ps-icon"><span class="material-symbols-outlined">mail</span></div>
                                    <div class="ps-body">
                                        <span class="ps-value stat-value" id="stat-not-sent"><span class="spinner-border spinner-border-sm"></span></span>
                                        <span class="ps-label">Not Sent</span>
                                    </div>
                                    <button class="btn btn-outline-primary ps-action" data-bs-toggle="modal" data-bs-target="#bulkSendModal" data-type="not_sent" title="Send All">
                                        <span class="material-symbols-outlined">send</span>
                                    </button>
                                </div>
                            </div>
                            <div class="col">
                                <div class="ps-card ps-clickable" style="--ps-accent:#8ab4cc; --ps-icon-bg:#e6f0fa;" onclick="$(document.getElementById('no-end-date-tab')).tab('show')">
                                    <div class="ps-icon"><span class="material-symbols-outlined">event_busy</span></div>
                                    <div class="ps-body">
                                        <span class="ps-value stat-value" id="stat-no-end-date"><span class="spinner-border spinner-border-sm"></span></span>
                                        <span class="ps-label">No End Date</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Nav tabs -->
                        <ul class="nav lease-pills mb-3" id="leaseTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="all-leases-tab" data-bs-toggle="tab" data-bs-target="#all-leases" type="button">
                                    <span class="material-symbols-outlined">description</span>
                                    All Leases
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="expiring-tab" data-bs-toggle="tab" data-bs-target="#expiring" type="button">
                                    <span class="material-symbols-outlined">schedule</span>
                                    Expiring Soon
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="no-lease-tab" data-bs-toggle="tab" data-bs-target="#no-lease" type="button">
                                    <span class="material-symbols-outlined">warning</span>
                                    Tenants Without Leases
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="no-end-date-tab" data-bs-toggle="tab" data-bs-target="#no-end-date" type="button">
                                    <span class="material-symbols-outlined">event_busy</span>
                                    No End Date
                                </button>
                            </li>
                        </ul>

                        <!-- Tab panes -->
                        <div class="tab-content" id="leaseTabContent">
                            <!-- All Leases Tab -->
                            <div class="tab-pane fade show active" id="all-leases" role="tabpanel">
                                <!-- Search Filters -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="filter_client" class="form-label">Filter by Client/Landlord</label>
                                        <select class="form-select" id="filter_client" name="filter_client">
                                            <option value="">All Clients</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="filter_property" class="form-label">Filter by Property</label>
                                        <select class="form-select" id="filter_property" name="filter_property">
                                            <option value="">All Properties</option>
                                        </select>
                                    </div>
                                    <div class="col-md-12 d-flex align-items-end">
                                        <button type="button" class="btn btn-primary w-100" id="searchLeasesBtn">
                                            <span class="material-symbols-outlined" style="font-size:18px;">search</span>
                                            Search Leases
                                        </button>
                                    </div>
                                </div>

                                <div class="position-relative">
                                    <div id="allLeasesLoader" class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status" style="display:none;">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <div id="searchPrompt">
                                            <span class="material-symbols-outlined" style="font-size:48px;">filter_alt</span>
                                            <p class="mt-2 text-muted">Select a client or property to view leases</p>
                                        </div>
                                    </div>
                                    <div class="table-responsive custom-scrollbar" id="allLeasesTableContainer" style="display:none;">
                                        <table id="allLeasesTable" class="display" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Tenant</th>
                                                    <th>Property</th>
                                                    <th>Unit</th>
                                                    <th>Start Date</th>
                                                    <th>End Date</th>
                                                    <th>Days to Expiry</th>
                                                    <th>Status</th>
                                                    <th>Contract</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Expiring Soon Tab -->
                            <div class="tab-pane fade" id="expiring" role="tabpanel">
                                <div class="position-relative">
                                    <div id="expiringLeasesLoader" class="text-center py-5" style="display: none;">
                                        <div class="spinner-border text-warning" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2 text-muted">Loading expiring leases...</p>
                                    </div>
                                    <div class="table-responsive custom-scrollbar" id="expiringLeasesTableContainer">
                                        <table id="expiringLeasesTable" class="display" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Lease Ref</th>
                                                    <th>Tenant</th>
                                                    <th>Property</th>
                                                    <th>Unit</th>
                                                    <th>End Date</th>
                                                    <th>Days to Expiry</th>
                                                    <th>Urgency</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Tenants Without Leases Tab -->
                            <div class="tab-pane fade" id="no-lease" role="tabpanel">
                                <div class="position-relative">
                                    <div id="noLeaseLoader" class="text-center py-5">
                                        <div class="spinner-border text-danger" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2 text-muted">Loading tenants without leases...</p>
                                    </div>
                                    <div class="table-responsive custom-scrollbar" id="noLeaseTableContainer" style="display:none;">
                                        <table id="noLeaseTable" class="display" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Tenant Name</th>
                                                    <th>Reference Number</th>
                                                    <th>Mobile</th>
                                                    <th>Email</th>
                                                    <th>Registered Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <!-- No End Date Tab -->
                            <div class="tab-pane fade" id="no-end-date" role="tabpanel">
                                <!-- Filters -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Filter by Client/Landlord</label>
                                        <select class="form-select" id="ned_filter_client"></select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Filter by Property</label>
                                        <select class="form-select" id="ned_filter_property">
                                            <option value="">All Properties</option>
                                        </select>
                                    </div>
                                    <div class="col-md-12 d-flex align-items-end mt-2">
                                        <button type="button" class="btn btn-primary w-100" id="searchNoEndDateBtn">
                                            <span class="material-symbols-outlined" style="font-size:18px;">search</span>
                                            Search
                                        </button>
                                    </div>
                                </div>
                                <div class="position-relative">
                                    <div id="noEndDateLoader" class="text-center py-5" style="display:none;">
                                        <div class="spinner-border text-dark" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2 text-muted">Loading leases without end date...</p>
                                    </div>
                                    <div class="table-responsive custom-scrollbar" id="noEndDateTableContainer">
                                        <table id="noEndDateTable" class="display" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Tenant</th>
                                                    <th>Client</th>
                                                    <th>Property</th>
                                                    <th>Unit</th>
                                                    <th>Start Date</th>
                                                    <th>Status</th>
                                                    <th>Contract</th>
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

<!-- Bulk Renewal Modal -->
<div class="modal fade" id="renewalModal" tabindex="-1" aria-labelledby="renewalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="renewalModalLabel">Bulk Lease Renewal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="renewalForm">
                <div class="modal-body">
                    <div class="row mb-3">
                        <!-- Client Selection -->
                        <div class="col-md-12 mb-3">
                            <label for="renewal_client" class="form-label">Search Client/Landlord <span class="text-danger">*</span></label>
                            <select class="form-select" id="renewal_client" name="renewal_client" required>
                                <option value="">Search for a client...</option>
                            </select>
                            <small class="text-muted">Search and select a client first to see their properties</small>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Renewal Type <span class="text-danger">*</span></label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="renewal_type" id="renewal_property" value="property" checked>
                                    <label class="form-check-label" for="renewal_property">
                                        Entire Property
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="renewal_type" id="renewal_units" value="units">
                                    <label class="form-check-label" for="renewal_units">
                                        Select Units
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Property Selection -->
                        <div class="col-md-12 mb-3" id="property_selection">
                            <label for="property_id" class="form-label">Select Property <span class="text-danger">*</span></label>
                            <select class="form-select" id="property_id" name="property_id" required>
                                <option value="">Loading properties...</option>
                            </select>
                            <small class="text-muted">All active leases in this property will be renewed</small>
                        </div>

                        <!-- Units Selection -->
                        <div class="col-md-12 mb-3" id="units_selection" style="display:none;">
                            <label for="selected_property" class="form-label">Select Property First <span class="text-danger">*</span></label>
                            <select class="form-select mb-2" id="selected_property" name="selected_property">
                                <option value="">Choose a property...</option>
                            </select>
                            <label class="form-label mt-2">Select Units to Renew <span class="text-danger">*</span></label>
                            <div id="units_list" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                <p class="text-muted">Select a property to see available units</p>
                            </div>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Expiration Criteria <span class="text-danger">*</span></label>
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="expiring_within_days" class="form-label">Expiring Within (Days)</label>
                                    <input type="number" class="form-control" id="expiring_within_days" name="expiring_within_days" min="0" max="365" value="90" placeholder="e.g., 90">
                                    <small class="text-muted">Leases expiring in the next X days</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="expired_within_days" class="form-label">Already Expired By (Days)</label>
                                    <input type="number" class="form-control" id="expired_within_days" name="expired_within_days" min="0" max="365" value="0" placeholder="e.g., 30">
                                    <small class="text-muted">Leases expired within the last X days</small>
                                </div>
                            </div>
                            <div class="alert alert-info mt-2 mb-0" role="alert">
                                <strong>Examples:</strong>
                                <ul class="mb-0 mt-1" style="font-size: 0.9rem;">
                                    <li><strong>Expiring: 90, Expired: 30</strong> → Renews leases that expired in the last 30 days OR will expire in the next 90 days</li>
                                    <li><strong>Expiring: 30, Expired: 0</strong> → Only renews leases expiring in the next 30 days (no already-expired leases)</li>
                                    <li><strong>Expiring: 60, Expired: 60</strong> → Renews leases within a 120-day window (60 days past to 60 days future)</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="renewal_years" class="form-label">Duration (Years) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="renewal_years" name="years" min="1" max="10" value="1" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="renewal_escalation" class="form-label">Escalation Rate (%) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="renewal_escalation" name="escalation_rate" min="0" max="100" value="0" required>
                            <small class="text-muted">Applied to all invoiceable items</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="renewBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="renewSpinner"></span>
                        <span id="renewBtnText">Renew Leases</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Send Contracts Modal -->
<div class="modal fade" id="bulkSendModal" tabindex="-1" aria-labelledby="bulkSendModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkSendModalLabel">Send Lease Contracts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="bulkSendForm">
                <div class="modal-body">
                    <input type="hidden" id="bulkSendType" name="type">
                    <p class="text-muted mb-3" id="bulkSendDesc"></p>

                    <div class="mb-3">
                        <label for="bs_client" class="form-label">Filter by Client / Landlord <span class="text-muted fw-normal">(optional)</span></label>
                        <select class="form-select" id="bs_client" name="client_id">
                            <option value="">All Clients</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="bs_property" class="form-label">Filter by Property <span class="text-muted fw-normal">(optional)</span></label>
                        <select class="form-select" id="bs_property" name="property_id">
                            <option value="">All Properties</option>
                        </select>
                    </div>

                    <div class="alert alert-info mb-0 py-2">
                        <small><span class="material-symbols-outlined" style="font-size:15px;vertical-align:middle;">info</span>
                            Leaving filters blank will target <strong>all</strong> matching active leases across the portfolio.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="bulkSendSubmitBtn">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="bulkSendSpinner"></span>
                        <span id="bulkSendBtnText">Send Contracts</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        let propertiesData = [];

        // ── Lease Stats ───────────────────────────────────────────────
        function loadStats() {
            $.ajax({
                url: "{{ route('property.leases.stats') }}",
                method: 'GET',
                success: function(response) {
                    var d = response.data;
                    $('#stat-expiring').text(d.expiring_soon ?? 0);
                    $('#stat-expired').text(d.expired ?? 0);
                    $('#stat-signed').text(d.signed ?? 0);
                    $('#stat-sent-not-signed').text(d.sent_not_signed ?? 0);
                    $('#stat-not-sent').text(d.not_sent ?? 0);
                    $('#stat-no-end-date').text(d.no_end_date ?? 0);
                },
                error: function() {
                    $('.stat-value').text('—');
                }
            });
        }

        loadStats();

        // ── Bulk Send Modal ────────────────────────────────────────
        $('#bulkSendModal').on('show.bs.modal', function(e) {
            var type = $(e.relatedTarget).data('type');
            $('#bulkSendType').val(type);
            if (type === 'not_sent') {
                $('#bulkSendModalLabel').text('Send Lease Contracts');
                $('#bulkSendDesc').text('Send contracts to active leases that have not yet had a contract sent. Use the filters below to narrow down by client or property.');
                $('#bulkSendBtnText').text('Send Contracts');
            } else {
                $('#bulkSendModalLabel').text('Resend Lease Contracts');
                $('#bulkSendDesc').text('Resend contracts to active leases where the contract was sent but not yet signed. Use the filters below to narrow down by client or property.');
                $('#bulkSendBtnText').text('Resend Contracts');
            }

            // Init client select2
            $('#bs_client').select2({
                dropdownParent: $('#bulkSendModal'),
                placeholder: 'Search for a client/landlord...',
                allowClear: true,
                width: '100%',
                minimumInputLength: 2,
                ajax: {
                    url: "{{ route('ajax.clients.list') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            client_type: 'Landlord',
                            search: params.term
                        };
                    },
                    processResults: function(response) {
                        return {
                            results: response.data.map(function(c) {
                                return {
                                    id: c.id,
                                    text: c.names + ' (' + c.reference_number + ')'
                                };
                            })
                        };
                    },
                    cache: true
                }
            });

            // Init property select2
            $('#bs_property').select2({
                dropdownParent: $('#bulkSendModal'),
                placeholder: 'All Properties',
                allowClear: true,
                width: '100%'
            });

            // Reset property list
            $('#bs_property').html('<option value="">All Properties</option>').trigger('change');
        });

        // When client changes in bulk-send modal, reload properties
        $('#bs_client').on('change', function() {
            var clientId = $(this).val();
            $('#bs_property').html('<option value="">All Properties</option>').trigger('change');
            if (!clientId) return;

            $.ajax({
                url: "{{ route('property.properties.data') }}",
                method: 'GET',
                data: {
                    client_id: clientId
                },
                success: function(response) {
                    var opts = '<option value="">All Properties</option>';
                    if (response.data) {
                        response.data.forEach(function(p) {
                            opts += '<option value="' + p.id + '">' + p.property_name + ' (' + p.reference_number + ')</option>';
                        });
                    }
                    $('#bs_property').html(opts).trigger('change');
                }
            });
        });

        // Destroy select2 on modal close
        $('#bulkSendModal').on('hidden.bs.modal', function() {
            if ($('#bs_client').hasClass('select2-hidden-accessible')) $('#bs_client').select2('destroy');
            if ($('#bs_property').hasClass('select2-hidden-accessible')) $('#bs_property').select2('destroy');
            $('#bulkSendForm')[0].reset();
            $('#bs_client').html('<option value="">All Clients</option>');
            $('#bs_property').html('<option value="">All Properties</option>');
        });

        // Bulk-send form submit
        $('#bulkSendForm').on('submit', function(e) {
            e.preventDefault();
            var type = $('#bulkSendType').val();
            var clientId = $('#bs_client').val();
            var propertyId = $('#bs_property').val();

            $('#bulkSendSpinner').removeClass('d-none');
            $('#bulkSendSubmitBtn').prop('disabled', true);

            $.ajax({
                url: "{{ route('property.leases.bulk-send-contracts') }}",
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    type: type,
                    client_id: clientId || null,
                    property_id: propertyId || null
                },
                success: function(res) {
                    $('#bulkSendModal').modal('hide');
                    toastr.success(res.message);
                    loadStats();
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to send contracts.');
                },
                complete: function() {
                    $('#bulkSendSpinner').addClass('d-none');
                    $('#bulkSendSubmitBtn').prop('disabled', false);
                }
            });
        });
        // ─────────────────────────────────────────────────────────────

        // Initialize All Leases DataTable
        var allLeasesTable = $('#allLeasesTable').DataTable({
            processing: true,
            data: [],
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'tenant_name',
                    render: function(data, type, row) {
                        return '<small class="text-muted">' + row.ref_number + '</small><br>' + data;
                    }
                },
                {
                    data: 'property_name'
                },
                {
                    data: 'unit_code'
                },
                {
                    data: 'date_start',
                    render: function(data) {
                        return data ? moment(data).format('MMM D, YYYY') : 'N/A';
                    }
                },
                {
                    data: 'date_end',
                    render: function(data) {
                        return data ? moment(data).format('MMM D, YYYY') : 'N/A';
                    }
                },
                {
                    data: 'days_to_expiry',
                    render: function(data, type, row) {
                        if (row.is_expired) {
                            return `<span class="badge bg-danger">Expired ${Math.abs(data)} days ago</span>`;
                        } else if (row.is_expiring) {
                            let urgency = data <= 30 ? 'danger' : data <= 60 ? 'warning' : 'info';
                            return `<span class="badge bg-${urgency}">${data} days</span>`;
                        }
                        return `<span class="text-muted">${data} days</span>`;
                    }
                },
                {
                    data: 'current_status_id',
                    render: function(data, type, row) {
                        // Time-based status takes priority
                        if (row.is_expired) {
                            return '<span class="badge bg-danger">Expired</span>';
                        } else if (row.is_expiring) {
                            return '<span class="badge bg-warning">Expiring Soon</span>';
                        }

                        // Lease status IDs
                        switch (parseInt(data)) {
                            case 1:
                                return '<span class="badge bg-secondary">Pending</span>';
                            case 5:
                                return '<span class="badge bg-success">Approved</span>';
                            case 28:
                                return '<span class="badge bg-danger">Terminated</span>';
                            case 29:
                                return '<span class="badge bg-warning">Withdrawn</span>';
                            case 32:
                                return '<span class="badge bg-primary">Owner\'s Account</span>';
                            case 2:
                                return '<span class="badge bg-info">Renewed</span>';
                            default:
                                return '<span class="badge bg-secondary">Unknown</span>';
                        }
                    }
                },
                {
                    data: 'contract_status',
                    orderable: true,
                    searchable: false,
                    render: function(status, type, row) {
                        if (status === 'signed') {
                            return '<span class="badge badge-light-success"><i class="fa fa-check me-1"></i>Signed</span>';
                        } else if (status === 'sent') {
                            return '<span class="badge badge-light-warning"><i class="fa fa-envelope me-1"></i>Sent</span>';
                        }
                        return '<span class="badge badge-light-secondary">Not Sent</span>';
                    }
                },
                {
                    data: 'encrypted_tenant_id',
                    orderable: false,
                    searchable: false,
                    render: function(encryptedId, type, row) {
                        let sendBtn = row.contract_status !== 'signed' ?
                            `<button class="btn btn-sm btn-outline-primary send-contract-btn ms-1" data-id="${row.ref_number}" title="${row.contract_status === 'sent' ? 'Resend' : 'Send'} Contract"><i class="fa fa-paper-plane"></i></button>` :
                            '';
                        let viewContractBtn = row.has_signed_pdf ?
                            `<a href="/property/leases/${row.encrypted_id}/view-signed-contract" class="btn btn-sm btn-outline-primary ms-1" target="_blank" title="View Signed Contract"><i class="fa fa-file-pdf-o"></i></a>` :
                            '';
                        let editBtn = `<a href="/property/leases/${row.encrypted_id}/edit" class="btn btn-sm btn-outline-secondary ms-1" title="Edit Lease"><i class="fa fa-pencil"></i></a>`;
                        let cancelBtn = row.lease_status === 'Active' ?
                            `<button class="btn btn-sm btn-outline-danger ms-1 cancel-lease-btn" data-id="${row.id}" title="Cancel Lease"><i class="fa fa-ban"></i></button>` :
                            '';
                        return `
                            <a href="/tenants/${encryptedId}" class="text-primary" title="View Tenant">
                                <span class="material-symbols-outlined" style="font-size: 18px;">visibility</span>
                            </a>
                            ${sendBtn}${viewContractBtn}${editBtn}${cancelBtn}
                        `;
                    }
                }
            ],
            pageLength: 25,
            responsive: true,
            order: [
                [6, 'asc']
            ] // Sort by days to expiry
        });

        // Initialize Expiring Leases DataTable
        var expiringLeasesTable = $('#expiringLeasesTable').DataTable({
            processing: true,
            data: [],
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'ref_number'
                },
                {
                    data: 'tenant_name'
                },
                {
                    data: 'property_name'
                },
                {
                    data: 'unit_code'
                },
                {
                    data: 'date_end',
                    render: function(data) {
                        return data ? moment(data).format('MMM D, YYYY') : 'N/A';
                    }
                },
                {
                    data: 'days_to_expiry',
                    render: function(data) {
                        return `<strong>${data} days</strong>`;
                    }
                },
                {
                    data: 'urgency',
                    render: function(data) {
                        const badges = {
                            'CRITICAL': 'bg-danger',
                            'URGENT': 'bg-warning',
                            'MODERATE': 'bg-info',
                            'LOW': 'bg-secondary'
                        };
                        return `<span class="badge ${badges[data]}">${data}</span>`;
                    }
                },
                {
                    data: 'encrypted_tenant_id',
                    orderable: false,
                    searchable: false,
                    render: function(encryptedId, type, row) {
                        return `
                            <a href="/tenants/${encryptedId}" class="text-primary" title="View Tenant">
                                <span class="material-symbols-outlined" style="font-size: 18px;">visibility</span>
                            </a>
                        `;
                    }
                }
            ],
            pageLength: 25,
            responsive: true,
            order: [
                [6, 'asc']
            ] // Sort by days to expiry ascending
        });

        // Function to load leases data with filters
        function loadLeasesData(filters = {}) {
            // Show loading indicators
            $('#searchPrompt').hide();
            $('#allLeasesLoader').show();
            $('#allLeasesLoader .spinner-border').show();
            $('#allLeasesTableContainer').hide();

            $.ajax({
                url: "{{ route('property.leases.data') }}",
                method: 'GET',
                data: filters,
                success: function(response) {
                    allLeasesTable.clear();
                    allLeasesTable.rows.add(response.data);
                    allLeasesTable.draw();

                    // Hide loader and show table
                    $('#allLeasesLoader').hide();
                    $('#allLeasesTableContainer').show();

                    if (response.data.length === 0) {
                        toastr.info('No leases found for the selected filters');
                    }
                },
                error: function(xhr) {
                    console.error('Error loading leases:', xhr);
                    toastr.error('Failed to load leases data');

                    // Show error message
                    $('#allLeasesLoader .spinner-border').hide();
                    $('#searchPrompt').html('<p class="text-danger">Failed to load data. Please try again.</p>').show();
                }
            });
        }

        // Function to load expiring leases
        function loadExpiringLeases() {
            $('#expiringLeasesLoader').show();
            $('#expiringLeasesTableContainer').hide();

            $.ajax({
                url: "{{ route('property.leases.expiring') }}",
                method: 'GET',
                success: function(response) {
                    expiringLeasesTable.clear();
                    expiringLeasesTable.rows.add(response.data);
                    expiringLeasesTable.draw();

                    // Hide loader and show table
                    $('#expiringLeasesLoader').hide();
                    $('#expiringLeasesTableContainer').show();
                },
                error: function(xhr) {
                    console.error('Error loading expiring leases:', xhr);
                    toastr.error('Failed to load expiring leases');
                    $('#expiringLeasesLoader').html('<p class="text-danger">Failed to load data. Please refresh the page.</p>');
                }
            });
        }

        // Load expiring leases when tab is clicked
        $('#expiring-tab').one('click', function() {
            loadExpiringLeases();
        });

        // ── No End Date Tab ───────────────────────────────────────────
        var noEndDateTable = $('#noEndDateTable').DataTable({
            processing: true,
            data: [],
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'tenant_name',
                    render: function(data, type, row) {
                        return '<small class="text-muted">' + row.ref_number + '</small><br>' + data;
                    }
                },
                {
                    data: 'client_name'
                },
                {
                    data: 'property_name'
                },
                {
                    data: 'unit_code'
                },
                {
                    data: 'date_start',
                    render: function(data) {
                        return data ? moment(data).format('MMM D, YYYY') : 'N/A';
                    }
                },
                {
                    data: 'current_status_id',
                    render: function(data) {
                        switch (parseInt(data)) {
                            case 1:
                                return '<span class="badge bg-secondary">Pending</span>';
                            case 5:
                                return '<span class="badge bg-success">Approved</span>';
                            case 28:
                                return '<span class="badge bg-danger">Terminated</span>';
                            case 29:
                                return '<span class="badge bg-warning">Withdrawn</span>';
                            case 32:
                                return '<span class="badge bg-primary">Owner\'s Account</span>';
                            case 2:
                                return '<span class="badge bg-info">Renewed</span>';
                            default:
                                return '<span class="badge bg-secondary">Unknown</span>';
                        }
                    }
                },
                {
                    data: 'contract_status',
                    orderable: false,
                    searchable: false,
                    render: function(status) {
                        if (status === 'signed') return '<span class="badge badge-light-success"><i class="fa fa-check me-1"></i>Signed</span>';
                        if (status === 'sent') return '<span class="badge badge-light-warning"><i class="fa fa-envelope me-1"></i>Sent</span>';
                        return '<span class="badge badge-light-secondary">Not Sent</span>';
                    }
                },
                {
                    data: 'encrypted_tenant_id',
                    orderable: false,
                    searchable: false,
                    render: function(encryptedId, type, row) {
                        let viewContractBtn = row.has_signed_pdf ?
                            '<a href="/property/leases/' + row.encrypted_id + '/view-signed-contract" class="btn btn-sm btn-outline-primary ms-1" target="_blank" title="View Signed Contract"><i class="fa fa-file-pdf-o"></i></a>' :
                            '';
                        return '<a href="/tenants/' + encryptedId + '" class="text-primary" title="View Tenant"><span class="material-symbols-outlined" style="font-size:18px;">visibility</span></a>' + viewContractBtn;
                    }
                }
            ],
            pageLength: 25,
            responsive: true,
            order: [
                [5, 'desc']
            ]
        });

        function loadNoEndDateLeases(filters) {
            $('#noEndDateLoader').show();
            $('#noEndDateTableContainer').hide();
            $.ajax({
                url: "{{ route('property.leases.no-end-date') }}",
                method: 'GET',
                data: filters || {},
                success: function(response) {
                    noEndDateTable.clear().rows.add(response.data).draw();
                    $('#noEndDateLoader').hide();
                    $('#noEndDateTableContainer').show();
                },
                error: function() {
                    toastr.error('Failed to load leases without end date.');
                    $('#noEndDateLoader').hide();
                    $('#noEndDateTableContainer').show();
                }
            });
        }

        // Init filters for No End Date tab
        $('#no-end-date-tab').one('click', function() {
            // Init client select2
            $('#ned_filter_client').select2({
                placeholder: 'Search for a client/landlord...',
                allowClear: true,
                width: '100%',
                minimumInputLength: 2,
                ajax: {
                    url: "{{ route('ajax.clients.list') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            client_type: 'Landlord',
                            search: params.term
                        };
                    },
                    processResults: function(response) {
                        return {
                            results: response.data.map(function(c) {
                                return {
                                    id: c.id,
                                    text: c.names + ' (' + c.reference_number + ')'
                                };
                            })
                        };
                    },
                    cache: true
                }
            });

            $('#ned_filter_client').on('change', function() {
                var clientId = $(this).val();
                $('#ned_filter_property').html('<option value="">All Properties</option>').trigger('change');
                if (!clientId) return;
                $.ajax({
                    url: "{{ route('property.properties.data') }}",
                    data: {
                        client_id: clientId
                    },
                    success: function(res) {
                        var opts = '<option value="">All Properties</option>';
                        (res.data || []).forEach(function(p) {
                            opts += '<option value="' + p.id + '">' + p.property_name + ' (' + p.reference_number + ')</option>';
                        });
                        $('#ned_filter_property').html(opts);
                        if ($('#ned_filter_property').hasClass('select2-hidden-accessible')) {
                            $('#ned_filter_property').select2('destroy');
                        }
                        $('#ned_filter_property').select2({
                            placeholder: 'All Properties',
                            allowClear: true,
                            width: '100%'
                        });
                    }
                });
            });

            $('#ned_filter_property').select2({
                placeholder: 'All Properties',
                allowClear: true,
                width: '100%'
            });

            loadNoEndDateLeases();
        });

        $('#searchNoEndDateBtn').on('click', function() {
            var filters = {};
            var c = $('#ned_filter_client').val();
            var p = $('#ned_filter_property').val();
            if (c) filters.client_id = c;
            if (p) filters.property_id = p;
            loadNoEndDateLeases(filters);
        });
        // ─────────────────────────────────────────────────────────────

        // Load clients and properties for filters
        function loadFilterOptions() {
            // Initialize client dropdown with AJAX search
            $('#filter_client').select2({
                placeholder: 'Search for a client/landlord...',
                allowClear: true,
                width: '100%',
                minimumInputLength: 2,
                ajax: {
                    url: "{{ route('ajax.clients.list') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            client_type: 'Landlord',
                            search: params.term
                        };
                    },
                    processResults: function(response) {
                        return {
                            results: response.data.map(function(client) {
                                return {
                                    id: client.id,
                                    text: `${client.names} (${client.reference_number})`
                                };
                            })
                        };
                    },
                    cache: true
                }
            });

            // Load all properties initially
            loadProperties();
        }

        // Load properties (optionally filtered by client)
        function loadProperties(clientId = null) {
            let url = "{{ route('property.properties.data') }}";
            let requestData = {};

            // If client is selected, filter properties by client
            if (clientId) {
                requestData.client_id = clientId;
            }

            $.ajax({
                url: url,
                method: 'GET',
                data: requestData,
                success: function(response) {
                    let options = '<option value="">All Properties</option>';
                    if (response.data && response.data.length > 0) {
                        response.data.forEach(function(property) {
                            // Only show properties that belong to the selected client
                            if (!clientId || property.client_id == clientId) {
                                options += `<option value="${property.id}">${property.property_name} (${property.reference_number})</option>`;
                            }
                        });
                    }
                    $('#filter_property').html(options);

                    // Reinitialize or update select2
                    if ($('#filter_property').hasClass('select2-hidden-accessible')) {
                        $('#filter_property').select2('destroy');
                    }
                    $('#filter_property').select2({
                        placeholder: clientId ? 'Properties for selected client' : 'All Properties',
                        allowClear: true,
                        width: '100%'
                    });
                },
                error: function(xhr) {
                    console.error('Error loading properties:', xhr);
                }
            });
        }

        // Load filter options on page load
        loadFilterOptions();

        // When client changes, reload properties filtered by that client
        $('#filter_client').on('change', function() {
            const clientId = $(this).val();

            // Clear property selection
            $('#filter_property').val('').trigger('change');

            // Reload properties filtered by client (or all if cleared)
            loadProperties(clientId || null);
        });

        // Search button click handler
        $('#searchLeasesBtn').on('click', function() {
            const filters = {};

            const clientId = $('#filter_client').val();
            const propertyId = $('#filter_property').val();

            if (clientId) filters.client_id = clientId;
            if (propertyId) filters.property_id = propertyId;

            if (!clientId && !propertyId) {
                toastr.warning('Please select at least one filter (Client or Property)');
                return;
            }

            loadLeasesData(filters);
        });

        // Send / Resend Contract button
        $(document).on('click', '.send-contract-btn', function() {
            const id = $(this).data('id');
            const btn = $(this);
            if (!confirm('Send the lease contract to this tenant for digital signing?')) return;

            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

            $.ajax({
                url: '/property/leases/' + id + '/send-contract',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(res) {
                    toastr.success(res.message);
                    // Refresh table
                    loadLeasesData({
                        client_id: $('#filter_client').val(),
                        property_id: $('#filter_property').val()
                    });
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to send contract.');
                    btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i>');
                }
            });
        });

        // Cancel Lease button
        $(document).on('click', '.cancel-lease-btn', function() {
            const id = $(this).data('id');
            const btn = $(this);
            if (!confirm('Cancel this lease? This will end it immediately and mark the unit as vacant.')) return;

            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

            $.ajax({
                url: '/property/leases/' + id + '/cancel',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(res) {
                    toastr.success(res.message);
                    loadLeasesData({
                        client_id: $('#filter_client').val(),
                        property_id: $('#filter_property').val()
                    });
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.error || 'Failed to cancel lease.');
                    btn.prop('disabled', false).html('<i class="fa fa-ban"></i>');
                }
            });
        });

        // Initialize Tenants Without Leases DataTable
        var noLeaseTable = $('#noLeaseTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ route('property.leases.tenants-without') }}",
                type: 'GET',
                dataSrc: 'data',
                beforeSend: function() {
                    $('#noLeaseLoader').show();
                    $('#noLeaseTableContainer').hide();
                },
                complete: function() {
                    $('#noLeaseLoader').hide();
                    $('#noLeaseTableContainer').show();
                },
                error: function() {
                    $('#noLeaseLoader').html('<p class="text-danger">Failed to load data. Please refresh the page.</p>');
                }
            },
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'names'
                },
                {
                    data: 'reference_number'
                },
                {
                    data: 'mobile_number'
                },
                {
                    data: 'email'
                },
                {
                    data: 'created_at',
                    render: function(data) {
                        return data ? moment(data).format('MMM D, YYYY') : 'N/A';
                    }
                },
                {
                    data: 'encrypted_id',
                    orderable: false,
                    searchable: false,
                    render: function(encryptedId) {
                        return `
                            <a href="/tenants/${encryptedId}" class="text-primary me-2" title="View Tenant">
                                <span class="material-symbols-outlined" style="font-size: 18px;">visibility</span>
                            </a>
                            <a href="/tenants/lease/create?client=${encryptedId}" class="text-success" title="Create Lease">
                                <span class="material-symbols-outlined" style="font-size: 18px;">add_circle</span>
                            </a>
                        `;
                    }
                }
            ],
            pageLength: 25,
            responsive: true,
            order: [
                [5, 'desc']
            ] // Sort by created date descending
        });

        // Load properties for renewal modal (filtered by client)
        function loadRenewalProperties(clientId = null) {
            if (!clientId) {
                $('#property_id').html('<option value="">Select a client first...</option>');
                $('#selected_property').html('<option value="">Select a client first...</option>');
                return;
            }

            $.ajax({
                url: "{{ route('property.leases.properties-units') }}",
                method: 'GET',
                data: {
                    client_id: clientId
                },
                success: function(response) {
                    // Filter properties by selected client
                    propertiesData = response.properties.filter(function(property) {
                        return property.client_id == clientId;
                    });

                    let propertyOptions = '<option value="">Select a property...</option>';
                    if (propertiesData.length > 0) {
                        propertiesData.forEach(function(property) {
                            propertyOptions += `<option value="${property.id}">${property.property_name} (${property.reference_number})</option>`;
                        });
                    } else {
                        propertyOptions = '<option value="">No properties found for this client</option>';
                    }

                    $('#property_id').html(propertyOptions);
                    $('#selected_property').html(propertyOptions);

                    // Reinitialize select2 on both property dropdowns
                    if ($('#property_id').hasClass('select2-hidden-accessible')) {
                        $('#property_id').select2('destroy');
                    }
                    if ($('#selected_property').hasClass('select2-hidden-accessible')) {
                        $('#selected_property').select2('destroy');
                    }

                    $('#property_id').select2({
                        dropdownParent: $('#renewalModal'),
                        placeholder: 'Select a property...',
                        allowClear: true,
                        width: '100%'
                    });

                    $('#selected_property').select2({
                        dropdownParent: $('#renewalModal'),
                        placeholder: 'Choose a property...',
                        allowClear: true,
                        width: '100%'
                    });
                },
                error: function(xhr) {
                    console.error('Error loading properties:', xhr);
                    toastr.error('Failed to load properties');
                }
            });
        }

        // Initialize client search in renewal modal
        $('#renewalModal').on('show.bs.modal', function() {
            // Initialize client dropdown with AJAX search
            $('#renewal_client').select2({
                dropdownParent: $('#renewalModal'),
                placeholder: 'Search for a client/landlord...',
                allowClear: true,
                width: '100%',
                minimumInputLength: 2,
                ajax: {
                    url: "{{ route('ajax.clients.list') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            client_type: 'Landlord',
                            search: params.term
                        };
                    },
                    processResults: function(response) {
                        return {
                            results: response.data.map(function(client) {
                                return {
                                    id: client.id,
                                    text: `${client.names} (${client.reference_number})`
                                };
                            })
                        };
                    },
                    cache: true
                }
            });

            // Initialize property dropdowns with placeholder
            $('#property_id').html('<option value="">Select a client first...</option>');
            $('#selected_property').html('<option value="">Select a client first...</option>');
        });

        // When client changes in renewal modal, reload properties
        $(document).on('change', '#renewal_client', function() {
            const clientId = $(this).val();

            // Clear property selections
            $('#property_id').val('').trigger('change');
            $('#selected_property').val('').trigger('change');
            $('#units_list').html('<p class="text-muted">Select a property to see available units</p>');

            // Load properties for selected client
            if (clientId) {
                loadRenewalProperties(clientId);
            } else {
                $('#property_id').html('<option value="">Select a client first...</option>');
                $('#selected_property').html('<option value="">Select a client first...</option>');
            }
        });

        // Destroy select2 on modal close to prevent memory leaks
        $('#renewalModal').on('hidden.bs.modal', function() {
            if ($('#renewal_client').hasClass('select2-hidden-accessible')) {
                $('#renewal_client').select2('destroy');
            }
            if ($('#property_id').hasClass('select2-hidden-accessible')) {
                $('#property_id').select2('destroy');
            }
            if ($('#selected_property').hasClass('select2-hidden-accessible')) {
                $('#selected_property').select2('destroy');
            }

            // Reset form
            $('#renewal_client').val('').html('<option value="">Search for a client...</option>');
            $('#property_id').html('<option value="">Select a client first...</option>');
            $('#selected_property').html('<option value="">Select a client first...</option>');
            $('#units_list').html('<p class="text-muted">Select a property to see available units</p>');
        });

        // Toggle between property and units selection
        $('input[name="renewal_type"]').on('change', function() {
            if ($(this).val() === 'property') {
                $('#property_selection').show();
                $('#units_selection').hide();
                $('#property_id').prop('required', true);
            } else {
                $('#property_selection').hide();
                $('#units_selection').show();
                $('#property_id').prop('required', false);
            }
        });

        // Load units when property selected
        $('#selected_property').on('change', function() {
            const propertyId = $(this).val();
            const property = propertiesData.find(p => p.id == propertyId);

            if (property && property.units.length > 0) {
                let unitsHtml = '';
                property.units.forEach(function(unit) {
                    unitsHtml += `
                        <div class="form-check mb-2">
                            <input class="form-check-input unit-checkbox" type="checkbox" name="unit_ids[]" value="${unit.id}" id="unit_${unit.id}">
                            <label class="form-check-label" for="unit_${unit.id}">
                                <strong>${unit.unit_code}</strong> - Lease: ${unit.lease_ref} (Expires: ${moment(unit.lease_end_date).format('MMM D, YYYY')})
                            </label>
                        </div>
                    `;
                });

                $('#units_list').html(unitsHtml);
            } else {
                $('#units_list').html('<p class="text-muted">No units with active leases found in this property</p>');
            }
        });

        // Handle renewal form submission
        $('#renewalForm').on('submit', function(e) {
            e.preventDefault();

            const renewalType = $('input[name="renewal_type"]:checked').val();
            const formData = {
                renewal_type: renewalType,
                years: $('#renewal_years').val(),
                escalation_rate: $('#renewal_escalation').val(),
                expiring_within_days: $('#expiring_within_days').val() || 90,
                expired_within_days: $('#expired_within_days').val() || 0,
            };

            if (renewalType === 'property') {
                formData.property_id = $('#property_id').val();
                if (!formData.property_id) {
                    toastr.error('Please select a property');
                    return;
                }
            } else {
                const selectedUnits = $('.unit-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();

                if (selectedUnits.length === 0) {
                    toastr.error('Please select at least one unit');
                    return;
                }

                formData.unit_ids = selectedUnits;
            }

            // Show spinner
            $('#renewSpinner').removeClass('d-none');
            $('#renewBtn').prop('disabled', true);
            $('#renewBtnText').text('Processing...');

            $.ajax({
                url: "{{ route('property.leases.bulk-renew') }}",
                method: 'POST',
                data: formData,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('#renewalModal').modal('hide');
                    toastr.success(response.message);

                    // Reload all tables with single data fetch
                    loadLeasesData();
                    noLeaseTable.ajax.reload();
                },
                error: function(xhr) {
                    const error = xhr.responseJSON;
                    toastr.error(error.message || 'Failed to renew leases');
                    console.error('Renewal error:', xhr);
                },
                complete: function() {
                    $('#renewSpinner').addClass('d-none');
                    $('#renewBtn').prop('disabled', false);
                    $('#renewBtnText').text('Renew Leases');
                }
            });
        });
    });
</script>
@endsection