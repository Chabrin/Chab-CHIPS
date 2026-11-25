<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $exportInfo['title'] }}</title>
    <style>
        @include('property.processed-returns.partials.pdf-styles')

        /* Fixed column widths leave room for editable remarks in the exported statement. */
        .txn td {
            padding: 4px 4px;
            font-size: 6.5pt;
        }

        .txn thead th {
            padding: 5px 4px;
            font-size: 6pt;
            letter-spacing: 0.2px;
        }
    </style>
</head>

<body>
    @php
        $money = fn($v) => number_format((float) $v, 2);
        $amt = fn($v) => abs(round((float) ($v ?? 0), 2)) > 0 ? number_format((float) $v, 2) : '';
        $landlordLabel = $landlordDisplay ?? ($return->landlord_code ?? '-');
        $landlordTitle = trim(preg_replace('/\(' . preg_quote((string) $return->landlord_code, '/') . '\)$/', '', $landlordLabel)) ?: $landlordLabel;
        $signoffColumns = ['Prepared by' => 'prepared', 'Checked by' => 'checked', 'Approved by' => 'approved', 'Paid by' => null, 'Client name' => null];
    @endphp

    @include('pdf.partials.letterhead', [
        'title' => ($part ?? 'all') === 'rent' ? 'RENT REPORT' : (str_starts_with((string) $return->return_code, 'RU') ? 'UTILITY RETURN STATEMENT' : 'RETURN STATEMENT'),
        'period' => $exportInfo['title'],
        'metaLines' => ['Return ID: ' . $return->return_code, 'Printed: ' . date('d M Y, H:i')],
    ])

    <table class="cards">
        <tr>
            <td class="col-landlord" style="width:100%;padding-right:0;">
                <div class="card">
                    <table style="width:100%;"><tr><td style="width:42%;vertical-align:top;">
                    <div class="card-label">LANDLORD</div>
                    <div class="landlord-name">{{ $landlordTitle }}</div>
                        <span class="ref">Account: {{ $return->landlord_code ?? '-' }}</span>
                    </td><td style="width:58%;vertical-align:top;">
                    <table class="kv">

                        <tr><td class="k">Property</td><td>{{ $propertyDisplay ?? ($return->property_code ?? '-') }}</td></tr>
                        <tr><td class="k">Return ID</td><td><strong>{{ $return->return_code }}</strong></td></tr>
                        <tr><td class="k">Type</td><td>{{ $exportInfo['type'] }}</td></tr>
                        @if($return->voucher_number)
                        <tr><td class="k">Voucher</td><td>{{ $return->voucher_number }}</td></tr>
                        @endif
                    </table>
                    </td></tr></table>
                </div>
            </td>

        </tr>
    </table>

    @php
        // The tenant columns this report shows (a rent report or a utility return leaves some out); widths are shares of the table
        $columns = $columns ?? \App\Services\ReturnStatementService::COLUMNS;
        $widths = ['unit_code' => 5, 'tenant_name' => 14, 'payable' => 6.5, 'rent_arrears' => 8, 'vat' => 5.5, 'house_deposit_amt' => 6.5,
            'elec_deposit_amt' => 6, 'water_deposit_amt' => 6, 'elec_bill' => 6.5, 'water_bill' => 6.5, 'service_charge' => 6.5, 'monthly_rent' => 7.5, 'remarks' => 15];
        $total = array_sum(array_intersect_key($widths, $columns)) ?: 100;
        $moneyColumns = ['payable', 'monthly_rent'];
        $totalKey = ['house_deposit_amt' => 'house_deposit', 'elec_deposit_amt' => 'elec_deposit', 'water_deposit_amt' => 'water_deposit'];
    @endphp
    <table class="txn">
        <thead>
            <tr>
                @foreach($columns as $key => $label)
                <th class="{{ in_array($key, ['unit_code', 'tenant_name', 'remarks']) ? '' : 'r' }}" style="width: {{ round($widths[$key] * 100 / $total, 1) }}%;">{{ strtoupper($label) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($tenantRows as $row)
            <tr class="{{ $loop->even ? 'alt' : '' }}">
                @foreach($columns as $key => $label)
                    @if($key === 'unit_code')
                    <td>{{ $row['unit_code'] ?: '-' }}</td>
                    @elseif($key === 'tenant_name')
                    <td>{{ $row['tenant_name'] }}</td>
                    @elseif($key === 'remarks')
                    <td class="remarks">{{ $row['remarks'] ?? '' }}</td>
                    @else
                    <td class="r">{{ in_array($key, $moneyColumns) ? $money($row[$key] ?? 0) : $amt($row[$key] ?? 0) }}</td>
                    @endif
                @endforeach
            </tr>
            @empty
            <tr>
                <td colspan="{{ count($columns) }}" class="empty">No tenant lines found for this return.</td>
            </tr>
            @endforelse

            @if(count($tenantRows) > 0)
            <tr class="totals">
                @foreach($columns as $key => $label)
                    @if($key === 'tenant_name')
                    <td>TOTALS</td>
                    @elseif(in_array($key, ['unit_code', 'remarks']))
                    <td></td>
                    @else
                    @php($sum = $totals[$totalKey[$key] ?? $key] ?? 0)
                    <td class="r">{{ in_array($key, $moneyColumns) ? $money($sum) : $amt($sum) }}</td>
                    @endif
                @endforeach
            </tr>
            @endif
        </tbody>
    </table>

    <div class="statement-closing">
    <table class="cards statement-summary"><tr><td style="width:48%;"></td>
            <td class="col-summary">
                <div class="card">
                    <div class="card-label">FINANCIAL SUMMARY</div>
                    <table class="summary">
                        @foreach($summaryRows as [$summaryKey, $summaryLabel, $summaryKind])
                        <tr class="{{ ['less' => 'sub', 'strong' => 'strong', 'closing' => 'closing'][$summaryKind] ?? '' }}"><td class="k">{{ $summaryLabel }}</td><td class="v {{ ['less' => 'dr', 'add' => 'cr'][$summaryKind] ?? '' }}">@if($summaryKind === 'less')( {{ $money($summary[$summaryKey]) }} )@else{{ $money($summary[$summaryKey]) }}@endif</td></tr>
                        @endforeach
                    </table>
                </div>
            </td>
    </tr></table>
    </div>

    <table class="txn" style="margin-top:14px;">
        <thead>
            <tr>
                <th style="width:46%;">DEDUCTIONS, OPENING BALANCE AND AMOUNT PAYABLE</th>
                <th style="width:24%;">REFERENCE</th>
                <th style="width:12%;">DATE</th>
                <th class="r" style="width:18%;">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            @foreach($settlementRows as $row)
            <tr class="{{ in_array($row['kind'], ['closing', 'strong']) ? 'totals' : '' }}">
                <td>{{ $row['label'] }}</td>
                <td>{{ $row['ref'] }}</td>
                <td>{{ $row['date'] }}</td>
                <td class="r">{{ $money($row['amount']) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if(count($arrears))
    <table class="txn" style="margin-top:14px;">
        <thead>
            <tr>
                <th style="width:14%;">ARREARS DETAILS: DATE</th>
                <th style="width:56%;">DETAILS</th>
                <th style="width:12%;">ROOM</th>
                <th class="r" style="width:18%;">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            @foreach($arrears as $row)
            <tr class="{{ $loop->even ? 'alt' : '' }}">
                <td>{{ $row['date'] }}</td>
                <td>{{ $row['details'] }}</td>
                <td>{{ $row['room'] }}</td>
                <td class="r">{{ $money($row['amount']) }}</td>
            </tr>
            @endforeach
            <tr class="totals">
                <td colspan="3">TOTAL ARREARS</td>
                <td class="r">{{ $money(array_sum(array_column($arrears, 'amount'))) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    <div class="statement-closing">
    @include('property.processed-returns.partials.pdf-signoff')
    </div>
</body>

</html>
