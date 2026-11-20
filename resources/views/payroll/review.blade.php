@extends('layouts.main')

@section('styles')
<style>
    /* Payroll review page. Everything is scoped to .pr-* classes and #prGrid so nothing else on the site changes. */
    .pr-btn-icon { font-size: 18px; }

    /* Summary tiles: two across on a phone, four on a laptop, one row of eight on a wide screen */
    .pr-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; }
    @media (min-width: 768px)  { .pr-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    @media (min-width: 1400px) { .pr-stats { grid-template-columns: repeat(8, minmax(0, 1fr)); } }
    .pr-stat { border: 1px solid #e3e8ef; border-radius: .5rem; padding: .4rem .7rem; background: #fff; height: 100%; }
    .pr-stat-label { margin: 0; font-size: .68rem; line-height: 1.2; letter-spacing: .04em; text-transform: uppercase; color: #6b7a8c; }
    .pr-stat-value { font-size: 1rem; line-height: 1.35; font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; color: #1f2d3d; }
    .pr-stat--net { border-color: rgba(53, 152, 219, .4); background: rgba(53, 152, 219, .07); }
    .pr-stat--net .pr-stat-value { color: var(--theme-default, #3598db); }

    /* Warnings: one slim bar that opens to the list */
    .pr-warnings { border: 1px solid #f3dfa2; border-radius: .5rem; background: #fffbea; }
    .pr-warnings-toggle { display: flex; align-items: center; gap: .5rem; width: 100%; padding: .45rem .75rem; border: 0; background: transparent; text-align: left; color: #6b5200; }
    .pr-warnings-toggle .pr-chevron { transition: transform .15s ease; }
    .pr-warnings-toggle[aria-expanded="true"] .pr-chevron { transform: rotate(180deg); }
    .pr-warning-list { max-height: 11rem; overflow-y: auto; margin: 0; padding: 0 .75rem .4rem 2.4rem; font-size: .8125rem; color: #4d4000; }
    .pr-warning-list li { padding: .25rem 0; border-top: 1px solid #f3e8bd; }

    /* Filter bar above the grid */
    .pr-filterbar { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; margin-bottom: .75rem; }
    .pr-search { flex: 1 1 16rem; max-width: 24rem; }
    .pr-filterbar .material-symbols-outlined { font-size: 18px; }

    /* The grid: header stays in view while the rows scroll, tidy padding, a clear hover, amounts aligned on the right */
    #prGrid table.dataTable { font-size: .8125rem; margin: 0; }
    #prGrid table.dataTable thead th {
        padding: .55rem .75rem; background: #f6f8fb; color: #5b6b7c; font-size: .72rem; font-weight: 600;
        letter-spacing: .03em; text-transform: uppercase; white-space: nowrap; border-bottom: 1px solid #dfe5ec;
    }
    #prGrid table.dataTable tbody td { padding: .45rem .75rem; vertical-align: middle; border-bottom: 1px solid #eef1f5; box-shadow: none; }
    #prGrid table.dataTable tbody tr:hover > td { background: #f2f7fc; }
    #prGrid table.dataTable .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    #prGrid table.dataTable thead th.num { text-align: right; }
    #prGrid table.dataTable .col-net { background: rgba(53, 152, 219, .06); font-weight: 600; }
    #prGrid table.dataTable thead th.col-net { background: rgba(53, 152, 219, .12); color: #2a6f9d; }
    #prGrid table.dataTable tbody tr:hover > td.col-net { background: rgba(53, 152, 219, .13); }
    #prGrid .dt-scroll-body { border-bottom: 1px solid #dfe5ec; }
    .pr-icon-btn { width: 28px; height: 28px; padding: 0; display: inline-flex; align-items: center; justify-content: center; }
    .pr-icon-btn .material-symbols-outlined { font-size: 18px; }

    /* Activity log: a coloured card per event down a thin rail. The colour says what kind of event it was. */
    .pr-log { position: relative; margin: 0; padding: 0; list-style: none; }
    .pr-log-item { --tone: #6b7a8c; --tone-ink: #4a5a6a; --tone-bg: #f4f6f9; position: relative; display: flex; gap: .75rem; padding-bottom: .7rem; }
    .pr-log-item:last-child { padding-bottom: 0; }
    .pr-log-item:not(:last-child)::before { content: ""; position: absolute; left: 13px; top: 28px; bottom: 0; width: 2px; background: #e4e9f0; }
    .pr-log-item--primary { --tone: #3598db; --tone-ink: #1f6f9f; --tone-bg: #eef6fc; }
    .pr-log-item--success { --tone: #2e9e5b; --tone-ink: #1f7a45; --tone-bg: #eef8f1; }
    .pr-log-item--warning { --tone: #e0a100; --tone-ink: #8a5a00; --tone-bg: #fff8e6; }
    .pr-log-item--danger  { --tone: #d64545; --tone-ink: #a32e2e; --tone-bg: #fdeeee; }
    .pr-log-item--info    { --tone: #1f9fb5; --tone-ink: #137a8c; --tone-bg: #eaf7fa; }
    .pr-log-dot { flex: 0 0 28px; width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: var(--tone); color: #fff; }
    .pr-log-dot .material-symbols-outlined { font-size: 16px; }
    .pr-log-body { flex: 1 1 auto; min-width: 0; padding: .45rem .75rem; background: var(--tone-bg); border-left: 4px solid var(--tone); border-radius: .5rem; }
    .pr-log-title { color: var(--tone-ink); }
    .pr-log-comment { margin-top: .25rem; padding-top: .25rem; border-top: 1px dashed rgba(0, 0, 0, .12); color: #3d4b5a; font-size: .8125rem; white-space: pre-line; }

    /* Alert chips above the grid: one click narrows the list to the employees an alert is about */
    .pr-chips { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; }
    .pr-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .2rem .65rem; font-size: .78rem; line-height: 1.4; border: 1px solid #d5dce5; border-radius: 999px; background: #fff; color: #3d4b5a; cursor: pointer; }
    .pr-chip:hover { background: #f2f7fc; }
    .pr-chip.is-active { background: var(--theme-default, #3598db); border-color: var(--theme-default, #3598db); color: #fff; }
    .pr-chip-count { min-width: 1.25rem; padding: 0 .3rem; border-radius: 999px; font-size: .7rem; font-weight: 600; text-align: center; background: rgba(0, 0, 0, .08); }
    .pr-chip--danger .pr-chip-count { background: #fdeaea; color: #a32e2e; }
    .pr-chip--warning .pr-chip-count { background: #fff2cc; color: #8a5a00; }
    .pr-chip.is-clear .pr-chip-count { background: #e5f5ec; color: #1f7a45; }
    .pr-chip.is-active .pr-chip-count { background: rgba(255, 255, 255, .25); color: #fff; }
    .pr-filter-note { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; margin-bottom: .6rem; padding: .4rem .7rem; font-size: .8125rem; color: #1f4f70; background: #eef6fc; border: 1px solid #cfe6f6; border-radius: .5rem; }
    .pr-filter-note .material-symbols-outlined { font-size: 18px; }
    .pr-warning-list li { display: flex; align-items: center; gap: .6rem; }
    .pr-warning-text { flex: 1 1 auto; min-width: 0; }
    .pr-alert-action { flex: 0 0 auto; font-size: .75rem; }

    /* Confirm payment dialog: room below it so a bar fixed to the bottom of the screen (such as the debug bar in development) never covers the buttons */
    .pr-pay-dialog { margin-top: 1rem; margin-bottom: 5rem; }
</style>
@endsection

@section('content')
@php
    $statusBadges = [
        'draft' => 'badge-light-secondary',
        'with_accountant' => 'badge-light-warning',
        'returned' => 'badge-light-danger',
        'approved' => 'badge-light-success',
        'paid' => 'badge-light-primary',
        'reversed' => 'badge-light-danger',
    ];
    // Present once the run can be paid; older callers of this page do not send them
    $canMarkPaid = $canMarkPaid ?? false;
    $payment = $payment ?? null;
    $reverseNote = $reverseNote ?? null;
    $paidByName = $paidByName ?? null;
    $changes = $changes ?? null;
    // How each history entry reads: its words, an icon and a colour
    $actionMeta = [
        'processed' => ['label' => 'Processed', 'icon' => 'calculate', 'tone' => 'secondary'],
        'submitted' => ['label' => 'Submitted to accountant', 'icon' => 'send', 'tone' => 'primary'],
        'approved' => ['label' => 'Approved', 'icon' => 'check_circle', 'tone' => 'success'],
        'paid' => ['label' => 'Marked as paid', 'icon' => 'payments', 'tone' => 'success'],
        'month_opened' => ['label' => 'Next month opened', 'icon' => 'event_available', 'tone' => 'info'],
        'month_not_opened' => ['label' => 'Next month not opened', 'icon' => 'event_busy', 'tone' => 'warning'],
        'returned' => ['label' => 'Returned to HR', 'icon' => 'undo', 'tone' => 'warning'],
        'reversed' => ['label' => 'Reversed', 'icon' => 'restart_alt', 'tone' => 'danger'],
        'eft_test_file' => ['label' => 'Downloaded test EFT file', 'icon' => 'download', 'tone' => 'info'],
        'limit_override' => ['label' => 'Legal limit on deductions overridden', 'icon' => 'gavel', 'tone' => 'danger'],
    ];
    $money = fn ($amount) => number_format((float) $amount, 2);
    // Plain notes from processing, then the alerts worked out from the run. Some alerts can narrow the grid to the employees they are about.
    $alerts = $alerts ?? [];
    $alertLabels = $alertLabels ?? [];
    $items = array_merge(array_map(fn ($message) => ['message' => $message], $warnings), $alerts);
    $warningCount = count($items);
    $filterAlerts = array_values(array_filter($alerts, fn ($alert) => !empty($alert['filter'])));
    $noContract = collect($alerts)->firstWhere('key', 'no_contract');
    $stats = [
        'Employees' => number_format((int) $totals->employees),
        'Gross' => $money($totals->gross),
        'PAYE' => $money($totals->paye),
        'NSSF' => $money($totals->nssf),
        'SHA' => $money($totals->sha),
        'Housing Levy' => $money($totals->housing_levy),
        'Total Deductions' => $money($totals->total_deductions),
        'Net Pay' => $money($totals->net_pay),
    ];
@endphp
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="card mb-3">
            <div class="card-header card-no-border d-flex align-items-start flex-wrap gap-2 py-3">
                <div class="me-auto">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">Payroll Review: {{ $period->start_date->format('F Y') }}</h5>
                        <span class="badge {{ $statusBadges[$run->status] ?? 'badge-light-secondary' }}">{{ $run->status_label }}</span>
                    </div>
                    <div class="text-muted small mt-1">
                        {{ $period->start_date->format('d M Y') }} to {{ $period->end_date->format('d M Y') }}
                        @if ($run->processed_by)
                            &middot; Processed by {{ $userNames[$run->processed_by] ?? 'Unknown' }}
                        @endif
                        @if ($run->approved_by)
                            &middot; Approved by {{ $userNames[$run->approved_by] ?? 'Unknown' }}
                        @endif
                        @if ($run->status === 'paid' && !empty($run->payment_date))
                            &middot; Paid on {{ $run->payment_date->format('d M Y') }}{{ $paidByName ? ' (confirmed by ' . $paidByName . ')' : '' }}
                        @endif
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('payroll.processing') }}" class="btn btn-light btn-sm d-inline-flex align-items-center gap-1">
                        <span class="material-symbols-outlined pr-btn-icon">arrow_back</span> Back
                    </a>
                    @if ((int) $totals->employees > 0)
                        <a href="{{ route('payroll.review.export', $period->id) }}" class="btn btn-light btn-sm d-inline-flex align-items-center gap-1">
                            <span class="material-symbols-outlined pr-btn-icon">download</span> Download Excel
                        </a>
                    @endif
                    @if ($canDownloadEft)
                        <a href="{{ route('payroll.review.eft', $period->id) }}" class="btn btn-light btn-sm d-inline-flex align-items-center gap-1">
                            <span class="material-symbols-outlined pr-btn-icon">account_balance</span> Download Test EFT File
                        </a>
                    @endif
                    @if ($canSubmit)
                        <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" id="submitRunBtn">
                            <span class="material-symbols-outlined pr-btn-icon">send</span> Submit to Accountant
                        </button>
                    @endif
                    @if ($canDiscard)
                        <button type="button" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1 comment-action" data-action="reverse" data-title="Discard and re-run" data-label="Reason for discarding this run">
                            <span class="material-symbols-outlined pr-btn-icon">delete_sweep</span> Discard &amp; Re-run
                        </button>
                    @endif
                    @if ($canDecide)
                        <button type="button" class="btn btn-success btn-sm d-inline-flex align-items-center gap-1" id="approveRunBtn">
                            <span class="material-symbols-outlined pr-btn-icon">check_circle</span> Approve
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1 comment-action" data-action="return" data-title="Return to HR" data-label="What needs to change?">
                            <span class="material-symbols-outlined pr-btn-icon">undo</span> Return to HR
                        </button>
                    @endif
                    @if ($canMarkPaid && $payment)
                        <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" id="markPaidBtn">
                            <span class="material-symbols-outlined pr-btn-icon">payments</span> Mark as Paid
                        </button>
                    @endif
                    @if ($canReverseApproved)
                        <button type="button" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1 comment-action" data-action="reverse" data-title="Reverse approved payroll" data-label="Reason for reversing this approved run" data-note="{{ $reverseNote }}">
                            <span class="material-symbols-outlined pr-btn-icon">restart_alt</span> Reverse
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body pt-0 pb-3">
                @if ($run->status === 'returned' && $run->return_comment)
                    <div class="alert alert-danger d-flex align-items-start gap-2 py-2 px-3 mb-2 small" role="alert">
                        <span class="material-symbols-outlined pr-btn-icon mt-1">undo</span>
                        <div><strong>Returned by the accountant:</strong> {{ $run->return_comment }}</div>
                    </div>
                @endif
                @if ($isOwnRun && $run->status === 'with_accountant')
                    <div class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 mb-2 small" role="alert">
                        <span class="material-symbols-outlined pr-btn-icon">info</span>
                        <div>You processed or submitted this payroll, so another accountant must review it.</div>
                    </div>
                @endif
                @if ($run->status === 'approved')
                    <div class="alert alert-info d-flex align-items-start gap-2 py-2 px-3 mb-2 small" role="alert" id="approvedNote">
                        <span class="material-symbols-outlined pr-btn-icon mt-1">payments</span>
                        @if ($canMarkPaid)
                            <div><strong>Next step:</strong> download the EFT file, pay it at the bank, then press <strong>Mark as Paid</strong>. That confirms the payment, locks this payroll and opens the next payroll month. Until then you can still reverse it if you find a mistake.</div>
                        @elseif ($isOwnRun)
                            <div>You processed or submitted this payroll, so another accountant must confirm the payment.</div>
                        @else
                            <div>Approved. The accountant confirms the bank payment next, and the next payroll month opens when they do.</div>
                        @endif
                    </div>
                @endif
                @if ($run->status === 'paid')
                    <div class="alert alert-success d-flex align-items-start gap-2 py-2 px-3 mb-2 small" role="alert" id="paidNote">
                        <span class="material-symbols-outlined pr-btn-icon mt-1">verified</span>
                        <div>
                            <strong>Paid.</strong> This payroll is locked and can no longer be reversed.
                            @if (!empty($run->payment_reference))
                                Bank reference: <strong>{{ $run->payment_reference }}</strong>.
                            @endif
                            If a mistake turns up, correct it with a one-time item in the next payroll month.
                        </div>
                    </div>
                @endif
                @if ($warningCount > 0)
                    {{-- One bar for every warning. Open when there are only one or two, folded away when there are more. --}}
                    <div class="pr-warnings mb-2" id="runWarningsGroup">
                        <button type="button" class="pr-warnings-toggle" data-bs-toggle="collapse" data-bs-target="#runWarnings"
                                aria-expanded="{{ $warningCount <= 2 ? 'true' : 'false' }}" aria-controls="runWarnings">
                            <span class="material-symbols-outlined pr-btn-icon">warning</span>
                            <span class="fw-semibold small text-nowrap">{{ $warningCount }} {{ $warningCount === 1 ? 'warning' : 'warnings' }} to check</span>
                            <span class="small text-truncate flex-grow-1" style="min-width:0;opacity:.8;">{{ $items[0]['message'] }}</span>
                            <span class="material-symbols-outlined pr-btn-icon pr-chevron">expand_more</span>
                        </button>
                        <div class="collapse {{ $warningCount <= 2 ? 'show' : '' }}" id="runWarnings">
                            <ul class="pr-warning-list">
                                @foreach ($items as $item)
                                    <li>
                                        <span class="pr-warning-text">{{ $item['message'] }}</span>
                                        @if (!empty($item['filter']))
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 pr-alert-action pr-alert-show" data-alert-filter="{{ $item['filter'] }}">Show {{ $item['count'] }}</button>
                                        @elseif (!empty($item['staff']))
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 pr-alert-action" data-bs-toggle="modal" data-bs-target="#noContractModal">See who</button>
                                        @elseif (!empty($item['link']))
                                            <a href="{{ $item['link']['url'] }}" class="btn btn-sm btn-outline-secondary py-0 pr-alert-action">{{ $item['link']['label'] }}</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <div class="pr-stats">
                    @foreach ($stats as $label => $value)
                        <div class="pr-stat {{ $label === 'Net Pay' ? 'pr-stat--net' : '' }}">
                            <div class="pr-stat-label">{{ $label }}</div>
                            <div class="pr-stat-value">{{ $value }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @if (!empty($changes))
            {{-- What changed since the last payroll, as HR saw it when processing this run --}}
            <div class="card mb-3" id="changesCard">
                <div class="card-header card-no-border py-3">
                    <h5 class="mb-0">Changes since the last payroll</h5>
                    <div class="text-muted small mt-1">Kept from when this payroll was processed, so you can see what may explain differences from last month.</div>
                </div>
                <div class="card-body pt-0">
                    @include('payroll.partials.changes-panel', ['changes' => $changes])
                </div>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header card-no-border d-flex align-items-center gap-2 py-3">
                <h5 class="mb-0">Employees</h5>
                <span class="badge text-bg-light border">{{ number_format((int) $totals->employees) }}</span>
            </div>
            <div class="card-body pt-0">
                <div class="pr-filterbar" role="search">
                    <div class="input-group input-group-sm pr-search">
                        <span class="input-group-text bg-transparent"><span class="material-symbols-outlined">search</span></span>
                        <input type="search" id="prSearch" class="form-control" placeholder="Search name, staff no or department" aria-label="Search employees" autocomplete="off">
                    </div>
                    @if (count($filterAlerts))
                        <div class="pr-chips" id="prAlertChips" role="group" aria-label="Show only employees with an alert">
                            <span class="small text-muted">Alerts</span>
                            <button type="button" class="pr-chip is-active" data-alert-filter="" aria-pressed="true">All</button>
                            @foreach ($filterAlerts as $alert)
                                <button type="button" class="pr-chip pr-chip--{{ $alert['tone'] }}" data-alert-filter="{{ $alert['filter'] }}" aria-pressed="false">
                                    {{ $alertLabels[$alert['filter']] ?? $alert['filter'] }} <span class="pr-chip-count">{{ $alert['count'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                    <div class="d-flex align-items-center gap-2 ms-md-auto">
                        <button type="button" class="btn btn-sm btn-light d-inline-flex align-items-center" id="prRefresh" title="Reload the list, for example after fixing a staff record" aria-label="Reload the list">
                            <span class="material-symbols-outlined">refresh</span>
                        </button>
                        <label for="prPageLength" class="small text-muted mb-0">Show</label>
                        <select id="prPageLength" class="form-select form-select-sm w-auto" aria-label="Entries per page">
                            @foreach ([10, 25, 50, 100] as $size)
                                <option value="{{ $size }}" @selected($size === 25)>{{ $size }}</option>
                            @endforeach
                            <option value="-1">All</option>
                        </select>
                        <span class="small text-muted">entries</span>
                    </div>
                </div>

                <div class="pr-filter-note d-none" id="prFilterNote" role="status">
                    <span class="material-symbols-outlined">filter_alt</span>
                    <span>Showing only employees with <strong id="prFilterName"></strong>. Use the edit icon to fix their staff record in a new tab, then press refresh.</span>
                    <button type="button" class="btn btn-link btn-sm p-0 ms-auto" data-alert-filter="">Show all</button>
                </div>

                <div id="prGrid">
                    <table class="display" id="run-employees-table" style="width:100%">
                        <thead>
                            <tr>
                                <th>Staff No</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th class="num">Basic</th>
                                <th class="num">Gross</th>
                                <th class="num">PAYE</th>
                                <th class="num">NSSF</th>
                                <th class="num">SHA</th>
                                <th class="num">Housing Levy</th>
                                <th class="num">Other</th>
                                <th class="num col-net">Net Pay</th>
                                <th class="num">Change</th>
                                <th>Flags</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header card-no-border py-3">
                <h5 class="mb-0">History</h5>
            </div>
            <div class="card-body pt-0">
                @if ($run->logs->isEmpty())
                    <p class="text-muted mb-0">No activity recorded yet.</p>
                @else
                    {{-- Newest first, as the run keeps its history --}}
                    <ol class="pr-log">
                        @foreach ($run->logs as $log)
                            @php
                                $meta = $actionMeta[$log->action] ?? ['label' => $log->action, 'icon' => 'history', 'tone' => 'secondary'];
                            @endphp
                            <li class="pr-log-item pr-log-item--{{ $meta['tone'] }}">
                                <span class="pr-log-dot"><span class="material-symbols-outlined">{{ $meta['icon'] }}</span></span>
                                <div class="pr-log-body">
                                    <div class="d-flex flex-wrap align-items-baseline column-gap-2">
                                        <strong class="pr-log-title">{{ $meta['label'] }}</strong>
                                        <span class="text-muted small">by {{ $log->user?->name ?? 'System' }}</span>
                                        <time class="text-muted small ms-auto" datetime="{{ $log->created_at?->toIso8601String() }}">{{ $log->created_at?->format('d M Y, H:i') }}</time>
                                    </div>
                                    @if ($log->comment)
                                        <div class="pr-log-comment">{{ $log->comment }}</div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="payslipModal" tabindex="-1" aria-labelledby="payslipModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="payslipModalLabel">Payslip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="payslipModalBody"></div>
        </div>
    </div>
</div>

@if ($noContract)
    <div class="modal fade" id="noContractModal" tabindex="-1" aria-labelledby="noContractModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="noContractModalTitle">Active staff with no contract</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">These staff are not in this run because they have no active contract. Add a contract for each, then re-run the payroll to include them.</p>
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Staff No</th><th>Name</th></tr></thead>
                        <tbody>
                            @foreach ($noContract['staff'] as $person)
                                <tr><td class="text-nowrap">{{ $person['number'] }}</td><td>{{ $person['name'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if (($noContract['more'] ?? 0) > 0)
                        <p class="small text-muted mt-2 mb-0">and {{ $noContract['more'] }} more.</p>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                    <a href="{{ route('contract.create') }}" class="btn btn-primary btn-sm">Add a contract</a>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="modal fade" id="commentModal" tabindex="-1" aria-labelledby="commentModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="commentForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="commentModalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small py-2 px-3 d-none" id="commentNote" role="alert"></div>
                    <label for="commentText" class="form-label" id="commentLabel"></label>
                    <textarea class="form-control" id="commentText" rows="4" maxlength="1000" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="commentSubmitBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if ($canMarkPaid && $payment)
    {{-- Confirm payment: the checks the accountant should read before pressing the button. Paying is final. --}}
    <div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payModalTitle" aria-hidden="true">
        {{-- The whole dialog scrolls (not just its body), so Confirm payment is never pinned under something fixed to the bottom of the screen --}}
        <div class="modal-dialog modal-lg pr-pay-dialog">
            <div class="modal-content">
                <form id="payForm" novalidate>
                    <input type="hidden" id="payNetTotal" value="{{ $payment['net_total'] }}">
                    <input type="hidden" id="payEmployees" value="{{ $payment['employees'] }}">
                    <div class="modal-header">
                        <h5 class="modal-title" id="payModalTitle">Confirm payment: {{ $period->start_date->format('F Y') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning small py-2 px-3 d-flex gap-2" role="alert">
                            <span class="material-symbols-outlined pr-btn-icon mt-1">lock</span>
                            <div>Marking this payroll as paid is <strong>final</strong>. It cannot be reversed afterwards, and it opens the next payroll month. Check the points below first.</div>
                        </div>

                        <ul class="list-unstyled small mb-3" id="payChecks">
                            <li class="d-flex gap-2 mb-2">
                                <span class="material-symbols-outlined pr-btn-icon text-success">groups</span>
                                <div><strong>{{ number_format($payment['employees']) }} staff</strong> are in this payroll, with a total net pay of <strong>KES {{ number_format($payment['net_total'], 2) }}</strong>.</div>
                            </li>
                            <li class="d-flex gap-2 mb-2">
                                <span class="material-symbols-outlined pr-btn-icon text-success">account_balance</span>
                                <div><strong>{{ number_format($payment['payable']['count']) }} staff</strong> are in the EFT file, KES <strong>{{ number_format($payment['payable']['total'], 2) }}</strong>.</div>
                            </li>
                            @if ($payment['excluded']['count'] > 0)
                                <li class="d-flex gap-2 mb-2" id="payExcluded">
                                    <span class="material-symbols-outlined pr-btn-icon text-warning">warning</span>
                                    <div>
                                        <strong>{{ number_format($payment['excluded']['count']) }} staff are not in the EFT file</strong> (KES {{ number_format($payment['excluded']['total'], 2) }}). Make sure they were paid another way, or that nothing is due to them.
                                        <ul class="mb-0 ps-3">
                                            @foreach ($payment['excluded']['reasons'] as $reason => $count)
                                                <li>{{ $reason }}: {{ $count }}</li>
                                            @endforeach
                                        </ul>
                                        @if (!empty($payment['excluded']['people']))
                                            <div class="text-muted">{{ implode(', ', $payment['excluded']['people']) }}{{ $payment['excluded']['count'] > count($payment['excluded']['people']) ? ' and ' . ($payment['excluded']['count'] - count($payment['excluded']['people'])) . ' more' : '' }}</div>
                                        @endif
                                    </div>
                                </li>
                            @endif
                            @if ($payment['eft_downloaded'])
                                <li class="d-flex gap-2 mb-2" id="payEftDone">
                                    <span class="material-symbols-outlined pr-btn-icon text-success">download_done</span>
                                    <div>An EFT file was downloaded by {{ $payment['eft_downloaded']['by'] }} on {{ $payment['eft_downloaded']['at'] }}.</div>
                                </li>
                            @else
                                <li class="d-flex gap-2 mb-2" id="payEftMissing">
                                    <span class="material-symbols-outlined pr-btn-icon text-warning">warning</span>
                                    <div>No EFT file has been downloaded for this payroll. Carry on only if the payment was made some other way.</div>
                                </li>
                            @endif
                        </ul>

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="payDate" class="form-label">Date the money was paid <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="payDate" value="{{ $payment['default_date'] }}" max="{{ $payment['default_date'] }}" required>
                            </div>
                            <div class="col-sm-6">
                                <label for="payReference" class="form-label">Bank reference <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="payReference" maxlength="100" placeholder="Batch or transaction number from the bank" autocomplete="off" required>
                            </div>
                            <div class="col-12">
                                <label for="payNote" class="form-label">Note <span class="text-muted">(optional)</span></label>
                                <textarea class="form-control" id="payNote" rows="2" maxlength="1000"></textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="payConfirm">
                                    <label class="form-check-label" for="payConfirm">I confirm the bank has processed this payment of <strong>KES {{ number_format($payment['net_total'], 2) }}</strong> for {{ number_format($payment['employees']) }} staff. I understand this cannot be undone.</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" id="paySubmitBtn" disabled>
                            <span class="material-symbols-outlined pr-btn-icon">payments</span> Confirm payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection

@section('scripts')
<script src="{{ asset('assets/js/payroll-review-grid.js') }}?v={{ filemtime(public_path('assets/js/payroll-review-grid.js')) }}"></script>
<script>
    $(document).ready(function() {
        const periodId = @json($period->id);
        const csrfToken = '{{ csrf_token() }}';
        const actionUrls = {
            submit: "{{ route('ajax.payroll.submit', $period->id) }}",
            approve: "{{ route('ajax.payroll.approve', $period->id) }}",
            return: "{{ route('ajax.payroll.return', $period->id) }}",
            reverse: "{{ route('ajax.payroll.reverse', $period->id) }}",
            pay: "{{ route('ajax.payroll.pay', $period->id) }}",
        };
        let commentAction = null;
        let activeFilter = '';
        const alertLabels = @json($alertLabels);

        const grid = $('#run-employees-table').DataTable({
            ajax: {
                url: "{{ route('ajax.payroll.employees', $period->id) }}",
                // The alert chosen above the grid goes to the server, which sends back only the employees it is about
                data: params => { if (activeFilter) params.alert_filter = activeFilter; },
                dataSrc: 'employees'
            },
            columns: PayrollReviewGrid.columns(),
            pageLength: 25,
            order: [[1, 'asc']],
            // The table's own "entries" and search boxes are replaced by the filter bar above it; the count and pages stay below
            layout: { topStart: null, topEnd: null, bottomStart: 'info', bottomEnd: 'paging' },
            // Rows scroll inside the grid and the header stays put
            scrollX: true,
            scrollY: '56vh',
            scrollCollapse: true,
            language: {
                emptyTable: 'No employees to show',
                zeroRecords: 'No employees match your search',
                info: 'Showing _START_ to _END_ of _TOTAL_ employees',
                infoEmpty: 'No employees',
                infoFiltered: '(filtered from _MAX_)'
            }
        });

        let searchTimer;
        $('#prSearch').on('input', function() {
            const term = this.value;
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => grid.search(term).draw(), 150);
        });
        $('#prPageLength').on('change', function() {
            grid.page.len(parseInt(this.value, 10)).draw();
        });
        // Alert filter: the chips above the grid and the "Show" buttons in the warnings bar all set the same filter
        function setAlertFilter(filter) {
            activeFilter = filter || '';
            $('#prAlertChips .pr-chip').each(function() {
                const on = String($(this).data('alert-filter') || '') === activeFilter;
                $(this).toggleClass('is-active', on).attr('aria-pressed', on ? 'true' : 'false');
            });
            $('#prFilterNote').toggleClass('d-none', !activeFilter);
            $('#prFilterName').text(alertLabels[activeFilter] || '');
            grid.ajax.reload();
        }
        $(document).on('click', '[data-alert-filter]', function() {
            setAlertFilter(String($(this).data('alert-filter') || ''));
            if ($(this).hasClass('pr-alert-show')) {
                document.getElementById('prGrid').scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
        $('#prRefresh').on('click', () => grid.ajax.reload(null, false));
        // How many employees each alert still covers comes back with the list, so fixed ones drop to 0 after a refresh
        grid.on('xhr.dt', function(e, settings, json) {
            $.each((json && json.alert_counts) || {}, function(key, count) {
                $('#prAlertChips .pr-chip[data-alert-filter="' + key + '"]').toggleClass('is-clear', count === 0).find('.pr-chip-count').text(count);
            });
        });

        // Keep the header lined up with the rows when the width changes
        $(window).on('resize', () => grid.columns.adjust());

        $('#run-employees-table').on('click', '.view-payslip', function() {
            const $button = $(this);
            $('#payslipModalLabel').text($button.data('name'));
            $('#payslipModalBody').html('<p class="text-muted mb-0">Loading...</p>');
            $('#payslipModal').modal('show');
            $.get(`/ajax/payroll/summaries/${$button.data('id')}/payslip`)
                .done(res => $('#payslipModalBody').html(res.html))
                .fail(() => $('#payslipModalBody').html('<p class="text-danger mb-0">Could not load this payslip.</p>'));
        });

        function runAction(action, data, $button) {
            $button.prop('disabled', true);
            $.ajax({
                url: actionUrls[action],
                type: 'POST',
                data: Object.assign({ _token: csrfToken }, data),
                success: function(res) {
                    toastr.success(res.message);
                    if (action === 'reverse') {
                        window.location = "{{ route('payroll.processing') }}";
                        return;
                    }
                    window.location.reload();
                },
                error: function(xhr) {
                    $button.prop('disabled', false);
                    const errors = xhr.responseJSON?.errors;
                    toastr.error(errors ? Object.values(errors).join(' ') : (xhr.responseJSON?.error || 'Could not update this payroll run'));
                }
            });
        }

        $('#submitRunBtn').on('click', function() {
            if (confirm('Submit this payroll to the accountant? It will be locked while they review it.')) {
                runAction('submit', {}, $(this));
            }
        });

        $('#approveRunBtn').on('click', function() {
            if (confirm('Approve this payroll? Payslips will open and loan balances will update.')) {
                runAction('approve', {}, $(this));
            }
        });

        $('.comment-action').on('click', function() {
            commentAction = $(this).data('action');
            $('#commentModalTitle').text($(this).data('title'));
            $('#commentLabel').text($(this).data('label'));
            // A warning for the action, such as what reversing an approved run takes back
            const note = $(this).data('note');
            $('#commentNote').toggleClass('d-none', !note).text(note || '');
            $('#commentText').val('');
            $('#commentModal').modal('show');
        });

        // Confirm payment: the button stays off until the date, the bank reference and the tick are all there
        function payFormReady() {
            const ready = !!$('#payDate').val() && $('#payReference').val().trim() !== '' && $('#payConfirm').is(':checked');
            $('#paySubmitBtn').prop('disabled', !ready);
            return ready;
        }
        $('#markPaidBtn').on('click', function() {
            $('#payReference, #payNote').val('');
            $('#payConfirm').prop('checked', false);
            payFormReady();
            $('#payModal').modal('show');
        });
        $('#payForm').on('input change', payFormReady);
        $('#payForm').on('submit', function(e) {
            e.preventDefault();
            if (!payFormReady()) {
                return;
            }
            runAction('pay', {
                payment_date: $('#payDate').val(),
                payment_reference: $('#payReference').val().trim(),
                payment_note: $('#payNote').val(),
                confirmed: 1,
                net_total: $('#payNetTotal').val(),
                employees: $('#payEmployees').val()
            }, $('#paySubmitBtn'));
        });

        $('#commentForm').on('submit', function(e) {
            e.preventDefault();
            runAction(commentAction, { comment: $('#commentText').val() }, $('#commentSubmitBtn'));
        });
    });
</script>
@endsection
