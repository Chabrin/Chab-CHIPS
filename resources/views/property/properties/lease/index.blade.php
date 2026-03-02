@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Lease Management</h5>
                        @permission('create_lease_agreements')
                        <div class="ms-auto d-flex gap-2">
                            <button type="button" class="btn btn-success btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#renewalModal">
                                <span class="material-symbols-outlined">autorenew</span>
                                <span class="ms-1">Bulk Renewal</span>
                            </button>
                            <a href="{{ route('tenant.lease.create') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">New Lease</span>
                            </a>
                        </div>
                        @endpermission
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

                        <!-- Nav tabs -->
                        <ul class="nav nav-tabs mb-3" id="leaseTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="all-leases-tab" data-bs-toggle="tab" data-bs-target="#all-leases" type="button">
                                    <span class="material-symbols-outlined" style="font-size:18px;">description</span>
                                    All Leases
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="expiring-tab" data-bs-toggle="tab" data-bs-target="#expiring" type="button">
                                    <span class="material-symbols-outlined" style="font-size:18px;">schedule</span>
                                    Expiring Soon (90 Days)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="no-lease-tab" data-bs-toggle="tab" data-bs-target="#no-lease" type="button">
                                    <span class="material-symbols-outlined" style="font-size:18px;">warning</span>
                                    Tenants Without Leases
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
                                                    <th>Lease Ref</th>
                                                    <th>Tenant</th>
                                                    <th>Property</th>
                                                    <th>Unit</th>
                                                    <th>Start Date</th>
                                                    <th>End Date</th>
                                                    <th>Days to Expiry</th>
                                                    <th>Status</th>
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
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="renewBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="renewSpinner"></span>
                        <span id="renewBtnText">Renew Leases</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        let propertiesData = [];
        
        // Initialize All Leases DataTable
        var allLeasesTable = $('#allLeasesTable').DataTable({
            processing: true,
            data: [],
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                { data: 'ref_number' },
                { data: 'tenant_name' },
                { data: 'property_name' },
                { data: 'unit_code' },
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
                        switch(parseInt(data)) {
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
            order: [[7, 'asc']] // Sort by days to expiry
        });

        // Initialize Expiring Leases DataTable
        var expiringLeasesTable = $('#expiringLeasesTable').DataTable({
            processing: true,
            data: [],
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                { data: 'ref_number' },
                { data: 'tenant_name' },
                { data: 'property_name' },
                { data: 'unit_code' },
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
            order: [[6, 'asc']] // Sort by days to expiry ascending
        });
        
        // Function to load leases data with filters
        function loadLeasesData(filters = {}) {
            // Show loading indicators
            $('#searchPrompt').hide();
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
                    $('#allLeasesLoader .spinner-border').hide();
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
                            results: response.clients.map(function(client) {
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
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                { data: 'names' },
                { data: 'reference_number' },
                { data: 'mobile_number' },
                { data: 'email' },
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
            order: [[5, 'desc']] // Sort by created date descending
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
                data: { client_id: clientId },
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
                            results: response.clients.map(function(client) {
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
