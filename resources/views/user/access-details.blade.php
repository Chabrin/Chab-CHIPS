@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">User Access Details</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button onclick="window.print()" class="btn btn-outline-primary btn-sm">
                                <span class="material-symbols-outlined me-1">print</span>
                                Print
                            </button>
                            <button onclick="window.close()" class="btn btn-outline-secondary btn-sm">
                                <span class="material-symbols-outlined me-1">close</span>
                                Close
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <h5 style="color: #3598db;">User Information</h5>
                                <div class="d-flex align-items-center gap-4 flex-wrap mt-2" style="font-size: 13px;">
                                    <div><strong>Username:</strong> {{ $user->username ?? 'N/A' }}</div>
                                    <div><strong>Email:</strong> {{ $user->email ?? 'N/A' }}</div>
                                    <div><strong>Total:</strong> <span class="badge badge-light-primary">{{ $stats['total_sessions'] }}</span></div>
                                    <div><strong>Logins:</strong> <span class="badge badge-light-success">{{ $stats['login_sessions'] }}</span></div>
                                    <div><strong>Logouts:</strong> <span class="badge badge-light-danger">{{ $stats['logout_sessions'] }}</span></div>
                                    <div><strong>Unique IPs:</strong> <span class="badge badge-light-info">{{ $stats['unique_ips'] }}</span></div>
                                    <div><strong>Last Login:</strong>
                                        @if($stats['last_login'])
                                        {{ \Carbon\Carbon::parse($stats['last_login'])->format('d M Y, h:i A') }}
                                        @else
                                        <span class="text-muted">Never</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <h5 style="color: #3598db;">Access Details</h5>
                                <div class="table-responsive mt-3">
                                    <table id="accessDetailsTable" class="display compact" style="width:100%; font-size: 11px;">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Action</th>
                                                <th>DateTime</th>
                                                <th>IP Address</th>
                                                <th>Location</th>
                                                <th>Coordinates</th>
                                                <th>Browser & Platform</th>
                                                <th>Device & ISP</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($accessLogs as $log)
                                            <tr>
                                                <td>{{ $log->id }}</td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="material-symbols-outlined me-1 text-{{ $log->action === 'login' ? 'success' : 'danger' }}" style="font-size: 18px;">
                                                            {{ $log->action === 'login' ? 'login' : 'logout' }}
                                                        </span>
                                                        <span class="badge {{ $log->action === 'login' ? 'badge-light-success' : 'badge-light-danger' }}">
                                                            {{ ucfirst($log->action) }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    @if($log->action === 'login')
                                                    {{ ($log->login_at ?? $log->created_at)->format('d M Y, h:i A') }}
                                                    @else
                                                    {{ ($log->logout_at ?? $log->created_at)->format('d M Y, h:i A') }}
                                                    @endif
                                                </td>
                                                <td>{{ $log->ip_address ?? 'Unknown' }}</td>
                                                <td>
                                                    @if($log->country)
                                                    <strong>{{ $log->country }}</strong><br>
                                                    <small>{{ $log->city ?? 'Unknown City' }}</small><br>
                                                    <small class="text-muted">{{ $log->region ?? '' }}</small>
                                                    @else
                                                    <span class="text-muted">Unknown</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($log->latitude && $log->longitude)
                                                    <small>
                                                        <strong>Lat:</strong> {{ number_format($log->latitude, 4) }}<br>
                                                        <strong>Lng:</strong> {{ number_format($log->longitude, 4) }}
                                                    </small>
                                                    @else
                                                    <span class="text-muted">N/A</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <strong>{{ $log->browser ?? 'Unknown' }}</strong><br>
                                                    <small>{{ $log->platform ?? 'Unknown' }}</small>
                                                    @if($log->browser_version)
                                                    <br><small class="text-muted">v{{ $log->browser_version }}</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($log->device)
                                                    <strong>{{ $log->device }}</strong><br>
                                                    @endif
                                                    @if($log->isp)
                                                    <small>{{ $log->isp }}</small><br>
                                                    @endif
                                                    @if($log->timezone)
                                                    <small class="text-muted">{{ $log->timezone }}</small>
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
                            </div>

                            <div class="mt-4">
                                <a href="{{ route('user.access') }}" class="btn btn-secondary btn-sm d-flex align-items-center" style="width: fit-content;">
                                    <span class="material-symbols-outlined">arrow_back</span>
                                    <span class="ms-1">Back to Access Logs</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endsection

    @section('scripts')
    <script>
        $(document).ready(function() {
            // Initialize DataTable for access details
            $('#accessDetailsTable').DataTable({
                order: [
                    [0, 'desc']
                ], // Order by ID descending
                pageLength: 25,
                lengthChange: true,
                lengthMenu: [
                    [10, 25, 50, 100],
                    [10, 25, 50, 100]
                ],
                autoWidth: false,
                responsive: true,
                ordering: true,
                columnDefs: [{
                        targets: [1], // Action column
                        orderable: true,
                        searchable: true
                    },
                    {
                        targets: [4, 5, 6, 7], // Location, Coordinates, Browser, Device columns
                        orderable: true,
                        searchable: true
                    }
                ],
                language: {
                    search: "Search logs:",
                    lengthMenu: "Show _MENU_ entries per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ access logs",
                    infoEmpty: "No access logs available",
                    infoFiltered: "(filtered from _MAX_ total logs)"
                }
            });
        });
    </script>
    @endsection