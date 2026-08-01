@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">

                <!-- Live Calculation Summary Panel -->
                <div class="card mb-3" id="calcSummaryCard" style="display:none; border-left:4px solid #7366ff;">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center" style="background:#f8f9fa;">
                        <h6 class="mb-0"><i class="fa fa-calculator me-2 text-primary"></i>Advance Calculation Summary</h6>
                        <small class="text-muted">Updates as you edit</small>
                    </div>
                    <div class="card-body py-3">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <div class="text-muted small mb-1">Principal</div>
                                <div class="fw-bold" id="cs_principal">&mdash;</div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-muted small mb-1">Percentage</div>
                                <div class="fw-bold" id="cs_rate">&mdash;</div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-muted small mb-1">Interest Amount</div>
                                <div class="fw-bold text-warning" id="cs_interest">&mdash;</div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-muted small mb-1">Charge</div>
                                <div class="fw-bold" id="cs_charge">&mdash;</div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-muted small mb-1">Total Amount</div>
                                <div class="fw-bold text-success fs-6" id="cs_total">&mdash;</div>
                            </div>
                            <div class="col-md-2" id="cs_monthly_wrap" style="display:none;">
                                <div class="text-muted small mb-1">Avg. Monthly Payment</div>
                                <div class="fw-bold text-primary" id="cs_monthly">&mdash;</div>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted fst-italic" id="cs_basis"></small>
                        </div>
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
                </div>

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
                                        <label for="interestRate" class="form-label">Percentage (%)</label>
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
                                        <label for="months" class="form-label">Repayment Months <span class="text-danger repayment-required">*</span></label>
                                        <input type="number" class="form-control" id="months" name="repayment_months" min="1" value="{{ old('repayment_months', $advance->repayment_months ?? '') }}">
                                        @error('repayment_months')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="paymentStartDate" class="form-label">Payment Start Date <span class="text-danger repayment-required">*</span></label>
                                        <input type="date" class="form-control" id="paymentStartDate" name="principal_start_date" value="{{ old('principal_start_date', $advance->principal_start_date ? $advance->principal_start_date->format('Y-m-d') : '') }}">
                                        @error('principal_start_date')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="p-2 bg-light border rounded small mt-4" id="repaymentHint">Optional — enter months to generate a repayment schedule preview.</div>
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

    loadClients();

    // Initialize Select2
    setTimeout(function() {
        $('#client, #property, #advanceType').select2({
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
            schedule.push({
                payment_date: currentDate.toISOString().slice(0, 10),
                principal_amount: 0,
                interest: gInterest,
                total_amount: gInterest,
                loan_balance: balance
            });
        }

        currentDate = new Date(principalStart);
        for (var month = 1; month <= months; month++) {
            if (month > 1 || gracePeriodMonths === 0) {
                currentDate.setMonth(currentDate.getMonth() + 1);
                currentDate.setDate(1);
            }
            var pInterest = Math.round(balance * rate / 100 * 100) / 100;
            var principal = Math.round(principalPerMonth * 100) / 100;
            if (month === months) {
                principal = Math.round(balance * 100) / 100;
            }
            balance = Math.round((balance - principal) * 100) / 100;
            schedule.push({
                payment_date: currentDate.toISOString().slice(0, 10),
                principal_amount: principal,
                interest: pInterest,
                total_amount: Math.round((principal + pInterest) * 100) / 100,
                loan_balance: Math.max(0, balance)
            });
        }
        return schedule;
    }

    function recalculate() {
        var advanceType = $('#advanceType').val();
        var issuedDate  = $('#issuedDate').val();
        var amount      = parseFloat($('#amount').val()) || 0;

        if (!advanceType || amount <= 0) {
            $('#calcSummaryCard').hide();
            $('#interestCalcInfo').html('-');
            return;
        }

        $('#calcSummaryCard').show();

        var interestAmount = 0, chargeAmount = 0, totalAmount = 0;
        var basisText = '', rateDisplay = '0%', monthlyPayment = 0;
        var scheduleRows = [];

        if (advanceType === '2') {
            var rate2     = parseFloat($('#interestRate').val()) || 3;
            var months2   = parseInt($('#months').val()) || 0;
            var psd2      = $('#paymentStartDate').val();
            chargeAmount  = parseFloat($('#chequeChargeGeneral').val()) || 100;
            rateDisplay   = rate2 + '%';

            if (months2 > 0 && issuedDate && psd2) {
                // Repayment months entered → reducing balance amortization
                scheduleRows = calcAmortization(amount, rate2, months2, issuedDate, psd2);

                var d2a      = new Date(issuedDate);
                var disbDay2 = d2a.getDate();
                var disbLast2 = new Date(d2a.getFullYear(), d2a.getMonth() + 1, 0).getDate();
                var disbRem2  = disbLast2 - disbDay2;
                if (disbDay2 < 25 && scheduleRows.length > 0) {
                    var proratedFirst2 = Math.round(scheduleRows[0].interest * (disbRem2 / disbLast2) * 100) / 100;
                    scheduleRows[0].interest     = proratedFirst2;
                    scheduleRows[0].total_amount = Math.round((scheduleRows[0].principal_amount + proratedFirst2) * 100) / 100;
                }

                var totalInterest2  = scheduleRows.reduce(function(s, r) { return s + r.interest; }, 0);
                var totalPrincipal2 = scheduleRows.reduce(function(s, r) { return s + r.principal_amount; }, 0);
                interestAmount = Math.round(totalInterest2 * 100) / 100;
                totalAmount    = Math.round((totalPrincipal2 + totalInterest2 + chargeAmount) * 100) / 100;
                monthlyPayment = (totalPrincipal2 + totalInterest2) / months2;
                basisText = 'Reducing balance at ' + rate2 + '% per month over ' + months2 + ' months.';
                $('#interestCalcInfo').html(rate2 + '% reducing balance over ' + months2 + ' months &rarr; ' + fmt(interestAmount) + ' total interest');

                var tbody2 = '', tPrin2 = 0, tInt2 = 0, tPay2 = 0;
                scheduleRows.forEach(function(row, i) {
                    tPrin2 += row.principal_amount;
                    tInt2  += row.interest;
                    tPay2  += row.total_amount;
                    tbody2 += '<tr><td>' + (i + 1) + '</td><td>' + row.payment_date + '</td><td>' +
                        fmt(row.principal_amount) + '</td><td>' + fmt(row.interest) + '</td><td>' +
                        fmt(row.total_amount) + '</td><td>' + fmt(row.loan_balance) + '</td></tr>';
                });
                var tfoot2 = '<tr class="fw-bold table-light"><td colspan="2">Totals</td><td>' +
                    fmt(tPrin2) + '</td><td>' + fmt(tInt2) + '</td><td>' + fmt(tPay2) + '</td><td>KSh 0.00</td></tr>';
                $('#cs_schedule_body').html(tbody2);
                $('#cs_schedule_foot').html(tfoot2);
                $('#cs_monthly_wrap').show();
                $('#cs_schedule_wrap').show();

            } else {
                // No months → day-of-month simple interest rule
                if (!issuedDate) {
                    basisText   = 'Select an issued date to calculate interest.';
                    totalAmount = Math.round((amount + chargeAmount) * 100) / 100;
                } else {
                    var d2      = new Date(issuedDate);
                    var day2    = d2.getDate();
                    var lastDay2 = new Date(d2.getFullYear(), d2.getMonth() + 1, 0).getDate();
                    var rem2    = lastDay2 - day2;

                    if (day2 < 20) {
                        interestAmount = Math.round(amount * rate2 / 100 * 100) / 100;
                        basisText = 'Issued before 20th (day ' + day2 + ') — full ' + rate2 + '% rate applied.';
                        $('#interestCalcInfo').html('Before 20th: ' + rate2 + '% full = ' + fmt(interestAmount));
                    } else if (day2 >= 20 && day2 <= 25) {
                        interestAmount = Math.round(amount * rate2 / 100 * (rem2 / lastDay2) * 100) / 100;
                        basisText = 'Issued 20th–25th (day ' + day2 + ') — prorated ' + rem2 + '/' + lastDay2 + ' days.';
                        $('#interestCalcInfo').html('20th–25th: ' + rate2 + '% prorated (' + rem2 + '/' + lastDay2 + ' days) = ' + fmt(interestAmount));
                    } else {
                        interestAmount = 0;
                        rateDisplay    = '0% (after 25th)';
                        basisText      = 'Issued after 25th (day ' + day2 + ') — no interest charged.';
                        $('#interestCalcInfo').html('After 25th: No interest charged');
                        $('#interestRate').val(0);
                    }
                }
                totalAmount = Math.round((amount + interestAmount + chargeAmount) * 100) / 100;
                $('#cs_monthly_wrap, #cs_schedule_wrap').hide();
            }

        } else if (advanceType === '3') {
            var rate3     = parseFloat($('#interestRate').val()) || 3;
            var months3   = parseInt($('#months').val()) || 3;
            var psd       = $('#paymentStartDate').val();
            chargeAmount  = parseFloat($('#chequeChargeGeneral').val()) || 100;
            rateDisplay   = rate3 + '%';

            if (!issuedDate || !psd) {
                basisText   = 'Select issued date and payment start date to preview schedule.';
                totalAmount = Math.round((amount + chargeAmount) * 100) / 100;
                $('#cs_monthly_wrap, #cs_schedule_wrap').hide();
            } else {
                scheduleRows = calcAmortization(amount, rate3, months3, issuedDate, psd);

                var d3       = new Date(issuedDate);
                var disbDay  = d3.getDate();
                var disbLast = new Date(d3.getFullYear(), d3.getMonth() + 1, 0).getDate();
                var disbRem  = disbLast - disbDay;

                if (disbDay < 25 && scheduleRows.length > 0) {
                    var proratedFirst = Math.round(scheduleRows[0].interest * (disbRem / disbLast) * 100) / 100;
                    scheduleRows[0].interest     = proratedFirst;
                    scheduleRows[0].total_amount = Math.round((scheduleRows[0].principal_amount + proratedFirst) * 100) / 100;
                }

                var totalInterest3  = scheduleRows.reduce(function(s, r) { return s + r.interest; }, 0);
                var totalPrincipal3 = scheduleRows.reduce(function(s, r) { return s + r.principal_amount; }, 0);
                interestAmount = Math.round(totalInterest3 * 100) / 100;
                totalAmount    = Math.round((totalPrincipal3 + totalInterest3 + chargeAmount) * 100) / 100;
                monthlyPayment = (totalPrincipal3 + totalInterest3) / months3;

                basisText = 'Reducing balance at ' + rate3 + '% per month over ' + months3 + ' months.';
                $('#interestCalcInfo').html(rate3 + '% reducing balance over ' + months3 + ' months &rarr; ' + fmt(interestAmount) + ' total interest');

                var tbody3 = '', tPrin = 0, tInt = 0, tPay = 0;
                scheduleRows.forEach(function(row, i) {
                    tPrin += row.principal_amount;
                    tInt  += row.interest;
                    tPay  += row.total_amount;
                    tbody3 += '<tr><td>' + (i + 1) + '</td><td>' + row.payment_date + '</td><td>' +
                        fmt(row.principal_amount) + '</td><td>' + fmt(row.interest) + '</td><td>' +
                        fmt(row.total_amount) + '</td><td>' + fmt(row.loan_balance) + '</td></tr>';
                });
                var tfoot3 = '<tr class="fw-bold table-light"><td colspan="2">Totals</td><td>' +
                    fmt(tPrin) + '</td><td>' + fmt(tInt) + '</td><td>' + fmt(tPay) + '</td><td>KSh 0.00</td></tr>';

                $('#cs_schedule_body').html(tbody3);
                $('#cs_schedule_foot').html(tfoot3);
                $('#cs_monthly_wrap').show();
                $('#cs_schedule_wrap').show();
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

    $('#issuedDate, #amount, #interestRate, #months, #paymentStartDate, #chequeChargeGeneral').on('change keyup', recalculate);

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
        $('#requisitionDate, #issuedDate, #amount, #months, #paymentStartDate, #narration').prop('required', false);
        $('#interestRate').prop('readonly', false);

        switch(advanceType) {
            case '2': // Client Payment Advance
                $('#requisitionDateGroup, #amountInterestGroup, #repaymentGroup, #chequeChargeGroup, #narrationGroup, #interestInfoGroup').show();
                $('#requisitionDate, #issuedDate, #amount, #narration').prop('required', true);
                $('#months, #paymentStartDate').prop('required', false);
                $('.repayment-required').hide();
                $('#repaymentHint').text('Optional — enter months to generate a repayment schedule preview.');
                $('#chequeChargeGeneral').val(100);
                recalculate();
                break;

            case '3': // Overpayment Advance
                $('#requisitionDateGroup, #amountInterestGroup, #repaymentGroup, #chequeChargeGroup, #narrationGroup, #interestInfoGroup').show();
                $('#requisitionDate, #issuedDate, #amount, #months, #paymentStartDate, #narration').prop('required', true);
                $('.repayment-required').show();
                $('#repaymentHint').text('Default: 3 months. Changes require authorization.');
                $('#months').val($('#months').val() || 3);
                $('#chequeChargeGeneral').val(100);
                recalculate();
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

    // Schedule preview toggle
    $('#cs_toggle_schedule').on('click', function() {
        $('#cs_schedule_table').toggle();
        const showing = $('#cs_schedule_table').is(':visible');
        $(this).html(showing
            ? '<i class="fa fa-table me-1"></i>Hide Schedule'
            : '<i class="fa fa-table me-1"></i>Preview Repayment Schedule');
    });
});
</script>
@endsection
