@extends('layouts.main')

@section('styles')
<style>
.request-type-badge {
    font-size: 0.7rem;
    padding: 3px 8px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.status-badge {
    font-size: 0.72rem;
    padding: 3px 10px;
}
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">
                            <span class="material-symbols-outlined" style="font-size:22px;vertical-align:middle;">pending_actions</span>
                            Pending Approvals
                        </h5>
                        <small class="text-muted" id="pendingCount"></small>
                    </div>
                    <div class="card-body">
                        <div id="noApprovals" class="text-center py-5" style="display:none;">
                            <span class="material-symbols-outlined text-muted" style="font-size:60px;">task_alt</span>
                            <p class="text-muted mt-2 mb-0">No pending approvals at this time.</p>
                            <small class="text-muted">You will see items here when you are assigned as an approver.</small>
                        </div>
                        <div class="table-responsive custom-scrollbar" id="approvalsTableWrap">
                            <table class="display" id="approvalsTable" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Type</th>
                                        <th>Workflow</th>
                                        <th>Level</th>
                                        <th>Status</th>
                                        <th>Initiated By</th>
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
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#approvalsTable').DataTable({
        data: [],
        columns: [
            { data: null, orderable: false, render: (d, t, r, m) => m.row + 1 },
            { 
                data: 'request_type',
                render: function(d) {
                    var label = d.charAt(0).toUpperCase() + d.slice(1);
                    return '<span class="badge bg-light text-dark request-type-badge">' + label + '</span>';
                }
            },
            { data: 'workflow_name', render: (d) => d || '-' },
            { 
                data: 'current_level',
                className: 'text-center',
                render: (d) => '<span class="badge bg-secondary">Level ' + d + '</span>'
            },
            { 
                data: 'status',
                render: function(d) {
                    var badgeClass = 'bg-secondary';
                    if (d === 'pending' || d === 'in_progress') badgeClass = 'bg-info';
                    else if (d === 'changes_requested') badgeClass = 'bg-warning';
                    var label = d.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    return '<span class="badge status-badge ' + badgeClass + '">' + label + '</span>';
                }
            },
            { data: 'initiated_by', render: (d) => d || '-' },
            { data: 'initiated_on', render: (d) => d || '-' },
            {
                data: null,
                orderable: false,
                render: function(d, t, r) {
                    return '<a href="/accounting/pending/approvals/' + r.encrypted_id + '" class="btn btn-sm btn-outline-primary"><span class="material-symbols-outlined" style="font-size:16px;">visibility</span> View</a>';
                }
            }
        ],
        paging: true,
        info: true,
        searching: true,
        ordering: true,
        order: [[6, 'desc']],
        pageLength: 25,
        language: {
            emptyTable: "No pending approvals found"
        }
    });

    // Load pending approvals
    function loadPendingApprovals() {
        $.ajax({
            url: '{{ route("ajax.approval-requests.pending") }}',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    table.clear();
                    if (response.data && response.data.length > 0) {
                        table.rows.add(response.data);
                        $('#noApprovals').hide();
                        $('#approvalsTableWrap').show();
                        $('#pendingCount').text(response.count + ' pending');
                    } else {
                        $('#noApprovals').show();
                        $('#approvalsTableWrap').hide();
                        $('#pendingCount').text('');
                    }
                    table.draw();
                }
            },
            error: function(xhr) {
                toastr.error('Failed to load pending approvals');
            }
        });
    }

    loadPendingApprovals();
});
</script>
@endsection
