<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->reference_number }}</title>
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

        .header .subtitle {
            font-size: 8pt;
            color: #666;
            margin-top: 2px;
        }

        .info-box {
            background-color: #f8f9fa;
            padding: 8px 10px;
            margin-bottom: 10px;
            border-radius: 3px;
            border-left: 3px solid #3598db;
        }

        .info-box table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
        }

        .info-box td {
            padding: 2px 0;
        }

        .info-box .label {
            font-weight: bold;
            width: 100px;
            color: #555;
        }

        .amount-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 7.5pt;
        }

        .amount-table thead {
            background-color: #3598db;
            color: white;
        }

        .amount-table th {
            padding: 5px 6px;
            text-align: left;
            font-weight: bold;
            border: none;
            border-bottom: 1px solid #3598db;
        }

        .amount-table th.text-right {
            text-align: right;
        }

        .amount-table td {
            padding: 4px 6px;
            border: none;
            border-bottom: 1px solid #ddd;
        }

        .amount-table td.text-right {
            text-align: right;
        }

        .amount-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .total-row td {
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

        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            background-color: #3598db;
            color: white;
            font-size: 7pt;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Chabrin Agencies Ltd - Invoice</h1>
        <div class="subtitle">Reference: {{ $invoice->reference_number }}</div>
    </div>

    <div class="info-box">
        <table>
            <tr>
                <td class="label">Tenant:</td>
                <td>{{ $invoice->tenant->names ?? '-' }}</td>
                <td class="label">Property:</td>
                <td>{{ $invoice->property->property_name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Phone:</td>
                <td>{{ $invoice->tenant->mobile_number ?? '-' }}</td>
                <td class="label">Unit:</td>
                <td>{{ $invoice->unit->unit_code ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Period:</td>
                <td>{{ $invoice->returns_period ? date('F Y', strtotime($invoice->returns_period)) : '-' }}</td>
                <td class="label">Due Date:</td>
                <td>{{ optional($invoice->date_due)->format('d M Y') }}</td>
            </tr>
        </table>
    </div>

    <table class="amount-table">
        <thead>
            <tr>
                <th style="width:40%;">Item</th>
                <th class="text-right" style="width:20%;">Amount</th>
                <th class="text-right" style="width:20%;">VAT</th>
                <th class="text-right" style="width:20%;">Payable</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $invoice->narration }}</td>
                <td class="text-right">{{ number_format($invoice->total_amount ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($invoice->vat_ammount ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($invoice->payable_amount ?? 0, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="3" style="text-align:right;">Balance Due</td>
                <td class="text-right">{{ number_format($invoice->current_balance ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="info-box">
        <table>
            <tr>
                <td class="label">Status:</td>
                <td><span class="status-badge">{{ $invoice->status_name ?? 'Pending' }}</span></td>
                <td class="label">Batch:</td>
                <td>{{ $invoice->batch_number ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Generated on {{ date('d M Y H:i:s') }}
    </div>
</body>

</html>
