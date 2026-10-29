@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h5 class="mb-0">Live Training Sessions & Workshops</h5>
                <div class="ms-auto d-flex align-items-center gap-2">
                    
                    <!-- NEW: Back Button to Main Training Index -->
                    <a href="{{ route('hr.training.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                        <span class="material-symbols-outlined me-1">arrow_back</span> Back to Training
                    </a>

                    @if($user->hasPermission('read_staff'))
                    <a href="{{ route('hr.training.sessions.report') }}" class="btn btn-light btn-sm">Attendance Report</a>
                    @endif

                    @if($user->hasPermission('update_staff'))
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createSessionModal">Schedule Session</button>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Trainer</th>
                                <th>Location / Link</th>
                                <th>Scheduled Date</th>
                                <th>Mandatory</th>
                                <th>My Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sessions as $session)
                            @php
                                $attendance = $myAttendances->get($session->id);
                                $isAttended = $attendance && $attendance->status === 'attended';
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $session->title }}</strong>
                                    @if($session->description)<div class="text-muted small">{{ $session->description }}</div>@endif
                                </td>
                                <td>{{ $session->trainer ?? 'HR Department' }}</td>
                                <td>
                                    @if($session->location_or_link)
                                        <a href="{{ $session->location_or_link }}" target="_blank">Access Link / Venue</a>
                                    @else
                                        TBA
                                    @endif
                                </td>
                                <td>{{ $session->scheduled_at->format('d M Y, H:i') }}</td>
                                <td>
                                    @if($session->is_mandatory)<span class="badge bg-danger">Mandatory</span>@else<span class="badge bg-secondary">Optional</span>@endif
                                </td>
                                <td>
                                    @if($isAttended)
                                        <span class="badge bg-success">Attended</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Invited / Pending</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if(!$isAttended)
                                    <form action="{{ route('hr.training.sessions.attend', $session->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="rating" value="5">
                                        <button type="submit" class="btn btn-success btn-sm">Mark Attended</button>
                                    </form>
                                    @else
                                    <span class="text-muted small">Completed</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No live training sessions scheduled yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@if($user->hasPermission('update_staff'))
<div class="modal fade" id="createSessionModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" action="{{ route('hr.training.sessions.store') }}" method="POST">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Schedule Training Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Session Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="title" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Trainer Name</label>
                    <input type="text" class="form-control" name="trainer">
                </div>
                <div class="mb-3">
                    <label class="form-label">Location or Meeting Link (Zoom/Teams)</label>
                    <input type="text" class="form-control" name="location_or_link">
                </div>
                <div class="mb-3">
                    <label class="form-label">Scheduled Date & Time <span class="text-danger">*</span></label>
                    <input type="datetime-local" class="form-control" name="scheduled_at" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Duration (Minutes)</label>
                    <input type="number" class="form-control" name="duration_minutes" value="60">
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="is_mandatory" name="is_mandatory" value="1">
                    <label class="form-check-label" for="is_mandatory">Mandatory Session</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Schedule &amp; Invite All</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection