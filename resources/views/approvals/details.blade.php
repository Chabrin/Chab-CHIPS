@extends('layouts.main')

@section('content')
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Approval Document Details</h4>
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
                                            <td><b>{{ $approvalItem->status }}</b></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Requested By:</strong></td>
                                            <td>{{ $approvalItem->requested_by }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Requested On:</strong></td>
                                            <td>{{ $approvalItem->requested_on }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <br>
                                <ul class="timeline position-relative">
                                    @foreach($approvalChain as $action)
                                    <li class="timeline-item mb-4 position-relative">
                                        <div class="d-flex align-items-center">
                                            <span style="font-size:35px !important; " class="material-symbols-outlined me-3 text-{{ $action->action_type === 'approve' ? 'success' : ($action->action_type === 'reject' ? 'danger' : 'secondary') }}" style="color: #3598db;">
                                                {{ $action->action_type === 'approve' ? 'order_approve' : ($action->action_type === 'reject' ? 'contract_delete' : 'pending') }}
                                            </span>
                                            <div>
                                                <p class="mb-0">Level: {{ $action->hierarchy_level }}</p>
                                                <p class="mb-0">Approver/Actor: <strong>{{ $action->approver_name }}</strong></p>
                                                <p class="mb-0">Approval Status:
                                                    @php
                                                    $status = $action->action_type ?? 'pending';
                                                    $label = $status === 'approve' ? 'Approved' : ($status === 'reject' ? 'Rejected' : 'Pending');
                                                    $badge = $status === 'approve' ? 'badge badge-light-success' : 'badge badge-light-danger';
                                                    @endphp
                                                    <span class="{{ $badge }} ms-2">{{ $label }}</span>
                                                </p>
                                            </div>
                                        </div>

                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        <div class="mt-4 ms-auto d-flex gap-2">
                            <button type="button" class="btn btn-secondary btn-sm d-flex align-items-center" onclick="window.location.href='/approval/requests'" style="padding:.25rem .4rem;font-size:.78rem;">
                                <span class="material-symbols-outlined" style="font-size:18px">arrow_back</span>
                                <span class="ms-1">Go Back</span>
                            </button>

                            @if(!$userAction || !$userAction->action_type)
                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#approveModal" style="padding:.25rem .4rem;font-size:.78rem;">
                                <span class="material-symbols-outlined" style="font-size:18px">check_circle</span>
                                <span class="ms-1">Approve</span>
                            </button>

                            <button type="button" class="btn btn-danger btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#rejectModal" style="padding:.25rem .4rem;font-size:.78rem;">
                                <span class="material-symbols-outlined" style="font-size:18px">cancel</span>
                                <span class="ms-1">Reject</span>
                            </button>
                            @else
                            <div class="alert alert-light-success mb-0 d-flex align-items-center" style="padding:.5rem 1rem;">
                                <span class="material-symbols-outlined me-2">info</span>
                                <span>You have already <strong>{{ $userAction->action_type }}d</strong> this request.</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approveModalLabel">Approve Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="approveForm">
                    <div class="mb-3">
                        <label for="approveReason" class="form-label">Reason</label>
                        <textarea class="form-control" id="approveReason" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center" id="approveButton">
                        <span class="material-symbols-outlined">check_circle</span>
                        <span class="ms-1">Submit</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectModalLabel">Reject Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="rejectForm">
                    <div class="mb-3">
                        <label for="rejectReason" class="form-label">Reason</label>
                        <textarea class="form-control" id="rejectReason" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger btn-sm d-flex align-items-center" id="rejectButton">
                        <span class="material-symbols-outlined">cancel</span>
                        <span class="ms-1">Submit</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Configure toastr options
        toastr.options = {
            "closeButton": true,
            "debug": false,
            "newestOnTop": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": false,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };
        $('#approveForm').on('submit', function(e) {
            e.preventDefault();
            const reason = $('#approveReason').val();
            if (!reason) {
                toastr.error('Please provide a reason');
                return;
            }
            const approvalItemId = '{{ $approvalItem->id }}';
            $('#approveButton').html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...').prop('disabled', true);

            // Submit approval via AJAX
            $.ajax({
                url: '/approval/action',
                method: 'POST',
                data: {
                    action_type: 'approve',
                    remarks: reason,
                    approval_item_id: approvalItemId,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success('Request approved successfully!');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        toastr.error(response.error || 'Failed to approve request.');
                        $('#approveButton').html('<span class="material-symbols-outlined">check_circle</span><span class="ms-1">Submit</span>').prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    const errorMsg = xhr.responseJSON?.error || 'Failed to approve request.';
                    toastr.error(errorMsg);
                    $('#approveButton').html('<span class="material-symbols-outlined">check_circle</span><span class="ms-1">Submit</span>').prop('disabled', false);
                }
            });
        });

        $('#rejectForm').on('submit', function(e) {
            e.preventDefault();
            const reason = $('#rejectReason').val();
            if (!reason) {
                toastr.error('Please provide a reason');
                return;
            }
            const approvalItemId = '{{ $approvalItem->id }}';
            $('#rejectButton').html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...').prop('disabled', true);

            // Submit rejection via AJAX
            $.ajax({
                url: '/approval/action',
                method: 'POST',
                data: {
                    action_type: 'reject',
                    remarks: reason,
                    approval_item_id: approvalItemId,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success('Request rejected successfully!');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        toastr.error(response.error || 'Failed to reject request.');
                        $('#rejectButton').html('<span class="material-symbols-outlined">cancel</span><span class="ms-1">Submit</span>').prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    const errorMsg = xhr.responseJSON?.error || 'Failed to reject request.';
                    toastr.error(errorMsg);
                    $('#rejectButton').html('<span class="material-symbols-outlined">cancel</span><span class="ms-1">Submit</span>').prop('disabled', false);
                }
            });
        });
    });
</script>
@endsection