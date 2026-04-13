<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Master Return {{ $return->return_code }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 8.4pt;
            margin: 0;
            padding: 8px 10px 92px 10px;
            color: #111;
        }

        .header-table,
        .meta-table,
        .data-table,
        .totals-table,
        .signoff-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table {
            margin-bottom: 5px;
            border-bottom: 2px solid #000;
        }

        .header-table td {
            vertical-align: top;
            padding: 1px 3px;
        }

        .logo-col {
            width: 18%;
        }

        .title-col {
            width: 52%;
            text-align: center;
        }

        .side-col {
            width: 30%;
            font-size: 7.4pt;
        }

        .company-name {
            font-size: 13pt;
            font-weight: bold;
            margin: 0;
        }

        .company-tag {
            font-size: 8.9pt;
            font-weight: bold;
            margin: 1px 0;
        }

        .company-address {
            font-size: 7pt;
            margin: 0;
        }

        .report-title {
            margin: 6px 0 0 0;
            font-size: 8.9pt;
            font-weight: bold;
        }

        .meta-table {
            margin-bottom: 4px;
            font-size: 7.4pt;
        }

        .meta-table td {
            padding: 1px 2px;
        }

        .meta-label {
            font-weight: bold;
            width: 22%;
        }

        .section-title {
            font-weight: bold;
            padding: 3px 0;
            margin-top: 6px;
            margin-bottom: 4px;
        }

        .data-table {
            margin-bottom: 7px;
            font-size: 7.5pt;
            table-layout: fixed;
        }

        .data-table th,
        .data-table td {
            border: none;
            padding: 2px 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: clip;
            line-height: 1.22;
        }

        .data-table th {
            text-align: left;
            font-weight: bold;
            padding-bottom: 3px;
        }

        .amount {
            text-align: right;
            white-space: nowrap;
        }

        .subtotal-row td {
            font-weight: bold;
            padding-top: 5px;
        }

        .less-section {
            margin-top: 12px;
        }

        .totals-table {
            margin-top: 6px;
            width: 48%;
            margin-left: auto;
            font-size: 7.7pt;
        }

        .totals-table td {
            border: none;
            padding: 3px 4px;
        }

        .totals-table .label {
            font-weight: bold;
        }

        .fixed-footer {
            position: fixed;
            left: 10px;
            right: 10px;
            bottom: 10px;
            font-size: 6.8pt;
        }

        .signoff-table td {
            width: 25%;
            padding-right: 6px;
            vertical-align: top;
        }

        .signoff-title {
            font-weight: bold;
            margin-bottom: 6px;
        }

        .signoff-line {
            border-bottom: 1px solid #000;
            height: 10px;
            margin-bottom: 2px;
        }

        .signoff-meta {
            margin-top: 2px;
            line-height: 1.2;
        }
    </style>
</head>

<body>
    <table class="header-table">
        <tr>
            <td class="logo-col">
            </td>
            <td class="title-col">
                <p class="company-name">CHABRIN AGENCIES LIMITED</p>
                <p class="company-tag">PROPERTY MANAGING AGENTS</p>
                <p class="company-address">5th Floor, Room 517, NACICO Plaza Landhies Road, PO Box 16659-00620 Nairobi, Kenya</p>
                <p class="report-title">RETURNS FOR THE MONTH OF {{ strtoupper($monthLabel) }}</p>
            </td>
            <td class="side-col">
                <table class="meta-table">
                    <tr>
                        <td class="meta-label">Landlord</td>
                        <td>{{ $landlordName }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Property</td>
                        <td>{{ $propertyName }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Return Code</td>
                        <td>{{ $return->return_code }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="section-title">RETURN NET SUM</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 58%;">Narration</th>
                <th style="width: 18%;">Ref</th>
                <th style="width: 24%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($credits as $line)
            <tr>
                <td>{{ $line['narration'] }}</td>
                <td>{{ $line['ref'] }}</td>
                <td class="amount">{{ number_format($line['amount'], 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="3" style="text-align:center;">No credit lines found.</td>
            </tr>
            @endforelse
            <tr class="subtotal-row">
                <td colspan="2">Subtotal</td>
                <td class="amount">{{ number_format($totals['credits'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title less-section">LESS</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 16%;">Date</th>
                <th style="width: 44%;">Narration</th>
                <th style="width: 18%;">Ref</th>
                <th style="width: 22%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($debits as $line)
            <tr>
                <td>{{ $line['date'] }}</td>
                <td>{{ $line['narration'] }}</td>
                <td>{{ $line['ref'] }}</td>
                <td class="amount">{{ number_format($line['amount'], 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" style="text-align:center;">No deduction lines found.</td>
            </tr>
            @endforelse
            <tr class="subtotal-row">
                <td></td>
                <td colspan="2">Subtotal</td>
                <td class="amount">{{ number_format($totals['debits'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">OTHER RECEIPTS FROM LANDLORD ACC</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 58%;">Narration</th>
                <th style="width: 18%;">Ref</th>
                <th style="width: 24%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($otherReceipts as $line)
            <tr>
                <td>{{ $line['narration'] }}</td>
                <td>{{ $line['ref'] }}</td>
                <td class="amount">{{ number_format($line['amount'], 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="3" style="text-align:center;">No other landlord receipts found.</td>
            </tr>
            @endforelse
            <tr class="subtotal-row">
                <td colspan="2">Subtotal</td>
                <td class="amount">{{ number_format($totals['other_receipts'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td class="label">Amount Payable</td>
            <td class="amount">{{ number_format($totals['amount_payable'], 2) }}</td>
        </tr>
        <tr>
            <td class="label">Amount Paid</td>
            <td class="amount">{{ number_format($totals['amount_paid'], 2) }}</td>
        </tr>
        <tr>
            <td class="label">Balance</td>
            <td class="amount">{{ number_format($totals['balance'], 2) }}</td>
        </tr>
    </table>

    <div class="fixed-footer">
        <table class="signoff-table">
            <tr>
                <td>
                    <div class="signoff-title">Prepared By</div>
                    <div class="signoff-line"></div>
                    <div class="signoff-meta">Date: __________________</div>
                    <div class="signoff-meta">Sign: __________________</div>
                </td>
                <td>
                    <div class="signoff-title">Checked By</div>
                    <div class="signoff-line"></div>
                    <div class="signoff-meta">Date: __________________</div>
                    <div class="signoff-meta">Sign: __________________</div>
                </td>
                <td>
                    <div class="signoff-title">Approved By</div>
                    <div class="signoff-line"></div>
                    <div class="signoff-meta">Date: __________________</div>
                    <div class="signoff-meta">Sign: __________________</div>
                </td>
                <td>
                    <div class="signoff-title">Client Name</div>
                    <div class="signoff-line"></div>
                    <div class="signoff-meta">Date: __________________</div>
                    <div class="signoff-meta">Sign: __________________</div>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>