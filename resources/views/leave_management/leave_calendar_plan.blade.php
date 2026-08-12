@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Leave Calendar Plan</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_leave_calendar_plans')
                                <button class="btn btn-success btn-sm d-flex align-items-center" id="generateAnnualPlansBtn">
                                    <span class="material-symbols-outlined">autorenew</span>
                                    <span class="ms-1">Generate Annual Plans</span>
                                </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="leave-calendar-plan-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
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


<!-- Generate Annual Plans Modal -->
<div class="modal fade" id="generateAnnualPlansModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Annual Leave Plans</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <span class="material-symbols-outlined" style="vertical-align:middle;">info</span>
                    This will generate leave calendar plans for all active staff members for the selected year and leave types.
                </div>
                <div class="mb-3">
                    <label for="plan_year" class="form-label">Year <span style="color:red">*</span></label>
                    <select class="form-select" id="plan_year" required>
                        <option value="{{ date('Y') }}">{{ date('Y') }} (Current Year)</option>
                        <option value="{{ date('Y') + 1 }}">{{ date('Y') + 1 }} (Next Year)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="plan_leave_types" class="form-label">Leave Types <span style="color:red">*</span></label>
                    <select class="form-select select2" id="plan_leave_types" multiple required>
                        <!-- Will be populated via AJAX -->
                    </select>
                    <div class="form-text">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">info</span>
                        Select specific leave types or leave empty to generate for all types
                    </div>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="select_all_leave_types">
                    <label class="form-check-label" for="select_all_leave_types">
                        <strong>Select All Leave Types</strong>
                    </label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="override_existing" value="1">
                    <label class="form-check-label" for="override_existing">
                        Override existing plans for this year
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmGeneratePlans">
                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">autorenew</span>
                    Generate Plans
                </button>
            </div>
        </div>
    </div>
</div>


@endsection
<script>
    $(document).ready(function() {
        let table = $('#leave-calendar-plan-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/leave-management/leave-calendar-plan/list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'leave_calendar_plans'
            },
            columns: [
                { 
                    data: 'employee', 
                    title: 'Employee', 
                    render: data => data && data.name ? data.name : '-' 
                },
                {
                    data: 'leave_types', 
                    title: 'Leave Types & Balances',
                    orderable: false,
                    render: function(data) {
                        if (!data || data.length === 0) return '<span class="text-muted">No leave plans</span>';
                        
                        let html = '<div class="leave-types-container" style="max-height: 300px; overflow-y: auto;">';
                        html += '<table class="table table-sm table-bordered mb-0" style="font-size: 0.875rem;">';
                        html += '<thead class="table-light"><tr>';
                        html += '<th style="width: 30%;">Leave Type</th>';
                        html += '<th style="width: 25%;">Period</th>';
                        html += '<th style="width: 15%;">Entitled</th>';
                        html += '<th style="width: 15%;">Balance</th>';
                        html += '<th style="width: 15%;">Status</th>';
                        html += '</tr></thead><tbody>';
                        
                        data.forEach(function(lt) {
                            let leaveName = lt.leave_type && lt.leave_type.type_name ? lt.leave_type.type_name : 'Unknown';
                            let statusBadge = lt.current_status_id == 5 
                                ? '<span class="badge bg-success">Approved</span>' 
                                : '<span class="badge bg-warning">Pending</span>';
                            
                            html += `<tr>
                                <td><strong>${leaveName}</strong></td>
                                <td><small>${new Date(lt.start_date).getFullYear()}<br>${new Date(lt.start_date).toLocaleDateString('en-GB', {day: '2-digit', month: 'short'})} - ${new Date(lt.end_date).toLocaleDateString('en-GB', {day: '2-digit', month: 'short'})}</small></td>
                                <td class="text-center">${lt.entitled_days}</td>
                                <td class="text-center"><span class="badge bg-${lt.balance > 0 ? 'info' : 'secondary'}">${lt.balance}</span></td>
                                <td class="text-center">${statusBadge}</td>
                            </tr>`;
                        });
                        
                        html += '</tbody></table></div>';
                        return html;
                    },
                    width: '55%'
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            order: [[1, 'asc']] // Sort by employee name
        });

        // Generate Annual Plans
        $('#generateAnnualPlansBtn').on('click', function() {
            // Load leave types when modal opens
            $.ajax({
                url: "{{ url('/ajax/hr/leave_types/list') }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    let $planLeaveTypes = $('#plan_leave_types');
                    $planLeaveTypes.empty();
                    
                    if (response.success && response.leave_types) {
                        response.leave_types.forEach(function(leaveType) {
                            $planLeaveTypes.append(new Option(leaveType.type_name, leaveType.id, false, false));
                        });
                        
                        // Initialize or refresh Select2
                        if ($planLeaveTypes.hasClass('select2-hidden-accessible')) {
                            $planLeaveTypes.select2('destroy');
                        }
                        $planLeaveTypes.select2({
                            dropdownParent: $('#generateAnnualPlansModal'),
                            placeholder: 'Select leave types (leave empty for all)',
                            allowClear: true,
                            width: '100%'
                        });
                    }
                },
                error: function() {
                    toastr.error('Failed to load leave types');
                }
            });
            
            $('#generateAnnualPlansModal').modal('show');
        });
        
        // Select/Deselect all leave types
        $('#select_all_leave_types').on('change', function() {
            let $planLeaveTypes = $('#plan_leave_types');
            if ($(this).is(':checked')) {
                $planLeaveTypes.find('option').prop('selected', true);
                $planLeaveTypes.trigger('change');
            } else {
                $planLeaveTypes.val(null).trigger('change');
            }
        });
        
        // Uncheck "select all" when manually changing selection
        $('#plan_leave_types').on('change', function() {
            let totalOptions = $(this).find('option').length;
            let selectedOptions = $(this).val() ? $(this).val().length : 0;
            $('#select_all_leave_types').prop('checked', totalOptions > 0 && selectedOptions === totalOptions);
        });
        
        $('#confirmGeneratePlans').on('click', function() {
            let $btn = $(this);
            let year = $('#plan_year').val();
            let leaveTypeIds = $('#plan_leave_types').val(); // Array or null
            let override = $('#override_existing').is(':checked') ? 1 : 0;
            
            if (!year) {
                toastr.error('Please select a year');
                return;
            }
            
            // If no leave types selected, it means generate for all
            let confirmMessage = `Are you sure you want to generate leave plans for all staff for ${year}?\n\n`;
            if (leaveTypeIds && leaveTypeIds.length > 0) {
                confirmMessage += `This will generate plans for ${leaveTypeIds.length} selected leave type(s).\n\n`;
            } else {
                confirmMessage += `This will generate plans for ALL leave types.\n\n`;
            }
            confirmMessage += `This will run in the background and may take several minutes.`;
            
            if (!confirm(confirmMessage)) {
                return;
            }
            
            $btn.prop('disabled', true);
            $btn.html('<span class="material-symbols-outlined spin" style="font-size:16px; vertical-align:middle;">sync</span> Starting...');
            
            $.ajax({
                url: "{{ url('/ajax/hr/leave-management/leave-calendar-plan/generate-annual') }}",
                type: 'POST',
                data: {
                    year: year,
                    leave_type_ids: leaveTypeIds, // Send array or null
                    override: override,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    $btn.prop('disabled', false);
                    $btn.html('<span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">autorenew</span> Generate Plans');
                    
                    if (response.success) {
                        $('#generateAnnualPlansModal').modal('hide');
                        toastr.success(response.message, 'Job Started', {timeOut: 8000});
                        
                        // Optionally reload table after a delay
                        setTimeout(function() {
                            table.ajax.reload(null, false);
                        }, 5000);
                    } else {
                        toastr.error(response.error || 'Failed to start generation');
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false);
                    $btn.html('<span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">autorenew</span> Generate Plans');
                    
                    let msg = 'Failed to start generation';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });
        
        // Reset modal when closed
        $('#generateAnnualPlansModal').on('hidden.bs.modal', function() {
            $('#plan_leave_types').val(null).trigger('change');
            $('#select_all_leave_types').prop('checked', false);
            $('#override_existing').prop('checked', false);
        });
    });
</script>