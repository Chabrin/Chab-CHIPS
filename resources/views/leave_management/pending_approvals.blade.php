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
            { data: null, orderable: false, render: function(d, t, r, m) { return m.row + 1; } },
            {
                data: 'employee',
                render: function(d) {
                    if (!d) return '-';
                    var staffName = d.full_names || d.name || ((d.first_name || '') + ' ' + (d.last_name || '')).trim();
                    return staffName ? '<strong>' + staffName + '</strong>' : '-';
                }
            },
            { data: 'department' },
            {
                data: 'leave_type',
                render: function(d) { return d && d.type_name ? d.type_name : '-'; }
            },
            { data: 'start_date' },
            { data: 'end_date' },
            { data: 'requested_days' },
            {
                data: 'current_status_id',
                render: function(status) {
                    if (status === 1) {
                        return '<span class="badge bg-warning text-dark">Stage 1: Awaiting HOD</span>';
                    } else if (status === 24) {
                        return '<span class="badge bg-info text-dark">Stage 2: Awaiting HR Final</span>';
                    }
                    return '<span class="badge bg-secondary">-</span>';
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    var buttons = '<div class="d-flex gap-1 align-items-center">';

                    // HOD / Supervisor Recommendation (Status 1 -> 24)
                    if (row.can_approve_hod) {
                        buttons += '<button class="btn btn-xs btn-outline-primary btn-action-status" data-id="' + row.id + '" data-status="24" data-label="recommend / partially approve">' +
                            '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">thumb_up</span> Recommend</button>';
                    }

                    // HR Final Approval (Status 24 -> 5)
                    if (row.can_approve_hr) {
                        buttons += '<button class="btn btn-xs btn-outline-success btn-action-status" data-id="' + row.id + '" data-status="5" data-label="grant final approval for">' +
                            '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">check_circle</span> Final Approve</button>';
                    }

                    // Rejection (Status -> 41)
                    if (row.can_reject) {
                        buttons += '<button class="btn btn-xs btn-outline-danger btn-action-status" data-id="' + row.id + '" data-status="41" data-label="reject">' +
                            '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">cancel</span> Reject</button>';
                    }

                    buttons += '</div>';
                    return buttons;
                }
            }
        ]
    });

    // Handle action clicks
    $(document).on('click', '.btn-action-status', function() {
        var $btn = $(this);
        var id = $btn.data('id');
        var status = $btn.data('status');
        var label = $btn.data('label');

        if (!confirm('Are you sure you want to ' + label + ' this leave application?')) {
            return;
        }

        $btn.prop('disabled', true);

        $.ajax({
            url: "{{ url('/ajax/leave-management/leave-applications/status-update') }}",
            type: 'POST',
            data: {
                id: id,
                status: status,
                _token: '{{ csrf_token() }}'
            },
            success: function(res) {
                if (res.success) {
                    toastr.success(res.message || 'Status updated successfully');
                    pendingTable.ajax.reload(null, false);
                } else {
                    toastr.error(res.error || 'Failed to update status');
                    $btn.prop('disabled', false);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false);
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Error processing request');
            }
        });
    });
});
</script>
@endsection