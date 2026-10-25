@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Training Compliance Report</h5>
                        <div class="ms-auto">
                            <a href="{{ route('settings.hr.training_materials') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to Training</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="training-compliance-table">
                                <thead>
                                    <tr>
                                        <th>Staff</th>
                                        <th>Staff No.</th>
                                        <th>Material</th>
                                        <th>Version</th>
                                        <th>Due</th>
                                        <th>Status</th>
                                        <th>Completed At</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($records as $record)
                                    @php
                                        $completed = (bool) $record->completed_at;
                                        $overdue = !$completed && $record->due_date && $record->due_date->isPast();
                                    @endphp
                                    <tr>
                                        <td>{{ $record->staff->full_names ?? '-' }}</td>
                                        <td>{{ $record->staff->staff_number ?? '-' }}</td>
                                        <td>{{ $record->material->title ?? '-' }}</td>
                                        <td>{{ $record->version_acknowledged }}</td>
                                        <td>{{ $record->due_date ? $record->due_date->format('d M Y') : '-' }}</td>
                                        <td>
                                            @if($completed)
                                            <span class="badge bg-success">Completed</span>
                                            @elseif($overdue)
                                            <span class="badge bg-danger">Overdue</span>
                                            @else
                                            <span class="badge bg-warning text-dark">Pending</span>
                                            @endif
                                        </td>
                                        <td>{{ $completed ? $record->completed_at->format('d M Y H:i') : '-' }}</td>
                                        <td>{{ $record->ip_address ?? '-' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {
        $('#training-compliance-table').DataTable({ order: [], pageLength: 25 });
    });
</script>
@endsection
