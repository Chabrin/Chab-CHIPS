<div class="payslip-preview" style="font-size: 11px;">
    <div class="payslip-header">
        <div>
            <div class="company-name" style="font-size: 16px; font-weight: bold;">CHABRIN AGENCIES LTD</div>
            <div style="font-size: 11px; color: #666; margin-top: 5px;">Currency: KSh</div>
        </div>
        <div>
            <div class="payslip-title" style="font-size: 11px;">PAYSLIP</div>
            <div class="period" style="font-size: 11px;">{{ date('F Y', strtotime($payroll->period)) }}</div>
        </div>
    </div>

    <div class="employee-info" style="font-size: 11px;">
        <table>
            <tr>
                <td style="width: 20%; font-size: 11px;">Employee:</td>
                <td style="width: 30%; font-size: 11px;">{{ $payroll->staff->full_names ?? 'N/A' }}</td>
                <td style="width: 20%; font-size: 11px;">Staff No:</td>
                <td style="width: 30%; font-size: 11px;">{{ $payroll->staff_number_id ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="font-size: 11px;">Position:</td>
                <td style="font-size: 11px;">{{ $payroll->position->position_name ?? 'N/A' }}</td>
                <td style="font-size: 11px;">Department:</td>
                <td style="font-size: 11px;">{{ $payroll->department->department_name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="font-size: 11px;">Period:</td>
                <td style="font-size: 11px;">{{ date('d/m/Y', strtotime($payroll->period)) }}</td>
                <td style="font-size: 11px;">Pay Date:</td>
                <td style="font-size: 11px;">{{ date('d/m/Y', strtotime($payroll->date_time)) }}</td>
            </tr>
        </table>
    </div>

    <table class="payslip-table" style="font-size: 11px;">
        <thead>
            <tr>
                <th colspan="2" class="section-title" style="font-size: 11px;">Earnings / Additions</th>
                <th colspan="2" class="section-title" style="font-size: 11px;">Deductions</th>
            </tr>
            <tr>
                <th style="font-size: 11px;">Description</th>
                <th class="text-right" style="font-size: 11px;">Amount (KSh)</th>
                <th style="font-size: 11px;">Description</th>
                <th class="text-right" style="font-size: 11px;">Amount (KSh)</th>
            </tr>
        </thead>
        <tbody>
            @php
            $additions = collect([['name' => 'Salary', 'amount' => $payroll->basic_salary]]);
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
                <td style="font-size: 11px;">{{ $additions[$i]['name'] }}</td>
                <td class="text-right" style="font-size: 11px;">{{ number_format($additions[$i]['amount'], 2) }}</td>
                @else
                <td>&nbsp;</td>
                <td class="text-right">&nbsp;</td>
                @endif

                @if($deductions->has($i))
                <td style="font-size: 11px;">{{ $deductions[$i]['name'] }}</td>
                <td class="text-right" style="font-size: 11px;">{{ number_format($deductions[$i]['amount'], 2) }}</td>
                @else
                <td>&nbsp;</td>
                <td class="text-right">&nbsp;</td>
                @endif
                </tr>
                @endfor

                <tr class="total-row">
                    <td style="font-size: 11px;">GROSS PAY</td>
                    <td class="text-right" style="font-size: 11px;">{{ number_format($payroll->basic_salary + $payroll->salary_arrears + $payroll->acting_allowance + $elements->where('element_type', '2')->sum('amount'), 2) }}</td>
                    <td style="font-size: 11px;">TOTAL DEDUCTIONS</td>
                    <td class="text-right" style="font-size: 11px;">{{ number_format($payroll->total_deductions, 2) }}</td>
                </tr>
        </tbody>
    </table>

    <table class="payslip-table" style="font-size: 11px;">
        <tbody>
            <tr class="net-pay-row">
                <td style="font-size: 11px;"><strong>NET PAY</strong></td>
                <td class="text-right" style="font-size: 11px;"><strong>{{ number_format($payroll->net_pay, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 30px; font-size: 10px; color: #666; text-align: center;">
        <p>This is a computer-generated payslip and does not require a signature.</p>
    </div>
</div>