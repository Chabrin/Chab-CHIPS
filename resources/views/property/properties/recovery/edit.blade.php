@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Recovery</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="recoveryForm" class="btn btn-primary btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Update</span>
                            </button>
                            <a href="{{ route('property.recoveries.show', Crypt::encrypt($recovery->id)) }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to Details</span>
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

                        <form action="{{ route('property.recoveries.update', Crypt::encrypt($recovery->id)) }}" method="POST" id="recoveryForm">
                            @csrf
                            @method('PUT')

                            <!-- Recoveries Section -->
                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">Recovery Information</h6>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="type_id" class="form-label">Type <span class="text-danger">*</span></label>
                                    <select class="form-select @error('type_id') is-invalid @enderror" id="type_id" name="type_id" required>
                                        <option value="">Please select</option>
                                        @foreach($transaction_types as $type)
                                            <option value="{{ $type->id }}" {{ (old('type_id', $recovery->type_id) == $type->id) ? 'selected' : '' }}>{{ $type->type_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('type_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="ref_number" class="form-label">Ref Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('ref_number') is-invalid @enderror" 
                                           id="ref_number" name="ref_number" value="{{ old('ref_number', $recovery->ref_number) }}" required>
                                    @error('ref_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="recovery_date" class="form-label">Recovery Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('recovery_date') is-invalid @enderror" 
                                           id="recovery_date" name="recovery_date" value="{{ old('recovery_date', $recovery->recovery_date) }}" required>
                                    @error('recovery_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control @error('amount') is-invalid @enderror" 
                                           id="amount" name="amount" value="{{ old('amount', $recovery->amount) }}" required>
                                    @error('amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="narration" class="form-label">Narration</label>
                                    <textarea class="form-control @error('narration') is-invalid @enderror" 
                                              id="narration" name="narration" rows="3">{{ old('narration', $recovery->narration) }}</textarea>
                                    @error('narration')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Client & Property Section -->
                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">Client & Property Information</h6>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="client_id" class="form-label">Client <span class="text-danger">*</span></label>
                                    <select class="form-select @error('client_id') is-invalid @enderror" id="client_id" name="client_id" required>
                                        <option value="">Loading clients...</option>
                                    </select>
                                    @error('client_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                                    <select class="form-select @error('property_id') is-invalid @enderror" id="property_id" name="property_id" required>
                                        <option value="">Please select client first</option>
                                    </select>
                                    @error('property_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="sla_id" class="form-label">SLA</label>
                                    <select class="form-select @error('sla_id') is-invalid @enderror" id="sla_id" name="sla_id">
                                        <option value="">Please select property first</option>
                                    </select>
                                    @error('sla_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="unit_id" class="form-label">Unit</label>
                                    <select class="form-select @error('unit_id') is-invalid @enderror" id="unit_id" name="unit_id">
                                        <option value="">Please select property first</option>
                                    </select>
                                    @error('unit_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="tenant_id" class="form-label">Tenant</label>
                                    <select class="form-select @error('tenant_id') is-invalid @enderror" id="tenant_id" name="tenant_id">
                                        <option value="">Please select unit first</option>
                                    </select>
                                    @error('tenant_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="lease_id" class="form-label">Lease</label>
                                    <select class="form-select @error('lease_id') is-invalid @enderror" id="lease_id" name="lease_id">
                                        <option value="">Please select unit first</option>
                                    </select>
                                    @error('lease_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="offset_invoice_id" class="form-label">Offset Invoice</label>
                                    <select class="form-select @error('offset_invoice_id') is-invalid @enderror" id="offset_invoice_id" name="offset_invoice_id">
                                        <option value="">Please select</option>
                                    </select>
                                    @error('offset_invoice_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="proof_reference" class="form-label">Proof (receipt / acknowledgement no.)</label>
                                    <input type="text" class="form-control @error('proof_reference') is-invalid @enderror" id="proof_reference" name="proof_reference" maxlength="100" value="{{ old('proof_reference', $recovery->proof_reference) }}" placeholder="Landlord's receipt or acknowledgement">
                                    @error('proof_reference')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="commisionable" class="form-label">Commissionable <span class="text-danger">*</span></label>
                                    <select class="form-select @error('commisionable') is-invalid @enderror" id="commisionable" name="commisionable" required>
                                        <option value="No" {{ old('commisionable', $recovery->commisionable) == 'No' ? 'selected' : '' }}>No</option>
                                        <option value="Yes" {{ old('commisionable', $recovery->commisionable) == 'Yes' ? 'selected' : '' }}>Yes</option>
                                    </select>
                                    @error('commisionable')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="return_type_id" class="form-label">Return Type</label>
                                    <select class="form-select @error('return_type_id') is-invalid @enderror" id="return_type_id" name="return_type_id">
                                        <option value="">Please select</option>
                                        <option value="Main return" {{ old('return_type_id', $recovery->return_type_id) == 'Main return' ? 'selected' : '' }}>Main return</option>
                                        <option value="Sub return" {{ old('return_type_id', $recovery->return_type_id) == 'Sub return' ? 'selected' : '' }}>Sub return</option>
                                        <option value="Commission return" {{ old('return_type_id', $recovery->return_type_id) == 'Commission return' ? 'selected' : '' }}>Commission return</option>
                                        <option value="Other" {{ old('return_type_id', $recovery->return_type_id) == 'Other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                    @error('return_type_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        // Initialize Select2 for all select elements
        $('#client_id, #property_id, #sla_id, #unit_id, #tenant_id, #lease_id, #offset_invoice_id, #type_id, #commisionable, #return_type_id').select2({
            placeholder: 'Please select',
            allowClear: true,
            width: '100%'
        });
        
        // Form submission with spinner
        $('#recoveryForm').on('submit', function(e) {
            $('#submitSpinner').removeClass('d-none');
            $('#saveButton').prop('disabled', true);
        });

        // Store existing recovery data
        const existingData = {
            clientId: {{ $recovery->client_id ?? 'null' }},
            propertyId: {{ $recovery->property_id ?? 'null' }},
            slaId: {{ $recovery->sla_id ?? 'null' }},
            unitId: {{ $recovery->unit_id ?? 'null' }},
            tenantId: {{ $recovery->tenant_id ?? 'null' }},
            leaseId: {{ $recovery->lease_id ?? 'null' }},
            invoiceId: {{ $recovery->offset_invoice_id ?? 'null' }}
        };

        // Load all clients on page load
        loadClients();

        function loadClients() {
            $.ajax({
                url: '/ajax/clients/list',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#client_id').empty().append('<option value="">Please select</option>');
                    let clients = response.data || response.clients || [];
                    if (clients.length > 0) {
                        clients.forEach(function(client) {
                            let selected = client.id == existingData.clientId ? 'selected' : '';
                            $('#client_id').append(`<option value="${client.id}" ${selected}>${client.names}</option>`);
                        });
                        // If client is selected, load properties
                        if (existingData.clientId) {
                            loadProperties(existingData.clientId);
                        }
                    }
                    $('#client_id').trigger('change.select2');
                },
                error: function(xhr) {
                    console.error('Error loading clients:', xhr);
                    $('#client_id').empty().append('<option value="">Error loading clients</option>');
                    $('#client_id').trigger('change.select2');
                }
            });
        }

        // Client selection handler
        $('#client_id').on('change', function() {
            let clientId = $(this).val();
            
            resetDropdown('#property_id', 'Please select');
            resetDropdown('#sla_id', 'Please select property first');
            resetDropdown('#unit_id', 'Please select property first');
            resetDropdown('#tenant_id', 'Please select unit first');
            resetDropdown('#lease_id', 'Please select unit first');
            resetDropdown('#offset_invoice_id', 'Please select unit first');
            
            if (clientId) {
                loadProperties(clientId);
            }
        });

        function loadProperties(clientId) {
            $.ajax({
                url: `/client/${clientId}/properties`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#property_id').empty().append('<option value="">Please select</option>');
                    if (response.properties && response.properties.length > 0) {
                        response.properties.forEach(function(property) {
                            let selected = property.id == existingData.propertyId ? 'selected' : '';
                            $('#property_id').append(`<option value="${property.id}" ${selected}>${property.reference_number} - ${property.property_name}</option>`);
                        });
                        // If property is selected, load SLAs and units
                        if (existingData.propertyId) {
                            loadSLAs(existingData.propertyId);
                            loadUnits(existingData.propertyId);
                        }
                    } else {
                        $('#property_id').append('<option value="">No properties found</option>');
                    }
                    $('#property_id').trigger('change.select2');
                },
                error: function(xhr) {
                    console.error('Error loading properties:', xhr);
                    $('#property_id').empty().append('<option value="">Error loading properties</option>');
                    $('#property_id').trigger('change.select2');
                }
            });
        }

        // Open tenant invoices of the selected unit
        function loadInvoices(unitId) {
            $.ajax({
                url: `/property/recoveries/lookup/unit/${unitId}/invoices`,
                method: 'GET',
                dataType: 'json',
                data: { status: 'outstanding' },
                success: function(response) {
                    $('#offset_invoice_id').empty().append('<option value="">Please select</option>');
                    if (response.invoices && response.invoices.length > 0) {
                        response.invoices.forEach(function(invoice) {
                            let amount = parseFloat(invoice.outstanding_amount || invoice.amount).toFixed(2);
                            let selected = invoice.id == existingData.invoiceId ? 'selected' : '';
                            $('#offset_invoice_id').append(`<option value="${invoice.id}" ${selected}>${invoice.invoice_number} - KES ${amount}</option>`);
                        });
                    } else {
                        $('#offset_invoice_id').append('<option value="">No outstanding invoices</option>');
                    }
                    $('#offset_invoice_id').trigger('change.select2');
                },
                error: function(xhr) {
                    console.error('Error loading invoices:', xhr);
                    $('#offset_invoice_id').empty().append('<option value="">Error loading invoices</option>');
                    $('#offset_invoice_id').trigger('change.select2');
                }
            });
        }

        $('#property_id').on('change', function() {
            let propertyId = $(this).val();
            
            resetDropdown('#sla_id', 'Please select');
            resetDropdown('#unit_id', 'Please select');
            resetDropdown('#lease_id', 'Please select unit first');
            
            if (propertyId) {
                loadSLAs(propertyId);
                loadUnits(propertyId);
            }
        });

        function loadSLAs(propertyId) {
            $.ajax({
                url: `/property/${propertyId}/sla`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#sla_id').empty().append('<option value="">Please select</option>');
                    let slas = response.sla || response.slas || [];
                    if (slas.length > 0) {
                        slas.forEach(function(sla) {
                            let selected = sla.id == existingData.slaId ? 'selected' : '';
                            $('#sla_id').append(`<option value="${sla.id}" ${selected}>${sla.reference_number}</option>`);
                        });
                    } else {
                        $('#sla_id').append('<option value="">No SLAs found</option>');
                    }
                    $('#sla_id').trigger('change.select2');
                },
                error: function(xhr) {
                    console.error('Error loading SLAs:', xhr);
                    $('#sla_id').empty().append('<option value="">Error loading SLAs</option>');
                    $('#sla_id').trigger('change.select2');
                }
            });
        }

        function loadUnits(propertyId) {
            $.ajax({
                url: `/property/${propertyId}/all_units`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#unit_id').empty().append('<option value="">Please select</option>');
                    if (response.units && response.units.length > 0) {
                        response.units.forEach(function(unit) {
                            let selected = unit.id == existingData.unitId ? 'selected' : '';
                            $('#unit_id').append(`<option value="${unit.id}" ${selected}>${unit.unit_code} - ${unit.unit_name || unit.unit_number}</option>`);
                        });
                        // If unit is selected, load tenant and leases
                        if (existingData.unitId) {
                            loadTenant(existingData.unitId);
                            loadLeases(existingData.unitId);
                            loadInvoices(existingData.unitId);
                        }
                    } else {
                        $('#unit_id').append('<option value="">No units found</option>');
                    }
                    $('#unit_id').trigger('change.select2');
                },
                error: function(xhr) {
                    console.error('Error loading units:', xhr);
                    $('#unit_id').empty().append('<option value="">Error loading units</option>');
                    $('#unit_id').trigger('change.select2');
                }
            });
        }

        $('#unit_id').on('change', function() {
            let unitId = $(this).val();
            
            resetDropdown('#tenant_id', 'Please select');
            resetDropdown('#lease_id', 'Please select');
            resetDropdown('#offset_invoice_id', 'Please select');
            
            if (unitId) {
                loadTenant(unitId);
                loadLeases(unitId);
                loadInvoices(unitId);
            }
        });

        function loadTenant(unitId) {
            $.ajax({
                url: `/unit/${unitId}/tenant`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#tenant_id').empty().append('<option value="">Please select</option>');
                    if (response.tenant) {
                        let selected = response.tenant.id == existingData.tenantId ? 'selected' : '';
                        $('#tenant_id').append(`<option value="${response.tenant.id}" ${selected}>${response.tenant.reference_number} - ${response.tenant.names}</option>`);
                    } else {
                        $('#tenant_id').append('<option value="">No tenant found for this unit</option>');
                    }
                    $('#tenant_id').trigger('change.select2');
                },
                error: function(xhr) {
                    console.error('Error loading tenant:', xhr);
                    $('#tenant_id').empty().append('<option value="">Error loading tenant</option>');
                    $('#tenant_id').trigger('change.select2');
                }
            });
        }

        function loadLeases(unitId) {
            $.ajax({
                url: `/unit/${unitId}/leases`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#lease_id').empty().append('<option value="">Please select</option>');
                    if (response.leases && response.leases.length > 0) {
                        response.leases.forEach(function(lease) {
                            let status = lease.current_status_id == 5 ? 'Active' : 'Inactive';
                            let selected = lease.id == existingData.leaseId ? 'selected' : '';
                            $('#lease_id').append(`<option value="${lease.id}" ${selected}>${lease.reference_number} - ${status}</option>`);
                        });
                    } else {
                        $('#lease_id').append('<option value="">No leases found for this unit</option>');
                    }
                    $('#lease_id').trigger('change.select2');
                },
                error: function(xhr) {
                    console.error('Error loading leases:', xhr);
                    $('#lease_id').empty().append('<option value="">Error loading leases</option>');
                    $('#lease_id').trigger('change.select2');
                }
            });
        }

        function resetDropdown(selector, placeholder) {
            $(selector).empty().append(`<option value="">${placeholder}</option>`);
        }
    });
</script>
@endsection
