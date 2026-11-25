<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $exportInfo['title'] }}</title>
    <style>
        @include('property.processed-returns.partials.pdf-styles')

        .txn td {
            padding: 4px 4px;
            font-size: 7.5pt;
        }

        .txn thead th {
            padding: 5px 4px;
            font-size: 6.5pt;
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
        'title' => 'UTILITY REPORT',
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
                    </table>
                    </td></tr></table>
                </div>
            </td>
        </tr>
    </table>

    <table class="txn">
        <thead>
            <tr>
                <th style="width: 8%;">ROOM</th>
                <th style="width: 30%;">TENANT</th>
                <th class="r" style="width: 14%;">ELEC BILL</th>
                <th class="r" style="width: 14%;">WATER BILL</th>
                <th class="r" style="width: 14%;">SVC CHARGE</th>
                <th style="width: 20%;">REMARKS</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tenantRows as $row)
            <tr class="{{ $loop->even ? 'alt' : '' }}">
                <td>{{ $row['unit_code'] ?: '-' }}</td>
                <td>{{ $row['tenant_name'] }}</td>
                <td class="r">{{ $amt($row['elec_bill']) }}</td>
                <td class="r">{{ $amt($row['water_bill']) }}</td>
                <td class="r">{{ $amt($row['service_charge']) }}</td>
                <td class="remarks">{{ $row['remarks'] ?? '' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="empty">No utility lines found for this return.</td>
            </tr>
            @endforelse

            @if(count($tenantRows) > 0)
            <tr class="totals">
                <td></td>
                <td>TOTALS</td>
                <td class="r">{{ $money($totals['elec_bill']) }}</td>
                <td class="r">{{ $money($totals['water_bill']) }}</td>
                <td class="r">{{ $money($totals['service_charge']) }}</td>
                <td></td>
            </tr>
            @endif
        </tbody>
    </table>

    <div class="statement-closing">
    <table class="cards statement-summary"><tr><td style="width:48%;"></td>
            <td class="col-summary">
                <div class="card">
                    <div class="card-label">UTILITY SUMMARY</div>
                    <table class="summary">
                        @foreach($summaryRows as [$summaryKey, $summaryLabel, $summaryKind])
                        <tr class="{{ $summaryKind === 'closing' ? 'closing' : '' }}"><td class="k">{{ $summaryLabel }}</td><td class="v">{{ $money($summary[$summaryKey]) }}</td></tr>
                        @endforeach
                    </table>
                </div>
            </td>
    </tr></table>
    <p style="font-size:7pt;margin-top:6px;">Commission, VAT, deductions and the amount payable are on the rent report. The two reports add up to the return.</p>
    @include('property.processed-returns.partials.pdf-signoff')
    </div>
</body>

</html>
