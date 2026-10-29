@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h5 class="mb-0">Session Attendance &amp; Compliance Report</h5>
                <div class="ms-auto">
                    <a href="{{ route('hr.training.sessions.index') }}" class="btn btn-light btn-sm">Back to Sessions</a>
                </div>
            </div>
            <div class="card-body">
                <!-- Filters Form -->
                <form method="GET" action="{{ route('hr.training.sessions.report') }}" class="row g-3 mb-4">
                    <div class="col-md-4">
                        <select name="session_id" class="form-select">
                            <option value="">All Training Sessions</option>
                            @foreach($sessions as $s)
                                <option value="{{ $s->id }}" {{ request('session_id') == $s->id ? 'selected' : '' }}>{{ $s->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="attended" {{ request('status') == 'attended' ? 'selected' : '' }}>Attended</option>
                            <option value="invited" {{ request('status') == 'invited' ? 'selected' : '' }}>Invited / Pending</option>
                        </select>
                    </div>
                    <div class="col-md-5 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="{{ route('hr.training.sessions.report') }}" class="btn btn-light">Reset</a>
                        <button type="submit" name="export" value="csv" class="btn btn-success ms-auto">Export CSV</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Staff Member</th>
                                <th>Session Title</th>
                                <th>Scheduled Date</th>
                                <th>Status</th>
                                <th>Attended At</th>
                                <th>Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reports as $row)
                            <tr>
                                <td>
                                    <strong>{{ $row->staff->name ?? 'N/A' }}</strong>
                                    <div class="text-muted small">{{ $row->staff->email ?? 'N/A' }}</div>
                                </td>
                                <td>{{ $row->session->title ?? 'N/A' }}</td>
                                <td>{{ $row->session->scheduled_at?->format('d M Y, H:i') ?? 'N/A' }}</td>
                                <td>
                                    @if($row->status === 'attended')
                                        <span class="badge bg-success">Attended</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Invited</span>
                                    @endif
                                </td>
                                <td>{{ $row->attended_at?->format('d M Y, H:i') ?? 'Not yet' }}</td>
                                <td>{{ $row->rating ? $row->rating . '/5' : 'N/A' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No attendance records found matching criteria.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection