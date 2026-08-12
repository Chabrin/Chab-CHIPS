@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <!-- Case Header -->
        <div class="row mb-3">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <div>
                            <h4 class="mb-0">Case: {{ $case->case_number }}</h4>
                            <small class="text-muted">Created: {{ $case->date_time->format('d M Y, h:i A') }}</small>
                        </div>
                        <div class="ms-auto d-flex gap-2">
                            <span class="badge {{ $case->current_status_id == 5 ? 'badge-light-success' : 'badge-light-secondary' }} fs-6">
                                {{ $case->current_status_id == 5 ? 'Open' : 'Closed' }}
                            </span>
                            <a href="{{ route('discipline.tracker.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to Cases</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Employee:</strong><br>
                                <a href="/hr/employees/{{ Crypt::encrypt($case->staff->id) }}" target="_blank">
                                    {{ $case->staff->full_names }}
                                </a>
                            </div>
                            <div class="col-md-3">
                                <strong>Position:</strong><br>
                                {{ $case->position->position_name ?? '-' }}
                            </div>
                            <div class="col-md-3">
                                <strong>Severity:</strong><br>
                                @php
                                    $severityColor = [
                                        'Minor' => 'info',
                                        'Moderate' => 'warning',
                                        'Serious' => 'danger',
                                        'Gross Misconduct' => 'dark'
                                    ][$case->severity_level] ?? 'secondary';
                                @endphp
                                <span class="badge badge-light-{{ $severityColor }}">{{ $case->severity_level }}</span>
                            </div>
                            <div class="col-md-3">
                                <strong>Current Step:</strong><br>
                                <span class="badge badge-light-primary">Step {{ $case->current_step }}: {{ $availableSteps[$case->current_step]['name'] }}</span>
                            </div>
                        </div>

                        @php $activeSuspension = $case->suspensions->whereNull('lifted_at')->sortByDesc('suspended_at')->first(); @endphp
                        <div class="row mt-3">
                            <div class="col-12">
                                @if($activeSuspension)
                                    <div class="alert alert-danger d-flex align-items-center justify-content-between mb-0">
                                        <div>
                                            <span class="material-symbols-outlined" style="vertical-align:middle;">block</span>
                                            <strong>Employee suspended pending investigation</strong> ({{ $activeSuspension->pay_status }}) —
                                            since {{ $activeSuspension->suspended_at->format('d M Y, h:i A') }}. Reason: {{ $activeSuspension->reason }}
                                        </div>
                                        <button type="button" class="btn btn-sm btn-light" id="liftSuspensionBtn">Lift Suspension</button>
                                    </div>
                                @elseif($case->investigators->isNotEmpty() && $case->current_step < 11 && !$case->closure)
                                    <button type="button" class="btn btn-outline-danger btn-sm" id="showSuspendFormBtn">
                                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">block</span>
                                        Suspend Employee Pending Investigation (Full Pay)
                                    </button>
                                    <form id="suspendPendingForm" class="mt-2 d-none">
                                        @csrf
                                        <div class="row g-2">
                                            <div class="col-md-8">
                                                <textarea class="form-control" name="reason" placeholder="Reason for protective suspension..." required></textarea>
                                            </div>
                                            <div class="col-md-4">
                                                <button type="submit" class="btn btn-danger w-100">Confirm Suspension</button>
                                            </div>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        $(document).ready(function() {
            $('#showSuspendFormBtn').on('click', function() {
                $('#suspendPendingForm').toggleClass('d-none');
            });

            $('#suspendPendingForm').on('submit', function(e) {
                e.preventDefault();
                if (!confirm('Suspend this employee pending investigation, on full pay?')) return false;
                $.ajax({
                    url: "{{ route('discipline.tracker.suspend.pending', $case->id) }}",
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            window.location.reload();
                        }
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.error || 'Failed to suspend employee');
                    }
                });
            });

            $('#liftSuspensionBtn').on('click', function() {
                if (!confirm('Lift this protective suspension?')) return;
                $.ajax({
                    url: "{{ route('discipline.tracker.lift.suspension', $case->id) }}",
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            window.location.reload();
                        }
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.error || 'Failed to lift suspension');
                    }
                });
            });
        });
        </script>

        <!-- Step Overview -->
        <div class="row mb-3">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <span class="material-symbols-outlined" style="vertical-align:middle;">list_alt</span>
                            Discipline Process Steps Overview
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Progress Bar -->
                        <div class="row mb-3">
                            <div class="col-sm-12">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="mb-3">Case Progress</h6>
                                        <div class="progress" style="height: 25px;">
                                            @php
                                                $progress = ($case->current_step / 14) * 100;
                                            @endphp
                                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: {{ $progress }}%;" aria-valuenow="{{ $case->current_step }}"  aria-valuemin="0" aria-valuemax="14"> Step {{ $case->current_step }} of 14 ({{ round($progress) }}%)
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            @php
                                $skippableSteps = [3, 4, 5, 6, 9, 10, 12];
                                $stepDescriptions = [
                                    1 => ['name' => 'Incident Report', 'desc' => 'Document the initial incident', 'icon' => 'report'],
                                    2 => ['name' => 'Register Case', 'desc' => 'Register with HR and set priority', 'icon' => 'assignment'],
                                    3 => ['name' => 'Assign Investigator', 'desc' => 'Optional: Assign investigator', 'icon' => 'person_search'],
                                    4 => ['name' => 'Upload Evidence', 'desc' => 'Optional: Upload supporting evidence', 'icon' => 'folder_open'],
                                    5 => ['name' => 'Witness Statements', 'desc' => 'Optional: Record witness statements', 'icon' => 'groups'],
                                    6 => ['name' => 'Investigation Notes', 'desc' => 'Optional: Record investigation notes', 'icon' => 'note_add'],
                                    7 => ['name' => 'Show Cause Notice', 'desc' => 'Issue formal notice to employee', 'icon' => 'gavel'],
                                    8 => ['name' => 'Employee Response', 'desc' => 'Employee submits response', 'icon' => 'reply'],
                                    9 => ['name' => 'Schedule Hearing', 'desc' => 'Optional: Schedule formal hearing', 'icon' => 'event'],
                                    10 => ['name' => 'Hearing Minutes', 'desc' => 'Optional: Record hearing minutes', 'icon' => 'description'],
                                    11 => ['name' => 'Final Outcome', 'desc' => 'Record final decision and sanctions', 'icon' => 'gavel'],
                                    12 => ['name' => 'Assign Training', 'desc' => 'Optional: Assign remedial training', 'icon' => 'school'],
                                    13 => ['name' => 'Update History', 'desc' => 'Update disciplinary record', 'icon' => 'history'],
                                    14 => ['name' => 'Close Case', 'desc' => 'Close and finalize case', 'icon' => 'task_alt']
                                ];
                            @endphp
                            
                            @foreach($stepDescriptions as $stepNum => $stepInfo)
                                @php
                                    $isCompleted = $stepNum < $case->current_step;
                                    $isCurrent = $stepNum == $case->current_step;
                                    $isSkippable = in_array($stepNum, $skippableSteps);
                                @endphp
                                <div class="col-md-6 col-lg-4 col-xl-3 mb-3">
                                    <div class="card h-100 rounded {{ $isCompleted ? 'border-success' : ($isCurrent ? 'border-primary' : 'border-secondary') }}"  style="border-width: 1px;">
                                        <div class="card-body p-2">
                                            <div class="d-flex align-items-start">
                                                <span class="material-symbols-outlined {{ $isCompleted ? 'text-success' : ($isCurrent ? 'text-primary' : 'text-muted') }}" style="font-size:24px; margin-right:8px;">
                                                    {{ $stepInfo['icon'] }}
                                                </span>
                                                <div class="flex-grow-1" style="min-width:0;">
                                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                                        <strong class="" style="font-size:0.9rem;">
                                                            Step {{ $stepNum }}
                                                        </strong>
                                                        @if($isCompleted)
                                                            <span class="badge badge-light-success badge-sm">Done</span>
                                                        @elseif($isCurrent)
                                                            <span class="badge badge-light-primary badge-sm">Current</span>
                                                        @else
                                                            <span class="badge badge-light-secondary badge-sm">Pending</span>
                                                        @endif
                                                    </div>
                                                    <div style="font-size:0.85rem; font-weight:600; margin-bottom:4px;">
                                                        {{ $stepInfo['name'] }}
                                                    </div>
                                                    <small class="text-muted d-block" style="font-size:0.75rem; line-height:1.2;">
                                                        {{ $stepInfo['desc'] }}
                                                    </small>
                                                    @if($isSkippable)
                                                        <div class="mt-1">
                                                            <span class="badge badge-light-warning text-dark badge-sm" style="font-size:0.65rem;">
                                                                <span class="material-symbols-outlined" style="font-size:10px; vertical-align:middle;">skip_next</span>
                                                                Skippable
                                                            </span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs for Steps -->

        <!-- Tabs for Steps -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="caseStepsTabs" role="tablist">
                            @foreach($availableSteps as $stepNum => $step)
                                @php
                                    $isCompleted = $stepNum < $case->current_step;
                                    $isCurrent = $stepNum == $case->current_step;
                                    $isLocked = $stepNum > $case->current_step;
                                @endphp
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $isCurrent ? 'active' : '' }} {{ $isLocked ? 'disabled' : '' }}" 
                                            id="step{{ $stepNum }}-tab" 
                                            data-bs-toggle="tab" 
                                            data-bs-target="#step{{ $stepNum }}" 
                                            type="button" 
                                            role="tab"
                                            {{ $isLocked ? 'disabled' : '' }}>
                                        @if($isCompleted)
                                            <span class="material-symbols-outlined text-success" style="font-size:16px;">check_circle</span>
                                        @elseif($isCurrent)
                                            <span class="material-symbols-outlined text-primary" style="font-size:16px;">radio_button_checked</span>
                                        @else
                                            <span class="material-symbols-outlined text-muted" style="font-size:16px;">lock</span>
                                        @endif
                                        Step {{ $stepNum }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>

                        <div class="tab-content mt-3" id="caseStepsTabContent">
                            @include('discipline_tracker.steps.step1_incident', ['case' => $case])
                            @include('discipline_tracker.steps.step2_register', ['case' => $case])
                            @include('discipline_tracker.steps.step3_investigator', ['case' => $case])
                            @include('discipline_tracker.steps.step4_evidence', ['case' => $case])
                            @include('discipline_tracker.steps.step5_witness', ['case' => $case])
                            @include('discipline_tracker.steps.step6_notes', ['case' => $case])
                            @include('discipline_tracker.steps.step7_show_cause', ['case' => $case])
                            @include('discipline_tracker.steps.step8_response', ['case' => $case])
                            @include('discipline_tracker.steps.step9_hearing', ['case' => $case])
                            @include('discipline_tracker.steps.step10_minutes', ['case' => $case])
                            @include('discipline_tracker.steps.step11_outcome', ['case' => $case])
                            @include('discipline_tracker.steps.step12_training', ['case' => $case])
                            @include('discipline_tracker.steps.step13_history', ['case' => $case])
                            @include('discipline_tracker.steps.step14_closure', ['case' => $case])
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timeline Sidebar -->
        <div class="row mt-3">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <span class="material-symbols-outlined" style="vertical-align:middle;">timeline</span>
                            Activity Timeline
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                        @if($case->activityLogs && count($case->activityLogs) > 0)
                            <div class="position-relative ps-4">
                                <!-- Vertical line -->
                                <div class="position-absolute top-0 bottom-0 border-start border-2 border-secondary-subtle" style="left: 10px;"></div>
                                
                                @foreach($case->activityLogs->sortByDesc('performed_at') as $activity)
                                    @php
                                        $badgeColor = 'secondary';
                                        $borderColor = 'secondary';
                                        if ($activity->activity_type === 'Step Completed') {
                                            $badgeColor = 'success';
                                            $borderColor = 'success';
                                        } elseif ($activity->activity_type === 'Step Skipped') {
                                            $badgeColor = 'warning';
                                            $borderColor = 'warning';
                                        } elseif ($activity->activity_type === 'Document Added') {
                                            $badgeColor = 'primary';
                                            $borderColor = 'primary';
                                        }
                                    @endphp
                                    <div class="position-relative pb-4 ps-3">
                                        <!-- Timeline marker -->
                                        <span class="position-absolute bg-{{ $badgeColor }} rounded-circle border border-2 border-white shadow-sm" 
                                              style="left: -25px; top: 5px; width: 12px; height: 12px;"></span>
                                        
                                        <!-- Timeline content -->
                                        <div class="bg-light p-3 rounded border-start border-3 border-{{ $borderColor }}">
                                            <h6 class="mb-1">
                                                {{ $activity->step_name }} 
                                                <span class="badge bg-secondary text-black">Step {{ $activity->step_number }}</span>
                                            </h6>
                                            <p class="mb-1 text-black">
                                                <strong>{{ $activity->activity_type }}</strong>: {{ $activity->activity_description }}
                                            </p>
                                            <small class="text-muted">
                                                <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">person</span> 
                                                {{ $activity->performedBy->name ?? 'System' }}
                                                | 
                                                <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">schedule</span> 
                                                {{ $activity->performed_at->format('d M Y, h:i A') }}
                                            </small>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted text-center">No activity recorded yet.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
$(document).ready(function() {
    // Auto-scroll to current step tab
    let currentStepTab = $('#step{{ $case->current_step }}-tab');
    if (currentStepTab.length) {
        currentStepTab[0].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }

    // Reload page after successful step completion
    window.reloadCasePage = function() {
        location.reload();
    };
});

// Skip Step Function
function skipStep(stepNumber) {
    if (!confirm('Are you sure you want to skip this step?')) {
        return;
    }

    // Prompt for reason
    var reason = prompt('Please provide a reason for skipping this step:');
    
    // If user cancels or enters empty reason
    if (reason === null || reason.trim() === '') {
        toastr.warning('Step skip cancelled. Please provide a reason.');
        return;
    }

    $.ajax({
        url: "{{ route('discipline.tracker.skip.step', $case->id) }}",
        type: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            step_number: stepNumber,
            reason: reason.trim()
        },
        success: function(response) {
            if(response.success) {
                toastr.success(response.message);
                window.reloadCasePage();
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.error || 'Failed to skip step');
        }
    });
}
</script>


@endsection