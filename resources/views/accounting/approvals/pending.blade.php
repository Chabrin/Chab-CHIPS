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
                                                <th>#</th>
                                                <th>Type</th>
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

                            <!-- Historical Tab -->
                            <div class="tab-pane fade" id="historical" role="tabpanel">
                                <div class="table-responsive custom-scrollbar">
                                    <table class="display" id="historicalTable" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Type</th>
                                                <th>Current Stage</th>
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
    // Current Table
    var currentTable = $('#currentTable').DataTable({
        data: [],
        columns: [
            { data: null, orderable: false, render: (d, t, r, m) => m.row + 1 },
            { 
                data: 'request_type',
                render: function(d) {
                    return d.charAt(0).toUpperCase() + d.slice(1);
                }
            },
            { 
                data: 'current_level_name',
                className: 'text-center',
                render: (d) => d || '-'
            },
            { 
                data: 'status',
                render: function(d) {
                    return d.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
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
            emptyTable: "No data"
        }
    });

    // Historical Table
    var historicalTable = $('#historicalTable').DataTable({
        data: [],
        columns: [
            { data: null, orderable: false, render: (d, t, r, m) => m.row + 1 },
            { 
                data: 'request_type',
                render: function(d) {
                    return d.charAt(0).toUpperCase() + d.slice(1);
                }
            },
            { 
                data: 'current_level_name',
                className: 'text-center',
                render: (d) => d || '-'
            },
            { 
                data: 'stage',
                render: function(d) {
                    if (d === 'approved') return '<span class="text-success">Approved</span>';
                    if (d === 'rejected') return '<span class="text-danger">Rejected</span>';
                    if (d === 'passed') return '<span class="text-muted">Passed my level</span>';
                    return '<span class="text-muted">Not yet reached</span>';
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
        order: [[5, 'desc']],
        pageLength: 25,
        language: {
            emptyTable: "No data"
        }
    });

    // Load current approvals
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
            error: function(xhr) {
                toastr.error('Failed to load pending approvals');
            }
        });
    }

    // Load historical approvals
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
            error: function(xhr) {
                toastr.error('Failed to load historical approvals');
            }
        });
    }

    loadCurrentApprovals();
    loadHistoricalApprovals();
});
</script>
@endsection
