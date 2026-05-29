@extends('layouts.main')

@section('styles')
<style>
.section-label {
    font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.7px; color: #3598db; margin-bottom: 6px;
}
.form-label { font-size: 0.78rem; font-weight: 500; margin-bottom: 3px; }

.ticket-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 4px; font-size: 0.72rem; font-weight: 600;
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

.thread-wrap { display:flex; flex-direction:column; gap:12px; }
.thread-msg  { display:flex; gap:10px; align-items:flex-start; }
.thread-msg.from-admin { flex-direction:row-reverse; }

.thread-avatar {
    width:32px; height:32px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-size:0.75rem; font-weight:700; flex-shrink:0;
}
.avatar-user   { background:#e8f4fd; color:#1a5f9e; border:1px solid #c8e0f9; }
.avatar-admin  { background:#e8f5e9; color:#1b5e20; border:1px solid #a5d6a7; }
.avatar-system { background:#f3e5f5; color:#4a148c; border:1px solid #ce93d8; }

.thread-bubble {
    max-width:75%; padding:10px 14px; border-radius:8px;
    font-size:0.82rem; line-height:1.5;
}
.bubble-user   { background:#f0f7ff; border:1px solid #d0e8fb; }
.bubble-admin  { background:#f0fff4; border:1px solid #b7e4c7; }
.bubble-system { background:#f8f0ff; border:1px solid #d9b3f5; font-style:italic; color:#555; }

.thread-meta { font-size:0.68rem; color:#9ca3af; margin-top:4px; }
.from-admin .thread-meta { text-align:right; }

.thread-note { border-left:3px solid #fd7e14; background:#fff8f0 !important; border-color:#ffd599 !important; }
.note-badge { font-size:0.65rem; font-weight:700; background:#fd7e14; color:#fff; padding:1px 5px; border-radius:3px; margin-left:4px; }

.compose-wrap    { border:1px solid #dee2e6; border-radius:6px; overflow:hidden; }
.compose-toolbar { background:#f8f9fa; border-bottom:1px solid #dee2e6; padding:6px 10px; display:flex; align-items:center; gap:6px; }
.compose-toolbar .btn { padding:2px 8px; font-size:0.75rem; }
.compose-body textarea { border:none !important; border-radius:0 !important; resize:vertical; min-height:100px; font-size:0.82rem; }
.compose-body textarea:focus { box-shadow:none; }
.compose-footer {
    background:#f8f9fa; border-top:1px solid #dee2e6;
    padding:6px 10px; display:flex; align-items:center; justify-content:space-between; gap:8px;
}
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('tickets.admin.index') }}"
               class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                <span class="material-symbols-outlined" style="font-size:15px;">arrow_back</span> Back
            </a>
            <h6 class="mb-0">
                Ticket <span style="color:#3598db;">#{{ $ticket->ticket_number }}</span>
            </h6>
            <span class="ticket-badge badge-{{ $ticket->status }}" id="statusBadge">
                {{ ucwords(str_replace('_', ' ', $ticket->status)) }}
            </span>
        </div>
        @if($ticket->status !== 'closed')
        <div class="d-flex gap-2">
            @if($ticket->status !== 'resolved')
            <button type="button" class="btn btn-success btn-sm d-flex align-items-center gap-1"
                    id="btnMarkResolved"
                    data-url="{{ route('tickets.admin.status', $ticket->encrypted_id) }}"
                    data-status="resolved">
                <span class="material-symbols-outlined" style="font-size:15px;">check_circle</span> Mark Resolved
            </button>
            @endif
            <button type="button" class="btn btn-secondary btn-sm d-flex align-items-center gap-1"
                    id="btnClose"
                    data-url="{{ route('tickets.admin.status', $ticket->encrypted_id) }}"
                    data-status="closed">
                <span class="material-symbols-outlined" style="font-size:15px;">lock</span> Close Ticket
            </button>
        </div>
        @endif
    </div>

    <div class="row g-3">

        {{-- ── Thread ── --}}
        <div class="col-lg-8">

            {{-- Original --}}
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
                                @if($ticket->attachments->where('reply_id', null)->count())
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

            {{-- Replies --}}
            @if($ticket->replies->count())
            <div class="card mb-3">
                <div class="card-header card-no-border py-2">
                    <div class="section-label">
                        Conversation ({{ $ticket->replies->count() }} {{ Str::plural('reply', $ticket->replies->count()) }})
                    </div>
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
                                    @if($reply->sender_role === 'admin')
                                        <span class="badge badge-light-success" style="font-size:0.62rem;">Support</span>
                                    @endif
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

            {{-- Compose --}}
            @if($ticket->status !== 'closed')
            <div class="card">
                <div class="card-header card-no-border py-2">
                    <div class="section-label">Reply to Ticket</div>
                </div>
                <div class="card-body pb-3">
                    <form id="replyForm" enctype="multipart/form-data">
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
                                          placeholder="Type your reply…" rows="5"></textarea>
                                <div id="replyBodyError" class="text-danger px-3 pb-2" style="font-size:0.75rem;"></div>
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
                                    <select name="change_status" class="form-select form-select-sm" style="width:auto; font-size:0.75rem;">
                                        <option value="">Keep Status</option>
                                        <option value="in_progress" {{ $ticket->status === 'open' ? 'selected' : '' }}>Set In Progress</option>
                                        <option value="resolved">Set Resolved</option>
                                    </select>
                                    <button type="submit" id="replyBtn"
                                            class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                                        <span class="reply-label d-flex align-items-center gap-1">
                                            <span class="material-symbols-outlined" style="font-size:15px;">send</span> Send Reply
                                        </span>
                                        <span class="reply-spinner d-none">
                                            <span class="spinner-border spinner-border-sm"></span> Sending…
                                        </span>
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

        </div>

        {{-- ── Sidebar ── --}}
        <div class="col-lg-4">

            {{-- Ticket details --}}
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

            {{-- Assignment --}}
            @if($ticket->status !== 'closed')
            <div class="card mb-3">
                <div class="card-header card-no-border py-2">
                    <div class="section-label">Assignment & Priority</div>
                </div>
                <div class="card-body pb-3">
                    <form id="assignForm">
                        @csrf
                        @method('PATCH')
                        <div class="mb-2">
                            <label class="form-label">Assign To</label>
                            <select name="assigned_to" class="form-select form-select-sm">
                                <option value="">-- Unassigned --</option>
                                @foreach($adminUsers as $admin)
                                <option value="{{ $admin->id }}"
                                        {{ $ticket->assigned_to == $admin->id ? 'selected' : '' }}>
                                    {{ $admin->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select form-select-sm">
                                <option value="low"    {{ $ticket->priority === 'low'    ? 'selected' : '' }}>Low</option>
                                <option value="medium" {{ $ticket->priority === 'medium' ? 'selected' : '' }}>Medium</option>
                                <option value="high"   {{ $ticket->priority === 'high'   ? 'selected' : '' }}>High</option>
                                <option value="urgent" {{ $ticket->priority === 'urgent' ? 'selected' : '' }}>Urgent</option>
                            </select>
                        </div>
                        <button type="submit" id="assignBtn" class="btn btn-primary btn-sm w-100">
                            <span class="assign-label">Save Assignment</span>
                            <span class="assign-spinner d-none">
                                <span class="spinner-border spinner-border-sm"></span>
                            </span>
                        </button>
                    </form>
                </div>
            </div>
            @endif

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

        </div>
    </div>
</div>
</div>

{{-- ── Success Modal ── --}}
<div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width:360px;">
        <div class="modal-content">
            <div class="modal-body text-center py-4 px-4">
                <span class="material-symbols-outlined text-success d-block mb-2" style="font-size:42px;">check_circle</span>
                <h6 class="mb-1" id="successModalTitle">Done</h6>
                <p id="successModalMessage" class="text-muted mb-3" style="font-size:0.82rem;"></p>
                <button id="successModalOk" class="btn btn-primary btn-sm px-4">OK</button>
            </div>
        </div>
    </div>
</div>

{{-- ── Error Modal ── --}}
<div class="modal fade" id="errorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:360px;">
        <div class="modal-content">
            <div class="modal-body text-center py-4 px-4">
                <span class="material-symbols-outlined text-danger d-block mb-2" style="font-size:42px;">error</span>
                <h6 class="mb-1">Error</h6>
                <p id="errorModalMessage" class="text-muted mb-3" style="font-size:0.82rem;"></p>
                <button class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ── Confirm Modal (for status changes) ── --}}
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:360px;">
        <div class="modal-content">
            <div class="modal-body text-center py-4 px-4">
                <span class="material-symbols-outlined text-warning d-block mb-2" style="font-size:42px;">help</span>
                <h6 class="mb-1" id="confirmTitle">Confirm</h6>
                <p id="confirmMessage" class="text-muted mb-3" style="font-size:0.82rem;"></p>
                <div class="d-flex justify-content-center gap-2">
                    <button class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                    <button id="confirmOk" class="btn btn-primary btn-sm px-3">Confirm</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function () {

    const CSRF = '{{ csrf_token() }}';

    // ── Helpers ─────────────────────────────────────────────────────
    function showSuccess(title, message, onOk) {
        $('#successModalTitle').text(title);
        $('#successModalMessage').text(message);
        const modal = new bootstrap.Modal(document.getElementById('successModal'));
        $('#successModalOk').off('click').on('click', function () {
            modal.hide();
            if (onOk) onOk();
        });
        modal.show();
    }

    function showError(msg) {
        $('#errorModalMessage').text(msg);
        new bootstrap.Modal(document.getElementById('errorModal')).show();
    }

    function showConfirm(title, message, onConfirm) {
        $('#confirmTitle').text(title);
        $('#confirmMessage').text(message);
        const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
        $('#confirmOk').off('click').on('click', function () {
            modal.hide();
            onConfirm();
        });
        modal.show();
    }

    function jsonFetch(url, method, body) {
        return fetch(url, {
            method,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
            body,
        }).then(res => res.json().then(data => ({ status: res.status, data })));
    }

    // ── Reply form ───────────────────────────────────────────────────
    $('#replyAttachment').on('change', function () {
        $('#attachmentNames').text(Array.from(this.files).map(f => f.name).join(', '));
    });

    $('#isInternalNote').on('change', function () {
        const ta = $('#replyBody');
        if ($(this).is(':checked')) {
            ta.css('background', '#fff8f0').attr('placeholder', 'Internal note — not visible to the user…');
        } else {
            ta.css('background', '').attr('placeholder', 'Type your reply…');
        }
    });

    $('#replyForm').on('submit', function (e) {
        e.preventDefault();
        $('#replyBodyError').text('');
        $('#replyBody').removeClass('is-invalid');

        const formData = new FormData(this);

        $('#replyBtn .reply-label').addClass('d-none');
        $('#replyBtn .reply-spinner').removeClass('d-none');
        $('#replyBtn').prop('disabled', true);

        jsonFetch('{{ route('tickets.admin.reply', $ticket->encrypted_id) }}', 'POST', formData)
            .then(({ status, data }) => {
                if (data.success) {
                    const isNote = $('#isInternalNote').is(':checked');
                    showSuccess(
                        isNote ? 'Note Added' : 'Reply Sent',
                        data.message,
                        () => window.location.reload()
                    );
                } else if (status === 422 && data.errors?.body) {
                    $('#replyBodyError').text(data.errors.body[0]);
                    $('#replyBody').addClass('is-invalid');
                } else {
                    showError(data.message || 'An unexpected error occurred.');
                }
            })
            .catch(() => showError('Network error. Please try again.'))
            .finally(() => {
                $('#replyBtn .reply-label').removeClass('d-none');
                $('#replyBtn .reply-spinner').addClass('d-none');
                $('#replyBtn').prop('disabled', false);
            });
    });

    // ── Status buttons (Resolve / Close) ────────────────────────────
    function doStatusChange(url, status, confirmMsg) {
        showConfirm(
            status === 'closed' ? 'Close Ticket' : 'Mark Resolved',
            confirmMsg,
            function () {
                const body = new FormData();
                body.append('_method', 'PATCH');
                body.append('status', status);

                jsonFetch(url, 'POST', body)
                    .then(({ data }) => {
                        if (data.success) {
                            showSuccess('Status Updated', data.message, () => window.location.reload());
                        } else {
                            showError(data.message || 'Failed to update status.');
                        }
                    })
                    .catch(() => showError('Network error. Please try again.'));
            }
        );
    }

    $('#btnMarkResolved').on('click', function () {
        doStatusChange(
            $(this).data('url'),
            'resolved',
            'Mark this ticket as resolved? The user will be notified.'
        );
    });

    $('#btnClose').on('click', function () {
        doStatusChange(
            $(this).data('url'),
            'closed',
            'Close this ticket? The user will be notified and no further replies can be added.'
        );
    });

    // ── Assign form ──────────────────────────────────────────────────
    $('#assignForm').on('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('_method', 'PATCH');

        $('#assignBtn .assign-label').addClass('d-none');
        $('#assignBtn .assign-spinner').removeClass('d-none');
        $('#assignBtn').prop('disabled', true);

        jsonFetch('{{ route('tickets.admin.assign', $ticket->encrypted_id) }}', 'POST', formData)
            .then(({ data }) => {
                if (data.success) {
                    showSuccess('Assignment Saved', data.message, null);
                } else {
                    showError(data.message || 'Failed to save assignment.');
                }
            })
            .catch(() => showError('Network error. Please try again.'))
            .finally(() => {
                $('#assignBtn .assign-label').removeClass('d-none');
                $('#assignBtn .assign-spinner').addClass('d-none');
                $('#assignBtn').prop('disabled', false);
            });
    });

});
</script>
@endsection