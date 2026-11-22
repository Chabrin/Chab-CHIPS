@extends('layouts.main')

@section('styles')
<style>
.nav-tabs .nav-link {
    color: #666;
    font-weight: 500;
}
.nav-tabs .nav-link.active {
    color: #3598db;
    font-weight: 600;
}
a.view-link {
    text-decoration: underline;
    color: #3598db;
    cursor: pointer;
    background: none;
    border: none;
    padding: 0;
    font-size: inherit;
}
a.view-link:hover {
    color: #1a6fa8;
}
/* Details modal styles */
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
    padding-left: 30px;
}
.approval-timeline::before {
    content: '';
    position: absolute;
    left: 9px;
    top: 6px;
    bottom: 6px;
    width: 2px;
    background: #e9ecef;
}
.timeline-item {
    position: relative;
    padding-bottom: 12px;
}
.timeline-item:last-child { padding-bottom: 0; }
.timeline-icon {
    position: absolute;
    left: -26px;
    top: 0;
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.timeline-icon .material-symbols-outlined { font-size: 20px; color: #3598db; }
.timeline-approver { font-weight: 600; font-size: 0.82rem; color: #333; }
.timeline-action { font-size: 0.75rem; color: #777; }
.timeline-date { font-size: 0.7rem; color: #999; }
.timeline-comment {
    font-size: 0.75rem;
    color: #666;
    margin-top: 4px;
    padding-left: 10px;
    border-left: 2px solid #e9ecef;
}
.status-card { border-left: 4px solid #dee2e6; }
.status-card.status-pending,
.status-card.status-in_progress { border-left-color: #17a2b8; }
.status-card.status-approved { border-left-color: #28a745; }
.status-card.status-rejected { border-left-color: #dc3545; }
.status-card.status-changes_requested { border-left-color: #fd7e14; }
/* Detail cards: one header style, equal-height columns, buttons with their icon centred */
/* The theme puts a 12px !important bottom margin on every .card, so the stack's spacing has to be !important too */
#approvalDetailsModal .card { margin-bottom: 0 !important; }
#approvalDetailsModal .col-lg-5 > .card { width: 100%; }
#approvalDetailsModal .col-lg-5 > .card + .card { margin-top: 1rem; }
#approvalDetailsModal .status-card { margin-bottom: 1rem !important; }
#approvalDetailsModal .card-header { padding: 0.6rem 1rem; }
#approvalDetailsModal .card-header h6 { margin: 0; }
#approvalDetailsModal .card-body { padding: 1rem; }
.approval-trail-body { max-height: 380px; overflow-y: auto; }
.approval-action-btn, #btnViewVoucherModal { display: inline-flex; align-items: center; gap: 4px; }
.approval-action-btn .material-symbols-outlined, #btnViewVoucherModal .material-symbols-outlined { font-size: 16px; }
.info-value { word-break: break-word; }
/* The action and voucher modals open over the details modal, so they sit above it and its backdrop. */
#approvalActionModal, #voucherSummaryModal { z-index: 1065; }
.modal-backdrop + .modal-backdrop { z-index: 1060; }
#voucherLinesTable th, #voucherLinesTable td { vertical-align: middle; }
/* On desktop the sidebar is a fixed panel the modals open over, so they centre in the content area beside it, not the whole window. */
@media (min-width: 992px) {
    #approvalDetailsModal, #approvalActionModal, #voucherSummaryModal { padding-left: var(--sidebar-width, 254px); }
}
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <h5 class="mb-3">
                            <span class="material-symbols-outlined" style="font-size:22px;vertical-align:middle;">pending_actions</span>
                            Pending Approvals
                        </h5>
                        <ul class="nav nav-tabs" id="approvalTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="current-tab" data-bs-toggle="tab" data-bs-target="#current" type="button" role="tab">
                                    Current
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="historical-tab" data-bs-toggle="tab" data-bs-target="#historical" type="button" role="tab">
                                    Historical
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="approvalTabsContent">
                            <!-- Current Tab -->
                            <div class="tab-pane fade show active" id="current" role="tabpanel">
                                <div class="table-responsive custom-scrollbar">
                                    <table class="display" id="currentTable" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>Voucher No.</th>
                                                <th>Amount</th>
                                                <th>Payee</th>
                                                <th>Stage</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Historical Tab -->
                            <div class="tab-pane fade" id="historical" role="tabpanel">
                                <div class="table-responsive custom-scrollbar">
                                    <table class="display" id="historicalTable" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>Voucher No.</th>
                                                <th>Amount</th>
                                                <th>Payee</th>
                                                <th>Stage</th>
                                                <th>My Status</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approval Details Modal -->
<div class="modal fade" id="approvalDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title">
                    <span class="material-symbols-outlined" style="font-size:20px;vertical-align:middle;">pending_actions</span>
                    Approval Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="approvalDetailsBody">
                <div class="text-center py-5" id="detailsLoading">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <span class="ms-2">Loading...</span>
                </div>
                <div id="detailsContent" style="display:none;"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Approval Action Modal (nested) -->
<div class="modal fade" id="approvalActionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approvalActionModalTitle">Approval Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="approvalAction">
                <input type="hidden" id="approvalEncryptedId">
                <input type="hidden" id="approvalReturnLevel">
                <div class="mb-3" id="sendBackChoices" style="display:none;">
                    <label class="form-label">What should happen to it?</label>
                    <div id="sendBackOptions"></div>
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
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
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
                            <div class="info-label">Voucher No.</div>
                            <div class="info-value fw-semibold" id="voucherNumber">-</div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-label">Date</div>
                            <div class="info-value" id="voucherDate">-</div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-label">Payee Type</div>
                            <div class="info-value" id="voucherVendorType">-</div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-label">Total Amount</div>
                            <div class="info-value fw-semibold" id="voucherAmount">-</div>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="info-label">Payee</div>
                            <div class="info-value" id="voucherVendorName">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Description</div>
                            <div class="info-value" id="voucherDescription">-</div>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="info-label">Prepared By</div>
                            <div class="info-value" id="voucherPreparedBy">-</div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Status</div>
                            <div class="info-value" id="voucherStatus">-</div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Entries</div>
                            <div class="info-value" id="voucherEntryCount">-</div>
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
    var approvalDetailsModal = new bootstrap.Modal(document.getElementById('approvalDetailsModal'));
    var approvalActionModal = new bootstrap.Modal(document.getElementById('approvalActionModal'));
    var voucherSummaryModal = new bootstrap.Modal(document.getElementById('voucherSummaryModal'));

    // ── Tables ────────────────────────────────────────────────────────────
    var currentTable = $('#currentTable').DataTable({
        data: [],
        columns: [
            { data: 'voucher_number', render: (d) => d || '-' },
            { data: 'amount', render: (d) => d || '-' },
            { data: 'payee', render: (d) => d || '-' },
            { data: 'current_level_name', render: (d) => d || '-' },
            { data: 'initiated_on', render: (d) => d || '-' },
            {
                data: null,
                orderable: false,
                render: function(d, t, r) {
                    return '<a class="view-link open-details" data-id="' + r.encrypted_id + '" data-type="' + r.request_type + '" data-rid="' + r.encrypted_request_id + '">View</a>';
                }
            }
        ],
        paging: true,
        info: true,
        searching: true,
        ordering: true,
        order: [[4, 'desc']],
        pageLength: 25,
        language: { emptyTable: "No data" }
    });

    var historicalTable = $('#historicalTable').DataTable({
        data: [],
        columns: [
            { data: 'voucher_number', render: (d) => d || '-' },
            { data: 'amount', render: (d) => d || '-' },
            { data: 'payee', render: (d) => d || '-' },
            { data: 'current_level_name', render: (d) => d || '-' },
            {
                data: 'stage',
                render: function(d) {
                    if (d === 'approved') return '<span class="text-success">Approved</span>';
                    if (d === 'rejected') return '<span class="text-danger">Rejected</span>';
                    if (d === 'passed') return '<span class="text-muted">Passed my level</span>';
                    return '<span class="text-muted">Not yet reached</span>';
                }
            },
            { data: 'initiated_on', render: (d) => d || '-' },
            {
                data: null,
                orderable: false,
                render: function(d, t, r) {
                    return '<a class="view-link open-details" data-id="' + r.encrypted_id + '" data-type="' + r.request_type + '" data-rid="' + r.encrypted_request_id + '">View</a>';
                }
            }
        ],
        paging: true,
        info: true,
        searching: true,
        ordering: true,
        order: [[5, 'desc']],
        pageLength: 25,
        language: { emptyTable: "No data" }
    });

    // ── Load functions ─────────────────────────────────────────────────────
    function loadCurrentApprovals() {
        $.ajax({
            url: '{{ route("ajax.approval-requests.pending") }}',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    currentTable.clear();
                    if (response.data && response.data.length > 0) {
                        currentTable.rows.add(response.data);
                    }
                    currentTable.draw();
                }
            },
            error: function() { toastr.error('Failed to load pending approvals'); }
        });
    }

    function loadHistoricalApprovals() {
        $.ajax({
            url: '{{ route("ajax.approval-requests.historical") }}',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    historicalTable.clear();
                    if (response.data && response.data.length > 0) {
                        historicalTable.rows.add(response.data);
                    }
                    historicalTable.draw();
                }
            },
            error: function() { toastr.error('Failed to load historical approvals'); }
        });
    }

    loadCurrentApprovals();
    loadHistoricalApprovals();

    // ── Open Details Modal ─────────────────────────────────────────────────
    $(document).on('click', '.open-details', function() {
        var encryptedId = $(this).data('id');
        var requestType = $(this).data('type');
        var encryptedRid = $(this).data('rid');

        $('#detailsLoading').show();
        $('#detailsContent').hide().empty();
        approvalDetailsModal.show();

        $.ajax({
            url: '/ajax/approval-requests/' + encryptedId,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (!response.success) {
                    toastr.error(response.message || 'Failed to load details');
                    approvalDetailsModal.hide();
                    return;
                }
                var d = response.data;
                $('#detailsContent').html(buildDetailsHtml(d, encryptedId, requestType, encryptedRid));
                $('#detailsLoading').hide();
                $('#detailsContent').show();
            },
            error: function() {
                toastr.error('Failed to load approval details');
                approvalDetailsModal.hide();
            }
        });
    });

    // ── Build Details HTML ─────────────────────────────────────────────────
    function buildDetailsHtml(d, encryptedId, requestType, encryptedRid) {
        // Status badge
        var badgeClass = 'bg-secondary';
        if (d.status === 'pending' || d.status === 'in_progress') badgeClass = 'bg-info';
        else if (d.status === 'approved') badgeClass = 'bg-success';
        else if (d.status === 'rejected') badgeClass = 'bg-danger';
        else if (d.status === 'changes_requested') badgeClass = 'bg-warning';

        var statusLabel = d.status_label || (d.status || '').replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());

        // Timeline
        var timelineHtml = '';
        if (d.timeline && d.timeline.length > 0) {
            timelineHtml = '<div class="approval-timeline">';
            d.timeline.forEach(function(item) {
                var icon = 'circle';
                if (item.action === 'approved') icon = 'check_circle';
                else if (item.action === 'rejected') icon = 'cancel';
                else if (item.action === 'changes_requested') icon = 'edit_note';
                else if (item.action === 'returned') icon = 'undo';
                else if (item.action === 'submitted' || item.action === 'resubmitted') icon = 'send';

                var levelBadge = item.level > 0
                    ? ' <span class="text-muted" style="font-size:0.78rem;">(' + (item.level_name || 'Level ' + item.level) + ')</span>'
                    : '';
                var commentHtml = item.comment
                    ? '<div class="timeline-comment">' + $('<div>').text(item.comment).html() + '</div>'
                    : '';

                timelineHtml +=
                    '<div class="timeline-item">' +
                    '<div class="timeline-icon"><span class="material-symbols-outlined">' + icon + '</span></div>' +
                    '<div class="d-flex justify-content-between align-items-start">' +
                    '<div><span class="timeline-approver">' + $('<div>').text(item.approver_name || 'System').html() + '</span>' + levelBadge + '</div>' +
                    '<span class="timeline-date">' + (item.action_on || '') + '</span>' +
                    '</div>' +
                    '<div class="timeline-action">' + (item.action_label || '') + '</div>' +
                    commentHtml +
                    '</div>';
            });
            timelineHtml += '</div>';
        } else {
            timelineHtml = '<p class="text-muted text-center py-4 mb-0">No activity recorded yet.</p>';
        }

        // View voucher button
        var voucherBtn = '';
        if (requestType === 'voucher' && encryptedRid) {
            voucherBtn =
                '<button type="button" class="btn btn-outline-primary btn-sm" id="btnViewVoucherModal" data-rid="' + encryptedRid + '">' +
                '<span class="material-symbols-outlined">receipt_long</span> View Voucher Summary' +
                '</button>';
        }

        // Action buttons
        var actionsHtml = '';
        if (d.can_approve && !d.is_complete) {
            var noun = requestType === 'voucher' ? 'voucher' : (requestType === 'return' ? 'return' : 'request');
            actionsHtml =
                '<div class="card"><div class="card-header"><h6>Actions</h6></div><div class="card-body">' +
                '<div class="d-flex flex-wrap gap-2">' +
                '<button type="button" class="btn btn-primary btn-sm approval-action-btn" data-action="approve" data-eid="' + encryptedId + '" data-noun="' + noun + '">' +
                '<span class="material-symbols-outlined" style="font-size:16px;">check</span> Approve ' + noun + '</button>' +
                '<button type="button" class="btn btn-danger btn-sm approval-action-btn" data-action="send_back" data-eid="' + encryptedId + '" data-noun="' + noun + '" data-level="' + d.current_level + '">' +
                '<span class="material-symbols-outlined" style="font-size:16px;">close</span> Reject ' + noun + ' with reason</button>' +
                '</div></div></div>';
        }

        var resubmitHtml = '';
        if (d.can_resubmit) {
            resubmitHtml =
                '<div class="card"><div class="card-body">' +
                '<p class="text-muted mb-2">Changes have been requested. You can resubmit after making the necessary changes.</p>' +
                '<button type="button" class="btn btn-primary btn-sm approval-action-btn" data-action="resubmit" data-eid="' + encryptedId + '">' +
                '<span class="material-symbols-outlined" style="font-size:16px;">send</span> Resubmit for Approval</button>' +
                '</div></div>';
        }

        // Voucher button sits in its own card so the right column reads as a stack of equal cards
        var voucherCard = voucherBtn
            ? '<div class="card"><div class="card-header"><h6>Voucher</h6></div><div class="card-body">' + voucherBtn + '</div></div>'
            : '';

        return '' +
            // Top: status summary across the full width
            '<div class="card status-card status-' + d.status + ' mb-3"><div class="card-body">' +
            '<div class="d-flex align-items-center justify-content-between mb-3">' +
            '<h6 class="mb-0">Request Status</h6>' +
            '<span class="badge ' + badgeClass + '">' + statusLabel + '</span>' +
            '</div>' +
            '<div class="row g-3">' +
            '<div class="col-6 col-md-3"><div class="info-label">Current Stage</div><div class="info-value">' + (d.current_level_name || 'Level ' + d.current_level) + '</div></div>' +
            '<div class="col-6 col-md-3"><div class="info-label">Initiated By</div><div class="info-value">' + $('<div>').text(d.initiated_by || '-').html() + '</div></div>' +
            '<div class="col-6 col-md-3"><div class="info-label">Initiated On</div><div class="info-value">' + (d.initiated_on || '-') + '</div></div>' +
            (d.completed_on
                ? '<div class="col-6 col-md-3"><div class="info-label">Completed On</div><div class="info-value">' + d.completed_on + '</div></div>'
                : '<div class="col-6 col-md-3"><div class="info-label">Current Approver</div><div class="info-value">' + (d.current_approver && !d.is_complete ? $('<div>').text(d.current_approver.name).html() : '-') + '</div></div>') +
            '</div>' +
            '</div></div>' +
            // Below: trail on the left, voucher and actions on the right, same card style and equal height
            '<div class="row g-3">' +
            '<div class="col-lg-7">' +
            '<div class="card h-100"><div class="card-header"><h6>Approval Trail</h6></div>' +
            '<div class="card-body approval-trail-body">' + timelineHtml + '</div></div>' +
            '</div>' +
            '<div class="col-lg-5">' +
            voucherCard +
            actionsHtml +
            resubmitHtml +
            '</div>' +
            '</div>';
    }

    // ── Action buttons inside details modal ────────────────────────────────
    $(document).on('click', '.approval-action-btn', function() {
        var action = $(this).data('action');
        var eid = $(this).data('eid');
        var level = $(this).data('level') || '';

        var noun = $(this).data('noun') || 'request';
        var titles = {
            approve: 'Approve ' + noun,
            send_back: 'Reject ' + noun + ' with reason',
            reject: 'Reject ' + noun,
            request_changes: 'Send ' + noun + ' back for changes',
            return: 'Send ' + noun + ' back to the previous stage',
            resubmit: 'Send ' + noun + ' for approval again'
        };
        var hints = {
            approve: 'Add a comment (optional)',
            send_back: 'The reason is kept on the ' + noun + ' and shown to the person who prepared it',
            reject: 'Please provide a reason for rejection',
            request_changes: 'Please describe the changes required',
            return: 'Add a comment (optional)',
            resubmit: 'Add a comment (optional)'
        };
        var required = (action === 'reject' || action === 'request_changes' || action === 'send_back');

        // One Reject button: the reason is always asked, and the person chooses where the item goes
        $('#sendBackChoices').toggle(action === 'send_back');
        if (action === 'send_back') {
            var current = parseInt(level, 10) || 1;
            var choices = [];
            if (current > 1) { choices.push(['return', 'Send it back to the previous approver (stage ' + (current - 1) + ')']); }
            choices.push(['request_changes', 'Send it back to the person who prepared it, to change and send again']);
            choices.push(['reject', 'Reject it completely (it is closed and must be raised again)']);
            var html = '';
            choices.forEach(function (c, i) {
                html += '<div class="form-check"><input class="form-check-input" type="radio" name="sendBackOption" id="sb' + i + '" value="' + c[0] + '"' + (i === 0 ? ' checked' : '') + '>' +
                    '<label class="form-check-label" for="sb' + i + '">' + c[1] + '</label></div>';
            });
            $('#sendBackOptions').html(html);
            level = current - 1;
        }

        $('#approvalAction').val(action);
        $('#approvalEncryptedId').val(eid);
        $('#approvalReturnLevel').val(level);
        $('#approvalActionModalTitle').text(titles[action] || 'Action');
        $('#approvalComment').val('');
        $('#commentHint').text(hints[action] || '');
        $('#commentLabel').text(required ? 'Comment (Required)' : 'Comment (Optional)');
        approvalActionModal.show();
    });

    $('#confirmApprovalAction').on('click', function() {
        var action = $('#approvalAction').val();
        var eid = $('#approvalEncryptedId').val();
        var comment = $('#approvalComment').val().trim();
        var returnLevel = $('#approvalReturnLevel').val();

        // The one Reject button: the chosen option decides which action runs
        if (action === 'send_back') {
            action = $('input[name="sendBackOption"]:checked').val() || 'reject';
        }

        if ((action === 'reject' || action === 'request_changes') && !comment) {
            toastr.error('Comment is required for this action.');
            return;
        }

        var urlMap = {
            approve: '/ajax/approval-requests/' + eid + '/approve',
            reject: '/ajax/approval-requests/' + eid + '/reject',
            request_changes: '/ajax/approval-requests/' + eid + '/request-changes',
            return: '/ajax/approval-requests/' + eid + '/return',
            resubmit: '/ajax/approval-requests/' + eid + '/resubmit'
        };
        var url = urlMap[action];
        if (!url) return;

        var data = { comment: comment, _token: '{{ csrf_token() }}' };
        if (action === 'return') data.target_level = returnLevel;

        $('#confirmApprovalAction').prop('disabled', true).text('Processing...');

        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    approvalActionModal.hide();
                    approvalDetailsModal.hide();
                    setTimeout(function() {
                        loadCurrentApprovals();
                        loadHistoricalApprovals();
                    }, 800);
                } else {
                    toastr.error(response.message || 'Action failed.');
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred.');
            },
            complete: function() {
                $('#confirmApprovalAction').prop('disabled', false).text('Confirm');
            }
        });
    });

    // ── Voucher Summary ────────────────────────────────────────────────────
    $(document).on('click', '#btnViewVoucherModal', function() {
        var rid = $(this).data('rid');
        $('#voucherLoading').show();
        $('#voucherContent').hide();
        voucherSummaryModal.show();

        $.ajax({
            url: '/ajax/payment/vouchers/' + rid + '/lines',
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

                    var $tbody = $('#voucherLinesTable tbody').empty();
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
            error: function() {
                toastr.error('Failed to load voucher details');
                voucherSummaryModal.hide();
            }
        });
    });
});
</script>
@endsection
