@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/chabrin.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
    .form-label-sm {
        font-size: 0.8125rem;
        font-weight: 500;
        margin-bottom: 3px;
        display: block;
    }

    .form-control-sm,
    .form-select-sm {
        font-size: 0.8125rem;
        padding: 0.3rem 0.5rem;
        height: calc(1.5em + 0.6rem + 2px);
    }

    .info-box {
        background-color: #e3f2fd;
        border-left: 3px solid #2196F3;
        padding: 8px 12px;
        margin-bottom: 12px;
        font-size: 0.8125rem;
        color: #1976d2;
    }

    .section-header {
        font-size: 0.875rem;
        font-weight: 600;
        color: #495057;
        margin: 15px 0 10px 0;
        padding-bottom: 5px;
        border-bottom: 1px solid #dee2e6;
    }

    .d-none {
        display: none !important;
    }

    /* Select2 styling to match form controls */
    .select2-container--default .select2-selection--single {
        height: calc(1.5em + 0.6rem + 2px);
        padding: 0.3rem 0.5rem;
        font-size: 0.8125rem;
        border: 1px solid #ced4da;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: calc(1.5em + 0.6rem);
        padding-left: 0;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: calc(1.5em + 0.6rem + 2px);
    }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex justify-content-between align-items-center" style="padding: 10px 15px;">
                        <h5 class="mb-0" style="font-size: 1rem;">New Advance Requisition</h5>
                        <div class="d-flex gap-2">
                            <button type="button" id="submitBtn" class="btn btn-primary btn-sm">
                                <i class="fa fa-save"></i> Submit
                            </button>
                            <a href="{{ route('client.advances.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fa fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body" style="padding: 15px;">
                        <form id="advanceForm">
                            @csrf

                            <div class="row g-2 mb-2">
                                <div class="col-md-3">
                                    <label class="form-label-sm">Advance Type <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="advanceType" name="advance_type_id" required>
                                        <option value="">Select Type</option>
                                        <option value="1">Expense Advance</option>
                                        <option value="2">Client Payment Advance</option>
                                        <option value="3">Overpayment Advance</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label-sm">Client <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="client" name="client_id" required>
                                        <option value="">Loading...</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label-sm">Property <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="property" name="property_id" required disabled>
                                        <option value="">Select client first</option>
                                    </select>
                                </div>
                            </div>

                            <div id="eligibilityInfo" class="info-box d-none">
                                <strong>Client Eligibility:</strong> <span id="eligibilityText"></span>
                            </div>

                            <div id="dynamicSection" class="d-none">
                                <div class="section-header">Requisition Details</div>

                                <!-- Expense Category (Expense Advance only) -->
                                <div class="row g-2 mb-2 d-none" id="rowExpenseCategory">
                                    <div class="col-md-6">
                                        <label class="form-label-sm">Expense Category <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" id="expenseCategory" name="expense_category">
                                            <option value="">Select Category</option>
                                            <option value="repairs_maintenance">Repairs and Maintenance</option>
                                            <option value="caretaker_salaries">Caretaker Salaries</option>
                                            <option value="garbage_collection">Garbage Collection</option>
                                            <option value="electricity_bills">Electricity Bills</option>
                                            <option value="water_bills">Water Bills</option>
                                            <option value="printing_photocopying">Printing and Photocopying</option>
                                            <option value="refund_tenant_deposits">Refund of Tenant Deposits</option>
                                            <option value="eviction_costs">Eviction Costs</option>
                                            <option value="locking_devices">Locking Devices</option>
                                            <option value="security_services">Security Services</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label-sm">Cheque Charge (KES)</label>
                                        <input type="number" class="form-control form-control-sm" id="chequeCharge" name="charge_amount" step="0.01" readonly>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label-sm">Charge Billed To</label>
                                        <input type="text" class="form-control form-control-sm" id="chargeBilledTo" name="charge_billed_to" readonly>
                                    </div>
                                </div>

                                <!-- Payment Method (Client Payment Advance only) -->
                                <div class="row g-2 mb-2 d-none" id="rowPaymentMethod">
                                    <div class="col-md-6">
                                        <label class="form-label-sm">Payment Method <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" id="paymentMethod" name="payment_method">
                                            <option value="">Select Method</option>
                                            <option value="bank_transfer">Direct Bank Transfer</option>
                                            <option value="alternative">Alternative Payment Instructions</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 d-none" id="rowPaymentInstructions">
                                        <label class="form-label-sm">Payment Instructions</label>
                                        <textarea class="form-control form-control-sm" id="paymentInstructions" name="payment_instructions" rows="2" placeholder="Enter alternative payment instructions"></textarea>
                                    </div>
                                </div>

                                <!-- Dates Row -->
                                <div class="row g-2 mb-2 d-none" id="rowDates">
                                    <div class="col-md-4">
                                        <label class="form-label-sm">Requisition Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" id="requisitionDate" name="requisition_date" value="{{ date('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label-sm">Issued Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" id="issuedDate" name="disbursement_date" value="{{ date('Y-m-d') }}">
                                    </div>
                                </div>

                                <!-- Amount Row -->
                                <div class="row g-2 mb-2 d-none" id="rowAmount">
                                    <div class="col-md-4">
                                        <label class="form-label-sm">Amount <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control form-control-sm" id="amount" name="amount" step="0.01" min="0">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label-sm">Percentage (%)</label>
                                        <input type="number" class="form-control form-control-sm" id="interestRate" name="interest_rate" step="0.01" min="0" value="3">
                                    </div>
                                    <div class="col-md-4 d-none" id="interestInfoCol">
                                        <label class="form-label-sm">Interest Info</label>
                                        <div class="info-box" id="interestCalcInfo" style="margin-bottom:0; padding:6px 10px;">-</div>
                                    </div>
                                </div>

                                <!-- Repayment Row (Overpayment only) -->
                                <div class="row g-2 mb-2 d-none" id="rowRepayment">
                                    <div class="col-md-4">
                                        <label class="form-label-sm">Repayment Months <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control form-control-sm" id="months" name="repayment_months" min="1" max="36" value="3">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label-sm">Payment Start Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" id="paymentStart" name="principal_start_date" value="{{ date('Y-m-d', strtotime('+1 month')) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <div class="info-box" style="margin-top:22px; margin-bottom:0; padding:6px 10px;">
                                            Default: 3 months. Changes require authorization.
                                        </div>
                                    </div>
                                </div>

                                <!-- Cheque Charge for Client Payment & Overpayment -->
                                <div class="row g-2 mb-2 d-none" id="rowChequeCharge">
                                    <div class="col-md-4">
                                        <label class="form-label-sm">Cheque Processing Charge (KES)</label>
                                        <input type="number" class="form-control form-control-sm" id="chequeChargeGeneral" name="charge_amount" step="0.01" value="100" readonly>
                                    </div>
                                </div>

                                <!-- Narration -->
                                <div class="row g-2 mb-2 d-none" id="rowNarration">
                                    <div class="col-md-12">
                                        <label class="form-label-sm">Narration <span class="text-danger">*</span></label>
                                        <textarea class="form-control form-control-sm" id="narration" name="narration" rows="2"></textarea>
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
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        // Initialize Select2
        $('#client, #property, #advanceType, #expenseCategory, #paymentMethod').select2({
            placeholder: 'Select option',
            allowClear: true,
            width: '100%'
        });

        // Load initial data
        loadClients();

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

        // Load Clients
        function loadClients() {
            $.ajax({
                url: '/ajax/clients/list',
                method: 'GET',
                success: function(response) {
                    const select = $('#client');
                    select.empty().append('<option value="">Select Client</option>');
                    const clients = response.data || response.clients || [];

                    // Filter LAN clients
                    const lanClients = clients.filter(c => c.reference_number && c.reference_number.startsWith('LAN'));
                    lanClients.forEach(function(client) {
                        select.append(`<option value="${client.id}">${client.names} (${client.reference_number})</option>`);
                    });
                    select.trigger('change.select2');
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to load clients'
                    });
                }
            });
        }

        // Client selection - load properties and eligibility
        $('#client').on('change', function() {
            const clientId = $(this).val();
            const propertySelect = $('#property');

            propertySelect.empty().append('<option value="">Select client first</option>').prop('disabled', true);
            $('#eligibilityInfo').addClass('d-none');

            if (!clientId) return;

            // Load properties (excludes merged/inactive source properties)
            $.ajax({
                url: `/client/${clientId}/properties`,
                method: 'GET',
                success: function(response) {
                    propertySelect.empty().append('<option value="">Select Property</option>');
                    if (response.properties && response.properties.length > 0) {
                        response.properties.forEach(p => {
                            const mergedTag = p.is_merged ? ' (Merged)' : '';
                            propertySelect.append(`<option value="${p.id}">${p.reference_number} - ${p.property_name}${mergedTag}</option>`);
                        });
                        propertySelect.prop('disabled', false);
                    }
                    propertySelect.trigger('change.select2');
                }
            });

            // Check eligibility
            $.ajax({
                url: '{{ route("property.advances.check-eligibility") }}',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { client_id: clientId },
                success: function(response) {
                    if (response.success) {
                        const data = response.data || response;
                        const avgReturns = parseFloat(data.average_monthly_returns || data.avg_returns || 0);
                        const outstanding = parseFloat(data.outstanding_balance || 0);
                        const maxAmount = parseFloat(data.maximum_advance_amount || data.max_advance_amount || 0);
                        const activeCount = parseInt(data.active_advances_count || 0);

                        $('#eligibilityText').html(
                            `Avg Returns: KSh ${avgReturns.toLocaleString()} | Outstanding: KSh ${outstanding.toLocaleString()} | ` +
                            `Max Advance: KSh ${maxAmount.toLocaleString()} | Active: ${activeCount}`
                        );
                        $('#eligibilityInfo').removeClass('d-none');
                    }
                }
            });
        });

        // Expense Category change - auto-set cheque charge
        $('#expenseCategory').on('change', function() {
            const category = $(this).val();
            if (category && chequeCharges[category]) {
                $('#chequeCharge').val(chequeCharges[category].charge);
                $('#chargeBilledTo').val(chequeCharges[category].billed_to);
            } else {
                $('#chequeCharge').val('');
                $('#chargeBilledTo').val('');
            }
        });

        // Payment Method change - show/hide instructions
        $('#paymentMethod').on('change', function() {
            if ($(this).val() === 'alternative') {
                $('#rowPaymentInstructions').removeClass('d-none');
            } else {
                $('#rowPaymentInstructions').addClass('d-none');
            }
        });

        // Auto-calculate interest based on issued date for Client Payment & Overpayment
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

            if (advanceType === '2') { // Client Payment Advance
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
            } else if (advanceType === '3') { // Overpayment Advance
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

        // Advance Type change - show/hide fields
        $('#advanceType').on('change', function() {
            const type = $(this).val();

            if (!type) {
                $('#dynamicSection').addClass('d-none');
                return;
            }

            $('#dynamicSection').removeClass('d-none');

            // Hide all dynamic rows
            $('#rowExpenseCategory, #rowPaymentMethod, #rowDates, #rowAmount, #rowRepayment, #rowChequeCharge, #rowNarration, #interestInfoCol').addClass('d-none');

            // Reset required
            $('#expenseCategory, #paymentMethod, #requisitionDate, #issuedDate, #amount, #months, #paymentStart, #narration').prop('required', false);
            $('#interestRate').val(3);

            switch (type) {
                case '1': // Expense Advance - No interest
                    $('#rowExpenseCategory, #rowDates, #rowAmount, #rowNarration').removeClass('d-none');
                    $('#expenseCategory, #requisitionDate, #issuedDate, #amount, #narration').prop('required', true);
                    $('#interestRate').val(0).prop('readonly', true);
                    $('#interestCalcInfo').html('No interest on expense advances');
                    break;

                case '2': // Client Payment Advance
                    $('#rowPaymentMethod, #rowDates, #rowAmount, #rowChequeCharge, #rowNarration, #interestInfoCol').removeClass('d-none');
                    $('#paymentMethod, #requisitionDate, #issuedDate, #amount, #narration').prop('required', true);
                    $('#interestRate').prop('readonly', false);
                    $('#chequeChargeGeneral').val(100);
                    calculateInterestInfo();
                    break;

                case '3': // Overpayment Advance
                    $('#rowDates, #rowAmount, #rowRepayment, #rowChequeCharge, #rowNarration, #interestInfoCol').removeClass('d-none');
                    $('#requisitionDate, #issuedDate, #amount, #months, #paymentStart, #narration').prop('required', true);
                    $('#interestRate').prop('readonly', false);
                    $('#months').val(3);
                    $('#chequeChargeGeneral').val(100);
                    calculateInterestInfo();
                    break;
            }
        });

        // Submit button
        $('#submitBtn').on('click', function() {
            const form = $('#advanceForm');

            if (!form[0].checkValidity()) {
                form[0].reportValidity();
                return;
            }

            Swal.fire({
                title: 'Submit Advance Requisition?',
                text: 'Please confirm all details are correct',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Submit',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#2196F3',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (result.isConfirmed) {
                    submitForm();
                }
            });
        });

        // AJAX form submission
        function submitForm() {
            const formData = $('#advanceForm').serialize();

            Swal.fire({
                title: 'Processing...',
                text: 'Creating advance requisition',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: '{{ route("property.advances.store") }}',
                method: 'POST',
                data: formData,
                success: function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.message || 'Advance requisition created successfully',
                        confirmButtonColor: '#2196F3'
                    }).then(() => {
                        window.location.href = '{{ route("client.advances.index") }}';
                    });
                },
                error: function(xhr) {
                    Swal.close();

                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON.errors;
                        let errorHtml = '<ul style="text-align: left; font-size: 0.875rem;">';
                        $.each(errors, function(field, messages) {
                            errorHtml += `<li>${messages[0]}</li>`;
                        });
                        errorHtml += '</ul>';

                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Errors',
                            html: errorHtml,
                            confirmButtonColor: '#2196F3'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: xhr.responseJSON?.message || 'Failed to create advance requisition',
                            confirmButtonColor: '#2196F3'
                        });
                    }
                }
            });
        }
    });
</script>
@endsection