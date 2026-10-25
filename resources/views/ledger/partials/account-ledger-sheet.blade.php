{{-- One account's ledger for a period: tiles and the journal lines with a running balance. Styled by the .gl-* rules on the page. --}}
@php
    $money = fn($v) => number_format((float) $v, 2);
    $amount = fn($v) => abs((float) $v) > 0 ? number_format((float) $v, 2) : '';
    $a = $ledger['account'];
@endphp
<div class="gl-sheet">
    <div class="gl-sheet-head">
        <div>
            <div class="gl-sheet-name">{{ $a['code'] }} &middot; {{ $a['name'] }}</div>
            <div class="gl-sheet-meta">{{ $a['class'] }} &middot; normally {{ strtolower($a['normally']) }} balance</div>
        </div>
        <div class="gl-sheet-period">{{ date('d M Y', strtotime($ledger['from'])) }} &ndash; {{ date('d M Y', strtotime($ledger['to'])) }}</div>
    </div>

    <div class="gl-tiles">
        <div class="gl-tile">
            <div class="gl-tile-label">Opening balance</div>
            <div class="gl-tile-value">{{ $money($ledger['opening']) }}</div>
        </div>
        <div class="gl-tile">
            <div class="gl-tile-label">Total debits</div>
            <div class="gl-tile-value gl-dr">{{ $money($ledger['debits']) }}</div>
        </div>
        <div class="gl-tile">
            <div class="gl-tile-label">Total credits</div>
            <div class="gl-tile-value gl-cr">{{ $money($ledger['credits']) }}</div>
        </div>
        <div class="gl-tile gl-tile-closing">
            <div class="gl-tile-label">Closing balance</div>
            <div class="gl-tile-value">{{ $money(abs($ledger['closing'])) }} {{ $ledger['closing'] == 0 ? '' : $ledger['closing_side'] }}</div>
            <div class="gl-tile-note">{{ number_format($ledger['line_count']) }} {{ $ledger['line_count'] === 1 ? 'line' : 'lines' }} in the period</div>
        </div>
    </div>

    <table class="gl-lines">
        <thead>
            <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Narration</th>
                <th class="r">Debit</th>
                <th class="r">Credit</th>
                <th class="r">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr class="bf">
                <td>{{ date('d-m-Y', strtotime($ledger['from'])) }}</td>
                <td></td>
                <td>Balance brought forward</td>
                <td class="r"></td>
                <td class="r"></td>
                <td class="r">{{ $money($ledger['opening']) }}</td>
            </tr>

            @forelse($ledger['entries'] as $e)
            <tr>
                <td style="white-space:nowrap;">{{ $e['date'] }}</td>
                <td class="ref">{{ $e['reference'] }}</td>
                <td>{{ $e['narration'] }}</td>
                <td class="r gl-dr">{{ $amount($e['debit']) }}</td>
                <td class="r gl-cr">{{ $amount($e['credit']) }}</td>
                <td class="r {{ $e['balance'] < 0 ? 'gl-neg' : '' }}">{{ $money($e['balance']) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="empty">No postings to this account in this period.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="totals">
                <td colspan="3">Period totals</td>
                <td class="r gl-dr">{{ $money($ledger['debits']) }}</td>
                <td class="r gl-cr">{{ $money($ledger['credits']) }}</td>
                <td class="r"></td>
            </tr>
            <tr class="final">
                <td colspan="5">Closing balance{{ $ledger['closing'] == 0 ? '' : ' (' . $ledger['closing_side'] . ')' }}</td>
                <td class="r">{{ $money(abs($ledger['closing'])) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="gl-note">
        The balance runs in this account's normal direction, so a negative figure means the account is on the opposite side.
        @if($ledger['truncated'])
        <br><strong>Showing the first {{ number_format(count($ledger['entries'])) }} of {{ number_format($ledger['line_count']) }} lines.</strong> The totals and closing balance cover the whole period. Choose a shorter period to see every line.
        @endif
    </div>
</div>
