@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Leave Pending Approvals</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="pending-approvals-table" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Staff</th>
                                        <th>Department</th>
                                        <th>Leave Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Days</th>
                                        <th>Relief Arrangements</th>
                                        <th>Current Stage</th>
                                        <th>Action</th>
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

<script>
$(document).ready(function() {
    let pendingTable = $('#pending-approvals-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ route('ajax.hr.leave.pending.approvals.list') }}",
            type: 'GET',
            dataSrc: 'leave_applications'
        },
        columns: [
            { 
                data: null, 
                orderable: false, 
                searchable: false,
                render: (d, t, r, m) => m.row + 1 
            },
            { 
                data: 'employee', 
                render: function(d) {
                    if (!d) return '-';
                    let staffName = d.full_names || d.name || `${d.first_name || ''} ${d.last_name || ''}`.trim();
                    return staffName ? `<strong>${staffName}</strong>` : '-';
                }
            },
            { 
                data: 'department',
                render: d => d || '-'
            },
            { 
                data: 'leave_type', 
                render: d => d && d.type_name ? d.type_name : '-' 
            },
            { 
                data: 'start_date',
                render: d => d || '-'
            },
            { 
                data: 'end_date',
                render: d => d || '-'
            },
            { 
                data: 'requested_days',
                render: d => d !== null && d !== undefined ? d : '-'
            },
            {
                data: 'handover_notes',
                render: function(notes, type, row) {
                    const text = row.handover_employee_name ? row.handover_employee_name + ': ' + (notes || '') : 'No relief arrangements recorded';
                    const receipt = row.handover_acknowledged_at ? 'Receipt acknowledged' : 'Awaiting acknowledgement';
                    const url = $('<div>').text(row.handover_review_url || '').html();
                    return $.fn.dataTable.render.text().display(text) + '<br><small>' + receipt + '</small><br><a href="' + url + '">View relief files and receipt</a>';
                }
            },
            {
                data: 'current_status_id',
                render: function(status, type, row) {
                    // Prefer the label coming from the backend if available
                    if (row.stage_label) {
                        if (status === 1) {
                            return `<span class="badge bg-warning text-dark">${row.stage_label}</span>`;
                        } else if (status === 24) {
                            return `<span class="badge bg-info text-dark">${row.stage_label}</span>`;
                        }
                    }

                    // Fallback
                    if (status === 1) {
                        return '<span class="badge bg-warning text-dark">Stage 1: Awaiting Supervisor</span>';
                    } else if (status === 24) {
                        return '<span class="badge bg-info text-dark">Stage 2: Awaiting HR Final</span>';
                    }
                    return '<span class="badge bg-secondary">-</span>';
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    let buttons = '<div class="d-flex gap-1 align-items-center flex-wrap">';

                    // Stage 1 – HOD / Supervisor Recommend
                    if (row.can_approve_hod) {
                        buttons += `
                            <button class="btn btn-xs btn-outline-primary btn-action-approve"
                                data-id="${row.id}" 
                                data-label="recommend">
                                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">thumb_up</span>
                                Recommend
                            </button>
                        `;
                    }

                    // Stage 2 – HR / Director Final Approve
                    if (row.can_approve_hr) {
                        buttons += `
                            <button class="btn btn-xs btn-outline-success btn-action-approve"
                                data-id="${row.id}" 
                                data-label="grant final approval for">
                                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">check_circle</span>
                                Final Approve
                            </button>
                        `;
                    }

                    // Reject (available at both stages when allowed)
                    if (row.can_reject) {
                        buttons += `
                            <button class="btn btn-xs btn-outline-danger btn-action-reject"
                                data-id="${row.id}">
                                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">cancel</span>
                                Reject
                            </button>
                        `;
                    }

                    if (!row.can_reject) {
                        buttons += '<small class="text-muted">' + $.fn.dataTable.render.text().display(row.waiting_description || 'Awaiting approval at the current stage.') + '</small>';
                    }
                    buttons += '</div>';
                    return buttons;
                }
            }
        ],
        order: [[0, 'asc']],
        pageLength: 10
    });

    // ========== APPROVE (Recommend or Final Approve) ==========
    $(document).on('click', '.btn-action-approve', function() {
        let $btn = $(this);
        let id = $btn.data('id');
        let label = $btn.data('label') || 'approve';

        if (!confirm(`Are you sure you want to ${label} this leave application?`)) {
            return;
        }

        $btn.prop('disabled', true);
        let originalHtml = $btn.html();
        $btn.html('<span class="spinner-border spinner-border-sm" role="status"></span> Processing...');

        $.ajax({
            url: "{{ route('ajax.hr.leave.applications.approve') }}",
            type: 'POST',
            data: {
                id: id,
                _token: '{{ csrf_token() }}'
            },
            success: function(res) {
                if (res.success) {
                    toastr.success(res.message || 'Approved successfully');
                    pendingTable.ajax.reload(null, false);
                } else {
                    toastr.error(res.error || 'Failed to approve');
                    $btn.prop('disabled', false).html(originalHtml);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(originalHtml);
                let msg = (xhr.responseJSON && xhr.responseJSON.error) 
                    ? xhr.responseJSON.error 
                    : 'Error processing request';
                toastr.error(msg);
            }
        });
    });

    // ========== REJECT ==========
    $(document).on('click', '.btn-action-reject', function() {
        let $btn = $(this);
        let id = $btn.data('id');

        if (!confirm('Are you sure you want to reject this leave application?')) {
            return;
        }

        $btn.prop('disabled', true);
        let originalHtml = $btn.html();
        $btn.html('<span class="spinner-border spinner-border-sm" role="status"></span> Processing...');

        $.ajax({
            url: "{{ route('ajax.hr.leave.applications.reject') }}",
            type: 'POST',
            data: {
                id: id,
                _token: '{{ csrf_token() }}'
            },
            success: function(res) {
                if (res.success) {
                    toastr.success(res.message || 'Rejected successfully');
                    pendingTable.ajax.reload(null, false);
                } else {
                    toastr.error(res.error || 'Failed to reject');
                    $btn.prop('disabled', false).html(originalHtml);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(originalHtml);
                let msg = (xhr.responseJSON && xhr.responseJSON.error) 
                    ? xhr.responseJSON.error 
                    : 'Error processing request';
                toastr.error(msg);
            }
        });
    });
});
</script>
@endsection
