<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Return {{ $return->return_code }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 8.5pt;
            margin: 0;
            padding: 10px 15px 95px 15px;
            color: #111;
        }

        .main-table tbody td {
            color: #111;
        }

        /* ── PAGE HEADER ─────────────────────────────── */
        .page-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }

        .page-header td {
            vertical-align: middle;
            padding: 0 4px;
        }

        .page-header .col-logo {
            width: 12%;
            text-align: left;
        }

        .page-header .col-company {
            width: 65%;
            text-align: center;
        }

        .page-header .col-meta {
            width: 23%;
            text-align: right;
            vertical-align: top;
            font-size: 7.5pt;
            color: #000;
            line-height: 1.6;
        }

        .company-name {
            font-size: 14pt;
            font-weight: bold;
            color: #000;
            margin: 0;
        }

        .company-tagline {
            font-size: 10pt;
            font-weight: bold;
            color: #000;
            margin: 2px 0 0 0;
        }

        .company-address {
            font-size: 7pt;
            color: #000;
            margin: 2px 0 0 0;
        }

        /* ── RETURN INFO STRIP ───────────────────────── */
        .return-info {
            background: #fff;
            border: 1px solid #000;
            border-radius: 0;
            padding: 4px 8px;
            margin-bottom: 6px;
            font-size: 7.5pt;
        }

        .return-info table {
            width: 100%;
            border-collapse: collapse;
        }

        .return-info td {
            padding: 1px 4px;
        }

        .return-info .lbl {
            color: #000;
            font-weight: bold;
        }

        /* ── MAIN TABLE ──────────────────────────────── */
        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 7pt;
        }

        .main-table thead tr:first-child th {
            background: #e0e0e0;
            color: #000;
            font-weight: bold;
            text-align: center;
            padding: 3px 2px;
            border: 1px solid #999;
            font-size: 7pt;
        }

        .main-table thead tr:last-child th {
            background: #ececec;
            color: #000;
            font-weight: bold;
            text-align: center;
            padding: 2px 2px;
            border: 1px solid #999;
            font-size: 6.5pt;
        }

        .main-table tbody td {
            border: 1px solid #999;
            padding: 2px 3px;
            vertical-align: top;
        }

        .main-table tbody tr:nth-child(even) td {
            background: #fff;
        }

        .main-table tbody tr.total-row td {
            background: #e0e0e0;
            font-weight: bold;
            border-top: 2px solid #999;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .text-muted-sm {
            color: #000;
            font-size: 6.5pt;
        }

        /* ── FOOTER LAYOUT ───────────────────────────── */
        .footer-wrap {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-wrap .col-blank {
            width: 55%;
            vertical-align: top;
        }

        .footer-wrap .col-summary {
            width: 45%;
            vertical-align: top;
            padding-left: 10px;
        }

        /* ── SUMMARY TABLE ───────────────────────────── */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
        }

        .summary-table th {
            background: #e0e0e0;
            color: #000;
            padding: 3px 6px;
            text-align: center;
            font-size: 7.5pt;
            border-bottom: 1px solid #999;
        }

        .summary-table td {
            padding: 2px 6px;
            border-bottom: 1px solid #ccc;
        }

        .summary-table tr.indent td:first-child {
            padding-left: 14px;
            color: #000;
        }

        .summary-table tr.total td {
            font-weight: bold;
            background: #e0e0e0;
            border-top: 1.5px solid #999;
        }

        .summary-table tr.net-income td {
            font-weight: bold;
            background: #d0d0d0;
            color: #000;
            font-size: 8.5pt;
            border-top: 2px solid #999;
        }

        .amount-col {
            text-align: right;
            white-space: nowrap;
        }

        .fixed-footer {
            position: fixed;
            left: 15px;
            right: 15px;
            bottom: 10px;
            font-size: 7pt;
            color: #000;
        }

        .signoff-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .signoff-table td {
            width: 20%;
            vertical-align: top;
            padding-right: 8px;
        }

        .signoff-title {
            font-weight: bold;
            margin-bottom: 10px;
        }

        .signoff-meta {
            margin-top: 2px;
            line-height: 1.2;
        }

        .signoff-label {
            display: inline-block;
            width: 30px;
        }

        .signoff-value {
            display: inline-block;
            width: calc(100% - 36px);
            border-bottom: 1px solid #000;
            min-height: 10px;
            vertical-align: bottom;
            padding-left: 2px;
        }

        .page-num {
            text-align: right;
            margin-top: 5px;
            font-size: 7pt;
        }
    </style>
</head>

<body>

    {{-- ══════ PAGE HEADER ══════ --}}
    <table class="page-header">
        <tr>
            <td class="col-logo"></td>
            <td class="col-company">
                <p class="company-name">CHABRIN AGENCIES LIMITED</p>
                <p class="company-tagline">PROPERTY MANAGING AGENTS</p>
                <p class="company-address">5th Floor, Room 517, NACICO Plaza Landhies Road, PO Box 16659-00620 Nairobi, Kenya</p>
            </td>
            <td class="col-meta">
                <strong>Print Date:</strong> {{ date('d/m/Y H:i') }}<br>
                <strong>Return ID:</strong> {{ $return->return_code }}<br>
                <strong>Period:</strong> {{ date('F Y', mktime(0, 0, 0, (int) $return->month, 1, (int) $return->year)) }}
            </td>
        </tr>
    </table>

    {{-- ══════ RETURN INFO STRIP ══════ --}}
    <div class="return-info">
        <table>
            <tr>
                <td class="lbl">Property:</td>
                <td>{{ $propertyDisplay ?? ($return->property_code ?? '-') }}</td>
                <td class="lbl">Landlord:</td>
                <td>{{ $landlordDisplay ?? ($return->landlord_code ?? '-') }}</td>
                <td class="lbl">Type:</td>
                <td>{{ $return->return_type ?? '-' }}</td>
                @if($return->voucher_number)
                <td class="lbl">Voucher:</td>
                <td>{{ $return->voucher_number }}</td>
                @endif
            </tr>
        </table>
    </div>

    {{-- ══════ MAIN DETAIL TABLE ══════ --}}
    <table class="main-table">
        <thead>
            {{-- Row 1: group headers --}}
            <tr>
                <th style="width:5%;">Room No</th>
                <th style="width:16%; text-align:left;">Tenant Name</th>
                <th style="width:8%;">Payable</th>
                <th style="width:8%;">Rent Arrears</th>
                <th style="width:6%;">VAT</th>
                <th style="width:8%;">House Deposit</th>
                <th style="width:8%;">Elec Deposit</th>
                <th style="width:8%;">Water Deposit</th>
                <th style="width:8%;">Elec Bill</th>
                <th style="width:8%;">Water Bill</th>
                <th style="width:8%;">Svc Charge</th>
                <th style="width:9%;">Monthly Rent</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tenantRows as $row)
            <tr>
                <td class="text-center">{{ $row['unit_code'] ?: '-' }}</td>
                <td>{{ $row['tenant_name'] }}</td>
                <td class="text-right">{{ $row['monthly_rent'] > 0 ? number_format($row['monthly_rent'], 2) : '' }}</td>
                <td class="text-right">{{ $row['rent_arrears'] > 0 ? number_format($row['rent_arrears'], 2) : '' }}</td>
                <td class="text-right">{{ $row['vat'] > 0 ? number_format($row['vat'], 2) : '' }}</td>
                <td class="text-right">{{ $row['house_deposit_amt'] > 0 ? number_format($row['house_deposit_amt'], 2) : '' }}</td>
                <td class="text-right">{{ $row['elec_deposit_amt'] > 0 ? number_format($row['elec_deposit_amt'], 2) : '' }}</td>
                <td class="text-right">{{ $row['water_deposit_amt'] > 0 ? number_format($row['water_deposit_amt'], 2) : '' }}</td>
                <td class="text-right">{{ $row['elec_bill'] > 0 ? number_format($row['elec_bill'], 2) : '' }}</td>
                <td class="text-right">{{ $row['water_bill'] > 0 ? number_format($row['water_bill'], 2) : '' }}</td>
                <td class="text-right">{{ $row['service_charge'] > 0 ? number_format($row['service_charge'], 2) : '' }}</td>
                <td class="text-right">{{ $row['monthly_rent'] > 0 ? number_format($row['monthly_rent'], 2) : '' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="12" class="text-center" style="padding:8px;">No tenant lines found for this return.</td>
            </tr>
            @endforelse

            {{-- TOTALS ROW --}}
            @if(count($tenantRows) > 0)
            <tr class="total-row">
                <td class="text-center">—</td>
                <td><strong>TOTALS</strong></td>
                <td class="text-right">{{ number_format($totals['monthly_rent'], 2) }}</td>
                <td class="text-right">{{ $totals['rent_arrears'] > 0 ? number_format($totals['rent_arrears'], 2) : '' }}</td>
                <td class="text-right">{{ $totals['vat'] > 0 ? number_format($totals['vat'], 2) : '' }}</td>
                <td class="text-right">{{ $totals['house_deposit'] > 0 ? number_format($totals['house_deposit'], 2) : '' }}</td>
                <td class="text-right">{{ $totals['elec_deposit'] > 0 ? number_format($totals['elec_deposit'], 2) : '' }}</td>
                <td class="text-right">{{ $totals['water_deposit'] > 0 ? number_format($totals['water_deposit'], 2) : '' }}</td>
                <td class="text-right">{{ $totals['elec_bill'] > 0 ? number_format($totals['elec_bill'], 2) : '' }}</td>
                <td class="text-right">{{ $totals['water_bill'] > 0 ? number_format($totals['water_bill'], 2) : '' }}</td>
                <td class="text-right">{{ $totals['service_charge'] > 0 ? number_format($totals['service_charge'], 2) : '' }}</td>
                <td class="text-right">{{ number_format($totals['monthly_rent'], 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    {{-- ══════ FOOTER: LEFT blank | RIGHT financial summary ══════ --}}
    <table class="footer-wrap">
        <tr>
            <td class="col-blank"></td>
            <td class="col-summary">
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th colspan="2">FINANCIAL SUMMARY</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Total Rent</td>
                            <td class="amount-col">{{ number_format($summary['total_rent'], 2) }}</td>
                        </tr>
                        <tr>
                            <td>Commissionable Amount</td>
                            <td class="amount-col">{{ number_format($summary['comm_total'], 2) }}</td>
                        </tr>
                        <tr class="indent">
                            <td>Less Commission ({{ number_format($return->commission_rate ?? 0, 2) }}%)</td>
                            <td class="amount-col">( {{ number_format($summary['commission'], 2) }} )</td>
                        </tr>
                        <tr class="total">
                            <td>Balance After Commission</td>
                            <td class="amount-col">{{ number_format($summary['bal_after_comm'], 2) }}</td>
                        </tr>
                        <tr class="indent">
                            <td>Less VAT on Commission</td>
                            <td class="amount-col">( {{ number_format($summary['vat'], 2) }} )</td>
                        </tr>
                        <tr class="total">
                            <td>Balance Less VAT &amp; Commission</td>
                            <td class="amount-col">{{ number_format($summary['bal_less_vat'], 2) }}</td>
                        </tr>
                        <tr>
                            <td>Add House Deposits</td>
                            <td class="amount-col">{{ number_format($summary['house_deposits'], 2) }}</td>
                        </tr>
                        <tr>
                            <td>Add Water &amp; Elec Bills</td>
                            <td class="amount-col">{{ number_format($summary['water_elec_bills'], 2) }}</td>
                        </tr>
                        <tr class="net-income">
                            <td>NET INCOME</td>
                            <td class="amount-col">{{ number_format($summary['net_income'], 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <div class="fixed-footer">
        <table class="signoff-table">
            <tr>
                <td>
                    <div class="signoff-title">Prepared By</div>
                    <div class="signoff-meta"><span class="signoff-label">Name:</span><span class="signoff-value">{{ (($signoff['prepared']['name'] ?? '') !== '-' && !empty($signoff['prepared']['name'])) ? $signoff['prepared']['name'] : '' }}</span></div>
                    <div class="signoff-meta"><span class="signoff-label">Date:</span><span class="signoff-value">{{ (($signoff['prepared']['date'] ?? '') !== '-' && !empty($signoff['prepared']['date'])) ? $signoff['prepared']['date'] : '' }}</span></div>
                    <div class="signoff-meta"><span class="signoff-label">Sign:</span><span class="signoff-value"></span></div>
                </td>
                <td>
                    <div class="signoff-title">Checked By</div>
                    <div class="signoff-meta"><span class="signoff-label">Name:</span><span class="signoff-value">{{ (($signoff['checked']['name'] ?? '') !== '-' && !empty($signoff['checked']['name'])) ? $signoff['checked']['name'] : '' }}</span></div>
                    <div class="signoff-meta"><span class="signoff-label">Date:</span><span class="signoff-value">{{ (($signoff['checked']['date'] ?? '') !== '-' && !empty($signoff['checked']['date'])) ? $signoff['checked']['date'] : '' }}</span></div>
                    <div class="signoff-meta"><span class="signoff-label">Sign:</span><span class="signoff-value"></span></div>
                </td>
                <td>
                    <div class="signoff-title">Approved By</div>
                    <div class="signoff-meta"><span class="signoff-label">Name:</span><span class="signoff-value">{{ (($signoff['approved']['name'] ?? '') !== '-' && !empty($signoff['approved']['name'])) ? $signoff['approved']['name'] : '' }}</span></div>
                    <div class="signoff-meta"><span class="signoff-label">Date:</span><span class="signoff-value">{{ (($signoff['approved']['date'] ?? '') !== '-' && !empty($signoff['approved']['date'])) ? $signoff['approved']['date'] : '' }}</span></div>
                    <div class="signoff-meta"><span class="signoff-label">Sign:</span><span class="signoff-value"></span></div>
                </td>
                <td>
                    <div class="signoff-title">Paid By</div>
                    <div class="signoff-meta"><span class="signoff-label">Name:</span><span class="signoff-value"></span></div>
                    <div class="signoff-meta"><span class="signoff-label">Date:</span><span class="signoff-value"></span></div>
                    <div class="signoff-meta"><span class="signoff-label">Sign:</span><span class="signoff-value"></span></div>
                </td>
                <td>
                    <div class="signoff-title">Client Name</div>
                    <div class="signoff-meta"><span class="signoff-label">Name:</span><span class="signoff-value"></span></div>
                    <div class="signoff-meta"><span class="signoff-label">Date:</span><span class="signoff-value"></span></div>
                    <div class="signoff-meta"><span class="signoff-label">Sign:</span><span class="signoff-value"></span></div>
                </td>
            </tr>
        </table>
        <div class="page-num"></div>
    </div>

</body>

</html>