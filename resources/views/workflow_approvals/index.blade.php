<!-- filepath: d:\otherProjects\Chips\chips_2025\resources\views\workflow_approvals\index.blade.php -->
@extends('layouts.main')
@section('content')

<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header card-no-border d-flex align-items-center">
                <h4 class="mb-0">Workflow Approvals</h4>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <select class="form-select" id="approval-section" data-allow-clear="true">
                        <option value="">-- Select Approval Section --</option>
                        @foreach($approvalSections as $section)
                            @permission($section['permission'])
                                <option value="{{ $section['label'] }}">{{ $section['label'] }}</option>
                            @endpermission
                        @endforeach
                    </select>
                </div>
                <hr class="mt-4 mb-4">
                <div class="mb-2 d-flex justify-content-end">
                    <button class="btn btn-primary btn-sm me-4 d-none d-flex align-items-center" id="bulk-approve"><span class="material-symbols-outlined me-2" style="font-size:18px;">check</span>Bulk Approve</button>
                    <button class="btn btn-light btn-sm d-none d-flex align-items-center" id="bulk-reject"><span class="material-symbols-outlined me-2" style="font-size:18px;">close_small</span>Bulk Reject</button>
                </div>
                <div class="table-responsive">
                    <table class="display table table-bordered" id="workflow-approvals-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all"></th>
                                <th>#</th>
                                <th>Type</th>
                                <th>Requested By</th>
                                <th>Requested At</th>
                                <th>Status</th>
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

<!-- Leave Application Days Modal (Stage 1 - Set Days) -->
<div class="modal fade" id="leaveDaysModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Set Actual Leave Days Taken</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="leave-days-content">
                    <!-- Content loaded dynamically -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined">close</span>
                    <span class="ms-1">Cancel</span>
                </button>
                <button type="button" class="btn btn-primary d-flex align-items-center" id="confirm-leave-days">
                    <span class="material-symbols-outlined">check</span>
                    <span class="ms-1">Confirm Days</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Leave Application Confirmation Modal (Stage 2 - Confirm Days) -->
<div class="modal fade" id="leaveConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Leave Days Taken</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="leave-confirm-content">
                    <!-- Content loaded dynamically -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined">close</span>
                    <span class="ms-1">Cancel</span>
                </button>
                <button type="button" class="btn btn-primary d-flex align-items-center" id="final-confirm-leave">
                    <span class="material-symbols-outlined">check</span>
                    <span class="ms-1">Approve</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
$(document).ready(function() {
    let currentLeaveId = null;
    let currentSection = '';
    let isPartialDayLeave = false;
    let leaveDurationType = 'full_day';
    
    let table = $('#workflow-approvals-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ route('ajax.workflow.approvals.list') }}",
            type: 'GET',
            data: function(d) {
                d.section = $('#approval-section').val();
            },
            dataSrc: 'records'
        },
        columns: [
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(id, type, row) {
                    return `<input type="checkbox" class="select-row" value="${id}" data-stage="${row.approval_stage || 0}">`;
                }
            },
            { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + 1 },
            { data: 'type', title: 'Type' },
            { data: 'requested_by', title: 'Requested By', render: data => data || '-' },
            { data: 'requested_at', title: 'Requested At', render: data => data ? new Date(data).toLocaleString() : '-' },
            { data: 'status', title: 'Status', render: data => data || '-' },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(id, type, row) {
                    let section = $('#approval-section').val();
                    let approveBtn = '';
                    
                    if (section === 'Leave Application') {
                        if (row.approval_stage === 1) {
                            approveBtn = `<button class="btn btn-xs btn-primary approve-leave-stage1" data-id="${row.id}" data-section="${section}" style="padding:2px 6px;">
                                <span class="material-symbols-outlined me-1" style="font-size:16px;">edit_calendar</span> Set Days
                            </button>`;
                        } else if (row.approval_stage === 2) {
                            approveBtn = `<button class="btn btn-xs btn-success approve-leave-stage2" data-id="${row.id}" data-section="${section}" style="padding:2px 6px;">
                                <span class="material-symbols-outlined me-1" style="font-size:16px;">check_circle</span> Confirm
                            </button>`;
                        }
                    } else {
                        approveBtn = `<button class="btn btn-xs btn-light approve-btn d-flex align-items-center" data-id="${row.id}" data-section="${section}" style="padding:1px 2px;">
                            <span class="material-symbols-outlined me-2" style="font-size:18px;">check</span> Approve
                        </button>`;
                    }
                    
                    return `
                    <div class="d-flex flex-row gap-2 align-items-center">
                        ${approveBtn}
                        <button class="btn btn-xs btn-light reject-btn d-flex align-items-center" data-id="${row.id}" data-section="${section}" style="padding:1px 2px;">
                            <span class="material-symbols-outlined me-2" style="font-size:18px;">close_small</span> Reject
                        </button>
                    </div>
                    `;
                }
            }
        ],
        paging: true,
        info: true,
        lengthChange: true
    });

    $('#approval-section').select2(
        {
            placeholder: "-- Select Approval Section --",
            allowClear: true,
            width: '100%'
        }
    );

    // Reload table on section change
    $('#approval-section').on('change', function() {
        selectedIds.clear();
        table.ajax.reload();
    });
    
    // Track selected IDs across all pages
    let selectedIds = new Set();
    
    function toggleBulkButtons() {
        if (selectedIds.size > 0) {
            $('#bulk-approve').removeClass('d-none');
            $('#bulk-reject').removeClass('d-none');
        } else {
            $('#bulk-approve').addClass('d-none');
            $('#bulk-reject').addClass('d-none');
        }
    }
    
    // Handle row checkbox change
    $(document).on('change', '.select-row', function(e) {
        e.stopPropagation();
        let id = $(this).val();
        let stage = $(this).data('stage');
        
        if ($(this).is(':checked')) {
            // For Leave Application Stage 1, don't allow selection (can't bulk approve)
            if ($('#approval-section').val() === 'Leave Application' && stage === 1) {
                $(this).prop('checked', false);
                toastr.warning('Stage 1 leave applications cannot be bulk approved');
                return;
            }

            // For Communication Templates, don't allow selection (can't bulk approve)
            if (section === 'Communication Templates') {
                $(this).prop('checked', false);
                toastr.warning('Communication templates cannot be bulk approved');
                return;
            }
            
            selectedIds.add(id);
        } else {
            selectedIds.delete(id);
        }
        toggleBulkButtons();
    });
    
    // Handle select-all checkbox
    $(document).on('change', '#select-all', function() {
        let checked = this.checked;
        let section = $('#approval-section').val();

        // Don't allow select-all for Communication Templates
        if (checked && section === 'Communication Templates') {
            $(this).prop('checked', false);
            toastr.warning('Communication templates cannot be bulk approved');
            return;
        }
        
        $('.select-row').each(function() {
            let stage = $(this).data('stage');
            // Don't select Stage 1 leave applications
            if (section === 'Leave Application' && stage === 1) {
                return;
            }
            // Don't select Communication Templates (redundant but safe)
            if (section === 'Communication Templates') {
                return;
            }
            $(this).prop('checked', checked).trigger('change');
        });
    });
    
    // On table redraw, re-check selected checkboxes
    table.on('draw', function() {
        $('.select-row').each(function() {
            if (selectedIds.has($(this).val())) {
                $(this).prop('checked', true);
            }
        });
        toggleBulkButtons();
    });

    // Show details modal on row click
    $('#workflow-approvals-table tbody').on('click', 'tr', function(e) {
        // Don't trigger if clicking checkbox or action buttons
        if ($(e.target).closest('input[type="checkbox"], button').length) return;
        
        let data = table.row(this).data();
        if (!data) return;
        
        let detailsContent = `
            <div class="row g-2">
                <div class="col-12">
                    <small class="text-muted">Type:</small><br>
                    <strong>${data.type || '-'}</strong>
                </div>`;
        
        // Add duration type for leave applications
        if (data.type === 'Leave Application' && data.duration_type) {
            detailsContent += `
                <div class="col-12">
                    <small class="text-muted">Duration Type:</small><br>
                    <strong>${data.duration_type}</strong>
                </div>`;
        }
        
        detailsContent += `
                <div class="col-12">
                    <small class="text-muted">Requested By:</small><br>
                    <strong>${data.requested_by || '-'}</strong>
                </div>
                <div class="col-12">
                    <small class="text-muted">Requested At:</small><br>
                    <strong>${data.requested_at ? new Date(data.requested_at).toLocaleString() : '-'}</strong>
                </div>`;
        
        // Add Stage 1 Approver for Leave Applications in Stage 2
        if (data.type === 'Leave Application' && data.approval_stage === 2 && data.stage_one_confirmed_by) {
            detailsContent += `
                <div class="col-12">
                    <small class="text-muted">Stage 1 Approved By:</small><br>
                    <strong class="text-success">${data.stage_one_confirmed_by}</strong>
                </div>`;
        }
        
        detailsContent += `
                <div class="col-12">
                    <small class="text-muted">Status:</small><br>
                    <strong>${data.status || '-'}</strong>
                </div>
                <div class="col-12">
                    <small class="text-muted">Details:</small><br>
                    <div class="border p-2 rounded">
                        ${data.details || '-'}
                    </div>
                </div>`;
        
        if (data.days_taken && data.days_taken.length > 0) {
            detailsContent += `
                <div class="col-12 mt-3">
                    <small class="text-muted">Days Set for Confirmation (Stage 2):</small><br>
                    <div class="border p-2 rounded">
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Day Type</th>
                                </tr>
                            </thead>
                            <tbody>`;
            data.days_taken.forEach(function(day) {
                detailsContent += `
                                <tr>
                                    <td>${day.date}</td>
                                    <td>${day.type}</td>
                                </tr>`;
            });
            detailsContent += `
                            </tbody>
                        </table>`;
            
            // Add Stage 1 approver info at the bottom of the days table
            if (data.stage_one_confirmed_by) {
                detailsContent += `
                        <div class="mt-2 p-2 bg-light rounded">
                            <small class="text-muted">
                                <i class="fa fa-info-circle"></i> 
                                These days were set by: <strong>${data.stage_one_confirmed_by}</strong>
                            </small>
                        </div>`;
            }
            
            detailsContent += `
                    </div>
                </div>`;
        }
        
        detailsContent += `
            </div>
        `;
        
        // Create modal content
        let modalContent = `
            <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Approval Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${detailsContent}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        $('#detailsModal').remove();
        
        // Add modal to body and show
        $('body').append(modalContent);
        $('#detailsModal').modal('show');
        
        // Clean up modal after it's hidden
        $('#detailsModal').on('hidden.bs.modal', function() {
            $(this).remove();
        });
    });

    // Add pointer cursor to rows
    $('#workflow-approvals-table tbody').css('cursor', 'pointer');

    function setButtonLoading($btn, loading = true) {
        if (loading) {
            $btn.prop('disabled', true);
            let spinner = '<span class="spinner-border spinner-border-sm ms-2" role="status" aria-hidden="true"></span>';
            if ($btn.find('.spinner-border').length === 0) {
                $btn.append(spinner);
            }
        } else {
            $btn.prop('disabled', false);
            $btn.find('.spinner-border').remove();
        }
    }
    
    // Stage 1: Set Leave Days
    $(document).on('click', '.approve-leave-stage1', function(e) {
        e.stopPropagation();
        let id = $(this).data('id');
        currentLeaveId = id;
        currentSection = $(this).data('section');
        
        // Get suggested days
        $.get(`/ajax/workflow-approvals/leave-application-days/${id}`, function(res) {
            if (res.success && res.days) {
                isPartialDayLeave = res.is_partial_day || false;
                leaveDurationType = res.leave_duration_type || 'full_day';
                leaveDurationType = res.leave_duration_type || 'full_day';
                let content = '';
                
                if (leaveDurationType === 'partial_day') {
                    // Hours only - same day with specific times
                    let day = res.days[0];
                    let holidayBadge = day.is_public_holiday ? `<span class="badge bg-danger ms-2"><i class="fa fa-calendar-times"></i> ${day.holiday_title}</span>` : '';
                    
                    content = `
                        <div class="alert alert-info">
                            <strong>Leave Type:</strong> Hours Only (Partial Day)<br>
                            <strong>Total Hours:</strong> ${day.hours || 0} hours
                        </div>
                        ${day.is_public_holiday ? `<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> <strong>Note:</strong> This date is a public holiday: ${day.holiday_title}</div>` : ''}
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date ${holidayBadge}</label>
                                <input type="text" class="form-control ${day.is_public_holiday ? 'border-danger' : ''}" value="${day.date}" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Day</label>
                                <input type="text" class="form-control" value="${day.day_name}" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="partial_start_time" value="${day.start_time || ''}" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Time</label>
                                <input type="time" class="form-control" id="partial_end_time" value="${day.end_time || ''}" readonly>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Day Type</label>
                                <select class="form-select" id="partial_day_type">
                                    <option value="1" ${day.type == 1 ? 'selected' : ''}>Weekday</option>
                                    <option value="2" ${day.type == 2 ? 'selected' : ''}>Saturday</option>
                                    <option value="3" ${day.type == 3 ? 'selected' : ''}>Sunday</option>
                                </select>
                            </div>
                            <input type="hidden" id="partial_date" value="${day.date}">
                        </div>`;
                        
                } else if (leaveDurationType === 'days_and_hours') {
                    // Days + Hours - multiple days with specific start/end times
                    content = `
                        <div class="alert alert-info">
                            <strong>Leave Type:</strong> Days + Hours<br>
                            <strong>Total Hours:</strong> ${res.total_hours || 0} hours<br>
                            <strong>Equivalent Days:</strong> ${Math.floor(res.total_hours / 8)} full days + ${(res.total_hours % 8).toFixed(2)} hours
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Select</th>
                                        <th>Date</th>
                                        <th>Day</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Type</th>
                                    </tr>
                                </thead>
                                <tbody id="leave-days-list">`;
                    
                    res.days.forEach(function(day, index) {
                        // Don't pre-check if it's a public holiday or Sunday
                        let shouldCheck = !day.is_public_holiday && day.type != 3;
                        let rowClass = day.is_public_holiday ? 'table-danger' : '';
                        let holidayBadge = day.is_public_holiday ? `<br><small class="text-danger"><i class="fa fa-calendar-times"></i> ${day.holiday_title}</small>` : '';
                        
                        content += `
                                    <tr class="${rowClass}">
                                        <td><input type="checkbox" class="day-checkbox" ${shouldCheck ? 'checked' : ''} data-index="${index}"></td>
                                        <td>${day.date}${holidayBadge}</td>
                                        <td>${day.day_name}</td>
                                        <td>${day.has_start_time ? '<span class="badge bg-primary">' + day.start_time + '</span>' : '-'}</td>
                                        <td>${day.has_end_time ? '<span class="badge bg-primary">' + day.end_time + '</span>' : '-'}</td>
                                        <td>
                                            <select class="form-select form-select-sm day-type-select" data-index="${index}">
                                                <option value="1" ${day.type == 1 ? 'selected' : ''}>Weekday</option>
                                                <option value="2" ${day.type == 2 ? 'selected' : ''}>Saturday</option>
                                                <option value="3" ${day.type == 3 ? 'selected' : ''}>Sunday</option>
                                            </select>
                                            <input type="hidden" class="day-date" value="${day.date}" data-index="${index}">
                                        </td>
                                    </tr>`;
                    });
                    
                    content += `
                                </tbody>
                            </table>
                        </div>`;
                        
                } else {
                    // Full day(s) - traditional day-based leave
                    let publicHolidaysCount = res.days.filter(d => d.is_public_holiday).length;
                    let sundaysCount = res.days.filter(d => d.type == 3).length;
                    let excludedCount = publicHolidaysCount + sundaysCount;
                    let holidayWarning = excludedCount > 0 ? `
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle"></i> <strong>Warning:</strong> 
                            ${publicHolidaysCount > 0 ? publicHolidaysCount + ' public holiday' + (publicHolidaysCount > 1 ? 's' : '') : ''}
                            ${publicHolidaysCount > 0 && sundaysCount > 0 ? ' and ' : ''}
                            ${sundaysCount > 0 ? sundaysCount + ' Sunday' + (sundaysCount > 1 ? 's' : '') : ''}
                            detected in this leave period (unchecked by default).
                        </div>` : '';
                    
                    content = `
                        <div class="alert alert-info">
                            <strong>Leave Type:</strong> Full Day(s)<br>
                            <strong>Instructions:</strong> Select the actual days the employee took leave.
                        </div>
                        ${holidayWarning}
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Select</th>
                                        <th>Date</th>
                                        <th>Day</th>
                                        <th>Type</th>
                                    </tr>
                                </thead>
                                <tbody id="leave-days-list">`;
                    
                    res.days.forEach(function(day, index) {
                        // Don't pre-check if it's a public holiday or Sunday
                        let shouldCheck = !day.is_public_holiday && day.type != 3;
                        let rowClass = day.is_public_holiday ? 'table-danger' : (day.type == 3 ? 'table-warning' : '');
                        let holidayBadge = day.is_public_holiday ? `<br><small class="text-danger"><i class="fa fa-calendar-times"></i> ${day.holiday_title}</small>` : '';
                        
                        content += `
                                    <tr class="${rowClass}">
                                        <td><input type="checkbox" class="day-checkbox" ${shouldCheck ? 'checked' : ''} data-index="${index}"></td>
                                        <td>${day.date}${holidayBadge}</td>
                                        <td>${day.day_name}</td>
                                        <td>
                                            <select class="form-select form-select-sm day-type-select" data-index="${index}">
                                                <option value="1" ${day.type == 1 ? 'selected' : ''}>Weekday</option>
                                                <option value="2" ${day.type == 2 ? 'selected' : ''}>Saturday</option>
                                                <option value="3" ${day.type == 3 ? 'selected' : ''}>Sunday</option>
                                            </select>
                                            <input type="hidden" class="day-date" value="${day.date}" data-index="${index}">
                                        </td>
                                    </tr>`;
                    });
                    
                    content += `
                                </tbody>
                            </table>
                        </div>`;
                }
                
                $('#leave-days-content').html(content);
                $('#leaveDaysModal').modal('show');
            } else {
                toastr.error('Failed to load leave days');
            }
        }).fail(function(xhr) {
            toastr.error('Failed to load leave days');
        });


    });
    
    // Confirm Leave Days (Stage 1)
    $('#confirm-leave-days').on('click', function() {
        let days = [];
        
        if (isPartialDayLeave) {
            // For partial day, just confirm the single day
            days.push({
                date: $('#partial_date').val(),
                type: $('#partial_day_type').val()
            });
        } else {
            // For full days, get all checked days
            $('.day-checkbox:checked').each(function() {
                let index = $(this).data('index');
                days.push({
                    date: $(`.day-date[data-index="${index}"]`).val(),
                    type: $(`.day-type-select[data-index="${index}"]`).val()
                });
            });
        }
        
        if (days.length === 0) {
            toastr.error('Please select at least one day');
            return;
        }
        
        let $btn = $(this);
        setButtonLoading($btn, true);
        
        $.post(`/ajax/workflow/approval/${currentLeaveId}/approve`, {
            _token: $('meta[name="csrf-token"]').attr('content'),
            section: currentSection,
            days_taken: days
        }, function(res) {
            if (res.success) {
                toastr.success('Days set successfully. Waiting for second approval.');
                $('#leaveDaysModal').modal('hide');
                table.ajax.reload();
            } else {
                toastr.error(res.error || 'Failed to set days');
            }
        }).fail(function(xhr) {
            let msg = 'Failed to set days';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                msg = xhr.responseJSON.error;
            }
            toastr.error(msg);
        }).always(function() {
            setButtonLoading($btn, false);
        });
    });
    
    // Stage 2: Confirm Leave Days - remains the same
    $(document).on('click', '.approve-leave-stage2', function(e) {
        e.stopPropagation();
        let id = $(this).data('id');
        currentLeaveId = id;
        currentSection = $(this).data('section');
        
        // Get days to confirm
        $.get(`/ajax/workflow-approvals/leave-application-days/${id}`, function(res) {
            if (res.success && res.days) {
                let content = `
                    <div class="alert alert-warning">
                        <strong>Review:</strong> Please confirm the days set by the first approver.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Day</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>`;
                
                res.days.forEach(function(day) {
                    content += `
                                <tr>
                                    <td>${day.date}</td>
                                    <td>${day.day_name}</td>
                                    <td>
                                        ${day.type == 1 ? '<span class="badge bg-primary">Weekday</span>' : 
                                          day.type == 2 ? '<span class="badge bg-info">Saturday</span>' : 
                                          '<span class="badge bg-warning">Sunday</span>'}
                                    </td>
                                </tr>`;
                });
                
                content += `
                            </tbody>
                        </table>
                        <div class="text-end">
                            <strong>Total Days: ${res.days.length}</strong>
                        </div>
                    </div>`;
                
                $('#leave-confirm-content').html(content);
                $('#leaveConfirmModal').modal('show');
            } else {
                toastr.error('Failed to load days for confirmation');
            }
        }).fail(function(xhr) {
            toastr.error('Failed to load days for confirmation');
        });
    });
    
    // Final Confirm Leave (Stage 2)
    $('#final-confirm-leave').on('click', function() {
        let $btn = $(this);
        setButtonLoading($btn, true);
        
        $.post(`/ajax/workflow/approval/${currentLeaveId}/approve`, {
            _token: $('meta[name="csrf-token"]').attr('content'),
            section: currentSection,
            confirm_days: true
        }, function(res) {
            if (res.success) {
                toastr.success('Leave application fully approved');
                $('#leaveConfirmModal').modal('hide');
                table.ajax.reload();
            } else {
                toastr.error(res.error || 'Failed to confirm');
            }
        }).fail(function(xhr) {
            let msg = 'Failed to confirm';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                msg = xhr.responseJSON.error;
            }
            toastr.error(msg);
        }).always(function() {
            setButtonLoading($btn, false);
        });
    });
    
    // Approve single (non-leave applications)
    $(document).on('click', '.approve-btn', function(e) {
        e.stopPropagation();
        let $btn = $(this);
        let id = $btn.data('id');
        let section = $btn.data('section');
        if (!confirm('Are you sure you want to approve this item?')) return;
        setButtonLoading($btn, true);
        $.post(`/ajax/workflow/approval/${id}/approve`, {
            _token: $('meta[name="csrf-token"]').attr('content'),
            section: section
        }, function(res) {
            if (res.success) {
                toastr.success('Approved successfully');
                selectedIds.delete(id);
                table.ajax.reload();
            } else {
                toastr.error(res.error || 'Failed to approve');
            }
        }).always(function() {
            setButtonLoading($btn, false);
        });
    });
    
    // Reject single
    $(document).on('click', '.reject-btn', function(e) {
        e.stopPropagation();
        let $btn = $(this);
        let id = $btn.data('id');
        let section = $btn.data('section');
        if (!confirm('Are you sure you want to reject this item?')) return;
        setButtonLoading($btn, true);
        $.post(`/ajax/workflow/approval/${id}/reject`, {
            _token: $('meta[name="csrf-token"]').attr('content'),
            section: section
        }, function(res) {
            if (res.success) {
                toastr.success('Rejected successfully');
                selectedIds.delete(id);
                table.ajax.reload();
            } else {
                toastr.error(res.error || 'Failed to reject');
            }
        }).always(function() {
            setButtonLoading($btn, false);
        });
    });
    
    // Bulk Approve
    $('#bulk-approve').on('click', function() {
        let $btn = $(this);
        let section = $('#approval-section').val();
        let ids = Array.from(selectedIds);
        if (ids.length === 0) return alert('Select at least one item.');
        if (!confirm('Are you sure you want to approve the selected items?')) return;
        setButtonLoading($btn, true);
        $.post('/ajax/workflow/approval/bulk/approve', {
            _token: $('meta[name="csrf-token"]').attr('content'),
            ids: ids,
            section: section
        }, function(res) {
            if (res.success) {
                toastr.success('Bulk approved successfully');
                selectedIds.clear();
                table.ajax.reload();
            } else {
                toastr.error(res.error || 'Failed to bulk approve');
            }
        }).always(function() {
            setButtonLoading($btn, false);
        });
    });
    
    // Bulk Reject
    $('#bulk-reject').on('click', function() {
        let $btn = $(this);
        let section = $('#approval-section').val();
        let ids = Array.from(selectedIds);
        if (ids.length === 0) return alert('Select at least one item.');
        if (!confirm('Are you sure you want to reject the selected items?')) return;
        setButtonLoading($btn, true);
        $.post('/ajax/workflow/approval/bulk/reject', {
            _token: $('meta[name="csrf-token"]').attr('content'),
            ids: ids,
            section: section
        }, function(res) {
            if (res.success) {
                toastr.success('Bulk rejected successfully');
                selectedIds.clear();
                table.ajax.reload();
            } else {
                toastr.error(res.error || 'Failed to bulk reject');
            }
        }).always(function() {
            setButtonLoading($btn, false);
        });
    });
});
</script>
@endsection