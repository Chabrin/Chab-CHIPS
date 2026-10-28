@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h5 class="mb-0">Training Compliance Report</h5>
                        
                        <!-- Filter and Export Actions -->
                        <form method="GET" action="{{ route('hr.training.compliance') }}" class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                            <!-- Material Filter -->
                            <select name="material_id" class="form-select form-select-sm" style="width: 180px;">
                                <option value="">All Materials</option>
                                @foreach($materials as $mat)
                                    <option value="{{ $mat->id }}" {{ request('material_id') == $mat->id ? 'selected' : '' }}>{{ $mat->title }}</option>
                                @endforeach
                            </select>

                            <!-- Status Filter -->
                            <select name="status" class="form-select form-select-sm" style="width: 140px;">
                                <option value="">All Statuses</option>
                                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                            </select>

                            <button type="submit" class="btn btn-outline-primary btn-sm">Filter</button>
                            <a href="{{ route('hr.training.compliance') }}" class="btn btn-light btn-sm">Reset</a>

                            <!-- Export Button -->
                            <button type="submit" name="export" value="csv" class="btn btn-success btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined" style="font-size: 16px;">download</span>
                                <span class="ms-1">Export CSV</span>
                            </button>
                        </form>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Staff Member</th>
                                        <th>Training Title</th>
                                        <th>Version</th>
                                        <th>Status</th>
                                        <th>Due Date</th>
                                        <th>Completed At</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($records as $record)
                                    <tr>
                                       <td>{{ $record->staff->name ?? $record->staff->full_name ?? $record->staff->full_names ?? '-' }}</td>
                                        <td>{{ $record->material->title ?? 'Deleted Material' }}</td>
                                        <td>{{ $record->version_acknowledged }}</td>
                                        <td>
                                            @if($record->completed_at)
                                                <span class="badge bg-success">Completed</span>
                                            @elseif($record->due_date && \Carbon\Carbon::parse($record->due_date)->isPast())
                                                <span class="badge bg-danger">Overdue</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            @endif
                                        </td>
                                        <td>{{ $record->due_date ? \Carbon\Carbon::parse($record->due_date)->format('d M Y') : 'N/A' }}</td>
                                        <td>{{ $record->completed_at ? $record->completed_at->format('d M Y, H:i') : 'Not yet' }}</td>
                                        <td><code>{{ $record->ip_address ?? 'N/A' }}</code></td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No compliance records found matching your filters.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection