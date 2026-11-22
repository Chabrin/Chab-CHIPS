{{-- The landlord statement as page content. Same data as the PDF (ClientLedgerService::statement), styled by the .stmt-* rules on the statement page. --}}
@php
    $money = fn($v) => number_format((float) $v, 2);
    $amount = fn($v) => abs((float) $v) > 0 ? number_format((float) $v, 2) : '';
    $st = $statement;
    $closing = (float) $st['closing'];
    $closingNote = $closing > 0 ? 'Due to landlord' : ($closing < 0 ? 'Due from landlord' : 'Nil balance');
    $props = $st['properties'];
@endphp
<div class="stmt-sheet">
    <div class="stmt-head">
        <div>
            <div class="stmt-name">{{ $account['names'] }}</div>
            <div class="stmt-meta">
                <span>Account no. <strong>{{ $account['reference_number'] }}</strong></span>
                @if(!empty($account['mobile_number']))<span>{{ $account['mobile_number'] }}</span>@endif
                @if(count($props))
                <span>{{ implode(', ', array_slice($props, 0, 3)) }}@if(count($props) > 3) +{{ count($props) - 3 }} more @endif</span>
                @endif
            </div>
        </div>
        <div class="stmt-period">{{ date('d M Y', strtotime($st['from'])) }} &ndash; {{ date('d M Y', strtotime($st['to'])) }}</div>
    </div>

    <div class="stmt-tiles">
        <div class="stmt-tile">
            <div class="stmt-tile-label">Opening balance</div>
            <div class="stmt-tile-value">{{ $money($st['opening']) }}</div>
        </div>
        <div class="stmt-tile">
            <div class="stmt-tile-label">Total credits</div>
            <div class="stmt-tile-value cr">{{ $money($st['credits']) }}</div>
        </div>
        <div class="stmt-tile">
            <div class="stmt-tile-label">Total debits</div>
            <div class="stmt-tile-value dr">{{ $money($st['debits']) }}</div>
        </div>
        <div class="stmt-tile stmt-tile-closing">
            <div class="stmt-tile-label">Closing balance</div>
            <div class="stmt-tile-value">{{ $money($closing) }}</div>
            <div class="stmt-tile-note">{{ $closingNote }}</div>
        </div>
    </div>

    <div class="stmt-table-wrap">
        <table class="stmt-table">
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
                    <td>{{ date('d-m-Y', strtotime($st['from'])) }}</td>
                    <td></td>
                    <td>Opening balance</td>
                    <td class="r"></td>
                    <td class="r"></td>
                    <td class="r">{{ $money($st['opening']) }}</td>
                </tr>

                @forelse($st['rows'] as $row)
                <tr class="{{ $row['counted'] ? '' : 'uncounted' }}">
                    <td class="nowrap">{{ $row['date_formatted'] }}</td>
                    <td class="ref">{{ $row['reference'] !== '-' ? $row['reference'] : '' }}</td>
                    <td>{{ $row['narration'] }}@unless($row['counted'])<span class="stmt-nc">not counted</span>@endunless</td>
                    <td class="r dr">{{ $amount($row['debit']) }}</td>
                    <td class="r cr">{{ $amount($row['credit']) }}</td>
                    <td class="r {{ $row['balance'] < 0 ? 'neg' : '' }}">{{ $money($row['balance']) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="empty">No transactions in this period.</td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="totals">
                    <td colspan="3">Period totals</td>
                    <td class="r dr">{{ $money($st['debits']) }}</td>
                    <td class="r cr">{{ $money($st['credits']) }}</td>
                    <td class="r"></td>
                </tr>
                <tr class="final">
                    <td colspan="5">Closing balance &mdash; {{ $closingNote }}</td>
                    <td class="r">{{ $money($closing) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @include('property.landlord.partials.charges-breakdown')

    @if($st['has_uncounted'] || $st['undated'] > 0)
    <div class="stmt-note">
        @if($st['has_uncounted'])
        Entries marked &ldquo;not counted&rdquo; (such as balance snapshots written by the returns process) are listed for reference only and do not change the balance or the totals.<br>
        @endif
        @if($st['undated'] > 0)
        {{ $st['undated'] }} {{ $st['undated'] === 1 ? 'entry has' : 'entries have' }} no date on the account and {{ $st['undated'] === 1 ? 'is' : 'are' }} not shown.
        @endif
    </div>
    @endif
</div>
