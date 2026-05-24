@extends('layouts.main')

@section('styles')
<style>
.gl-label {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #6b7a90;
    margin-bottom: 3px;
}
.gl-group-title {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #3598DB;
    padding-bottom: 8px;
    margin-bottom: 14px;
    border-bottom: 2px solid #e8f4fd;
    display: flex;
    align-items: center;
    gap: 6px;
}
.gl-group-title .material-symbols-outlined { font-size: 18px; }
.select2-container--default .select2-selection--single {
    height: 31px;
    border-color: #dee2e6;
    border-radius: 4px;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 29px;
    font-size: 0.85rem;
    color: #495057;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 29px;
}
.select2-container--default.select2-container--disabled .select2-selection--single {
    background-color: #f8f9fa;
    cursor: default;
}
.view-mode .select2-container--default .select2-selection--single {
    border-color: transparent;
    background: transparent;
}
.settings-card { border: 1px solid #e3e8ef; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
.settings-card .card-body { padding: 18px 20px; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">

        {{-- Page header --}}
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="mb-0 fw-bold">General Ledger Settings</h5>
                <small class="text-muted">Configure GL account mappings and system defaults</small>
            </div>
            <div class="d-flex gap-2" id="actionBtns">
                @permission('update_gl_settings')
                <button id="editBtn" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                    <span class="material-symbols-outlined" style="font-size:17px;">edit</span>
                    <span>Edit</span>
                </button>
                <button id="saveBtn" class="btn btn-success btn-sm d-none d-flex align-items-center gap-1">
                    <span class="btn-save-label d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size:17px;">save</span>
                        <span>Save Changes</span>
                    </span>
                    <span class="btn-save-spinner d-none d-flex align-items-center gap-1">
                        <span class="spinner-border spinner-border-sm"></span>
                        <span>Saving...</span>
                    </span>
                </button>
                <button id="cancelBtn" class="btn btn-outline-secondary btn-sm d-none">Cancel</button>
                @endpermission
            </div>
        </div>

        <form id="glSettingsForm">
            @csrf

            @php
            $groups = [
                [
                    'title' => 'Equity & Income',
                    'icon'  => 'trending_up',
                    'fields' => [
                        ['retained_earnings_id',           'Retained Earnings'],
                        ['income_summary_account_id',      'Income Summary'],
                        ['revenues_account_id',            'Revenues'],
                        ['other_income_account_id',        'Other Income'],
                        ['loans_interests_revenue_account_id', 'Loans Interest Revenue'],
                        ['miscallenous_revenue_id',        'Miscellaneous Revenue'],
                    ],
                ],
                [
                    'title' => 'Receivables',
                    'icon'  => 'account_balance_wallet',
                    'fields' => [
                        ['receivable_control_id',          'AR Control Account'],
                        ['clients_account_id',             'Clients'],
                        ['tenants_account_id',             'Tenants'],
                        ['loans_receivable_account_id',    'Loans Receivable'],
                        ['interests_receivable_account_id','Interest Receivable'],
                    ],
                ],
                [
                    'title' => 'Payables',
                    'icon'  => 'payments',
                    'fields' => [
                        ['payable_control_account_id',     'AP Control Account'],
                        ['returns_payable_account_id',     'Returns Payable'],
                        ['clients_advance_loans_payable_id','Client Advance Loans Payable'],
                        ['deposits_payable_account_id',    'Deposits Payable'],
                    ],
                ],
                [
                    'title' => 'Bank & Cash',
                    'icon'  => 'account_balance',
                    'fields' => [
                        ['business_bank_account_id',       'Business Bank'],
                        ['clients_bank_account_id',        'Clients Bank'],
                        ['petty_cash_account_id',          'Petty Cash'],
                        ['co_op_bank_id',                  'Co-op Bank'],
                        ['equity_bank_account_id',         'Equity Bank'],
                        ['mpesa_account_id',               'M-Pesa'],
                    ],
                ],
                [
                    'title' => 'Payroll & Expenses',
                    'icon'  => 'group',
                    'fields' => [
                        ['payroll_expense_account_id',          'Payroll Expense'],
                        ['accrued_payroll_expense_account_id',  'Accrued Payroll Expense'],
                        ['net_salaries_account_id',             'Net Salaries'],
                        ['other_employee_deductions_id',        'Other Employee Deductions'],
                        ['earned_commission_acc_id',            'Earned Commission'],
                        ['rent_commission_account_id',          'Rent Commission'],
                    ],
                ],
                [
                    'title' => 'Control Accounts',
                    'icon'  => 'tune',
                    'fields' => [
                        ['vat_control_account_id',              'VAT Control'],
                        ['bank_charges_account_id',             'Bank Charges'],
                        ['bank_interest_account_id',            'Bank Interest'],
                        ['tenants_suspense_account_id',         'Tenants Suspense'],
                        ['payment_voucher_control_account_id',  'Payment Voucher Control'],
                    ],
                ],
            ];
            @endphp

            {{-- Account groups: two columns --}}
            <div class="row g-3 mb-3">
                @foreach($groups as $group)
                <div class="col-xl-6">
                    <div class="card settings-card h-100">
                        <div class="card-body">
                            <div class="gl-group-title">
                                <span class="material-symbols-outlined">{{ $group['icon'] }}</span>
                                {{ $group['title'] }}
                            </div>
                            <div class="row g-2">
                                @foreach($group['fields'] as [$name, $label])
                                <div class="col-sm-6">
                                    <label class="gl-label">{{ $label }}</label>
                                    <select name="{{ $name }}"
                                            class="form-select form-select-sm gl-account-select w-100"
                                            disabled>
                                        @if($setup && $setup->$name && isset($accounts[$setup->$name]))
                                        <option value="{{ $setup->$name }}" selected>
                                            {{ $accounts[$setup->$name]->account_code }} — {{ $accounts[$setup->$name]->account_name }}
                                        </option>
                                        @else
                                        <option value="">— not set —</option>
                                        @endif
                                    </select>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- VAT & Fiscal Year --}}
            <div class="card settings-card mb-3">
                <div class="card-body">
                    <div class="gl-group-title">
                        <span class="material-symbols-outlined">percent</span>
                        VAT &amp; Fiscal Year
                    </div>
                    <div class="row g-2">
                        <div class="col-sm-3">
                            <label class="gl-label">Revenue VAT (%)</label>
                            <input type="number" name="revenue_vat" step="0.01" min="0" max="100"
                                   class="form-control form-control-sm"
                                   value="{{ $setup?->revenue_vat ?? '' }}"
                                   disabled>
                        </div>
                        <div class="col-sm-3">
                            <label class="gl-label">Clients VAT (%)</label>
                            <input type="number" name="clients_vat" step="0.01" min="0" max="100"
                                   class="form-control form-control-sm"
                                   value="{{ $setup?->clients_vat ?? '' }}"
                                   disabled>
                        </div>
                        <div class="col-sm-4">
                            <label class="gl-label">Active Fiscal Year</label>
                            <select name="active_fiscal_year_id"
                                    class="form-select form-select-sm"
                                    id="fiscalYearSelect"
                                    disabled>
                                <option value="">— select period —</option>
                                @foreach($periods as $period)
                                <option value="{{ $period->id }}"
                                    {{ $setup && $setup->active_fiscal_year_id == $period->id ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::parse($period->period_start)->format('d/m/Y') }}
                                    &ndash;
                                    {{ \Carbon\Carbon::parse($period->period_end)->format('d/m/Y') }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

        </form>

    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function () {
    const searchUrl = '{{ route("ajax.gl.accounts.search") }}';

    // ── Init Select2 on all account selects ───────────────────────────────────
    function initSelect2() {
        $('.gl-account-select').each(function () {
            if ($(this).data('select2')) return; // already initialised
            $(this).select2({
                theme: 'default',
                width: '100%',
                placeholder: 'Search account…',
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
        });
    }

    initSelect2();

    // ── Edit mode ──────────────────────────────────────────────────────────────
    $('#editBtn').on('click', function () {
        // Enable all inputs & selects
        $('#glSettingsForm select, #glSettingsForm input').prop('disabled', false);

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

        const data = {};
        // Collect selects (including Select2)
        $('#glSettingsForm select').each(function () {
            data[$(this).attr('name')] = $(this).val() || '';
        });
        // Collect inputs
        $('#glSettingsForm input[type=number]').each(function () {
            data[$(this).attr('name')] = $(this).val() || '';
        });
        data['_token'] = $('input[name="_token"]').val();

        $.ajax({
            url: '{{ route("gl.settings.update") }}',
            method: 'POST',
            data: data,
            success: function (res) {
                if (res.success) {
                    toastr.success('GL settings saved successfully.');
                    setTimeout(() => location.reload(), 800);
                } else {
                    toastr.error(res.error || 'Failed to save settings.');
                    resetSaveBtn();
                }
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON?.error || 'Failed to save settings.');
                resetSaveBtn();
            }
        });

        function resetSaveBtn() {
            $btn.find('.btn-save-label').removeClass('d-none');
            $btn.find('.btn-save-spinner').addClass('d-none');
            $btn.prop('disabled', false);
            $('#cancelBtn').prop('disabled', false);
        }
    });
});
</script>
@endsection
