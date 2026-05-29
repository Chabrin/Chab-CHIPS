@extends('layouts.main')

@section('styles')
<style>
.section-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    color: #3598db;
    margin-bottom: 6px;
}
.form-label { font-size: 0.78rem; font-weight: 500; margin-bottom: 3px; }
#ticketsTable td, #ticketsTable th { font-size: 0.8rem; vertical-align: middle; }

.ticket-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.72rem;
    font-weight: 600;
}
.badge-open    { background: #fff3cd; color: #856404; border: 1px solid #ffd666; }
.badge-in_progress { background: #cff4fc; color: #055160; border: 1px solid #9eeaf9; }
.badge-resolved { background: #d1e7dd; color: #0a3622; border: 1px solid #a3cfbb; }
.badge-closed  { background: #e2e3e5; color: #383d41; border: 1px solid #c6c8ca; }

.priority-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 4px;
}
.priority-low    { background: #6c757d; }
.priority-medium { background: #ffc107; }
.priority-high   { background: #fd7e14; }
.priority-urgent { background: #dc3545; }

.stat-card {
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 12px 16px;
    background: #fff;
}
.stat-card .stat-num  { font-size: 1.6rem; font-weight: 700; line-height: 1; color: #1a2e44; }
.stat-card .stat-label { font-size: 0.72rem; color: #6c757d; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px; }
.stat-card .stat-icon  { font-size: 22px; opacity: 0.18; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

    {{-- Page header --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="mb-0">Support Tickets</h6>
        <a href="{{ route('tickets.admin.export') }}"
           class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
            <span class="material-symbols-outlined" style="font-size:15px;">download</span> Export
        </a>
    </div>

    {{-- ── Stats row ── --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-num">{{ $stats['open'] }}</div>
                    <div class="stat-label">Open</div>
                </div>
                <span class="material-symbols-outlined stat-icon text-warning">inbox</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-num">{{ $stats['in_progress'] }}</div>
                    <div class="stat-label">In Progress</div>
                </div>
                <span class="material-symbols-outlined stat-icon text-info">autorenew</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-num">{{ $stats['resolved'] }}</div>
                    <div class="stat-label">Resolved</div>
                </div>
                <span class="material-symbols-outlined stat-icon text-success">check_circle</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-num">{{ $stats['closed'] }}</div>
                    <div class="stat-label">Closed</div>
                </div>
                <span class="material-symbols-outlined stat-icon text-secondary">lock</span>
            </div>
        </div>
    </div>

    {{-- ── Filters card ── --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('tickets.admin.index') }}" id="filterForm">
                <div class="row g-2 align-items-end">

                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control form-control-sm"
                               placeholder="Ticket #, subject, user…" value="{{ request('search') }}">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="open"        {{ request('status') === 'open'        ? 'selected' : '' }}>Open</option>
                            <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                            <option value="resolved"    {{ request('status') === 'resolved'    ? 'selected' : '' }}>Resolved</option>
                            <option value="closed"      {{ request('status') === 'closed'      ? 'selected' : '' }}>Closed</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select form-select-sm">
                            <option value="">All Priorities</option>
                            <option value="low"    {{ request('priority') === 'low'    ? 'selected' : '' }}>Low</option>
                            <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Medium</option>
                            <option value="high"   {{ request('priority') === 'high'   ? 'selected' : '' }}>High</option>
                            <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Assigned To</label>
                        <select name="assigned_to" class="form-select form-select-sm">
                            <option value="">Anyone</option>
                            <option value="unassigned" {{ request('assigned_to') === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
                            @foreach($adminUsers as $admin)
                            <option value="{{ $admin->id }}" {{ request('assigned_to') == $admin->id ? 'selected' : '' }}>
                                {{ $admin->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-1 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <span class="material-symbols-outlined" style="font-size:15px;">search</span>
                        </button>
                        <a href="{{ route('tickets.admin.index') }}" class="btn btn-outline-secondary btn-sm">
                            <span class="material-symbols-outlined" style="font-size:15px;">close</span>
                        </a>
                    </div>

                </div>
            </form>
        </div>
    </div>

    {{-- ── Tickets table ── --}}
    <div class="card">
        <div class="card-header card-no-border d-flex align-items-center justify-content-between py-2">
            <h6 class="mb-0">
                All Tickets
                <span class="badge badge-light-primary ms-1" style="font-size:0.72rem;">{{ $tickets->total() }}</span>
            </h6>
        </div>
        <div class="card-body pt-1 pb-2">
            <div class="table-responsive custom-scrollbar">
                <table class="table table-sm table-hover mb-0" id="ticketsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:100px;">Ticket #</th>
                            <th>Subject</th>
                            <th>Submitted By</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Replies</th>
                            <th>Last Updated</th>
                            <th style="width:60px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tickets as $ticket)
                        <tr>
                            <td class="fw-semibold" style="color:#3598db;">#{{ $ticket->ticket_number }}</td>
                            <td>
                                <div style="max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                    {{ $ticket->subject }}
                                </div>
                                @if($ticket->unread_admin_count > 0)
                                <span class="badge badge-light-danger ms-1" style="font-size:0.68rem;">
                                    {{ $ticket->unread_admin_count }} new
                                </span>
                                @endif
                            </td>
                            <td>{{ $ticket->user->name ?? '—' }}</td>
                            <td>{{ $ticket->category ?? '—' }}</td>
                            <td>
                                <span class="priority-dot priority-{{ $ticket->priority }}"></span>
                                {{ ucfirst($ticket->priority) }}
                            </td>
                            <td>
                                <span class="ticket-badge badge-{{ $ticket->status }}">
                                    {{ ucwords(str_replace('_', ' ', $ticket->status)) }}
                                </span>
                            </td>
                            <td>{{ $ticket->assignedTo->name ?? '<span class="text-muted">—</span>' }}</td>
                            <td class="text-center">{{ $ticket->replies_count }}</td>
                            <td>
                                <span title="{{ $ticket->updated_at->format('d M Y H:i') }}">
                                    {{ $ticket->updated_at->diffForHumans() }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('tickets.admin.show', $ticket->encrypted_id) }}"
                                   class="btn btn-sm btn-outline-primary py-0 px-2"
                                   style="font-size:0.75rem;">
                                    View
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4" style="font-size:0.82rem;">
                                No tickets found matching your filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($tickets->hasPages())
            <div class="d-flex justify-content-end mt-2">
                {{ $tickets->appends(request()->query())->links('pagination::bootstrap-5') }}
            </div>
            @endif

        </div>
    </div>

</div>
</div>
@endsection
