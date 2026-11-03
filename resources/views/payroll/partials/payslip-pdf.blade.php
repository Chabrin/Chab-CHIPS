<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $payroll->staff_number_id }}</title>
    <style>
        {{-- Plain, colorless payslip — dompdf can't embed remote fonts, so this
             uses Helvetica (a built-in PDF base font) rather than Inter; the
             browser-rendered preview (payslip-preview.blade.php) uses real
             Inter since it doesn't have that constraint. --}}
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #1c1c1e;
            padding: 24px;
            background: #fff;
        }

        /* ── Header ── */
        .hdr { display: table; width: 100%; padding-bottom: 14px; margin-bottom: 18px; }
        .hdr-left  { display: table-cell; vertical-align: bottom; }
        .hdr-right { display: table-cell; vertical-align: bottom; text-align: right; }
        .co-name   { font-size: 16px; font-weight: bold; letter-spacing: -0.2px; }
        .co-sub    { font-size: 9px; color: #6b6b70; margin-top: 2px; }
        .slip-label { font-size: 15px; letter-spacing: 3px; text-transform: uppercase; }
        .slip-period { font-size: 10px; color: #6b6b70; margin-top: 3px; }

        /* ── Employee Info ── */
        .info-box { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-box td { padding: 4px 0; font-size: 10px; }
        .lbl { color: #6b6b70; width: 15%; white-space: nowrap; text-transform: uppercase; font-size: 8px; letter-spacing: 0.5px; }
        .val { color: #1c1c1e; width: 35%; padding-left: 6px; }

        /* ── Main payslip table ── */
        .slip-tbl { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .sec-hdr { font-size: 9px; font-weight: bold; letter-spacing: 1px; padding-bottom: 8px; text-transform: uppercase; }
        .col-hdr { color: #6b6b70; font-size: 8px; font-weight: normal; text-transform: uppercase; letter-spacing: 0.5px; padding: 6px 0 6px 0; }
        .col-hdr-r { text-align: right; }

        .slip-tbl tbody td { padding: 5px 0; font-size: 10px; }
        .amt { text-align: right; }

        /* Gap between the Earnings and Deductions halves of the shared table */
        .slip-tbl td:nth-child(2), .slip-tbl th:nth-child(2) { padding-right: 18px; }
        .slip-tbl td:nth-child(3), .slip-tbl th:nth-child(3) { padding-left: 18px; }

        .total-row td { font-weight: bold; padding-top: 10px; }

        /* ── Tax table ── */
        .tax-tbl { width: 100%; border-collapse: collapse; margin-bottom: 16px; background: #f5f5f6; }
        .tax-hdr td { font-size: 9px; font-weight: bold; letter-spacing: 1px; padding: 10px 10px 4px 10px; text-transform: uppercase; }
        .tax-tbl td { padding: 4px 10px; font-size: 10px; }
        .tax-tbl tr:last-child td { padding-bottom: 10px; }
        .tax-lbl { color: #6b6b70; width: 27%; }
        .tax-val { text-align: right; width: 23%; }

        /* ── Net Pay ── */
        .net-tbl { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .net-tbl td { padding: 6px 0; }
        .net-label { font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; width: 70%; }
        .net-amount { font-size: 18px; font-weight: bold; text-align: right; width: 30%; }

        /* ── Footer ── */
        .footer { padding-top: 10px; margin-top: 8px; font-size: 8px; color: #6b6b70; text-align: center; }
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
        <div class="slip-label">Payslip</div>
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
        <td class="lbl">Days Paid</td>
        <td class="val">{{ rtrim(rtrim(number_format((float) ($payroll->worked_days ?? 0), 2, '.', ''), '0'), '.') }}</td>
        <td class="lbl">Period Days</td>
        <td class="val">{{ $payroll->payrollPeriod?->duration_days ?? '-' }}</td>
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
            <td>Gross Pay</td>
            <td class="amt">{{ number_format($grossPay, 2) }}</td>
            <td>Total Deductions</td>
            <td class="amt">{{ number_format($payroll->total_deductions, 2) }}</td>
        </tr>
    </tbody>
</table>

{{-- PAYE Tax Breakdown --}}
<table class="tax-tbl">
    <tr class="tax-hdr">
        <td colspan="4">Tax Summary</td>
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
        <td class="tax-val">{{ number_format($payroll->tax_payable ?? 0, 2) }}</td>
    </tr>
</table>

{{-- Net Pay --}}
<table class="net-tbl">
    <tr>
        <td class="net-label">Net Pay</td>
        <td class="net-amount">KSh {{ number_format($payroll->net_pay, 2) }}</td>
    </tr>
</table>

<div class="footer">
    This is a computer-generated payslip and does not require a signature.
</div>

</body>
</html>
