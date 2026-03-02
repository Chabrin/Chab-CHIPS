@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card card-no-border">
                    <div class="card-header pb-3 d-flex justify-content-between align-items-center">
                        <h5>Edit Client Advance</h5>
                        <div class="d-flex gap-2">
                            <button type="submit" form="advanceForm" class="btn btn-primary btn-sm">
                                <i class="fa fa-save"></i> Update Advance
                            </button>
                            <a href="{{ route('property.advances.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fa fa-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>
                    <div class="card-body custom-scrollbar">
                        <form id="advanceForm" action="{{ route('property.advances.update', Crypt::encrypt($advance->id)) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="advanceType" class="form-label">Advance Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="advanceType" name="advance_type_id" required>
                                        <option value="">Select Advance Type</option>
                                        <option value="1" {{ old('advance_type_id', $advance->advance_type_id) == 1 ? 'selected' : '' }}>New Advance</option>
                                        <option value="2" {{ old('advance_type_id', $advance->advance_type_id) == 2 ? 'selected' : '' }}>Top Up Advance</option>
                                        <option value="3" {{ old('advance_type_id', $advance->advance_type_id) == 3 ? 'selected' : '' }}>Reschedule Advance</option>
                                        <option value="4" {{ old('advance_type_id', $advance->advance_type_id) == 4 ? 'selected' : '' }}>Lumpsum Advance</option>
                                    </select>
                                    @error('advance_type_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="client" class="form-label">Client <span class="text-danger">*</span></label>
                                    <select class="form-select" id="client" name="client_id" required>
                                        <option value="">Loading clients...</option>
                                    </select>
                                    @error('client_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="property" class="form-label">Property <span class="text-danger">*</span></label>
                                    <select class="form-select" id="property" name="property_id" required>
                                        <option value="">Loading properties...</option>
                                    </select>
                                    @error('property_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="miscCharge" class="form-label">Miscellaneous Charge <span class="text-danger">*</span></label>
                                    <select class="form-select" id="miscCharge" name="miscallenous_charge_id" required>
                                        <option value="">Select Charge</option>
                                    </select>
                                    @error('miscallenous_charge_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="chargeAmount" class="form-label">Charge Amount <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="chargeAmount" name="charge_amount" step="0.01" min="0" value="{{ old('charge_amount', $advance->charge_amount) }}" required>
                                    @error('charge_amount')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Clients Advances Requisitions Section -->
                            <div id="requisitionsSection">
                                <h5 class="border-bottom pb-2 mb-3 mt-4">Clients Advances Requisitions</h5>

                                <!-- Previous Reference (Top Up & Reschedule only) -->
                                <div class="row field-group" id="previousReferenceGroup" style="display: none;">
                                    <div class="col-md-6 mb-3">
                                        <label for="previousReference" class="form-label">Previous Reference <span class="text-danger">*</span></label>
                                        <select class="form-select" id="previousReference" name="previous_advance_id">
                                            <option value="">Please select</option>
                                        </select>
                                        @error('previous_advance_id')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Requisition Date & Issued Date (All except Reschedule) -->
                                <div class="row field-group" id="requisitionDateGroup" style="display: none;">
                                    <div class="col-md-6 mb-3">
                                        <label for="requisitionDate" class="form-label">Requisition Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="requisitionDate" name="requisition_date" value="{{ old('requisition_date', $advance->requisition_date ? $advance->requisition_date->format('Y-m-d') : '') }}">
                                        @error('requisition_date')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="issuedDate" class="form-label">Issued Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="issuedDate" name="disbursement_date" value="{{ old('disbursement_date', $advance->disbursement_date ? $advance->disbursement_date->format('Y-m-d') : '') }}">
                                        @error('disbursement_date')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Months & Payment Start Date (New, Top Up, Lumpsum) -->
                                <div class="row field-group" id="monthsPaymentGroup" style="display: none;">
                                    <div class="col-md-6 mb-3">
                                        <label for="months" class="form-label">Months <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="months" name="repayment_months" min="1" value="{{ old('repayment_months', $advance->repayment_months) }}">
                                        @error('repayment_months')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="paymentStartDate" class="form-label">Payment Start Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="paymentStartDate" name="principal_start_date" value="{{ old('principal_start_date', $advance->principal_start_date ? $advance->principal_start_date->format('Y-m-d') : '') }}">
                                        @error('principal_start_date')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Payment Start Date only (Reschedule) -->
                                <div class="row field-group" id="reschedulePaymentGroup" style="display: none;">
                                    <div class="col-md-6 mb-3">
                                        <label for="reschedulePaymentStartDate" class="form-label">Payment Start Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="reschedulePaymentStartDate" name="principal_start_date" value="{{ old('principal_start_date', $advance->principal_start_date ? $advance->principal_start_date->format('Y-m-d') : '') }}">
                                        @error('principal_start_date')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Top Up (Top Up only) -->
                                <div class="row field-group" id="topUpGroup" style="display: none;">
                                    <div class="col-md-6 mb-3">
                                        <label for="topUp" class="form-label">Top up <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="topUp" name="top_up" step="0.01" min="0" value="{{ old('top_up', $advance->top_up) }}">
                                        @error('top_up')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Amount & Interest (New, Lumpsum) -->
                                <div class="row field-group" id="amountInterestGroup" style="display: none;">
                                    <div class="col-md-6 mb-3">
                                        <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" value="{{ old('amount', $advance->amount) }}">
                                        @error('amount')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="interestRate" class="form-label">Interest (%)</label>
                                        <input type="number" class="form-control" id="interestRate" name="interest_rate" step="0.01" min="0" value="{{ old('interest_rate', $advance->interest_rate ?? '0.00') }}">
                                        @error('interest_rate')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Interest only (Top Up) -->
                                <div class="row field-group" id="interestOnlyGroup" style="display: none;">
                                    <div class="col-md-6 mb-3">
                                        <label for="interestRateTopUp" class="form-label">Interest (%)</label>
                                        <input type="number" class="form-control" id="interestRateTopUp" name="interest_rate" step="0.01" min="0" value="{{ old('interest_rate', $advance->interest_rate ?? '0.00') }}">
                                        @error('interest_rate')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Narration (All except Reschedule) -->
                                <div class="row field-group" id="narrationGroup" style="display: none;">
                                    <div class="col-md-12 mb-3">
                                        <label for="narration" class="form-label">Narration <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="narration" name="narration" rows="3">{{ old('narration', $advance->narration) }}</textarea>
                                        @error('narration')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <!-- One Off (All types) -->
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">One Off</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="oneOff" name="one_off" value="1" {{ old('one_off', $advance->one_off) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="oneOff">
                                                Yes
                                            </label>
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
    // Store existing values
    const existingData = {
        client_id: {{ $advance->client_id }},
        property_id: {{ $advance->property_id }},
        miscallenous_charge_id: {{ $advance->miscallenous_charge_id }},
        previous_advance_id: {{ $advance->previous_advance_id ?? 'null' }},
        advance_type_id: {{ $advance->advance_type_id }}
    };
    
    // Load dropdowns on page load
    loadClients();
    loadMiscCharges();
    
    // Initialize Select2
    setTimeout(function() {
        $('#client').select2({
            placeholder: 'Select Client',
            allowClear: true,
            width: '100%'
        });
        
        $('#property').select2({
            placeholder: 'Select Property',
            allowClear: true,
            width: '100%'
        });
        
        $('#miscCharge').select2({
            placeholder: 'Select Charge',
            allowClear: true,
            width: '100%'
        });
        
        $('#advanceType').select2({
            placeholder: 'Select Advance Type',
            allowClear: true,
            width: '100%'
        });
        
        $('#previousReference').select2({
            placeholder: 'Select Previous Reference',
            allowClear: true,
            width: '100%'
        });
        
        // Trigger advance type change to show relevant fields
        setTimeout(function() {
            $('#advanceType').trigger('change');
        }, 500);
    }, 200);
    
    // Load Clients
    function loadClients() {
        $.ajax({
            url: '/ajax/clients/list',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                const select = $('#client');
                select.empty().append('<option value="">Please select</option>');
                let clients = response.data || response.clients || [];
                if (clients.length > 0) {
                    let lanClients = clients.filter(function(client) {
                        return client.reference_number && client.reference_number.startsWith('LAN');
                    });
                    
                    lanClients.forEach(function(client) {
                        const selected = client.id === existingData.client_id ? 'selected' : '';
                        select.append(`<option value="${client.id}" ${selected}>${client.names} (${client.reference_number})</option>`);
                    });
                }
                select.trigger('change.select2');
                
                // Load properties for the selected client
                if (existingData.client_id) {
                    loadProperties(existingData.client_id);
                    loadPreviousAdvances(existingData.client_id);
                }
            },
            error: function(xhr) {
                console.error('Error loading clients:', xhr);
                toastr.error('Failed to load clients');
            }
        });
    }

    // Load Miscellaneous Charges
    function loadMiscCharges() {
        $.ajax({
            url: '{{ route("property.advances.misc_charges") }}',
            method: 'GET',
            dataType: 'json',
            success: function(charges) {
                const select = $('#miscCharge');
                select.empty().append('<option value="">Please select</option>');
                if (charges && charges.length > 0) {
                    charges.forEach(function(charge) {
                        const selected = charge.id === existingData.miscallenous_charge_id ? 'selected' : '';
                        select.append(`<option value="${charge.id}" ${selected}>${charge.text}</option>`);
                    });
                }
                select.trigger('change.select2');
            },
            error: function(xhr, status, error) {
                console.error('Error loading charges:', xhr);
                toastr.error('Failed to load miscellaneous charges');
            }
        });
    }

    // Load Properties
    function loadProperties(clientId) {
        const propertySelect = $('#property');
        
        $.ajax({
            url: `/client/${clientId}/properties`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                propertySelect.empty().append('<option value="">Please select</option>');
                if (response.properties && response.properties.length > 0) {
                    response.properties.forEach(function(property) {
                        const selected = property.id === existingData.property_id ? 'selected' : '';
                        propertySelect.append(`<option value="${property.id}" ${selected}>${property.reference_number} - ${property.property_name}</option>`);
                    });
                    propertySelect.prop('disabled', false);
                } else {
                    propertySelect.append('<option value="">No properties found</option>');
                }
                propertySelect.trigger('change.select2');
            },
            error: function(xhr) {
                console.error('Error loading properties:', xhr);
                propertySelect.empty().append('<option value="">Error loading properties</option>');
                propertySelect.trigger('change.select2');
                toastr.error('Failed to load properties');
            }
        });
    }

    // Load Previous Advances
    function loadPreviousAdvances(clientId) {
        const previousRefSelect = $('#previousReference');
        
        $.ajax({
            url: '{{ route("property.advances.client-previous") }}',
            method: 'GET',
            data: { client_id: clientId },
            dataType: 'json',
            success: function(advances) {
                previousRefSelect.empty().append('<option value="">Please select</option>');
                if (advances && advances.length > 0) {
                    advances.forEach(function(advance) {
                        const selected = advance.id === existingData.previous_advance_id ? 'selected' : '';
                        previousRefSelect.append(`<option value="${advance.id}" ${selected}>${advance.reference_number || 'Advance #' + advance.id}</option>`);
                    });
                    previousRefSelect.prop('disabled', false);
                } else {
                    previousRefSelect.append('<option value="">No previous advances found</option>');
                }
                previousRefSelect.trigger('change.select2');
            },
            error: function(xhr) {
                console.error('Error loading previous advances:', xhr);
                previousRefSelect.empty().append('<option value="">Error loading previous advances</option>');
                previousRefSelect.trigger('change.select2');
            }
        });
    }

    // Handle Advance Type change to show/hide fields
    $('#advanceType').on('change', function() {
        const advanceType = $(this).val();
        
        // Hide requisitions section if no type selected
        if (!advanceType) {
            $('#requisitionsSection').hide();
            return;
        }
        
        // Show requisitions section
        $('#requisitionsSection').show();
        
        // Hide all optional groups first
        $('.field-group').hide();
        
        // Remove required attributes from all conditional fields
        $('#previousReference, #requisitionDate, #issuedDate, #months, #paymentStartDate, #reschedulePaymentStartDate, #topUp, #amount, #narration').prop('required', false);
        
        switch(advanceType) {
            case '1': // New Advance
                $('#requisitionDateGroup, #monthsPaymentGroup, #amountInterestGroup, #narrationGroup').show();
                $('#requisitionDate, #issuedDate, #months, #paymentStartDate, #amount, #narration').prop('required', true);
                break;
                
            case '2': // Top Up Advance
                $('#previousReferenceGroup, #requisitionDateGroup, #monthsPaymentGroup, #topUpGroup, #interestOnlyGroup, #narrationGroup').show();
                $('#previousReference, #requisitionDate, #issuedDate, #months, #paymentStartDate, #topUp, #narration').prop('required', true);
                break;
                
            case '3': // Reschedule Advance
                $('#previousReferenceGroup, #reschedulePaymentGroup').show();
                $('#previousReference, #reschedulePaymentStartDate').prop('required', true);
                break;
                
            case '4': // Lumpsum Advance
                $('#requisitionDateGroup, #monthsPaymentGroup, #amountInterestGroup, #narrationGroup').show();
                $('#requisitionDate, #issuedDate, #months, #paymentStartDate, #amount, #narration').prop('required', true);
                break;
                
            default:
                $('#requisitionsSection').hide();
                break;
        }
    });

    // Load Properties based on selected client
    $('#client').on('change', function() {
        const clientId = $(this).val();
        const propertySelect = $('#property');
        const previousRefSelect = $('#previousReference');
        
        // Reset property dropdown
        propertySelect.empty().append('<option value="">Please select client first</option>');
        propertySelect.prop('disabled', true);
        
        // Reset previous reference dropdown
        previousRefSelect.empty().append('<option value="">Please select</option>');
        previousRefSelect.prop('disabled', true);
        
        if (clientId) {
            loadProperties(clientId);
            loadPreviousAdvances(clientId);
        }
    });

    // Submit form with AJAX
    $('#advanceForm').submit(function(e) {
        e.preventDefault();
        
        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                toastr.success(response.message || 'Client advance updated successfully');
                setTimeout(function() {
                    window.location.href = '{{ route("property.advances.index") }}';
                }, 1500);
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    $('.invalid-feedback').remove();
                    
                    $.each(errors, function(field, messages) {
                        const input = $(`[name="${field}"]`);
                        input.addClass('is-invalid');
                        input.after(`<div class="invalid-feedback d-block">${messages[0]}</div>`);
                    });
                } else {
                    toastr.error(xhr.responseJSON?.message || 'An error occurred');
                }
            }
        });
    });
});
</script>
@endsection
