<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Tenant Statement</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8pt;
            margin: 0;
            padding: 15px 25px;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 1.5px solid #3598db;
            padding-bottom: 8px;
        }

        .header h1 {
            margin: 0;
            font-size: 13pt;
            color: #3598db;
        }

        .tenant-info {
            background-color: #f8f9fa;
            padding: 8px 10px;
            margin-bottom: 10px;
            border-radius: 3px;
            border-left: 3px solid #3598db;
        }

        .tenant-info table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
        }

        .tenant-info td {
            padding: 2px 0;
        }

        .tenant-info .label {
            font-weight: bold;
            width: 100px;
            color: #555;
        }

        .period-info {
            text-align: center;
            margin-bottom: 10px;
            font-style: italic;
            color: #666;
            font-size: 7.5pt;
        }

        .statement-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 7.5pt;
        }

        .statement-table thead {
            background-color: #3598db;
            color: white;
        }

        .statement-table th {
            padding: 5px 6px;
            text-align: left;
            font-weight: bold;
            border: none;
            border-bottom: 1px solid #3598db;
        }

        .statement-table th.text-right {
            text-align: right;
        }

        .statement-table td {
            padding: 4px 6px;
            border: none;
            border-bottom: 1px solid #ddd;
        }

        .statement-table td.text-right {
            text-align: right;
        }

        .statement-table td.balance-cell {
            text-align: right;
            font-weight: bold;
        }

        .statement-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .totals-row td {
            font-weight: bold;
            background-color: #3598db !important;
            color: white;
            border-top: 1.5px solid #2a7ab5;
            padding: 5px 6px;
        }

        .statement-table tbody tr:last-child {
            font-weight: bold;
            background-color: #e8f4f8;
            border-top: 1.5px solid #3598db;
        }

        .footer {
            margin-top: 15px;
            text-align: center;
            font-size: 7pt;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 6px;
        }

        .narration-cell {
            max-width: 250px;
            word-wrap: break-word;
            font-size: 7pt;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Chabrin Agencies Ltd - Tenant Statement</h1>
    </div>

    <div class="tenant-info">
        <table>
            <tr>
                <td class="label">Tenant Name:</td>
                <td>{{ $tenantInfo['tenant_name'] }}</td>
                <td class="label">Email:</td>
                <td>{{ $tenantInfo['tenant_email'] }}</td>
            </tr>
            <tr>
                <td class="label">Phone:</td>
                <td>{{ $tenantInfo['tenant_phone'] }}</td>
                <td class="label">Property:</td>
                <td>{{ $tenantInfo['property_name'] }}</td>
            </tr>
            <tr>
                <td class="label">Unit:</td>
                <td>{{ $tenantInfo['unit_name'] }}</td>
                <td class="label"></td>
                <td></td>
            </tr>
        </table>
    </div>

    <div class="period-info">
        Statement Period: {{ $dateFrom }} to {{ $dateTo }}
    </div>

    <table class="statement-table">
        <thead>
            <tr>
                <th style="width: 12%;">Date</th>
                <th style="width: 15%;">Reference</th>
                <th style="width: 35%;">Narration</th>
                <th class="text-right" style="width: 12%;">Dr</th>
                <th class="text-right" style="width: 12%;">Cr</th>
                <th class="text-right" style="width: 14%;">Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($statement as $entry)
            <tr>
                <td>{{ $entry['date_formatted'] }}</td>
                <td>{{ $entry['reference'] }}</td>
                <td class="narration-cell">{{ $entry['narration'] }}</td>
                <td class="text-right">{{ $entry['debit_formatted'] === '-' ? '' : $entry['debit_formatted'] }}</td>
                <td class="text-right">{{ $entry['credit_formatted'] === '-' ? '' : $entry['credit_formatted'] }}</td>
                <td class="balance-cell">{{ $entry['balance_formatted'] }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            @php
            $totalDebit = collect($statement)->sum('debit');
            $totalCredit = collect($statement)->sum('credit');
            $closingBalance = collect($statement)->last()['balance'] ?? 0;
            @endphp
            <tr class="totals-row">
                <td colspan="3" style="text-align:right;">Closing Balance</td>
                <td class="text-right">{{ $totalDebit > 0 ? number_format($totalDebit, 2) : '' }}</td>
                <td class="text-right">{{ $totalCredit > 0 ? number_format($totalCredit, 2) : '' }}</td>
                <td class="text-right">{{ number_format($closingBalance, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Generated on {{ date('d M Y H:i:s') }}
    </div>
</body>

</html>