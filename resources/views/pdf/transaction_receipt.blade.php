<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" conte .total {
        font-weight: 700;
        font-size: 15px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI" , Roboto, "Helvetica Neue" , Arial, sans-serif !important;
        }

        .footer {
        margin-top: 30px;
        font-size: 13px;
        color: #586069;
        text-align: center;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI" , Roboto, "Helvetica Neue" , Arial, sans-serif !important;
        }

        .footer a {
        color: #0366d6;
        text-decoration: none;
        font-weight: 600;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI" , Roboto, "Helvetica Neue" , Arial, sans-serif !important;
        }width, initial-scale=1.0">
    <title>Payment Receipt</title>
    <style>
        @page {
            size: A4;
            margin: 5mm 8mm 8mm 8mm;
        }

        body {
            font-family: sans-serif !important;
            font-size: 13px;
            margin: 0;
            padding: 10px;
            background: #fff;
            color: #24292e;
            position: relative;
        }

        *,
        *::before,
        *::after {
            font-family: sans-serif !important;
        }

        .container {
            max-width: 100%;
            margin: auto;
            padding: 15px;
            background: #fff;
            position: relative;
            z-index: 1;
            font-family: sans-serif !important;
        }

        .header {
            border-bottom: 1px solid #e1e4e8;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        .logo img {
            max-width: 140px;
            max-height: 60px;
            object-fit: contain;
        }

        .title {
            font-size: 17px;
            font-weight: 600;
            color: #3598db;
            font-family: sans-serif !important;
        }

        /* Details */
        .details {
            margin-bottom: 25px;
            line-height: 1.2;
            font-family: sans-serif !important;
        }

        .details p {
            margin: 6px 0;
            font-size: 12px !important;
            font-family: sans-serif !important;
            line-height: 1.2;
        }

        /* Table */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
            font-family: sans-serif !important;
        }

        .table th {
            font-weight: 600;
            background: #f6f8fa;
            border-bottom: 1px solid #e1e4e8;
            padding: 4px;
            font-family: sans-serif !important;
        }

        .table td {
            border-bottom: 0.5px solid #fafbfc;
            padding: 10px;
            vertical-align: top;
            font-family: sans-serif !important;
        }

        .table th:first-child,
        .table td:first-child {
            text-align: left;
        }

        .table th:last-child,
        .table td:last-child {
            text-align: right;
        }

        .total {
            font-weight: 700;
            font-size: 15px;
            font-family: sans-serif !important;
        }

        .footer {
            position: fixed;
            bottom: 10mm;
            left: 0;
            right: 0;
            font-size: 10px;
            text-align: left;
            font-family: sans-serif !important;
            width: 100%;
            padding: 0 8mm;
            box-sizing: border-box;
        }

        .footer a {
            color: #0366d6;
            text-decoration: none;
            font-weight: 600;
            font-family: sans-serif !important;
        }

        @media print {
            body {
                margin: 0;
                padding: 40px;
            }

            .container {
                border: none;
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <table style="width:100%; border-collapse:collapse;" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="text-align:left; vertical-align:middle;">
                        <div class="logo">
                            @if(isset($company) && $company->is_host == 1 && !empty($company->logo_data))
                            <img src="{{ $company->logo_data }}" alt="Company Logo">
                            @elseif(isset($company) && !empty($company->logo_url))
                            <img src="{{ $company->logo_url }}" alt="Company Logo">
                            @elseif(isset($company) && !empty($company->logo))
                            <img src="{{ asset('storage/' . $company->logo) }}" alt="Company Logo">
                            @else
                            <img src="{{ asset('images/default_logo.png') }}" alt="Logo">
                            @endif
                        </div>
                    </td>
                    <td style="text-align:right; vertical-align:middle;">
                        <div class="title">Transaction Receipt</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="details">
            <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($transaction->trans_date)->format('M d, Y') }}</p>
            <p><strong>Account Number:</strong> {{ $transaction->account_no }}</p>
            <p><strong>Transaction ID:</strong> {{ $transaction->id ?? 'N/A' }}</p>
            <p><strong>Pension Plan:</strong> {{ $fund_name }}</p>
            @if(isset($transaction->member))
            <p><strong>Member:</strong> {{ $transaction->member->first_name ?? '' }} {{ $transaction->member->last_name ?? '' }} ({{ $transaction->member->e_mail ?? '' }})</p>
            @endif
        </div>

        <table class="table">
            <tr>
                <th>Description</th>
                <th>Amount</th>
            </tr>
            <tr>
                <td>{{ ucfirst(strtolower($transaction->trans_type ?? 'Transaction')) }} | Receipted on {{ \Carbon\Carbon::parse($transaction->trans_date)->format('M d, Y') }} <br>
                    <small>Transaction Reference: {{ $transaction->reference_no }}</small> <br>
                    <small>MOP: {{ $transaction->mop }}</small> <br>
                </td>
                <td>{{ number_format($transaction->amount, 2) }} KES</td>
            </tr>
            <tr>
                <td>Processing Fee</td>
                <td>0.00 KES</td>
            </tr>
            <tr>
                <td class="total">Total</td>
                <td class="total">{{ number_format($transaction->amount, 2) }} KES</td>
            </tr>
        </table>

        <div class="footer">
            <p>{{ $company->company_name ?? 'Pension Management System' }} | {{ $company->physical_add ?? 'Corporate Address' }}</p>
            <p>{{ $company->email ?? 'info@company.com' }} | {{ $company->mobile ?? $company->tel ?? '0700000000' }} | Receipt generated: {{ $generated_at->format('M d, Y H:i:s T') }} | This is an automated receipt. Please retain for your records.</p>
        </div>
    </div>
</body>

</html>