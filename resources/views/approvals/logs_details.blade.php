@extends('layouts.main')

@section('content')
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Approval History Details</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 style="color: #3598db;">Document Details</h5>
                                <br>
                                <table style="font-size:13px;" class="table table-borderless">
                                    <tbody>
                                        <tr>
                                            <td><strong>Item Type:</strong></td>
                                            <td>{{ $approvalItem->item_type }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Member No:</strong></td>
                                            <td>{{ $approvalItem->member_no }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Final Status:</strong></td>
                                            <td>
                                                <span class="badge {{ $approvalItem->status === 'Approved' ? 'badge-light-success' : ($approvalItem->status === 'Rejected' ? 'badge-light-danger' : 'badge-light-warning') }}">
                                                    {{ ucfirst($approvalItem->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Requested By:</strong></td>
                                            <td>{{ $approvalItem->requested_by }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Requested On:</strong></td>
                                            <td>{{ $approvalItem->requested_on }}</td>
                                        </tr>
                                        @if($userAction->remarks)
                                        <tr>
                                            <td><strong>Your Remarks:</strong></td>
                                            <td>{{ $userAction->remarks }}</td>
                                        </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6">

                                <ul class="timeline position-relative">
                                    @foreach($approvalChain as $action)
                                    <li class="timeline-item mb-4 position-relative">
                                        <div class="d-flex align-items-center">
                                            <span style="font-size:35px !important;" class="material-symbols-outlined me-3 text-{{ $action->action_type === 'approve' ? 'success' : ($action->action_type === 'reject' ? 'danger' : 'secondary') }}">
                                                {{ $action->action_type === 'approve' ? 'order_approve' : ($action->action_type === 'reject' ? 'contract_delete' : 'pending') }}
                                            </span>
                                            <div>

                                                <p class="mb-0">Approver/Actor: <strong>{{ $action->approver_name }}</strong> (Level {{ $action->hierarchy_level }})</p>
                                                <p class="mb-0">Approval Status:
                                                    @php
                                                    $status = $action->action_type ?? 'pending';
                                                    $label = $status === 'approve' ? 'Approved' : ($status === 'reject' ? 'Rejected' : 'Pending');
                                                    $badge = $status === 'approve' ? 'badge badge-light-success' : ($status === 'reject' ? 'badge badge-light-danger' : 'badge badge-light-secondary');
                                                    @endphp
                                                    <span class="{{ $badge }} ms-2">{{ $label }}</span>
                                                </p>
                                                @if($action->remarks)
                                                <p class="mb-0"><small><em>Remarks: {{ $action->remarks }}</em></small></p>
                                                @endif
                                                @if($action->updated_at)
                                                <p class="mb-0"><small class="text-muted">{{ $action->updated_at->format('d M Y, h:i A') }}</small></p>
                                                @endif
                                            </div>
                                        </div>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="{{ route('approval.logs.index') }}" class="btn btn-secondary btn-sm d-flex align-items-center" style="width: fit-content;">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to History</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection