@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h5 class="mb-0">
                            <span class="material-symbols-outlined" style="vertical-align:middle;">schedule</span>
                            Lapsed Response Deadlines Report
                        </h5>
                        <div class="ms-auto">
                            <span class="badge bg-danger fs-6">{{ $lapsedCases->count() }} Cases</span>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($lapsedCases->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered" id="lapsedDeadlinesTable">
                                    <thead>
                                        <tr>
                                            <th>Case #</th>
                                            <th>Employee</th>
                                            <th>Original Deadline</th>
                                            <th>Current Deadline</th>
                                            <th>Days Overdue</th>
                                            <th>Extensions</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($lapsedCases as $case)
                                        @php
                                            $latestExtension = $case->deadlineExtensions->first();
                                            $currentDeadline = $latestExtension 
                                                ? \Carbon\Carbon::parse($latestExtension->new_deadline)
                                                : \Carbon\Carbon::parse($case->showCauseNotice->response_deadline);
                                            $daysOverdue = now()->diffInDays($currentDeadline);
                                        @endphp
                                        <tr>
                                            <td><strong>{{ $case->case_number }}</strong></td>
                                            <td>{{ $case->staff->full_names }}</td>
                                            <td>{{ \Carbon\Carbon::parse($case->showCauseNotice->response_deadline)->format('d M Y, h:i A') }}</td>
                                            <td>
                                                {{ $currentDeadline->format('d M Y, h:i A') }}
                                                @if($latestExtension)
                                                    <br><small class="text-muted">(Extended)</small>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-danger">{{ $daysOverdue }} days</span>
                                            </td>
                                            <td>{{ $case->deadlineExtensions->count() }}</td>
                                            <td>
                                                <a href="{{ route('discipline.tracker.show', Crypt::encrypt($case->id)) }}" class="btn btn-sm btn-primary">
                                                    <span class="material-symbols-outlined" style="font-size:14px;">visibility</span>
                                                    View Case
                                                </a>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="alert alert-success">
                                <span class="material-symbols-outlined" style="vertical-align:middle;">check_circle</span>
                                No lapsed deadlines found. All pending responses are within their deadlines.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#lapsedDeadlinesTable').DataTable({
        order: [[4, 'desc']], // Sort by days overdue
        pageLength: 25
    });
});
</script>
@endsection