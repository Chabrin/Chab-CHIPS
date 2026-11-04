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
                <td class="r">{{ $money($row['monthly_rent'] ?? 0) }}</td>
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
                <td class="r">{{ $money($totals['monthly_rent']) }}</td>
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
                        <tr><td class="k">Total rent</td><td class="v">{{ $money($summary['total_rent']) }}</td></tr>
                        <tr><td class="k">Commissionable amount</td><td class="v">{{ $money($summary['comm_total']) }}</td></tr>
                        <tr class="sub"><td class="k">Less commission ({{ number_format($return->commission_rate ?? 0, 2) }}%)</td><td class="v dr">( {{ $money($summary['commission']) }} )</td></tr>
                        <tr class="strong"><td class="k">Balance after commission</td><td class="v">{{ $money($summary['bal_after_comm']) }}</td></tr>
                        <tr class="sub"><td class="k">Less VAT on commission</td><td class="v dr">( {{ $money($summary['vat']) }} )</td></tr>
                        <tr class="strong"><td class="k">Balance less VAT &amp; commission</td><td class="v">{{ $money($summary['bal_less_vat']) }}</td></tr>
                        <tr><td class="k">Add house deposits</td><td class="v cr">{{ $money($summary['house_deposits']) }}</td></tr>
                        <tr><td class="k">Add water &amp; elec bills</td><td class="v cr">{{ $money($summary['water_elec_bills']) }}</td></tr>
                        <tr class="closing"><td class="k">Net income</td><td class="v">{{ $money($summary['net_income']) }}</td></tr>
                    </table>
                </div>
            </td>
    </tr></table>

    @include('property.processed-returns.partials.pdf-signoff')
    </div>
</body>

</html>
