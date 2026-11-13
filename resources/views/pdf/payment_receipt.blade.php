<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payment Receipt {{ $receipt_number }}</title>
    <style>
        @page {
            margin: 30px 34px 60px 34px;
        }

        @include('pdf.partials.statement-styles')
    </style>
</head>

<body>
    @include('pdf.partials.letterhead', [
        'title' => 'PAYMENT RECEIPT',
        'period' => $receipt_number,
        'metaLines' => ['Date: ' . $payment_date],
    ])

    <table class="cards">
        <tr>
            <td class="col-landlord">
                <div class="card">
                    <div class="card-label">RECEIVED FROM</div>
                    <div class="landlord-name">{{ $tenant_name }}</div>
                    <table class="kv">
                        <tr><td class="k">Property</td><td>{{ $property }}</td></tr>
                        <tr><td class="k">Unit</td><td>{{ $unit }}</td></tr>
                        @if($bank)
                        <tr><td class="k">Paid through</td><td>{{ $bank }}</td></tr>
                        @endif
                        <tr><td class="k">Bank reference</td><td>{{ $reference }}</td></tr>
                    </table>
                </div>
            </td>
            <td class="col-summary">
                <div class="card">
                    <div class="card-label">PAYMENT SUMMARY</div>
                    <table class="summary">
                        <tr><td class="k">Amount received</td><td class="v cr">{{ number_format($amount, 2) }}</td></tr>
                        <tr><td class="k">Applied to invoices</td><td class="v">{{ number_format($allocated_total, 2) }}</td></tr>
                        <tr class="closing"><td class="k">Held as credit</td><td class="v">{{ number_format($credit, 2) }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="txn">
        <thead>
            <tr>
                <th style="width: 22%;">INVOICE</th>
                <th style="width: 40%;">FOR</th>
                <th class="r" style="width: 19%;">PAID</th>
                <th class="r" style="width: 19%;">BALANCE LEFT</th>
            </tr>
        </thead>
        <tbody>
            @forelse($allocations as $row)
            <tr class="{{ $loop->even ? 'alt' : '' }}">
                <td class="ref">{{ $row['invoice'] }}</td>
                <td>{{ $row['description'] }}</td>
                <td class="r cr">{{ number_format($row['allocated'], 2) }}</td>
                <td class="r">{{ number_format($row['balance'], 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="empty">No invoice was open for this payment, so the full amount is held as credit on the account.</td>
            </tr>
            @endforelse

            <tr class="final">
                <td colspan="2" style="text-align: right;">Total applied</td>
                <td class="r">{{ number_format($allocated_total, 2) }}</td>
                <td class="r"></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Chabrin Agencies Limited &middot; Receipt {{ $receipt_number }} &middot; Generated on {{ date('d M Y H:i:s') }}
    </div>
</body>

</html>
