@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                <h4>Leave Adjustments</h4>
                <small class="text-muted">Refund leave days when public holidays occur during approved leave</small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="leave-adjustments-table" class="display table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Position</th>
                                <th>Leave Type</th>
                                <th>Period</th>
                                <th>Days Taken</th>
                                <th>Days Refunded</th>
                                <th>Net Days</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Adjust Leave Days Modal -->
<div class="modal fade" id="adjustLeaveDaysModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adjust Leave Days</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Employee:</strong> <span id="adjust-employee-name"></span></p>
                        <p><strong>Position:</strong> <span id="adjust-position"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Leave Type:</strong> <span id="adjust-leave-type"></span></p>
                        <p><strong>Period:</strong> <span id="adjust-period"></span></p>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> Select days to refund (e.g., days that became public holidays). The employee's leave balance will be automatically updated.
                </div>

                <div id="partial-day-info" class="alert alert-warning d-none">
                    <i class="fa fa-clock"></i> <strong>Partial Day Leave:</strong> <span id="partial-hours-info"></span>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="select-all-days" class="form-check-input">
                                </th>
                                <th>Date</th>
                                <th>Day Type</th>
                                <th>Current Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="days-taken-list">
                            <!-- Days will be loaded here -->
                        </tbody>
                    </table>
                </div>

                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Reason for Adjustment <span class="text-danger">*</span></label>
                            <textarea id="adjustment_reason" class="form-control" rows="3" required 
                                placeholder="E.g., December 12, 2025 declared as public holiday by government"></textarea>
                        </div>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-md-12">
                        <div class="card bg-light text-black">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>Days Originally Taken:</strong>
                                        <p class="mb-0" id="original-days-count">0</p>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Days to Refund:</strong>
                                        <p class="mb-0 text-primary" id="refund-days-count">0</p>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Net Days Used:</strong>
                                        <p class="mb-0 text-success" id="net-days-count">0</p>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Balance Impact:</strong>
                                        <p class="mb-0 text-info">+<span id="balance-impact">0</span> days</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
               <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined">close</span>
                    <span class="ms-1">Cancel</span>
                </button>
                <button type="button" class="btn btn-primary" id="save-adjustment">
                    <span class="material-symbols-outlined">save</span>
                    <span class="ms-1">Save Adjustment</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Adjustment History Modal -->
<div class="modal fade" id="adjustmentHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adjustment History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="history-content">
                    <!-- History will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let currentLeaveId = null;
    let isPartialDay = false;
    let totalHours = 0;
    
    let table = $('#leave-adjustments-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ url('/ajax/leave-management/leave-adjustments/list') }}",
            dataSrc: 'leave_requests'
        },
        columns: [
            { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + 1 },
            { 
                data: 'employee', 
                render: data => data ? data.full_names : '-' 
            },
            { 
                data: 'position', 
                render: data => data ? data.position_name : '-' 
            },
            { 
                data: 'leave_type', 
                render: data => data ? data.type_name : '-' 
            },
            { 
                data: null,
                render: data => {
                    let start = new Date(data.start_date).toLocaleDateString();
                    let end = new Date(data.end_date).toLocaleDateString();
                    return `${start} - ${end}`;
                }
            },
            { 
                data: 'days_taken_count',
                className: 'text-center',
                render: (data, type, row) => {
                    if (row.is_partial_day) {
                        return `<span class="badge bg-info">${row.hours_requested} hrs</span>`;
                    }
                    return `<span class="badge bg-primary">${data || 0} days</span>`;
                }
            },
            { 
                data: 'days_refunded_count',
                className: 'text-center',
                render: data => data > 0 ? `<span class="badge bg-success">${data} days</span>` : '-'
            },
            { 
                data: null,
                className: 'text-center',
                render: (data, type, row) => {
                    let net = (row.days_taken_count || 0) - (row.days_refunded_count || 0);
                    if (row.is_partial_day) {
                        return `<strong>${row.hours_requested} hrs</strong>`;
                    }
                    return `<strong>${net} days</strong>`;
                }
            },
            {
                data: 'leave_adjustment_current_status_id',
                render: data => {
                    if( data === null ) return '<span class="badge bg-secondary">Pending</span>';
                    // Status 1 = Pending (for the leave request itself)
                    if (data == 1) return '<span class="badge bg-warning">Pending Approval</span>';
                    // Status 5 = Approved
                    if (data == 5) return '<span class="badge bg-success">Approved</span>';
                    // Status 24 = Partially Approved
                    if (data == 24) return '<span class="badge bg-info">Partially Approved</span>';
                    // Status 41 = Rejected
                    if (data == 41) return '<span class="badge bg-danger">Rejected</span>';
                    // Status 49 = Active/Completed
                    if (data == 49) return '<span class="badge bg-primary">Active</span>';
                    // Status 42 = Closed
                    if (data == 42) return '<span class="badge bg-secondary">Closed</span>';
                    // Default for unknown statuses
                    return '<span class="badge bg-secondary">Unknown</span>';
                }
            },
            {
                data: null,
                orderable: false,
                className: 'text-center',
                render: (data, type, row) => {
                    let btns = '<div class="d-flex justify-content-center gap-1">';
                    if( row.can_update && row.leave_adjustment_current_status_id == 1 ) {
                        btns += `
                            <button class="btn btn-sm adjust-days" data-id="${row.id}" title="Adjust Days">
                                <span class="material-symbols-outlined">edit_calendar</span>
                            </button>
                        `;
                    }
                    if (row.can_view && row.adjustment_count > 0) {
                        btns += `
                            <button class="btn btn-sm view-history" data-id="${row.id}" title="View History">
                                <span class="material-symbols-outlined">history</span>
                            </button>
                        `;
                    }
                    btns += '</div>';
                    return btns;
                }
            }
        ],
        order: [[4, 'desc']],
        paging: true,
        info: true,
        lengthChange: true
    });

    // Adjust Days Button
    $(document).on('click', '.adjust-days', function() {
        let id = $(this).data('id');
        currentLeaveId = id;
        
        // Load leave details
        $.get(`/ajax/leave-management/leave-adjustments/${id}`, function(res) {
            if (!res.success) {
                toastr.error(res.error || 'Failed to load leave details');
                return;
            }
            
            let leave = res.leave_request;
            isPartialDay = res.is_partial_day;
            totalHours = res.hours_requested || 0;
            
            // Populate modal header info
            $('#adjust-employee-name').text(leave.employee?.full_names || '-');
            $('#adjust-position').text(leave.position?.position_name || '-');
            $('#adjust-leave-type').text(leave.leave_type?.type_name || '-');
            $('#adjust-period').text(`${new Date(leave.start_date).toLocaleDateString()} - ${new Date(leave.end_date).toLocaleDateString()}`);
            
            // Show partial day info if applicable
            if (isPartialDay) {
                $('#partial-day-info').removeClass('d-none');
                $('#partial-hours-info').text(`This is a partial day leave of ${totalHours} hours. Refunding this will restore the hours to the employee's balance.`);
            } else {
                $('#partial-day-info').addClass('d-none');
            }
            
            // Populate days taken table
            let tbody = $('#days-taken-list');
            tbody.empty();
            
            if (res.days_taken && res.days_taken.length > 0) {
                res.days_taken.forEach(day => {
                    let statusBadge = '';
                    let checkbox = '';
                    
                    if (day.current_status_id == 5) {
                        statusBadge = '<span class="badge bg-success">Taken</span>';
                        checkbox = `<input type="checkbox" class="form-check-input day-checkbox" value="${day.id}" data-date="${day.leave_date}">`;
                    } else if (day.current_status_id == 41) {
                        statusBadge = '<span class="badge bg-warning">Refunded</span>';
                        checkbox = '<i class="fa fa-check-circle text-success"></i>';
                    }
                    
                    let dayTypeNames = {1: 'Weekday', 2: 'Saturday', 3: 'Sunday'};
                    let dayTypeName = dayTypeNames[day.day_type_id] || 'Unknown';
                    
                    let row = `
                        <tr data-fraction="${day.day_fraction ?? 1}">
                            <td class="text-center">${checkbox}</td>
                            <td>${new Date(day.leave_date).toLocaleDateString()}</td>
                            <td>${dayTypeName}</td>
                            <td>${statusBadge}</td>
                            <td><span class="day-action-text">${day.current_status_id == 41 ? 'Already refunded' : 'Available for refund'}</span></td>
                        </tr>
                    `;
                    tbody.append(row);
                });
            } else {
                tbody.append('<tr><td colspan="5" class="text-center">No days found</td></tr>');
            }
            
            updateSummary();
            $('#adjustLeaveDaysModal').modal('show');
        }).fail(function(xhr) {
            toastr.error('Failed to load leave details');
        });
    });

    // Select All Days
    $(document).on('change', '#select-all-days', function() {
        $('.day-checkbox').prop('checked', this.checked);
        updateSummary();
    });

    // Individual Day Checkbox
    $(document).on('change', '.day-checkbox', function() {
        updateSummary();
    });

    function updateSummary() {
        // Days count by the share of a day they charge (a Saturday is half a day)
        let totalDays = 0;
        $('#days-taken-list tr[data-fraction]').each(function() { totalDays += parseFloat($(this).data('fraction')) || 1; });
        let selectedDays = 0;
        $('.day-checkbox:checked').each(function() { selectedDays += parseFloat($(this).closest('tr').data('fraction')) || 1; });
        let netDays = totalDays - selectedDays;
        
        $('#original-days-count').text(isPartialDay ? `${totalHours} hrs` : `${totalDays} days`);
        $('#refund-days-count').text(isPartialDay && selectedDays > 0 ? `${totalHours} hrs` : `${selectedDays} days`);
        $('#net-days-count').text(isPartialDay ? (selectedDays > 0 ? '0 hrs' : `${totalHours} hrs`) : `${netDays} days`);
        $('#balance-impact').text(isPartialDay && selectedDays > 0 ? totalHours/8 : selectedDays);
    }

    // Save Adjustment
    $('#save-adjustment').on('click', function() {
        let selectedDayIds = [];
        $('.day-checkbox:checked').each(function() {
            selectedDayIds.push($(this).val());
        });
        
        if (selectedDayIds.length === 0) {
            toastr.warning('Please select at least one day to refund');
            return;
        }
        
        let reason = $('#adjustment_reason').val().trim();
        if (!reason) {
            toastr.warning('Please provide a reason for the adjustment');
            $('#adjustment_reason').focus();
            return;
        }
        
        let $btn = $(this);
        let $icon = $btn.find('.material-symbols-outlined');
        let origText = $btn.find('span.ms-1').text();
        
        $icon.addClass('spin');
        $btn.find('span.ms-1').text('Processing...');
        $btn.prop('disabled', true);
        
        $.ajax({
            url: `/ajax/leave-management/leave-adjustments/${currentLeaveId}/adjust`,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                day_ids: selectedDayIds,
                reason: reason,
                is_partial_day: isPartialDay,
                hours_to_refund: isPartialDay ? totalHours : null
            },
            success: function(res) {
                if (res.success) {
                    toastr.success('Leave days adjusted successfully');
                    $('#adjustLeaveDaysModal').modal('hide');
                    table.ajax.reload();
                } else {
                    toastr.error(res.error || 'Failed to adjust leave days');
                }
            },
            error: function(xhr) {
                let msg = 'Failed to adjust leave days';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                }
                toastr.error(msg);
            },
            complete: function() {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);
            }
        });
    });

    // View History Button
    $(document).on('click', '.view-history', function() {
        let id = $(this).data('id');
        
        $.get(`/ajax/leave-management/leave-adjustments/${id}/history`, function(res) {
            if (!res.success) {
                toastr.error('Failed to load adjustment history');
                return;
            }
            
            let html = '<div class="table-responsive"><table class="table table-sm table-bordered">';
            html += '<thead><tr><th>Requested</th><th>Days</th><th>Status</th><th>Reason</th><th>Approvals</th></tr></thead><tbody>';
            
            if (res.history && res.history.length > 0) {
                res.history.forEach(item => {
                    let statusBadge = '';
                    if (item.current_status_id == 1) statusBadge = '<span class="badge bg-warning">Pending</span>';
                    else if (item.current_status_id == 24) statusBadge = '<span class="badge bg-info">Partially Approved</span>';
                    else if (item.current_status_id == 5) statusBadge = '<span class="badge bg-success">Approved</span>';
                    else if (item.current_status_id == 41) statusBadge = '<span class="badge bg-danger">Rejected</span>';
                    
                    let approvals = '';
                    if (item.approved_by) {
                        approvals += `<small>1st: ${item.approved_by.name}<br>${new Date(item.approved_at).toLocaleString()}</small>`;
                    }
                    if (item.confirmed_by) {
                        approvals += `<br><small>2nd: ${item.confirmed_by.name}<br>${new Date(item.confirmed_at).toLocaleString()}</small>`;
                    }
                    if (!approvals) approvals = '-';
                    
                    html += `<tr>
                        <td><small>${item.requested_by.name}<br>${new Date(item.requested_at).toLocaleString()}</small></td>
                        <td>${item.days_refunded} days</td>
                        <td>${statusBadge}</td>
                        <td><small>${item.reason || '-'}</small>${item.rejection_reason ? '<br><span class="text-danger">Rejection: ' + item.rejection_reason + '</span>' : ''}</td>
                        <td>${approvals}</td>
                    </tr>`;
                });
            } else {
                html += '<tr><td colspan="5" class="text-center">No adjustment history found</td></tr>';
            }
            
            html += '</tbody></table></div>';
            $('#history-content').html(html);
            $('#adjustmentHistoryModal').modal('show');
        }).fail(function() {
            toastr.error('Failed to load adjustment history');
        });
    });

    // Reset form on modal hide
    $('#adjustLeaveDaysModal').on('hidden.bs.modal', function() {
        $('#adjustment_reason').val('');
        $('#days-taken-list').empty();
        $('#select-all-days').prop('checked', false);
        currentLeaveId = null;
        isPartialDay = false;
        totalHours = 0;
    });
});
</script>

<style>
.material-symbols-outlined.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    100% { transform: rotate(360deg); }
}

.table-hover tbody tr:hover {
    background-color: #f8f9fa;
}
</style>
@endsection