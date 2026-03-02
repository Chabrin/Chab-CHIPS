@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">User Access Logs</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-sm btn-primary" onclick="testAccessLog()">
                            Test Access Log
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" class="mb-4">
                        <div class="row">
                            <div class="col-md-3">
                                <label for="user_id">User ID</label>
                                <input type="number" class="form-control" name="user_id" value="{{ request('user_id') }}" placeholder="Filter by user ID">
                            </div>
                            <div class="col-md-3">
                                <label for="action">Action</label>
                                <select name="action" class="form-control">
                                    <option value="">All Actions</option>
                                    <option value="login" {{ request('action') == 'login' ? 'selected' : '' }}>Login</option>
                                    <option value="logout" {{ request('action') == 'logout' ? 'selected' : '' }}>Logout</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="{{ request('date_from') }}">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to">Date To</label>
                                <input type="date" class="form-control" name="date_to" value="{{ request('date_to') }}">
                            </div>
                            <div class="col-md-2">
                                <label>&nbsp;</label>
                                <div class="d-flex">
                                    <button type="submit" class="btn btn-primary mr-2">Filter</button>
                                    <a href="{{ route('admin.access-logs') }}" class="btn btn-secondary">Clear</a>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- Logs Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>IP Address</th>
                                    <th>Location</th>
                                    <th>Device Info</th>
                                    <th>Session Duration</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                <tr>
                                    <td>{{ $log->id }}</td>
                                    <td>
                                        @if($log->user)
                                        {{ $log->user->username ?? $log->user->email }}
                                        <small class="text-muted d-block">ID: {{ $log->user_id }}</small>
                                        @else
                                        <span class="text-muted">User not found</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $log->action == 'login' ? 'success' : 'warning' }}">
                                            {{ ucfirst($log->action) }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $log->ip_address }}
                                        @if($log->isp)
                                        <small class="text-muted d-block">{{ $log->isp }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $log->location }}</td>
                                    <td>
                                        {{ $log->device_info }}
                                        @if($log->mac_address)
                                        <small class="text-muted d-block">MAC: {{ $log->mac_address }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($log->session_duration)
                                        {{ $log->formatted_session_duration }}
                                        @else
                                        <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($log->action == 'login' && $log->login_at)
                                        {{ $log->login_at->format('Y-m-d H:i:s') }}
                                        @elseif($log->action == 'logout' && $log->logout_at)
                                        {{ $log->logout_at->format('Y-m-d H:i:s') }}
                                        @else
                                        {{ $log->created_at->format('Y-m-d H:i:s') }}
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No access logs found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    @if($logs->hasPages())
                    <div class="d-flex justify-content-center">
                        {{ $logs->appends(request()->query())->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function testAccessLog() {
        fetch('{{ route("admin.access-logs.test") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.message) {
                    alert('Success: ' + data.message);
                    // Reload the page after a short delay to see the new log
                    setTimeout(() => location.reload(), 2000);
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error: ' + error.message);
            });
    }
</script>
@endsection