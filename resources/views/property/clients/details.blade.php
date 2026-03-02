@php
use Illuminate\Support\Facades\Crypt;
@endphp
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
                            @permission('update_clients')
                            <a href="/property/clients/{{ Crypt::encrypt($client->id) }}/edit" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">edit_document</span>
                                <span class="ms-1">Edit Client</span>
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

                        <!-- Summary Header -->
                        <div class="mb-3 d-flex flex-row justify-content-between align-items-center">
                            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                                <strong>Ref #:</strong> {{ $client->reference_number ?? 'N/A' }} |
                                <strong>National ID:</strong> {{ $client->national_id ?? '-' }} |
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
                                        @include('property.clients.include.menu')
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Tabs Navigation -->
                        <ul class="nav nav-tabs mb-4" id="clientTabs" role="tablist">
                            @include('property.clients.include.tabs')
                        </ul>

                        <!-- Tabs Content -->
                        <div class="tab-content" id="clientTabsContent">

                            <!-- Profile Information Tab -->
                            @permission('read_clients')
                            @include('property.clients.include.profile')
                            @endpermission

                            <!-- Statistics Tab -->
                            @permission('read_clients')
                            @include('property.clients.include.stats')
                            @endpermission

                            <!-- Properties Tab (AJAX Load) -->
                            @permission('read_properties')
                            @include('property.clients.include.properties')
                            @endpermission

                            <!-- SLA Tab (AJAX Load) -->
                            @permission('read_service_agreements')
                            @include('property.clients.include.sla')
                            @endpermission

                            <!-- Units Tab (AJAX Load) -->
                            @permission('read_units')
                            @include('property.clients.include.units')
                            @endpermission

                            <!-- Tenants Tab (AJAX Load) -->
                            @permission('read_tenants')
                            @include('property.clients.include.tenants')
                            @endpermission

                            <!-- Leases Tab (AJAX Load) -->
                            @permission('read_lease_agreements')
                            @include('property.clients.include.leases')
                            @endpermission

                            <!-- Recoveries Tab (AJAX Load) -->
                            @permission('read_recoveries')
                            @include('property.clients.include.recoveries')
                            @endpermission

                            <!-- Inspections Tab (AJAX Load) -->
                            @permission('read_inspections')
                            @include('property.clients.include.inspections')
                            @endpermission

                            <!-- Repairs & Maintenance Tab (AJAX Load) -->
                            @permission('read_repair_requests')
                            @include('property.clients.include.repairs')
                            @endpermission

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
                                                    @permission('create_properties')
                                                    <option value="properties">Properties</option>
                                                    @endpermission
                                                    @permission('create_units')
                                                    <option value="units">Units</option>
                                                    @endpermission
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

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        var propertiesTable = null;
        var unitsTable = null;
        var tenantsTable = null;
        var leasesTable = null;
        var propertiesLoaded = false;
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
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        // View button
                        buttons += `
                            <a href="/property/properties/${row.encrypted_id}/edit" title="Edit">
                                <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
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
                emptyTable: "Please select a property to view units"
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
            columns: [
                {
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
                            <a href="/property/sla/${row.encrypted_id}/show" title="View Details">
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

        // Load properties when tab is clicked
        $('#properties-tab').on('click', function() {
            if (!propertiesLoaded) {
                loadProperties();
                propertiesLoaded = true;
            }
        });

        // Load properties dropdown when units tab is clicked
        $('#units-tab').on('click', function() {
            if (!propertiesDropdownLoaded) {
                loadPropertiesDropdown();
                propertiesDropdownLoaded = true;
            }
        });

        // Load units when property is selected
        $('#propertySelect').on('change', function() {
            var propertyId = $(this).val();
            if (propertyId) {
                loadUnits(propertyId);
            } else {
                unitsTable.clear().draw();
            }
        });

        // Initialize Bootstrap dropdowns (if needed)
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'))
        var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
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

        function loadTenants(propertyId) {
            // Show spinner
            $('#tenantSpinner').show();

            $.ajax({
                url: "{{ route('property.tenants.get') }}",
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
            columns: [
                {
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
                    data: 'property_name'
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
    });
</script>
@endsection