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
.stage-badge {
    font-size: 0.68rem;
    padding: 2px 6px;
}
.nav-tabs .nav-link {
    color: #666;
    font-weight: 500;
}
.nav-tabs .nav-link.active {
    color: #3598db;
    font-weight: 600;
}
.tab-count {
    font-size: 0.75rem;
    padding: 2px 8px;
    border-radius: 10px;
    margin-left: 6px;
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
                                <button class="nav-link active" id="my-stage-tab" data-bs-toggle="tab" data-bs-target="#my-stage" type="button" role="tab">
                                    My Stage
                                    <span class="tab-count bg-primary text-white" id="myStageCount">0</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="other-stages-tab" data-bs-toggle="tab" data-bs-target="#other-stages" type="button" role="tab">
                                    Other Stages
                                    <span class="tab-count bg-secondary text-white" id="otherStagesCount">0</span>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="approvalTabsContent">
                            <!-- My Stage Tab -->
                            <div class="tab-pane fade show active" id="my-stage" role="tabpanel">
                                <div id="noMyStage" class="text-center py-5" style="display:none;">
                                    <span class="material-symbols-outlined text-muted" style="font-size:60px;">task_alt</span>
                                    <p class="text-muted mt-2 mb-0">No approvals pending at your stage.</p>
                                </div>
                                <div class="table-responsive custom-scrollbar" id="myStageTableWrap">
                                    <table class="display" id="myStageTable" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Type</th>
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

                            <!-- Other Stages Tab -->
                            <div class="tab-pane fade" id="other-stages" role="tabpanel">
                                <div id="noOtherStages" class="text-center py-5" style="display:none;">
                                    <span class="material-symbols-outlined text-muted" style="font-size:60px;">history</span>
                                    <p class="text-muted mt-2 mb-0">No approvals at other stages.</p>
                                </div>
                                <div class="table-responsive custom-scrollbar" id="otherStagesTableWrap">
                                    <table class="display" id="otherStagesTable" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Type</th>
                                                <th>Current Level</th>
                                                <th>My Level</th>
                                                <th>Stage</th>
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
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // My Stage Table
    var myStageTable = $('#myStageTable').DataTable({
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
        order: [[5, 'desc']],
        pageLength: 25,
        language: {
            emptyTable: "No pending approvals at your stage"
        }
    });

    // Other Stages Table
    var otherStagesTable = $('#otherStagesTable').DataTable({
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
            { 
                data: 'current_level',
                className: 'text-center',
                render: (d) => '<span class="badge bg-secondary">Level ' + d + '</span>'
            },
            { 
                data: 'my_level',
                className: 'text-center',
                render: (d) => '<span class="badge bg-primary">Level ' + d + '</span>'
            },
            { 
                data: 'stage',
                className: 'text-center',
                render: function(d) {
                    if (d === 'waiting') {
                        return '<span class="badge stage-badge bg-warning text-dark">Not yet reached</span>';
                    } else {
                        return '<span class="badge stage-badge bg-success">Already passed</span>';
                    }
                }
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
                    return '<a href="/accounting/pending/approvals/' + r.encrypted_id + '" class="btn btn-sm btn-outline-secondary"><span class="material-symbols-outlined" style="font-size:16px;">visibility</span> View</a>';
                }
            }
        ],
        paging: true,
        info: true,
        searching: true,
        ordering: true,
        order: [[7, 'desc']],
        pageLength: 25,
        language: {
            emptyTable: "No approvals at other stages"
        }
    });

    // Load my stage approvals
    function loadMyStageApprovals() {
        $.ajax({
            url: '{{ route("ajax.approval-requests.pending") }}',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    myStageTable.clear();
                    $('#myStageCount').text(response.count);
                    if (response.data && response.data.length > 0) {
                        myStageTable.rows.add(response.data);
                        $('#noMyStage').hide();
                        $('#myStageTableWrap').show();
                    } else {
                        $('#noMyStage').show();
                        $('#myStageTableWrap').hide();
                    }
                    myStageTable.draw();
                }
            },
            error: function(xhr) {
                toastr.error('Failed to load pending approvals');
            }
        });
    }

    // Load other stages approvals
    function loadOtherStagesApprovals() {
        $.ajax({
            url: '{{ route("ajax.approval-requests.historical") }}',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    otherStagesTable.clear();
                    $('#otherStagesCount').text(response.count);
                    if (response.data && response.data.length > 0) {
                        otherStagesTable.rows.add(response.data);
                        $('#noOtherStages').hide();
                        $('#otherStagesTableWrap').show();
                    } else {
                        $('#noOtherStages').show();
                        $('#otherStagesTableWrap').hide();
                    }
                    otherStagesTable.draw();
                }
            },
            error: function(xhr) {
                toastr.error('Failed to load historical approvals');
            }
        });
    }

    loadMyStageApprovals();
    loadOtherStagesApprovals();
});
</script>
@endsection
