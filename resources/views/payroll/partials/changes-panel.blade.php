{{-- What has changed since the last payroll and could change pay. Shown to HR before a run is processed and to the accountant when reviewing it.
     Expects $changes, the list PayrollChangeFeed builds (or the copy kept with the run). It informs only. --}}
@php
    $changes = $changes ?? null;
    $groups = collect($changes['groups'] ?? []);
    $changedGroups = $groups->where('kind', 'change');
    $includedGroups = $groups->where('kind', 'included');
    $total = (int) ($changes['changes_total'] ?? 0);
    $high = (int) ($changes['high_total'] ?? 0);
    $tone = $total === 0 ? 'clear' : ($high > 0 ? 'high' : 'some');
@endphp
@if ($changes)
    <style>
        .pc-panel { border: 1px solid #e3e8ef; border-radius: .5rem; background: #fff; font-size: .8125rem; text-align: left; }
        .pc-head { display: flex; gap: .6rem; align-items: flex-start; padding: .65rem .85rem; border-radius: .5rem .5rem 0 0; }
        .pc-panel--clear .pc-head { background: #eef8f1; color: #1f7a45; }
        .pc-panel--some .pc-head { background: #eef6fc; color: #1f6f9f; }
        .pc-panel--high .pc-head { background: #fff6e0; color: #8a5a00; }
        .pc-head .material-symbols-outlined { font-size: 22px; flex: 0 0 auto; }
        .pc-title { font-weight: 600; font-size: .9rem; }
        .pc-sub { opacity: .85; }
        .pc-subhead { padding: .5rem .85rem .2rem; font-size: .7rem; letter-spacing: .04em; text-transform: uppercase; color: #6b7a8c; border-top: 1px solid #eef1f5; }
        .pc-group { border-top: 1px solid #eef1f5; }
        .pc-group > summary { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; padding: .5rem .85rem; cursor: pointer; list-style: none; font-weight: 600; color: #1f2d3d; }
        .pc-group > summary::-webkit-details-marker { display: none; }
        .pc-group > summary::before { content: "\25B8"; color: #6b7a8c; font-size: .8rem; transition: transform .15s ease; }
        .pc-group[open] > summary::before { transform: rotate(90deg); }
        .pc-chip { min-width: 1.4rem; padding: 0 .4rem; border-radius: 999px; background: #eef1f5; color: #3d4b5a; font-size: .7rem; font-weight: 600; text-align: center; }
        .pc-chip--high { background: #fff2cc; color: #8a5a00; }
        .pc-hint { padding: 0 .85rem .3rem 1.9rem; color: #6b7a8c; }
        .pc-rows { list-style: none; margin: 0; padding: 0 .85rem .5rem 1.9rem; }
        .pc-row { padding: .35rem 0 .35rem .6rem; border-top: 1px solid #f1f3f7; border-left: 3px solid transparent; }
        .pc-row:first-child { border-top: 0; }
        .pc-row.is-high { border-left-color: #e0a100; background: #fffbea; }
        .pc-what { color: #3d4b5a; }
        .pc-detail { color: #1f2d3d; font-variant-numeric: tabular-nums; }
        .pc-meta { color: #6b7a8c; font-size: .75rem; }
        .pc-more { padding: 0 .85rem .5rem 1.9rem; color: #6b7a8c; }
        .pc-warn { margin: .4rem .85rem; padding: .4rem .6rem; border: 1px solid #f3dfa2; border-radius: .4rem; background: #fffbea; color: #6b5200; }
        .pc-foot { padding: .5rem .85rem; border-top: 1px solid #eef1f5; color: #6b7a8c; font-size: .75rem; }
    </style>
    <div class="pc-panel pc-panel--{{ $tone }}" id="changesPanel">
        <div class="pc-head">
            <span class="material-symbols-outlined">{{ $total === 0 ? 'task_alt' : 'rule' }}</span>
            <div>
                @if ($total === 0)
                    <div class="pc-title">Nothing that affects pay has changed since the last payroll</div>
                @else
                    <div class="pc-title">{{ $total }} {{ $total === 1 ? 'change' : 'changes' }} since the last payroll</div>
                @endif
                <div class="pc-sub">
                    Measured from {{ $changes['since']['label'] }} ({{ $changes['since']['date'] }}).
                    @if ($high > 0)
                        <strong>{{ $high }} could change pay a lot.</strong>
                    @endif
                </div>
            </div>
        </div>

        @foreach ($changedGroups as $group)
            @include('payroll.partials.changes-group', ['group' => $group, 'open' => ($group['high'] ?? 0) > 0 || $total <= 8])
        @endforeach

        @if ($includedGroups->isNotEmpty())
            <div class="pc-subhead">Also feeding this payroll</div>
            @foreach ($includedGroups as $group)
                @include('payroll.partials.changes-group', ['group' => $group, 'open' => false])
            @endforeach
        @endif

        @if (!empty($changes['warnings']))
            <div class="pc-warn">Some checks could not be completed ({{ implode(', ', $changes['warnings']) }}), so this list may be incomplete.</div>
        @endif

        <div class="pc-foot">For your information only: it does not stop or change the payroll. Prepared {{ $changes['taken_at'] }}.</div>
    </div>
@endif
