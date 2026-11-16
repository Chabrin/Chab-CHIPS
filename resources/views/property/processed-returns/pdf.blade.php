<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Return {{ $return->return_code }}</title>
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
        $period = date('F Y', mktime(0, 0, 0, (int) $return->month, 1, (int) $return->year));
        $landlordLabel = $landlordDisplay ?? ($return->landlord_code ?? '-');
        $landlordTitle = trim(preg_replace('/\(' . preg_quote((string) $return->landlord_code, '/') . '\)$/', '', $landlordLabel)) ?: $landlordLabel;
        $signoffColumns = ['Prepared by' => 'prepared', 'Checked by' => 'checked', 'Approved by' => 'approved', 'Paid by' => null, 'Client name' => null];
    @endphp

    @include('pdf.partials.letterhead', [
        'title' => 'RETURN STATEMENT',
        'period' => $period,
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
                        <tr><td class="k">Type</td><td>{{ $return->return_type ?? '-' }}</td></tr>
                        @if($return->voucher_number)
                        <tr><td class="k">Voucher</td><td>{{ $return->voucher_number }}</td></tr>
                        @endif
                    </table>
                    </td></tr></table>
                </div>
            </td>

        </tr>
    </table>

    <table class="txn">
        <thead>
            <tr>
                <th class="" style="width: 5%;">ROOM</th>
                <th class="" style="width: 14%;">TENANT</th>
                <th class="r" style="width: 6.5%;">PAYABLE</th>
                <th class="r" style="width: 8%;">ARREARS / PREPAYMENTS</th>
                <th class="r" style="width: 5.5%;">VAT</th>
                <th class="r" style="width: 6.5%;">HOUSE DEP.</th>
                <th class="r" style="width: 6%;">ELEC DEP.</th>
                <th class="r" style="width: 6%;">WATER DEP.</th>
                <th class="r" style="width: 6.5%;">ELEC BILL</th>
                <th class="r" style="width: 6.5%;">WATER BILL</th>
                <th class="r" style="width: 6.5%;">SVC CHARGE</th>
                <th class="r" style="width: 7.5%;">MONTHLY RENT</th>
                <th class="" style="width: 15%;">REMARKS</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tenantRows as $row)
            <tr class="{{ $loop->even ? 'alt' : '' }}">
                <td>{{ $row['unit_code'] ?: '-' }}</td>
                <td>{{ $row['tenant_name'] }}</td>
                <td class="r">{{ $money($row['payable'] ?? 0) }}</td>
                <td class="r">{{ $amt($row['rent_arrears']) }}</td>
                <td class="r">{{ $amt($row['vat']) }}</td>
                <td class="r">{{ $amt($row['house_deposit_amt']) }}</td>
                <td class="r">{{ $amt($row['elec_deposit_amt']) }}</td>
                <td class="r">{{ $amt($row['water_deposit_amt']) }}</td>
                <td class="r">{{ $amt($row['elec_bill']) }}</td>
                <td class="r">{{ $amt($row['water_bill']) }}</td>
                <td class="r">{{ $amt($row['service_charge']) }}</td>
                <td class="r">{{ $money($row['monthly_rent'] ?? 0) }}</td>
                <td class="remarks">{{ $row['remarks'] ?? '' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="13" class="empty">No tenant lines found for this return.</td>
            </tr>
            @endforelse

            @if(count($tenantRows) > 0)
            <tr class="totals">
                <td></td>
                <td>TOTALS</td>
                <td class="r">{{ $money($totals['payable']) }}</td>
                <td class="r">{{ $amt($totals['rent_arrears']) }}</td>
                <td class="r">{{ $amt($totals['vat']) }}</td>
                <td class="r">{{ $amt($totals['house_deposit']) }}</td>
                <td class="r">{{ $amt($totals['elec_deposit']) }}</td>
                <td class="r">{{ $amt($totals['water_deposit']) }}</td>
                <td class="r">{{ $amt($totals['elec_bill']) }}</td>
                <td class="r">{{ $amt($totals['water_bill']) }}</td>
                <td class="r">{{ $amt($totals['service_charge']) }}</td>
                <td class="r">{{ $money($totals['monthly_rent']) }}</td>
                <td></td>
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
