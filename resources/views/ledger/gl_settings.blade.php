@extends('layouts.main')

@section('styles')
<style>
/* ── Tab nav ─────────────────────────────────────────────────────────── */
#glTabs .nav-link {
    font-size: 0.8rem;
    font-weight: 600;
    color: #6b7a90;
    padding: 8px 16px;
    border-bottom: 2px solid transparent;
    border-radius: 0;
    white-space: nowrap;
}
#glTabs .nav-link.active {
    color: #3598DB;
    border-bottom-color: #3598DB;
    background: transparent;
}
#glTabs .nav-link .material-symbols-outlined {
    font-size: 16px;
    vertical-align: -3px;
    margin-right: 4px;
}
#glTabs { border-bottom: 1px solid #dee2e6; flex-wrap: nowrap; overflow-x: auto; }

/* ── Setting rows ────────────────────────────────────────────────────── */
.setting-row {
    display: flex;
    align-items: center;
    padding: 11px 0;
    border-bottom: 1px solid #f1f4f8;
}
.setting-row:last-child { border-bottom: none; }
.setting-label {
    width: 230px;
    min-width: 230px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #4a5568;
    padding-right: 24px;
    line-height: 1.3;
}
.setting-label small {
    display: block;
    font-weight: 400;
    color: #9aa5b4;
    font-size: 0.7rem;
    margin-top: 1px;
}
.setting-field { flex: 1; max-width: 420px; }

/* ── Select2 sizing ──────────────────────────────────────────────────── */
.select2-container--default .select2-selection--single {
    height: 32px;
    border-color: #dee2e6;
    border-radius: 5px;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 30px;
    font-size: 0.84rem;
    color: #495057;
    padding-left: 10px;
}
.select2-container--default .select2-selection--single .select2-selection__arrow { height: 30px; }
.select2-container--default.select2-container--disabled .select2-selection--single {
    background: #f8f9fa;
    border-color: #e9ecef;
    cursor: default;
}
.select2-container--default.select2-container--disabled .select2-selection--single .select2-selection__rendered {
    color: #6c757d;
}
.form-control:disabled, .form-select:disabled {
    background-color: #f8f9fa;
    color: #6c757d;
}

/* ── Tab pane card ───────────────────────────────────────────────────── */
.tab-card {
    border: 1px solid #dee2e6;
    border-top: none;
    border-radius: 0 0 10px 10px;
    background: #fff;
    padding: 20px 28px;
    min-height: 340px;
}
.gl-group-hint {
    font-size: 0.75rem;
    color: #9aa5b4;
    margin-bottom: 16px;
}
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">

        {{-- Header --}}
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="mb-0 fw-bold">General Ledger Settings</h5>
                <small class="text-muted">Account mappings and system defaults for the GL module</small>
            </div>
            <div class="d-flex gap-2">
                @permission('update_gl_settings')
                <button id="editBtn" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                    <span class="material-symbols-outlined" style="font-size:16px;">edit</span>
                    <span>Edit Settings</span>
                </button>
                <button id="saveBtn" class="btn btn-success btn-sm d-none d-flex align-items-center gap-1">
                    <span class="btn-save-label d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size:16px;">save</span>
                        <span>Save Changes</span>
                    </span>
                    <span class="btn-save-spinner d-none d-flex align-items-center gap-1">
                        <span class="spinner-border spinner-border-sm"></span>
                        <span>Saving…</span>
                    </span>
                </button>
                <button id="cancelBtn" class="btn btn-outline-secondary btn-sm d-none">Cancel</button>
                @endpermission
            </div>
        </div>

        <form id="glSettingsForm">
            @csrf

            {{-- Tab navigation --}}
            <ul class="nav" id="glTabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-equity"     type="button"><span class="material-symbols-outlined">trending_up</span>Equity &amp; Income</button></li>
                <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab-recv"       type="button"><span class="material-symbols-outlined">account_balance_wallet</span>Receivables</button></li>
                <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab-pay"        type="button"><span class="material-symbols-outlined">payments</span>Payables</button></li>
                <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab-bank"       type="button"><span class="material-symbols-outlined">account_balance</span>Bank &amp; Cash</button></li>
                <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab-payroll"    type="button"><span class="material-symbols-outlined">group</span>Payroll &amp; Expenses</button></li>
                <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab-control"    type="button"><span class="material-symbols-outlined">tune</span>Control Accounts</button></li>
                <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab-vat"        type="button"><span class="material-symbols-outlined">percent</span>VAT &amp; Fiscal Year</button></li>
            </ul>

            {{-- Tab content --}}
            <div class="tab-content">

                {{-- Equity & Income --}}
                <div class="tab-pane fade show active tab-card" id="tab-equity">
                    <p class="gl-group-hint">Accounts used for equity movements and income recognition.</p>
                    @foreach([
                        ['retained_earnings_id',              'Retained Earnings',          'Cumulative net income carried forward'],
                        ['income_summary_account_id',         'Income Summary',             'Clearing account for closing entries'],
                        ['revenues_account_id',               'Revenues',                   'Primary revenue account'],
                        ['other_income_account_id',           'Other Income',               'Non-operating income'],
                        ['loans_interests_revenue_account_id','Loans Interest Revenue',     'Interest earned on loans issued'],
                        ['miscallenous_revenue_id',           'Miscellaneous Revenue',      'Unclassified revenue items'],
                    ] as [$name, $label, $hint])
                    <div class="setting-row">
                        <div class="setting-label">{{ $label }}<small>{{ $hint }}</small></div>
                        <div class="setting-field">
                            <select name="{{ $name }}" class="gl-account-select w-100" disabled>
                                @if($setup?->$name && isset($accounts[$setup->$name]))
                                    <option value="{{ $setup->$name }}" selected>{{ $accounts[$setup->$name]->account_code }} — {{ $accounts[$setup->$name]->account_name }}</option>
                                @else
                                    <option value=""></option>
                                @endif
                            </select>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Receivables --}}
                <div class="tab-pane fade tab-card" id="tab-recv">
                    <p class="gl-group-hint">Accounts tracking money owed to the organisation.</p>
                    @foreach([
                        ['receivable_control_id',          'AR Control Account',   'Master control for accounts receivable'],
                        ['clients_account_id',             'Clients',              'Client debtor account'],
                        ['tenants_account_id',             'Tenants',              'Tenant debtor account'],
                        ['loans_receivable_account_id',    'Loans Receivable',     'Outstanding loans given out'],
                        ['interests_receivable_account_id','Interest Receivable',  'Accrued interest yet to be received'],
                    ] as [$name, $label, $hint])
                    <div class="setting-row">
                        <div class="setting-label">{{ $label }}<small>{{ $hint }}</small></div>
                        <div class="setting-field">
                            <select name="{{ $name }}" class="gl-account-select w-100" disabled>
                                @if($setup?->$name && isset($accounts[$setup->$name]))
                                    <option value="{{ $setup->$name }}" selected>{{ $accounts[$setup->$name]->account_code }} — {{ $accounts[$setup->$name]->account_name }}</option>
                                @else
                                    <option value=""></option>
                                @endif
                            </select>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Payables --}}
                <div class="tab-pane fade tab-card" id="tab-pay">
                    <p class="gl-group-hint">Accounts tracking money the organisation owes.</p>
                    @foreach([
                        ['payable_control_account_id',      'AP Control Account',            'Master control for accounts payable'],
                        ['returns_payable_account_id',      'Returns Payable',               'Liability for client returns pending payment'],
                        ['clients_advance_loans_payable_id','Client Advance Loans Payable',  'Advances received from clients'],
                        ['deposits_payable_account_id',     'Deposits Payable',              'Security deposits held on behalf of tenants'],
                    ] as [$name, $label, $hint])
                    <div class="setting-row">
                        <div class="setting-label">{{ $label }}<small>{{ $hint }}</small></div>
                        <div class="setting-field">
                            <select name="{{ $name }}" class="gl-account-select w-100" disabled>
                                @if($setup?->$name && isset($accounts[$setup->$name]))
                                    <option value="{{ $setup->$name }}" selected>{{ $accounts[$setup->$name]->account_code }} — {{ $accounts[$setup->$name]->account_name }}</option>
                                @else
                                    <option value=""></option>
                                @endif
                            </select>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Bank & Cash --}}
                <div class="tab-pane fade tab-card" id="tab-bank">
                    <p class="gl-group-hint">Bank and cash accounts used across the system.</p>
                    @foreach([
                        ['business_bank_account_id', 'Business Bank',  'Primary operating bank account'],
                        ['clients_bank_account_id',  'Clients Bank',   'Bank account for client-related receipts'],
                        ['petty_cash_account_id',    'Petty Cash',     'On-hand cash for small expenses'],
                        ['co_op_bank_id',            'Co-op Bank',     'Co-operative Bank account'],
                        ['equity_bank_account_id',   'Equity Bank',    'Equity Bank account'],
                        ['mpesa_account_id',         'M-Pesa',         'M-Pesa mobile money account'],
                    ] as [$name, $label, $hint])
                    <div class="setting-row">
                        <div class="setting-label">{{ $label }}<small>{{ $hint }}</small></div>
                        <div class="setting-field">
                            <select name="{{ $name }}" class="gl-account-select w-100" disabled>
                                @if($setup?->$name && isset($accounts[$setup->$name]))
                                    <option value="{{ $setup->$name }}" selected>{{ $accounts[$setup->$name]->account_code }} — {{ $accounts[$setup->$name]->account_name }}</option>
                                @else
                                    <option value=""></option>
                                @endif
                            </select>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Payroll & Expenses --}}
                <div class="tab-pane fade tab-card" id="tab-payroll">
                    <p class="gl-group-hint">Accounts used in payroll processing and staff cost tracking.</p>
                    @foreach([
                        ['payroll_expense_account_id',         'Payroll Expense',           'Total gross payroll expense'],
                        ['accrued_payroll_expense_account_id', 'Accrued Payroll Expense',   'Payroll costs accrued but not yet paid'],
                        ['net_salaries_account_id',            'Net Salaries',              'Take-home pay disbursements'],
                        ['other_employee_deductions_id',       'Other Employee Deductions', 'Miscellaneous payroll deductions'],
                        ['earned_commission_acc_id',           'Earned Commission',         'Commission income earned by staff'],
                        ['rent_commission_account_id',         'Rent Commission',           'Commission on rental transactions'],
                    ] as [$name, $label, $hint])
                    <div class="setting-row">
                        <div class="setting-label">{{ $label }}<small>{{ $hint }}</small></div>
                        <div class="setting-field">
                            <select name="{{ $name }}" class="gl-account-select w-100" disabled>
                                @if($setup?->$name && isset($accounts[$setup->$name]))
                                    <option value="{{ $setup->$name }}" selected>{{ $accounts[$setup->$name]->account_code }} — {{ $accounts[$setup->$name]->account_name }}</option>
                                @else
                                    <option value=""></option>
                                @endif
                            </select>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Control Accounts --}}
                <div class="tab-pane fade tab-card" id="tab-control">
                    <p class="gl-group-hint">System control and suspense accounts for transaction routing.</p>
                    @foreach([
                        ['vat_control_account_id',             'VAT Control',               'Collects VAT on sales and purchases'],
                        ['bank_charges_account_id',            'Bank Charges',              'Fees charged by the bank'],
                        ['bank_interest_account_id',           'Bank Interest',             'Interest charged on overdrafts or loans'],
                        ['tenants_suspense_account_id',        'Tenants Suspense',          'Unallocated tenant receipts staging account'],
                        ['payment_voucher_control_account_id', 'Payment Voucher Control',   'Staging account for payment vouchers'],
                    ] as [$name, $label, $hint])
                    <div class="setting-row">
                        <div class="setting-label">{{ $label }}<small>{{ $hint }}</small></div>
                        <div class="setting-field">
                            <select name="{{ $name }}" class="gl-account-select w-100" disabled>
                                @if($setup?->$name && isset($accounts[$setup->$name]))
                                    <option value="{{ $setup->$name }}" selected>{{ $accounts[$setup->$name]->account_code }} — {{ $accounts[$setup->$name]->account_name }}</option>
                                @else
                                    <option value=""></option>
                                @endif
                            </select>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- VAT & Fiscal Year --}}
                <div class="tab-pane fade tab-card" id="tab-vat">
                    <p class="gl-group-hint">Default VAT rates and the currently active financial period.</p>

                    <div class="setting-row">
                        <div class="setting-label">Revenue VAT (%)<small>Default VAT rate applied on revenues</small></div>
                        <div class="setting-field">
                            <input type="number" name="revenue_vat" step="0.01" min="0" max="100"
                                   class="form-control form-control-sm"
                                   style="max-width:140px;"
                                   value="{{ $setup?->revenue_vat ?? '' }}"
                                   disabled>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">Clients VAT (%)<small>Default VAT rate applied on client invoices</small></div>
                        <div class="setting-field">
                            <input type="number" name="clients_vat" step="0.01" min="0" max="100"
                                   class="form-control form-control-sm"
                                   style="max-width:140px;"
                                   value="{{ $setup?->clients_vat ?? '' }}"
                                   disabled>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">Active Fiscal Year<small>The current open accounting period</small></div>
                        <div class="setting-field">
                            <select name="active_fiscal_year_id" class="form-select form-select-sm" style="max-width:300px;" disabled>
                                <option value="">— select period —</option>
                                @foreach($periods as $period)
                                <option value="{{ $period->id }}"
                                    {{ $setup && $setup->active_fiscal_year_id == $period->id ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::parse($period->period_start)->format('d M Y') }}
                                    &ndash;
                                    {{ \Carbon\Carbon::parse($period->period_end)->format('d M Y') }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

            </div>{{-- /tab-content --}}
        </form>

    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function () {
    const searchUrl = '{{ route("ajax.gl.accounts.search") }}';

    // ── Init Select2 ───────────────────────────────────────────────────────────
    $('.gl-account-select').select2({
        theme: 'default',
        width: '100%',
        placeholder: 'Search by code or name…',
        allowClear: true,
        minimumInputLength: 0,
        ajax: {
            url: searchUrl,
            dataType: 'json',
            delay: 250,
            data: params => ({ q: params.term || '' }),
            processResults: data => ({ results: data.results }),
            cache: true,
        },
    });

    // Keep Select2 in sync with disabled state
    function setEditMode(on) {
        $('#glSettingsForm select, #glSettingsForm input').prop('disabled', !on);
        // Select2 picks up disabled state automatically on prop change
    }

    // ── Edit ───────────────────────────────────────────────────────────────────
    $('#editBtn').on('click', function () {
        setEditMode(true);
        $('#editBtn').addClass('d-none');
        $('#saveBtn, #cancelBtn').removeClass('d-none');
    });

    // ── Cancel ─────────────────────────────────────────────────────────────────
    $('#cancelBtn').on('click', function () {
        location.reload();
    });

    // ── Save ───────────────────────────────────────────────────────────────────
    $('#saveBtn').on('click', function () {
        const $btn = $(this);
        $btn.find('.btn-save-label').addClass('d-none');
        $btn.find('.btn-save-spinner').removeClass('d-none');
        $btn.prop('disabled', true);
        $('#cancelBtn').prop('disabled', true);

        // Build payload
        const data = { _token: $('input[name="_token"]').val() };
        $('#glSettingsForm select').each(function () {
            data[$(this).attr('name')] = $(this).val() || '';
        });
        $('#glSettingsForm input[type=number]').each(function () {
            data[$(this).attr('name')] = $(this).val() || '';
        });

        $.ajax({
            url: '{{ route("gl.settings.update") }}',
            method: 'POST',
            data: data,
            success: function (res) {
                if (res.success) {
                    toastr.success('Settings saved successfully.');
                    setTimeout(() => location.reload(), 800);
                } else {
                    toastr.error(res.error || 'Failed to save settings.');
                    resetBtn();
                }
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON?.error || 'Failed to save settings.');
                resetBtn();
            },
        });

        function resetBtn() {
            $btn.find('.btn-save-label').removeClass('d-none');
            $btn.find('.btn-save-spinner').addClass('d-none');
            $btn.prop('disabled', false);
            $('#cancelBtn').prop('disabled', false);
        }
    });
});
</script>
@endsection
