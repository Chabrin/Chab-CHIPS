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
                            @if(isset($application) && $application->current_status_id == 1)
                                @permission('update_leave_applications')
                                <button type="button" class="btn btn-success btn-sm d-flex align-items-center btn-approve-leave" data-id="{{ $application->id }}">
                                    <span class="material-symbols-outlined">check_circle</span>
                                    <span class="ms-1">Approve Leave</span>
                                </button>
                                <button type="button" class="btn btn-danger btn-sm d-flex align-items-center btn-reject-leave" data-id="{{ $application->id }}">
                                    <span class="material-symbols-outlined">cancel</span>
                                    <span class="ms-1">Reject</span>
                                </button>
                                @endpermission
                            @endif
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
                                <div class="card-header bg-light py-2">
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
                                                    <span class="badge bg-info">Partially Approved</span>
                                                @elseif($application->current_status_id == 5)
                                                    <span class="badge bg-success">Approved</span>
                                                @elseif($application->current_status_id == 41)
                                                    <span class="badge bg-danger">Rejected</span>
                                                @else
                                                    {{ $application->current_status_id }}
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

<script>
$(document).ready(function() {$('.btn-approve-leave, .btn-reject-leave').on('click', function() {
        let isApprove = $(this).hasClass('btn-approve-leave');
        let action = isApprove ? 'approve' : 'reject';
        let id = $(this).data('id');

        if (!confirm(`Are you sure you want to ${action} this leave application?`)) {
            return;
        }

        $.ajax({
            url: "{{ url('/ajax/leave-management/leave-applications/status-update') }}",
            type: 'POST',
            data: {
                id: id,
                status: isApprove ? 5 : 41, // 5 = Approved, 41 = Rejected
                _token: '{{ csrf_token() }}'
            },
            success: function(res) {
                if (res.success) {
                    toastr.success(`Leave application ${action}d successfully.`);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    toastr.error(res.error || 'Failed to update status');
                }
            },
            error: function() {
                toastr.error('Server error updating status');
            }
        });
    });
});
</script>

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