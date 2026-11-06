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
/* Voucher Tabs Styling */
.voucher-tabs {
    border-bottom: 2px solid #e9ecef;
    margin-bottom: 1rem;
}
.voucher-tabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    border-radius: 0;
    padding: 0.75rem 1.25rem;
    font-size: 0.85rem;
    font-weight: 500;
    color: #6c757d;
    background: transparent;
    transition: all 0.2s ease;
}
.voucher-tabs .nav-link:hover {
    color: #3598db;
    border-bottom-color: #c8e0f9;
}
.voucher-tabs .nav-link.active {
    color: #3598db;
    border-bottom-color: #3598db;
    background: transparent;
}
.voucher-tabs .nav-link .material-symbols-outlined {
    font-size: 18px;
    vertical-align: middle;
    margin-right: 6px;
}
.tab-content > .tab-pane {
    padding-top: 0.5rem;
}
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
         VOUCHER TYPE TABS
    ════════════════════════════════════════════════════ --}}
    <ul class="nav nav-tabs voucher-tabs" id="voucherTypeTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="client-tab" data-bs-toggle="tab" data-bs-target="#client-vouchers"
                    type="button" role="tab" aria-controls="client-vouchers" aria-selected="true">
                <span class="material-symbols-outlined">person</span>Client Vouchers
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="returns-tab" data-bs-toggle="tab" data-bs-target="#returns-vouchers"
                    type="button" role="tab" aria-controls="returns-vouchers" aria-selected="false">
                <span class="material-symbols-outlined">assignment_return</span>Returns Vouchers
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="internal-tab" data-bs-toggle="tab" data-bs-target="#internal-vouchers"
                    type="button" role="tab" aria-controls="internal-vouchers" aria-selected="false">
                <span class="material-symbols-outlined">corporate_fare</span>Internal Vouchers
            </button>
        </li>
    </ul>

    {{-- Tab Content --}}
    <div class="tab-content" id="voucherTypeTabsContent">

        {{-- ═══════════════════════════════════════════════════
             TAB 1: CLIENT VOUCHERS
        ════════════════════════════════════════════════════ --}}
        <div class="tab-pane fade show active" id="client-vouchers" role="tabpanel" aria-labelledby="client-tab">

            {{-- VOUCHER DETAILS CARD --}}
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

            </div>

            {{-- ── Payee Bank Details (Other payee type only) ── --}}
            <div class="row g-2 mb-3" id="payeeBankDetailsRow" style="display:none;">
                <div class="col-md-3">
                    <label class="form-label">Payee Bank Name</label>
                    <input type="text" class="form-control form-control-sm" id="hdrBankName"
                           placeholder="e.g. KCB BANK">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Payee Account Name</label>
                    <input type="text" class="form-control form-control-sm" id="hdrAccountName"
                           placeholder="e.g. JOHN DOE">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Payee Account Number</label>
                    <input type="text" class="form-control form-control-sm" id="hdrAccountNumber"
                           placeholder="e.g. 1234567890">
                </div>
            </div>

            {{-- ── No Bank Setup Warning (for Landlords) ── --}}
            <div id="noBankSetupWarning" style="display:none;">
                <small class="text-danger">No bank details found for this landlord.</small>
            </div>

            {{-- ── Voucher Info ── --}}
            <div class="border-top pt-3 mb-3">
                <div class="entry-section-label">Voucher Info</div>
                <div class="row g-2">

                    <div class="col-md-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-sm" id="voucherDate"
                               value="{{ date('Y-m-d') }}">
                    </div>

                    <div class="col-md-3">
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
                        <label class="form-label">Post to Year <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="postYear">
                            @foreach($years as $yr)
                            <option value="{{ $yr }}" {{ $yr == $currentYear ? 'selected' : '' }}>{{ $yr }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
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

                {{-- Row 1.5: Per-entry payee bank — shown only for Landlord with 2+ bank accounts --}}
                <div class="row g-2 mb-2" id="entryPayeeBankRow" style="display:none;">
                    <div class="col-md-5">
                        <label class="form-label">Payee Bank for this entry <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="entryPayeeBank">
                            <option value="">-- Select bank --</option>
                        </select>
                        <div id="entryPayeeBankMsg" class="text-danger mt-1" style="font-size:0.72rem;"></div>
                    </div>
                    <div class="col-md-7 d-flex align-items-end pb-1">
                        <small class="text-muted" style="font-size:0.73rem;">
                            <span class="material-symbols-outlined" style="font-size:13px;vertical-align:middle;">info</span>
                            Entries going to the same bank can share a cheque number
                        </small>
                    </div>
                </div>

                {{-- Row 2: Source Account + Cheque Details + Amount + Add Entry (all per-entry) --}}
                <div class="row g-2 mb-2">

                    <div class="col-lg-6">
                        <label class="form-label">Source Account</label>
                        <select class="form-select form-select-sm" id="sourceAccount">
                            <option value="">-- Select Account --</option>
                            @foreach($bankAccounts as $acc)
                            <option value="{{ $acc->id }}"
                                    data-account-number="{{ $acc->account_number }}"
                                    data-account-name="{{ $acc->account_name }}"
                                    data-bank-name="{{ $acc->bank->bank_name ?? '' }}">
                                {{ $acc->account_name }}{{ $acc->bank ? ' — ' . $acc->bank->bank_name : '' }}
                            </option>
                            @endforeach
                        </select>
                        <div id="sourceAccountInfo" style="display:none; margin-top:3px;">
                            <div class="account-info-pill">
                                <span class="material-symbols-outlined">account_balance</span>
                                <span id="sourceAccNumber"></span>
                                <span class="text-muted">|</span>
                                <span id="sourceAccBank"></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Cheque Book</label>
                        <select class="form-select form-select-sm" id="cheque_books">
                            <option value="">-- Select cheque book --</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Cheque No.</label>
                        <input type="text" class="form-control form-control-sm" id="entryChequeNo"
                               placeholder="Auto" readonly
                               style="background-color:#eef3f8; color:#1a2e44; font-weight:600;">
                        <div id="entryChequeMsg" class="text-danger mt-1" style="font-size:0.72rem;"></div>
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
                            <th>Cheque No.</th>
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
        {{-- END TAB 1: CLIENT VOUCHERS --}}

        {{-- ═══════════════════════════════════════════════════
             TAB 2: RETURNS VOUCHERS
        ════════════════════════════════════════════════════ --}}
        <div class="tab-pane fade" id="returns-vouchers" role="tabpanel" aria-labelledby="returns-tab">
            <div class="row">
                {{-- Left Column: Approved Returns List --}}
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header card-no-border py-2">
                            <div class="d-flex align-items-center justify-content-between">
                                <h6 class="mb-0">
                                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">checklist</span>
                                    Approved Returns
                                    <span class="badge badge-light-primary ms-1" id="returnsCount">0</span>
                                </h6>
                                <div class="d-flex gap-2 align-items-center">
                                    <select class="form-select form-select-sm" id="returnsYear" style="width:100px;">
                                        @foreach($years as $yr)
                                        <option value="{{ $yr }}" {{ $yr == $currentYear ? 'selected' : '' }}>{{ $yr }}</option>
                                        @endforeach
                                    </select>
                                    <select class="form-select form-select-sm" id="returnsMonth" style="width:120px;">
                                        @foreach([1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'] as $num => $name)
                                        <option value="{{ $num }}" {{ $num == $currentMonth ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-primary btn-sm" id="loadReturnsBtn">
                                        <span class="material-symbols-outlined" style="font-size:16px;">refresh</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="table-responsive custom-scrollbar">
                                <table class="display" id="returnsTable" style="width:100%; font-size:0.82rem;">
                                    <thead>
                                        <tr>
                                            <th>Landlord</th>
                                            <th>Reference</th>
                                            <th>Property</th>
                                            <th class="text-end">Net Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right Column: Voucher Form --}}
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header card-no-border py-2">
                            <h6 class="mb-0">
                                <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">receipt_long</span>
                                Returns Voucher Details
                            </h6>
                        </div>
                        <div class="card-body">
                            <input type="hidden" id="returnLandlordId">
                            <input type="hidden" id="returnPropertyId">
                            <input type="hidden" id="returnSummaryId">

                            {{-- Landlord Info --}}
                            <div class="entry-section-label">Payee Details</div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Landlord</label>
                                    <input type="text" class="form-control form-control-sm" id="returnLandlordName" readonly style="background-color:#f8f9fa;" placeholder="Select return">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Reference</label>
                                    <input type="text" class="form-control form-control-sm" id="returnReference" readonly style="background-color:#f8f9fa;" placeholder="Select return">
                                </div>
                            </div>

                            {{-- Bank Details (Read-only) --}}
                            <div class="entry-section-label">
                                Bank Details (Landlord)
                                <span id="returnBankWarning" class="text-muted ms-2" style="font-size:0.72rem; display:none;">
                                    <span class="material-symbols-outlined" style="font-size:12px;vertical-align:middle;">info</span>
                                    No bank details (cheque client)
                                </span>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Bank Name</label>
                                    <input type="text" class="form-control form-control-sm" id="returnBankName" readonly style="background-color:#f8f9fa;" placeholder="-">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Account Name</label>
                                    <input type="text" class="form-control form-control-sm" id="returnAccountName" readonly style="background-color:#f8f9fa;" placeholder="-">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Account Number</label>
                                    <input type="text" class="form-control form-control-sm" id="returnAccountNumber" readonly style="background-color:#f8f9fa;" placeholder="-">
                                </div>
                            </div>

                            {{-- Payment Details (for amounts <= 1M only) --}}
                            <div id="returnSinglePaymentSection">
                                <div class="entry-section-label">Payment Details</div>
                                <div class="row g-2 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Source Account <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" id="returnSourceAccount">
                                            <option value="">-- Select Source Account --</option>
                                            @foreach($bankAccounts as $acc)
                                            <option value="{{ $acc->id }}"
                                                    data-account-number="{{ $acc->account_number }}"
                                                    data-account-name="{{ $acc->account_name }}"
                                                    data-bank-name="{{ $acc->bank->bank_name ?? '' }}">
                                                {{ $acc->account_name }}{{ $acc->bank ? ' — ' . $acc->bank->bank_name : '' }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Cheque Book</label>
                                        <select class="form-select form-select-sm" id="returnChequeBook">
                                            <option value="">-- Select cheque book --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Cheque No.</label>
                                        <input type="text" class="form-control form-control-sm" id="returnChequeNo" readonly placeholder="Auto">
                                        <div id="returnChequeMsg" class="text-danger mt-1" style="font-size:0.72rem;"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Voucher Info --}}
                            <div class="entry-section-label">Voucher Info</div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Voucher Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-sm" id="returnVoucherDate" value="{{ date('Y-m-d') }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Post to Year <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="returnPostYear">
                                        @foreach($years as $yr)
                                        <option value="{{ $yr }}" {{ $yr == $currentYear ? 'selected' : '' }}>{{ $yr }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Post to Month <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="returnPostMonth">
                                        @foreach([1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'] as $num => $name)
                                        <option value="{{ $num }}" {{ $num == $currentMonth ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- Return Amount & Entries --}}
                            <div class="entry-section-label">Return Amount</div>
                            <div class="mb-3">
                                <label class="form-label">Net Balance</label>
                                <input type="text" class="form-control form-control-sm fw-bold" id="returnNetBalance" readonly style="background-color:#f0f7ff; color:#1a5f9e;" placeholder="0.00">
                            </div>

                            <div id="returnSplitSection" style="display:none;">
                                <div class="py-2 mb-3" style="font-size:0.82rem; border-left:3px solid #3598db; padding-left:0.75rem;">
                                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;color:#3598db;">info</span>
                                    Amount exceeds 1M. Split into multiple entries (max 999,999 each).
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="entry-section-label mb-0">Split Entries</div>
                                    <button type="button" class="btn btn-sm btn-primary" id="addReturnSplitBtn">
                                        <span class="material-symbols-outlined" style="font-size:14px;">add</span>
                                        Add Entry
                                    </button>
                                </div>

                                <div id="returnSplitEntriesList" class="mb-3">
                                    {{-- Split entries will be added here --}}
                                </div>

                                <div class="d-flex justify-content-between align-items-center p-2" style="border-top:1px solid #dee2e6;">
                                    <span style="font-size:0.85rem;">Total Split Amount:</span>
                                    <span class="fw-bold" id="returnSplitTotal" style="font-size:0.9rem;">0.00</span>
                                </div>
                                <div id="returnSplitWarning" class="text-danger mt-1" style="font-size:0.75rem; display:none;">
                                    Total must equal Net Balance
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-secondary btn-sm" id="cancelReturnVoucherBtn">
                                    <span class="material-symbols-outlined" style="font-size:16px;">close</span>
                                    Cancel
                                </button>
                                <button type="button" class="btn btn-success btn-sm" id="createReturnVoucherBtn" disabled>
                                    <span class="material-symbols-outlined" style="font-size:16px;">task_alt</span>
                                    Create Voucher
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {{-- END TAB 2: RETURNS VOUCHERS --}}

        {{-- ═══════════════════════════════════════════════════
             TAB 3: INTERNAL VOUCHERS
        ════════════════════════════════════════════════════ --}}
        <div class="tab-pane fade" id="internal-vouchers" role="tabpanel" aria-labelledby="internal-tab">
            <div class="card">
                <div class="card-body text-center py-5">
                    <span class="material-symbols-outlined text-muted" style="font-size:48px;">corporate_fare</span>
                    <h6 class="mt-3 text-muted">Internal Vouchers</h6>
                    <p class="text-muted mb-0" style="font-size:0.85rem;">Content coming soon...</p>
                </div>
            </div>
        </div>
        {{-- END TAB 3: INTERNAL VOUCHERS --}}

    </div>
    {{-- END Tab Content --}}

</div>
</div>

{{-- ── Return Split Entry Modal ── --}}
<div class="modal fade" id="returnSplitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title d-flex align-items-center gap-1">
                    <span class="material-symbols-outlined" style="font-size:18px; color:#3598db;">receipt_long</span>
                    <span id="splitModalTitle">Add Split Entry</span>
                </h6>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="editSplitIndex" value="">
                
                <div class="mb-3">
                    <label class="form-label">Amount <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" max="999999" class="form-control form-control-sm" id="splitAmount" placeholder="Max 999,999">
                    <small class="text-muted">Maximum amount per entry: 999,999</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Source Account <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="splitSourceAccount">
                        <option value="">-- Select Source Account --</option>
                        @foreach($bankAccounts as $acc)
                        <option value="{{ $acc->id }}"
                                data-account-number="{{ $acc->account_number }}"
                                data-account-name="{{ $acc->account_name }}"
                                data-bank-name="{{ $acc->bank->bank_name ?? '' }}">
                            {{ $acc->account_name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Cheque Book <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="splitChequeBook">
                        <option value="">-- Select cheque book --</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Cheque No.</label>
                    <input type="text" class="form-control form-control-sm" id="splitChequeNo" readonly placeholder="Auto">
                    <div id="splitChequeMsg" class="text-danger mt-1" style="font-size:0.72rem;"></div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="saveSplitEntryBtn">
                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">save</span>
                    Save Entry
                </button>
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

                            <dt class="col-5 text-muted fw-normal">Bank Name</dt>
                            <dd class="col-7 mb-0" id="det_payee_bank_name"></dd>

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
                            <dd class="col-7 mb-0" id="det_cheque_no"></dd>

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
    let uidCounter = 0;

    // Cheque locks keyed by cheque_number with a ref_count:
    //   lockedChequesMap[num] = { leaf_id, lock_token, cheque_number, ref_count }
    // ref_count tracks how many entries share the same cheque.
    // The lock is released only when ref_count reaches 0.
    let lockedChequesMap = {};

    // Holds the NEW cheque lock being set up for the entry currently being composed.
    // leaf_id is null when the user has chosen "reuse" (no new lock needed).
    let currentEntryCheque = { leaf_id: null, lock_token: null, cheque_number: null };

    // True when the user has selected a reuse cheque for the current (not-yet-added) entry.
    let currentEntryIsReuse = false;

    // ─── Payee Type toggle ────────────────────────────────────────────
    $('#vendorType').on('change', function () {
        const v = $(this).val();
        $('#landlordWrap').toggle(v === 'Landlord');
        $('#otherPayeeWrap').toggle(v === 'Other');
        $('#finalVendorId').val('');
        $('#finalVendorName').val('');
        // Bank details row: only visible for Other (manual entry); Landlord uses per-entry bank
        $('#payeeBankDetailsRow').toggle(v === 'Other');
        if (v !== 'Landlord') {
            $('#landlordSearch').val(null).trigger('change');
            clearLandlordBankFields();
        }
        if (v !== 'Other') { $('#vendorNameInput').val(''); }
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
                        id:           l.reference_number,
                        text:         l.names + ' (' + l.reference_number + ')',
                        vendorName:   l.names,
                        vendorId:     l.reference_number,
                        encryptedId:  l.encrypted_id,
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

    // Store landlord bank details for selection
    let landlordBankDetails = [];

    $('#landlordSearch').on('select2:select', function (e) {
        $('#finalVendorId').val(e.params.data.vendorId);
        $('#finalVendorName').val(e.params.data.vendorName);
        $('#err_vendor_name_landlord').text('');
        
        // Fetch bank details for the selected landlord
        const encryptedId = e.params.data.encryptedId;
        if (encryptedId) {
            fetchLandlordBankDetails(encryptedId);
        }
    });

    $('#landlordSearch').on('select2:clear', function () {
        $('#finalVendorId').val('');
        $('#finalVendorName').val('');
        clearLandlordBankFields();
    });

    // ─── Fetch Landlord Bank Details ──────────────────────────────────
    function fetchLandlordBankDetails(encryptedClientId) {
        clearLandlordBankFields();
        $.ajax({
            url: '/ajax/clients/' + encryptedClientId + '/bank-details',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    landlordBankDetails = response.data;
                    let entryOptions = '<option value="">-- Select bank --</option>';
                    response.data.forEach(function(bank, index) {
                        const lbl = bank.bank_name + ' — ' + bank.account_number + ' (' + bank.account_name + ')';
                        entryOptions += '<option value="' + index + '" data-bank-name="' + escHtml(bank.bank_name) + '" data-account-name="' + escHtml(bank.account_name) + '" data-account-number="' + escHtml(bank.account_number) + '">' + lbl + '</option>';
                    });
                    $('#entryPayeeBank').html(entryOptions);
                    if (response.data.length === 1) {
                        $('#entryPayeeBank').val('0');
                    }
                    $('#entryPayeeBankRow').show();
                    $('#noBankSetupWarning').hide();
                } else {
                    landlordBankDetails = [];
                    $('#noBankSetupWarning').show();
                }
            },
            error: function(xhr) {
                console.error('Error fetching landlord bank details:', xhr);
            }
        });
    }

    // ─── Clear Landlord Bank Fields ───────────────────────────────────
    function clearLandlordBankFields() {
        landlordBankDetails = [];
        $('#noBankSetupWarning').hide();
        $('#entryPayeeBankRow').hide();
        $('#entryPayeeBank').html('<option value="">-- Select bank --</option>');
        resetCurrentEntryChequeLock();
        $('#cheque_books').val('');
        updateReuseOptions();
    }

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

    // ─── Release a single cheque leaf lock ───────────────────────────
    function releaseChequeLeaf(leaf_id, lock_token) {
        if (!leaf_id) return;
        const data = new FormData();
        data.append('leaf_id', leaf_id);
        data.append('lock_token', lock_token);
        data.append('_token', $('meta[name="csrf-token"]').attr('content'));
        navigator.sendBeacon('{{ route("ajax.voucher-cheque.release") }}', data);
    }

    // ─── Reset the in-flight (not-yet-added) cheque lock ─────────────
    function resetCurrentEntryChequeLock() {
        if (currentEntryCheque.leaf_id) {
            releaseChequeLeaf(currentEntryCheque.leaf_id, currentEntryCheque.lock_token);
        }
        currentEntryCheque  = { leaf_id: null, lock_token: null, cheque_number: null };
        currentEntryIsReuse = false;
        $('#entryChequeNo').val('');
        $('#entryChequeMsg').text('');
        // Note: does NOT reset #cheque_books — callers that need it cleared do so explicitly,
        // so the #cheque_books change handler can call this without clearing its own selection.
    }

    // ─── Returns a "bank identity" key for the entry currently being composed ──
    // Format: "BankName|AccountNumber" — used to determine cheque compatibility.
    function getEntryPayeeBankKey() {
        const vType = $('#vendorType').val();
        if (vType === 'Landlord') {
            const idx = $('#entryPayeeBank').val();
            if (idx !== '' && idx !== null && landlordBankDetails[idx]) {
                const b = landlordBankDetails[idx];
                return (b.bank_name || '') + '|' + (b.account_number || '');
            }
            return null;
        }
        // Other payee — manual header fields
        const bn = $('#hdrBankName').val(), an = $('#hdrAccountNumber').val();
        return bn || an ? bn + '|' + an : null;
    }

    // ─── Returns payee bank data object for the entry currently being composed ──
    function getCurrentEntryPayeeBankData() {
        const vType = $('#vendorType').val();
        if (vType === 'Landlord') {
            const idx = $('#entryPayeeBank').val();
            if (idx !== '' && idx !== null && landlordBankDetails[idx]) {
                const b = landlordBankDetails[idx];
                return {
                    payee_bank_key:       (b.bank_name || '') + '|' + (b.account_number || ''),
                    payee_bank_label:     (b.bank_name || '') + ' — ' + (b.account_number || ''),
                    payee_bank_name:      b.bank_name      || '',
                    payee_account_name:   b.account_name   || '',
                    payee_account_number: b.account_number || '',
                };
            }
            return { payee_bank_key: null, payee_bank_label: null, payee_bank_name: '', payee_account_name: '', payee_account_number: '' };
        }
        // Other payee — manual header fields
        return {
            payee_bank_key:       $('#hdrBankName').val() + '|' + $('#hdrAccountNumber').val(),
            payee_bank_label:     $('#hdrBankName').val() + ' — ' + $('#hdrAccountNumber').val(),
            payee_bank_name:      $('#hdrBankName').val(),
            payee_account_name:   $('#hdrAccountName').val(),
            payee_account_number: $('#hdrAccountNumber').val(),
        };
    }

    // ─── Rebuild "Reuse Existing Cheque" optgroup in #cheque_books ────
    // Shows unique cheques from pendingEntries where the source account and
    // payee bank both match the current entry being composed.
    function updateReuseOptions() {
        $('#cheque_books optgroup[data-type="reuse"]').remove();

        const sourceAccId       = $('#sourceAccount').val();
        const currentBankKey    = getEntryPayeeBankKey();
        if (!sourceAccId || !currentBankKey) return;

        const seen       = new Set();
        const compatible = [];
        pendingEntries.forEach(function (e, idx) {
            if (String(e.source_account_id) === String(sourceAccId)
                && e.payee_bank_key === currentBankKey
                && e.cheque_number
                && !seen.has(e.cheque_number)) {
                seen.add(e.cheque_number);
                compatible.push({ entry: e, rowNum: idx + 1 });
            }
        });

        if (!compatible.length) return;

        const $grp = $('<optgroup label="↩ Reuse Existing Cheque" data-type="reuse">');
        compatible.forEach(function (c) {
            $grp.append(
                '<option value="reuse:' + escHtml(c.entry.cheque_number) + '">'
                + c.entry.cheque_number
                + '  — shared with Entry #' + c.rowNum + ' (' + fmtNum(c.entry.amount) + ')'
                + '</option>'
            );
        });
        $('#cheque_books').append($grp);
    }

    // ─── Source Account: show info pill + load cheque books (per entry) ──
    $('#sourceAccount').on('change', function () {
        const bankAccountId = $(this).val();
        const opt           = $(this).find(':selected');

        resetCurrentEntryChequeLock();
        $('#cheque_books').empty().append('<option value="">-- Select --</option>');

        if (!bankAccountId) {
            $('#sourceAccountInfo').hide();
            return;
        }

        const accNum   = opt.data('account-number') || '';
        const accName  = opt.data('account-name')   || '';
        const bankName = opt.data('bank-name')       || '';
        $('#sourceAccNumber').text(accNum);
        $('#sourceAccBank').text(accName + (bankName ? ' — ' + bankName : ''));
        $('#sourceAccountInfo').show();

        $.ajax({
            url: '{{ route("ajax.voucher-cheque.book") }}',
            method: 'POST',
            data: { bank_account_id: bankAccountId, _token: '{{ csrf_token() }}' },
            success: function (r) {
                $('#cheque_books').empty().append('<option value="">-- Select --</option>');
                if (r.success && r.books && r.books.length > 0) {
                    const $grp = $('<optgroup label="New Cheque — From Book">');
                    r.books.forEach(function (book) {
                        $grp.append(`<option value="${book.id}">Cheque #${book.first_cheque_number} (${book.leafs} leaves)</option>`);
                    });
                    $('#cheque_books').append($grp);
                } else {
                    $('#cheque_books').append('<option value="" disabled>No cheque books available</option>');
                }
                // Add "Reuse" options for compatible existing entries
                updateReuseOptions();
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.message || 'No available cheques for this account';
                $('#entryChequeMsg').text(msg);
            },
        });
    });

    // ─── Per-entry payee bank: changing bank resets cheque selection ──────
    $('#entryPayeeBank').on('change', function () {
        resetCurrentEntryChequeLock();
        $('#cheque_books').val('');
        updateReuseOptions();
    });

    // ─── Cheque selection: handle "New from book" vs "Reuse existing" ────
    $('#cheque_books').on('change', function () {
        const val = $(this).val();

        // Release any existing in-flight lock (only if it was a new lock)
        resetCurrentEntryChequeLock();

        if (!val) return;

        if (val.startsWith('reuse:')) {
            // ── Reuse mode: no new lock, just reference the cheque number ──
            const chequeNum = val.substring(6); // strip 'reuse:' prefix

            // Validate: payee bank must match the entry we're reusing from
            const bankKey = getEntryPayeeBankKey();
            const source  = $('#sourceAccount').val();
            const donor   = pendingEntries.find(function (e) {
                return e.cheque_number === chequeNum
                    && String(e.source_account_id) === String(source)
                    && e.payee_bank_key === bankKey;
            });
            if (!donor) {
                $('#entryChequeMsg').text('Cannot reuse this cheque — bank mismatch.');
                $('#cheque_books').val('');
                return;
            }

            $('#entryChequeNo').val(chequeNum);
            currentEntryCheque  = { leaf_id: null, lock_token: null, cheque_number: chequeNum };
            currentEntryIsReuse = true;
            $('#entryChequeMsg').text('');
            return;
        }

        // ── New cheque: lock next available leaf from the selected book ──
        const bookId = val; // integer book ID

        $.ajax({
            url: '{{ route("ajax.voucher-cheque.lock") }}',
            method: 'POST',
            data: { cheque_book_id: bookId, _token: '{{ csrf_token() }}' },
            success: function (r) {
                if (r.success && r.cheque_number) {
                    $('#entryChequeNo').val(r.cheque_number);
                    currentEntryCheque = {
                        leaf_id:       r.leaf_id,
                        lock_token:    r.lock_token,
                        cheque_number: r.cheque_number,
                    };
                    currentEntryIsReuse = false;
                    $('#entryChequeMsg').text('');
                } else {
                    $('#entryChequeMsg').text('No cheque number available.');
                }
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.message || 'Failed to lock cheque number';
                $('#entryChequeMsg').text(msg);
                toastr.error(msg);
            },
        });
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
            const entryTypeLbl = e.entry_type_label    ? '<small>' + escHtml(e.entry_type_label)    + '</small>' : '<small class="text-muted">—</small>';
            // Payment info: show source account + payee bank on two lines
            let paymentInfoHtml = '';
            if (e.source_account_label) {
                paymentInfoHtml += '<small class="text-muted">From: ' + escHtml(e.source_account_label) + '</small>';
            }
            if (e.payee_bank_label) {
                paymentInfoHtml += (paymentInfoHtml ? '<br>' : '') + '<small>To: ' + escHtml(e.payee_bank_label) + '</small>';
            }
            const sourceAccLbl = paymentInfoHtml || '<small class="text-muted">—</small>';
            const chequeLbl    = e.cheque_number
                ? '<code style="font-size:0.78rem;">' + escHtml(e.cheque_number) + '</code>'
                    + (e.is_reuse ? ' <small class="text-muted">(shared)</small>' : '')
                : '<span class="text-muted">—</span>';
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

        // Validate per-entry payee bank for Landlord with multiple banks
        if ($('#vendorType').val() === 'Landlord' && landlordBankDetails.length > 1) {
            if ($('#entryPayeeBank').val() === '' || $('#entryPayeeBank').val() === null) {
                toastr.warning('Select the payee bank for this entry');
                $('#entryPayeeBank').focus();
                return;
            }
        }

        const entryTypeOpt   = $('#entryType').find(':selected');
        const billingCode    = entryTypeOpt.attr('data-billing-code') || null;
        const entryTypeLabel = entryTypeOpt.val() ? entryTypeOpt.text().trim() : null;

        const sourceAccOpt   = $('#sourceAccount').find(':selected');
        const sourceAccId    = sourceAccOpt.val() || null;
        const sourceAccLabel = sourceAccId
            ? (sourceAccOpt.data('account-number') + ' | ' + sourceAccOpt.data('account-name'))
            : null;

        // Capture payee bank for this entry
        const payeeBankData = getCurrentEntryPayeeBankData();

        // Capture cheque info
        const chequeNumber    = currentEntryCheque.cheque_number;
        const isReuse         = currentEntryIsReuse;
        // On reuse, this entry's own currentEntryCheque.leaf_id/lock_token are null
        // (no new lock was taken) — the real values live on the donor entry's lock,
        // tracked in lockedChequesMap. Look those up so every entry that shares a
        // cheque still carries the same leaf_id/lock_token to the backend.
        const chequeLeafId    = isReuse ? (lockedChequesMap[chequeNumber]?.leaf_id ?? null) : currentEntryCheque.leaf_id;
        const chequeLockToken = isReuse ? (lockedChequesMap[chequeNumber]?.lock_token ?? null) : currentEntryCheque.lock_token;

        uidCounter++;
        const uid = uidCounter;

        pendingEntries.push({
            uid,
            description:          desc,
            entry_number:         billingCode,
            entry_type_label:     entryTypeLabel,
            source_account_id:    sourceAccId,
            source_account_label: sourceAccLabel,
            // Payee bank per line (used for account_number/account_bank on the line)
            account_number:       payeeBankData.payee_account_number || null,
            account_bank:         payeeBankData.payee_bank_name      || null,
            payee_bank_key:       payeeBankData.payee_bank_key       || null,
            payee_bank_label:     payeeBankData.payee_bank_label     || null,
            cheque_number:        chequeNumber || null,
            cheque_leaf_id:       chequeLeafId || null,
            cheque_lock_token:    chequeLockToken || null,
            is_reuse:             isReuse,
            amount,
        });

        // ── Update lockedChequesMap (refcount per unique cheque) ──
        if (chequeNumber) {
            if (isReuse) {
                // Increment refcount — another entry shares this cheque
                if (lockedChequesMap[chequeNumber]) {
                    lockedChequesMap[chequeNumber].ref_count++;
                }
                // (if not in map, the donor entry owns the lock — nothing to increment here)
            } else {
                // New lock — register in map with refcount 1
                lockedChequesMap[chequeNumber] = {
                    leaf_id:      chequeLeafId,
                    lock_token:   chequeLockToken,
                    cheque_number: chequeNumber,
                    ref_count:    1,
                };
            }
        }

        // currentEntryCheque is now committed — clear in-flight state
        currentEntryCheque  = { leaf_id: null, lock_token: null, cheque_number: null };
        currentEntryIsReuse = false;

        refreshTable();

        // Reset entry form for the next entry
        $('#entryDesc').val('').focus();
        $('#entryAmount').val('');
        $('#entryType').val('');
        $('#entryPayeeBank').val('');
        $('#sourceAccount').val('');
        $('#sourceAccountInfo').hide();
        $('#cheque_books').empty().append('<option value="">-- Select --</option>');
        $('#entryChequeNo').val('');
        $('#entryChequeMsg').text('');
        // Refresh reuse options (new entries may now be available for reuse)
        updateReuseOptions();
    });

    // ─── Delete entry ───────────────────────────────────────────────
    $('#entriesTableBody').on('click', '.delete-entry', function (e) {
        e.preventDefault();
        const uid   = parseInt($(this).data('uid'));
        const entry = pendingEntries.find(function (x) { return x.uid === uid; });

        if (entry && entry.cheque_number && lockedChequesMap[entry.cheque_number]) {
            lockedChequesMap[entry.cheque_number].ref_count--;
            if (lockedChequesMap[entry.cheque_number].ref_count <= 0) {
                // Last reference — release the lock
                releaseChequeLeaf(lockedChequesMap[entry.cheque_number].leaf_id, lockedChequesMap[entry.cheque_number].lock_token);
                delete lockedChequesMap[entry.cheque_number];
            }
        }

        pendingEntries = pendingEntries.filter(function (x) { return x.uid !== uid; });
        refreshTable();
        updateReuseOptions();
    });

    // ─── View entry detail modal ──────────────────────────────────────
    $('#entriesTableBody').on('click', '.view-entry-detail', function (e) {
        e.preventDefault();
        const uid   = parseInt($(this).data('uid'));
        const entry = pendingEntries.find(function (x) { return x.uid === uid; });
        if (!entry) return;

        const rowNum = pendingEntries.indexOf(entry) + 1;

        // ── Payee info — use per-entry bank if set, fall back to header ──
        const payeeType     = $('#vendorType').find(':selected').text().trim();
        const payeeName     = $('#finalVendorName').val() || '—';
        const payeeBankName = entry.account_bank || $('#hdrBankName').val()     || '—';
        const payeeAccName  = entry.payee_bank_label  || $('#hdrAccountName').val()  || '—';
        const payeeAccNum   = entry.account_number    || $('#hdrAccountNumber').val() || '—';

        $('#det_num_badge').text('Entry #' + rowNum);
        $('#det_payee_type').text(payeeType    || '—');
        $('#det_payee_name').text(payeeName);
        $('#det_payee_bank_name').text(payeeBankName);
        $('#det_payee_acc_name').text(payeeAccName);
        $('#det_payee_acc_num').text(payeeAccNum);

        // ── Entry info ──
        $('#det_type').text(entry.entry_type_label || '—');
        $('#det_code').text(entry.entry_number     || '—');
        $('#det_desc').text(entry.description      || '—');
        $('#det_account').html(entry.source_account_label
            ? '<span class="badge badge-light-primary">' + escHtml(entry.source_account_label) + '</span>'
            : '<span class="text-muted">—</span>');
        $('#det_cheque_no').html(entry.cheque_number
            ? '<code>' + escHtml(entry.cheque_number) + '</code>'
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

        // Determine voucher_type based on active tab
        let voucherType = 'general_voucher';  // default for Client Vouchers tab
        if ($('#internal-tab').hasClass('active')) {
            voucherType = 'internal_voucher';
        }

        $.ajax({
            url: '{{ route("payment.vouchers.create.full") }}',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                vendor_type:           $('#vendorType').val(),
                vendor_id:             $('#finalVendorId').val(),
                vendor_name:           $('#finalVendorName').val(),
                vendor_bank_name:      $('#hdrBankName').val(),
                vendor_account_name:   $('#hdrAccountName').val(),
                vendor_account_number: $('#hdrAccountNumber').val(),
                voucher_date:          $('#voucherDate').val(),
                year:                  $('#postYear').val(),
                month:                 $('#postMonth').val(),
                voucher_type:          voucherType,
                lines: pendingEntries.map(function (e) {
                    return {
                        entry_number:       e.entry_number,
                        entry_description:  e.description,
                        account_number:     e.account_number,
                        account_bank:       e.account_bank,
                        cheque_number:      e.cheque_number || null,
                        cheque_leaf_id:     e.cheque_leaf_id || null,
                        cheque_lock_token:  e.cheque_lock_token || null,
                        amount:             e.amount,
                    };
                }),
            }),
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (r) {
                // Cheques are now committed — clear all locks so beforeunload won't release them
                lockedChequesMap    = {};
                currentEntryCheque  = { leaf_id: null, lock_token: null, cheque_number: null };
                currentEntryIsReuse = false;

                toastr.success(r.message);
                const detailUrl = '{{ route("cheque.printing.details", "PH") }}'.replace('PH', r.encrypted_id);
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

    // ─── Release all held cheque locks when navigating away ─────────────
    $(window).on('beforeunload', function () {
        // Release any in-flight (not-yet-added) new cheque lock
        if (currentEntryCheque.leaf_id) {
            releaseChequeLeaf(currentEntryCheque.leaf_id, currentEntryCheque.lock_token);
        }
        // Release all unique locks still tracked in the map
        Object.values(lockedChequesMap).forEach(function (lock) {
            releaseChequeLeaf(lock.leaf_id, lock.lock_token);
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

    // ═══════════════════════════════════════════════════════════════════
    // RETURNS VOUCHERS TAB
    // ═══════════════════════════════════════════════════════════════════

    var returnsTable = null;
    var currentReturnData = null;

    // Initialize Returns DataTable
    function initReturnsTable() {
        if (returnsTable) {
            returnsTable.destroy();
        }

        returnsTable = $('#returnsTable').DataTable({
            data: [],
            columns: [
                {
                    data: 'landlord_name',
                    render: function(data, type, row) {
                        var merged = row.is_master ? ' <span class="badge bg-info text-dark">Merged</span>' : '';
                        return '<a href="#" class="return-landlord-link" data-return=\'' + JSON.stringify(row).replace(/'/g, '&#39;') + '\' style="text-decoration:underline; color:#3598db; cursor:pointer;">' + escHtml(data) + '</a>' + merged;
                    }
                },
                { data: 'landlord_code' },
                {
                    data: 'property_name',
                    render: function(data, type, row) {
                        return row.is_master ? escHtml(row.merged_properties || 'Merged properties') : escHtml(data);
                    }
                },
                {
                    data: 'net_balance',
                    className: 'text-end'
                }
            ],
            paging: true,
            pageLength: 10,
            info: true,
            searching: true,
            ordering: true,
            order: [[0, 'asc']],
            language: {
                emptyTable: 'No approved returns found for selected period',
                zeroRecords: 'No matching records found'
            }
        });
    }

    // Load returns data
    function loadReturnsData() {
        var year = $('#returnsYear').val();
        var month = $('#returnsMonth').val();

        $.ajax({
            url: '{{ route("ajax.payment.vouchers.approved-returns") }}',
            type: 'GET',
            data: { year: year, month: month },
            dataType: 'json',
            beforeSend: function() {
                $('#loadReturnsBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
            },
            success: function(response) {
                if (response.success) {
                    if (!returnsTable) {
                        initReturnsTable();
                    }
                    returnsTable.clear();
                    returnsTable.rows.add(response.data);
                    returnsTable.draw();

                    $('#returnsCount').text(response.summary.total_count);
                } else {
                    toastr.error(response.message || 'Failed to load returns');
                }
            },
            error: function(xhr) {
                toastr.error('Failed to load returns data');
            },
            complete: function() {
                $('#loadReturnsBtn').prop('disabled', false).html('<span class="material-symbols-outlined" style="font-size:16px;">refresh</span>');
            }
        });
    }

    // Handle landlord click to populate voucher form
    $(document).on('click', '.return-landlord-link', function(e) {
        e.preventDefault();
        var returnData = $(this).data('return');
        
        currentReturnData = returnData;
        
        // Populate landlord info
        $('#returnLandlordId').val(returnData.landlord_id);
        $('#returnPropertyId').val(returnData.property_id);
        $('#returnSummaryId').val(returnData.id);
        $('#returnLandlordName').val(returnData.landlord_name);
        $('#returnReference').val(returnData.landlord_code);
        
        // Format and display net balance
        var netBalance = parseFloat(returnData.net_balance_raw) || 0;
        $('#returnNetBalance').val(fmtNum(netBalance));
        
        if (returnData.is_master) {
            // A merged return is paid into the one bank account chosen for the merged properties (none: paid by cheque)
            var bank = returnData.merged_bank || {};
            $('#returnBankName').val(bank.bank_name || '');
            $('#returnAccountName').val(bank.account_name || '');
            $('#returnAccountNumber').val(bank.account_number || '');
            $('#returnBankWarning').toggle(!bank.bank_name);
            displayReturnLineItems(netBalance);
            validateReturnVoucherForm();
        } else {
            // Load property bank details
            loadPropertyBankDetails(returnData.property_id, netBalance);
        }
    });

    // Load property bank details
    function loadPropertyBankDetails(propertyId, netBalance) {
        $.ajax({
            url: '{{ route("ajax.property.bank-details") }}',
            type: 'GET',
            data: { property_id: propertyId },
            success: function(response) {
                if (response.success && response.data) {
                    // Populate bank details
                    $('#returnBankName').val(response.data.bank_name);
                    $('#returnAccountName').val(response.data.account_name);
                    $('#returnAccountNumber').val(response.data.account_number);
                    
                    // Hide warning
                    $('#returnBankWarning').hide();
                } else {
                    // Clear bank details and show warning
                    $('#returnBankName').val('');
                    $('#returnAccountName').val('');
                    $('#returnAccountNumber').val('');
                    $('#returnBankWarning').show();
                }
                
                // Show line items if amount > 1M
                displayReturnLineItems(netBalance);
                
                // Validate form
                validateReturnVoucherForm();
            },
            error: function(xhr) {
                // Clear bank details and show warning
                $('#returnBankName').val('');
                $('#returnAccountName').val('');
                $('#returnAccountNumber').val('');
                $('#returnBankWarning').show();
                toastr.warning('Could not load bank details. The voucher can still be created for a cheque payment.');
                validateReturnVoucherForm();
            }
        });
    }

    // Split entries management
    var returnSplitEntries = [];
    var splitEntryIdCounter = 0;

    // Display split section if amount exceeds 1M
    function displayReturnLineItems(netBalance) {
        if (netBalance > 1000000) {
            // Show split section, hide single payment section
            $('#returnSplitSection').show();
            $('#returnSinglePaymentSection').hide();
            
            // Clear single payment fields
            $('#returnSourceAccount').val('');
            $('#returnChequeBook').empty().append('<option value="">-- Select cheque book --</option>');
            $('#returnChequeNo').val('');
            $('#returnChequeMsg').text('');
            
            // Start with empty split entries - user must add manually
            returnSplitEntries = [];
            splitEntryIdCounter = 0;
            
            renderReturnSplitEntries();
        } else {
            // Show single payment section, hide split section
            $('#returnSplitSection').hide();
            $('#returnSinglePaymentSection').show();
            returnSplitEntries = [];
        }
    }

    // Render split entries table
    function renderReturnSplitEntries() {
        var html = '<div class="table-responsive">';
        html += '<table class="table table-sm table-hover mb-0" style="font-size:0.82rem; border:none;">';
        html += '<thead style="border-bottom:2px solid #dee2e6;"><tr>';
        html += '<th style="border:none; width:50px;">#</th>';
        html += '<th style="border:none;">Amount</th>';
        html += '<th style="border:none;">Source Account</th>';
        html += '<th style="border:none;">CHQ</th>';
        html += '<th style="border:none; width:80px;">Actions</th>';
        html += '</tr></thead><tbody>';
        
        if (returnSplitEntries.length === 0) {
            html += '<tr><td colspan="5" class="text-center text-muted py-3" style="border:none;">No split entries. Click "Add Entry" to split the amount.</td></tr>';
        } else {
            returnSplitEntries.forEach(function(entry, idx) {
                var isComplete = entry.amount && entry.source_account_id && entry.cheque_number;
                var rowStyle = isComplete ? '' : 'border-left:3px solid #ffc107;';
                
                html += '<tr style="' + rowStyle + '">';
                html += '<td style="border:none; padding-top:12px;">' + (idx + 1) + '</td>';
                html += '<td style="border:none; padding-top:12px;">' + (entry.amount ? fmtNum(entry.amount) : '<span class="text-muted">-</span>') + '</td>';
                html += '<td style="border:none; padding-top:12px;"><small>' + (entry.source_account_name || '<span class="text-muted">Not set</span>') + '</small></td>';
                html += '<td style="border:none; padding-top:12px;"><code style="font-size:0.75rem;">' + (entry.cheque_number || '<span class="text-muted">-</span>') + '</code></td>';
                html += '<td style="border:none;">';
                html += '<button type="button" class="btn btn-sm btn-link text-primary p-0 me-2 edit-split-entry" data-index="' + idx + '" title="Edit">';
                html += '<span class="material-symbols-outlined" style="font-size:16px;">edit</span></button>';
                html += '<button type="button" class="btn btn-sm btn-link text-danger p-0 delete-split-entry" data-index="' + idx + '" title="Delete">';
                html += '<span class="material-symbols-outlined" style="font-size:16px;">delete</span></button>';
                html += '</td></tr>';
            });
        }
        
        html += '</tbody></table></div>';
        $('#returnSplitEntriesList').html(html);
        
        // Update total
        var total = returnSplitEntries.reduce(function(sum, e) { return sum + (parseFloat(e.amount) || 0); }, 0);
        $('#returnSplitTotal').text(fmtNum(total));
        
        // Check if total matches
        var netBalance = parseFloat($('#returnNetBalance').val().replace(/,/g, '')) || 0;
        if (Math.abs(total - netBalance) > 0.01) {
            $('#returnSplitWarning').show();
        } else {
            $('#returnSplitWarning').hide();
        }
        
        validateReturnVoucherForm();
    }

    // Add split entry button
    $('#addReturnSplitBtn').on('click', function() {
        $('#splitModalTitle').text('Add Split Entry');
        $('#editSplitIndex').val('');
        $('#splitAmount').val('');
        $('#splitSourceAccount').val('');
        $('#splitChequeBook').empty().append('<option value="">-- Select cheque book --</option>');
        $('#splitChequeNo').val('');
        $('#splitChequeMsg').text('');
        $('#returnSplitModal').modal('show');
    });

    // Edit split entry
    $(document).on('click', '.edit-split-entry', function() {
        var index = $(this).data('index');
        var entry = returnSplitEntries[index];
        
        $('#splitModalTitle').text('Edit Split Entry');
        $('#editSplitIndex').val(index);
        $('#splitAmount').val(entry.amount);
        $('#splitSourceAccount').val(entry.source_account_id);
        
        // Load cheque books for the account
        if (entry.source_account_id) {
            loadSplitChequeBooks(entry.source_account_id, entry.cheque_book_id);
        }
        
        $('#splitChequeNo').val(entry.cheque_number);
        $('#returnSplitModal').modal('show');
    });

    // Delete split entry
    $(document).on('click', '.delete-split-entry', function() {
        var index = $(this).data('index');
        returnSplitEntries.splice(index, 1);
        renderReturnSplitEntries();
    });

    // Split source account change
    $('#splitSourceAccount').on('change', function() {
        var accountId = $(this).val();
        $('#splitChequeBook').empty().append('<option value="">-- Select cheque book --</option>');
        $('#splitChequeNo').val('');
        $('#splitChequeMsg').text('');
        
        if (accountId) {
            loadSplitChequeBooks(accountId);
        }
    });

    // Load cheque books for split
    function loadSplitChequeBooks(accountId, selectedBookId) {
        $.ajax({
            url: '{{ route("ajax.voucher-cheque.book") }}',
            method: 'POST',
            data: { bank_account_id: accountId, _token: '{{ csrf_token() }}' },
            success: function(r) {
                $('#splitChequeBook').empty().append('<option value="">-- Select cheque book --</option>');
                if (r.success && r.books && r.books.length > 0) {
                    r.books.forEach(function(book) {
                        var selected = selectedBookId && book.id == selectedBookId ? 'selected' : '';
                        $('#splitChequeBook').append(
                            `<option value="${book.id}" ${selected}>Cheque #${book.first_cheque_number} (${book.leafs} leaves)</option>`
                        );
                    });
                } else {
                    $('#splitChequeBook').append('<option value="">No cheque books available</option>');
                }
            },
            error: function(xhr) {
                $('#splitChequeMsg').text('Failed to load cheque books');
            }
        });
    }

    // Split cheque book change
    $('#splitChequeBook').on('change', function() {
        var bookId = $(this).val();
        $('#splitChequeNo').val('');
        $('#splitChequeMsg').text('');
        
        if (!bookId) return;
        
        $.ajax({
            url: '{{ route("ajax.voucher-cheque.lock") }}',
            method: 'POST',
            data: { cheque_book_id: bookId, _token: '{{ csrf_token() }}' },
            success: function(r) {
                if (r.success && r.cheque_number) {
                    $('#splitChequeNo').val(r.cheque_number);
                    $('#splitChequeNo').data('leaf-token-id', r.lock_token);
                    $('#splitChequeNo').data('leaf-id', r.leaf_id);
                } else {
                    $('#splitChequeMsg').text('No cheque number available');
                }
            },
            error: function(xhr) {
                $('#splitChequeMsg').text('Failed to lock cheque number');
            }
        });
    });

    // Save split entry
    $('#saveSplitEntryBtn').on('click', function() {
        var amount = parseFloat($('#splitAmount').val());
        var sourceAccountId = $('#splitSourceAccount').val();
        var sourceAccountName = $('#splitSourceAccount option:selected').text();
        var chequeBookId = $('#splitChequeBook').val();
        var chequeNumber = $('#splitChequeNo').val();
        var chequeLeafToken = $('#splitChequeNo').data('leaf-token-id');
        var chequeLeafId = $('#splitChequeNo').data('leaf-id');
        
        // Validate
        if (!amount || amount <= 0) {
            toastr.error('Please enter a valid amount');
            return;
        }
        if (amount > 999999) {
            toastr.error('Amount cannot exceed 999,999');
            return;
        }
        if (!sourceAccountId) {
            toastr.error('Please select a source account');
            return;
        }
        if (!chequeNumber) {
            toastr.error('Please select a cheque book to get cheque number');
            return;
        }
        
        var editIndex = $('#editSplitIndex').val();
        
        if (editIndex !== '') {
            // Update existing
            returnSplitEntries[parseInt(editIndex)] = {
                id: returnSplitEntries[parseInt(editIndex)].id,
                amount: amount,
                source_account_id: sourceAccountId,
                source_account_name: sourceAccountName,
                cheque_book_id: chequeBookId,
                cheque_number: chequeNumber,
                cheque_leaf_id: chequeLeafId,
                cheque_leaf_token: chequeLeafToken
            };
        } else {
            // Add new
            returnSplitEntries.push({
                id: ++splitEntryIdCounter,
                amount: amount,
                source_account_id: sourceAccountId,
                source_account_name: sourceAccountName,
                cheque_book_id: chequeBookId,
                cheque_number: chequeNumber,
                cheque_leaf_id: chequeLeafId,
                cheque_leaf_token: chequeLeafToken
            });
        }
        
        renderReturnSplitEntries();
        $('#returnSplitModal').modal('hide');
    });

    // Clear modal on close
    $('#returnSplitModal').on('hidden.bs.modal', function() {
        $('#splitModalTitle').text('Add Split Entry');
        $('#editSplitIndex').val('');
        $('#splitAmount').val('');
        $('#splitSourceAccount').val('');
        $('#splitChequeBook').empty().append('<option value="">-- Select cheque book --</option>');
        $('#splitChequeNo').val('').data('leaf-token-id', '').data('leaf-id', '');
        $('#splitChequeMsg').text('');
    });

    // Source account selection - load cheque books
    $('#returnSourceAccount').on('change', function() {
        var bankAccountId = $(this).val();
        
        // Clear cheque fields
        $('#returnChequeBook').empty().append('<option value="">-- Select cheque book --</option>');
        $('#returnChequeNo').val('');
        $('#returnChequeMsg').text('');
        
        if (!bankAccountId) {
            validateReturnVoucherForm();
            return;
        }
        
        // Fetch cheque books for this account
        $.ajax({
            url: '{{ route("ajax.voucher-cheque.book") }}',
            method: 'POST',
            data: { bank_account_id: bankAccountId, _token: '{{ csrf_token() }}' },
            success: function(r) {
                if (r.success && r.books && r.books.length > 0) {
                    r.books.forEach(function(book) {
                        $('#returnChequeBook').append(
                            `<option value="${book.id}">Cheque #${book.first_cheque_number} (${book.leafs} leaves)</option>`
                        );
                    });
                } else {
                    $('#returnChequeBook').append('<option value="">No cheque books available</option>');
                }
                validateReturnVoucherForm();
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || 'No available cheques';
                $('#returnChequeMsg').text(msg);
                toastr.warning(msg);
                validateReturnVoucherForm();
            }
        });
    });

    // Cheque book selection - lock cheque number
    $('#returnChequeBook').on('change', function() {
        var bookId = $(this).val();
        
        $('#returnChequeNo').val('');
        $('#returnChequeMsg').text('');
        
        if (!bookId) {
            validateReturnVoucherForm();
            return;
        }
        
        $.ajax({
            url: '{{ route("ajax.voucher-cheque.lock") }}',
            method: 'POST',
            data: { cheque_book_id: bookId, _token: '{{ csrf_token() }}' },
            success: function(r) {
                if (r.success && r.cheque_number) {
                    $('#returnChequeNo').val(r.cheque_number);
                    $('#returnChequeNo').data('leaf-token-id', r.lock_token);
                    $('#returnChequeNo').data('leaf-id', r.leaf_id);
                    $('#returnChequeMsg').text('');
                } else {
                    $('#returnChequeMsg').text('No cheque number available.');
                }
                validateReturnVoucherForm();
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || 'Failed to lock cheque number';
                $('#returnChequeMsg').text(msg);
                toastr.error(msg);
                validateReturnVoucherForm();
            }
        });
    });

    // Validate return voucher form
    function validateReturnVoucherForm() {
        var isValid = true;
        
        // Check if return is selected
        if (!$('#returnSummaryId').val()) {
            isValid = false;
        }
        
        // Bank details are optional: cheque clients have none
        // Check if voucher date is set
        if (!$('#returnVoucherDate').val()) {
            isValid = false;
        }
        
        // Check if net balance is set
        var netBalance = parseFloat($('#returnNetBalance').val().replace(/,/g, '')) || 0;
        if (!netBalance) {
            isValid = false;
        }
        
        // Check split entries if amount > 1M
        if (netBalance > 1000000) {
            // Must have split entries
            if (returnSplitEntries.length === 0) {
                isValid = false;
            } else {
                // Check all entries are complete
                var allComplete = returnSplitEntries.every(function(e) {
                    return e.amount && e.source_account_id && e.cheque_number;
                });
                if (!allComplete) {
                    isValid = false;
                }
                
                // Check total matches
                var total = returnSplitEntries.reduce(function(sum, e) { return sum + (parseFloat(e.amount) || 0); }, 0);
                if (Math.abs(total - netBalance) > 0.01) {
                    isValid = false;
                }
            }
        } else {
            // For amounts <= 1M, check single source account and cheque
            if (!$('#returnSourceAccount').val() || !$('#returnChequeNo').val()) {
                isValid = false;
            }
        }
        
        $('#createReturnVoucherBtn').prop('disabled', !isValid);
    }

    // Trigger validation on field changes
    $('#returnVoucherDate, #returnPostYear, #returnPostMonth').on('change', function() {
        validateReturnVoucherForm();
    });

    // Cancel/Clear return voucher form
    $('#cancelReturnVoucherBtn').on('click', function() {
        currentReturnData = null;
        
        // Clear form fields
        $('#returnLandlordId').val('');
        $('#returnPropertyId').val('');
        $('#returnSummaryId').val('');
        $('#returnLandlordName').val('');
        $('#returnReference').val('');
        $('#returnBankName').val('');
        $('#returnAccountName').val('');
        $('#returnAccountNumber').val('');
        $('#returnNetBalance').val('');
        $('#returnSourceAccount').val('');
        $('#returnChequeBook').empty().append('<option value="">-- Select cheque book --</option>');
        $('#returnChequeNo').val('');
        $('#returnChequeMsg').text('');
        
        // Reset to defaults
        $('#returnVoucherDate').val('{{ date("Y-m-d") }}');
        $('#returnPostYear').val('{{ $currentYear }}');
        $('#returnPostMonth').val('{{ $currentMonth }}');
        
        // Hide bank warning and split section, show single payment section
        $('#returnBankWarning').hide();
        $('#returnSplitSection').hide();
        $('#returnSinglePaymentSection').show();
        
        // Reset split entries
        returnSplitEntries = [];
        splitEntryIdCounter = 0;
        
        // Disable create button
        $('#createReturnVoucherBtn').prop('disabled', true);
    });

    // Create return voucher
    $('#createReturnVoucherBtn').on('click', function() {
        if (!currentReturnData) {
            toastr.error('No return selected');
            return;
        }
        
        // Bank details are optional (cheque clients): the voucher is paid by cheque either way
        var netBalance = parseFloat(currentReturnData.net_balance_raw) || 0;
        var voucherData = {
            return_summary_id: currentReturnData.id,
            landlord_id: currentReturnData.landlord_id,
            property_id: currentReturnData.property_id,
            vendor_name: $('#returnLandlordName').val(),
            vendor_type: 'Landlord',
            bank_name: $('#returnBankName').val(),
            account_name: $('#returnAccountName').val(),
            account_number: $('#returnAccountNumber').val(),
            voucher_date: $('#returnVoucherDate').val(),
            post_year: $('#returnPostYear').val(),
            post_month: $('#returnPostMonth').val(),
            net_balance: netBalance
        };
        
        // Check if splitting is required
        if (netBalance > 1000000) {
            // Use split entries
            if (returnSplitEntries.length === 0) {
                toastr.error('Please add split entries for amounts exceeding 1M');
                return;
            }
            
            var total = returnSplitEntries.reduce(function(sum, e) { return sum + (parseFloat(e.amount) || 0); }, 0);
            if (Math.abs(total - netBalance) > 0.01) {
                toastr.error('Total split amount must equal net balance');
                return;
            }
            
            voucherData.split_entries = returnSplitEntries.map(function(e) {
                return {
                    amount: e.amount,
                    source_account_id: e.source_account_id,
                    cheque_number: e.cheque_number,
                    cheque_leaf_id: e.cheque_leaf_id,
                    cheque_lock_token: e.cheque_leaf_token
                };
            });
        } else {
            // Single entry
            if (!$('#returnSourceAccount').val()) {
                toastr.error('Please select a source account');
                return;
            }
            if (!$('#returnChequeNo').val()) {
                toastr.error('Please select a cheque book to get cheque number');
                return;
            }
            
            voucherData.source_account_id = $('#returnSourceAccount').val();
            voucherData.cheque_number = $('#returnChequeNo').val();
            voucherData.cheque_leaf_id = $('#returnChequeNo').data('leaf-id');
            voucherData.cheque_lock_token = $('#returnChequeNo').data('leaf-token-id');
        }
        
        $('#createReturnVoucherBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Creating...');
        
        $.ajax({
            url: '{{ route("payment.vouchers.create.return") }}',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                _token: '{{ csrf_token() }}',
                ...voucherData
            }),
            success: function(r) {
                if (r.success) {
                    toastr.success('Return voucher ' + (r.voucher_number || '') + ' created successfully!');
                    const printUrl = '{{ route("cheque.printing.details", "PH") }}'.replace('PH', r.encrypted_id);
                    setTimeout(function () { window.location.href = printUrl; }, 1200);
                } else {
                    toastr.error(r.message || 'Failed to create voucher');
                    validateReturnVoucherForm();
                    $('#createReturnVoucherBtn').prop('disabled', false).html('<span class="material-symbols-outlined" style="font-size:16px;">task_alt</span> Create Voucher');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || 'Failed to create return voucher';
                if (xhr.status === 422 && xhr.responseJSON?.errors) {
                    var firstErr = Object.values(xhr.responseJSON.errors)[0][0];
                    toastr.error(firstErr);
                } else {
                    toastr.error(msg);
                }
                validateReturnVoucherForm();
                $('#createReturnVoucherBtn').prop('disabled', false).html('<span class="material-symbols-outlined" style="font-size:16px;">task_alt</span> Create Voucher');
            }
        });
    });

    // Load returns when tab is shown
    $('#returns-tab').on('shown.bs.tab', function() {
        if (!returnsTable) {
            loadReturnsData();
        }
    });

    // Reload button click
    $('#loadReturnsBtn').on('click', function() {
        loadReturnsData();
    });

    // Year/Month filter change
    $('#returnsYear, #returnsMonth').on('change', function() {
        loadReturnsData();
    });

});
</script>
@endsection
