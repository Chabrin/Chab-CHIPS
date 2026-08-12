@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Tenant Movement</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="movementForm" class="btn btn-success btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined">save</span>
                                <span class="ms-1">Update</span>
                            </button>
                            <a href="{{ route('property.movements.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
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

                        <form action="{{ route('property.movements.update', $movement->id) }}" method="POST" id="movementForm">
                            @csrf
                            @method('PUT')

                            <!-- Movement Details Section -->
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label for="client_search" class="form-label">Client <span class="text-danger">*</span></label>
                                    <select class="form-select @error('client_id') is-invalid @enderror" id="client_search" name="client_id" required>
                                        <option value="">Search for a client...</option>
                                        @if($movement->client)
                                        <option value="{{ $movement->client_id }}" selected>{{ $movement->client->names }} ({{ $movement->client->reference_number }})</option>
                                        @endif
                                    </select>
                                    @error('client_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">Type at least 2 characters to search</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="sla_id" class="form-label">SLA <span class="text-danger">*</span></label>
                                    <select class="form-select @error('sla_id') is-invalid @enderror" id="sla_id" name="sla_id" required>
                                        <option value="">Select a client first</option>
                                        @if($movement->sla)
                                        <option value="{{ $movement->sla_id }}" selected>{{ $movement->sla->reference_number ?? $movement->sla->sla_reference }}</option>
                                        @endif
                                    </select>
                                    @error('sla_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                    <select class="form-select @error('property_id') is-invalid @enderror" id="property_id" name="property_id" required>
                                        <option value="">Select SLA first</option>
                                        @if($movement->property)
                                        <option value="{{ $movement->property_id }}" data-zone-id="{{ $movement->zone_id }}" selected>{{ $movement->property->property_name }} ({{ $movement->property->reference_number }})</option>
                                        @endif
                                    </select>
                                    @error('property_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="unit_from_id" class="form-label">Unit From <span class="text-danger">*</span></label>
                                    <select class="form-select @error('unit_from_id') is-invalid @enderror" id="unit_from_id" name="unit_from_id" required>
                                        <option value="">Select a property first</option>
                                        @if($movement->unitFrom)
                                        <option value="{{ $movement->unit_from_id }}" 
                                            data-lease-id="{{ $movement->lease_id }}" 
                                            data-lease-ref="{{ $movement->lease->ref_number ?? '' }}"
                                            data-tenant-id="{{ $movement->tenant_id }}"
                                            data-tenant-name="{{ $movement->tenant->names ?? '' }}" 
                                            selected>{{ $movement->unitFrom->unit_code ?? $movement->unitFrom->unit_name }}</option>
                                        @endif
                                    </select>
                                    @error('unit_from_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="unit_to_id" class="form-label">Unit To <span class="text-danger">*</span></label>
                                    <select class="form-select @error('unit_to_id') is-invalid @enderror" id="unit_to_id" name="unit_to_id" required>
                                        <option value="">Select a property first</option>
                                        @if($movement->unitTo)
                                        <option value="{{ $movement->unit_to_id }}" selected>{{ $movement->unitTo->unit_code ?? $movement->unitTo->unit_name }}</option>
                                        @endif
                                    </select>
                                    @error('unit_to_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Read-only lease field populated after unit from selection -->
                                <div class="col-md-4 mb-3">
                                    <label for="lease_display" class="form-label">Lease</label>
                                    <input type="text" class="form-control" id="lease_display" 
                                           value="{{ $movement->lease ? $movement->lease->ref_number . ' - ' . ($movement->tenant->names ?? '') : '' }}" readonly>
                                    <input type="hidden" id="lease_id" name="lease_id" value="{{ $movement->lease_id }}">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="new_lease_end_date" class="form-label">New Lease End Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('new_lease_end_date') is-invalid @enderror" 
                                           id="new_lease_end_date" name="new_lease_end_date" 
                                           value="{{ old('new_lease_end_date', $movement->new_lease_end_date ? \Carbon\Carbon::parse($movement->new_lease_end_date)->format('Y-m-d') : '') }}" required>
                                    @error('new_lease_end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="new_lease_start_date" class="form-label">New Lease Start Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('new_lease_start_date') is-invalid @enderror" 
                                           id="new_lease_start_date" name="new_lease_start_date" 
                                           value="{{ old('new_lease_start_date', $movement->new_lease_start_date ? \Carbon\Carbon::parse($movement->new_lease_start_date)->format('Y-m-d') : '') }}" required>
                                    @error('new_lease_start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="current_balance" class="form-label">Current Balance</label>
                                    <input type="number" step="0.01" class="form-control @error('current_balance') is-invalid @enderror" 
                                           id="current_balance" name="current_balance" 
                                           value="{{ old('current_balance', $movement->current_balance ?? 0) }}" required>
                                    @error('current_balance')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="move_with_balance" name="move_with_balance" value="1"
                                               {{ old('move_with_balance', $movement->move_with_balance) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="move_with_balance">
                                            Move with balance to the new unit
                                        </label>
                                        <div class="form-text text-muted">Check this to carry the current outstanding balance over to the new unit's lease. <strong>If the tenant is in arrears at the time of confirmation, a carried-forward balance invoice will automatically be raised against the new lease.</strong></div>
                                    </div>
                                </div>

                                <!-- Live Tenant Balance Card -->
                                <div class="col-md-12 mb-3" id="tenantBalanceCard" style="display:none;">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-header bg-light py-2 d-flex align-items-center gap-2">
                                            <span class="material-symbols-outlined" style="font-size:18px;">account_balance_wallet</span>
                                            <span class="fw-semibold small">Tenant Account Balance</span>
                                            <span class="ms-auto" id="balanceStatusBadge"></span>
                                        </div>
                                        <div class="card-body py-2">
                                            <div class="row text-center">
                                                <div class="col-4 border-end">
                                                    <small class="text-muted d-block">Total Invoiced</small>
                                                    <span class="fw-bold" id="balTotalInvoiced">—</span>
                                                </div>
                                                <div class="col-4 border-end">
                                                    <small class="text-muted d-block">Total Paid</small>
                                                    <span class="fw-bold text-success" id="balTotalPaid">—</span>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted d-block">Balance</small>
                                                    <span class="fw-bold" id="balBalance">—</span>
                                                </div>
                                            </div>
                                            <p class="text-muted small mb-0 mt-2" id="balAsOf"></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Hidden fields -->
                                <input type="hidden" id="tenant_id" name="tenant_id" value="{{ $movement->tenant_id }}">
                                <input type="hidden" id="zone_id" name="zone_id" value="{{ $movement->zone_id }}">
                            </div>

                            <!-- New Lease Invoicable Items Section -->
                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">New Lease Invoicable Items</h6>
                                </div>

                                <div class="col-12">
                                    <div id="invoicableItemsContainer">
                                        @foreach($movement->items as $index => $item)
                                        <div class="invoicable-item-entry mb-3 p-3 border rounded">
                                            <div class="row align-items-baseline">
                                                <div class="col-md-4 mb-3">
                                                    <label for="item_{{ $index }}" class="form-label">Item <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="item_{{ $index }}" name="invoicable_items[{{ $index }}][item]" data-item-id="{{ $item->item_id }}" required>
                                                        <option value="">Please select</option>
                                                        @if($item->item)
                                                        <option value="{{ $item->item_id }}" selected>{{ $item->item->item_name }}</option>
                                                        @endif
                                                    </select>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label for="amount_{{ $index }}" class="form-label">Amount <span class="text-danger">*</span></label>
                                                    <input type="number" step="0.01" class="form-control" 
                                                           id="amount_{{ $index }}" name="invoicable_items[{{ $index }}][amount]" 
                                                           value="{{ $item->amount }}" placeholder="0.00" min="0" required>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label for="cycle_{{ $index }}" class="form-label">Cycle <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="cycle_{{ $index }}" name="invoicable_items[{{ $index }}][cycle]" required>
                                                        <option value="">Please select</option>
                                                        <option value="Monthly" {{ optional($item->cycle)->cycle_name == 'Monthly' ? 'selected' : '' }}>Monthly</option>
                                                        <option value="Quarterly" {{ optional($item->cycle)->cycle_name == 'Quarterly' ? 'selected' : '' }}>Quarterly</option>
                                                        <option value="Semi-Annually" {{ optional($item->cycle)->cycle_name == 'Semi-Annually' ? 'selected' : '' }}>Semi-Annually</option>
                                                        <option value="Annually" {{ optional($item->cycle)->cycle_name == 'Annually' ? 'selected' : '' }}>Annually</option>
                                                        <option value="One-Time" {{ optional($item->cycle)->cycle_name == 'One-Time' ? 'selected' : '' }}>One-Time</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2 mb-3">
                                                    <button type="button" class="btn btn-danger btn-sm remove-invoicable-item {{ $index == 0 ? 'd-none' : '' }}">
                                                        <span class="material-symbols-outlined">remove_circle</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>

                                    <button type="button" class="btn btn-success btn-sm" id="addInvoicableItem">
                                        <span class="material-symbols-outlined">add_circle</span>
                                        <span class="ms-1">Add Item</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        let invoiceableItems = [];
        let itemCounter = {{ count($movement->items) }};
        let existingItems = @json($movement->items->pluck('item_id', 'item_id')->toArray());

        // Initialize all select2 dropdowns
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

        // Initialize other select2 dropdowns
        $('#sla_id').select2({
            placeholder: 'Select a client first',
            allowClear: true,
            width: '100%'
        });

        $('#property_id').select2({
            placeholder: 'Select SLA first',
            allowClear: true,
            width: '100%'
        });

        $('#unit_from_id').select2({
            placeholder: 'Select a property first',
            allowClear: true,
            width: '100%'
        });

        $('#unit_to_id').select2({
            placeholder: 'Select a property first',
            allowClear: true,
            width: '100%'
        });

        // Initialize existing item selects
        @for($i = 0; $i < count($movement->items); $i++)
        $('#item_{{ $i }}').select2({
            placeholder: 'Please select',
            allowClear: true,
            width: '100%'
        });

        $('#cycle_{{ $i }}').select2({
            placeholder: 'Please select',
            allowClear: true,
            width: '100%'
        });
        @endfor

        // Load invoiceable items
        loadInvoiceableItems();
        
        // Load vacant units on page load so unit_to can be changed
        const initialPropertyId = {{ $movement->property_id }};
        const initialUnitToId = {{ $movement->unit_to_id ?? 'null' }};
        if (initialPropertyId) {
            loadVacantUnits(initialPropertyId, initialUnitToId);
        }

        function loadInvoiceableItems() {
            $.ajax({
                url: '/invoiceable-items',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.items && response.items.length > 0) {
                        invoiceableItems = response.items;
                        // Populate existing items
                        @foreach($movement->items as $index => $item)
                        const itemId_{{ $index }} = $('#item_{{ $index }}').data('item-id');
                        populateInvoiceableItems('#item_{{ $index }}', itemId_{{ $index }});
                        @endforeach
                    }
                },
                error: function(xhr) {
                    console.error('Error loading invoiceable items:', xhr);
                }
            });
        }

        function populateInvoiceableItems(selectId, selectedId = null) {
            $(selectId).empty().append('<option value="">Please select</option>');
            
            const filteredItems = invoiceableItems.filter(function(item) {
                if (!item.feature) return false;
                let featureArray = typeof item.feature === 'string' ? JSON.parse(item.feature) : item.feature;
                return featureArray.some(function(feature) {
                    const featureStr = String(feature);
                    return featureStr === '1' || featureStr === '2' || featureStr === '3';
                });
            });
            
            filteredItems.forEach(function(item) {
                const isSelected = selectedId && item.id == selectedId ? 'selected' : '';
                $(selectId).append(`<option value="${item.id}" ${isSelected}>${item.item_name}</option>`);
            });

            // Reinitialize select2 if it exists
            if ($(selectId).hasClass('select2-hidden-accessible')) {
                $(selectId).select2('destroy');
            }
            $(selectId).select2({
                placeholder: 'Please select',
                allowClear: true,
                width: '100%'
            });
            
            // Set the value explicitly for Select2
            if (selectedId) {
                $(selectId).val(selectedId).trigger('change');
            }
        }

        // When client changes, load SLAs
        $('#client_search').on('change', function() {
            const clientId = $(this).val();
            
            if (clientId) {
                loadSLAs(clientId);
            } else {
                $('#sla_id').html('<option value="">Select a client first</option>').prop('disabled', true);
                $('#property_id').html('<option value="">Select SLA first</option>').prop('disabled', true);
                $('#unit_from_id').html('<option value="">Select a property first</option>').prop('disabled', true);
                $('#unit_to_id').html('<option value="">Select a property first</option>').prop('disabled', true);
            }
        });

        function loadSLAs(clientId) {
            $('#sla_id').html('<option value="">Loading SLAs...</option>').prop('disabled', true);
            
            $.ajax({
                url: `/client/${clientId}/slas`,
                method: 'GET',
                success: function(response) {
                    $('#sla_id').empty().prop('disabled', false);
                    $('#sla_id').append('<option value="">Please select</option>');
                    
                    if (response.slas && response.slas.length > 0) {
                        response.slas.forEach(function(sla) {
                            $('#sla_id').append(`<option value="${sla.id}">${sla.reference_number || sla.sla_reference}</option>`);
                        });
                    } else {
                        $('#sla_id').append('<option value="">No SLA found</option>');
                    }
                    
                    // Reinitialize select2
                    $('#sla_id').select2({
                        placeholder: 'Please select',
                        allowClear: true,
                        width: '100%'
                    });
                },
                error: function(xhr) {
                    console.error('Error loading SLAs:', xhr);
                    $('#sla_id').html('<option value="">Error loading SLAs</option>').prop('disabled', false);
                }
            });
        }

        // When SLA changes, load properties
        $('#sla_id').on('change', function() {
            const slaId = $(this).val();
            
            if (slaId) {
                loadProperties(slaId);
            } else {
                $('#property_id').html('<option value="">Select SLA first</option>').prop('disabled', true);
                $('#unit_from_id').html('<option value="">Select a property first</option>').prop('disabled', true);
                $('#unit_to_id').html('<option value="">Select a property first</option>').prop('disabled', true);
            }
        });

        function loadProperties(slaId) {
            $('#property_id').html('<option value="">Loading properties...</option>').prop('disabled', true);
            
            $.ajax({
                url: `/sla/${slaId}/properties`,
                method: 'GET',
                success: function(response) {
                    $('#property_id').empty().prop('disabled', false);
                    $('#property_id').append('<option value="">Please select</option>');
                    
                    if (response.properties && response.properties.length > 0) {
                        response.properties.forEach(function(property) {
                            $('#property_id').append(`<option value="${property.id}" data-zone-id="${property.zone_id || ''}">${property.property_name} (${property.reference_number})</option>`);
                        });
                    } else {
                        $('#property_id').append('<option value="">No properties found</option>');
                    }
                    
                    // Reinitialize select2
                    $('#property_id').select2({
                        placeholder: 'Please select',
                        allowClear: true,
                        width: '100%'
                    });
                },
                error: function(xhr) {
                    console.error('Error loading properties:', xhr);
                    $('#property_id').html('<option value="">Error loading properties</option>').prop('disabled', false);
                }
            });
        }

        // When property changes, load occupied units (Unit From) and vacant units (Unit To)
        $('#property_id').on('change', function() {
            const propertyId = $(this).val();
            const zoneId = $(this).find('option:selected').data('zone-id');
            
            $('#zone_id').val(zoneId);
            
            if (propertyId) {
                loadOccupiedUnits(propertyId);
                loadVacantUnits(propertyId);
            } else {
                $('#unit_from_id').html('<option value="">Select a property first</option>').prop('disabled', true);
                $('#unit_to_id').html('<option value="">Select a property first</option>').prop('disabled', true);
            }
        });

        function loadOccupiedUnits(propertyId) {
            $('#unit_from_id').html('<option value="">Loading units...</option>').prop('disabled', true);
            
            $.ajax({
                url: `/property/${propertyId}/occupied-units`,
                method: 'GET',
                success: function(response) {
                    $('#unit_from_id').empty().prop('disabled', false);
                    $('#unit_from_id').append('<option value="">Please select</option>');
                    
                    if (response.units && response.units.length > 0) {
                        response.units.forEach(function(unit) {
                            $('#unit_from_id').append(`<option value="${unit.id}" 
                                data-lease-id="${unit.lease_id}" 
                                data-lease-ref="${unit.lease_ref}"
                                data-tenant-id="${unit.tenant_id}"
                                data-tenant-name="${unit.tenant_name}">${unit.unit_code || unit.unit_name}</option>`);
                        });
                    } else {
                        $('#unit_from_id').append('<option value="">No occupied units found</option>');
                    }
                    
                    // Reinitialize select2
                    $('#unit_from_id').select2({
                        placeholder: 'Please select',
                        allowClear: true,
                        width: '100%'
                    });
                },
                error: function(xhr) {
                    console.error('Error loading occupied units:', xhr);
                }
            });
        }

        function loadVacantUnits(propertyId, selectedUnitId = null) {
            $('#unit_to_id').html('<option value="">Loading units...</option>').prop('disabled', true);
            
            $.ajax({
                url: `/property/${propertyId}/vacant-units`,
                method: 'GET',
                success: function(response) {
                    $('#unit_to_id').empty().prop('disabled', false);
                    $('#unit_to_id').append('<option value="">Please select</option>');
                    
                    if (response.units && response.units.length > 0) {
                        response.units.forEach(function(unit) {
                            $('#unit_to_id').append(`<option value="${unit.id}">${unit.unit_code || unit.unit_name}</option>`);
                        });
                    } else {
                        $('#unit_to_id').append('<option value="">No vacant units available</option>');
                    }
                    
                    // Reinitialize select2
                    $('#unit_to_id').select2({
                        placeholder: 'Please select',
                        allowClear: true,
                        width: '100%'
                    });
                    
                    // Set the previously selected unit if provided
                    if (selectedUnitId) {
                        $('#unit_to_id').val(selectedUnitId).trigger('change');
                    }
                },
                error: function(xhr) {
                    console.error('Error loading units:', xhr);
                }
            });
        }

        function fetchTenantBalance(tenantId) {
            $('#tenantBalanceCard').hide();
            if (!tenantId) return;
            $.get('{{ route('get.tenant.balance') }}', { tenant_id: tenantId }, function(res) {
                const b = res.balance;
                if (!b) return;
                const inArrears = b.is_in_arrears;
                const balColour  = inArrears ? 'text-danger' : 'text-success';
                const badgeHtml  = inArrears
                    ? '<span class="badge bg-danger">In Arrears</span>'
                    : '<span class="badge bg-success">Credit</span>';
                $('#balTotalInvoiced').text(parseFloat(b.total_invoiced).toLocaleString('en', {minimumFractionDigits:2}));
                $('#balTotalPaid').text(parseFloat(b.total_paid).toLocaleString('en', {minimumFractionDigits:2}));
                $('#balBalance').removeClass('text-danger text-success').addClass(balColour)
                    .text((inArrears ? '(' : '') + b.balance_formatted + (inArrears ? ')' : ''));
                $('#balanceStatusBadge').html(badgeHtml);
                $('#balAsOf').text('As of: ' + b.as_of_date);
                $('#current_balance').val(parseFloat(b.balance).toFixed(2));
                $('#tenantBalanceCard').fadeIn();
            });
        }

        // When Unit From changes, populate lease information
        $('#unit_from_id').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            
            if (selectedOption.val()) {
                const leaseId = selectedOption.data('lease-id');
                const leaseRef = selectedOption.data('lease-ref');
                const tenantId = selectedOption.data('tenant-id');
                const tenantName = selectedOption.data('tenant-name');
                
                $('#lease_id').val(leaseId);
                $('#lease_display').val(leaseRef + ' - ' + tenantName);
                $('#tenant_id').val(tenantId);
                fetchTenantBalance(tenantId);
            } else {
                $('#lease_id').val('');
                $('#lease_display').val('');
                $('#tenant_id').val('');
                $('#tenantBalanceCard').hide();
                $('#current_balance').val(0);
            }
        });

        // Auto-load balance for existing tenant on page load
        @if($movement->tenant_id)
        fetchTenantBalance({{ $movement->tenant_id }});
        @endif

        // Add invoiceable item
        $('#addInvoicableItem').on('click', function() {
            if (invoiceableItems.length === 0) {
                toastr.warning('Loading invoiceable items, please wait...');
                return;
            }

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
                    <div class="row align-items-baseline">
                        <div class="col-md-4 mb-3">
                            <label for="item_${itemCounter}" class="form-label">Item <span class="text-danger">*</span></label>
                            <select class="form-select" id="item_${itemCounter}" name="invoicable_items[${itemCounter}][item]" required>
                                ${itemOptions}
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="amount_${itemCounter}" class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" 
                                   id="amount_${itemCounter}" name="invoicable_items[${itemCounter}][amount]" 
                                   placeholder="0.00" min="0" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="cycle_${itemCounter}" class="form-label">Cycle <span class="text-danger">*</span></label>
                            <select class="form-select" id="cycle_${itemCounter}" name="invoicable_items[${itemCounter}][cycle]" required>
                                <option value="">Please select</option>
                                <option value="Monthly">Monthly</option>
                                <option value="Quarterly">Quarterly</option>
                                <option value="Semi-Annually">Semi-Annually</option>
                                <option value="Annually">Annually</option>
                                <option value="One-Time">One-Time</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <button type="button" class="btn btn-danger btn-sm remove-invoicable-item">
                                <span class="material-symbols-outlined">remove_circle</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('#invoicableItemsContainer').append(itemEntry);
            
            // Initialize select2 for the newly added selects
            $(`#item_${itemCounter}`).select2({
                placeholder: 'Please select',
                allowClear: true,
                width: '100%'
            });
            
            $(`#cycle_${itemCounter}`).select2({
                placeholder: 'Please select',
                allowClear: true,
                width: '100%'
            });
            
            itemCounter++;
            updateRemoveButtons();
        });

        $(document).on('click', '.remove-invoicable-item', function() {
            $(this).closest('.invoicable-item-entry').remove();
            updateRemoveButtons();
        });

        function updateRemoveButtons() {
            if ($('.invoicable-item-entry').length > 1) {
                $('.remove-invoicable-item').removeClass('d-none');
            } else {
                $('.remove-invoicable-item').addClass('d-none');
            }
        }

        // Form submission
        $('#movementForm').on('submit', function() {
            $('#submitSpinner').removeClass('d-none');
            $('#saveButton').prop('disabled', true);
        });
    });
</script>
@endsection
