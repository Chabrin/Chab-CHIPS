<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600&display=swap" rel="stylesheet">

<style>
.ps-wrap {
    font-family: "Inter", "Helvetica Neue", Arial, sans-serif;
    font-weight: 300;
    color: #1c1c1e;
    -webkit-font-smoothing: antialiased;
    font-variant-numeric: lining-nums tabular-nums;
    font-feature-settings: "lnum" 1, "tnum" 1;
    font-size: 13px;
    line-height: 1.5;
    max-width: 860px;
    margin: 0 auto;
    background: #fff;
    padding: 36px 40px;
    border-top: 3px solid #1c1c1e;
}

/* Header */
.ps-header { display: flex; justify-content: space-between; align-items: flex-start;
             gap: 24px; padding-bottom: 20px; border-bottom: 1px solid #dcdcdf; }
.ps-co-name { font-size: 20px; font-weight: 500; letter-spacing: -0.01em; }
.ps-co-sub  { color: #6b6b70; font-size: 12px; margin-top: 4px; }
.ps-slip-label  { font-size: 24px; font-weight: 200; letter-spacing: 0.18em; text-transform: uppercase; text-align: right; }
.ps-slip-period { color: #6b6b70; font-size: 12px; text-align: right; margin-top: 2px; }

/* Info grid */
.ps-info { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px 24px;
           padding: 20px 0; border-bottom: 1px solid #dcdcdf; }
.ps-field label { display: block; font-size: 10px; font-weight: 400; text-transform: uppercase;
                   letter-spacing: 0.1em; color: #6b6b70; }
.ps-field span { font-size: 13px; font-weight: 400; }

/* Earnings / Deductions */
.ps-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; padding-top: 20px; }
.ps-wrap h2 { font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.14em;
              color: #1c1c1e; margin-bottom: 8px; }
.ps-tbl { width: 100%; border-collapse: collapse; }
.ps-tbl td, .ps-tbl th { padding: 7px 0; border-bottom: 1px solid #f5f5f6; text-align: left; font-weight: 300; }
.ps-tbl th { font-size: 10px; font-weight: 400; text-transform: uppercase; letter-spacing: 0.1em;
             color: #6b6b70; border-bottom: 1px solid #dcdcdf; }
.ps-amt { text-align: right; white-space: nowrap; }
.ps-total td { font-weight: 500; border-top: 1px solid #1c1c1e; border-bottom: none; padding-top: 9px; }

/* Tax strip */
.ps-tax { margin-top: 24px; background: #f5f5f6; padding: 16px 18px; }
.ps-tax .ps-tbl td { border-bottom-color: #e6e6e8; }

/* Net Pay */
.ps-net { margin-top: 24px; display: flex; justify-content: space-between; align-items: baseline;
          padding: 18px 0; border-top: 1px solid #1c1c1e; border-bottom: 1px solid #1c1c1e; }
.ps-net-label { font-size: 12px; font-weight: 400; text-transform: uppercase; letter-spacing: 0.16em; }
.ps-net-amount { font-size: 28px; font-weight: 200; letter-spacing: -0.01em; }
.ps-net-amount small { font-size: 13px; font-weight: 300; color: #6b6b70; margin-right: 6px; }

/* Footer */
.ps-footer { text-align: center; font-size: 11px; color: #6b6b70; padding-top: 10px;
             border-top: 1px solid #dcdcdf; margin-top: 24px; }

@media (max-width: 640px) {
    .ps-wrap { padding: 24px 18px; }
    .ps-header { flex-direction: column; }
    .ps-slip-label, .ps-slip-period { text-align: left; }
    .ps-info { grid-template-columns: 1fr 1fr; }
    .ps-columns { grid-template-columns: 1fr; }
    .ps-net-amount { font-size: 22px; }
}
</style>

<div class="ps-wrap">

    @php
        $positionName   = $payroll->position->position_name   ?? $payroll->staff->position->position_name   ?? 'N/A';
        $departmentName = $payroll->department->department_name ?? $payroll->staff->department->department_name ?? 'N/A';
        $bankName       = $payroll->staff->bank->bank_name ?? 'N/A';
        $accountName    = $payroll->staff->account_name   ?? 'N/A';
        $accountNumber  = $payroll->staff->account_number ?? 'N/A';

        $additions = collect([['name' => 'Basic Salary', 'amount' => $payroll->basic_salary]]);
        if (($payroll->salary_arrears   ?? 0) > 0) $additions->push(['name' => 'Salary Arrears',   'amount' => $payroll->salary_arrears]);
        if (($payroll->acting_allowance ?? 0) > 0) $additions->push(['name' => 'Acting Allowance', 'amount' => $payroll->acting_allowance]);
        foreach ($elements->where('element_type', '2')->filter(fn($e) => $e['amount'] > 0) as $e)
            $additions->push(['name' => $e['element_name'], 'amount' => $e['amount']]);

        $deductions = collect();
        foreach ($elements->where('element_type', '1')->filter(fn($e) => $e['amount'] > 0) as $e)
            $deductions->push(['name' => $e['element_name'], 'amount' => $e['amount']]);
        if (($payroll->akiba_sacco_loan        ?? 0) > 0) $deductions->push(['name' => 'Akiba Sacco Loan',        'amount' => $payroll->akiba_sacco_loan]);
        if (($payroll->akiba_share_contribution ?? 0) > 0) $deductions->push(['name' => 'Akiba Share Contribution','amount' => $payroll->akiba_share_contribution]);
        if (($payroll->social_welfare           ?? 0) > 0) $deductions->push(['name' => 'Social Welfare',          'amount' => $payroll->social_welfare]);
        if (($payroll->other_deductions         ?? 0) > 0) $deductions->push(['name' => 'Other Deductions',        'amount' => $payroll->other_deductions]);

        $grossPay        = $payroll->basic_salary + ($payroll->salary_arrears ?? 0) + ($payroll->acting_allowance ?? 0)
                           + $elements->where('element_type', '2')->sum('amount');
        $taxableIncome   = $grossPay - ($payroll->nssf ?? 0) - ($payroll->nhif ?? 0) - ($payroll->housing_levy ?? 0);
        $taxBeforeRelief = ($payroll->tax_payable ?? 0) + 2400;
        $maxRows         = max($additions->count(), $deductions->count());
    @endphp

    {{-- Header --}}
    <div class="ps-header">
        <div>
            <div class="ps-co-name">CHABRIN AGENCIES LTD</div>
            <div class="ps-co-sub">Currency: Kenya Shillings (KSh)</div>
        </div>
        <div>
            <div class="ps-slip-label">Payslip</div>
            <div class="ps-slip-period">{{ date('F Y', strtotime($payroll->period)) }}</div>
        </div>
    </div>

    {{-- Employee Info --}}
    <div class="ps-info">
        <div class="ps-field"><label>Employee</label><span>{{ $payroll->staff->full_names ?? 'N/A' }}</span></div>
        <div class="ps-field"><label>Staff No</label><span>{{ $payroll->staff_number_id ?? 'N/A' }}</span></div>
        <div class="ps-field"><label>Position</label><span>{{ $positionName }}</span></div>
        <div class="ps-field"><label>Department</label><span>{{ $departmentName }}</span></div>
        <div class="ps-field"><label>Period</label><span>{{ date('d/m/Y', strtotime($payroll->period)) }}</span></div>
        <div class="ps-field"><label>Pay Date</label><span>{{ date('d/m/Y', strtotime($payroll->date_time)) }}</span></div>
        <div class="ps-field"><label>Days Paid</label><span>{{ ($payroll->worked_days ?? '') === '' ? '-' : rtrim(rtrim(number_format((float) $payroll->worked_days, 2, '.', ''), '0'), '.') }}</span></div>
        <div class="ps-field"><label>Period Days</label><span>{{ $payroll->payrollPeriod?->duration_days ?? '-' }}</span></div>
        <div class="ps-field"><label>Bank</label><span>{{ $bankName }}</span></div>
        <div class="ps-field"><label>Account No</label><span>{{ $accountNumber }}</span></div>
        <div class="ps-field"><label>Account Name</label><span>{{ $accountName }}</span></div>
    </div>

    {{-- Earnings & Deductions --}}
    <div class="ps-columns">
        <div>
            <h2>Earnings</h2>
            <table class="ps-tbl">
                <thead><tr><th>Description</th><th class="ps-amt">KSh</th></tr></thead>
                <tbody>
                    @foreach ($additions as $item)
                    <tr><td>{{ $item['name'] }}</td><td class="ps-amt">{{ number_format($item['amount'], 2) }}</td></tr>
                    @endforeach
                    <tr class="ps-total"><td>Gross Pay</td><td class="ps-amt">{{ number_format($grossPay, 2) }}</td></tr>
                </tbody>
            </table>
        </div>
        <div>
            <h2>Deductions</h2>
            <table class="ps-tbl">
                <thead><tr><th>Description</th><th class="ps-amt">KSh</th></tr></thead>
                <tbody>
                    @foreach ($deductions as $item)
                    <tr><td>{{ $item['name'] }}</td><td class="ps-amt">{{ number_format($item['amount'], 2) }}</td></tr>
                    @endforeach
                    <tr class="ps-total"><td>Total Deductions</td><td class="ps-amt">{{ number_format($payroll->total_deductions, 2) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- PAYE Tax Breakdown --}}
    <div class="ps-tax">
        <h2>Tax Summary</h2>
        <table class="ps-tbl">
            <tbody>
                <tr><td>Taxable Pay</td><td class="ps-amt">{{ number_format($taxableIncome, 2) }}</td></tr>
                <tr><td>Tax Chargeable</td><td class="ps-amt">{{ number_format($taxBeforeRelief, 2) }}</td></tr>
                <tr><td>Personal Relief</td><td class="ps-amt">2,400.00</td></tr>
                <tr class="ps-total"><td>PAYE Payable</td><td class="ps-amt">{{ number_format($payroll->tax_payable ?? 0, 2) }}</td></tr>
            </tbody>
        </table>
    </div>

    {{-- Net Pay --}}
    <div class="ps-net">
        <div class="ps-net-label">Net Pay</div>
        <div class="ps-net-amount"><small>KSh</small>{{ number_format($payroll->net_pay, 2) }}</div>
    </div>

    <div class="ps-footer">
        This is a computer-generated payslip and does not require a signature.
    </div>

</div>
