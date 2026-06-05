@php
use Illuminate\Support\Facades\Crypt;
@endphp
@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">My Disciplinary Records</h5>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                        <div class="alert alert-light-success alert-dismissible fade show" role="alert">
                            <strong>Success!</strong> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif
                        @if(session('error'))
                        <div class="alert alert-light-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        <!-- Staff Summary Header -->
                        <div class="mb-4">
                            <h5 class="mb-1 d-inline"><b>{{ $staff->full_names }}</b></h5>
                            <span class="text-muted ms-2" style="font-size: 0.875rem;">
                                <strong>Staff #:</strong> {{ $staff->staff_number }} |
                                <strong>Dept:</strong> <span class="badge bg-primary">
                                    {{ $staff->department->department_name ?? 'N/A' }}</span>
                            </span>
                            <p class="text-muted mb-0" style="font-size: 0.8rem;">
                                <strong>Position:</strong> {{ $staff->position->position_name ?? '-' }} |
                                <strong>Email:</strong> <span style="text-decoration:underline; color: #1976d2;">{{ $staff->email_address ?? '-' }}</span>
                            </p>
                        </div>

                        @php
                            // Only show cases that have reached step 7 or beyond (employee is formally notified)
                            $visibleCases = $staff->disciplineCases->filter(function($case) {
                                return $case->current_step >= 7;
                            });

                            function getEmployeeStep($internalStep) {
                                $stepMapping = [
                                    7  => 1,
                                    8  => 2,
                                    9  => 3,
                                    10 => 4,
                                    11 => 5,
                                    12 => 6,
                                    13 => 7,
                                    14 => 8,
                                ];
                                return $stepMapping[$internalStep] ?? 1;
                            }

                            function getEmployeeStepName($internalStep) {
                                $stepNames = [
                                    7  => 'Show Cause Notice Received',
                                    8  => 'Your Response Required',
                                    9  => 'Hearing Scheduled',
                                    10 => 'Hearing Completed',
                                    11 => 'Decision Issued',
                                    12 => 'Training Assignment',
                                    13 => 'Record Updated',
                                    14 => 'Case Closed',
                                ];
                                return $stepNames[$internalStep] ?? 'In Progress';
                            }
                        @endphp

                        <!-- My Discipline Cases -->
                        <div class="card mb-4">
                            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">gavel</span>
                                    My Discipline Cases
                                </h6>
                                <span class="badge bg-danger text-white">{{ $visibleCases->count() }}</span>
                            </div>
                            <div class="card-body py-2">
                                @if($visibleCases->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 120px;">Case #</th>
                                                <th style="width: 150px;">Date Received</th>
                                                <th>Allegation</th>
                                                <th style="width: 100px;">Severity</th>
                                                <th style="width: 150px;">Current Stage</th>
                                                <th style="width: 120px;">Status</th>
                                                <th style="width: 200px;">Actions Required</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($visibleCases as $case)
                                            <tr>
                                                <td><strong>{{ $case->case_number }}</strong></td>
                                                <td>
                                                    @if($case->showCauseNotice)
                                                        {{ $case->showCauseNotice->issue_date ? \Carbon\Carbon::parse($case->showCauseNotice->issue_date)->format('d M Y') : '-' }}
                                                    @else
                                                        {{ $case->date_time ? $case->date_time->format('d M Y') : '-' }}
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($case->showCauseNotice)
                                                        {{ Str::limit($case->showCauseNotice->allegations, 60) }}
                                                    @elseif($case->incidentReports->first())
                                                        {{ Str::limit($case->incidentReports->first()->incident_description, 60) }}
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($case->severity_level == 'Minor')
                                                        <span class="badge bg-warning">Minor</span>
                                                    @elseif($case->severity_level == 'Major')
                                                        <span class="badge bg-danger">Major</span>
                                                    @elseif($case->severity_level == 'Gross')
                                                        <span class="badge bg-dark">Gross</span>
                                                    @else
                                                        <span class="badge bg-secondary">{{ $case->severity_level }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div><small class="text-muted">{{ getEmployeeStepName($case->current_step) }}</small></div>
                                                    <span class="badge bg-info">Step {{ getEmployeeStep($case->current_step) }} of 8</span>
                                                </td>
                                                <td>
                                                    @if($case->closed_at)
                                                        <span class="badge bg-secondary">Closed</span>
                                                    @else
                                                        <span class="badge bg-success">Active</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @php
                                                        $pendingActions = [];

                                                        if($case->showCauseNotice && $case->showCauseNotice->response_deadline) {
                                                            $originalDeadline = \Carbon\Carbon::parse($case->showCauseNotice->response_deadline);
                                                            $currentDeadline  = $originalDeadline;

                                                            $latestExtension = $case->deadlineExtensions
                                                                ->where('request_status', 'Approved')
                                                                ->whereNotNull('new_deadline')
                                                                ->sortByDesc('extended_at')
                                                                ->first();

                                                            if ($latestExtension) {
                                                                $currentDeadline = \Carbon\Carbon::parse($latestExtension->new_deadline);
                                                            }

                                                            $now             = \Carbon\Carbon::now();
                                                            $deadlineExpired = $now->greaterThan($currentDeadline);
                                                            $hoursRemaining  = $now->diffInHours($currentDeadline, false);

                                                            $pendingRequest = $case->deadlineExtensions
                                                                ->where('request_status', 'Pending')
                                                                ->whereNull('reviewed_at')
                                                                ->first();

                                                            if(($case->current_step == 7 || $case->current_step == 8) && !$case->employeeResponse) {
                                                                if($deadlineExpired) {
                                                                    $pendingActions[] = '<div class="mb-2"><span class="badge bg-danger">Deadline Expired: '.$currentDeadline->format('d M Y H:i').'</span></div>';
                                                                    if($pendingRequest) {
                                                                        $pendingActions[] = '<div><span class="badge bg-warning text-dark">
                                                                            <span class="material-symbols-outlined" style="font-size:12px; vertical-align:middle;">pending</span>
                                                                            Extension Request Pending
                                                                        </span></div>';
                                                                    } else {
                                                                        $pendingActions[] = '<button type="button" class="btn btn-sm btn-info" onclick="openExtensionRequestModal(\''.$case->id.'\', \''.$case->case_number.'\', \''.$currentDeadline->format('d M Y H:i').'\')">
                                                                            <span class="material-symbols-outlined" style="font-size:14px;">schedule_send</span>
                                                                            Request Extension
                                                                        </button>';
                                                                    }
                                                                } elseif($hoursRemaining > 0 && $hoursRemaining <= 24) {
                                                                    $pendingActions[] = '<div class="mb-2"><span class="badge bg-warning text-dark">Urgent: '.round($hoursRemaining).'h remaining</span></div>';
                                                                    if($pendingRequest) {
                                                                        $pendingActions[] = '<div class="mb-2"><span class="badge bg-info text-dark" style="font-size:0.7rem;">Extension Request Pending</span></div>';
                                                                    }
                                                                    $pendingActions[] = '<button type="button" class="btn btn-sm btn-warning" onclick="openResponseModal(\''.$case->id.'\', \''.$case->case_number.'\', \''.$currentDeadline->format('d M Y H:i').'\')">
                                                                        <span class="material-symbols-outlined" style="font-size:14px;">edit_note</span>
                                                                        Respond Now
                                                                    </button>';
                                                                } else {
                                                                    $pendingActions[] = '<div class="mb-2"><span class="badge bg-info">Due: '.$currentDeadline->format('d M Y H:i').'</span></div>';
                                                                    if($pendingRequest) {
                                                                        $pendingActions[] = '<div class="mb-2"><span class="badge bg-info text-dark" style="font-size:0.7rem;">Extension Request Pending</span></div>';
                                                                    }
                                                                    $pendingActions[] = '<button type="button" class="btn btn-sm btn-primary d-flex align-items-center" onclick="openResponseModal(\''.$case->id.'\', \''.$case->case_number.'\', \''.$currentDeadline->format('d M Y H:i').'\')">
                                                                        <span class="material-symbols-outlined" style="font-size:14px;">edit_note</span>
                                                                        Respond
                                                                    </button>';
                                                                }
                                                            }
                                                        }

                                                        if(in_array($case->current_step, [9, 10]) && $case->hearings->count()) {
                                                            $hearing = $case->hearings->first();
                                                            if($hearing) {
                                                                $hearingDate = \Carbon\Carbon::parse($hearing->hearing_date)->format('d M Y H:i');
                                                                $pendingActions[] = '<span class="badge bg-primary">Hearing: '.$hearingDate.'</span>';
                                                            }
                                                        }
                                                    @endphp
                                                    @if(count($pendingActions))
                                                        {!! implode(' ', $pendingActions) !!}
                                                    @else
                                                        <span class="text-muted" style="font-size: 0.75rem;">No action needed</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @else
                                <div class="text-muted" style="font-size: 0.875rem;">No formal discipline notices have been issued to you.</div>
                                @endif
                            </div>
                        </div>

                        <!-- Assigned Investigations -->
                        @if($staff->investigationsAssigned && $staff->investigationsAssigned->count())
                        <div class="card mb-4">
                            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">search</span>
                                    Assigned Investigations
                                </h6>
                                <span class="badge bg-primary text-white">{{ $staff->investigationsAssigned->count() }}</span>
                            </div>
                            <div class="card-body py-2">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Case #</th>
                                                <th>Subject</th>
                                                <th>Assigned Date</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($staff->investigationsAssigned as $investigation)
                                            @php $case = $investigation->case; @endphp
                                            <tr>
                                                <td><strong>{{ $case->case_number }}</strong></td>
                                                <td>{{ $case->staff->full_names ?? '-' }}</td>
                                                <td>{{ $investigation->assigned_at ? \Carbon\Carbon::parse($investigation->assigned_at)->format('d M Y') : '-' }}</td>
                                                <td>
                                                    @if($investigation->status == 'Completed')
                                                        <span class="badge bg-success">Completed</span>
                                                    @elseif($investigation->status == 'In Progress')
                                                        <span class="badge bg-warning text-dark">In Progress</span>
                                                    @else
                                                        <span class="badge bg-secondary">{{ $investigation->status }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <a href="{{ route('discipline.tracker.show', Crypt::encrypt($case->id)) }}"
                                                       class="btn btn-sm btn-outline-primary d-flex align-items-center" style="width:fit-content;">
                                                        <span class="material-symbols-outlined" style="font-size:16px;">visibility</span>
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Reported Incidents -->
                        @if($staff->incidentReports && $staff->incidentReports->count())
                        <div class="card mb-4">
                            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">report</span>
                                    Reported Incidents
                                </h6>
                                <span class="badge bg-info text-white">{{ $staff->incidentReports->count() }}</span>
                            </div>
                            <div class="card-body py-2">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Case #</th>
                                                <th>Incident Date</th>
                                                <th>Description</th>
                                                <th>Subject</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($staff->incidentReports as $report)
                                            @php $case = $report->case; @endphp
                                            <tr>
                                                <td><strong>{{ $case->case_number }}</strong></td>
                                                <td>{{ $report->incident_date_time ? \Carbon\Carbon::parse($report->incident_date_time)->format('d M Y') : '-' }}</td>
                                                <td>{{ Str::limit($report->incident_description, 60) }}</td>
                                                <td>{{ $case->staff->full_names ?? '-' }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Witness Statements -->
                        @if($staff->witnessStatements && $staff->witnessStatements->count())
                        <div class="card mb-4">
                            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">contact_support</span>
                                    Witness Statements Provided
                                </h6>
                                <span class="badge bg-secondary text-white">{{ $staff->witnessStatements->count() }}</span>
                            </div>
                            <div class="card-body py-2">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Case #</th>
                                                <th>Statement Date</th>
                                                <th>Subject</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($staff->witnessStatements as $statement)
                                            @php $case = $statement->case; @endphp
                                            <tr>
                                                <td><strong>{{ $case->case_number }}</strong></td>
                                                <td>{{ $statement->statement_date ? \Carbon\Carbon::parse($statement->statement_date)->format('d M Y') : '-' }}</td>
                                                <td>{{ $case->staff->full_names ?? '-' }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @endif

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Employee Response Modal -->
<div class="modal fade" id="employeeResponseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Submit Response to Show Cause Notice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="employeeResponseForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="case_id" id="response_case_id">
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <strong>Case #:</strong> <span id="response_case_number"></span><br>
                        <strong>Deadline:</strong> <span id="response_deadline"></span>
                    </div>
                    <div class="mb-3">
                        <label for="response_date" class="form-label">Response Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="response_date" id="response_date" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="mb-3">
                        <label for="admits_allegations" class="form-label">Do you admit to the allegations? <span class="text-danger">*</span></label>
                        <select class="form-select" name="admits_allegations" id="admits_allegations" required>
                            <option value="">Select...</option>
                            <option value="1">Yes, I admit</option>
                            <option value="0">No, I deny</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="response_text" class="form-label">Your Response <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="response_text" id="response_text" rows="6" required placeholder="Provide your detailed response to the allegations..."></textarea>
                        <small class="text-muted">Explain your position clearly and provide any relevant context.</small>
                    </div>
                    <div class="mb-3">
                        <label for="mitigating_circumstances" class="form-label">Mitigating Circumstances</label>
                        <textarea class="form-control" name="mitigating_circumstances" id="mitigating_circumstances" rows="4" placeholder="Any factors that should be considered in your defense..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="response_documents" class="form-label">Supporting Documents</label>
                        <input type="file" class="form-control" name="documents[]" id="response_documents" multiple>
                        <small class="text-muted">Upload any evidence or supporting documents (PDF, DOC, images)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined">close</span>
                        <span class="ms-1">Cancel</span>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">send</span>
                        Submit Response
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Extension Request Modal -->
<div class="modal fade" id="extensionRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Request Deadline Extension</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="extensionRequestForm">
                @csrf
                <input type="hidden" name="case_id" id="ext_case_id">
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <strong>Case #:</strong> <span id="ext_case_number"></span><br>
                        <strong>Current Deadline:</strong> <span id="ext_current_deadline"></span>
                    </div>
                    <div class="alert alert-warning mb-3">
                        <span class="material-symbols-outlined" style="vertical-align:middle;">info</span>
                        <strong>Note:</strong> Your extension request will be reviewed by HR. You will be notified of the decision.
                    </div>
                    <div class="mb-3">
                        <label for="ext_reason" class="form-label">Reason for Extension Request <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="extension_reason" id="ext_reason" rows="4" required
                            placeholder="Explain why you need more time to respond (e.g., gathering evidence, consulting with legal counsel, medical reasons...)"></textarea>
                        <small class="text-muted">Be specific and provide valid justification.</small>
                    </div>
                    <div class="mb-3">
                        <label for="ext_requested_days" class="form-label">Additional Days Requested <span class="text-danger">*</span></label>
                        <select class="form-select" name="requested_days" id="ext_requested_days" required>
                            <option value="">Select...</option>
                            <option value="1">1 Day</option>
                            <option value="2">2 Days</option>
                            <option value="3">3 Days</option>
                            <option value="5">5 Days</option>
                            <option value="7">7 Days</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined">close</span>
                        <span class="ms-1">Cancel</span>
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">schedule_send</span>
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .card-header {
        border-bottom: 1px solid #e0e0e0;
    }
    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 20;
    }
    .card {
        border: 1px solid #e0e0e0;
    }
</style>

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
function openResponseModal(caseId, caseNumber, deadline) {
    $('#response_case_id').val(caseId);
    $('#response_case_number').text(caseNumber);
    $('#response_deadline').text(deadline);
    $('#employeeResponseModal').modal('show');
}

function openExtensionRequestModal(caseId, caseNumber, currentDeadline) {
    $('#ext_case_id').val(caseId);
    $('#ext_case_number').text(caseNumber);
    $('#ext_current_deadline').text(currentDeadline);
    $('#extensionRequestModal').modal('show');
}

$(document).ready(function() {
    // Submit Employee Response
    $('#employeeResponseForm').on('submit', function(e) {
        e.preventDefault();
        let formData = new FormData(this);
        let caseId = $('#response_case_id').val();
        $.ajax({
            url: "{{ url('/discipline/tracker/case') }}/" + caseId + "/submit-response",
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if(response.success) {
                    $('#employeeResponseModal').modal('hide');
                    toastr.success(response.message);
                    setTimeout(function() { location.reload(); }, 1500);
                }
            },
            error: function(xhr) {
                if(xhr.status === 403 && xhr.responseJSON?.error) {
                    toastr.error(xhr.responseJSON.error);
                } else {
                    toastr.error('Failed to submit response. Please try again.');
                }
            }
        });
    });

    // Submit Extension Request
    $('#extensionRequestForm').on('submit', function(e) {
        e.preventDefault();
        let formData = {
            _token: '{{ csrf_token() }}',
            case_id: $('#ext_case_id').val(),
            extension_reason: $('#ext_reason').val(),
            requested_days: $('#ext_requested_days').val()
        };
        $.ajax({
            url: "{{ route('discipline.tracker.request.extension') }}",
            type: 'POST',
            data: formData,
            success: function(response) {
                if(response.success) {
                    $('#extensionRequestModal').modal('hide');
                    toastr.success(response.message);
                    setTimeout(function() { location.reload(); }, 1500);
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.error || 'Failed to submit extension request. Please try again.');
            }
        });
    });
});
</script>

@endsection