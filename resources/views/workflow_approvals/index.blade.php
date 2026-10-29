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
                            <option value="{{ $section['label'] }}">{{ $section['label'] }}</option>
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

<!-- Leave Application Approval Modal (preview of the days that will be charged) -->
<div class="modal fade" id="leaveConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Leave</h5>
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

<script>
$(document).ready(function() {
    let currentLeaveId = null;
    let currentSection = '';

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
                    return `<input type="checkbox" class="select-row" value="${id}">`;
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
                        approveBtn = `<button class="btn btn-xs btn-success approve-leave" data-id="${row.id}" data-section="${section}" style="padding:2px 6px;">
                            <span class="material-symbols-outlined me-1" style="font-size:16px;">check_circle</span> Approve
                        </button>`;
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
        let section = $('#approval-section').val();

        if ($(this).is(':checked')) {
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
        
        if (data.type === 'Leave Application') {
            const colleague = $('<div>').text(data.handover_employee_name || 'Not recorded').html();
            const notes = $('<div>').text(data.handover_notes || 'No handover notes recorded.').html();
            detailsContent += `<div class="col-12 mt-3"><strong>Work handover</strong>
                <p class="mb-1">Hand over to: ${colleague}</p>
                <div class="border rounded p-2" style="white-space:pre-wrap;overflow-wrap:anywhere;">${notes}</div></div>`;
        }

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
    
    // Leave: show the days that will be charged, then approve
    $(document).on('click', '.approve-leave', function(e) {
        e.stopPropagation();
        currentLeaveId = $(this).data('id');
        currentSection = $(this).data('section');

        $.get(`/ajax/workflow-approvals/leave-application-days/${currentLeaveId}`, function(res) {
            if (!(res.success && res.days)) {
                toastr.error(res.error || 'Failed to load the leave days');
                return;
            }

            let content = `
                <div class="alert alert-info">
                    These are the days that will be charged to the employee's leave balance${res.is_partial_day ? ' (' + res.total_hours + ' hours)' : ''}.
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Type</th>
                                <th>Charged</th>
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
                                <td>${parseFloat(day.day_fraction)}</td>
                            </tr>`;
            });

            content += `
                        </tbody>
                    </table>
                    <div class="text-end">
                        <strong>Total Days: ${res.total_days}</strong>
                    </div>
                </div>`;

            $('#leave-confirm-content').html(content);
            $('#leaveConfirmModal').modal('show');
        }).fail(function(xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to load the leave days');
        });
    });

    // Leave: approve (supervisor stage or HR final stage, the server decides which)
    $('#final-confirm-leave').on('click', function() {
        let $btn = $(this);
        setButtonLoading($btn, true);

        $.post(`/ajax/workflow/approval/${currentLeaveId}/approve`, {
            _token: $('meta[name="csrf-token"]').attr('content'),
            section: currentSection
        }, function(res) {
            if (res.success) {
                toastr.success('Leave application approved');
                $('#leaveConfirmModal').modal('hide');
                table.ajax.reload();
            } else {
                toastr.error(res.error || 'Failed to approve');
            }
        }).fail(function(xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to approve');
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
        }).fail(function(xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to approve');
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
        }).fail(function(xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to reject');
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
        }).fail(function(xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to bulk approve');
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
        }).fail(function(xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to bulk reject');
        }).always(function() {
            setButtonLoading($btn, false);
        });
    });
});
</script>
@endsection
