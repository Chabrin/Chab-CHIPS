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

    input.form-control-sm,
    select.form-select-sm {
        font-size: 0.8125rem;
        padding: 0.3rem 0.5rem;
        height: calc(1.5em + 0.6rem + 2px);
    }

    #narration.form-control-sm {
        font-size: 0.8125rem !important;
        padding: 0.3rem 0.5rem !important;
        height: auto !important;
        min-height: calc(1.5em + 0.6rem + 2px) !important;
        overflow: auto !important;
        resize: vertical !important;
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

    .cs-compact-table th,
    .cs-compact-table td {
        padding: 0.25rem 0.4rem;
        font-size: 0.75rem;
        white-space: nowrap;
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
                                        <option value="2">Client Payment Advance</option>
                                        <option value="3">Overpayment Advance</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label for="landlord_search_input" class="form-label-sm mb-1">Search Landlord</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control form-control-sm" id="landlord_search_input" placeholder="-- Search --">
                                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="search_landlord_btn">
                                            <span class="material-symbols-outlined" id="search_landlord_icon" style="font-size:18px;">search</span>
                                            <span class="spinner-border spinner-border-sm" role="status" id="search_landlord_spinner" style="display:none;"></span>
                                        </button>
                                    </div>
                                    <small class="text-muted">Search by name, reference, phone, email or ID number</small>
                                </div>
                                <div class="col-md-4">
                                    <label for="landlord_direct_select" class="form-label-sm mb-1">Select Landlord <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="landlord_direct_select" disabled>
                                        <option value="">-- Search First --</option>
                                    </select>
                                    <input type="hidden" id="client_id" name="client_id" value="">
                                </div>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-md-4">
                                    <label class="form-label-sm">Property <span class="text-danger">*</span>
                                        <span class="spinner-border spinner-border-sm text-primary ms-1" role="status" id="property_loading_spinner" style="display:none; width:0.7rem; height:0.7rem; border-width:0.15em;"></span>
                                    </label>
                                    <select class="form-select form-select-sm" id="property" name="property_id" required disabled>
                                        <option value="">Select landlord first</option>
                                    </select>
                                </div>
                                <div class="col-md-8 d-flex align-items-end">
                                    <small class="text-muted">
                                        <strong>Selected Landlord:</strong> <span id="selected_client_name">-</span>
                                    </small>
                                </div>
                            </div>

                            <div id="eligibilityInfo" class="info-box d-none">
                                <strong>Client Eligibility:</strong> <span id="eligibilityText"></span>
                            </div>

                            <div class="row g-3">
                                <div class="col-lg-5">
                                    <div id="dynamicSection" class="d-none">
                                        <div class="section-header">Requisition Details</div>

                                        <!-- Dates Row -->
                                        <div class="row g-2 mb-2 d-none" id="rowDates">
                                            <div class="col-md-6">
                                                <label class="form-label-sm">Requisition Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control form-control-sm" id="requisitionDate" name="requisition_date" value="{{ date('Y-m-d') }}">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label-sm">Issued Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control form-control-sm" id="issuedDate" name="disbursement_date" value="{{ date('Y-m-d') }}">
                                            </div>
                                        </div>

                                        <!-- Amount Row -->
                                        <div class="row g-2 mb-2 d-none" id="rowAmount">
                                            <div class="col-md-6">
                                                <label class="form-label-sm">Amount <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control form-control-sm" id="amount" name="amount" step="0.01" min="0">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label-sm">Percentage (%)</label>
                                                <input type="number" class="form-control form-control-sm" id="interestRate" name="interest_rate" step="0.01" min="0" value="3">
                                            </div>
                                        </div>

                                        <!-- Repayment Row: Months (Overpayment only) + Payment Start Date (both types) -->
                                        <div class="row g-2 mb-2 d-none" id="rowRepayment">
                                            <div class="col-md-6 d-none" id="colMonths">
                                                <label class="form-label-sm">Repayment Months <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control form-control-sm" id="months" name="repayment_months" min="1" max="360">
                                            </div>
                                            <div class="col-md-6" id="colPaymentStart">
                                                <label class="form-label-sm">Payment Start Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control form-control-sm" id="paymentStart" name="principal_start_date" min="{{ date('Y-m-d') }}" value="{{ date('Y-m-d', strtotime('+1 month')) }}">
                                            </div>
                                        </div>

                                        <!-- Cheque Charge for Client Payment & Overpayment -->
                                        <div class="row g-2 mb-2 d-none" id="rowChequeCharge">
                                            <div class="col-md-6">
                                                <label class="form-label-sm">Cheque Processing Charge (KES)</label>
                                                <input type="number" class="form-control form-control-sm" id="chequeChargeGeneral" name="charge_amount" step="0.01" value="100" readonly>
                                            </div>
                                        </div>

                                        <!-- Narration -->
                                        <div class="row g-2 mb-2 d-none" id="rowNarration">
                                            <div class="col-md-12">
                                                <label class="form-label-sm">Narration</label>
                                                <textarea class="form-control form-control-sm" id="narration" name="narration" rows="2"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-7">
                                    <!-- Live Calculation Summary Panel -->
                                    <div id="calcSummaryCard" class="p-3 h-100" style="border-radius:4px; background:#fff;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="fw-semibold" style="font-size:0.875rem;"><i class="fa fa-calculator me-2"></i>Advance Calculation Summary</span>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-6 col-md-4"><div class="text-muted small mb-1">Principal</div><div class="fw-bold" id="cs_principal">&mdash;</div></div>
                                            <div class="col-6 col-md-4"><div class="text-muted small mb-1">Percentage</div><div class="fw-bold" id="cs_rate">&mdash;</div></div>
                                            <div class="col-6 col-md-4"><div class="text-muted small mb-1">Percentage Amount</div><div class="fw-bold" id="cs_interest">&mdash;</div></div>
                                            <div class="col-6 col-md-4"><div class="text-muted small mb-1">Charge</div><div class="fw-bold" id="cs_charge">&mdash;</div></div>
                                            <div class="col-6 col-md-4"><div class="text-muted small mb-1">Total Amount</div><div class="fw-bold" id="cs_total">&mdash;</div></div>
                                            <div class="col-6 col-md-4 d-none" id="cs_monthly_wrap"><div class="text-muted small mb-1">Avg. Monthly Payment</div><div class="fw-bold" id="cs_monthly">&mdash;</div></div>
                                        </div>
                                        <div class="mt-2"><small class="text-muted fst-italic" id="cs_basis"></small></div>
                                        <div id="cs_schedule_wrap" class="mt-3" style="display:none;">
                                            <div class="text-muted small mb-1">Repayment Schedule</div>
                                            <div id="cs_schedule_table">
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered text-center mb-0 cs-compact-table">
                                                        <thead class="table-light">
                                                            <tr><th>#</th><th>Due Date</th><th>Principal</th><th>Interest</th><th>Payment</th></tr>
                                                        </thead>
                                                        <tbody id="cs_schedule_body"></tbody>
                                                        <tfoot id="cs_schedule_foot"></tfoot>
                                                    </table>
                                                </div>
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
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        // Initialize Select2 for the plain dropdowns
        $('#property, #advanceType').select2({
            placeholder: 'Select option',
            allowClear: true,
            width: '100%'
        });

        // Captured before any type-switch clears it, so Overpayment can restore a sensible default
        var defaultPaymentStart = $('#paymentStart').val();

        function resetClientSelection() {
            $('#client_id').val('');
            $('#selected_client_name').text('-');
            $('#property').html('<option value="">Select landlord first</option>').prop('disabled', true).trigger('change.select2');
            $('#eligibilityInfo').addClass('d-none');
        }

        // ── Search Landlord — reusing the exact same endpoint as /tenants/lease/create ──
        $('#search_landlord_btn').on('click', function() {
            const searchTerm = $('#landlord_search_input').val().trim();
            if (searchTerm.length < 2) {
                Swal.fire({ icon: 'warning', title: 'Enter at least 2 characters to search', confirmButtonColor: '#2196F3' });
                return;
            }

            resetClientSelection();
            $('#search_landlord_icon').hide();
            $('#search_landlord_spinner').show();
            $('#search_landlord_btn').prop('disabled', true);

            $.ajax({
                url: '/ajax/landlord/search-landlords',
                method: 'GET',
                data: { search: searchTerm },
                success: function(response) {
                    $('#landlord_direct_select').empty().append('<option value="">-- Select Landlord --</option>');
                    if (response.success && response.landlords && response.landlords.length > 0) {
                        $.each(response.landlords, function(i, l) {
                            $('#landlord_direct_select').append(`<option value="${l.id}">${l.names} - ${l.reference_number || ''}</option>`);
                        });
                        $('#landlord_direct_select').prop('disabled', false);
                    } else {
                        $('#landlord_direct_select').append('<option value="">-- No Results --</option>').prop('disabled', true);
                    }
                },
                error: function() {
                    Swal.fire({ icon: 'error', title: 'Error searching landlords', confirmButtonColor: '#2196F3' });
                },
                complete: function() {
                    $('#search_landlord_icon').show();
                    $('#search_landlord_spinner').hide();
                    $('#search_landlord_btn').prop('disabled', false);
                }
            });
        });

        $('#landlord_search_input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#search_landlord_btn').click();
            }
        });

        // Landlord selected - load their properties and eligibility
        $('#landlord_direct_select').on('change', function() {
            const clientId = $(this).val();
            const landlordText = $(this).find(':selected').text();
            const propertySelect = $('#property');

            propertySelect.html('<option value="">Select landlord first</option>').prop('disabled', true).trigger('change.select2');
            $('#eligibilityInfo').addClass('d-none');

            if (!clientId) {
                $('#client_id').val('');
                $('#selected_client_name').text('-');
                return;
            }

            $('#client_id').val(clientId);
            $('#selected_client_name').text(landlordText);

            // Load properties (excludes merged/inactive source properties)
            $('#property_loading_spinner').show();
            propertySelect.html('<option value="">Loading properties...</option>').trigger('change.select2');
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
                    } else {
                        propertySelect.html('<option value="">No properties found</option>');
                    }
                    propertySelect.trigger('change.select2');
                },
                error: function() {
                    propertySelect.html('<option value="">Failed to load properties</option>').trigger('change.select2');
                },
                complete: function() {
                    $('#property_loading_spinner').hide();
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
            for (var i = 0; i < gracePeriodMonths - 1; i++) {
                currentDate.setMonth(currentDate.getMonth() + 1);
                currentDate.setDate(1);
                var gInterest = Math.round(balance * rate / 100 * 100) / 100;
                schedule.push({ payment_date: currentDate.toISOString().slice(0,10), principal_amount: 0, interest: gInterest, total_amount: gInterest, loan_balance: balance });
            }
            // Principal repayment schedule — the first installment always falls exactly
            // on the chosen Payment Start Date; only later installments roll to the next month.
            currentDate = new Date(principalStart);
            for (var month = 1; month <= months; month++) {
                if (month > 1) {
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

        function resetCalcSummary() {
            $('#cs_principal, #cs_rate, #cs_interest, #cs_charge, #cs_total, #cs_monthly').html('&mdash;');
            $('#cs_basis').text('');
            $('#cs_monthly_wrap, #cs_schedule_wrap').hide();
        }

        function recalculate() {
            var advanceType  = $('#advanceType').val();
            var issuedDate   = $('#issuedDate').val();
            var amount       = parseFloat($('#amount').val()) || 0;

            if (!advanceType || amount <= 0) {
                resetCalcSummary();
                return;
            }

            var interestAmount = 0, chargeAmount = 0, totalAmount = 0;
            var basisText = '', rateDisplay = '0%', monthlyPayment = 0;
            var scheduleRows = [];

            if (advanceType === '2' || advanceType === '3') {
                var rate     = parseFloat($('#interestRate').val()) || 0;
                var months   = parseInt($('#months').val()) || 0;
                var psd      = $('#paymentStart').val();
                chargeAmount = parseFloat($('#chequeChargeGeneral').val()) || 100;
                rateDisplay  = rate + '%';

                // Repayment schedule (amortization) only applies to Overpayment Advance (type 3)
                if (advanceType === '3' && months > 0 && issuedDate && psd) {
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

                    var tbody = '', tPrin = 0, tInt = 0, tPay = 0;
                    scheduleRows.forEach(function(row, i) {
                        tPrin += row.principal_amount; tInt += row.interest; tPay += row.total_amount;
                        tbody += '<tr><td>' + (i+1) + '</td><td>' + row.payment_date + '</td><td>' +
                            fmt(row.principal_amount) + '</td><td>' + fmt(row.interest) + '</td><td>' +
                            fmt(row.total_amount) + '</td></tr>';
                    });
                    var tfoot = '<tr class="fw-bold table-light"><td colspan="2">Totals</td><td>' +
                        fmt(tPrin) + '</td><td>' + fmt(tInt) + '</td><td>' + fmt(tPay) + '</td></tr>';
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
                        } else if (day2 <= 25) {
                            interestAmount = Math.round(amount * rate / 100 * (rem2 / lastDay2) * 100) / 100;
                            basisText = 'Issued 20th–25th (day ' + day2 + ') — prorated ' + rem2 + '/' + lastDay2 + ' days.';
                        } else {
                            interestAmount = 0;
                            rateDisplay    = '0% (after 25th)';
                            basisText      = 'Issued after 25th (day ' + day2 + ') — no interest charged.';
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

        $('#issuedDate, #amount, #interestRate, #months, #paymentStart, #chequeChargeGeneral').on('change keyup', recalculate);

        // Advance Type change - show/hide fields
        $('#advanceType').on('change', function() {
            const type = $(this).val();

            if (!type) {
                $('#dynamicSection').addClass('d-none');
                resetCalcSummary();
                return;
            }

            $('#dynamicSection').removeClass('d-none');

            // Hide all dynamic rows
            $('#rowDates, #rowAmount, #rowRepayment, #rowChequeCharge, #rowNarration').addClass('d-none');
            $('#colMonths').addClass('d-none');
            $('#colPaymentStart').removeClass('col-md-6 col-md-12');

            // Reset required
            $('#requisitionDate, #issuedDate, #amount, #months, #paymentStart').prop('required', false);
            $('#interestRate').val(3).prop('readonly', false);

            switch (type) {
                case '2': // Client Payment Advance — one-off payment, no repayment schedule, but still has a start date
                    $('#rowDates, #rowAmount, #rowRepayment, #rowChequeCharge, #rowNarration').removeClass('d-none');
                    $('#requisitionDate, #issuedDate, #amount, #paymentStart').prop('required', true);
                    $('#colMonths').addClass('d-none');
                    $('#colPaymentStart').addClass('col-md-12');
                    $('#months').val('');
                    $('#paymentStart').val($('#paymentStart').val() || defaultPaymentStart);
                    $('#chequeChargeGeneral').val(100);
                    recalculate();
                    break;

                case '3': // Overpayment Advance
                    $('#rowDates, #rowAmount, #rowRepayment, #rowChequeCharge, #rowNarration').removeClass('d-none');
                    $('#requisitionDate, #issuedDate, #amount, #months, #paymentStart').prop('required', true);
                    $('#colMonths').removeClass('d-none');
                    $('#colPaymentStart').addClass('col-md-6');
                    $('#months').val($('#months').val() || 3);
                    $('#paymentStart').val($('#paymentStart').val() || defaultPaymentStart);
                    $('#chequeChargeGeneral').val(100);
                    recalculate();
                    break;
            }
        });

        // Submit button
        $('#submitBtn').on('click', function() {
            const form = $('#advanceForm');

            if (!$('#client_id').val()) {
                Swal.fire({ icon: 'warning', title: 'Please search for and select a landlord', confirmButtonColor: '#2196F3' });
                return;
            }

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