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

/* ── Status badges ── */
.ticket-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 0.72rem;
    font-weight: 600;
}
.badge-open        { background: #fff3cd; color: #856404; border: 1px solid #ffd666; }
.badge-in_progress { background: #cff4fc; color: #055160; border: 1px solid #9eeaf9; }
.badge-resolved    { background: #d1e7dd; color: #0a3622; border: 1px solid #a3cfbb; }
.badge-closed      { background: #e2e3e5; color: #383d41; border: 1px solid #c6c8ca; }

.priority-dot { width:8px; height:8px; border-radius:50%; display:inline-block; margin-right:4px; }
.priority-low    { background:#6c757d; }
.priority-medium { background:#ffc107; }
.priority-high   { background:#fd7e14; }
.priority-urgent { background:#dc3545; }

/* ── Thread ── */
.thread-wrap { display:flex; flex-direction:column; gap:12px; }

.thread-msg {
    display: flex;
    gap: 10px;
    align-items: flex-start;
}
.thread-msg.from-admin { flex-direction: row-reverse; }

.thread-avatar {
    width: 32px; height: 32px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; font-weight: 700;
    flex-shrink: 0;
}
.avatar-user  { background: #e8f4fd; color: #1a5f9e; border: 1px solid #c8e0f9; }
.avatar-admin { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }
.avatar-system { background: #f3e5f5; color: #4a148c; border: 1px solid #ce93d8; }

.thread-bubble {
    max-width: 75%;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 0.82rem;
    line-height: 1.5;
    position: relative;
}
.bubble-user   { background: #f0f7ff; border: 1px solid #d0e8fb; }
.bubble-admin  { background: #f0fff4; border: 1px solid #b7e4c7; }
.bubble-system { background: #f8f0ff; border: 1px solid #d9b3f5; font-style: italic; color: #555; }

.thread-meta {
    font-size: 0.68rem;
    color: #9ca3af;
    margin-top: 4px;
}
.from-admin .thread-meta { text-align: right; }

.thread-note { /* internal note styling */
    border-left: 3px solid #fd7e14;
    background: #fff8f0 !important;
    border-color: #ffd599 !important;
}
.note-badge {
    font-size: 0.65rem;
    font-weight: 700;
    background: #fd7e14;
    color: #fff;
    padding: 1px 5px;
    border-radius: 3px;
    margin-left: 4px;
}

/* ── Compose area ── */
.compose-wrap {
    border: 1px solid #dee2e6;
    border-radius: 6px;
    overflow: hidden;
}
.compose-toolbar {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 6px 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.compose-toolbar .btn { padding: 2px 8px; font-size: 0.75rem; }
.compose-body textarea {
    border: none !important;
    border-radius: 0 !important;
    resize: vertical;
    min-height: 100px;
    font-size: 0.82rem;
}
.compose-body textarea:focus { box-shadow: none; }
.compose-footer {
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
    padding: 6px 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

    {{-- Page header --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('tickets.my') }}"
               class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                <span class="material-symbols-outlined" style="font-size:15px;">arrow_back</span> Back
            </a>
            <h6 class="mb-0">
                Ticket <span style="color:#3598db;">#{{ $ticket->ticket_number }}</span>
            </h6>
            <span class="ticket-badge badge-{{ $ticket->status }}">
                {{ ucwords(str_replace('_', ' ', $ticket->status)) }}
            </span>
        </div>
        {{-- Quick action buttons --}}
        @if($ticket->status !== 'closed')
        <div class="d-flex gap-2">
            @if($ticket->status !== 'resolved')
             <span class="btn btn-danger btn-sm d-flex align-items-center gap-1">Unresolved</span>
            @endif
            
        </div>
        @endif
    </div>

    <div class="row g-3">

        {{-- ══════════════════════════════════════
             LEFT — Thread
        ═══════════════════════════════════════ --}}
        <div class="col-lg-8">

            {{-- Original ticket message --}}
            <div class="card mb-3">
                <div class="card-header card-no-border py-2 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0" style="font-size:0.9rem;">{{ $ticket->subject }}</h6>
                    <span class="text-muted" style="font-size:0.72rem;">
                        Opened {{ $ticket->created_at->format('d M Y, H:i') }}
                    </span>
                </div>
                <div class="card-body pb-3">
                    <div class="thread-msg">
                        <div class="thread-avatar avatar-user">
                            {{ strtoupper(substr($ticket->user->name ?? 'U', 0, 2)) }}
                        </div>
                        <div>
                            <div class="thread-bubble bubble-user">
                                {!! nl2br(e($ticket->description)) !!}

                                @if($ticket->attachments->count())
                                <div class="mt-2 pt-2 border-top d-flex flex-wrap gap-1">
                                    @foreach($ticket->attachments->where('reply_id', null) as $att)
                                    <a href="{{ route('tickets.attachment.download', $att->encrypted_id) }}"
                                       class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.72rem;">
                                        <span class="material-symbols-outlined" style="font-size:13px; vertical-align:middle;">attach_file</span>
                                        {{ $att->original_name }}
                                    </a>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                            <div class="thread-meta">
                                <strong>{{ $ticket->user->name ?? 'User' }}</strong> · {{ $ticket->created_at->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Reply thread --}}
            @if($ticket->replies->count())
            <div class="card mb-3">
                <div class="card-header card-no-border py-2">
                    <div class="section-label">Conversation ({{ $ticket->replies->count() }} {{ Str::plural('reply', $ticket->replies->count()) }})</div>
                </div>
                <div class="card-body pb-3">
                    <div class="thread-wrap">
                        @foreach($ticket->replies as $reply)
                        <div class="thread-msg {{ $reply->sender_role === 'admin' ? 'from-admin' : '' }}">
                            <div class="thread-avatar avatar-{{ $reply->sender_role === 'admin' ? 'admin' : ($reply->sender_role === 'system' ? 'system' : 'user') }}">
                                @if($reply->sender_role === 'system')
                                    <span class="material-symbols-outlined" style="font-size:14px;">info</span>
                                @else
                                    {{ strtoupper(substr($reply->sender->name ?? '?', 0, 2)) }}
                                @endif
                            </div>
                            <div>
                                <div class="thread-bubble bubble-{{ $reply->sender_role === 'admin' ? 'admin' : ($reply->sender_role === 'system' ? 'system' : 'user') }} {{ $reply->is_internal_note ? 'thread-note' : '' }}">
                                    @if($reply->is_internal_note)
                                        <span class="note-badge">Internal Note</span>
                                    @endif
                                    {!! nl2br(e($reply->body)) !!}

                                    @if($reply->attachments->count())
                                    <div class="mt-2 pt-2 border-top d-flex flex-wrap gap-1">
                                        @foreach($reply->attachments as $att)
                                        <a href="{{ route('tickets.attachment.download', $att->encrypted_id) }}"
                                           class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.72rem;">
                                            <span class="material-symbols-outlined" style="font-size:13px; vertical-align:middle;">attach_file</span>
                                            {{ $att->original_name }}
                                        </a>
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                                <div class="thread-meta">
                                    <strong>{{ $reply->sender->name ?? ($reply->sender_role === 'system' ? 'System' : 'User') }}</strong>
                                    @if($reply->sender_role === 'admin') <span class="badge badge-light-success" style="font-size:0.62rem;">Support</span> @endif
                                    · {{ $reply->created_at->diffForHumans() }}
                                    · {{ $reply->created_at->format('d M Y H:i') }}
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            {{-- ── Compose reply ── --}}
            @if($ticket->status !== 'closed')
            <div class="card">
                <div class="card-header card-no-border py-2">
                    <div class="section-label">Reply to Ticket</div>
                </div>
                <div class="card-body pb-3">
                    <form enctype="multipart/form-data" id="replyForm">
                        @csrf

                        <div class="compose-wrap mb-2">
                            <div class="compose-toolbar">
                                <div class="form-check form-check-sm mb-0">
                                    <input class="form-check-input" type="checkbox" id="isInternalNote" name="is_internal_note" value="1">
                                    <label class="form-check-label" for="isInternalNote" style="font-size:0.75rem;">
                                        Internal Note <span class="text-muted">(not visible to user)</span>
                                    </label>
                                </div>
                            </div>
                            <div class="compose-body">
                                <textarea class="form-control" id="replyBody" name="body"
                                          placeholder="Type your reply…" rows="5">{{ old('body') }}</textarea>
                                @error('body')
                                <div class="text-danger mt-1" style="font-size:0.75rem;">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="compose-footer">
                                <div>
                                    <label class="btn btn-outline-secondary btn-sm" for="replyAttachment">
                                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">attach_file</span>
                                        Attach
                                    </label>
                                    <input type="file" id="replyAttachment" name="attachments[]" multiple class="d-none">
                                    <span id="attachmentNames" class="ms-2 text-muted" style="font-size:0.72rem;"></span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    {{-- Status change on reply --}}
                                    <select name="change_status" class="form-select form-select-sm" style="width:auto; font-size:0.75rem;">
                                        <option value="">Keep Status</option>
                                        <option value="in_progress" {{ $ticket->status === 'open' ? 'selected' : '' }}>Set In Progress</option>
                                        <option value="resolved">Set Resolved</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                                        <span class="material-symbols-outlined" style="font-size:15px;">send</span> Send Reply
                                    </button>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
            @else
            <div class="alert alert-secondary py-2" style="font-size:0.82rem;">
                <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">lock</span>
                This ticket is <strong>closed</strong>. No further replies can be added.
            </div>
            @endif

        </div>{{-- /col-lg-8 --}}

        {{-- ══════════════════════════════════════
             RIGHT — Ticket details & actions
        ═══════════════════════════════════════ --}}
        <div class="col-lg-4">

            {{-- Ticket Info --}}
            <div class="card mb-3">
                <div class="card-header card-no-border py-2">
                    <div class="section-label">Ticket Details</div>
                </div>
                <div class="card-body pb-3">
                    <dl class="row mb-0" style="font-size:0.82rem; row-gap:6px;">

                        <dt class="col-5 text-muted fw-normal">Ticket #</dt>
                        <dd class="col-7 mb-0 fw-semibold" style="color:#3598db;">#{{ $ticket->ticket_number }}</dd>

                        <dt class="col-5 text-muted fw-normal">Status</dt>
                        <dd class="col-7 mb-0">
                            <span class="ticket-badge badge-{{ $ticket->status }}">
                                {{ ucwords(str_replace('_', ' ', $ticket->status)) }}
                            </span>
                        </dd>

                        <dt class="col-5 text-muted fw-normal">Priority</dt>
                        <dd class="col-7 mb-0">
                            <span class="priority-dot priority-{{ $ticket->priority }}"></span>
                            {{ ucfirst($ticket->priority) }}
                        </dd>

                        <dt class="col-5 text-muted fw-normal">Category</dt>
                        <dd class="col-7 mb-0">{{ $ticket->category ?? '—' }}</dd>

                        <dt class="col-5 text-muted fw-normal">Submitted By</dt>
                        <dd class="col-7 mb-0">{{ $ticket->user->name ?? '—' }}</dd>

                        <dt class="col-5 text-muted fw-normal">Email</dt>
                        <dd class="col-7 mb-0" style="word-break:break-all;">{{ $ticket->user->email ?? '—' }}</dd>

                        <dt class="col-5 text-muted fw-normal">Opened</dt>
                        <dd class="col-7 mb-0">{{ $ticket->created_at->format('d M Y') }}</dd>

                        <dt class="col-5 text-muted fw-normal">Last Updated</dt>
                        <dd class="col-7 mb-0">{{ $ticket->updated_at->diffForHumans() }}</dd>

                    </dl>
                </div>
            </div>


            {{-- Activity log --}}
            <div class="card">
                <div class="card-header card-no-border py-2">
                    <div class="section-label">Activity Log</div>
                </div>
                <div class="card-body pb-2 pt-2">
                    @forelse($ticket->activities as $activity)
                    <div class="d-flex gap-2 mb-2" style="font-size:0.76rem;">
                        <span class="material-symbols-outlined text-muted" style="font-size:14px; margin-top:1px;">radio_button_checked</span>
                        <div>
                            <span>{{ $activity->description }}</span>
                            <div class="text-muted">{{ $activity->created_at->format('d M Y H:i') }}</div>
                        </div>
                    </div>
                    @empty
                    <div class="text-muted" style="font-size:0.78rem;">No activity yet.</div>
                    @endforelse
                </div>
            </div>

        </div>{{-- /col-lg-4 --}}
    </div>

</div>
</div>
@endsection

{{-- ── Response Modal ── --}}
@push('modals')
<div class="modal fade" id="ticketModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width:360px;">
        <div class="modal-content">
            <div class="modal-body text-center py-4 px-4">
                <span class="material-symbols-outlined d-block mb-2" id="ticketModalIcon" style="font-size:40px;"></span>
                <p id="ticketModalMessage" class="mb-3" style="font-size:0.85rem;"></p>
                <button class="btn btn-primary btn-sm px-4" id="ticketModalOk" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
$(document).ready(function () {

    const CSRF = '{{ csrf_token() }}';

    function showModal(icon, iconClass, message, onOk) {
        $('#ticketModalIcon').text(icon).attr('class', 'material-symbols-outlined d-block mb-2 ' + iconClass);
        $('#ticketModalMessage').text(message);
        $('#ticketModalOk').off('click.modal').on('click.modal', function () { if (onOk) onOk(); });
        new bootstrap.Modal(document.getElementById('ticketModal')).show();
    }

    // Show selected filenames
    $('#replyAttachment').on('change', function () {
        $('#attachmentNames').text(Array.from(this.files).map(f => f.name).join(', ') || '');
    });

    // Highlight compose when internal note is checked
    $('#isInternalNote').on('change', function () {
        const ta = $('#replyBody');
        if ($(this).is(':checked')) {
            ta.css('background', '#fff8f0').attr('placeholder', 'Internal note — not visible to the user…');
        } else {
            ta.css('background', '').attr('placeholder', 'Type your reply…');
        }
    });

    // Reply form
    $('#replyForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $(this).find('[type=submit]');
        btn.prop('disabled', true).text('Sending…');

        fetch('{{ route('tickets.admin.reply', $ticket->encrypted_id) }}', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
            body: new FormData(this),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showModal('check_circle', 'text-success', data.message, () => window.location.reload());
            } else {
                showModal('error', 'text-danger', data.message || 'Something went wrong.', null);
            }
        })
        .catch(() => showModal('error', 'text-danger', 'Network error. Please try again.', null))
        .finally(() => btn.prop('disabled', false).html('<span class="material-symbols-outlined" style="font-size:15px;">send</span> Send Reply'));
    });

    // Assign form
    $('#assignForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $(this).find('[type=submit]');
        btn.prop('disabled', true).text('Saving…');

        const body = new FormData(this);
        body.append('_method', 'PATCH');

        fetch('{{ route('tickets.admin.assign', $ticket->encrypted_id) }}', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
            body,
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showModal('check_circle', 'text-success', data.message, null);
            } else {
                showModal('error', 'text-danger', data.message || 'Something went wrong.', null);
            }
        })
        .catch(() => showModal('error', 'text-danger', 'Network error. Please try again.', null))
        .finally(() => btn.prop('disabled', false).text('Save Assignment'));
    });

    // Status buttons (Mark Resolved / Close Ticket) — already native POST forms, swap to fetch
    $('[data-status-form]').on('submit', function (e) {
        e.preventDefault();
        const status = $(this).find('[name=status]').val();
        const label  = status === 'closed' ? 'Close this ticket?' : 'Mark as resolved?';
        if (!confirm(label)) return;

        const body = new FormData(this);
        body.append('_method', 'PATCH');

        fetch('{{ route('tickets.admin.status', $ticket->encrypted_id) }}', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
            body,
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showModal('check_circle', 'text-success', data.message, () => window.location.reload());
            } else {
                showModal('error', 'text-danger', data.message || 'Something went wrong.', null);
            }
        })
        .catch(() => showModal('error', 'text-danger', 'Network error. Please try again.', null));
    });

});
</script>
@endsection