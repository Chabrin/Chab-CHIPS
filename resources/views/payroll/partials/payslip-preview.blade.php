<style>
.ps-wrap { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; font-size: 13px; color: #495057; max-width: 860px; margin: 0 auto; }

/* Header */
.ps-header { display: flex; justify-content: space-between; align-items: center;
             border-bottom: 3px solid #3598db; padding-bottom: 12px; margin-bottom: 16px; }
.ps-co-name { font-size: 22px; font-weight: 700; color: #3598db; letter-spacing: -0.3px; }
.ps-co-sub  { font-size: 11px; color: #9ca3af; margin-top: 2px; }
.ps-slip-label  { font-size: 15px; font-weight: 700; color: #0a0a0a; letter-spacing: 1px; text-align: right; }
.ps-slip-period { font-size: 13px; color: #6b7280; text-align: right; margin-top: 3px; }

/* Info grid */
.ps-info { width: 100%; border-collapse: collapse; margin-bottom: 18px;
           border: 1px solid #d9ebf7; border-radius: 6px; overflow: hidden; }
.ps-info td { padding: 7px 12px; border-bottom: 1px solid #f0f7fd; vertical-align: middle; }
.ps-info tr:last-child td { border-bottom: none; }
.ps-info tr:nth-child(odd)  { background: #f5fafd; }
.ps-info tr:nth-child(even) { background: #fff; }
.ps-lbl { color: #6b7280; font-size: 12px; width: 14%; white-space: nowrap; }
.ps-val { color: #0a0a0a; font-weight: 600; font-size: 13px; width: 36%; }

/* Main table */
.ps-tbl { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
.ps-tbl th, .ps-tbl td { border: 1px solid #d9ebf7; }
.ps-sec { background: #3598db; color: #fff; font-size: 11px; font-weight: 700;
          letter-spacing: 0.7px; padding: 8px 12px; text-transform: uppercase; }
.ps-col { background: #d9ebf7; color: #0a0a0a; font-size: 11px; font-weight: 700; padding: 6px 12px; }
.ps-col-r { text-align: right; }
.ps-tbl tbody tr:nth-child(odd)  td { background: #f5fafd; }
.ps-tbl tbody tr:nth-child(even) td { background: #fff; }
.ps-tbl tbody td { padding: 6px 12px; font-size: 13px; }
.ps-amt { text-align: right; font-variant-numeric: tabular-nums; }
.ps-total td { background: #3598db !important; color: #fff !important;
               font-weight: 700; font-size: 13px; padding: 8px 12px; }

/* Tax strip */
.ps-tax { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
.ps-tax-hdr td { background: #3598db; color: #fff; font-size: 11px; font-weight: 700;
                 letter-spacing: 0.7px; padding: 7px 12px; text-transform: uppercase; }
.ps-tax td { border: 1px solid #d9ebf7; padding: 6px 12px; font-size: 13px; }
.ps-tax tr:nth-child(2) td { background: #f5fafd; }
.ps-tax tr:nth-child(3) td { background: #fff; }
.ps-tx-lbl { color: #6b7280; width: 27%; }
.ps-tx-val { text-align: right; font-weight: 600; color: #0a0a0a; width: 23%; }

/* Net Pay */
.ps-net { width: 100%; border-collapse: collapse; margin-bottom: 16px; border-radius: 6px; overflow: hidden; }
.ps-net td { padding: 10px 14px; }
.ps-net-label  { background: #3598db; color: #fff; font-size: 13px; font-weight: 700;
                 letter-spacing: 0.5px; width: 65%; }
.ps-net-amount { background: #3598db; color: #fff; font-size: 18px; font-weight: 700;
                 text-align: right; width: 35%; }

/* Footer */
.ps-footer { text-align: center; font-size: 11px; color: #9ca3af; padding-top: 10px;
             border-top: 1px solid #d9ebf7; margin-top: 4px; }
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
            <div class="ps-slip-label">PAYSLIP</div>
            <div class="ps-slip-period">{{ date('F Y', strtotime($payroll->period)) }}</div>
        </div>
    </div>

    {{-- Employee Info --}}
    <table class="ps-info">
        <tr>
            <td class="ps-lbl">Employee</td>
            <td class="ps-val">{{ $payroll->staff->full_names ?? 'N/A' }}</td>
            <td class="ps-lbl">Staff No</td>
            <td class="ps-val">{{ $payroll->staff_number_id ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="ps-lbl">Position</td>
            <td class="ps-val">{{ $positionName }}</td>
            <td class="ps-lbl">Department</td>
            <td class="ps-val">{{ $departmentName }}</td>
        </tr>
        <tr>
            <td class="ps-lbl">Period</td>
            <td class="ps-val">{{ date('d/m/Y', strtotime($payroll->period)) }}</td>
            <td class="ps-lbl">Pay Date</td>
            <td class="ps-val">{{ date('d/m/Y', strtotime($payroll->date_time)) }}</td>
        </tr>
        <tr>
            <td class="ps-lbl">Bank</td>
            <td class="ps-val">{{ $bankName }}</td>
            <td class="ps-lbl">Account No</td>
            <td class="ps-val">{{ $accountNumber }}</td>
        </tr>
        <tr>
            <td class="ps-lbl">Acct Name</td>
            <td class="ps-val" colspan="3">{{ $accountName }}</td>
        </tr>
    </table>

    {{-- Earnings & Deductions --}}
    <table class="ps-tbl">
        <thead>
            <tr>
                <th colspan="2" class="ps-sec">Earnings / Additions</th>
                <th colspan="2" class="ps-sec">Deductions</th>
            </tr>
            <tr>
                <th class="ps-col"   style="width:30%;">Description</th>
                <th class="ps-col ps-col-r" style="width:20%;">Amount (KSh)</th>
                <th class="ps-col"   style="width:30%;">Description</th>
                <th class="ps-col ps-col-r" style="width:20%;">Amount (KSh)</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < $maxRows; $i++)
            <tr>
                @if ($additions->has($i))
                    <td>{{ $additions[$i]['name'] }}</td>
                    <td class="ps-amt">{{ number_format($additions[$i]['amount'], 2) }}</td>
                @else
                    <td>&nbsp;</td><td>&nbsp;</td>
                @endif
                @if ($deductions->has($i))
                    <td>{{ $deductions[$i]['name'] }}</td>
                    <td class="ps-amt">{{ number_format($deductions[$i]['amount'], 2) }}</td>
                @else
                    <td>&nbsp;</td><td>&nbsp;</td>
                @endif
            </tr>
            @endfor
            <tr class="ps-total">
                <td>GROSS PAY</td>
                <td class="ps-amt">{{ number_format($grossPay, 2) }}</td>
                <td>TOTAL DEDUCTIONS</td>
                <td class="ps-amt">{{ number_format($payroll->total_deductions, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- PAYE Tax Breakdown --}}
    <table class="ps-tax">
        <tr class="ps-tax-hdr"><td colspan="4">PAYE Tax Calculation</td></tr>
        <tr>
            <td class="ps-tx-lbl">Taxable Earnings</td>
            <td class="ps-tx-val">{{ number_format($taxableIncome, 2) }}</td>
            <td class="ps-tx-lbl">Tax Chargeable</td>
            <td class="ps-tx-val">{{ number_format($taxBeforeRelief, 2) }}</td>
        </tr>
        <tr>
            <td class="ps-tx-lbl">Personal Relief</td>
            <td class="ps-tx-val">2,400.00</td>
            <td class="ps-tx-lbl">PAYE Payable</td>
            <td class="ps-tx-val" style="color:#3598db;">{{ number_format($payroll->tax_payable ?? 0, 2) }}</td>
        </tr>
    </table>

    {{-- Net Pay --}}
    <table class="ps-net">
        <tr>
            <td class="ps-net-label">NET PAY</td>
            <td class="ps-net-amount">KSh {{ number_format($payroll->net_pay, 2) }}</td>
        </tr>
    </table>

    <div class="ps-footer">
        This is a computer-generated payslip and does not require a signature.
    </div>

</div>
