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

            {{-- ── Header info (stays fixed for all entries) ── --}}
            <div class="row g-3 mb-3">

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

                <div class="col-md-3">
                    <label class="form-label">Account Name</label>
                    <input type="text" class="form-control form-control-sm" id="hdrAccountName"
                           placeholder="e.g. KCB BANK LIMITED">
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

            {{-- ── Entry fields (clears after each Add Entry) ── --}}
            <div class="border-top pt-3">
                <div class="entry-section-label">Entry Details</div>
                <div class="row g-2 align-items-end">

                    <div class="col-md-4">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="entryDesc"
                               placeholder="e.g. ADVANCE PAYMENT APR 2026">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Account No.</label>
                        <input type="text" class="form-control form-control-sm" id="entryAccountNo"
                               placeholder="e.g. 1234567890">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Cheque No.</label>
                        <input type="text" class="form-control form-control-sm" id="entryChequeNo"
                               placeholder="e.g. 001234">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control form-control-sm"
                               id="entryAmount" placeholder="0.00">
                    </div>

                    <div class="col-md-2">
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
                            <th>Description</th>
                            <th>Bank Acc</th>
                            <th>Cheque No</th>
                            <th class="text-end">Amount</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="entriesTableBody">
                        <tr id="noEntriesRow">
                            <td colspan="6" class="text-center text-muted py-4" style="font-size:0.82rem;">
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
@endsection

@section('scripts')
<script>
$(document).ready(function () {

    // ─── State ────────────────────────────────────────────────────────
    let pendingEntries = [];   // [{uid, description, account_number, cheque_number, amount}]
    let uidCounter     = 0;

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

    // ─── Refresh table and totals ─────────────────────────────────────
    function refreshTable() {
        const tbody = $('#entriesTableBody');
        tbody.empty();

        if (!pendingEntries.length) {
            tbody.html('<tr id="noEntriesRow"><td colspan="6" class="text-center text-muted py-4" style="font-size:0.82rem;">No entries added yet — fill the form above and click Add Entry</td></tr>');
            $('#entriesCountBadge').text(0);
            $('#entriesTotalDisplay').hide();
            $('#createVoucherBtn').prop('disabled', true);
            return;
        }

        let total = 0;
        pendingEntries.forEach(function (e, i) {
            total += e.amount;
            tbody.append(
                '<tr data-uid="' + e.uid + '">' +
                '<td>' + (i + 1) + '</td>' +
                '<td>' + escHtml(e.description) + '</td>' +
                '<td><small>' + (e.account_number || '-') + '</small></td>' +
                '<td><small>' + (e.cheque_number || '-') + '</small></td>' +
                '<td class="text-end fw-bold">' + fmtNum(e.amount) + '</td>' +
                '<td><a href="#" class="delete-entry text-danger" data-uid="' + e.uid + '" title="Remove">' +
                '<span class="material-symbols-outlined" style="font-size:17px;">delete</span></a></td>' +
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
        const desc     = $('#entryDesc').val().trim();
        const accNo    = $('#entryAccountNo').val().trim();
        const chequeNo = $('#entryChequeNo').val().trim();
        const amount   = parseFloat($('#entryAmount').val());

        if (!desc)         { toastr.warning('Description is required'); $('#entryDesc').focus(); return; }
        if (!(amount > 0)) { toastr.warning('Enter a valid amount');    $('#entryAmount').focus(); return; }

        uidCounter++;
        pendingEntries.push({ uid: uidCounter, description: desc, account_number: accNo, cheque_number: chequeNo, amount: amount });
        refreshTable();

        $('#entryDesc').val('').focus();
        $('#entryAmount').val('');
        $('#entryAccountNo').val('');
        $('#entryChequeNo').val('');
    });

    // ─── Delete entry (client-side only) ─────────────────────────────
    $('#entriesTableBody').on('click', '.delete-entry', function (e) {
        e.preventDefault();
        const uid = parseInt($(this).data('uid'));
        pendingEntries = pendingEntries.filter(function (e) { return e.uid !== uid; });
        refreshTable();
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
                        entry_description: e.description,
                        account_number:    e.account_number,
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
