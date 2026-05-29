@extends('layouts.main')

@section('styles')
<style>
.form-label { font-size: 0.78rem; font-weight: 500; margin-bottom: 3px; }

.ticket-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.72rem;
    font-weight: 600;
}
.badge-open        { background: #fff3cd; color: #856404; border: 1px solid #ffd666; }
.badge-in_progress { background: #cff4fc; color: #055160; border: 1px solid #9eeaf9; }
.badge-resolved    { background: #d1e7dd; color: #0a3622; border: 1px solid #a3cfbb; }
.badge-closed      { background: #e2e3e5; color: #383d41; border: 1px solid #c6c8ca; }

.priority-dot { width:7px; height:7px; border-radius:50%; display:inline-block; margin-right:3px; }
.priority-low    { background:#6c757d; }
.priority-medium { background:#ffc107; }
.priority-high   { background:#fd7e14; }
.priority-urgent { background:#dc3545; }

#ticketsTable td, #ticketsTable th { font-size: 0.8rem; vertical-align: middle; }

.empty-state {
    text-align: center;
    padding: 48px 20px;
    color: #9ca3af;
}
.empty-state .material-symbols-outlined { font-size: 48px; display: block; margin-bottom: 8px; opacity: 0.3; }
.empty-state h6 { color: #6c757d; margin-bottom: 4px; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

    {{-- Page header --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="mb-0">My Support Tickets</h6>
        <a href="{{ route('tickets.create') }}"
           class="btn btn-primary btn-sm d-flex align-items-center gap-1">
            <span class="material-symbols-outlined" style="font-size:15px;">add</span> New Ticket
        </a>
    </div>

    {{-- ── Filter bar ── --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('tickets.my') }}">
                <div class="row g-2 align-items-end">

                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control form-control-sm"
                               placeholder="Subject or ticket #…" value="{{ request('search') }}">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="open"        {{ request('status') === 'open'        ? 'selected' : '' }}>Open</option>
                            <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                            <option value="resolved"    {{ request('status') === 'resolved'    ? 'selected' : '' }}>Resolved</option>
                            <option value="closed"      {{ request('status') === 'closed'      ? 'selected' : '' }}>Closed</option>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <span class="material-symbols-outlined" style="font-size:15px;">search</span>
                        </button>
                        <a href="{{ route('tickets.my') }}" class="btn btn-outline-secondary btn-sm">
                            <span class="material-symbols-outlined" style="font-size:15px;">close</span>
                        </a>
                    </div>

                </div>
            </form>
        </div>
    </div>

    {{-- ── Tickets list ── --}}
    <div class="card">
        <div class="card-header card-no-border py-2">
            <h6 class="mb-0">
                Tickets
                <span class="badge badge-light-primary ms-1" style="font-size:0.72rem;">{{ $tickets->total() }}</span>
            </h6>
        </div>
        <div class="card-body pt-1 pb-2">

            @if($tickets->count())
            <div class="table-responsive custom-scrollbar">
                <table class="table table-sm table-hover mb-0" id="ticketsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:100px;">Ticket #</th>
                            <th>Subject</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Replies</th>
                            <th>Last Updated</th>
                            <th style="width:60px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tickets as $ticket)
                        <tr>
                            <td class="fw-semibold" style="color:#3598db;">#{{ $ticket->ticket_number }}</td>
                            <td>
                                <div style="max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                    {{ $ticket->subject }}
                                </div>
                                @if($ticket->unread_user_count > 0)
                                <span class="badge badge-light-primary ms-1" style="font-size:0.68rem;">
                                    {{ $ticket->unread_user_count }} new
                                </span>
                                @endif
                            </td>
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
                            <td class="text-center">{{ $ticket->replies_count }}</td>
                            <td>
                                <span title="{{ $ticket->updated_at->format('d M Y H:i') }}">
                                    {{ $ticket->updated_at->diffForHumans() }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('tickets.show', $ticket->encrypted_id) }}"
                                   class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:0.75rem;">
                                    View
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($tickets->hasPages())
            <div class="d-flex justify-content-end mt-2">
                {{ $tickets->appends(request()->query())->links('pagination::bootstrap-5') }}
            </div>
            @endif

            @else
            <div class="empty-state">
                <span class="material-symbols-outlined">confirmation_number</span>
                <h6>No tickets yet</h6>
                <p style="font-size:0.82rem; margin-bottom:12px;">
                    Haven't had any issues? Great! When you do, submit a ticket and our team will help you out.
                </p>
                <a href="{{ route('tickets.create') }}" class="btn btn-primary btn-sm">
                    <span class="material-symbols-outlined" style="font-size:15px; vertical-align:middle;">add</span> Submit Your First Ticket
                </a>
            </div>
            @endif

        </div>
    </div>

</div>
</div>
@endsection
