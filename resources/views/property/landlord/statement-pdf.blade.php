<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Landlord Account Statement</title>
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

        .landlord-info {
            background-color: #f8f9fa;
            padding: 8px 10px;
            margin-bottom: 10px;
            border-radius: 3px;
            border-left: 3px solid #3598db;
        }

        .landlord-info table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
        }

        .landlord-info td {
            padding: 2px 0;
        }

        .landlord-info .label {
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
            table-layout: fixed;
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
            font-weight: normal;
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
            font-weight: normal;
            white-space: nowrap;
        }

        .statement-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .totals-row {
            font-weight: normal;
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

        .date-cell,
        .reference-cell,
        .amount-cell {
            white-space: nowrap;
        }

        .date-cell {
            padding-right: 10px;
        }

        .reference-cell {
            padding-left: 10px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Chabrin Agencies Ltd - Landlord Account Statement</h1>
    </div>

    <div class="landlord-info">
        <table>
            <tr>
                <td class="label">Landlord Name:</td>
                <td>{{ $landlordInfo['landlord_name'] }}</td>
                <td class="label">Email:</td>
                <td>{{ $landlordInfo['landlord_email'] }}</td>
            </tr>
            <tr>
                <td class="label">Phone:</td>
                <td>{{ $landlordInfo['landlord_phone'] }}</td>
                <td class="label">Property:</td>
                <td>{{ $landlordInfo['property_name'] }}</td>
            </tr>
        </table>
    </div>

    <div class="period-info">
        Statement Period: {{ $dateFrom }} to {{ $dateTo }}
    </div>

    <table class="statement-table">
        <thead>
            <tr>
                <th style="width: 10%;">Date</th>
                <th style="width: 13%;">Reference</th>
                <th style="width: 31%;">Narration</th>
                <th class="text-right" style="width: 11%;">Dr</th>
                <th class="text-right" style="width: 11%;">Cr</th>
                <th class="text-right" style="width: 24%;">Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($statement as $entry)
            <tr>
                <td class="date-cell">{{ $entry['date_formatted'] }}</td>
                <td class="reference-cell">{{ $entry['reference'] }}</td>
                <td class="narration-cell">{{ $entry['narration'] }}</td>
                <td class="text-right amount-cell">{{ $entry['debit_formatted'] }}</td>
                <td class="text-right amount-cell">{{ $entry['credit_formatted'] }}</td>
                <td class="balance-cell amount-cell">{{ $entry['balance_formatted'] }}</td>
            </tr>
            @endforeach
            @if(count($statement) > 0)
            @php
            $lastEntry = end($statement);
            @endphp
            <tr class="totals-row">
                <td colspan="5">Closing Balance</td>
                <td class="balance-cell amount-cell">{{ $lastEntry['balance_formatted'] }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    @if(!empty($recordsWarning))
    <div style="background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 3px; padding: 8px 10px; margin: 10px 0; font-size: 7pt; color: #856404;">
        <strong>Notice:</strong> {{ $recordsWarning }}
    </div>
    @endif

    <div class="footer">
        Generated on {{ date('d M Y H:i:s') }}
    </div>
</body>

</html>