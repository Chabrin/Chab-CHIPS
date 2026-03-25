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
            font-size: 7pt;
            margin: 0;
            padding: 10px 15px;
            color: #222;
        }

        /* ── PAGE HEADER ─────────────────────────────── */
        .page-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            border-bottom: 2px solid #2c5f8a;
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
            font-size: 6.5pt;
            color: #444;
            line-height: 1.6;
        }

        .company-name {
            font-size: 13pt;
            font-weight: bold;
            color: #2c5f8a;
            margin: 0;
        }

        .company-tagline {
            font-size: 9pt;
            font-weight: bold;
            color: #555;
            margin: 2px 0 0 0;
        }

        .company-address {
            font-size: 6pt;
            color: #777;
            margin: 2px 0 0 0;
        }

        /* ── RETURN INFO STRIP ───────────────────────── */
        .return-info {
            background: #f0f4fa;
            border: 1px solid #d0dcf0;
            border-radius: 3px;
            padding: 4px 8px;
            margin-bottom: 6px;
            font-size: 6.5pt;
        }

        .return-info table {
            width: 100%;
            border-collapse: collapse;
        }

        .return-info td {
            padding: 1px 4px;
        }

        .return-info .lbl {
            color: #777;
            font-weight: bold;
        }

        /* ── MAIN TABLE ──────────────────────────────── */
        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 6pt;
        }

        .main-table thead tr:first-child th {
            background: #2c5f8a;
            color: #fff;
            font-weight: bold;
            text-align: center;
            padding: 3px 2px;
            border: 1px solid #1a4a70;
            font-size: 6pt;
        }

        .main-table thead tr:last-child th {
            background: #3a7bbf;
            color: #fff;
            font-weight: bold;
            text-align: center;
            padding: 2px 2px;
            border: 1px solid #1a4a70;
            font-size: 5.5pt;
        }

        .main-table tbody td {
            border: 1px solid #ccc;
            padding: 2px 3px;
            vertical-align: top;
        }

        .main-table tbody tr:nth-child(even) td {
            background: #f5f8fc;
        }

        .main-table tbody tr.total-row td {
            background: #e8f0fb;
            font-weight: bold;
            border-top: 2px solid #2c5f8a;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .text-muted-sm {
            color: #888;
            font-size: 5.5pt;
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
            font-size: 6.5pt;
        }

        .summary-table th {
            background: #2c5f8a;
            color: #fff;
            padding: 3px 6px;
            text-align: center;
            font-size: 6.5pt;
        }

        .summary-table td {
            padding: 2px 6px;
            border-bottom: 1px solid #e0e8f4;
        }

        .summary-table tr.indent td:first-child {
            padding-left: 14px;
            color: #555;
        }

        .summary-table tr.total td {
            font-weight: bold;
            background: #e8f0fb;
            border-top: 1.5px solid #2c5f8a;
        }

        .summary-table tr.net-income td {
            font-weight: bold;
            background: #2c5f8a;
            color: #fff;
            font-size: 7.5pt;
            border-top: 2px solid #1a4a70;
        }

        .amount-col {
            text-align: right;
            white-space: nowrap;
        }
    </style>
</head>

<body>

    {{-- ══════ PAGE HEADER ══════ --}}
    <table class="page-header">
        <tr>
            <td class="col-logo">
                @if($logoSrc)
                <img src="{{ $logoSrc }}" style="max-height:45px; max-width:90px;">
                @endif
            </td>
            <td class="col-company">
                <p class="company-name">CHABRIN AGENCIES LIMITED</p>
                <p class="company-tagline">PROPERTY MANAGING AGENTS</p>
                <p class="company-address">5th Floor, Room 517, NACICO Plaza Landhies Road, PO Box 16659-00620 Nairobi, Kenya</p>
            </td>
            <td class="col-meta">
                <strong>Print Date:</strong> {{ date('d/m/Y H:i') }}<br>
                <strong>Return ID:</strong> {{ $return->return_code }}<br>
                <strong>Period:</strong> {{ str_pad($return->month, 2, '0', STR_PAD_LEFT) }}/{{ $return->year }}
            </td>
        </tr>
    </table>

    {{-- ══════ RETURN INFO STRIP ══════ --}}
    <div class="return-info">
        <table>
            <tr>
                <td class="lbl">Property:</td>
                <td>{{ $return->property_code ?? '-' }}</td>
                <td class="lbl">Landlord:</td>
                <td>{{ $return->landlord_code }}</td>
                <td class="lbl">Type:</td>
                <td>{{ $return->return_type ?? '-' }}</td>
                <td class="lbl">Commission:</td>
                <td>{{ number_format($return->commission_rate ?? 0, 2) }}%</td>
                <td class="lbl">Net Amount:</td>
                <td><strong>{{ number_format(($summary['bal_less_vat'] + $summary['house_deposits'] + $summary['water_elec_bills']), 2) }}</strong></td>
                <td class="lbl">Amount Paid:</td>
                <td><strong>{{ number_format($return->amount_paid ?? 0, 2) }}</strong></td>
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
                <th rowspan="2" style="width:2%;">#</th>
                <th rowspan="2" style="width:12%; text-align:left;">Tenant Name</th>
                <th rowspan="2" style="width:5%;">Payable</th>
                <th rowspan="2" style="width:5%;">Rent Arrears</th>
                <th rowspan="2" style="width:4%;">VAT</th>
                <th colspan="2" style="width:10%;">House Deposit</th>
                <th colspan="2" style="width:10%;">Elec Deposit</th>
                <th colspan="2" style="width:10%;">Water Deposit</th>
                <th rowspan="2" style="width:5%;">Elec Bill</th>
                <th rowspan="2" style="width:5%;">Water Bill</th>
                <th rowspan="2" style="width:5%;">Svc Charge</th>
                <th rowspan="2" style="width:5%;">Monthly Rent</th>
                <th rowspan="2" style="width:7%; text-align:left;">Remarks</th>
            </tr>
            {{-- Row 2: sub-headers --}}
            <tr>
                <th style="width:5%;">Amt</th>
                <th style="width:6%;">Ref</th>
                <th style="width:5%;">Amt</th>
                <th style="width:6%;">Ref</th>
                <th style="width:5%;">Amt</th>
                <th style="width:6%;">Ref</th>
            </tr>
        </thead>
        <tbody>
            @php $snr = 1; @endphp
            @forelse($tenantRows as $row)
            <tr>
                <td class="text-center">{{ $snr++ }}</td>
                <td>{{ $row['tenant_name'] }} <span class="text-muted-sm">({{ $row['unit_code'] }})</span></td>
                <td class="text-right">{{ $row['monthly_rent'] > 0 ? number_format($row['monthly_rent'], 2) : '' }}</td>
                <td class="text-right">{{ $row['rent_arrears'] > 0 ? number_format($row['rent_arrears'], 2) : '' }}</td>
                <td class="text-right">{{ $row['vat'] > 0 ? number_format($row['vat'], 2) : '' }}</td>
                <td class="text-right">{{ $row['house_deposit_amt'] > 0 ? number_format($row['house_deposit_amt'], 2) : '' }}</td>
                <td class="text-muted-sm">{{ $row['house_deposit_ref'] }}</td>
                <td class="text-right">{{ $row['elec_deposit_amt'] > 0 ? number_format($row['elec_deposit_amt'], 2) : '' }}</td>
                <td class="text-muted-sm">{{ $row['elec_deposit_ref'] }}</td>
                <td class="text-right">{{ $row['water_deposit_amt'] > 0 ? number_format($row['water_deposit_amt'], 2) : '' }}</td>
                <td class="text-muted-sm">{{ $row['water_deposit_ref'] }}</td>
                <td class="text-right">{{ $row['elec_bill'] > 0 ? number_format($row['elec_bill'], 2) : '' }}</td>
                <td class="text-right">{{ $row['water_bill'] > 0 ? number_format($row['water_bill'], 2) : '' }}</td>
                <td class="text-right">{{ $row['service_charge'] > 0 ? number_format($row['service_charge'], 2) : '' }}</td>
                <td class="text-right">{{ $row['monthly_rent'] > 0 ? number_format($row['monthly_rent'], 2) : '' }}</td>
                <td>{{ trim($row['remarks']) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="16" class="text-center" style="padding:8px; color:#999;">No tenant lines found for this return.</td>
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
                <td></td>
                <td class="text-right">{{ $totals['elec_deposit'] > 0 ? number_format($totals['elec_deposit'], 2) : '' }}</td>
                <td></td>
                <td class="text-right">{{ $totals['water_deposit'] > 0 ? number_format($totals['water_deposit'], 2) : '' }}</td>
                <td></td>
                <td class="text-right">{{ $totals['elec_bill'] > 0 ? number_format($totals['elec_bill'], 2) : '' }}</td>
                <td class="text-right">{{ $totals['water_bill'] > 0 ? number_format($totals['water_bill'], 2) : '' }}</td>
                <td class="text-right">{{ $totals['service_charge'] > 0 ? number_format($totals['service_charge'], 2) : '' }}</td>
                <td class="text-right">{{ number_format($totals['monthly_rent'], 2) }}</td>
                <td></td>
            </tr>
            @endif
        </tbody>
    </table>

    {{-- ══════ FOOTER: LEFT blank | RIGHT financial summary ══════ --}}
    <table class="footer-wrap">
        <tr>
            <td class="col-blank">
                @if($return->remarks || $return->remarks_2)
                <div style="font-size:6.5pt; color:#555; background:#fff8e1; border-left:3px solid #f5a623; padding:4px 8px; border-radius:2px;">
                    <strong>Remarks:</strong> {{ trim(($return->remarks ?? '') . ' ' . ($return->remarks_2 ?? '')) }}
                </div>
                @endif
            </td>
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

</body>

</html>