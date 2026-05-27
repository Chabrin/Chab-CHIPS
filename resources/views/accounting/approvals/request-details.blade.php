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
    padding-left: 32px;
}
.approval-timeline::before {
    content: '';
    position: absolute;
    left: 9px;
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
.timeline-icon {
    position: absolute;
    left: -28px;
    top: 2px;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.timeline-icon .material-symbols-outlined {
    font-size: 18px;
}
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
                                    <div class="info-label">Current Approver Stage</div>
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
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnViewVoucher" data-id="{{ encrypt($approvalRequest->request_id) }}">
                                    <span class="material-symbols-outlined" style="font-size:16px;">receipt_long</span>
                                    View Voucher Summary
                                </button>
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
                                    <button type="button" class="btn btn-primary btn-sm" id="btnApprove">
                                        <span class="material-symbols-outlined" style="font-size:16px;">check</span> Approve
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm" id="btnReject">
                                        <span class="material-symbols-outlined" style="font-size:16px;">close</span> Reject
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm" id="btnRequestChanges">
                                        <span class="material-symbols-outlined" style="font-size:16px;">edit_note</span> Request Changes
                                    </button>
                                    @if($details['current_level'] > 1)
                                    <button type="button" class="btn btn-primary btn-sm" id="btnReturn">
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
                                    <div class="timeline-item">
                                        <div class="timeline-icon">
                                            @if($item['action'] === 'approved')
                                            <span class="material-symbols-outlined text-success">check_circle</span>
                                            @elseif($item['action'] === 'rejected')
                                            <span class="material-symbols-outlined text-danger">cancel</span>
                                            @elseif($item['action'] === 'changes_requested')
                                            <span class="material-symbols-outlined text-warning">edit_note</span>
                                            @elseif($item['action'] === 'returned')
                                            <span class="material-symbols-outlined text-secondary">undo</span>
                                            @elseif($item['action'] === 'submitted' || $item['action'] === 'resubmitted')
                                            <span class="material-symbols-outlined text-info">send</span>
                                            @else
                                            <span class="material-symbols-outlined text-muted">circle</span>
                                            @endif
                                        </div>
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

<!-- Voucher Summary Modal -->
<div class="modal fade" id="voucherSummaryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <span class="material-symbols-outlined" style="font-size:20px;vertical-align:middle;">receipt_long</span>
                    Voucher Summary
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="voucherLoading" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <span class="ms-2">Loading...</span>
                </div>
                <div id="voucherContent" style="display:none;">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <small class="text-muted d-block">Voucher No.</small>
                            <strong id="voucherNumber">-</strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Date</small>
                            <span id="voucherDate">-</span>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Payee Type</small>
                            <span id="voucherVendorType">-</span>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Total Amount</small>
                            <strong id="voucherAmount">-</strong>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <small class="text-muted d-block">Payee</small>
                            <span id="voucherVendorName">-</span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Description</small>
                            <span id="voucherDescription">-</span>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <small class="text-muted d-block">Prepared By</small>
                            <span id="voucherPreparedBy">-</span>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Status</small>
                            <span id="voucherStatus">-</span>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Entries</small>
                            <span id="voucherEntryCount">-</span>
                        </div>
                    </div>
                    <hr>
                    <h6 class="mb-3">Voucher Lines</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered" id="voucherLinesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Description</th>
                                    <th>Payee</th>
                                    <th>Account</th>
                                    <th>Cheque No.</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
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

    // Voucher Summary Modal
    var voucherSummaryModal = new bootstrap.Modal(document.getElementById('voucherSummaryModal'));

    $('#btnViewVoucher').on('click', function() {
        var voucherId = $(this).data('id');
        $('#voucherLoading').show();
        $('#voucherContent').hide();
        voucherSummaryModal.show();

        $.ajax({
            url: '/ajax/payment/vouchers/' + voucherId + '/lines',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#voucherNumber').text(response.header.voucher_number);
                    $('#voucherDate').text(response.header.voucher_date);
                    $('#voucherVendorType').text(response.header.vendor_type || '-');
                    $('#voucherAmount').text(response.header.debit_amount);
                    $('#voucherVendorName').text(response.header.vendor_name || '-');
                    $('#voucherDescription').text(response.header.description || '-');
                    $('#voucherPreparedBy').text(response.header.prepared_by || '-');
                    $('#voucherStatus').text(response.header.voucher_status || '-');
                    $('#voucherEntryCount').text(response.count);

                    var $tbody = $('#voucherLinesTable tbody');
                    $tbody.empty();
                    if (response.data && response.data.length > 0) {
                        response.data.forEach(function(line, idx) {
                            $tbody.append(
                                '<tr>' +
                                '<td>' + (idx + 1) + '</td>' +
                                '<td>' + (line.entry_description || '-') + '</td>' +
                                '<td>' + (line.payee_name || '-') + '</td>' +
                                '<td>' + (line.account_number || '-') + '</td>' +
                                '<td>' + (line.cheque_number || '-') + '</td>' +
                                '<td class="text-end">' + (line.amount || '0.00') + '</td>' +
                                '</tr>'
                            );
                        });
                    } else {
                        $tbody.append('<tr><td colspan="6" class="text-center text-muted">No lines</td></tr>');
                    }

                    $('#voucherLoading').hide();
                    $('#voucherContent').show();
                } else {
                    toastr.error('Failed to load voucher details');
                    voucherSummaryModal.hide();
                }
            },
            error: function(xhr) {
                toastr.error('Failed to load voucher details');
                voucherSummaryModal.hide();
            }
        });
    });
});
</script>
@endsection
