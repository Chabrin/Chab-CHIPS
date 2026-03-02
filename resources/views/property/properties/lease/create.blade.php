@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Add New Lease Agreement</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="leaseAgreementForm" class="btn btn-primary btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Save</span>
                            </button>
                            <a href="{{ $client ? route('property.clients.show', $client->encrypted_id) : route('property.properties.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back</span>
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

                        <form action="#" method="POST" id="leaseAgreementForm" enctype="multipart/form-data">
                            @csrf

                            <!-- Lease Agreements Section -->
                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">Lease Agreements</h6>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="client_name" class="form-label">Client/Landlord <span class="text-danger">*</span></label>
                                    @if($client)
                                        <!-- Pre-filled client (coming from client details page) -->
                                        <div class="input-group">
                                            <input type="text" class="form-control @error('client_name') is-invalid @enderror" id="client_name" name="client_name" value="{{ $client->names }}" required readonly>
                                            <input type="hidden" id="client_id" name="client_id" value="{{ $client->id }}">
                                            @error('client_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    @else
                                        <!-- Client search dropdown (when no client is pre-selected) -->
                                        <select class="form-select @error('client_id') is-invalid @enderror" id="client_search" name="client_id" required>
                                            <option value="">Search for a client...</option>
                                        </select>
                                        @error('client_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="text-muted">Type at least 2 characters to search</small>
                                    @endif
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                    <select class="form-select @error('property_id') is-invalid @enderror" id="property_id" name="property_id" required>
                                        <option value="">{{ $client ? 'Loading properties...' : 'Select a client first' }}</option>
                                    </select>
                                    @error('property_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="sla_ref" class="form-label">SLA Ref <span class="text-danger">*</span></label>
                                    <select class="form-select @error('sla_ref') is-invalid @enderror" id="sla_ref" name="sla_ref" required>
                                        <option value="">Please select</option>
                                    </select>
                                    @error('sla_ref')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="unit_id" class="form-label">Unit <span class="text-danger">*</span></label>
                                    <select class="form-select @error('unit_id') is-invalid @enderror" id="unit_id" name="unit_id" required>
                                        <option value="">Please select</option>
                                    </select>
                                    @error('unit_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="tenant_name" class="form-label">Tenant <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control @error('tenant_name') is-invalid @enderror" 
                                               id="tenant_name" name="tenant_name" 
                                               value="{{ old('tenant_name') }}" required readonly>
                                        <input type="hidden" id="tenant_id" name="tenant_id" value="{{ old('tenant_id') }}">
                                        @error('tenant_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="linked_lease" class="form-label">Linked Lease</label>
                                    <select class="form-select @error('linked_lease') is-invalid @enderror" id="linked_lease" name="linked_lease">
                                        <option value="">Please select</option>
                                    </select>
                                    @error('linked_lease')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="date_start" class="form-label">Date Start <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('date_start') is-invalid @enderror" 
                                           id="date_start" name="date_start" 
                                           value="{{ old('date_start') }}" required>
                                    @error('date_start')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="years" class="form-label">Years <span class="text-danger">*</span></label>
                                    <select class="form-select @error('years') is-invalid @enderror" id="years" name="years" required>
                                        <option value="">Please select</option>
                                        <option value="1" {{ old('years') == '1' ? 'selected' : 'selected' }}>1</option>
                                        <option value="2" {{ old('years') == '2' ? 'selected' : '' }}>2</option>
                                        <option value="3" {{ old('years') == '3' ? 'selected' : '' }}>3</option>
                                        <option value="4" {{ old('years') == '4' ? 'selected' : '' }}>4</option>
                                        <option value="5" {{ old('years') == '5' ? 'selected' : '' }}>5</option>
                                        <option value="6" {{ old('years') == '6' ? 'selected' : '' }}>6</option>
                                        <option value="7" {{ old('years') == '7' ? 'selected' : '' }}>7</option>
                                        <option value="8" {{ old('years') == '8' ? 'selected' : '' }}>8</option>
                                        <option value="9" {{ old('years') == '9' ? 'selected' : '' }}>9</option>
                                        <option value="10" {{ old('years') == '10' ? 'selected' : '' }}>10</option>
                                    </select>
                                    @error('years')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="auto_renewal" class="form-label">Auto-Renewal <span class="text-danger">*</span></label>
                                    <select class="form-select @error('auto_renewal') is-invalid @enderror" id="auto_renewal" name="auto_renewal" required>
                                        <option value="Yes" {{ old('auto_renewal') == 'Yes' ? 'selected' : 'selected' }}>Yes</option>
                                        <option value="No" {{ old('auto_renewal') == 'No' ? 'selected' : '' }}>No</option>
                                    </select>
                                    @error('auto_renewal')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="escalation_rate" class="form-label">Escalation Rate <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control @error('escalation_rate') is-invalid @enderror" 
                                           id="escalation_rate" name="escalation_rate" 
                                           value="{{ old('escalation_rate', '7.5') }}" required>
                                    @error('escalation_rate')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="escalation_frequency" class="form-label">Escalation Frequency</label>
                                    <select class="form-select @error('escalation_frequency') is-invalid @enderror" id="escalation_frequency" name="escalation_frequency">
                                        <option value="4">Annually</option>
                                        <option value="6">Biennially</option>
                                    </select>
                                    @error('escalation_frequency')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="documents" class="form-label">Documents</label>
                                    <div class="input-group">
                                        <input type="file" class="form-control @error('documents') is-invalid @enderror" 
                                               id="documents" name="documents[]" multiple accept=".pdf,.doc,.docx">
                                        <button type="button" class="btn btn-outline-secondary" id="addFilesBtn">
                                            <span class="material-symbols-outlined" style="font-size:18px;">add</span>
                                            Add files
                                        </button>
                                        @error('documents')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Invoicable Items Section -->
                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">Invoicable Items</h6>
                                </div>

                                <div class="col-12 mb-3">
                                    <button type="button" class="btn btn-success btn-sm d-flex align-items-center" id="addInvoicableItem">
                                        <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                        Add Item
                                    </button>
                                </div>

                                <div id="invoicableItemsContainer">
                                    <div class="invoicable-item-entry mb-3 p-3 border rounded">
                                        <div class="row align-items-end">
                                            <div class="col-md-4 mb-3">
                                                <label for="item_0" class="form-label">Item <span class="text-danger">*</span></label>
                                                <select class="form-select" id="item_0" name="invoicable_items[0][item]" required>
                                                </select>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <label for="amount_0" class="form-label">Amount <span class="text-danger">*</span></label>
                                                <input type="number" step="0.01" class="form-control" 
                                                       id="amount_0" name="invoicable_items[0][amount]" 
                                                       value="0.00" required>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <label for="cycle_0" class="form-label">Cycle <span class="text-danger">*</span></label>
                                                <select class="form-select" id="cycle_0" name="invoicable_items[0][cycle]" required>
                                                    <option value="">Please select</option>
                                                    <option value="4">Annually</option>
                                                    <option value="5">Daily</option>
                                                    <option value="2">Monthly</option>
                                                    <option value="1">One time</option>
                                                    <option value="3">Quarterly</option>
                                                </select>
                                            </div>

                                            <div class="col-md-3 mb-3">
                                                <label class="form-label d-block">Prepay Client</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="prepay_client_0" name="invoicable_items[0][prepay_client]" value="Yes">
                                                    <label class="form-check-label" for="prepay_client_0">Yes</label>
                                                </div>
                                            </div>

                                            <div class="d-flex gap-1">
                                                <button type="button" class="btn btn-success btn-sm add-item-inline d-none">
                                                    <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                                </button>
                                                <button type="button" class="btn btn-light btn-sm remove-invoicable-item d-none">
                                                    <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                                                </button>
                                            </div>
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


<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Form submission with spinner
        $('#leaseAgreementForm').on('submit', function(e) {
            $('#submitSpinner').removeClass('d-none');
            $('#saveButton').prop('disabled', true);
        });

        // Check if client is pre-filled or needs to be searched
        @if($client)
            // Client is pre-filled, load properties immediately
            const clientId = $('#client_id').val();
            if (clientId) {
                loadProperties(clientId);
            }
        @else
            // No client pre-filled, initialize search dropdown
            $('#client_search').select2({
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
            
            // When client is selected from search, load their properties
            $('#client_search').on('change', function() {
                const selectedClientId = $(this).val();
                
                if (selectedClientId) {
                    loadProperties(selectedClientId);
                } else {
                    // Reset dependent fields
                    $('#property_id').html('<option value="">Select a client first</option>').prop('disabled', true);
                    $('#sla_ref').html('<option value="">Please select</option>').prop('disabled', true);
                    $('#unit_id').html('<option value="">Please select</option>').prop('disabled', true);
                    $('#tenant_name').val('');
                    $('#tenant_id').val('');
                }
            });
        @endif

        // Function to load properties for the client
        function loadProperties(clientId) {
            $('#property_id').html('<option value="">Loading properties...</option>').prop('disabled', true);
            
            $.ajax({
                url: `/client/${clientId}/properties`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Properties loaded:', response);
                    $('#property_id').empty().prop('disabled', false);
                    $('#property_id').append('<option value="">Please select</option>');
                    
                    if (response.properties && response.properties.length > 0) {
                        response.properties.forEach(function(property) {
                            $('#property_id').append(`<option value="${property.id}">${property.property_name}</option>`);
                        });
                    } else {
                        $('#property_id').append('<option value="">No properties found for this client</option>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading properties:', xhr.responseText);
                    $('#property_id').empty().prop('disabled', false);
                    $('#property_id').append('<option value="">Error loading properties</option>');
                }
            });
        }

        // Load SLA and Units when property is selected
        $('#property_id').on('change', function() {
            const propertyId = $(this).val();
            
            if (propertyId) {
                loadSLAForProperty(propertyId);
                loadUnitsForProperty(propertyId);
            } else {
                // Reset dependent fields
                $('#sla_ref').html('<option value="">Please select</option>').prop('disabled', false);
                $('#unit_id').html('<option value="">Please select</option>').prop('disabled', false);
                $('#tenant_name').val('');
                $('#tenant_id').val('');
            }
        });

        // Function to load SLA for the selected property
        function loadSLAForProperty(propertyId) {
            $('#sla_ref').html('<option value="">Loading SLA...</option>').prop('disabled', true);
            
            $.ajax({
                url: `/property/${propertyId}/sla`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('SLA loaded:', response);
                    $('#sla_ref').empty().prop('disabled', false);
                    $('#sla_ref').append('<option value="">Please select</option>');
                    
                    if (response.sla && response.sla.length > 0) {
                        response.sla.forEach(function(sla) {
                            $('#sla_ref').append(`<option value="${sla.id}">${sla.sla_reference || sla.reference_number}</option>`);
                        });
                    } else {
                        $('#sla_ref').append('<option value="">No SLA found for this property</option>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading SLA:', xhr.responseText);
                    $('#sla_ref').empty().prop('disabled', false);
                    $('#sla_ref').append('<option value="">Error loading SLA</option>');
                }
            });
        }

        // Function to load units for the selected property
        function loadUnitsForProperty(propertyId) {
            $('#unit_id').html('<option value="">Loading units...</option>').prop('disabled', true);
            
            $.ajax({
                url: `/property/${propertyId}/units`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Units loaded:', response);
                    $('#unit_id').empty().prop('disabled', false);
                    $('#unit_id').append('<option value="">Please select</option>');
                    
                    if (response.units && response.units.length > 0) {
                        response.units.forEach(function(unit) {
                            $('#unit_id').append(`<option value="${unit.id}">${unit.unit_name || unit.unit_number}</option>`);
                        });
                    } else {
                        $('#unit_id').append('<option value="">No units found for this property</option>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading units:', xhr.responseText);
                    $('#unit_id').empty().prop('disabled', false);
                    $('#unit_id').append('<option value="">Error loading units</option>');
                }
            });
        }

        // Load tenant when unit is selected
        $('#unit_id').on('change', function() {
            const unitId = $(this).val();
            const propertyId = $('#property_id').val();
            
            if (unitId && propertyId) {
                loadTenantForUnit(propertyId, unitId);
            } else {
                // Reset tenant field
                $('#tenant_name').val('');
                $('#tenant_id').val('');
            }
        });

        // Function to load tenant for the selected unit
        function loadTenantForUnit(propertyId, unitId) {
            $.ajax({
                url: `/property/${propertyId}/unit/${unitId}/tenant`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Tenant loaded:', response);
                    
                    if (response.tenant) {
                        $('#tenant_id').val(response.tenant.id);
                        $('#tenant_name').val(response.tenant.full_name || response.tenant.name);
                    } else {
                        $('#tenant_name').val('');
                        $('#tenant_id').val('');
                        console.log('No tenant found for this unit');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading tenant:', xhr.responseText);
                    $('#tenant_name').val('');
                    $('#tenant_id').val('');
                }
            });
        }

        // Client Search Modal
        $('#searchClientBtn').on('click', function() {
            $('#clientSearchModal').modal('show');
        });

        $('#clientSearchInput').on('keyup', function() {
            let searchTerm = $(this).val();
            if (searchTerm.length > 2) {
                // AJAX search for clients
                $.ajax({
                    url: '/api/clients/search',
                    method: 'GET',
                    data: { term: searchTerm },
                    success: function(response) {
                        let resultsHtml = '<div class="list-group">';
                        response.clients.forEach(function(client) {
                            resultsHtml += `
                                <a href="#" class="list-group-item list-group-item-action select-client" 
                                   data-client-id="${client.id}" 
                                   data-client-name="${client.full_name}">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">${client.full_name}</h6>
                                        <small>${client.client_number}</small>
                                    </div>
                                    <p class="mb-1">${client.email || ''}</p>
                                    <small>${client.phone || ''}</small>
                                </a>
                            `;
                        });
                        resultsHtml += '</div>';
                        $('#clientSearchResults').html(resultsHtml);
                    }
                });
            }
        });

        $(document).on('click', '.select-client', function(e) {
            e.preventDefault();
            let clientId = $(this).data('client-id');
            let clientName = $(this).data('client-name');
            $('#client_id').val(clientId);
            $('#client_name').val(clientName);
            $('#clientSearchModal').modal('hide');
        });

        // Tenant Search Modal
        $('#searchTenantBtn').on('click', function() {
            $('#tenantSearchModal').modal('show');
        });

        $('#tenantSearchInput').on('keyup', function() {
            let searchTerm = $(this).val();
            if (searchTerm.length > 2) {
                // AJAX search for tenants
                $.ajax({
                    url: '/api/tenants/search',
                    method: 'GET',
                    data: { term: searchTerm },
                    success: function(response) {
                        let resultsHtml = '<div class="list-group">';
                        response.tenants.forEach(function(tenant) {
                            resultsHtml += `
                                <a href="#" class="list-group-item list-group-item-action select-tenant" 
                                   data-tenant-id="${tenant.id}" 
                                   data-tenant-name="${tenant.full_name}">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">${tenant.full_name}</h6>
                                        <small>${tenant.tenant_number}</small>
                                    </div>
                                    <p class="mb-1">${tenant.email || ''}</p>
                                    <small>${tenant.phone || ''}</small>
                                </a>
                            `;
                        });
                        resultsHtml += '</div>';
                        $('#tenantSearchResults').html(resultsHtml);
                    }
                });
            }
        });

        $(document).on('click', '.select-tenant', function(e) {
            e.preventDefault();
            let tenantId = $(this).data('tenant-id');
            let tenantName = $(this).data('tenant-name');
            $('#tenant_id').val(tenantId);
            $('#tenant_name').val(tenantName);
            $('#tenantSearchModal').modal('hide');
        });

        // Edit Client Button
        $('#editClientBtn').on('click', function() {
            let clientId = $('#client_id').val();
            if (clientId) {
                window.location.href = '/clients/' + clientId + '/edit';
            } else {
                alert('Please select a client first');
            }
        });

        // Edit Tenant Button
        $('#editTenantBtn').on('click', function() {
            let tenantId = $('#tenant_id').val();
            if (tenantId) {
                window.location.href = '/tenants/' + tenantId + '/edit';
            } else {
                alert('Please select a tenant first');
            }
        });

        let invoiceableItems = []; // Store all invoiceable items

        // Load invoiceable items on page load
        loadInvoiceableItems();

        // Function to load invoiceable items
        function loadInvoiceableItems() {
            $.ajax({
                url: '/invoiceable-items',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Invoiceable items loaded:', response);
                    if (response.items && response.items.length > 0) {
                        invoiceableItems = response.items;
                        
                        // Populate the first dropdown
                        populateInvoiceableItems('#item_0');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading invoiceable items:', xhr.responseText);
                }
            });
        }

        // Function to populate invoiceable items dropdown
        function populateInvoiceableItems(selectId) {
            $(selectId).empty();
            $(selectId).append('<option value="">Please select</option>');
            
            // Filter items where feature array contains "1", "2", or "3"
            const filteredItems = invoiceableItems.filter(function(item) {
                if (!item.feature) return false;
                
                // Parse feature if it's a string (JSON), otherwise use as-is
                let featureArray = typeof item.feature === 'string' ? JSON.parse(item.feature) : item.feature;
                
                // Check if feature array contains "1", "2", or "3"
                return featureArray.some(function(feature) {
                    const featureStr = String(feature);
                    return featureStr === '1' || featureStr === '2' || featureStr === '3';
                });
            });
            
            console.log('Filtered invoiceable items for lease:', filteredItems);
            
            if (filteredItems.length > 0) {
                filteredItems.forEach(function(item) {
                    $(selectId).append(`<option value="${item.id}">${item.item_name}</option>`);
                });
            } else {
                $(selectId).append('<option value="">No items available</option>');
            }
        }

        // Update the Dynamic Invoicable Items section
        let itemCounter = 1;
        $('#addInvoicableItem').on('click', function() {
            if (invoiceableItems.length === 0) {
                alert('Loading invoiceable items, please wait...');
                return;
            }

            // Generate invoiceable items options
            let itemOptions = '<option value="">Please select</option>';
            const filteredItems = invoiceableItems.filter(function(item) {
                if (!item.feature) return false;
                let featureArray = typeof item.feature === 'string' ? JSON.parse(item.feature) : item.feature;
                return featureArray.some(function(feature) {
                    const featureStr = String(feature);
                    return featureStr === '1' || featureStr === '2' || featureStr === '3';
                });
            });
            
            filteredItems.forEach(function(item) {
                itemOptions += `<option value="${item.id}">${item.item_name}</option>`;
            });

            const itemEntry = `
                <div class="invoicable-item-entry mb-3 p-3 border rounded">
                    <div class="row align-items-end">
                        <div class="col-md-4 mb-3">
                            <label for="item_${itemCounter}" class="form-label">Item <span class="text-danger">*</span></label>
                            <select class="form-select" id="item_${itemCounter}" name="invoicable_items[${itemCounter}][item]" required>
                                ${itemOptions}
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="amount_${itemCounter}" class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" 
                                id="amount_${itemCounter}" name="invoicable_items[${itemCounter}][amount]" 
                                value="0.00" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="cycle_${itemCounter}" class="form-label">Cycle <span class="text-danger">*</span></label>
                            <select class="form-select" id="cycle_${itemCounter}" name="invoicable_items[${itemCounter}][cycle]" required>
                                <option value="">Please select</option>
                                <option value="4">Annually</option>
                                <option value="5">Daily</option>
                                <option value="2">Monthly</option>
                                <option value="1">One time</option>
                                <option value="3">Quarterly</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label d-block">Prepay Client</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="prepay_client_${itemCounter}" name="invoicable_items[${itemCounter}][prepay_client]" value="Yes">
                                <label class="form-check-label" for="prepay_client_${itemCounter}">Yes</label>
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-light btn-sm remove-invoicable-item">
                                <span class="material-symbols-outlined" style="font-size:16px;">remove</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('#invoicableItemsContainer').append(itemEntry);
            itemCounter++;
            updateRemoveButtons('.invoicable-item-entry', '.remove-invoicable-item');
        });

        $(document).on('click', '.remove-invoicable-item', function() {
            $(this).closest('.invoicable-item-entry').remove();
            updateRemoveButtons('.invoicable-item-entry', '.remove-invoicable-item');
        });

        // Helper function to show/hide remove buttons
        function updateRemoveButtons(containerClass, buttonClass) {
            if ($(containerClass).length > 1) {
                $(buttonClass).removeClass('d-none');
            } else {
                $(buttonClass).addClass('d-none');
            }
        }

        // Add Files Button functionality
        $('#addFilesBtn').on('click', function() {
            $('#documents').click();
        });

        // Display selected files
        $('#documents').on('change', function() {
            let files = $(this)[0].files;
            if (files.length > 0) {
                let fileNames = [];
                for (let i = 0; i < files.length; i++) {
                    fileNames.push(files[i].name);
                }
                alert('Selected files:\n' + fileNames.join('\n'));
            }
        });

        // Calculate lease end date
        $('#date_start, #years').on('change', function() {
            let startDate = $('#date_start').val();
            let years = $('#years').val();
            
            if (startDate && years) {
                let start = new Date(startDate);
                start.setFullYear(start.getFullYear() + parseInt(years));
                console.log('Lease End Date:', start.toISOString().split('T')[0]);
            }
        });
    });
</script>
@endsection