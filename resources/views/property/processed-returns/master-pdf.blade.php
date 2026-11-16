<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Master Return {{ $return->return_code }}</title>
    <style>
        @include('property.processed-returns.partials.pdf-styles')
    </style>
</head>

<body>
    @php
        $money = fn($v) => number_format((float) $v, 2);
        $amt = fn($v) => abs(round((float) ($v ?? 0), 2)) > 0 ? number_format((float) $v, 2) : '';
        $balance = (float) $totals['balance'];
        $landlordTitle = trim(preg_replace('/\(' . preg_quote((string) $return->landlord_code, '/') . '\)$/', '', $landlordName)) ?: $landlordName;
        $signoffColumns = ['Prepared by' => 'prepared', 'Checked by' => 'checked', 'Approved by' => 'approved', 'Client name' => null];
    @endphp

    @include('pdf.partials.letterhead', [
        'title' => 'MASTER RETURN',
        'period' => $monthLabel,
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

                        <tr><td class="k">Property</td><td>{{ $propertyName }}</td></tr>
                        <tr><td class="k">Return ID</td><td><strong>{{ $return->return_code }}</strong></td></tr>
                        @if($return->voucher_number)
                        <tr><td class="k">Voucher</td><td>{{ $return->voucher_number }}</td></tr>
                        @endif
                    </table>
                    </td></tr></table>
                </div>
            </td>

        </tr>
    </table>

    <div class="section">RETURN NET SUM</div>
    <table class="txn">
        <thead>
            <tr>
                <th style="width: 48%;">NARRATION</th>
                <th style="width: 28%;">REFERENCE</th>
                <th class="r" style="width: 24%;">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            @forelse($credits as $line)
            <tr class="{{ $loop->even ? 'alt' : '' }}">
                <td>{{ $line['narration'] }}</td>
                <td class="ref">{{ $line['ref'] !== '-' ? $line['ref'] : '' }}</td>
                <td class="r cr">{{ $amt($line['amount']) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="3" class="empty">No credit lines found.</td>
            </tr>
            @endforelse
            <tr class="totals">
                <td colspan="2">Subtotal</td>
                <td class="r">{{ $money($totals['credits']) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section">LESS</div>
    <table class="txn">
        <thead>
            <tr>
                <th style="width: 13%;">DATE</th>
                <th style="width: 40%;">NARRATION</th>
                <th style="width: 23%;">REFERENCE</th>
                <th class="r" style="width: 24%;">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            @forelse($debits as $line)
            <tr class="{{ $loop->even ? 'alt' : '' }}">
                <td>{{ $line['date'] !== '-' ? $line['date'] : '' }}</td>
                <td>{{ $line['narration'] }}</td>
                <td class="ref">{{ $line['ref'] !== '-' ? $line['ref'] : '' }}</td>
                <td class="r dr">{{ $amt($line['amount']) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="empty">No deduction lines found.</td>
            </tr>
            @endforelse
            <tr class="totals">
                <td colspan="3">Subtotal</td>
                <td class="r">{{ $money($totals['debits']) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section">OTHER RECEIPTS FROM LANDLORD ACC</div>
    <table class="txn">
        <thead>
            <tr>
                <th style="width: 48%;">NARRATION</th>
                <th style="width: 28%;">REFERENCE</th>
                <th class="r" style="width: 24%;">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            @forelse($otherReceipts as $line)
            <tr class="{{ $loop->even ? 'alt' : '' }}">
                <td>{{ $line['narration'] }}</td>
                <td class="ref">{{ $line['ref'] !== '-' ? $line['ref'] : '' }}</td>
                <td class="r">{{ $amt($line['amount']) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="3" class="empty">No other landlord receipts found.</td>
            </tr>
            @endforelse
            <tr class="totals">
                <td colspan="2">Subtotal</td>
                <td class="r">{{ $money($totals['other_receipts']) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="statement-closing">
    <table class="cards statement-summary"><tr><td style="width:48%;"></td>
            <td class="col-summary">
                <div class="card">
                    <div class="card-label">RETURN SUMMARY</div>
                    <table class="summary">
                        <tr><td class="k">Return net sum</td><td class="v cr">{{ $money($totals['credits']) }}</td></tr>
                        <tr><td class="k">Less deductions</td><td class="v dr">( {{ $money($totals['debits']) }} )</td></tr>
                        <tr><td class="k">Other receipts from landlord acc</td><td class="v">{{ $money($totals['other_receipts']) }}</td></tr>
                        <tr class="strong"><td class="k">Amount payable</td><td class="v">{{ $money($totals['amount_payable']) }}</td></tr>
                        <tr><td class="k">Amount paid</td><td class="v">{{ $money($totals['amount_paid']) }}</td></tr>
                        <tr class="closing"><td class="k">Balance</td><td class="v">{{ $money($balance) }}</td></tr>
                    </table>
                </div>
            </td>
    </tr></table>

    @include('property.processed-returns.partials.pdf-signoff')
    </div>
</body>

</html>
