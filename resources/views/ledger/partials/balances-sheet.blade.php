{{-- Account balances grouped by class, with the trial balance totals. Styled by the .gl-* rules on the page. --}}
@php
    use Illuminate\Support\Facades\Crypt;
    $money = fn($v) => number_format((float) $v, 2);
    $totals = $data['totals'];
@endphp

@forelse($data['groups'] as $group)
<div class="gl-card">
    <div class="gl-card-head">
        <span class="gl-card-title">{{ $group['name'] }}</span>
        <span class="gl-card-count">{{ $group['count'] }} {{ $group['count'] === 1 ? 'account' : 'accounts' }}</span>
    </div>
    <table class="gl-table">
        <thead>
            <tr>
                <th>Code</th>
                <th>Account</th>
                <th class="r">Debit</th>
                <th class="r">Credit</th>
                <th class="r">Balance</th>
                <th class="r"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($group['accounts'] as $a)
            <tr>
                <td class="code">{{ $a['code'] }}</td>
                <td>
                    {{ $a['name'] }}
                    @if(count($a['setup_roles']))
                    <span class="gl-system" title="Used in GL Settings: {{ implode(', ', $a['setup_roles']) }}">system</span>
                    @endif
                </td>
                <td class="r {{ $a['debit'] > 0 ? 'gl-dr' : 'gl-muted' }}">{{ $a['debit'] > 0 ? $money($a['debit']) : '—' }}</td>
                <td class="r {{ $a['credit'] > 0 ? 'gl-cr' : 'gl-muted' }}">{{ $a['credit'] > 0 ? $money($a['credit']) : '—' }}</td>
                <td class="r {{ $a['abnormal'] ? 'gl-abnormal' : '' }}" @if($a['abnormal']) title="Balance is on the opposite side to this account's normal side ({{ $a['normally'] }})" @endif>
                    {{ $money($a['balance']) }}@if($a['side'])<span class="gl-side">{{ $a['side'] }}</span>@endif
                </td>
                <td class="r">
                    @if($a['code'] !== '-')
                    <a class="gl-link" href="{{ route('gl.account.ledger', ['account' => Crypt::encrypt($a['id']), 'to' => $data['as_at']]) }}">View ledger</a>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@empty
<div class="gl-empty">
    <span class="material-symbols-outlined">account_balance</span>
    <p class="mt-2 mb-0">No account has postings as at this date.</p>
</div>
@endforelse

<div class="gl-tb">
    <div>
        <div class="gl-tb-label">Trial balance</div>
        <div>
            @if($totals['balanced'])
            <span class="gl-tb-ok">&#10003; Balanced</span>
            @else
            <span class="gl-tb-bad">&#9888; Not balanced, difference {{ $money(abs($totals['difference'])) }}</span>
            @endif
        </div>
    </div>
    <div>
        <div class="gl-tb-label">Total debits</div>
        <div class="gl-tb-value">{{ $money($totals['debit']) }}</div>
    </div>
    <div>
        <div class="gl-tb-label">Total credits</div>
        <div class="gl-tb-value">{{ $money($totals['credit']) }}</div>
    </div>
</div>

@if($data['hidden'] > 0)
<div class="gl-note">{{ $data['hidden'] }} {{ $data['hidden'] === 1 ? 'account has' : 'accounts have' }} no postings and {{ $data['hidden'] === 1 ? 'is' : 'are' }} hidden. Untick "Hide accounts with no postings" to show every account in the chart.</div>
@endif
