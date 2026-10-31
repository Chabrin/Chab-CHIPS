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
                        <h5 class="mb-0">Leave Application Details</h5>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('leave-management.leave-applications') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($leave_applications && $leave_applications->count())
                            @foreach($leave_applications as $application)
                            <div class="card mb-3">
                                <div class="card-header bg-light py-2 d-flex align-items-center">
                                    <h6 class="mb-0" style="font-size: 0.9rem;">
                                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">event_available</span>
                                        Leave Application - {{ $application->leaveType->type_name ?? '-' }}
                                    </h6>
                                </div>
                                <div class="card-body py-2">
                                    <div class="row g-2">
                                        <div class="col-md-3">
                                            <small class="text-muted">Employee:</small><br>
                                            <strong>{{ $application->employee->full_names ?? '-' }}</strong>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Position:</small><br>
                                            <strong>{{ $application->position->position_name ?? '-' }}</strong>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Leave Type:</small><br>
                                            <strong>{{ $application->leaveType->type_name ?? '-' }}</strong>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Status:</small><br>
                                            <strong>
                                                @if($application->current_status_id == 1)
                                                    <span class="badge bg-warning">Pending</span>
                                                @elseif($application->current_status_id == 24)
                                                    <span class="badge bg-info">Pending HR</span>
                                                @elseif($application->current_status_id == 5)
                                                    <span class="badge bg-success">Approved</span>
                                                @elseif($application->current_status_id == 41)
                                                    <span class="badge bg-danger">Rejected</span>
                                                @else
                                                    {{ $application->current_status_id }}
                                                @endif
                                                @if($application->recalled_at)
                                                    <span class="badge bg-dark ms-1">Recalled · {{ $application->recall_effective_date ? \Carbon\Carbon::parse($application->recall_effective_date)->format('d M Y') : '' }}</span>
                                                @endif
                                            </strong>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Start Date:</small><br>
                                            <strong>{{ $application->start_date ? \Carbon\Carbon::parse($application->start_date)->format('d M Y') : '-' }}</strong>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">End Date:</small><br>
                                            <strong>{{ $application->end_date ? \Carbon\Carbon::parse($application->end_date)->format('d M Y') : '-' }}</strong>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Requested Days:</small><br>
                                            <strong>{{ $application->requested_days ?? '-' }}</strong>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Entitled Days:</small><br>
                                            <strong>{{ $application->entitled_days ?? '-' }}</strong>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Days Balance:</small><br>
                                            <strong>{{ $application->days_balance ?? '-' }}</strong>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Used Days:</small><br>
                                            <strong>{{ $application->used_days ?? '-' }}</strong>
                                        </div>
                                        @if($application->amount)
                                        <div class="col-md-3">
                                            <small class="text-muted">Amount:</small><br>
                                            <strong>KES {{ number_format($application->amount, 2) }}</strong>
                                        </div>
                                        @endif
                                        <div class="col-md-3">
                                            <small class="text-muted">Created By:</small><br>
                                            <strong>{{ $application->createdBy->name ?? '-' }}</strong>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Date Created:</small><br>
                                            <strong>{{ $application->date_time ? \Carbon\Carbon::parse($application->date_time)->format('d M Y H:i') : '-' }}</strong>
                                        </div>
                                    </div>
                                    @if($application->recalled_at)
                                    <div class="alert alert-secondary py-2 mt-2 mb-0"><strong>Recall reason:</strong> {{ $application->recall_reason }} <span class="ms-2">({{ number_format((float) $application->recalled_days, 2) }} days refunded)</span></div>
                                    @endif
                                </div>
                            </div>
                            <div class="card mb-3">
                                <div class="card-header bg-light py-2 d-flex align-items-center justify-content-between">
                                    <h6 class="mb-0">Department staffing</h6>
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        @if((int) $application->current_status_id === 5 && !$application->recalled_at && $application->employee?->department_id && $application->start_date && $application->end_date && \Carbon\Carbon::parse($application->start_date)->toDateString() <= now()->toDateString() && \Carbon\Carbon::parse($application->end_date)->toDateString() >= now()->toDateString() && auth()->user()->isAbleTo('update_leave_applications'))
                                            @if(!$departmentStaffingHealthy)
                                                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#recallLeaveModal">Recall Employee</button>
                                            @else
                                                <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Active staffing is at or above 80%">Recall unavailable · Staffing healthy</button>
                                            @endif
                                        @endif
                                        <form method="get" class="d-flex align-items-center gap-2">
                                            <label for="staffing-date" class="small mb-0">Check date</label>
                                            <input type="date" id="staffing-date" name="staffing_date" class="form-control form-control-sm" value="{{ $staffingDate }}">
                                            <button class="btn btn-sm btn-outline-primary">Check</button>
                                        </form>
                                    </div>
                                </div>
                                <div class="card-body py-2">
                                    @if($application->employee?->department)
                                        @if($staffingThreshold && $staffingThreshold['staff_total'] > 0)
                                            <div class="alert {{ $staffingThreshold['met'] ? 'alert-success' : 'alert-danger' }} py-2 mb-2">
                                                <strong>Department:</strong> {{ $application->employee->department->department_name }}<br>
                                                <strong>Active staff:</strong> {{ $staffingThreshold['active_staff'] }} of {{ $staffingThreshold['staff_total'] }} available ({{ number_format($staffingThreshold['active_percentage'], 1) }}% active). On approved leave: {{ $staffingThreshold['on_leave'] }}.<br>
                                                @if($staffingThreshold['met'])
                                                    <strong>Status:</strong> At or above 80% threshold — staffing level is healthy.
                                                @else
                                                    <strong>Understaffing Warning:</strong> Active staff at {{ number_format($staffingThreshold['active_percentage'], 1) }}% (below 80% threshold). Approval or recall required!
                                                @endif
                                            </div>
                                        @endif
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach($departmentStaff as $member)
                                                <span class="badge {{ $onLeaveStaffIds->contains($member->id) ? 'bg-warning text-dark' : 'bg-light text-dark border' }}">{{ $member->full_names }}{{ $member->staff_number ? ' · #'.$member->staff_number : '' }}{{ $member->position?->position_name ? ' · '.$member->position->position_name : '' }}{{ $onLeaveStaffIds->contains($member->id) ? ' · On leave' : '' }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-muted">No department is assigned to this employee.</div>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        @else
                            <div class="alert alert-info">No leave applications found for this staff member.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if(isset($application) && (int) $application->current_status_id === 5 && !$application->recalled_at && $application->employee?->department_id && $application->start_date && $application->end_date && \Carbon\Carbon::parse($application->start_date)->toDateString() <= now()->toDateString() && \Carbon\Carbon::parse($application->end_date)->toDateString() >= now()->toDateString() && auth()->user()->isAbleTo('update_leave_applications') && !$departmentStaffingHealthy)
<div class="modal fade" id="recallLeaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Recall employee from leave</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <label class="form-label" for="recall-effective-date">Effective recall date</label>
            <input type="date" class="form-control mb-3" id="recall-effective-date" min="{{ now()->toDateString() }}" max="{{ \Carbon\Carbon::parse($application->end_date)->toDateString() }}" value="{{ now()->toDateString() }}">
            <label class="form-label" for="recall-reason">Recall reason</label>
            <textarea class="form-control" id="recall-reason" rows="4" maxlength="2000" required></textarea>
            <div class="form-text">Unused approved leave days from the effective date onward will be returned to the employee’s balance.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" id="submit-leave-recall">Confirm recall</button></div>
    </div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('submit-leave-recall')?.addEventListener('click', async function () {
        const reason = document.getElementById('recall-reason').value.trim();
        const effectiveDate = document.getElementById('recall-effective-date').value;
        if (!reason || !effectiveDate) { alert('Enter a recall reason and effective date.'); return; }
        this.disabled = true;
        try {
            const response = await fetch(@json(route('ajax.leaveApplications.recall', $application->id)), {
                method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'},
                body: JSON.stringify({reason: reason, effective_date: effectiveDate})
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.error || 'Recall failed.');
            alert(result.message);
            window.location.reload();
        } catch (error) { alert(error.message); this.disabled = false; }
    });
});
</script>
@endif


<style>
    .card-header {
        border-bottom: 1px solid #e0e0e0;
    }

    .card-body .row.g-2>div {
        padding-top: 0.25rem;
        padding-bottom: 0.25rem;
        line-height: 1.5;
    }

    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 20;
    }

    .card {
        border: 1px solid #e0e0e0;
    }
</style>
@endsection
