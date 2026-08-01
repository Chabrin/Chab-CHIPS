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
                                        <option value=""></option>
                                    </select>
                                    <small class="text-muted">Type to search - by landlord name, or by property name/reference if you don't know the landlord.</small>
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

                                <!-- Repayment Row (Type 2 optional, Type 3 required) -->
                                <div class="row g-2 mb-2 d-none" id="rowRepayment">
                                    <div class="col-md-4">
                                        <label class="form-label-sm">Repayment Months <span class="text-danger repayment-required" style="display:none;">*</span></label>
                                        <input type="number" class="form-control form-control-sm" id="months" name="repayment_months" min="1" max="360">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label-sm">Payment Start Date <span class="text-danger repayment-required" style="display:none;">*</span></label>
                                        <input type="date" class="form-control form-control-sm" id="paymentStart" name="principal_start_date" value="{{ date('Y-m-d', strtotime('+1 month')) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <div class="info-box" id="repaymentHint" style="margin-top:22px; margin-bottom:0; padding:6px 10px;">
                                            Optional — enter months to generate a repayment schedule preview.
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

                            <!-- Live Calculation Summary Panel -->
                            <div id="calcSummaryCard" class="mt-3 p-3" style="display:none; border:1px solid #dee2e6; border-left:4px solid #7366ff; border-radius:4px; background:#fff;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-semibold" style="font-size:0.875rem;"><i class="fa fa-calculator me-2 text-primary"></i>Advance Calculation Summary</span>
                                    <small class="text-muted">Updates as you edit</small>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6 col-md-2"><div class="text-muted small mb-1">Principal</div><div class="fw-bold" id="cs_principal">&mdash;</div></div>
                                    <div class="col-6 col-md-2"><div class="text-muted small mb-1">Percentage</div><div class="fw-bold" id="cs_rate">&mdash;</div></div>
                                    <div class="col-6 col-md-2"><div class="text-muted small mb-1">Percentage Amount</div><div class="fw-bold text-warning" id="cs_interest">&mdash;</div></div>
                                    <div class="col-6 col-md-2"><div class="text-muted small mb-1">Charge</div><div class="fw-bold" id="cs_charge">&mdash;</div></div>
                                    <div class="col-6 col-md-2"><div class="text-muted small mb-1">Total Amount</div><div class="fw-bold text-success" id="cs_total">&mdash;</div></div>
                                    <div class="col-6 col-md-2 d-none" id="cs_monthly_wrap"><div class="text-muted small mb-1">Avg. Monthly Payment</div><div class="fw-bold text-primary" id="cs_monthly">&mdash;</div></div>
                                </div>
                                <div class="mt-2"><small class="text-muted fst-italic" id="cs_basis"></small></div>
                                <div id="cs_schedule_wrap" class="mt-3" style="display:none;">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="cs_toggle_schedule">
                                        <i class="fa fa-table me-1"></i>Preview Repayment Schedule
                                    </button>
                                    <div id="cs_schedule_table" class="mt-2" style="display:none;">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered text-center mb-0">
                                                <thead class="table-light">
                                                    <tr><th>#</th><th>Due Date</th><th>Principal</th><th>Interest</th><th>Payment</th><th>Balance</th></tr>
                                                </thead>
                                                <tbody id="cs_schedule_body"></tbody>
                                                <tfoot id="cs_schedule_foot"></tfoot>
                                            </table>
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
        // Initialize Select2 for the plain dropdowns
        $('#property, #advanceType, #expenseCategory, #paymentMethod').select2({
            placeholder: 'Select option',
            allowClear: true,
            width: '100%'
        });

        // Client field is a live search - hitting the server on every keystroke, matching
        // either a landlord's name or a property's name/reference. Each result carries a
        // client_id (the value actually selected) and, for property matches, a property_id.
        $('#client').select2({
            placeholder: 'Type to search - landlord name or property name/reference',
            allowClear: true,
            width: '100%',
            minimumInputLength: 1,
            ajax: {
                url: '/ajax/property/advances/search',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return { q: params.term || '' };
                },
                processResults: function(response) {
                    return { results: response.results || [] };
                },
                cache: true
            }
        });

        // A client picked via a property-name match also carries which specific property
        // matched, so it can be pre-selected once that landlord's own property list has
        // loaded. select2:select fires with the full result object (id, text, property_id)
        // before the underlying <select>'s native change event fires.
        let pendingPropertySelection = null;
        $('#client').on('select2:select', function(e) {
            pendingPropertySelection = e.params.data.property_id || null;
        });
        $('#client').on('select2:clear', function() {
            pendingPropertySelection = null;
        });

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

        // Client (or property-match) selection - load properties and eligibility
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
                    if (pendingPropertySelection) {
                        propertySelect.val(pendingPropertySelection);
                        pendingPropertySelection = null;
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

        // ===== LIVE CALCULATION ENGINE =====

        function fmt(n) {
            return 'KSh ' + parseFloat(n || 0).toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        function calcAmortization(amount, rate, months, disbursementDate, principalStartDate) {
            var schedule = [];
            var balance = amount;
            var principalPerMonth = amount / months;
            var disbursement = new Date(disbursementDate);
            var principalStart = new Date(principalStartDate);
            var disbYM = disbursement.getFullYear() * 12 + disbursement.getMonth();
            var startYM = principalStart.getFullYear() * 12 + principalStart.getMonth();
            var gracePeriodMonths = Math.max(0, startYM - disbYM);

            var currentDate = new Date(disbursement);
            for (var i = 0; i < gracePeriodMonths; i++) {
                currentDate.setMonth(currentDate.getMonth() + 1);
                currentDate.setDate(1);
                var gInterest = Math.round(balance * rate / 100 * 100) / 100;
                schedule.push({ payment_date: currentDate.toISOString().slice(0,10), principal_amount: 0, interest: gInterest, total_amount: gInterest, loan_balance: balance });
            }
            currentDate = new Date(principalStart);
            for (var month = 1; month <= months; month++) {
                if (month > 1 || gracePeriodMonths === 0) {
                    currentDate.setMonth(currentDate.getMonth() + 1);
                    currentDate.setDate(1);
                }
                var pInterest = Math.round(balance * rate / 100 * 100) / 100;
                var principal = Math.round(principalPerMonth * 100) / 100;
                if (month === months) principal = Math.round(balance * 100) / 100;
                balance = Math.round((balance - principal) * 100) / 100;
                schedule.push({ payment_date: currentDate.toISOString().slice(0,10), principal_amount: principal, interest: pInterest, total_amount: Math.round((principal + pInterest) * 100) / 100, loan_balance: Math.max(0, balance) });
            }
            return schedule;
        }

        function recalculate() {
            var advanceType  = $('#advanceType').val();
            var issuedDate   = $('#issuedDate').val();
            var amount       = parseFloat($('#amount').val()) || 0;

            if (!advanceType || amount <= 0) {
                $('#calcSummaryCard').hide();
                $('#interestCalcInfo').html('-');
                return;
            }

            $('#calcSummaryCard').show();

            var interestAmount = 0, chargeAmount = 0, totalAmount = 0;
            var basisText = '', rateDisplay = '0%', monthlyPayment = 0;
            var scheduleRows = [];

            if (advanceType === '1') {
                chargeAmount = parseFloat($('#chequeCharge').val()) || 0;
                totalAmount  = Math.round((amount + chargeAmount) * 100) / 100;
                rateDisplay  = '0%';
                basisText    = 'Expense Advance — no interest charged. Charge is auto-set by expense category.';
                $('#cs_monthly_wrap, #cs_schedule_wrap').hide();

            } else if (advanceType === '2' || advanceType === '3') {
                var rate     = parseFloat($('#interestRate').val()) || 0;
                var months   = parseInt($('#months').val()) || 0;
                var psd      = $('#paymentStart').val();
                chargeAmount = parseFloat($('#chequeChargeGeneral').val()) || 100;
                rateDisplay  = rate + '%';

                if (months > 0 && issuedDate && psd) {
                    // Reducing balance amortization
                    scheduleRows = calcAmortization(amount, rate, months, issuedDate, psd);

                    var d3       = new Date(issuedDate);
                    var disbDay  = d3.getDate();
                    var disbLast = new Date(d3.getFullYear(), d3.getMonth() + 1, 0).getDate();
                    var disbRem  = disbLast - disbDay;
                    if (disbDay < 25 && scheduleRows.length > 0) {
                        var proratedFirst = Math.round(scheduleRows[0].interest * (disbRem / disbLast) * 100) / 100;
                        scheduleRows[0].interest     = proratedFirst;
                        scheduleRows[0].total_amount = Math.round((scheduleRows[0].principal_amount + proratedFirst) * 100) / 100;
                    }

                    var totalInterest  = scheduleRows.reduce(function(s, r) { return s + r.interest; }, 0);
                    var totalPrincipal = scheduleRows.reduce(function(s, r) { return s + r.principal_amount; }, 0);
                    interestAmount = Math.round(totalInterest * 100) / 100;
                    totalAmount    = Math.round((totalPrincipal + totalInterest + chargeAmount) * 100) / 100;
                    monthlyPayment = (totalPrincipal + totalInterest) / months;
                    basisText = 'Reducing balance at ' + rate + '% per month over ' + months + ' months.';
                    $('#interestCalcInfo').html(rate + '% reducing balance over ' + months + ' months &rarr; ' + fmt(interestAmount) + ' total interest');

                    var tbody = '', tPrin = 0, tInt = 0, tPay = 0;
                    scheduleRows.forEach(function(row, i) {
                        tPrin += row.principal_amount; tInt += row.interest; tPay += row.total_amount;
                        tbody += '<tr><td>' + (i+1) + '</td><td>' + row.payment_date + '</td><td>' +
                            fmt(row.principal_amount) + '</td><td>' + fmt(row.interest) + '</td><td>' +
                            fmt(row.total_amount) + '</td><td>' + fmt(row.loan_balance) + '</td></tr>';
                    });
                    var tfoot = '<tr class="fw-bold table-light"><td colspan="2">Totals</td><td>' +
                        fmt(tPrin) + '</td><td>' + fmt(tInt) + '</td><td>' + fmt(tPay) + '</td><td>KSh 0.00</td></tr>';
                    $('#cs_schedule_body').html(tbody);
                    $('#cs_schedule_foot').html(tfoot);
                    $('#cs_monthly_wrap').show();
                    $('#cs_schedule_wrap').show();

                } else {
                    // Day-of-month simple interest
                    if (!issuedDate) {
                        basisText   = 'Select an issued date to calculate interest.';
                        totalAmount = Math.round((amount + chargeAmount) * 100) / 100;
                    } else {
                        var d2       = new Date(issuedDate);
                        var day2     = d2.getDate();
                        var lastDay2 = new Date(d2.getFullYear(), d2.getMonth() + 1, 0).getDate();
                        var rem2     = lastDay2 - day2;

                        if (day2 < 20) {
                            interestAmount = Math.round(amount * rate / 100 * 100) / 100;
                            basisText = 'Issued before 20th (day ' + day2 + ') — full ' + rate + '% rate applied.';
                            $('#interestCalcInfo').html('Before 20th: ' + rate + '% full = ' + fmt(interestAmount));
                        } else if (day2 <= 25) {
                            interestAmount = Math.round(amount * rate / 100 * (rem2 / lastDay2) * 100) / 100;
                            basisText = 'Issued 20th–25th (day ' + day2 + ') — prorated ' + rem2 + '/' + lastDay2 + ' days.';
                            $('#interestCalcInfo').html('20th–25th: ' + rate + '% prorated (' + rem2 + '/' + lastDay2 + ' days) = ' + fmt(interestAmount));
                        } else {
                            interestAmount = 0;
                            rateDisplay    = '0% (after 25th)';
                            basisText      = 'Issued after 25th (day ' + day2 + ') — no interest charged.';
                            $('#interestCalcInfo').html('After 25th: No interest charged');
                        }
                    }
                    totalAmount = Math.round((amount + interestAmount + chargeAmount) * 100) / 100;
                    $('#cs_monthly_wrap, #cs_schedule_wrap').hide();
                }
            }

            $('#cs_principal').text(fmt(amount));
            $('#cs_rate').text(rateDisplay);
            $('#cs_interest').text(fmt(interestAmount));
            $('#cs_charge').text(fmt(chargeAmount));
            $('#cs_total').text(fmt(totalAmount));
            $('#cs_monthly').text(monthlyPayment > 0 ? fmt(monthlyPayment) : '—');
            $('#cs_basis').text(basisText);
        }

        $('#issuedDate, #amount, #interestRate, #months, #paymentStart, #chequeCharge, #chequeChargeGeneral').on('change keyup', recalculate);

        $('#cs_toggle_schedule').on('click', function() {
            $('#cs_schedule_table').toggle();
            var showing = $('#cs_schedule_table').is(':visible');
            $(this).html(showing ? '<i class="fa fa-table me-1"></i>Hide Schedule' : '<i class="fa fa-table me-1"></i>Preview Repayment Schedule');
        });

        // Advance Type change - show/hide fields
        $('#advanceType').on('change', function() {
            const type = $(this).val();

            if (!type) {
                $('#dynamicSection').addClass('d-none');
                $('#calcSummaryCard').hide();
                $('#cs_principal, #cs_rate, #cs_interest, #cs_charge, #cs_total, #cs_monthly').html('&mdash;');
                $('#cs_basis').text('');
                $('#cs_monthly_wrap, #cs_schedule_wrap').hide();
                $('#cs_schedule_table').hide();
                return;
            }

            $('#dynamicSection').removeClass('d-none');

            // Hide all dynamic rows
            $('#rowExpenseCategory, #rowPaymentMethod, #rowDates, #rowAmount, #rowRepayment, #rowChequeCharge, #rowNarration, #interestInfoCol').addClass('d-none');

            // Reset required
            $('#expenseCategory, #paymentMethod, #requisitionDate, #issuedDate, #amount, #months, #paymentStart, #narration').prop('required', false);
            $('#interestRate').val(3).prop('readonly', false);

            switch (type) {
                case '1': // Expense Advance - No interest
                    $('#rowExpenseCategory, #rowDates, #rowAmount, #rowNarration').removeClass('d-none');
                    $('#expenseCategory, #requisitionDate, #issuedDate, #amount, #narration').prop('required', true);
                    $('#interestRate').val(0).prop('readonly', true);
                    $('#interestCalcInfo').html('No interest on expense advances');
                    recalculate();
                    break;

                case '2': // Client Payment Advance
                    $('#rowPaymentMethod, #rowDates, #rowAmount, #rowRepayment, #rowChequeCharge, #rowNarration, #interestInfoCol').removeClass('d-none');
                    $('#paymentMethod, #requisitionDate, #issuedDate, #amount, #narration').prop('required', true);
                    $('#months, #paymentStart').prop('required', false);
                    $('.repayment-required').hide();
                    $('#repaymentHint').text('Optional — enter months to generate a repayment schedule preview.');
                    $('#chequeChargeGeneral').val(100);
                    recalculate();
                    break;

                case '3': // Overpayment Advance
                    $('#rowDates, #rowAmount, #rowRepayment, #rowChequeCharge, #rowNarration, #interestInfoCol').removeClass('d-none');
                    $('#requisitionDate, #issuedDate, #amount, #months, #paymentStart, #narration').prop('required', true);
                    $('.repayment-required').show();
                    $('#repaymentHint').text('Default: 3 months. Changes require authorization.');
                    $('#months').val($('#months').val() || 3);
                    $('#chequeChargeGeneral').val(100);
                    recalculate();
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