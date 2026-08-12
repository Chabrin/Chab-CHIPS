@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Discipline Cases Tracker</h4>
                        <div class="ms-auto d-flex gap-2">
                            <a class="btn btn-outline-danger btn-sm d-flex align-items-center" href="{{ route('discipline.tracker.appeals.index') }}">
                                <span class="material-symbols-outlined">gavel</span>
                                <span class="ms-1">Appeals</span>
                            </a>
                            @permission('create_discipline_records')
                                <a class="btn btn-primary btn-sm d-flex align-items-center" href="{{ route('discipline.tracker.create.incident') }}">
                                    <span class="material-symbols-outlined">add_circle</span>
                                    <span class="ms-1">Create Incident Report</span>
                                </a>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info d-flex align-items-start gap-2 mb-3" role="alert">
                            <span class="material-symbols-outlined">info</span>
                            <div>
                                A newly created incident report starts as <strong>Pending</strong> and must be
                                confirmed before its case can proceed to the next steps — that's why a Pending
                                case's number and view action are disabled below.
                                @permission('update_discipline_records')
                                    Confirm pending cases at
                                    <a href="{{ route('workflow.approvals.confirmations') }}">Maker-Checker Confirmations</a>.
                                @endpermission
                            </div>
                        </div>
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="discipline-cases-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Case Number</th>
                                        <th>Employee</th>
                                        <th>Position</th>
                                        <th>Current Step</th>
                                        <th>Severity</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th>Date Created</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data loaded via DataTables AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Timeline Modal -->
<div class="modal fade" id="timelineModal" tabindex="-1" aria-labelledby="timelineModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="timelineModalLabel">
                    <span class="material-symbols-outlined" style="vertical-align:middle;">timeline</span>
                    Case Timeline
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="scroll h-300px" id="timelineContent">
                <!-- Timeline content loaded via AJAX -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined">close</span>
                    <span class="ms-1">Cancel</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // DataTable
    let table = $('#discipline-cases-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ route('discipline.tracker.cases.list') }}",
            type: 'GET',
            dataType: 'json',
            dataSrc: 'cases'
        },
        columns: [
            { 
                data: null, 
                render: (data, type, row, meta) => meta.row + 1,
                orderable: false 
            },
            { 
                data: 'case_number',
                render: function(data, type, row) {
                    if (row.can_view && row.encrypted_case_id && (row.current_status_id == 5 || row.current_status_id == 42)) {
                        return `<a href="/discipline/tracker/case/${row.encrypted_case_id}" class="text-primary fw-bold">${data}</a>`;
                    }
                    return `<span class="fw-bold" title="Confirm Case before proceeding">${data}</span>`;
                }
            },
            { 
                data: 'staff', 
                render: function(data, type, row) {
                    if (data && data.name) {
                        if (row.can_view && data.encrypted_id) {
                            return `<a href="/hr/employees/${data.encrypted_id}" target="_blank">${data.name}</a>`;
                        }
                        return data.name;
                    }
                    return '-';
                }
            },
            { 
                data: 'position',
                render: (data) => data || '-'
            },
            { 
                data: 'current_step',
                render: function(data, type, row) {
                    let stepBadge = '';
                    let badgeColor = 'badge-light-secondary';
                    
                    // Color code steps
                    if (data <= 3) badgeColor = 'badge-light-info';
                    else if (data <= 6) badgeColor = 'badge-light-primary';
                    else if (data <= 10) badgeColor = 'badge-light-warning';
                    else if (data <= 13) badgeColor = 'badge-light-success';
                    else if (data == 14) badgeColor = 'badge-light-dark';
                    
                    stepBadge = `<span class="badge ${badgeColor}">Step ${data}</span>`;
                    return `${stepBadge}<br><small class="text-muted">${row.step_name}</small>`;
                }
            },
            { 
                data: 'severity_level',
                render: function(data) {
                    let badgeColor = 'badge-light-secondary';
                    switch(data) {
                        case 'Minor':
                            badgeColor = 'badge-light-info';
                            break;
                        case 'Moderate':
                            badgeColor = 'badge-light-warning';
                            break;
                        case 'Serious':
                            badgeColor = 'badge-light-danger';
                            break;
                        case 'Gross Misconduct':
                            badgeColor = 'badge-light-dark';
                            break;
                    }
                    return `<span class="badge ${badgeColor}">${data}</span>`;
                }
            },
            { 
                data: 'current_status_id',
                render: function(data) {
                    if (data == 1) {
                        return '<span class="badge badge-light-info">Pending</span>';
                    } else if (data == 5) {
                        return '<span class="badge badge-light-success">Open</span>';
                    } else if (data == 42) {
                        return '<span class="badge badge-light-secondary">Closed</span>';
                    }
                    return '-';
                }
            },
            { 
                data: 'created_by',
                render: (data) => data || '-'
            },
            { 
                data: 'date_time',
                render: function(data) {
                    if (data) {
                        let date = new Date(data);
                        return date.toLocaleDateString('en-GB') + '<br><small class="text-muted">' + date.toLocaleTimeString('en-GB', {hour: '2-digit', minute:'2-digit'}) + '</small>';
                    }
                    return '-';
                }
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(id, type, row) {
                    let buttons = '<div class="d-flex flex-row gap-1">';
                    
                    // View button
                    if(row.can_view && row.encrypted_case_id) {
                        if( row.current_status_id == 5 || row.current_status_id == 42 ) {
                            buttons += `
                                <a href="/discipline/tracker/case/${row.encrypted_case_id}" title="View Case">
                                    <span class="material-symbols-outlined view-icon" style="font-size:18px;">visibility</span>
                                </a>
                            `;
                        } else {
                            buttons += `
                                <a href="#" title="Pending Case Confirmation before proceeding">
                                    <span class="material-symbols-outlined edit-icon" style="font-size:18px;">visibility_off</span>
                                </a>
                            `;
                        }
                    }
                    
                    // Timeline button
                    if(row.can_view) {
                        buttons += `
                            <a href="#" class="view-timeline" data-case-id="${row.id}" title="View Timeline">
                                <span class="material-symbols-outlined" style="font-size:18px;">timeline</span>
                            </a>
                        `;
                    }
                    
                    buttons += '</div>';
                    return buttons;
                }
            }
        ],
        order: [[8, 'desc']], // Sort by date created desc
        paging: true,
        info: true,
        lengthChange: true,
        pageLength: 25
    });

    // View timeline modal
    $(document).on('click', '.view-timeline', function(e) {
        e.preventDefault();
        let caseId = $(this).data('case-id');
        
        // Show loading modal
        $('#timelineModal').modal('show');
        $('#timelineContent').html('<div class="text-center"><div class="spinner-border" role="status"></div><p>Loading timeline...</p></div>');
        
        // Fetch timeline
        $.ajax({
            url: `/discipline/tracker/case/${caseId}/timeline`,
            type: 'GET',
            success: function(response) {
                if (response.timeline && response.timeline.length > 0) {
                    let html = '<div class="position-relative ps-4">';
                    
                    response.timeline.forEach(function(item, index) {
                        let markerColor = 'info';
                        let borderColor = 'info';
                        if (item.activity_type === 'Step Completed') {
                            markerColor = 'success';
                            borderColor = 'success';
                        } else if (item.activity_type === 'Step Skipped') {
                            markerColor = 'warning';
                            borderColor = 'warning';
                        } else if (item.activity_type === 'Document Added') {
                            markerColor = 'primary';
                            borderColor = 'primary';
                        }
                        
                        let isLast = (index === response.timeline.length - 1);
                        let borderClass = isLast ? '' : 'border-start border-2 border-secondary';
                        
                        html += `
                            <div class="position-relative pb-4 ${borderClass}" style="padding-left: 1.5rem;">
                                <div class="position-absolute bg-${markerColor} rounded-circle border border-2 border-white shadow-sm" 
                                     style="width: 14px; height: 14px; left: -7px; top: 0;"></div>
                                <div class="bg-light text-black p-3 rounded border-start border-3 border-${borderColor}">
                                    <h6 class="mb-1">${item.step_name} <span class="badge bg-secondary">Step ${item.step_number}</span></h6>
                                    <p class="mb-1"><strong>${item.activity_type}</strong>: ${item.description || '-'}</p>
                                    <small class="text-muted">
                                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">person</span> ${item.performed_by} 
                                        | <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">schedule</span> ${item.performed_at}
                                    </small>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                    $('#timelineContent').html(html);
                } else {
                    $('#timelineContent').html('<p class="text-center text-muted">No timeline activity found.</p>');
                }
            },
            error: function() {
                $('#timelineContent').html('<p class="text-center text-danger">Failed to load timeline.</p>');
            }
        });
    });
});
</script>

@endsection