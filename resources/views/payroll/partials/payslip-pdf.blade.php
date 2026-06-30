<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $payroll->staff_number_id }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #1e293b;
            padding: 24px;
            background: #fff;
        }

        /* ── Header ── */
        .hdr { display: table; width: 100%; border-bottom: 3px solid #1e40af; padding-bottom: 10px; margin-bottom: 14px; }
        .hdr-left  { display: table-cell; vertical-align: middle; }
        .hdr-right { display: table-cell; vertical-align: middle; text-align: right; }
        .co-name   { font-size: 18px; font-weight: bold; color: #1e40af; letter-spacing: -0.3px; }
        .co-sub    { font-size: 9px; color: #64748b; margin-top: 2px; }
        .slip-label { font-size: 13px; font-weight: bold; color: #1e293b; letter-spacing: 1px; }
        .slip-period { font-size: 11px; color: #475569; margin-top: 3px; }

        /* ── Employee Info ── */
        .info-box { width: 100%; border-collapse: collapse; margin-bottom: 14px;
                    border: 1px solid #cbd5e1; border-radius: 4px; }
        .info-box td { padding: 4px 8px; font-size: 10px; border-bottom: 1px solid #e2e8f0; }
        .info-box tr:last-child td { border-bottom: none; }
        .info-box tr:nth-child(odd) td  { background: #f8fafc; }
        .info-box tr:nth-child(even) td { background: #ffffff; }
        .lbl { color: #64748b; width: 15%; white-space: nowrap; }
        .val { color: #0f172a; font-weight: 600; width: 35%; }

        /* ── Main payslip table ── */
        .slip-tbl { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .slip-tbl th, .slip-tbl td { border: 1px solid #cbd5e1; }

        .sec-hdr { background: #1e40af; color: #fff; font-size: 9px; font-weight: bold;
                   letter-spacing: 0.6px; padding: 6px 8px; text-transform: uppercase; }
        .col-hdr { background: #e0e7ff; color: #1e3a8a; font-size: 9px; font-weight: 700;
                   padding: 5px 8px; }
        .col-hdr-r { text-align: right; }

        .slip-tbl tbody tr:nth-child(odd)  td { background: #f8fafc; }
        .slip-tbl tbody tr:nth-child(even) td { background: #ffffff; }
        .slip-tbl tbody td { padding: 4px 8px; font-size: 10px; }
        .amt { text-align: right; }

        .total-row td { background: #1e3a8a !important; color: #fff !important;
                        font-weight: 700; font-size: 10px; padding: 6px 8px; }

        /* ── Tax table ── */
        .tax-tbl { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .tax-hdr td { background: #0f172a; color: #fff; font-size: 9px; font-weight: bold;
                      letter-spacing: 0.6px; padding: 5px 8px; text-transform: uppercase; }
        .tax-tbl td { border: 1px solid #cbd5e1; padding: 4px 8px; font-size: 10px; }
        .tax-tbl tr:nth-child(odd)  td { background: #f8fafc; }
        .tax-tbl tr:nth-child(even) td { background: #fff; }
        .tax-lbl { color: #475569; width: 27%; }
        .tax-val { text-align: right; font-weight: 600; color: #0f172a; width: 23%; }

        /* ── Net Pay ── */
        .net-tbl { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .net-tbl td { padding: 8px 10px; }
        .net-label { background: #0f172a; color: #fff; font-size: 11px; font-weight: bold;
                     width: 70%; letter-spacing: 0.5px; }
        .net-amount { background: #0f172a; color: #4ade80; font-size: 14px; font-weight: bold;
                      text-align: right; width: 30%; }

        /* ── Footer ── */
        .footer { border-top: 1px solid #e2e8f0; padding-top: 8px; margin-top: 4px;
                  font-size: 8px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>

@php
    $positionName   = $payroll->position->position_name   ?? $payroll->staff->position->position_name   ?? 'N/A';
    $departmentName = $payroll->department->department_name ?? $payroll->staff->department->department_name ?? 'N/A';
    $bankName       = $payroll->staff->bank->bank_name ?? 'N/A';
    $accountName    = $payroll->staff->account_name   ?? 'N/A';
    $accountNumber  = $payroll->staff->account_number ?? 'N/A';

    $additions = collect([['name' => 'Basic Salary', 'amount' => $payroll->basic_salary]]);
    if (($payroll->salary_arrears  ?? 0) > 0) $additions->push(['name' => 'Salary Arrears',   'amount' => $payroll->salary_arrears]);
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
<div class="hdr">
    <div class="hdr-left">
        <div class="co-name">CHABRIN AGENCIES LTD</div>
        <div class="co-sub">Currency: Kenya Shillings (KSh)</div>
    </div>
    <div class="hdr-right">
        <div class="slip-label">PAYSLIP</div>
        <div class="slip-period">{{ date('F Y', strtotime($payroll->period)) }}</div>
    </div>
</div>

{{-- Employee Info --}}
<table class="info-box">
    <tr>
        <td class="lbl">Employee</td>
        <td class="val">{{ $payroll->staff->full_names ?? 'N/A' }}</td>
        <td class="lbl">Staff No</td>
        <td class="val">{{ $payroll->staff_number_id ?? 'N/A' }}</td>
    </tr>
    <tr>
        <td class="lbl">Position</td>
        <td class="val">{{ $positionName }}</td>
        <td class="lbl">Department</td>
        <td class="val">{{ $departmentName }}</td>
    </tr>
    <tr>
        <td class="lbl">Period</td>
        <td class="val">{{ date('d/m/Y', strtotime($payroll->period)) }}</td>
        <td class="lbl">Pay Date</td>
        <td class="val">{{ date('d/m/Y', strtotime($payroll->date_time)) }}</td>
    </tr>
    <tr>
        <td class="lbl">Bank</td>
        <td class="val">{{ $bankName }}</td>
        <td class="lbl">Account No</td>
        <td class="val">{{ $accountNumber }}</td>
    </tr>
    <tr>
        <td class="lbl">Acct Name</td>
        <td class="val" colspan="3">{{ $accountName }}</td>
    </tr>
</table>

{{-- Earnings & Deductions --}}
<table class="slip-tbl">
    <thead>
        <tr>
            <th colspan="2" class="sec-hdr">Earnings / Additions</th>
            <th colspan="2" class="sec-hdr">Deductions</th>
        </tr>
        <tr>
            <th class="col-hdr"     style="width:30%;">Description</th>
            <th class="col-hdr col-hdr-r" style="width:20%;">Amount (KSh)</th>
            <th class="col-hdr"     style="width:30%;">Description</th>
            <th class="col-hdr col-hdr-r" style="width:20%;">Amount (KSh)</th>
        </tr>
    </thead>
    <tbody>
        @for ($i = 0; $i < $maxRows; $i++)
        <tr>
            @if ($additions->has($i))
                <td>{{ $additions[$i]['name'] }}</td>
                <td class="amt">{{ number_format($additions[$i]['amount'], 2) }}</td>
            @else
                <td>&nbsp;</td><td>&nbsp;</td>
            @endif
            @if ($deductions->has($i))
                <td>{{ $deductions[$i]['name'] }}</td>
                <td class="amt">{{ number_format($deductions[$i]['amount'], 2) }}</td>
            @else
                <td>&nbsp;</td><td>&nbsp;</td>
            @endif
        </tr>
        @endfor
        <tr class="total-row">
            <td>GROSS PAY</td>
            <td class="amt">{{ number_format($grossPay, 2) }}</td>
            <td>TOTAL DEDUCTIONS</td>
            <td class="amt">{{ number_format($payroll->total_deductions, 2) }}</td>
        </tr>
    </tbody>
</table>

{{-- PAYE Tax Breakdown --}}
<table class="tax-tbl">
    <tr class="tax-hdr">
        <td colspan="4">PAYE Tax Calculation</td>
    </tr>
    <tr>
        <td class="tax-lbl">Taxable Earnings</td>
        <td class="tax-val">{{ number_format($taxableIncome, 2) }}</td>
        <td class="tax-lbl">Tax Chargeable</td>
        <td class="tax-val">{{ number_format($taxBeforeRelief, 2) }}</td>
    </tr>
    <tr>
        <td class="tax-lbl">Personal Relief</td>
        <td class="tax-val">2,400.00</td>
        <td class="tax-lbl">PAYE Payable</td>
        <td class="tax-val" style="color:#1e40af;">{{ number_format($payroll->tax_payable ?? 0, 2) }}</td>
    </tr>
</table>

{{-- Net Pay --}}
<table class="net-tbl">
    <tr>
        <td class="net-label">NET PAY</td>
        <td class="net-amount">KSh {{ number_format($payroll->net_pay, 2) }}</td>
    </tr>
</table>

<div class="footer">
    This is a computer-generated payslip and does not require a signature. &nbsp;|&nbsp; Generated on {{ date('d/m/Y H:i:s') }}
</div>

</body>
</html>
