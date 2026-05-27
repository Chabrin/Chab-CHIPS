@extends('layouts.main')

@section('styles')
<style>
#extraInfoPanel {
    animation: fadeSlideIn 0.18s ease;
}
@keyframes fadeSlideIn {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.info-toggle-btn {
    background: none;
    border: none;
    padding: 0;
    line-height: 1;
    cursor: pointer;
}
.info-toggle-btn .material-symbols-outlined {
    font-size: 30px;
    color: #3598db;
    font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
    transition: color 0.15s;
}
.info-toggle-btn:hover .material-symbols-outlined,
.info-toggle-btn.active .material-symbols-outlined {
    color: #1a6fa8;
    font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
.hdr-label {
    font-size: 0.67rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #8e9bae;
    margin-bottom: 2px;
}
.hdr-value {
    font-size: 0.85rem;
    color: #222;
}
.section-divider {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    color: #3598db;
    margin-bottom: 8px;
}
.extra-label {
    font-size: 0.67rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #8e9bae;
    margin-bottom: 2px;
}
.extra-value {
    font-size: 0.82rem;
    color: #333;
}
#lines-table td, #lines-table th { font-size: 0.8rem; }

/* Approval Timeline Styles */
.approval-timeline {
    position: relative;
    padding-left: 28px;
}
.approval-timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}
.timeline-item {
    position: relative;
    padding-bottom: 20px;
}
.timeline-item:last-child {
    padding-bottom: 0;
}
.timeline-item::before {
    content: '';
    position: absolute;
    left: -22px;
    top: 4px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid #dee2e6;
}
.timeline-item.action-submitted::before { border-color: #17a2b8; background: #e3f2fd; }
.timeline-item.action-approved::before { border-color: #28a745; background: #d4edda; }
.timeline-item.action-rejected::before { border-color: #dc3545; background: #f8d7da; }
.timeline-item.action-changes_requested::before { border-color: #ffc107; background: #fff3cd; }
.timeline-item.action-returned::before { border-color: #6c757d; background: #e9ecef; }
.timeline-item.action-resubmitted::before { border-color: #17a2b8; background: #e3f2fd; }
.timeline-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}
.timeline-approver {
    font-weight: 600;
    font-size: 0.85rem;
    color: #333;
}
.timeline-badge {
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 4px;
}
.timeline-level {
    font-size: 0.7rem;
    color: #666;
    background: #f1f3f5;
    padding: 2px 6px;
    border-radius: 4px;
}
.timeline-date {
    font-size: 0.75rem;
    color: #888;
}
.timeline-comment {
    font-size: 0.82rem;
    color: #555;
    background: #f8f9fa;
    padding: 8px 12px;
    border-radius: 6px;
    margin-top: 6px;
    border-left: 3px solid #dee2e6;
}
.approval-status-card {
    border-left: 4px solid #dee2e6;
}
.approval-status-card.status-pending { border-left-color: #ffc107; }
.approval-status-card.status-in_progress { border-left-color: #17a2b8; }
.approval-status-card.status-approved { border-left-color: #28a745; }
.approval-status-card.status-rejected { border-left-color: #dc3545; }
.approval-status-card.status-changes_requested { border-left-color: #fd7e14; }
.approval-actions-wrap {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">

                <!-- Page header -->
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="mb-0 text-muted">Voucher Details</h6>
                    <a href="{{ route('payment.vouchers.index') }}" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size:16px;">arrow_back</span> Back
                    </a>
                </div>

                <!-- Summary Card -->
                <div class="card mb-3">
                    <div class="card-body py-3 position-relative">

                        <!-- Info icon — top right -->
                        <button type="button" id="toggleInfoBtn" class="info-toggle-btn position-absolute" style="top:10px; right:14px;" title="More details">
                            <span class="material-symbols-outlined">tips_and_updates</span>
                        </button>

                        <!-- Primary fields -->
                        <div class="row g-3 align-items-end pe-5">
                            <div class="col-auto">
                                <div class="hdr-label">Voucher No.</div>
                                <div class="hdr-value fw-bold">{{ $voucher->voucher_number }}</div>
                            </div>
                            <div class="col-auto">
                                <div class="hdr-label">Date</div>
                                <div class="hdr-value">{{ $voucher->voucher_date?->format('d/m/Y') ?? '-' }}</div>
                            </div>
                            <div class="col-auto">
                                <div class="hdr-label">Payee Type</div>
                                <div class="hdr-value">
                                    <span class="badge badge-light-info">{{ $voucher->vendor_type ?? '-' }}</span>
                                </div>
                            </div>
                            <div class="col">
                                <div class="hdr-label">Payee</div>
                                <div class="hdr-value fw-semibold" title="{{ $voucher->vendor_id }}">{{ $voucher->vendor_name ?? '-' }}</div>
                            </div>
                            <div class="col-auto">
                                <div class="hdr-label">Amount</div>
                                <div class="hdr-value fw-bold">{{ number_format($voucher->debit_amount ?? 0, 2) }}</div>
                            </div>
                            <div class="col-auto">
                                <div class="hdr-label">Status</div>
                                <div class="hdr-value">
                                    @if($voucher->voucher_status === 'Paid')
                                    <span class="badge badge-light-success">Paid</span>
                                    @else
                                    <span class="badge badge-light-danger">Pending</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Extra info panel -->
                        <div id="extraInfoPanel" class="border-top mt-3 pt-3" style="display:none;">
                            <div class="row g-0">

                                <!-- Left: Voucher meta -->
                                <div class="col-md-7 pe-4">
                                    <div class="section-divider">Voucher Info</div>
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <div class="extra-label">Description</div>
                                            <div class="extra-value">{{ $voucher->description ?? '-' }}</div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="extra-label">Prepared By</div>
                                            <div class="extra-value">{{ $voucher->prepared_by ?? '-' }}</div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="extra-label">Checked By</div>
                                            <div class="extra-value">{{ $voucher->checked_by ?? '-' }}</div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="extra-label">Check Status</div>
                                            <div class="extra-value">{{ $voucher->checked_status ?? 'No' }}</div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="extra-label">Auth. By</div>
                                            <div class="extra-value">{{ $voucher->authorized_by ?? '-' }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Divider -->
                                <div class="col-auto d-none d-md-flex px-3">
                                    <div style="width:1px; background:#e9ecef;"></div>
                                </div>

                                <!-- Right: Payee details -->
                                <div class="col-md-4 ps-md-3 pt-3 pt-md-0">
                                    <div class="section-divider">Payee Details</div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="extra-label">Name</div>
                                            <div class="extra-value fw-semibold">{{ $voucher->vendor_name ?? '-' }}</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="extra-label">Type</div>
                                            <div class="extra-value">{{ $voucher->vendor_type ?? '-' }}</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="extra-label">Ref / ID</div>
                                            <div class="extra-value">{{ $voucher->vendor_id ?? '-' }}</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="extra-label">Month / Year</div>
                                            <div class="extra-value">
                                                @php
                                                    $monthName = \Carbon\Carbon::createFromDate(null, $voucher->month, null)->format('F');
                                                @endphp
                                                {{ $monthName }} {{ $voucher->year }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>

                <div class="row">
                    <!-- Lines Table -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header card-no-border d-flex align-items-center justify-content-between py-2">
                                <h6 class="mb-0">Voucher Lines</h6>
                                <small class="text-muted" id="lines_info" style="display:none;"><strong id="lines_count">0</strong> entries</small>
                            </div>
                            <div class="card-body pt-0">
                                <div class="table-responsive custom-scrollbar">
                                    <table class="display" id="lines-table" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Description</th>
                                                <th>Payee</th>
                                                <th>Source Account No.</th>
                                                <th>Source Bank / Account</th>
                                                <th>Cheque No.</th>
                                                <th class="text-end">Amount</th>
                                                <th>Approved</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Approval Timeline -->
                    <div class="col-lg-4">
                        <div class="card approval-status-card" id="approvalCard" style="display:none;">
                            <div class="card-header card-no-border py-2">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h6 class="mb-0">Approval Status</h6>
                                    <span class="badge" id="approvalStatusBadge">-</span>
                                </div>
                            </div>
                            <div class="card-body pt-0">
                                <!-- Current Status Info -->
                                <div class="mb-3" id="approvalCurrentInfo">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <small class="text-muted">Current Level:</small>
                                        <span class="fw-semibold" id="currentLevelText">-</span>
                                        <small class="text-muted">of</small>
                                        <span class="fw-semibold" id="maxLevelText">-</span>
                                    </div>
                                    <div id="currentApproverInfo" style="display:none;">
                                        <small class="text-muted">Awaiting approval from:</small>
                                        <div class="fw-semibold" id="currentApproverName">-</div>
                                    </div>
                                </div>

                                <!-- Action Buttons (for approvers) -->
                                <div class="approval-actions-wrap mb-3" id="approvalActions" style="display:none;">
                                    <button type="button" class="btn btn-success btn-sm" id="btnApprove">
                                        <span class="material-symbols-outlined" style="font-size:16px;">check</span> Approve
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" id="btnReject">
                                        <span class="material-symbols-outlined" style="font-size:16px;">close</span> Reject
                                    </button>
                                    <button type="button" class="btn btn-warning btn-sm" id="btnRequestChanges">
                                        <span class="material-symbols-outlined" style="font-size:16px;">edit_note</span> Request Changes
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm" id="btnReturn" style="display:none;">
                                        <span class="material-symbols-outlined" style="font-size:16px;">undo</span> Return
                                    </button>
                                </div>

                                <!-- Resubmit Button (for initiator) -->
                                <div class="mb-3" id="resubmitActions" style="display:none;">
                                    <button type="button" class="btn btn-primary btn-sm" id="btnResubmit">
                                        <span class="material-symbols-outlined" style="font-size:16px;">send</span> Resubmit for Approval
                                    </button>
                                </div>

                                <!-- Timeline -->
                                <div class="border-top pt-3">
                                    <h6 class="text-muted mb-3" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px;">Approval Trail</h6>
                                    <div class="approval-timeline" id="approvalTimeline">
                                        <div class="text-muted text-center py-3" id="timelineLoading">
                                            <small>Loading timeline...</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- No Approval Card -->
                        <div class="card" id="noApprovalCard" style="display:none;">
                            <div class="card-body text-center py-4">
                                <span class="material-symbols-outlined text-muted mb-2" style="font-size:40px;">verified_user</span>
                                <p class="text-muted mb-0">No approval workflow configured for this voucher.</p>
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
                    <select class="form-select form-select-sm" id="returnToLevel"></select>
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

        // Info icon toggle
        $('#toggleInfoBtn').on('click', function() {
            const $panel = $('#extraInfoPanel');
            const $btn   = $(this);
            $panel.toggle();
            $btn.toggleClass('active', $panel.is(':visible'));
        });

        var table = $('#lines-table').DataTable({
            data: [],
            columns: [
                {
                    data: null,
                    orderable: false,
                    render: function(d, t, r, m) { return m.row + 1; }
                },
                {
                    data: 'entry_description',
                    render: function(d) { return '<span style="font-size:0.8rem;">' + (d || '-') + '</span>'; }
                },
                {
                    data: 'payee_name',
                    render: function(d) {
                        return d && d !== '-'
                            ? '<span class="fw-semibold" style="font-size:0.8rem;">' + d + '</span>'
                            : '<span class="text-muted">—</span>';
                    }
                },
                {
                    data: 'account_number',
                    render: function(d) {
                        return d && d !== '-'
                            ? '<code style="font-size:0.78rem;">' + d + '</code>'
                            : '<span class="text-muted">—</span>';
                    }
                },
                {
                    data: 'account_bank',
                    render: function(d) {
                        return d && d !== '-'
                            ? '<small>' + d + '</small>'
                            : '<span class="text-muted">—</span>';
                    }
                },
                {
                    data: 'cheque_number',
                    render: function(d) {
                        return d && d !== '-'
                            ? d
                            : '<span class="text-muted">—</span>';
                    }
                },
                {
                    data: 'amount',
                    className: 'text-end fw-bold'
                },
                {
                    data: 'is_approved',
                    className: 'text-center',
                    render: function(d) {
                        if (d === 'Yes') return '<span class="badge badge-light-success">Yes</span>';
                        if (d === 'No')  return '<span class="badge badge-light-danger">No</span>';
                        return d || '—';
                    }
                }
            ],
            paging: true,
            info: true,
            searching: true,
            ordering: true,
            order: [[0, 'asc']],
            pageLength: 25
        });

        function loadLines() {
            $.ajax({
                url: '/ajax/payment/vouchers/{{ encrypt($voucher->id) }}/lines',
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        $('#lines_info').show();
                        $('#lines_count').text(response.count);
                        table.clear();
                        table.rows.add(response.data);
                        table.draw();
                    }
                },
                error: function() {
                    toastr.error('Failed to load voucher lines.');
                }
            });
        }

        loadLines();
        loadApprovalInfo();

        // Approval Action Handlers
        var approvalActionModal = new bootstrap.Modal(document.getElementById('approvalActionModal'));
        var currentApprovalId = null;
        var currentLevel = 1;

        $('#btnApprove').on('click', function() {
            openApprovalModal('approve', 'Approve Voucher', 'Add a comment (optional)', false);
        });

        $('#btnReject').on('click', function() {
            openApprovalModal('reject', 'Reject Voucher', 'Please provide a reason for rejection', true);
        });

        $('#btnRequestChanges').on('click', function() {
            openApprovalModal('request_changes', 'Request Changes', 'Please describe the changes required', true);
        });

        $('#btnReturn').on('click', function() {
            openApprovalModal('return', 'Return to Previous Level', 'Add a comment (optional)', false);
            populateReturnLevels();
        });

        $('#btnResubmit').on('click', function() {
            openApprovalModal('resubmit', 'Resubmit for Approval', 'Add a comment (optional)', false);
        });

        function openApprovalModal(action, title, hint, required) {
            $('#approvalAction').val(action);
            $('#approvalActionModalTitle').text(title);
            $('#approvalComment').val('');
            $('#commentHint').text(hint);
            $('#commentLabel').text(required ? 'Comment (Required)' : 'Comment (Optional)');
            $('#returnLevelWrap').toggle(action === 'return');
            approvalActionModal.show();
        }

        function populateReturnLevels() {
            var $select = $('#returnToLevel');
            $select.empty();
            for (var i = 1; i < currentLevel; i++) {
                $select.append('<option value="' + i + '">Level ' + i + '</option>');
            }
        }

        $('#confirmApprovalAction').on('click', function() {
            var action = $('#approvalAction').val();
            var comment = $('#approvalComment').val().trim();

            if ((action === 'reject' || action === 'request_changes') && !comment) {
                toastr.error('Comment is required for this action.');
                return;
            }

            var url, data = { comment: comment };

            switch(action) {
                case 'approve':
                    url = '/ajax/approval-requests/' + currentApprovalId + '/approve';
                    break;
                case 'reject':
                    url = '/ajax/approval-requests/' + currentApprovalId + '/reject';
                    break;
                case 'request_changes':
                    url = '/ajax/approval-requests/' + currentApprovalId + '/request-changes';
                    break;
                case 'return':
                    url = '/ajax/approval-requests/' + currentApprovalId + '/return';
                    data.target_level = parseInt($('#returnToLevel').val());
                    break;
                case 'resubmit':
                    url = '/ajax/approval-requests/' + currentApprovalId + '/resubmit';
                    break;
                default:
                    return;
            }

            $.ajax({
                url: url,
                type: 'POST',
                data: data,
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        approvalActionModal.hide();
                        loadApprovalInfo();
                    } else {
                        toastr.error(response.message || 'Action failed.');
                    }
                },
                error: function(xhr) {
                    var msg = xhr.responseJSON?.message || 'An error occurred.';
                    toastr.error(msg);
                }
            });
        });

        function loadApprovalInfo() {
            $.ajax({
                url: '/ajax/approval-requests/for-item',
                type: 'GET',
                data: {
                    request_type: 'voucher',
                    request_id: {{ $voucher->id }}
                },
                success: function(response) {
                    if (response.success && response.has_approval) {
                        currentApprovalId = response.encrypted_id;
                        currentLevel = response.data.current_level;
                        renderApprovalInfo(response.data);
                        $('#approvalCard').show();
                        $('#noApprovalCard').hide();
                    } else {
                        $('#approvalCard').hide();
                        $('#noApprovalCard').show();
                    }
                },
                error: function() {
                    $('#approvalCard').hide();
                    $('#noApprovalCard').show();
                }
            });
        }

        function renderApprovalInfo(data) {
            // Update status card class
            $('#approvalCard').removeClass('status-pending status-in_progress status-approved status-rejected status-changes_requested')
                .addClass('status-' + data.status);

            // Status badge
            var badgeClass = 'bg-secondary';
            if (data.status === 'approved') badgeClass = 'bg-success';
            else if (data.status === 'rejected') badgeClass = 'bg-danger';
            else if (data.status === 'pending' || data.status === 'in_progress') badgeClass = 'bg-info';
            else if (data.status === 'changes_requested') badgeClass = 'bg-warning';

            $('#approvalStatusBadge').removeClass().addClass('badge ' + badgeClass).text(data.status_label);

            // Level info
            $('#currentLevelText').text(data.current_level);
            $('#maxLevelText').text(data.max_level);

            // Current approver
            if (data.current_approver && !data.is_complete) {
                $('#currentApproverInfo').show();
                $('#currentApproverName').text(data.current_approver.name);
            } else {
                $('#currentApproverInfo').hide();
            }

            // Action buttons
            if (data.can_approve && !data.is_complete) {
                $('#approvalActions').show();
                $('#btnReturn').toggle(data.current_level > 1);
            } else {
                $('#approvalActions').hide();
            }

            // Resubmit button
            $('#resubmitActions').toggle(data.can_resubmit);

            // Timeline
            renderTimeline(data.timeline);
        }

        function renderTimeline(timeline) {
            var $timeline = $('#approvalTimeline');
            $timeline.empty();

            if (!timeline || timeline.length === 0) {
                $timeline.html('<div class="text-muted text-center py-3"><small>No activity yet.</small></div>');
                return;
            }

            timeline.forEach(function(item) {
                var levelText = item.level > 0 ? 'Level ' + item.level : 'Initiator';
                var html = `
                    <div class="timeline-item action-${item.action}">
                        <div class="timeline-header">
                            <span class="timeline-approver">${item.approver_name}</span>
                            <span class="timeline-level">${levelText}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge timeline-badge ${item.action_badge_class}">${item.action_label}</span>
                            <span class="timeline-date">${item.action_on}</span>
                        </div>
                        ${item.comment ? '<div class="timeline-comment">' + item.comment + '</div>' : ''}
                    </div>
                `;
                $timeline.append(html);
            });
        }
    });
</script>
@endsection
