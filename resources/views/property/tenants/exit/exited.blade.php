@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{--  HEADER                                                                     --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-0 fw-bold" style="color:#1a1a2e;">
            <span class="material-symbols-outlined align-middle me-1" style="font-size:24px;color:#e74c3c;">door_back</span>
            Exited Tenants
        </h4>
        <small class="text-muted">All tenants whose exit has been finalised.</small>
    </div>
    <a href="{{ route('tenant.exit.index') }}" class="btn btn-sm btn-outline-primary">
        <span class="material-symbols-outlined align-middle" style="font-size:15px;">exit_to_app</span>
        Process New Exit
    </a>
</div>

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{--  STATS STRIP                                                                --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
<div class="row g-2 mb-3">
    <div class="col-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-4 fw-bold" style="color:#1a1a2e;">{{ $stats['total'] }}</div>
            <div class="text-muted" style="font-size:0.78rem;">Total Exits</div>
        </div>
    </div>
    <div class="col-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-4 fw-bold text-success">{{ $stats['this_month'] }}</div>
            <div class="text-muted" style="font-size:0.78rem;">This Month</div>
        </div>
    </div>
    <div class="col-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-4 fw-bold text-primary">{{ $stats['with_refund'] }}</div>
            <div class="text-muted" style="font-size:0.78rem;">Deposit Refunds</div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{--  FILTERS                                                                    --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 px-3">
        <form method="GET" action="{{ route('tenant.exit.exited') }}">
            <div class="row g-2 align-items-end">

                <div class="col-12 col-md-3">
                    <label class="form-label mb-1" style="font-size:0.76rem;">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white">
                            <span class="material-symbols-outlined" style="font-size:14px;color:#aaa;">search</span>
                        </span>
                        <input type="text" name="search" class="form-control"
                               placeholder="Name, ref, phone, ID…"
                               value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" style="font-size:0.76rem;">Landlord</label>
                    <div class="position-relative">
                        <input type="text" id="landlordSearch" class="form-control form-control-sm"
                               placeholder="Search landlord…" autocomplete="off"
                               value="{{ $activeLandlordName }}">
                        <input type="hidden" name="landlord_id" id="landlordId"
                               value="{{ request('landlord_id') }}">
                        <div class="autocomplete-dropdown d-none" id="landlordDropdown"></div>
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" style="font-size:0.76rem;">Property</label>
                    <div class="position-relative">
                        <input type="text" id="propertySearch" class="form-control form-control-sm"
                               placeholder="Search property…" autocomplete="off"
                               value="{{ $activePropertyName }}">
                        <input type="hidden" name="property_id" id="propertyId"
                               value="{{ request('property_id') }}">
                        <div class="autocomplete-dropdown d-none" id="propertyDropdown"></div>
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" style="font-size:0.76rem;">Exit Reason</label>
                    <select name="exit_reason" class="form-select form-select-sm">
                        <option value="">All Reasons</option>
                        @foreach(['End of Lease','Voluntary Vacate','Eviction','Transfer','Upgrade / Downgrade','Non-Payment','Other'] as $r)
                            <option value="{{ $r }}" @selected(request('exit_reason') === $r)>{{ $r }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-1" style="min-width:130px;">
                    <label class="form-label mb-1" style="font-size:0.76rem;">From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="{{ request('date_from') }}">
                </div>

                <div class="col-6 col-md-1" style="min-width:130px;">
                    <label class="form-label mb-1" style="font-size:0.76rem;">To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="{{ request('date_to') }}">
                </div>

                <div class="col-12 col-md-auto d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary px-3">
                        <span class="material-symbols-outlined align-middle" style="font-size:14px;">filter_list</span>
                        Filter
                    </button>
                    @if(request()->hasAny(['search','landlord_id','property_id','exit_reason','date_from','date_to']))
                    <a href="{{ route('tenant.exit.exited') }}" class="btn btn-sm btn-outline-secondary">
                        <span class="material-symbols-outlined align-middle" style="font-size:14px;">close</span>
                        Clear
                    </a>
                    @endif
                </div>

            </div>
        </form>
    </div>
</div>

@if(request()->hasAny(['search','landlord_id','property_id','exit_reason','date_from','date_to']))
<div class="mb-2" style="font-size:0.8rem;color:#666;">
    Showing <strong>{{ $exits->total() }}</strong> result{{ $exits->total() !== 1 ? 's' : '' }} matching your filters.
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{--  TABLE                                                                      --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0" style="font-size:0.83rem;">
                <thead style="background:#1a1a2e;color:#fff;">
                    <tr>
                        <th style="width:36px;" class="ps-2"></th>
                        <th style="width:28px;">#</th>
                        <th>Tenant</th>
                        <th>Property / Unit</th>
                        <th>Landlord</th>
                        <th>Reason</th>
                        <th>Notice</th>
                        <th>Exit Date</th>
                        <th class="text-end pe-3">Refund (KES)</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($exits as $exit)

                    {{-- ── MAIN ROW ────────────────────────────────────────── --}}
                    <tr class="exit-row" id="row-{{ $exit->id }}" style="cursor:pointer;"
                        onclick="togglePanel({{ $exit->id }}, this)">
                        <td class="ps-2 text-center align-middle">
                            <span class="material-symbols-outlined chevron-icon"
                                  style="font-size:18px;color:#aaa;vertical-align:middle;">
                                chevron_right
                            </span>
                        </td>
                        <td class="text-muted align-middle">
                            {{ $loop->iteration + ($exits->currentPage() - 1) * $exits->perPage() }}
                        </td>
                        <td class="align-middle">
                            <div class="fw-semibold">{{ $exit->tenant_name }}</div>
                            <div class="text-muted" style="font-size:0.74rem;">
                                {{ $exit->tenant_ref }}
                                @if($exit->tenant_mobile) &bull; {{ $exit->tenant_mobile }} @endif
                            </div>
                        </td>
                        <td class="align-middle">
                            <div>{{ $exit->property_name }}</div>
                            <div class="text-muted" style="font-size:0.74rem;">Unit: {{ $exit->unit_name }}</div>
                        </td>
                        <td class="align-middle" style="font-size:0.8rem;">
                            {{ $exit->landlord_name ?? '—' }}
                        </td>
                        <td class="align-middle">
                            <span class="badge rounded-pill
                                @if($exit->exit_reason === 'Eviction')          bg-danger
                                @elseif($exit->exit_reason === 'Non-Payment')   bg-warning text-dark
                                @elseif($exit->exit_reason === 'End of Lease')  bg-secondary
                                @elseif($exit->exit_reason === 'Transfer')      bg-info text-dark
                                @else                                            bg-light text-dark border
                                @endif" style="font-size:0.71rem;">
                                {{ $exit->exit_reason }}
                            </span>
                        </td>
                        <td class="align-middle" style="font-size:0.78rem;">
                            {{ $exit->vacate_notice_date ? \Carbon\Carbon::parse($exit->vacate_notice_date)->format('d M Y') : '—' }}
                        </td>
                        <td class="align-middle">
                            @if($exit->actual_exit_date)
                                <span class="fw-semibold">{{ \Carbon\Carbon::parse($exit->actual_exit_date)->format('d M Y') }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end pe-3 align-middle">
                            @if($exit->total_refunded > 0)
                                <span class="text-success fw-semibold">{{ number_format($exit->total_refunded, 2) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>

                    {{-- ── NESTED DETAIL PANEL ─────────────────────────────── --}}
                    <tr class="panel-row d-none" id="panel-{{ $exit->id }}">
                        <td colspan="9" class="p-0">
                            <div class="exit-panel">

                                {{-- Loading --}}
                                <div class="panel-loading text-center py-4 text-muted" style="font-size:0.82rem;">
                                    <div class="spinner-border spinner-border-sm me-1 text-secondary"></div>
                                    Loading exit details…
                                </div>

                                {{-- Error --}}
                                <div class="panel-error d-none text-danger px-4 py-3" style="font-size:0.82rem;">
                                    Failed to load exit detail. Please try again.
                                </div>

                                {{-- Content --}}
                                <div class="panel-content d-none" style="padding:12px 20px 20px;">

                                    {{-- Identity + meta bar --}}
                                    <div class="pb-2 mb-3 border-bottom d-flex justify-content-between align-items-start flex-wrap gap-2">
                                        <div>
                                            <div class="fw-bold" style="font-size:0.95rem;color:#1a1a2e;" data-field="t-name"></div>
                                            <div class="text-muted" style="font-size:0.75rem;">
                                                <span data-field="t-ref"></span>
                                                &bull; <span data-field="t-mobile"></span>
                                                &bull; <span data-field="t-email"></span>
                                            </div>
                                            <div style="font-size:0.78rem;margin-top:3px;">
                                                <span class="material-symbols-outlined align-middle" style="font-size:13px;color:#aaa;">apartment</span>
                                                <strong data-field="ex-property"></strong> &mdash; Unit <strong data-field="ex-unit"></strong>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-3 flex-wrap" style="font-size:0.78rem;text-align:right;">
                                            <div>
                                                <div class="text-muted">Notice Date</div>
                                                <div class="fw-semibold" data-field="ex-notice"></div>
                                            </div>
                                            <div>
                                                <div class="text-muted">Intended Exit</div>
                                                <div class="fw-semibold" data-field="ex-intended"></div>
                                            </div>
                                            <div>
                                                <div class="text-muted">Actual Exit</div>
                                                <div class="fw-semibold" style="color:#e74c3c;" data-field="ex-actual"></div>
                                            </div>
                                            <div>
                                                <div class="text-muted">Finalised By</div>
                                                <div class="fw-semibold" data-field="ex-finalised-by"></div>
                                                <div style="font-size:0.7rem;color:#aaa;" data-field="ex-finalised-at"></div>
                                            </div>
                                            <div>
                                                <div class="text-muted" style="font-size:0.7rem;">Closing Balance</div>
                                                <div class="fw-bold" style="font-size:1.05rem;" data-field="closing-balance"></div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Notes --}}
                                    <div class="d-none mb-3" data-field="notes-wrap">
                                        <div class="row g-2">
                                            <div class="col-md-6 d-none" data-field="exit-notes-wrap">
                                                <div class="section-label">Exit Notes</div>
                                                <div class="p-2 bg-white border rounded" style="font-size:0.79rem;" data-field="ex-exit-notes"></div>
                                            </div>
                                            <div class="col-md-6 d-none" data-field="final-notes-wrap">
                                                <div class="section-label">Final / Closing Notes</div>
                                                <div class="p-2 bg-white border rounded" style="font-size:0.79rem;" data-field="ex-final-notes"></div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Four data inner-cards --}}
                                    <div class="row g-3">

                                        {{-- INSPECTION --}}
                                        <div class="col-12 col-xl-6">
                                            <div class="inner-card">
                                                <div class="inner-card-header">
                                                    <span class="material-symbols-outlined">checklist</span>
                                                    Unit Inspection
                                                </div>
                                                <div class="inner-card-body" data-field="insp-body">
                                                    <div class="text-muted text-center py-2" style="font-size:0.8rem;">No inspection recorded.</div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- STATEMENT --}}
                                        <div class="col-12 col-xl-6">
                                            <div class="inner-card">
                                                <div class="inner-card-header d-flex justify-content-between align-items-center">
                                                    <span>
                                                        <span class="material-symbols-outlined">receipt_long</span>
                                                        Account Statement
                                                    </span>
                                                    <span class="badge px-2 py-1"
                                                          style="font-size:0.7rem;background:#1a1a2e;"
                                                          data-field="stmt-balance-badge">—</span>
                                                </div>
                                                <div class="inner-card-body p-0" data-field="stmt-body">
                                                    <div class="text-muted text-center py-2" style="font-size:0.8rem;padding:10px;">No transactions found.</div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- DEPOSIT REFUNDS --}}
                                        <div class="col-12 col-md-6">
                                            <div class="inner-card">
                                                <div class="inner-card-header">
                                                    <span class="material-symbols-outlined">payments</span>
                                                    Deposit Refunds
                                                </div>
                                                <div class="inner-card-body p-0" data-field="refund-body">
                                                    <div class="text-muted text-center py-2" style="font-size:0.8rem;padding:10px;">No refunds recorded.</div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- COMMUNICATIONS --}}
                                        <div class="col-12 col-md-6">
                                            <div class="inner-card">
                                                <div class="inner-card-header">
                                                    <span class="material-symbols-outlined">mark_email_read</span>
                                                    Communications
                                                </div>
                                                <div class="inner-card-body" style="max-height:280px;overflow-y:auto;" data-field="comms-body">
                                                    <div class="text-muted text-center py-2" style="font-size:0.8rem;">No communications logged.</div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>{{-- /.row --}}
                                </div>{{-- /.panel-content --}}
                            </div>{{-- /.exit-panel --}}
                        </td>
                    </tr>

                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">
                            <span class="material-symbols-outlined d-block mb-2" style="font-size:40px;color:#ccc;">door_back</span>
                            No exited tenants found{{ request()->hasAny(['search','landlord_id','property_id','exit_reason','date_from','date_to']) ? ' matching your filters' : '' }}.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($exits->hasPages())
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top" style="font-size:0.8rem;">
            <div class="text-muted">
                Showing {{ $exits->firstItem() }}–{{ $exits->lastItem() }} of {{ $exits->total() }} exits
            </div>
            {{ $exits->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
        @endif

    </div>
</div>

</div>{{-- /.container-fluid --}}
</div>{{-- /.page-body --}}


{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{--  STYLES                                                                     --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
<style>
.material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 300,'GRAD' 0,'opsz' 20; }

/* Table row states */
.exit-row:hover td { background:#f0f4ff !important; }
.exit-row.is-open td { background:#e8edf8 !important; }
.exit-row.is-open .chevron-icon { transform:rotate(90deg); color:#1a1a2e !important; }
.exit-row .chevron-icon { transition:transform .2s ease, color .2s ease; display:block; }

/* Panel */
.exit-panel {
    background: #f4f6fb;
    border-top: 2px solid #1a1a2e;
    border-bottom: 2px solid #dee2e6;
    animation: slideDown .15s ease;
}
@keyframes slideDown {
    from { opacity:0; transform:translateY(-4px); }
    to   { opacity:1; transform:translateY(0); }
}

/* Section label (used for notes) */
.section-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #999;
    margin-bottom: 5px;
}

/* Inner cards */
.inner-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.inner-card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 7px 12px;
    font-size: 0.76rem;
    font-weight: 700;
    color: #1a1a2e;
    display: flex;
    align-items: center;
    gap: 5px;
    flex-shrink: 0;
}
.inner-card-header .material-symbols-outlined { font-size:14px; }
.inner-card-body {
    padding: 10px 12px;
    font-size: 0.8rem;
    flex: 1;
}

/* Condition badges */
.cond-badge { font-size:0.68rem; font-weight:700; padding:2px 6px; border-radius:20px; }

/* Statement colouring */
.statement-dr { color:#e74c3c; }
.statement-cr { color:#27ae60; }

/* Autocomplete filter dropdowns */
.autocomplete-dropdown {
    position: absolute;
    left: 0; right: 0; top: 100%;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0 0 6px 6px;
    z-index: 9999;
    max-height: 220px;
    overflow-y: auto;
    box-shadow: 0 4px 12px rgba(0,0,0,.12);
}
.autocomplete-item {
    padding: 7px 12px;
    cursor: pointer;
    font-size: 0.82rem;
    border-bottom: 1px solid #f0f0f0;
}
.autocomplete-item:hover { background: #f8f9fa; }
.autocomplete-item .sub  { color: #999; font-size: 0.73rem; }
.comm-card .comm-meta { color:#999; font-size:0.7rem; margin-top:3px; }
.comm-card.email-card { border-left:3px solid #3498db; }
.comm-card.sms-card   { border-left:3px solid #2ecc71; }
.comm-card.both-card  { border-left:3px solid #9b59b6; }
</style>


{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{--  JAVASCRIPT                                                                  --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
'use strict';

const DETAIL_URL  = "{{ url('tenant-exit/exited-detail') }}";
const panelCache  = {}; // exitId → true (prevents re-fetch on re-open)

/* ─────────────────────────────────────────────────────────────────────────────
   FILTER AUTOCOMPLETES (Property & Landlord)
───────────────────────────────────────────────────────────────────────────── */
const SEARCH_PROPERTIES_URL = "{{ route('tenant.exit.search.properties') }}";
const SEARCH_LANDLORDS_URL  = "{{ route('tenant.exit.search.landlords') }}";

function makeAutocomplete(inputId, dropdownId, hiddenId, searchUrl, labelKey, subKey) {
    const input    = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    const hidden   = document.getElementById(hiddenId);
    let   timer    = null;

    input.addEventListener('input', function () {
        const val = input.value.trim();
        clearTimeout(timer);

        // If user clears the field, also clear the hidden id
        if (!val) {
            hidden.value = '';
            dropdown.classList.add('d-none');
            dropdown.innerHTML = '';
            return;
        }

        if (val.length < 2) return;

        timer = setTimeout(function () {
            $.get(searchUrl, { search: val }, function (data) {
                dropdown.innerHTML = '';
                const items = data[Object.keys(data).find(k => Array.isArray(data[k]))] || [];

                if (!items.length) {
                    dropdown.innerHTML = '<div class="autocomplete-item text-muted">No results found.</div>';
                    dropdown.classList.remove('d-none');
                    return;
                }

                items.forEach(function (item) {
                    const div = document.createElement('div');
                    div.className = 'autocomplete-item';
                    div.innerHTML = '<div>' + item[labelKey] + '</div>' +
                                    (subKey && item[subKey] ? '<div class="sub">' + item[subKey] + '</div>' : '');
                    div.addEventListener('mousedown', function (e) {
                        e.preventDefault(); // prevent blur firing before click
                        input.value  = item[labelKey];
                        hidden.value = item.id;
                        dropdown.classList.add('d-none');
                    });
                    dropdown.appendChild(div);
                });
                dropdown.classList.remove('d-none');
            });
        }, 280);
    });

    // Close on blur
    input.addEventListener('blur', function () {
        setTimeout(function () { dropdown.classList.add('d-none'); }, 150);
    });

    // If user focuses and then clears manually, allow re-search
    input.addEventListener('focus', function () {
        if (input.value.trim().length >= 2 && !hidden.value) {
            input.dispatchEvent(new Event('input'));
        }
    });
}

makeAutocomplete('propertySearch', 'propertyDropdown', 'propertyId',
    SEARCH_PROPERTIES_URL, 'property_name', 'reference_number');

makeAutocomplete('landlordSearch', 'landlordDropdown', 'landlordId',
    SEARCH_LANDLORDS_URL, 'names', 'reference_number');

function togglePanel(exitId, row) {
    const panelRow = document.getElementById('panel-' + exitId);
    const isOpen   = !panelRow.classList.contains('d-none');

    // Close any other open panel
    document.querySelectorAll('.exit-row.is-open').forEach(function (r) {
        if (r.id !== 'row-' + exitId) {
            document.getElementById('panel-' + r.id.replace('row-', '')).classList.add('d-none');
            r.classList.remove('is-open');
        }
    });

    if (isOpen) {
        panelRow.classList.add('d-none');
        row.classList.remove('is-open');
        return;
    }

    panelRow.classList.remove('d-none');
    row.classList.add('is-open');

    if (panelCache[exitId]) return; // already loaded

    $.get(DETAIL_URL + '/' + exitId, function (data) {
        if (!data.success) { showError(exitId); return; }
        panelCache[exitId] = true;
        renderPanel(exitId, data);
    }).fail(function () { showError(exitId); });
}

function showError(exitId) {
    const ep = document.getElementById('panel-' + exitId).querySelector('.exit-panel');
    ep.querySelector('.panel-loading').classList.add('d-none');
    ep.querySelector('.panel-error').classList.remove('d-none');
}

/* ─────────────────────────────────────────────────────────────────────────────
   RENDER
───────────────────────────────────────────────────────────────────────────── */
function renderPanel(exitId, data) {
    const ep      = document.getElementById('panel-' + exitId).querySelector('.exit-panel');
    const content = ep.querySelector('.panel-content');

    const t      = data.tenant;
    const ex     = data.exit;
    const insp   = data.inspection;
    const stmt   = data.statement      || [];
    const refunds= data.refunds        || [];
    const comms  = data.communications || [];
    const bal    = parseFloat(data.closing_balance);
    const balFmt = (bal >= 0 ? 'CR ' : 'DR ') +
                   Math.abs(bal).toLocaleString('en-KE', {minimumFractionDigits:2});

    /* helpers */
    function set(f, v)   { const e = content.querySelector('[data-field="'+f+'"]'); if (e) e.textContent = v||''; }
    function html(f, v)  { const e = content.querySelector('[data-field="'+f+'"]'); if (e) e.innerHTML  = v||''; }
    function show(f)     { const e = content.querySelector('[data-field="'+f+'"]'); if (e) e.classList.remove('d-none'); }

    /* ── identity ─────────────────────────────────────────────────────── */
    set('t-name',    t.names);
    set('t-ref',     t.reference_number);
    set('t-mobile',  t.mobile_number);
    set('t-email',   t.email_address);
    set('ex-property',    ex.property_name);
    set('ex-unit',        ex.unit_name);
    set('ex-notice',      ex.vacate_notice_date);
    set('ex-intended',    ex.intended_exit_date);
    set('ex-actual',      ex.actual_exit_date);
    set('ex-finalised-by',ex.finalised_by_name);
    set('ex-finalised-at',ex.finalised_at);

    const balEl = content.querySelector('[data-field="closing-balance"]');
    if (balEl) { balEl.textContent = balFmt; balEl.style.color = bal >= 0 ? '#27ae60' : '#e74c3c'; }

    /* ── notes ───────────────────────────────────────────────────────── */
    let hasNotes = false;
    if (ex.exit_notes)  { set('ex-exit-notes',  ex.exit_notes);  show('exit-notes-wrap');  hasNotes = true; }
    if (ex.final_notes) { set('ex-final-notes', ex.final_notes); show('final-notes-wrap'); hasNotes = true; }
    if (hasNotes) show('notes-wrap');

    /* ── 1. INSPECTION ───────────────────────────────────────────────── */
    if (insp) {
        const badge = function (val) {
            if (!val || val === '-') return '<span class="text-muted">—</span>';
            const cls = {
                'Excellent': 'bg-success',
                'Good':      'bg-success opacity-75',
                'Fair':      'bg-warning text-dark',
                'Poor':      'bg-danger opacity-75',
                'Very Poor': 'bg-danger'
            }[val] || 'bg-secondary';
            return '<span class="badge cond-badge ' + cls + '">' + val + '</span>';
        };

        const condRows = [
            ['Overall', insp.overall_condition],   ['Walls',      insp.walls_condition],
            ['Floors',  insp.floors_condition],     ['Ceiling',    insp.ceiling_condition],
            ['Doors',   insp.doors_condition],       ['Windows',    insp.windows_condition],
            ['Plumbing',insp.plumbing_condition],   ['Electrical', insp.electrical_condition],
        ].map(function (p) {
            return '<div class="d-flex justify-content-between align-items-center border-bottom py-1 px-1">' +
                   '<span class="text-muted">' + p[0] + '</span>' + badge(p[1]) + '</div>';
        }).join('');

        const extras =
            '<div class="d-flex gap-3 pt-2 mt-1 border-top flex-wrap" style="font-size:0.77rem;">' +
            '<div><span class="text-muted">Keys</span><br><strong>' + (insp.keys_returned||'—') + '</strong></div>' +
            (insp.damage_amount  ? '<div><span class="text-muted">Damage</span><br><strong class="text-danger">KES ' + Number(insp.damage_amount ).toLocaleString('en-KE',{minimumFractionDigits:2}) + '</strong></div>' : '') +
            (insp.cleaning_cost  ? '<div><span class="text-muted">Cleaning</span><br><strong>KES ' + Number(insp.cleaning_cost).toLocaleString('en-KE',{minimumFractionDigits:2}) + '</strong></div>' : '') +
            '</div>' +
            (insp.damage_description ? '<div class="text-muted mt-1" style="font-size:0.75rem;">' + insp.damage_description + '</div>' : '') +
            (insp.general_notes      ? '<div class="text-muted mt-1" style="font-size:0.75rem;">' + insp.general_notes + '</div>' : '');

        const photos = (insp.photo_paths && insp.photo_paths.length)
            ? '<div class="d-flex flex-wrap gap-1 mt-2">' +
              insp.photo_paths.map(function (p) {
                  return '<a href="/storage/'+p+'" target="_blank">' +
                         '<img src="/storage/'+p+'" style="width:52px;height:52px;object-fit:cover;border-radius:4px;border:1px solid #dee2e6;"></a>';
              }).join('') + '</div>'
            : '';

        html('insp-body',
            '<div style="font-size:0.76rem;color:#888;margin-bottom:6px;">' +
            'Date: <strong>' + (insp.inspection_date||'—') + '</strong>' +
            (insp.inspected_by_name ? ' &bull; By: <strong>' + insp.inspected_by_name + '</strong>' : '') +
            '</div>' + condRows + extras + photos
        );
    }

    /* ── 2. STATEMENT ────────────────────────────────────────────────── */
    const stmtBadge = content.querySelector('[data-field="stmt-balance-badge"]');
    if (stmtBadge) { stmtBadge.textContent = balFmt; stmtBadge.style.background = bal >= 0 ? '#27ae60' : '#e74c3c'; }

    if (stmt.length) {
        const rows = stmt.map(function (e) {
            const neg = parseFloat(e.balance) < 0;
            return '<tr>' +
                '<td>' + e.date_formatted + '</td>' +
                '<td>' + e.reference + '</td>' +
                '<td>' + e.narration + '</td>' +
                '<td class="text-end statement-dr">' + e.debit_formatted + '</td>' +
                '<td class="text-end statement-cr">' + e.credit_formatted + '</td>' +
                '<td class="text-end fw-bold" style="color:' + (neg?'#e74c3c':'#27ae60') + '">' + e.balance_formatted + '</td>' +
                '</tr>';
        }).join('');
        html('stmt-body',
            '<div style="max-height:260px;overflow-y:auto;">' +
            '<table class="table table-sm table-bordered mb-0" style="font-size:0.74rem;">' +
            '<thead class="table-dark sticky-top"><tr>' +
            '<th>Date</th><th>Ref</th><th>Narration</th>' +
            '<th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th>' +
            '</tr></thead><tbody>' + rows + '</tbody></table></div>'
        );
    }

    /* ── 3. DEPOSIT REFUNDS ──────────────────────────────────────────── */
    if (refunds.length) {
        const total = refunds.reduce(function (s, r) { return s + parseFloat(r.refund_amount||0); }, 0);
        const rows  = refunds.map(function (r) {
            return '<tr>' +
                '<td>' + (r.refunded_at_formatted||'-') + '</td>' +
                '<td class="text-end fw-semibold text-success">' + Number(r.refund_amount).toLocaleString('en-KE',{minimumFractionDigits:2}) + '</td>' +
                '<td>' + (r.refunded_by_name||'-') + '</td>' +
                '<td class="text-muted">' + (r.refund_notes||'—') + '</td>' +
                '</tr>';
        }).join('');
        html('refund-body',
            '<table class="table table-sm table-bordered mb-0" style="font-size:0.77rem;">' +
            '<thead class="table-light"><tr><th>Date</th><th class="text-end">Amount (KES)</th><th>By</th><th>Notes</th></tr></thead>' +
            '<tbody>' + rows + '</tbody>' +
            '<tfoot class="table-light"><tr><td class="fw-bold">Total</td>' +
            '<td class="text-end fw-bold text-success">' + total.toLocaleString('en-KE',{minimumFractionDigits:2}) + '</td>' +
            '<td colspan="2"></td></tr></tfoot></table>'
        );
    }

    /* ── 4. COMMUNICATIONS ───────────────────────────────────────────── */
    if (comms.length) {
        html('comms-body', comms.map(function (c) {
            const cls  = c.comm_type === 'Email' ? 'email-card' : c.comm_type === 'SMS' ? 'sms-card' : 'both-card';
            const prev = c.message_body.substring(0, 130) + (c.message_body.length > 130 ? '…' : '');
            return '<div class="comm-card ' + cls + '">' +
                '<div class="d-flex justify-content-between align-items-start">' +
                '<strong>' + (c.subject || c.comm_type + ' Message') + '</strong>' +
                '<span class="badge bg-light text-dark border" style="font-size:0.67rem;">' + c.comm_type + '</span></div>' +
                '<div style="color:#444;margin-top:3px;">' + prev + '</div>' +
                '<div class="comm-meta">By ' + (c.sent_by_name||'-') + ' &bull; ' + (c.sent_at ? new Date(c.sent_at).toLocaleDateString() : '-') + '</div>' +
                '</div>';
        }).join(''));
    }

    /* ── show content ────────────────────────────────────────────────── */
    ep.querySelector('.panel-loading').classList.add('d-none');
    content.classList.remove('d-none');
}
</script>
@endsection