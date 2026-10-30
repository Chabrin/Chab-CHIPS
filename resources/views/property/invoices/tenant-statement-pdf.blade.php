<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Tenant Statement</title>
    <style>
        @page {
            margin: 30px 34px 60px 34px;
        }

        @include('pdf.partials.statement-styles')
    </style>
</head>

<body>
    @php
        $totalDebit = collect($statement)->sum('debit');
        $totalCredit = collect($statement)->sum('credit');
        $closingBalance = collect($statement)->last()['balance'] ?? 0;
    @endphp

    @include('pdf.partials.letterhead', [
        'title' => 'TENANT STATEMENT',
        'period' => $dateFrom . ' to ' . $dateTo,
    ])

    <table class="cards">
        <tr>
            <td class="col-landlord">
                <div class="card">
                    <div class="card-label">TENANT</div>
                    <div class="landlord-name">{{ $tenantInfo['tenant_name'] }}</div>
                    <table class="kv">
                        <tr><td class="k">Email</td><td>{{ $tenantInfo['tenant_email'] }}</td></tr>
                        <tr><td class="k">Phone</td><td>{{ $tenantInfo['tenant_phone'] }}</td></tr>
                        <tr><td class="k">Property</td><td>{{ $tenantInfo['property_name'] }}</td></tr>
                        <tr><td class="k">Unit</td><td>{{ $tenantInfo['unit_name'] }}</td></tr>
                    </table>
                </div>
            </td>
            <td class="col-summary">
                <div class="card">
                    <div class="card-label">STATEMENT SUMMARY</div>
                    <table class="summary">
                        <tr><td class="k">Total Dr</td><td class="v dr">{{ number_format($totalDebit, 2) }}</td></tr>
                        <tr><td class="k">Total Cr</td><td class="v cr">{{ number_format($totalCredit, 2) }}</td></tr>
                        <tr class="closing"><td class="k">Closing Balance</td><td class="v {{ $closingBalance < 0 ? 'neg' : '' }}">{{ number_format($closingBalance, 2) }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="txn">
        <thead>
            <tr>
                <th style="width: 11%;">DATE</th>
                <th style="width: 31%;">REFERENCE</th>
                <th style="width: 22%;">NARRATION</th>
                <th class="r" style="width: 11%;">DR</th>
                <th class="r" style="width: 12%;">CR</th>
                <th class="r" style="width: 13%;">BALANCE</th>
            </tr>
        </thead>
        <tbody>
            @forelse($statement as $entry)
            <tr class="{{ $loop->even ? 'alt' : '' }}">
                <td>{{ $entry['date_formatted'] }}</td>
                <td class="ref">{{ $entry['reference'] }}</td>
                <td>{{ $entry['narration'] }}</td>
                <td class="r dr">{{ $entry['debit_formatted'] === '-' ? '' : $entry['debit_formatted'] }}</td>
                <td class="r cr">{{ $entry['credit_formatted'] === '-' ? '' : $entry['credit_formatted'] }}</td>
                <td class="r {{ $entry['balance'] < 0 ? 'neg' : '' }}">{{ $entry['balance_formatted'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="empty">No transactions in this period.</td>
            </tr>
            @endforelse

            <tr class="final">
                <td colspan="3" style="text-align: right;">Closing Balance</td>
                <td class="r">{{ $totalDebit > 0 ? number_format($totalDebit, 2) : '' }}</td>
                <td class="r">{{ $totalCredit > 0 ? number_format($totalCredit, 2) : '' }}</td>
                <td class="r">{{ number_format($closingBalance, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Chabrin Agencies Limited &middot; Generated on {{ date('d M Y H:i:s') }}
    </div>
</body>

</html>
