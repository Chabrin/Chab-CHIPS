<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $payroll->staff_number_id }}</title>
    <style>
        body {
            font-family: 'Segoe UI', 'Calibri', 'Arial', sans-serif;
            font-size: 11px;
            margin: 20px;
            color: #000;
            letter-spacing: 0.3px;
        }

        .payslip-header {
            display: table;
            width: 100%;
            margin-bottom: 15px;
            padding-bottom: 8px;
        }

        .payslip-header .left {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .payslip-header .right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            text-align: right;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #3598db;
        }

        .payslip-title {
            font-size: 11px;
            font-weight: bold;
            color: #000;
        }

        .period {
            font-size: 11px;
            color: #000;
            margin-top: 2px;
        }

        .employee-info {
            margin-bottom: 12px;
            font-size: 11px;
            line-height: 1.5;
        }

        .employee-info table {
            width: 100%;
            border-collapse: collapse;
        }

        .employee-info td {
            padding: 2px 5px;
            vertical-align: top;
        }

        .employee-info strong {
            color: #000;
            font-weight: normal;
            font-size: 11px;
            font-family: 'Segoe UI', 'Calibri', 'Arial', sans-serif;
        }

        .payslip-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 11px;
        }

        .payslip-table th {
            background: transparent;
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: 600;
            color: #000;
            font-size: 11px;
            font-family: 'Segoe UI', 'Calibri', 'Arial', sans-serif;
        }

        .payslip-table td {
            padding: 6px 8px;
            border: 1px solid #ddd;
            color: #000;
            font-family: 'Segoe UI', 'Calibri', 'Arial', sans-serif;
        }

        .text-right {
            text-align: right;
        }

        .total-row {
            background: transparent;
            font-weight: 600;
            color: #000;
        }

        .net-pay-row {
            background: transparent;
            font-weight: 600;
            font-size: 11px;
            color: #3598db;
        }

        .section-title {
            background: transparent;
            color: #3598db;
            padding: 6px 8px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid #ddd !important;
        }

        .footer {
            margin-top: 15px;
            font-size: 9px;
            color: #666;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="payslip-header">
        <div class="left">
            <div class="company-name">CHABRIN AGENCIES LTD</div>
            <div style="font-size: 11px; color: #666; margin-top: 5px;">Currency: KSh</div>
        </div>
        <div class="right">
            <div class="payslip-title">PAYSLIP</div>
            <div class="period">{{ date('F Y', strtotime($payroll->period)) }}</div>
        </div>
    </div>

    <div class="employee-info">
        <table>
            <tr>
                <td style="width: 20%;">Employee:</td>
                <td style="width: 30%;">{{ $payroll->staff->full_names ?? 'N/A' }}</td>
                <td style="width: 20%;">Staff No:</td>
                <td style="width: 30%;">{{ $payroll->staff_number_id ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Position:</td>
                <td>{{ $payroll->position->position_name ?? 'N/A' }}</td>
                <td>Department:</td>
                <td>{{ $payroll->department->department_name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Period:</td>
                <td>{{ date('d/m/Y', strtotime($payroll->period)) }}</td>
                <td>Pay Date:</td>
                <td>{{ date('d/m/Y', strtotime($payroll->date_time)) }}</td>
            </tr>
        </table>
    </div>

    <table class="payslip-table">
        <thead>
            <tr>
                <th colspan="2" class="section-title">Earnings / Additions</th>
                <th colspan="2" class="section-title">Deductions</th>
            </tr>
            <tr>
                <th>Description</th>
                <th class="text-right">Amount (KSh)</th>
                <th>Description</th>
                <th class="text-right">Amount (KSh)</th>
            </tr>
        </thead>
        <tbody>
            @php
            $additions = collect([['name' => 'Basic Salary', 'amount' => $payroll->basic_salary]]);
            if($payroll->salary_arrears > 0) {
            $additions->push(['name' => 'Salary Arrears', 'amount' => $payroll->salary_arrears]);
            }
            if($payroll->acting_allowance > 0) {
            $additions->push(['name' => 'Acting Allowance', 'amount' => $payroll->acting_allowance]);
            }
            // Add element_type 2 (additions/allowances) - only non-zero
            foreach($elements->where('element_type', '2')->filter(function($element) { return $element['amount'] > 0; }) as $element) {
            $additions->push(['name' => $element['element_name'], 'amount' => $element['amount']]);
            }

            $deductions = collect();
            // Add element_type 1 (deductions) - only non-zero
            foreach($elements->where('element_type', '1')->filter(function($element) { return $element['amount'] > 0; }) as $element) {
            $deductions->push(['name' => $element['element_name'], 'amount' => $element['amount']]);
            }
            if($payroll->other_deductions > 0) {
            $deductions->push(['name' => 'Other Deductions', 'amount' => $payroll->other_deductions]);
            }

            $maxRows = max($additions->count(), $deductions->count());
            @endphp

            @for($i = 0; $i < $maxRows; $i++)
                <tr>
                @if($additions->has($i))
                <td>{{ $additions[$i]['name'] }}</td>
                <td class="text-right">{{ number_format($additions[$i]['amount'], 2) }}</td>
                @else
                <td>&nbsp;</td>
                <td class="text-right">&nbsp;</td>
                @endif

                @if($deductions->has($i))
                <td>{{ $deductions[$i]['name'] }}</td>
                <td class="text-right">{{ number_format($deductions[$i]['amount'], 2) }}</td>
                @else
                <td>&nbsp;</td>
                <td class="text-right">&nbsp;</td>
                @endif
                </tr>
                @endfor

                <tr class="total-row">
                    <td>GROSS PAY</td>
                    <td class="text-right">{{ number_format($payroll->basic_salary + $payroll->salary_arrears + $payroll->acting_allowance + $elements->where('element_type', '2')->sum('amount'), 2) }}</td>
                    <td>TOTAL DEDUCTIONS</td>
                    <td class="text-right">{{ number_format($payroll->total_deductions, 2) }}</td>
                </tr>
        </tbody>
    </table>

    <table class="payslip-table">
        <tbody>
            <tr class="net-pay-row">
                <td><strong>NET PAY</strong></td>
                <td class="text-right"><strong>{{ number_format($payroll->net_pay, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>This is a computer-generated payslip and does not require a signature.</p>
        <p>Generated on {{ date('d/m/Y H:i:s') }}</p>
    </div>
</body>

</html>