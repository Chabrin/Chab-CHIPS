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
                            <a href="{{ route('property.advances.index') }}" class="btn btn-secondary btn-sm">
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
                                        <option value="1">New Advance</option>
                                        <option value="2">Top Up Advance</option>
                                        <option value="3">Reschedule Advance</option>
                                        <option value="4">Lumpsum Advance</option>
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

                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label-sm">Miscellaneous Charge <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="miscCharge" name="miscallenous_charge_id" required>
                                        <option value="">Select Charge</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-sm">Charge Amount <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm" id="chargeAmount" name="charge_amount" step="0.01" min="0" required>
                                </div>
                            </div>

                            <div id="eligibilityInfo" class="info-box d-none">
                                <strong>Client Eligibility:</strong> <span id="eligibilityText"></span>
                            </div>

                            <div id="dynamicSection" class="d-none">
                                <div class="section-header">Requisition Details</div>

                                <div class="row g-2 mb-2 d-none" id="rowPreviousRef">
                                    <div class="col-md-6">
                                        <label class="form-label-sm">Previous Reference <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" id="previousReference" name="previous_advance_id">
                                            <option value="">Select</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row g-2 mb-2 d-none" id="rowDates">
                                    <div class="col-md-4">
                                        <label class="form-label-sm">Requisition Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" id="requisitionDate" name="requisition_date" value="{{ date('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label-sm">Issued Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" id="issuedDate" name="disbursement_date" value="{{ date('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label-sm">Grace Period (Months)</label>
                                        <input type="number" class="form-control form-control-sm" id="gracePeriod" name="grace_period" min="0" value="0">
                                    </div>
                                </div>

                                <div class="row g-2 mb-2 d-none" id="rowAmount">
                                    <div class="col-md-3">
                                        <label class="form-label-sm">Amount <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control form-control-sm" id="amount" name="amount" step="0.01" min="0">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label-sm">Interest Rate (%)</label>
                                        <input type="number" class="form-control form-control-sm" id="interestRate" name="interest_rate" step="0.01" min="0.5" value="3">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label-sm">Repayment Months <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control form-control-sm" id="months" name="repayment_months" min="3" max="36" value="12">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label-sm">Payment Start Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" id="paymentStart" name="principal_start_date" value="{{ date('Y-m-d', strtotime('+1 month')) }}">
                                    </div>
                                </div>

                                <div class="row g-2 mb-2 d-none" id="rowTopUp">
                                    <div class="col-md-3">
                                        <label class="form-label-sm">Top Up Amount <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control form-control-sm" id="topUpAmount" name="top_up" step="0.01" min="0">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label-sm">Interest Rate (%)</label>
                                        <input type="number" class="form-control form-control-sm" id="topUpInterest" name="interest_rate" step="0.01" min="0.5" value="3">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label-sm">Repayment Months <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control form-control-sm" id="topUpMonths" name="repayment_months" min="3" max="36" value="12">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label-sm">Payment Start Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" id="topUpPaymentStart" name="principal_start_date" value="{{ date('Y-m-d', strtotime('+1 month')) }}">
                                    </div>
                                </div>

                                <div class="row g-2 mb-2 d-none" id="rowReschedule">
                                    <div class="col-md-4">
                                        <label class="form-label-sm">New Payment Start Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" id="reschedulePaymentStart" name="principal_start_date" value="{{ date('Y-m-d', strtotime('+1 month')) }}">
                                    </div>
                                </div>

                                <div class="row g-2 mb-2 d-none" id="rowNarration">
                                    <div class="col-md-12">
                                        <label class="form-label-sm">Narration <span class="text-danger">*</span></label>
                                        <textarea class="form-control form-control-sm" id="narration" name="narration" rows="2"></textarea>
                                    </div>
                                </div>

                                <div class="row g-2 mb-2 d-none" id="rowOneOff">
                                    <div class="col-md-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="oneOff" name="one_off" value="1">
                                            <label class="form-check-label form-label-sm" for="oneOff">One Off Payment</label>
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
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        // Initialize Select2 with simple configuration
        $('#client, #property, #miscCharge, #advanceType, #previousReference').select2({
            placeholder: 'Select option',
            allowClear: true,
            width: '100%'
        });

        // Load initial data
        loadClients();
        loadMiscCharges();

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

        // Load Misc Charges
        function loadMiscCharges() {
            $.ajax({
                url: '{{ route("property.advances.misc_charges") }}',
                method: 'GET',
                success: function(charges) {
                    const select = $('#miscCharge');
                    select.empty().append('<option value="">Select Charge</option>');
                    if (charges && charges.length > 0) {
                        charges.forEach(c => select.append(`<option value="${c.id}">${c.text}</option>`));
                    }
                    select.trigger('change.select2');
                }
            });
        }

        // Client selection - load properties and eligibility
        $('#client').on('change', function() {
            const clientId = $(this).val();
            const propertySelect = $('#property');
            const previousRefSelect = $('#previousReference');

            propertySelect.empty().append('<option value="">Select client first</option>').prop('disabled', true);
            previousRefSelect.empty().append('<option value="">Select</option>').prop('disabled', true);
            $('#eligibilityInfo').addClass('d-none');

            if (!clientId) return;

            // Load properties
            $.ajax({
                url: `/client/${clientId}/properties`,
                method: 'GET',
                success: function(response) {
                    propertySelect.empty().append('<option value="">Select Property</option>');
                    if (response.properties && response.properties.length > 0) {
                        response.properties.forEach(p => {
                            propertySelect.append(`<option value="${p.id}">${p.reference_number} - ${p.property_name}</option>`);
                        });
                        propertySelect.prop('disabled', false);
                    }
                    propertySelect.trigger('change.select2');
                }
            });

            // Load previous advances
            $.ajax({
                url: '{{ route("property.advances.client-previous") }}',
                method: 'GET',
                data: {
                    client_id: clientId
                },
                success: function(advances) {
                    previousRefSelect.empty().append('<option value="">Select</option>');
                    if (advances && advances.length > 0) {
                        advances.forEach(a => {
                            previousRefSelect.append(`<option value="${a.id}">${a.reference_number || 'Advance #' + a.id}</option>`);
                        });
                        previousRefSelect.prop('disabled', false);
                    }
                    previousRefSelect.trigger('change.select2');
                }
            });

            // Check eligibility
            $.ajax({
                url: '{{ route("property.advances.check-eligibility") }}',
                method: 'GET',
                data: {
                    client_id: clientId
                },
                success: function(response) {
                    if (response.success) {
                        const avgReturns = parseFloat(response.avg_returns || 0);
                        const outstanding = parseFloat(response.outstanding_balance || 0);
                        const maxAmount = parseFloat(response.max_advance_amount || 0);
                        const activeCount = parseInt(response.active_advances_count || 0);

                        $('#eligibilityText').html(
                            `Avg Returns: KSh ${avgReturns.toLocaleString()} | Outstanding: KSh ${outstanding.toLocaleString()} | ` +
                            `Max Advance: KSh ${maxAmount.toLocaleString()} | Active: ${activeCount}`
                        );
                        $('#eligibilityInfo').removeClass('d-none');
                    }
                }
            });
        });

        // Advance Type change - show/hide fields using CSS classes
        $('#advanceType').on('change', function() {
            const type = $(this).val();

            if (!type) {
                $('#dynamicSection').addClass('d-none');
                return;
            }

            $('#dynamicSection').removeClass('d-none');

            // Hide all groups first
            $('#rowPreviousRef, #rowDates, #rowAmount, #rowTopUp, #rowReschedule, #rowNarration, #rowOneOff').addClass('d-none');

            // Remove all required attributes
            $('#previousReference, #requisitionDate, #issuedDate, #amount, #months, #paymentStart, #topUpAmount, #topUpMonths, #topUpPaymentStart, #reschedulePaymentStart, #narration').prop('required', false);

            switch (type) {
                case '1': // New Advance
                    $('#rowDates, #rowAmount, #rowNarration, #rowOneOff').removeClass('d-none');
                    $('#requisitionDate, #issuedDate, #amount, #months, #paymentStart, #narration').prop('required', true);
                    break;

                case '2': // Top Up
                    $('#rowPreviousRef, #rowDates, #rowTopUp, #rowNarration, #rowOneOff').removeClass('d-none');
                    $('#previousReference, #requisitionDate, #issuedDate, #topUpAmount, #topUpMonths, #topUpPaymentStart, #narration').prop('required', true);
                    break;

                case '3': // Reschedule
                    $('#rowPreviousRef, #rowReschedule, #rowOneOff').removeClass('d-none');
                    $('#previousReference, #reschedulePaymentStart').prop('required', true);
                    break;

                case '4': // Lumpsum
                    $('#rowDates, #rowAmount, #rowNarration, #rowOneOff').removeClass('d-none');
                    $('#requisitionDate, #issuedDate, #amount, #months, #paymentStart, #narration').prop('required', true);
                    break;
            }
        });

        // Submit button
        $('#submitBtn').on('click', function() {
            const form = $('#advanceForm');

            // Basic validation
            if (!form[0].checkValidity()) {
                form[0].reportValidity();
                return;
            }

            // Show confirmation
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

            // Show loading
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
                        window.location.href = '{{ route("property.advances.index") }}';
                    });
                },
                error: function(xhr) {
                    Swal.close();

                    if (xhr.status === 422) {
                        // Validation errors
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