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
                            <a href="{{ route('client.advances.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fa fa-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>
                    <div class="card-body custom-scrollbar">
                        <form id="advanceForm" action="{{ route('property.advances.update', Crypt::encrypt($advance->id)) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="advanceType" class="form-label">Advance Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="advanceType" name="advance_type_id" required>
                                        <option value="">Select Advance Type</option>
                                        <option value="1" {{ old('advance_type_id', $advance->advance_type_id) == 1 ? 'selected' : '' }}>Expense Advance</option>
                                        <option value="2" {{ old('advance_type_id', $advance->advance_type_id) == 2 ? 'selected' : '' }}>Client Payment Advance</option>
                                        <option value="3" {{ old('advance_type_id', $advance->advance_type_id) == 3 ? 'selected' : '' }}>Overpayment Advance</option>
                                    </select>
                                    @error('advance_type_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="client" class="form-label">Client <span class="text-danger">*</span></label>
                                    <select class="form-select" id="client" name="client_id" required>
                                        <option value="">Loading clients...</option>
                                    </select>
                                    @error('client_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="property" class="form-label">Property <span class="text-danger">*</span></label>
                                    <select class="form-select" id="property" name="property_id" required>
                                        <option value="">Loading properties...</option>
                                    </select>
                                    @error('property_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Requisitions Section -->
                            <div id="requisitionsSection">
                                <h5 class="border-bottom pb-2 mb-3 mt-4">Requisition Details</h5>

                                <!-- Expense Category (Expense Advance only) -->
                                <div class="row field-group" id="expenseCategoryGroup" style="display: none;">
                                    <div class="col-md-4 mb-3">
                                        <label for="expenseCategory" class="form-label">Expense Category <span class="text-danger">*</span></label>
                                        <select class="form-select" id="expenseCategory" name="expense_category">
                                            <option value="">Select Category</option>
                                            <option value="repairs_maintenance" {{ old('expense_category', $advance->expense_category) == 'repairs_maintenance' ? 'selected' : '' }}>Repairs and Maintenance</option>
                                            <option value="caretaker_salaries" {{ old('expense_category', $advance->expense_category) == 'caretaker_salaries' ? 'selected' : '' }}>Caretaker Salaries</option>
                                            <option value="garbage_collection" {{ old('expense_category', $advance->expense_category) == 'garbage_collection' ? 'selected' : '' }}>Garbage Collection</option>
                                            <option value="electricity_bills" {{ old('expense_category', $advance->expense_category) == 'electricity_bills' ? 'selected' : '' }}>Electricity Bills</option>
                                            <option value="water_bills" {{ old('expense_category', $advance->expense_category) == 'water_bills' ? 'selected' : '' }}>Water Bills</option>
                                            <option value="printing_photocopying" {{ old('expense_category', $advance->expense_category) == 'printing_photocopying' ? 'selected' : '' }}>Printing and Photocopying</option>
                                            <option value="refund_tenant_deposits" {{ old('expense_category', $advance->expense_category) == 'refund_tenant_deposits' ? 'selected' : '' }}>Refund of Tenant Deposits</option>
                                            <option value="eviction_costs" {{ old('expense_category', $advance->expense_category) == 'eviction_costs' ? 'selected' : '' }}>Eviction Costs</option>
                                            <option value="locking_devices" {{ old('expense_category', $advance->expense_category) == 'locking_devices' ? 'selected' : '' }}>Locking Devices</option>
                                            <option value="security_services" {{ old('expense_category', $advance->expense_category) == 'security_services' ? 'selected' : '' }}>Security Services</option>
                                        </select>
                                        @error('expense_category')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Cheque Charge (KES)</label>
                                        <input type="number" class="form-control" id="chequeChargeExpense" name="charge_amount" step="0.01" value="{{ old('charge_amount', $advance->charge_amount) }}" readonly>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Charge Billed To</label>
                                        <input type="text" class="form-control" id="chargeBilledTo" name="charge_billed_to" value="{{ old('charge_billed_to', $advance->charge_billed_to) }}" readonly>
                                    </div>
                                </div>

                                <!-- Payment Method (Client Payment only) -->
                                <div class="row field-group" id="paymentMethodGroup" style="display: none;">
                                    <div class="col-md-6 mb-3">
                                        <label for="paymentMethod" class="form-label">Payment Method <span class="text-danger">*</span></label>
                                        <select class="form-select" id="paymentMethod" name="payment_method">
                                            <option value="">Select Method</option>
                                            <option value="bank_transfer" {{ old('payment_method', $advance->payment_method) == 'bank_transfer' ? 'selected' : '' }}>Direct Bank Transfer</option>
                                            <option value="alternative" {{ old('payment_method', $advance->payment_method) == 'alternative' ? 'selected' : '' }}>Alternative Payment Instructions</option>
                                        </select>
                                        @error('payment_method')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3" id="paymentInstructionsGroup" style="display: none;">
                                        <label class="form-label">Payment Instructions</label>
                                        <textarea class="form-control" id="paymentInstructions" name="payment_instructions" rows="2">{{ old('payment_instructions', $advance->payment_instructions) }}</textarea>
                                    </div>
                                </div>

                                <!-- Dates -->
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

                                <!-- Amount & Interest -->
                                <div class="row field-group" id="amountInterestGroup" style="display: none;">
                                    <div class="col-md-4 mb-3">
                                        <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" value="{{ old('amount', $advance->amount) }}">
                                        @error('amount')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="interestRate" class="form-label">Interest Rate (%)</label>
                                        <input type="number" class="form-control" id="interestRate" name="interest_rate" step="0.01" min="0" value="{{ old('interest_rate', $advance->interest_rate ?? '0.00') }}">
                                        @error('interest_rate')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3" id="interestInfoGroup" style="display: none;">
                                        <label class="form-label">Interest Info</label>
                                        <div class="p-2 bg-light border rounded small" id="interestCalcInfo">-</div>
                                    </div>
                                </div>

                                <!-- Repayment (Overpayment only) -->
                                <div class="row field-group" id="repaymentGroup" style="display: none;">
                                    <div class="col-md-4 mb-3">
                                        <label for="months" class="form-label">Repayment Months <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="months" name="repayment_months" min="1" value="{{ old('repayment_months', $advance->repayment_months ?? 3) }}">
                                        @error('repayment_months')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="paymentStartDate" class="form-label">Payment Start Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="paymentStartDate" name="principal_start_date" value="{{ old('principal_start_date', $advance->principal_start_date ? $advance->principal_start_date->format('Y-m-d') : '') }}">
                                        @error('principal_start_date')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="p-2 bg-light border rounded small mt-4">Default: 3 months. Changes require authorization.</div>
                                    </div>
                                </div>

                                <!-- Cheque Charge (Client Payment & Overpayment) -->
                                <div class="row field-group" id="chequeChargeGroup" style="display: none;">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Cheque Processing Charge (KES)</label>
                                        <input type="number" class="form-control" id="chequeChargeGeneral" name="charge_amount" step="0.01" value="{{ old('charge_amount', $advance->charge_amount ?? 100) }}" readonly>
                                    </div>
                                </div>

                                <!-- Narration -->
                                <div class="row field-group" id="narrationGroup" style="display: none;">
                                    <div class="col-md-12 mb-3">
                                        <label for="narration" class="form-label">Narration <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="narration" name="narration" rows="3">{{ old('narration', $advance->narration) }}</textarea>
                                        @error('narration')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
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
    const existingData = {
        client_id: {{ $advance->client_id }},
        property_id: {{ $advance->property_id }},
        advance_type_id: {{ $advance->advance_type_id }}
    };

    // Cheque charge mapping by expense category
    const chequeCharges = {
        'repairs_maintenance': { charge: 100, billed_to: 'Client' },
        'caretaker_salaries': { charge: 150, billed_to: 'Client' },
        'garbage_collection': { charge: 100, billed_to: 'Client' },
        'electricity_bills': { charge: 100, billed_to: 'Client' },
        'water_bills': { charge: 100, billed_to: 'Client' },
        'printing_photocopying': { charge: 100, billed_to: 'Client' },
        'refund_tenant_deposits': { charge: 150, billed_to: 'Tenant' },
        'eviction_costs': { charge: 100, billed_to: 'Client' },
        'locking_devices': { charge: 100, billed_to: 'Client' },
        'security_services': { charge: 100, billed_to: 'Client' }
    };

    loadClients();

    // Initialize Select2
    setTimeout(function() {
        $('#client, #property, #advanceType, #expenseCategory, #paymentMethod').select2({
            allowClear: true,
            width: '100%'
        });

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

                if (existingData.client_id) {
                    loadProperties(existingData.client_id);
                }
            },
            error: function(xhr) {
                console.error('Error loading clients:', xhr);
                toastr.error('Failed to load clients');
            }
        });
    }

    // Load Properties (excludes merged/inactive source properties)
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
                        const mergedTag = property.is_merged ? ' (Merged)' : '';
                        propertySelect.append(`<option value="${property.id}" ${selected}>${property.reference_number} - ${property.property_name}${mergedTag}</option>`);
                    });
                    propertySelect.prop('disabled', false);
                }
                propertySelect.trigger('change.select2');
            },
            error: function(xhr) {
                console.error('Error loading properties:', xhr);
                toastr.error('Failed to load properties');
            }
        });
    }

    // Expense Category change - auto-set cheque charge
    $('#expenseCategory').on('change', function() {
        const category = $(this).val();
        if (category && chequeCharges[category]) {
            $('#chequeChargeExpense').val(chequeCharges[category].charge);
            $('#chargeBilledTo').val(chequeCharges[category].billed_to);
        } else {
            $('#chequeChargeExpense').val('');
            $('#chargeBilledTo').val('');
        }
    });

    // Payment Method change
    $('#paymentMethod').on('change', function() {
        if ($(this).val() === 'alternative') {
            $('#paymentInstructionsGroup').show();
        } else {
            $('#paymentInstructionsGroup').hide();
        }
    });

    // Interest calculation info
    function calculateInterestInfo() {
        const advanceType = $('#advanceType').val();
        const issuedDate = $('#issuedDate').val();
        const amount = parseFloat($('#amount').val()) || 0;
        const rate = parseFloat($('#interestRate').val()) || 3;

        if (!issuedDate || amount <= 0) {
            $('#interestCalcInfo').html('-');
            return;
        }

        const dateObj = new Date(issuedDate);
        const day = dateObj.getDate();
        const lastDay = new Date(dateObj.getFullYear(), dateObj.getMonth() + 1, 0).getDate();
        const remainingDays = lastDay - day;

        if (advanceType === '2') {
            if (day < 20) {
                const interest = amount * rate / 100;
                $('#interestCalcInfo').html(`Before 20th: ${rate}% full = KSh ${interest.toLocaleString(undefined, {minimumFractionDigits:2})}`);
            } else if (day >= 20 && day <= 25) {
                const prorated = (amount * rate / 100) * (remainingDays / lastDay);
                $('#interestCalcInfo').html(`20th-25th: ${rate}% prorated (${remainingDays}/${lastDay} days) = KSh ${prorated.toLocaleString(undefined, {minimumFractionDigits:2})}`);
            } else {
                $('#interestCalcInfo').html('After 25th: No interest charged');
                $('#interestRate').val(0);
            }
        } else if (advanceType === '3') {
            if (day < 25) {
                const prorated = (amount * rate / 100) * (remainingDays / lastDay);
                $('#interestCalcInfo').html(`Before 25th: ${rate}% prorated (${remainingDays}/${lastDay} days) = KSh ${prorated.toLocaleString(undefined, {minimumFractionDigits:2})}`);
            } else {
                const interest = amount * rate / 100;
                $('#interestCalcInfo').html(`${rate}% monthly (reducing balance) = KSh ${interest.toLocaleString(undefined, {minimumFractionDigits:2})}/month`);
            }
        }
    }

    $('#issuedDate, #amount, #interestRate').on('change keyup', calculateInterestInfo);

    // Handle Advance Type change to show/hide fields
    $('#advanceType').on('change', function() {
        const advanceType = $(this).val();

        if (!advanceType) {
            $('#requisitionsSection').hide();
            return;
        }

        $('#requisitionsSection').show();
        $('.field-group').hide();

        // Remove required from conditional fields
        $('#expenseCategory, #paymentMethod, #requisitionDate, #issuedDate, #amount, #months, #paymentStartDate, #narration').prop('required', false);
        $('#interestRate').prop('readonly', false);

        switch(advanceType) {
            case '1': // Expense Advance - No interest
                $('#expenseCategoryGroup, #requisitionDateGroup, #amountInterestGroup, #narrationGroup').show();
                $('#expenseCategory, #requisitionDate, #issuedDate, #amount, #narration').prop('required', true);
                $('#interestRate').val(0).prop('readonly', true);
                $('#interestInfoGroup').hide();
                break;

            case '2': // Client Payment Advance
                $('#paymentMethodGroup, #requisitionDateGroup, #amountInterestGroup, #chequeChargeGroup, #narrationGroup, #interestInfoGroup').show();
                $('#paymentMethod, #requisitionDate, #issuedDate, #amount, #narration').prop('required', true);
                $('#chequeChargeGeneral').val(100);
                if ($('#paymentMethod').val() === 'alternative') {
                    $('#paymentInstructionsGroup').show();
                }
                calculateInterestInfo();
                break;

            case '3': // Overpayment Advance
                $('#requisitionDateGroup, #amountInterestGroup, #repaymentGroup, #chequeChargeGroup, #narrationGroup, #interestInfoGroup').show();
                $('#requisitionDate, #issuedDate, #amount, #months, #paymentStartDate, #narration').prop('required', true);
                $('#months').val($('#months').val() || 3);
                $('#chequeChargeGeneral').val(100);
                calculateInterestInfo();
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

        propertySelect.empty().append('<option value="">Please select client first</option>');
        propertySelect.prop('disabled', true);

        if (clientId) {
            loadProperties(clientId);
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
                    window.location.href = '{{ route("client.advances.index") }}';
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
