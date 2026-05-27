@extends('layouts.main')

@section('styles')
<style>
.info-label {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #8e9bae;
    margin-bottom: 2px;
}
.info-value {
    font-size: 0.88rem;
    color: #333;
}
.approval-timeline {
    position: relative;
    padding-left: 24px;
}
.approval-timeline::before {
    content: '';
    position: absolute;
    left: 7px;
    top: 8px;
    bottom: 8px;
    width: 2px;
    background: #dee2e6;
}
.timeline-item {
    position: relative;
    padding-bottom: 24px;
}
.timeline-item:last-child {
    padding-bottom: 0;
}
.timeline-item::before {
    content: '';
    position: absolute;
    left: -20px;
    top: 6px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid #dee2e6;
}
.timeline-item.action-submitted::before,
.timeline-item.action-resubmitted::before { border-color: #17a2b8; }
.timeline-item.action-approved::before { border-color: #28a745; }
.timeline-item.action-rejected::before { border-color: #dc3545; }
.timeline-item.action-changes_requested::before { border-color: #ffc107; }
.timeline-item.action-returned::before { border-color: #6c757d; }
.timeline-approver {
    font-weight: 600;
    font-size: 0.9rem;
    color: #333;
}
.timeline-action {
    font-size: 0.82rem;
    color: #666;
}
.timeline-date {
    font-size: 0.75rem;
    color: #999;
}
.timeline-comment {
    font-size: 0.82rem;
    color: #555;
    margin-top: 6px;
    padding-left: 12px;
    border-left: 2px solid #e9ecef;
}
.status-card {
    border-left: 4px solid #dee2e6;
}
.status-card.status-pending,
.status-card.status-in_progress { border-left-color: #17a2b8; }
.status-card.status-approved { border-left-color: #28a745; }
.status-card.status-rejected { border-left-color: #dc3545; }
.status-card.status-changes_requested { border-left-color: #fd7e14; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">

                <!-- Page header -->
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="mb-0 text-muted">Approval Request Details</h6>
                    <a href="{{ route('accounting.pending.approvals') }}" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size:16px;">arrow_back</span> Back to List
                    </a>
                </div>

                <div class="row">
                    <!-- Left: Request Info & Actions -->
                    <div class="col-lg-5">
                        <!-- Status Card -->
                        <div class="card status-card status-{{ $details['status'] }} mb-3">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h6 class="mb-0">Request Status</h6>
                                    @php
                                        $badgeClass = 'bg-secondary';
                                        if (in_array($details['status'], ['pending', 'in_progress'])) $badgeClass = 'bg-info';
                                        elseif ($details['status'] === 'approved') $badgeClass = 'bg-success';
                                        elseif ($details['status'] === 'rejected') $badgeClass = 'bg-danger';
                                        elseif ($details['status'] === 'changes_requested') $badgeClass = 'bg-warning';
                                    @endphp
                                    <span class="badge {{ $badgeClass }}">{{ $details['status_label'] }}</span>
                                </div>

                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="info-label">Type</div>
                                        <div class="info-value">{{ ucfirst($approvalRequest->request_type) }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Workflow</div>
                                        <div class="info-value">{{ $details['workflow_name'] ?? '-' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Progress</div>
                                        <div class="info-value">Level {{ $details['current_level'] }} of {{ $details['max_level'] }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Initiated By</div>
                                        <div class="info-value">{{ $details['initiated_by'] ?? '-' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Initiated On</div>
                                        <div class="info-value">{{ $details['initiated_on'] ?? '-' }}</div>
                                    </div>
                                    @if($details['completed_on'])
                                    <div class="col-6">
                                        <div class="info-label">Completed On</div>
                                        <div class="info-value">{{ $details['completed_on'] }}</div>
                                    </div>
                                    @endif
                                </div>

                                @if($details['current_approver'] && !$details['is_complete'])
                                <div class="mt-3 pt-3 border-top">
                                    <div class="info-label">Current Approver</div>
                                    <div class="info-value">
                                        <span class="material-symbols-outlined text-primary" style="font-size:18px;vertical-align:middle;">person</span>
                                        {{ $details['current_approver']['name'] }}
                                        <small class="text-muted">({{ $details['current_approver']['email'] }})</small>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>

                        <!-- View Item -->
                        @if($approvalRequest->request_type === 'voucher')
                        <div class="card mb-3">
                            <div class="card-body py-3">
                                <a href="/payment/vouchers/{{ encrypt($approvalRequest->request_id) }}/details" class="btn btn-outline-primary btn-sm" target="_blank">
                                    <span class="material-symbols-outlined" style="font-size:16px;">open_in_new</span>
                                    View Payment Voucher
                                </a>
                            </div>
                        </div>
                        @endif

                        <!-- Action Buttons -->
                        @if($canApprove && !$details['is_complete'])
                        <div class="card">
                            <div class="card-header py-2">
                                <h6 class="mb-0">Actions</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-success btn-sm" id="btnApprove">
                                        <span class="material-symbols-outlined" style="font-size:16px;">check</span> Approve
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" id="btnReject">
                                        <span class="material-symbols-outlined" style="font-size:16px;">close</span> Reject
                                    </button>
                                    <button type="button" class="btn btn-warning btn-sm" id="btnRequestChanges">
                                        <span class="material-symbols-outlined" style="font-size:16px;">edit_note</span> Request Changes
                                    </button>
                                    @if($details['current_level'] > 1)
                                    <button type="button" class="btn btn-secondary btn-sm" id="btnReturn">
                                        <span class="material-symbols-outlined" style="font-size:16px;">undo</span> Return
                                    </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif

                        @if($canResubmit)
                        <div class="card">
                            <div class="card-body">
                                <p class="text-muted mb-2">Changes have been requested. You can resubmit after making the necessary changes.</p>
                                <button type="button" class="btn btn-primary btn-sm" id="btnResubmit">
                                    <span class="material-symbols-outlined" style="font-size:16px;">send</span> Resubmit for Approval
                                </button>
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Right: Timeline -->
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-header py-2">
                                <h6 class="mb-0">Approval Trail</h6>
                            </div>
                            <div class="card-body">
                                @if(count($details['timeline']) > 0)
                                <div class="approval-timeline">
                                    @foreach($details['timeline'] as $item)
                                    <div class="timeline-item action-{{ $item['action'] }}">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <span class="timeline-approver">{{ $item['approver_name'] }}</span>
                                                @if($item['level'] > 0)
                                                <span class="text-muted" style="font-size:0.78rem;">(Level {{ $item['level'] }})</span>
                                                @endif
                                            </div>
                                            <span class="timeline-date">{{ $item['action_on'] }}</span>
                                        </div>
                                        <div class="timeline-action">{{ $item['action_label'] }}</div>
                                        @if($item['comment'])
                                        <div class="timeline-comment">{{ $item['comment'] }}</div>
                                        @endif
                                    </div>
                                    @endforeach
                                </div>
                                @else
                                <p class="text-muted text-center py-4 mb-0">No activity recorded yet.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Approval Action Modal -->
<div class="modal fade" id="approvalActionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approvalActionModalTitle">Approval Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="approvalAction">
                <div class="mb-3" id="returnLevelWrap" style="display:none;">
                    <label class="form-label">Return to Level</label>
                    <select class="form-select form-select-sm" id="returnToLevel">
                        @for($i = 1; $i < $details['current_level']; $i++)
                        <option value="{{ $i }}">Level {{ $i }}</option>
                        @endfor
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" id="commentLabel">Comment</label>
                    <textarea class="form-control" id="approvalComment" rows="3" placeholder="Enter your comment..."></textarea>
                    <small class="text-muted" id="commentHint"></small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="confirmApprovalAction">Confirm</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    var approvalActionModal = new bootstrap.Modal(document.getElementById('approvalActionModal'));
    var encryptedId = '{{ $encryptedId }}';

    $('#btnApprove').on('click', function() {
        openActionModal('approve', 'Approve Request', 'Add a comment (optional)', false);
    });

    $('#btnReject').on('click', function() {
        openActionModal('reject', 'Reject Request', 'Please provide a reason for rejection', true);
    });

    $('#btnRequestChanges').on('click', function() {
        openActionModal('request_changes', 'Request Changes', 'Please describe the changes required', true);
    });

    $('#btnReturn').on('click', function() {
        openActionModal('return', 'Return to Previous Level', 'Add a comment (optional)', false);
    });

    $('#btnResubmit').on('click', function() {
        openActionModal('resubmit', 'Resubmit for Approval', 'Add a comment (optional)', false);
    });

    function openActionModal(action, title, hint, required) {
        $('#approvalAction').val(action);
        $('#approvalActionModalTitle').text(title);
        $('#approvalComment').val('');
        $('#commentHint').text(hint);
        $('#commentLabel').text(required ? 'Comment (Required)' : 'Comment (Optional)');
        $('#returnLevelWrap').toggle(action === 'return');
        approvalActionModal.show();
    }

    $('#confirmApprovalAction').on('click', function() {
        var action = $('#approvalAction').val();
        var comment = $('#approvalComment').val().trim();

        if ((action === 'reject' || action === 'request_changes') && !comment) {
            toastr.error('Comment is required for this action.');
            return;
        }

        var url, data = { 
            comment: comment,
            _token: '{{ csrf_token() }}'
        };

        switch(action) {
            case 'approve':
                url = '/ajax/approval-requests/' + encryptedId + '/approve';
                break;
            case 'reject':
                url = '/ajax/approval-requests/' + encryptedId + '/reject';
                break;
            case 'request_changes':
                url = '/ajax/approval-requests/' + encryptedId + '/request-changes';
                break;
            case 'return':
                url = '/ajax/approval-requests/' + encryptedId + '/return';
                data.target_level = parseInt($('#returnToLevel').val());
                break;
            case 'resubmit':
                url = '/ajax/approval-requests/' + encryptedId + '/resubmit';
                break;
            default:
                return;
        }

        $('#confirmApprovalAction').prop('disabled', true).text('Processing...');

        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    approvalActionModal.hide();
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    toastr.error(response.message || 'Action failed.');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || 'An error occurred.';
                toastr.error(msg);
            },
            complete: function() {
                $('#confirmApprovalAction').prop('disabled', false).text('Confirm');
            }
        });
    });
});
</script>
@endsection
