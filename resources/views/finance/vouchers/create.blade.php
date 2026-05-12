@extends('layouts.main')

@section('styles')
<style>
.form-label { font-size: 0.78rem; font-weight: 500; margin-bottom: 3px; }
.entry-section-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    color: #3598db;
    margin-bottom: 6px;
}
#entriesTable td, #entriesTable th { font-size: 0.8rem; }
.account-info-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #f0f7ff;
    border: 1px solid #c8e0f9;
    border-radius: 4px;
    padding: 3px 8px;
    font-size: 0.73rem;
    color: #1a5f9e;
    margin-top: 4px;
}
.account-info-pill .material-symbols-outlined { font-size: 13px; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

    {{-- Page title --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="mb-0">Create Payment Voucher</h6>
        <a href="{{ route('payment.vouchers.index') }}"
           class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
            <span class="material-symbols-outlined" style="font-size:15px;">arrow_back</span> Back
        </a>
    </div>

    {{-- ═══════════════════════════════════════════════════
         VOUCHER DETAILS CARD
    ════════════════════════════════════════════════════ --}}
    <div class="card mb-3">
        <div class="card-header card-no-border py-2">
            <h6 class="mb-0">Voucher Details</h6>
        </div>
        <div class="card-body pb-3">

            {{-- Hidden payee fields --}}
            <input type="hidden" id="finalVendorId"   name="vendor_id">
            <input type="hidden" id="finalVendorName" name="vendor_name">

            {{-- ── Payee Details ── --}}
            <div class="entry-section-label">Payee Details</div>
            <div class="row g-2 mb-3">

                <div class="col-md-2">
                    <label class="form-label">Payee Type <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="vendorType">
                        <option value="">-- Select --</option>
                        <option value="Landlord">Landlord</option>
                        <option value="Other">Other</option>
                    </select>
                    <div class="text-danger mt-1" style="font-size:0.75rem;" id="err_vendor_type"></div>
                </div>

                <div class="col-md-4" id="landlordWrap" style="display:none;">
                    <label class="form-label">Landlord <span class="text-danger">*</span></label>
                    <select id="landlordSearch" style="width:100%;"></select>
                    <div class="text-danger mt-1" style="font-size:0.75rem;" id="err_vendor_name_landlord"></div>
                </div>

                <div class="col-md-4" id="otherPayeeWrap" style="display:none;">
                    <label class="form-label">Payee Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="vendorNameInput"
                           placeholder="TYPE PAYEE NAME" style="text-transform:uppercase;">
                    <div class="text-danger mt-1" style="font-size:0.75rem;" id="err_vendor_name"></div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Payee Account Name</label>
                    <input type="text" class="form-control form-control-sm" id="hdrAccountName"
                           placeholder="e.g. KCB BANK LIMITED">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Payee Account Number</label>
                    <input type="text" class="form-control form-control-sm" id="hdrAccountNumber"
                           placeholder="e.g. 1234567890">
                </div>

            </div>

            {{-- ── Voucher Info ── --}}
            <div class="border-top pt-3 mb-3">
                <div class="entry-section-label">Voucher Info</div>
                <div class="row g-2">

                    <div class="col-md-2">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-sm" id="voucherDate"
                               value="{{ date('Y-m-d') }}">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Pay Mode</label>
                        <select class="form-select form-select-sm" id="payMode">
                            <option value="">-- Select --</option>
                            <option value="Cheque">Cheque</option>
                            <option value="EFT">EFT</option>
                            <option value="RTGS">RTGS</option>
                            <option value="Cash">Cash</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Post to Year <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="postYear">
                            @foreach($years as $yr)
                            <option value="{{ $yr }}" {{ $yr == $currentYear ? 'selected' : '' }}>{{ $yr }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Post to Month <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="postMonth">
                            @foreach([1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'] as $num => $name)
                            <option value="{{ $num }}" {{ $num == $currentMonth ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                </div>
            </div>

            {{-- ── Entry fields ── --}}
            <div class="border-top pt-3">
                <div class="entry-section-label">Entry Details</div>

                {{-- Row 1: Entry Type + Description --}}
                <div class="row g-2 mb-2">

                    <div class="col-md-4">
                        <label class="form-label">Entry Type</label>
                        <select class="form-select form-select-sm" id="entryType">
                            <option value="">-- Select Entry Type --</option>
                            @foreach($invoiceableItems as $item)
                            <option value="{{ $item->id }}"
                                    data-billing-code="{{ $item->billing_code }}"
                                    data-item-name="{{ $item->item_name }}">
                                {{ $item->item_name }}{{ $item->billing_code ? ' (' . $item->billing_code . ')' : '' }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="entryDesc"
                               placeholder="e.g. ADVANCE PAYMENT APR 2026">
                    </div>

                </div>

                {{-- Row 2: Source Account → auto-locked Cheque No + Amount + Add Entry --}}
                <div class="row g-2 align-items-start">

                    <div class="col-md-4">
                        <label class="form-label">Source Account</label>
                        <select class="form-select form-select-sm" id="sourceAccount">
                            <option value="">-- Select Company Bank Account --</option>
                            @foreach($bankAccounts as $acc)
                            <option value="{{ $acc->id }}"
                                    data-account-number="{{ $acc->account_number }}"
                                    data-account-name="{{ $acc->account_name }}"
                                    data-bank-name="{{ $acc->bank->bank_name ?? '' }}">
                                {{ $acc->account_name }}{{ $acc->bank ? ' — ' . $acc->bank->bank_name : '' }}
                            </option>
                            @endforeach
                        </select>
                        <div id="sourceAccountInfo" style="display:none;">
                            <div class="account-info-pill">
                                <span class="material-symbols-outlined">account_balance</span>
                                <span id="sourceAccNumber"></span>
                                <span class="text-muted">|</span>
                                <span id="sourceAccBank"></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">
                            Cheque No.
                            <span id="chequeLoadingSpinner" class="spinner-border spinner-border-sm text-primary ms-1" style="display:none; width:10px; height:10px;"></span>
                        </label>
                        <input type="text" class="form-control form-control-sm" id="entryChequeNo"
                               placeholder="Auto from book" readonly
                               style="background-color:#eef3f8; color:#1a2e44; font-weight:600; letter-spacing:0.5px;">
                        <div id="chequeNoMsg" class="text-danger mt-1" style="font-size:0.72rem;"></div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control form-control-sm"
                               id="entryAmount" placeholder="0.00">
                    </div>

                    <div class="col-md-2 d-flex align-items-end" style="padding-top:22px;">
                        @permission('create_payment_vouchers')
                        <button type="button" id="addEntryBtn"
                                class="btn btn-primary btn-sm w-100 d-flex align-items-center justify-content-center gap-1">
                            <span class="material-symbols-outlined" style="font-size:16px;">add</span> Add Entry
                        </button>
                        @endpermission
                    </div>

                </div>
            </div>

        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
         ENTRIES TABLE CARD
    ════════════════════════════════════════════════════ --}}
    <div class="card">
        <div class="card-header card-no-border d-flex align-items-center justify-content-between py-2">
            <h6 class="mb-0">
                Entries
                <span id="entriesCountBadge" class="badge badge-light-primary ms-1">0</span>
            </h6>
            <span id="entriesTotalDisplay" class="text-muted" style="font-size:0.8rem; display:none;">
                Total: <strong id="entriesTotalAmount"></strong>
            </span>
        </div>
        <div class="card-body pt-1 pb-2">
            <div class="table-responsive custom-scrollbar mb-3">
                <table class="table table-sm table-hover mb-0" id="entriesTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Entry Type</th>
                            <th>Description</th>
                            <th>Source Account</th>
                            <th>Cheque No</th>
                            <th class="text-end">Amount</th>
                            <th style="width:70px;"></th>
                        </tr>
                    </thead>
                    <tbody id="entriesTableBody">
                        <tr id="noEntriesRow">
                            <td colspan="7" class="text-center text-muted py-4" style="font-size:0.82rem;">
                                No entries added yet — fill the form above and click Add Entry
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end">
                @permission('create_payment_vouchers')
                <button type="button" id="createVoucherBtn"
                        class="btn btn-success btn-sm d-flex align-items-center gap-1" disabled>
                    <span class="btn-label d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size:16px;">task_alt</span> Create Voucher
                    </span>
                    <span class="btn-spinner d-none">
                        <span class="spinner-border spinner-border-sm"></span> Creating...
                    </span>
                </button>
                @endpermission
            </div>
        </div>
    </div>

</div>
</div>

{{-- ── Entry Detail Modal ── --}}
<div class="modal fade" id="entryDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title d-flex align-items-center gap-1">
                    <span class="material-symbols-outlined" style="font-size:18px; color:#3598db;">receipt_long</span>
                    Entry Details
                    <span id="det_num_badge" class="badge badge-light-primary ms-1" style="font-size:0.72rem;"></span>
                </h6>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-3">

                    {{-- ── Left: Payee Info ── --}}
                    <div class="col-md-5">
                        <div style="font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.7px; color:#3598db; margin-bottom:8px;">
                            Payee Info
                        </div>
                        <dl class="row mb-0" style="font-size:0.82rem; row-gap:4px;">
                            <dt class="col-5 text-muted fw-normal">Type</dt>
                            <dd class="col-7 mb-0 fw-semibold" id="det_payee_type"></dd>

                            <dt class="col-5 text-muted fw-normal">Name</dt>
                            <dd class="col-7 mb-0 fw-semibold" id="det_payee_name"></dd>

                            <dt class="col-5 text-muted fw-normal">Account Name</dt>
                            <dd class="col-7 mb-0" id="det_payee_acc_name"></dd>

                            <dt class="col-5 text-muted fw-normal">Account No.</dt>
                            <dd class="col-7 mb-0" id="det_payee_acc_num"></dd>
                        </dl>
                    </div>

                    {{-- ── Divider ── --}}
                    <div class="col-md-1 d-flex justify-content-center">
                        <div style="width:1px; background:#e9ecef; min-height:100%;"></div>
                    </div>

                    {{-- ── Right: Entry Info ── --}}
                    <div class="col-md-6">
                        <div style="font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.7px; color:#3598db; margin-bottom:8px;">
                            Entry Info
                        </div>
                        <dl class="row mb-0" style="font-size:0.82rem; row-gap:4px;">
                            <dt class="col-5 text-muted fw-normal">Entry Type</dt>
                            <dd class="col-7 mb-0" id="det_type"></dd>

                            <dt class="col-5 text-muted fw-normal">Billing Code</dt>
                            <dd class="col-7 mb-0" id="det_code"></dd>

                            <dt class="col-5 text-muted fw-normal">Description</dt>
                            <dd class="col-7 mb-0 fw-semibold" id="det_desc"></dd>

                            <dt class="col-5 text-muted fw-normal">Source Account</dt>
                            <dd class="col-7 mb-0" id="det_account"></dd>

                            <dt class="col-5 text-muted fw-normal">Cheque No.</dt>
                            <dd class="col-7 mb-0" id="det_cheque"></dd>

                            <dt class="col-5 text-muted fw-normal">Amount</dt>
                            <dd class="col-7 mb-0 fw-bold text-dark" id="det_amount" style="font-size:0.9rem;"></dd>
                        </dl>
                    </div>

                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
$(document).ready(function () {

    // ─── State ────────────────────────────────────────────────────────
    let pendingEntries = [];
    let uidCounter     = 0;

    // Tracks the cheque lock held on the current (not yet added) entry form
    let currentLock = { leaf_id: null, lock_token: null, cheque_number: null };

    // ─── Payee Type toggle ────────────────────────────────────────────
    $('#vendorType').on('change', function () {
        const v = $(this).val();
        $('#landlordWrap').toggle(v === 'Landlord');
        $('#otherPayeeWrap').toggle(v === 'Other');
        $('#finalVendorId').val('');
        $('#finalVendorName').val('');
        if (v !== 'Landlord') { $('#landlordSearch').val(null).trigger('change'); }
        if (v !== 'Other')    { $('#vendorNameInput').val(''); }
    });

    // ─── Landlord Select2 ─────────────────────────────────────────────
    $('#landlordSearch').select2({
        ajax: {
            url: '{{ route("ajax.landlord.search.landlords") }}',
            dataType: 'json',
            delay: 300,
            data: params => ({ search: params.term }),
            processResults: function (data) {
                if (!data.success) return { results: [] };
                return {
                    results: data.landlords.map(l => ({
                        id:         l.reference_number,
                        text:       l.names + ' (' + l.reference_number + ')',
                        vendorName: l.names,
                        vendorId:   l.reference_number,
                    }))
                };
            },
            error: () => ({ results: [] })
        },
        minimumInputLength: 2,
        placeholder: 'Type to search landlord...',
        allowClear: true,
        width: '100%',
    });

    $('#landlordSearch').on('select2:select', function (e) {
        $('#finalVendorId').val(e.params.data.vendorId);
        $('#finalVendorName').val(e.params.data.vendorName);
        $('#err_vendor_name_landlord').text('');
    });
    $('#landlordSearch').on('select2:clear', function () {
        $('#finalVendorId').val('');
        $('#finalVendorName').val('');
    });

    // ─── Other payee — force uppercase ───────────────────────────────
    $('#vendorNameInput').on('input', function () {
        const pos = this.selectionStart;
        this.value = this.value.toUpperCase();
        this.setSelectionRange(pos, pos);
        $('#finalVendorName').val(this.value);
        $('#finalVendorId').val('');
    });

    // ─── Entry Type: auto-fill description with item_name ────────────
    $('#entryType').on('change', function () {
        const itemName = $(this).find(':selected').data('item-name') || '';
        if (itemName) { $('#entryDesc').val(itemName.toUpperCase()); }
    });

    // ─── Source Account: show info pill + auto-lock next cheque ──────
    $('#sourceAccount').on('change', function () {
        const bankAccountId = $(this).val();
        const opt           = $(this).find(':selected');

        // Release any existing lock before doing anything else
        releaseCurrentLock();

        $('#chequeNoMsg').text('');
        $('#entryChequeNo').val('');

        if (!bankAccountId) {
            $('#sourceAccountInfo').hide();
            return;
        }

        // Show account info pill
        const accNum   = opt.data('account-number') || '';
        const accName  = opt.data('account-name')   || '';
        const bankName = opt.data('bank-name')       || '';
        $('#sourceAccNumber').text(accNum);
        $('#sourceAccBank').text(accName + (bankName ? ' — ' + bankName : ''));
        $('#sourceAccountInfo').show();

        // Fetch and lock next available cheque
        $('#chequeLoadingSpinner').show();
        $('#sourceAccount').prop('disabled', true);

        $.ajax({
            url: '{{ route("ajax.voucher-cheque.lock") }}',
            method: 'POST',
            data: { bank_account_id: bankAccountId, _token: '{{ csrf_token() }}' },
            success: function (r) {
                $('#chequeLoadingSpinner').hide();
                $('#sourceAccount').prop('disabled', false);

                currentLock.leaf_id       = r.leaf_id;
                currentLock.lock_token    = r.lock_token;
                currentLock.cheque_number = r.cheque_number;

                $('#entryChequeNo').val(r.cheque_number);
            },
            error: function (xhr) {
                $('#chequeLoadingSpinner').hide();
                $('#sourceAccount').prop('disabled', false);

                const msg = xhr.responseJSON?.message || 'No available cheques for this account';
                $('#chequeNoMsg').text(msg);
                toastr.warning(msg);

                // Clear selection so user knows it failed
                $('#sourceAccount').val('');
                $('#sourceAccountInfo').hide();
            },
        });
    });

    // ─── Release the current form-level cheque lock ───────────────────
    function releaseCurrentLock() {
        if (!currentLock.leaf_id) return;
        $.post('{{ route("ajax.voucher-cheque.release") }}', {
            leaf_id:    currentLock.leaf_id,
            lock_token: currentLock.lock_token,
            _token:     '{{ csrf_token() }}',
        });
        currentLock = { leaf_id: null, lock_token: null, cheque_number: null };
    }

    // ─── Release a specific entry's cheque lock ───────────────────────
    function releaseEntryLock(entry) {
        if (!entry.leaf_id) return;
        $.post('{{ route("ajax.voucher-cheque.release") }}', {
            leaf_id:    entry.leaf_id,
            lock_token: entry.lock_token,
            _token:     '{{ csrf_token() }}',
        });
    }

    // ─── Validate voucher-level fields ────────────────────────────────
    function validateVoucherFields() {
        let ok = true;
        $('#err_vendor_type, #err_vendor_name, #err_vendor_name_landlord').text('');
        $('#vendorType, #vendorNameInput').removeClass('is-invalid');

        const vendorType = $('#vendorType').val();
        if (!vendorType) {
            $('#vendorType').addClass('is-invalid');
            $('#err_vendor_type').text('Select a payee type');
            ok = false;
        } else if (!$('#finalVendorName').val()) {
            if (vendorType === 'Landlord') {
                $('#err_vendor_name_landlord').text('Select a landlord');
            } else {
                $('#vendorNameInput').addClass('is-invalid');
                $('#err_vendor_name').text('Payee name is required');
            }
            ok = false;
        }
        return ok;
    }

    // ─── Refresh table ────────────────────────────────────────────────
    function refreshTable() {
        const tbody = $('#entriesTableBody');
        tbody.empty();

        if (!pendingEntries.length) {
            tbody.html('<tr id="noEntriesRow"><td colspan="7" class="text-center text-muted py-4" style="font-size:0.82rem;">No entries added yet — fill the form above and click Add Entry</td></tr>');
            $('#entriesCountBadge').text(0);
            $('#entriesTotalDisplay').hide();
            $('#createVoucherBtn').prop('disabled', true);
            return;
        }

        let total = 0;
        pendingEntries.forEach(function (e, i) {
            total += e.amount;
            const entryTypeLbl = e.entry_type_label  ? '<small>' + escHtml(e.entry_type_label)  + '</small>' : '<small class="text-muted">—</small>';
            const sourceAccLbl = e.source_account_label ? '<small>' + escHtml(e.source_account_label) + '</small>' : '<small class="text-muted">—</small>';
            const chequeLbl    = e.cheque_number
                ? '<span class="badge badge-light-warning">' + escHtml(e.cheque_number) + '</span>'
                : '<small class="text-muted">—</small>';
            tbody.append(
                '<tr data-uid="' + e.uid + '">' +
                '<td>' + (i + 1) + '</td>' +
                '<td>' + entryTypeLbl + '</td>' +
                '<td>' + escHtml(e.description) + '</td>' +
                '<td>' + sourceAccLbl + '</td>' +
                '<td>' + chequeLbl + '</td>' +
                '<td class="text-end fw-bold">' + fmtNum(e.amount) + '</td>' +
                '<td class="text-nowrap">' +
                '<a href="#" class="view-entry-detail text-primary me-2" data-uid="' + e.uid + '" title="View details">' +
                '<span class="material-symbols-outlined" style="font-size:17px;">info</span></a>' +
                '<a href="#" class="delete-entry text-danger" data-uid="' + e.uid + '" title="Remove">' +
                '<span class="material-symbols-outlined" style="font-size:17px;">delete</span></a>' +
                '</td>' +
                '</tr>'
            );
        });

        $('#entriesCountBadge').text(pendingEntries.length);
        $('#entriesTotalAmount').text(fmtNum(total));
        $('#entriesTotalDisplay').show();
        $('#createVoucherBtn').prop('disabled', false);
    }

    // ─── Add Entry ────────────────────────────────────────────────────
    $('#addEntryBtn').on('click', function () {
        const desc   = $('#entryDesc').val().trim();
        const amount = parseFloat($('#entryAmount').val());

        if (!desc)         { toastr.warning('Description is required'); $('#entryDesc').focus(); return; }
        if (!(amount > 0)) { toastr.warning('Enter a valid amount');    $('#entryAmount').focus(); return; }

        const entryTypeOpt   = $('#entryType').find(':selected');
        const billingCode    = entryTypeOpt.data('billing-code') || null;
        const entryTypeLabel = entryTypeOpt.val() ? entryTypeOpt.text().trim() : null;

        const sourceAccOpt   = $('#sourceAccount').find(':selected');
        const accountNumber  = sourceAccOpt.val() ? (sourceAccOpt.data('account-number') || null) : null;
        const accountBank    = sourceAccOpt.val()
            ? ((sourceAccOpt.data('account-name') || '') + (sourceAccOpt.data('bank-name') ? ' — ' + sourceAccOpt.data('bank-name') : ''))
            : null;
        const sourceAccLabel = sourceAccOpt.val()
            ? (sourceAccOpt.data('account-number') + ' | ' + sourceAccOpt.data('account-name'))
            : null;

        uidCounter++;
        pendingEntries.push({
            uid:                  uidCounter,
            description:          desc,
            entry_number:         billingCode,
            entry_type_label:     entryTypeLabel,
            account_number:       accountNumber,
            account_bank:         accountBank,
            source_account_label: sourceAccLabel,
            cheque_number:        currentLock.cheque_number,
            leaf_id:              currentLock.leaf_id,
            lock_token:           currentLock.lock_token,
            amount:               amount,
        });

        // Lock is now owned by the entry — clear form-level lock tracking
        currentLock = { leaf_id: null, lock_token: null, cheque_number: null };

        refreshTable();

        $('#entryDesc').val('').focus();
        $('#entryAmount').val('');
        $('#entryChequeNo').val('');
        $('#chequeNoMsg').text('');
        $('#entryType').val('');
        $('#sourceAccount').val('');
        $('#sourceAccountInfo').hide();
    });

    // ─── Delete entry — release its cheque lock ───────────────────────
    $('#entriesTableBody').on('click', '.delete-entry', function (e) {
        e.preventDefault();
        const uid   = parseInt($(this).data('uid'));
        const entry = pendingEntries.find(function (x) { return x.uid === uid; });
        if (entry) { releaseEntryLock(entry); }
        pendingEntries = pendingEntries.filter(function (x) { return x.uid !== uid; });
        refreshTable();
    });

    // ─── View entry detail modal ──────────────────────────────────────
    $('#entriesTableBody').on('click', '.view-entry-detail', function (e) {
        e.preventDefault();
        const uid   = parseInt($(this).data('uid'));
        const entry = pendingEntries.find(function (x) { return x.uid === uid; });
        if (!entry) return;

        const rowNum = pendingEntries.indexOf(entry) + 1;

        // ── Payee info (from header form fields) ──
        const payeeType    = $('#vendorType').find(':selected').text().trim();
        const payeeName    = $('#finalVendorName').val() || '—';
        const payeeAccName = $('#hdrAccountName').val()  || '—';
        const payeeAccNum  = $('#hdrAccountNumber').val() || '—';

        $('#det_num_badge').text('Entry #' + rowNum);
        $('#det_payee_type').text(payeeType    || '—');
        $('#det_payee_name').text(payeeName);
        $('#det_payee_acc_name').text(payeeAccName);
        $('#det_payee_acc_num').text(payeeAccNum);

        // ── Entry info ──
        $('#det_type').text(entry.entry_type_label || '—');
        $('#det_code').text(entry.entry_number     || '—');
        $('#det_desc').text(entry.description      || '—');
        $('#det_account').html(entry.source_account_label
            ? '<span class="badge badge-light-primary">' + escHtml(entry.source_account_label) + '</span>'
            : '<span class="text-muted">—</span>');
        $('#det_cheque').html(entry.cheque_number
            ? '<span class="badge badge-light-warning fw-semibold">' + escHtml(entry.cheque_number) + '</span> <small class="text-success ms-1">locked</small>'
            : '<span class="text-muted">—</span>');
        $('#det_amount').text('KES ' + fmtNum(entry.amount));

        $('#entryDetailModal').modal('show');
    });

    // ─── Create Voucher ───────────────────────────────────────────────
    function setCreateBusy(on) {
        $('#createVoucherBtn .btn-label').toggleClass('d-none', on);
        $('#createVoucherBtn .btn-spinner').toggleClass('d-none', !on);
        $('#createVoucherBtn').prop('disabled', on);
    }

    $('#createVoucherBtn').on('click', function () {
        if (!pendingEntries.length) {
            toastr.warning('Add at least one entry first');
            return;
        }
        if (!validateVoucherFields()) {
            $('html, body').animate({ scrollTop: 0 }, 300);
            return;
        }

        setCreateBusy(true);

        $.ajax({
            url: '{{ route("payment.vouchers.create.full") }}',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                vendor_type:  $('#vendorType').val(),
                vendor_id:    $('#finalVendorId').val(),
                vendor_name:  $('#finalVendorName').val(),
                voucher_date: $('#voucherDate').val(),
                year:         $('#postYear').val(),
                month:        $('#postMonth').val(),
                lines: pendingEntries.map(function (e) {
                    return {
                        entry_number:      e.entry_number,
                        entry_description: e.description,
                        account_number:    e.account_number,
                        account_bank:      e.account_bank,
                        cheque_number:     e.cheque_number,
                        amount:            e.amount,
                    };
                }),
            }),
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (r) {
                toastr.success(r.message);
                const detailUrl = '{{ route("payment.vouchers.details", "PH") }}'.replace('PH', r.encrypted_id);
                setTimeout(function () { window.location.href = detailUrl; }, 1200);
            },
            error: function (xhr) {
                setCreateBusy(false);
                if (xhr.status === 422 && xhr.responseJSON?.errors) {
                    const firstErr = Object.values(xhr.responseJSON.errors)[0][0];
                    toastr.error(firstErr);
                } else {
                    toastr.error(xhr.responseJSON?.message || 'Failed to create voucher');
                }
            },
        });
    });

    // ─── Release form-level lock if user navigates away ───────────────
    $(window).on('beforeunload', function () {
        if (currentLock.leaf_id) {
            navigator.sendBeacon(
                '{{ route("ajax.voucher-cheque.release") }}',
                new URLSearchParams({ leaf_id: currentLock.leaf_id, lock_token: currentLock.lock_token, _token: '{{ csrf_token() }}' })
            );
        }
    });

    // ─── Helpers ──────────────────────────────────────────────────────
    function fmtNum(n) {
        return n.toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

});
</script>
@endsection
