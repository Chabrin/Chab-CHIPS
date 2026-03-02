@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Add New Recovery</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="recoveryForm" class="btn btn-primary btn-sm d-flex align-items-center" id="saveButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Save</span>
                            </button>
                            <a href="#" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
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

                        <form action="" method="POST" id="recoveryForm" enctype="multipart/form-data">
                            @csrf

                            <!-- Recoveries Section -->
                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">Recoveries</h6>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                                    <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                                        <option value="">Please select</option>
                                        @foreach($transaction_types as $type)
                                            <option value="{{ $type->id }}" {{ old('type') == $type->id ? 'selected' : '' }}>{{ $type->type_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="ref_number" class="form-label">Ref Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('ref_number') is-invalid @enderror" 
                                           id="ref_number" name="ref_number" required>
                                    @error('ref_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="recovery_date" class="form-label">Recovery Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('recovery_date') is-invalid @enderror" 
                                           id="recovery_date" name="recovery_date"  required>
                                    @error('recovery_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="narration" class="form-label">Narration <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('narration') is-invalid @enderror" 
                                           id="narration" name="narration" 
                                           value="{{ old('narration') }}" required>
                                    @error('narration')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
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
                                    <label for="property_id" class="form-label">Property</label>
                                    <select class="form-select @error('property_id') is-invalid @enderror" id="property_id" name="property_id">
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
                                    <label for="offset_invoice" class="form-label">Offset Invoice <span class="text-danger">*</span></label>
                                    <select class="form-select @error('offset_invoice') is-invalid @enderror" id="offset_invoice" name="offset_invoice" required>
                                        <option value="">Please select</option>
                                        
                                    </select>
                                    @error('offset_invoice')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="commissionable" class="form-label">Commissionable</label>
                                    <select class="form-select @error('commissionable') is-invalid @enderror" id="commissionable" name="commissionable">
                                        <option value="No" {{ old('commissionable') == 'No' ? 'selected' : 'selected' }}>No</option>
                                        <option value="Yes" {{ old('commissionable') == 'Yes' ? 'selected' : '' }}>Yes</option>
                                    </select>
                                    @error('commissionable')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="account_id" class="form-label">Account</label>
                                    <select class="form-select @error('account_id') is-invalid @enderror" id="account_id" name="account_id">
                                        <option value="">Please select</option>
                                       
                                    </select>
                                    @error('account_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control @error('amount') is-invalid @enderror" 
                                           id="amount" name="amount" 
                                           value="{{ old('amount', '0.00') }}" required>
                                    @error('amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="return_type" class="form-label">Return Type <span class="text-danger">*</span></label>
                                    <select class="form-select @error('return_type') is-invalid @enderror" id="return_type" name="return_type" required>
                                        <option value="">Please select</option>
                                        <option value="Main return" {{ old('return_type') == 'Main return' ? 'selected' : 'selected' }}>Main return</option>
                                        <option value="Sub return" {{ old('return_type') == 'Sub return' ? 'selected' : '' }}>Sub return</option>
                                        <option value="Commission return" {{ old('return_type') == 'Commission return' ? 'selected' : '' }}>Commission return</option>
                                        <option value="Other" {{ old('return_type') == 'Other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                    @error('return_type')
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
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        // Initialize Select2 for all select elements
        $('#client_id').select2({
            placeholder: 'Please select',
            allowClear: true,
            width: '100%'
        });
        
        $('#property_id').select2({
            placeholder: 'Please select client first',
            allowClear: true,
            width: '100%'
        });
        
        $('#sla_id').select2({
            placeholder: 'Please select property first',
            allowClear: true,
            width: '100%'
        });
        
        $('#unit_id').select2({
            placeholder: 'Please select property first',
            allowClear: true,
            width: '100%'
        });
        
        $('#tenant_id').select2({
            placeholder: 'Please select unit first',
            allowClear: true,
            width: '100%'
        });
        
        $('#lease_id').select2({
            placeholder: 'Please select unit first',
            allowClear: true,
            width: '100%'
        });
        
        $('#offset_invoice').select2({
            placeholder: 'Please select',
            allowClear: true,
            width: '100%'
        });
        
        $('#type').select2({
            placeholder: 'Please select',
            allowClear: true,
            width: '100%'
        });
        
        $('#commissionable').select2({
            placeholder: 'Please select',
            allowClear: true,
            width: '100%'
        });
        
        $('#return_type').select2({
            placeholder: 'Please select',
            allowClear: true,
            width: '100%'
        });
        
        // Form submission with spinner
        $('#recoveryForm').on('submit', function(e) {
            $('#submitSpinner').removeClass('d-none');
            $('#saveButton').prop('disabled', true);
        });

        // Auto-generate reference number if empty
        if ($('#ref_number').val() === '') {
            let timestamp = Date.now();
            $('#ref_number').val(timestamp.toString());
        }

        // Load all clients on page load
        loadClients();

        function loadClients() {
            $.ajax({
                url: '/ajax/clients/list',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#client_id').empty().append('<option value="">Please select</option>');
                    // Handle both response.data and response.clients formats
                    let clients = response.data || response.clients || [];
                    if (clients.length > 0) {
                        clients.forEach(function(client) {
                            $('#client_id').append(`<option value="${client.id}">${client.names}</option>`);
                        });
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

        // Client selection handler - load properties and invoices
        $('#client_id').on('change', function() {
            let clientId = $(this).val();
            
            // Reset dependent dropdowns
            resetDropdown('#property_id', 'Please select');
            resetDropdown('#sla_id', 'Please select property first');
            resetDropdown('#unit_id', 'Please select property first');
            resetDropdown('#tenant_id', 'Please select unit first');
            resetDropdown('#lease_id', 'Please select unit first');
            resetDropdown('#offset_invoice', 'Please select');
            
            if (clientId) {
                // Load properties for selected client
                loadProperties(clientId);
                
                // Load outstanding invoices for selected client
                loadInvoices(clientId);
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
                            $('#property_id').append(`<option value="${property.id}">${property.reference_number} - ${property.property_name}</option>`);
                        });
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

        function loadInvoices(clientId) {
            $.ajax({
                url: `/client/${clientId}/invoices`,
                method: 'GET',
                dataType: 'json',
                data: { status: 'outstanding' },
                success: function(response) {
                    $('#offset_invoice').empty().append('<option value="">Please select</option>');
                    if (response.invoices && response.invoices.length > 0) {
                        response.invoices.forEach(function(invoice) {
                            let amount = parseFloat(invoice.outstanding_amount || invoice.amount).toFixed(2);
                            $('#offset_invoice').append(`<option value="${invoice.id}">${invoice.invoice_number} - KES ${amount}</option>`);
                        });
                    } else {
                        $('#offset_invoice').append('<option value="">No outstanding invoices</option>');
                    }
                    $('#offset_invoice').trigger('change.select2');
                },
                error: function(xhr) {
                    console.error('Error loading invoices:', xhr);
                    $('#offset_invoice').empty().append('<option value="">Error loading invoices</option>');
                    $('#offset_invoice').trigger('change.select2');
                }
            });
        }

        // Property selection handler - load SLAs and units
        $('#property_id').on('change', function() {
            let propertyId = $(this).val();
            
            // Reset dependent dropdowns
            resetDropdown('#sla_id', 'Please select');
            resetDropdown('#unit_id', 'Please select');
            resetDropdown('#lease_id', 'Please select unit first');
            
            if (propertyId) {
                // Load SLAs for selected property
                loadSLAs(propertyId);
                
                // Load units for selected property
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
                    // Handle both response.sla and response.slas formats
                    let slas = response.sla || response.slas || [];
                    if (slas.length > 0) {
                        slas.forEach(function(sla) {
                            $('#sla_id').append(`<option value="${sla.id}">${sla.reference_number}</option>`);
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
                            $('#unit_id').append(`<option value="${unit.id}">${unit.unit_code} - ${unit.unit_name || unit.unit_number}</option>`);
                        });
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

        // Unit selection handler - load tenant and leases for selected unit
        $('#unit_id').on('change', function() {
            let unitId = $(this).val();
            
            resetDropdown('#tenant_id', 'Please select');
            resetDropdown('#lease_id', 'Please select');
            
            if (unitId) {
                // Load tenant for selected unit
                loadTenant(unitId);
                
                // Load leases for selected unit
                loadLeases(unitId);
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
                        $('#tenant_id').append(`<option value="${response.tenant.id}" selected>${response.tenant.reference_number} - ${response.tenant.names}</option>`);
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
                            $('#lease_id').append(`<option value="${lease.id}">${lease.reference_number} - ${status}</option>`);
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

        // Helper function to reset dropdown
        function resetDropdown(selector, placeholder) {
            $(selector).empty().append(`<option value="">${placeholder}</option>`);
        }

        // Lease selection handler - auto-populate related fields (optional enhancement)
        $('#lease_id').on('change', function() {
            let leaseId = $(this).val();
            if (leaseId) {
                $.ajax({
                    url: `/lease/${leaseId}/details`,
                    method: 'GET',
                    dataType: 'json',
                    success: function(lease) {
                        // Auto-populate SLA if available
                        if (lease.sla_id && $('#sla_id option[value="' + lease.sla_id + '"]').length > 0) {
                            $('#sla_id').val(lease.sla_id);
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading lease details:', xhr);
                    }
                });
            }
        });

        // Offset Invoice selection - auto-populate amount
        $('#offset_invoice').on('change', function() {
            let invoiceId = $(this).val();
            if (invoiceId) {
                $.ajax({
                    url: `/invoice/${invoiceId}/details`,
                    method: 'GET',
                    dataType: 'json',
                    success: function(invoice) {
                        // Auto-populate amount if not already set
                        if ($('#amount').val() == '0.00' || $('#amount').val() == '') {
                            let outstandingAmount = parseFloat(invoice.outstanding_amount || invoice.amount).toFixed(2);
                            $('#amount').val(outstandingAmount);
                        }
                        
                        console.log('Invoice Details:', {
                            number: invoice.invoice_number,
                            amount: invoice.amount,
                            outstanding: invoice.outstanding_amount,
                            date: invoice.invoice_date
                        });
                    },
                    error: function(xhr) {
                        console.error('Error loading invoice details:', xhr);
                    }
                });
            }
        });

        // Validate amount against invoice
        $('#amount').on('blur', function() {
            let amount = parseFloat($(this).val());
            let invoiceId = $('#offset_invoice').val();
            
            if (invoiceId && amount > 0) {
                $.ajax({
                    url: `/invoice/${invoiceId}/validate-recovery`,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        amount: amount,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (!response.valid) {
                            alert('Warning: Recovery amount exceeds outstanding invoice amount');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error validating amount:', xhr);
                    }
                });
            }
        });

        // Commissionable change handler
        $('#commissionable').on('change', function() {
            if ($(this).val() === 'Yes') {
                $('#return_type').prop('required', true);
            }
        });

        // Type change handler - adjust form based on recovery type
        $('#type').on('change', function() {
            let typeId = $(this).val();
            let typeText = $('#type option:selected').text();
            
            // Adjust narration placeholder based on type
            let placeholders = {
                'Payment': 'Payment received for...',
                'Refund': 'Refund issued for...',
                'Credit Note': 'Credit note applied for...',
                'Reversal': 'Reversal of...',
                'Adjustment': 'Adjustment made for...'
            };
            
            if (placeholders[typeText]) {
                $('#narration').attr('placeholder', placeholders[typeText]);
            }
        });
    });
</script>
@endsection