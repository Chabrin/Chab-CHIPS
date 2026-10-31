@extends('layouts.main')
@section('styles')
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
@endsection
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Leave Applications</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('read_leave_applications')
                            <button type="button" id="export-leave-applications" class="btn btn-outline-success btn-sm d-flex align-items-center" disabled title="Export the Applications List search across all pages; calendar dates do not filter this export">
                                <span class="material-symbols-outlined" style="font-size:18px;">download</span>
                                <span class="ms-1">Export CSV</span>
                            </button>
                            @endpermission
                            @permission('create_leave_applications')
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addLeaveApplicationModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Leave Application</span>
                            </button>
                            @endpermission
                        </div>
                    </div>

                    <div class="card-body">
                        @permission('read_leave_applications')
                        <div id="leave-applications-export-status" class="small text-muted mb-2" role="status" aria-live="polite"></div>
                        @endpermission
                        <!-- Tabs Navigation -->
                        <ul class="nav nav-tabs mb-4" id="leaveTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">list</span>
                                    Applications List
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="calendar-tab" data-bs-toggle="tab" data-bs-target="#calendar" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">calendar_month</span>
                                    Calendar View
                                </button>
                            </li>
                        </ul>

                        <!-- Tabs Content -->
                        <div class="tab-content" id="leaveTabsContent">
                            <!-- Applications List Tab -->
                            <div class="tab-pane fade show active" id="list" role="tabpanel" aria-labelledby="list-tab">
                                <div class="row g-2 align-items-end mb-3">
                                    <div class="col-md-3"><label class="form-label mb-1" for="leave-filter-status">Status</label>
                                        <select id="leave-filter-status" class="form-select form-select-sm">
                                            <option value="">All statuses</option><option value="1">Pending HOD</option><option value="24">Pending HR</option><option value="5">Approved</option><option value="41">Rejected</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3"><label class="form-label mb-1" for="leave-filter-department">Department</label>
                                        <select id="leave-filter-department" class="form-select form-select-sm"><option value="">All departments</option>
                                            @foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->department_name }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3"><label class="form-label mb-1" for="leave-filter-from">Leave overlaps from</label><input type="date" id="leave-filter-from" class="form-control form-control-sm"></div>
                                    <div class="col-md-3"><label class="form-label mb-1" for="leave-filter-to">Through</label><input type="date" id="leave-filter-to" class="form-control form-control-sm"></div>
                                    <div class="col-md-3 d-flex gap-2"><button type="button" id="apply-leave-filters" class="btn btn-primary btn-sm">Apply filters</button><button type="button" id="clear-leave-filters" class="btn btn-light btn-sm">Clear</button></div>
                                </div>
                                <div class="card border mb-3" id="department-leave-panel">
                                    <div class="card-header py-2 d-flex justify-content-between align-items-center"><strong>Who is on approved leave</strong><span class="small text-muted" id="department-leave-period"></span></div>
                                    <div class="card-body py-2" id="department-leave-results"><span class="text-muted">Loading leave schedule…</span></div>
                                </div>
                                <div class="table-responsive custom-scrollbar">
                                    <table class="display" id="leave-applications-table" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Staff</th>
                                                <th>Department</th>
                                                <th>Designation</th>
                                                <th>Leave Type</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Current Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Data loaded via DataTables AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Calendar View Tab -->
                            <div class="tab-pane fade" id="calendar" role="tabpanel" aria-labelledby="calendar-tab">
                                <div id="leave-calendar"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Leave Application Modal -->
<div class="modal fade" id="addLeaveApplicationModal" tabindex="-1" aria-labelledby="addLeaveApplicationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addLeaveApplicationModalLabel">Leave Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="leaveApplicationForm">
                    <input type="hidden" name="id" id="leaveApplicationId">

                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <label for="staff_id" class="form-label">Staff <span style="color:red">*</span></label>
                            <select class="form-select" name="staff_id" id="staff_id" required>
                                <!-- Populate dynamically -->
                            </select>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label for="leave_type_id" class="form-label">Leave Type <span style="color:red">*</span></label>
                            <select class="form-select" name="leave_type_id" id="leave_type_id" required>
                                <!-- Populate dynamically -->
                            </select>
                        </div>
                    </div>

                    <!-- Pre-scheduled Annual Leave Block Picker -->
                    <div class="row mb-3 d-none" id="annualSlotPickerContainer">
                        <div class="col-12">
                            <div class="p-3 bg-light border rounded">
                                <label for="scheduled_slot_select" class="form-label fw-bold text-primary mb-1">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">event_available</span>
                                    Select Pre-Scheduled 21-Day Leave Block <span style="color:red">*</span>
                                </label>
                                <select class="form-select" id="scheduled_slot_select">
                                    <option value="">-- Choose from your scheduled plan --</option>
                                </select>
                                <small class="text-muted">Dates and working days will automatically populate from your approved annual calendar plan.</small>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3 d-none" id="annualReservedDateNotice">
                        <div class="col-12"><div class="alert alert-warning py-2 mb-0" id="annualReservedDateText"></div></div>
                    </div>

                    <!-- Sick Leave Pay Tier Notice -->
                    <div class="row mb-3 d-none" id="sickLeaveTierAlert">
                        <div class="col-12">
                            <div class="alert alert-info py-2 mb-0" id="sickTierContent">
                                <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">info</span>
                                <span id="sickTierText">Calculating pay tier breakdown...</span>
                            </div>
                        </div>
                    </div>

                    <!-- Leave Duration Type -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label">Leave Duration <span style="color:red">*</span></label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="leave_duration_type" id="full_day" value="full_day" checked>
                                <label class="btn btn-outline-primary" for="full_day">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">calendar_today</span>
                                    Full Day(s)
                                </label>

                                <input type="radio" class="btn-check" name="leave_duration_type" id="partial_day" value="partial_day">
                                <label class="btn btn-outline-primary" for="partial_day">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">schedule</span>
                                    Hours Only
                                </label>

                                <input type="radio" class="btn-check" name="leave_duration_type" id="days_and_hours" value="days_and_hours">
                                <label class="btn btn-outline-primary" for="days_and_hours">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">event_note</span>
                                    Days + Hours
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Full Days Fields -->
                    <div id="full-days-section">
                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <label for="start_date" class="form-label">Start Date <span style="color:red">*</span></label>
                                <input type="date" class="form-control" name="start_date" id="start_date" required>
                            </div>
                            <div class="col-lg-6 mb-3">
                                <label for="requested_days" class="form-label">Requested Days <span style="color:red">*</span></label>
                                <input type="number" class="form-control" name="requested_days" id="requested_days" min="0.5" step="0.5">
                                <small class="text-muted">A Saturday counts as half a day.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Partial Day (Hours Only) Fields -->
                    <div id="hours-only-section" style="display:none;">
                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <label for="leave_date_hours" class="form-label">Date <span style="color:red">*</span></label>
                                <input type="date" class="form-control" name="leave_date_hours" id="leave_date_hours">
                            </div>
                            <div class="col-lg-6 mb-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="time" class="form-control" name="start_time_only" id="start_time_only" placeholder="Start Time">
                                    <span>to</span>
                                    <input type="time" class="form-control" name="end_time_only" id="end_time_only" placeholder="End Time">
                                </div>
                            </div>
                            <div class="col-lg-12 mb-3">
                                <label for="hours_requested_only" class="form-label">Hours Requested</label>
                                <input type="number" class="form-control" name="hours_requested_only" id="hours_requested_only" min="0.5" step="0.5" readonly>
                                <small class="text-muted">Automatically calculated from start and end time</small>
                            </div>
                        </div>
                    </div>

                    <!-- Days + Hours Combined Fields -->
                    <div id="days-hours-section" style="display:none;">
                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <label for="start_date_combined" class="form-label">Start Date & Time <span style="color:red">*</span></label>
                                <div class="d-flex gap-2">
                                    <input type="date" class="form-control" name="start_date_combined" id="start_date_combined">
                                    <input type="time" class="form-control" name="start_time_combined" id="start_time_combined" value="08:00">
                                </div>
                            </div>
                            <div class="col-lg-6 mb-3">
                                <label for="end_date_combined" class="form-label">End Date & Time <span style="color:red">*</span></label>
                                <div class="d-flex gap-2">
                                    <input type="date" class="form-control" name="end_date_combined" id="end_date_combined">
                                    <input type="time" class="form-control" name="end_time_combined" id="end_time_combined" value="17:00">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12 mb-3">
                            <label for="available_days" class="form-label">Available Days</label>
                            <input type="number" class="form-control" name="available_days" id="available_days" min="0" readonly>
                        </div>
                    </div>

                    <h3 class="mb-3 mt-4">Leave Allowance</h3>
                    <hr class="mb-3 mt-3" />
                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <label for="allowance_type" class="form-label">Allowance Type</label>
                            <select class="form-select" name="allowance_type" id="allowance_type">
                                <option value="1" selected>Lumpsum</option>
                                <option value="2">Percentage</option>
                            </select>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="number" class="form-control" name="amount" id="amount" min="0" step="0.01">
                        </div>
                    </div>
                    @include('leave_management.partials.handover-fields', ['prefix' => 'hr'])
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        @permission('create_leave_applications')
                        <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center" id="saveLeaveApplicationBtn">
                            <span class="material-symbols-outlined">check_circle</span>
                            <span class="ms-1">Save</span>
                        </button>
                        @endpermission
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Event Details Modal -->
<div class="modal fade" id="eventDetailsModal" tabindex="-1" aria-labelledby="eventDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventDetailsModalLabel">Leave Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="eventDetailsBody">
                <!-- Content loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-light d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined">close</span>
                    <span class="ms-1">Cancel</span>
                </button>
                <a href="#" id="viewDetailsLink" class="btn btn-sm btn-primary d-flex align-items-center">
                    <span class="material-symbols-outlined">visibility</span> View Full Details
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    #leave-calendar { max-width: 100%; }
    #leave-calendar .fc { font-size: 0.9rem; }
    #leave-calendar .fc-toolbar-title { font-size: 1.25rem; font-weight: 600; }
    #leave-calendar .fc-button { padding: 0.4rem 0.8rem; font-size: 0.875rem; }
    #leave-calendar .fc-daygrid-day-number { padding: 4px; font-size: 0.85rem; }
    #leave-calendar .fc-col-header-cell { padding: 8px 4px; font-weight: 600; background-color: #f8f9fa; }
    #leave-calendar .fc-daygrid-day { min-height: 100px; }
    #leave-calendar .fc-event { font-size: 0.8rem; padding: 2px 4px; margin: 1px; border-radius: 3px; cursor: pointer; }
    #leave-calendar .fc-event-title { font-weight: 500; }
    #leave-calendar .fc-event:hover { opacity: 0.85; transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); transition: all 0.2s ease; }
    #leave-calendar .fc-event.status-approved { background-color: #28a745; border-color: #28a745; }
    #leave-calendar .fc-event.status-pending { background-color: #ffc107; border-color: #ffc107; color: #000; }
    #leave-calendar .fc-event.status-rejected { background-color: #dc3545; border-color: #dc3545; }
    #leave-calendar .fc-day-today { background-color: #fff3cd !important; }
    .nav-link { display: flex; align-items: center; gap: 0.25rem; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 20; }
    @media (max-width: 768px) {
        #leave-calendar .fc-toolbar { flex-direction: column; gap: 10px; }
        #leave-calendar .fc-toolbar-chunk { display: flex; justify-content: center; }
        #leave-calendar .fc-daygrid-day { min-height: 70px; }
        #leave-calendar .fc-event { font-size: 0.7rem; }
    }
</style>

<script src="{{ asset('assets/js/hr-leave-export.js') }}?v={{ filemtime(public_path('assets/js/hr-leave-export.js')) }}"></script>
<script>
$(document).ready(function() {
    $('#staff_id').on('change', function () {
        const staffId = String($(this).val() || '');
        const departmentId = String($(this).find('option:selected').attr('data-department-id') || '');
        $('#hr_handover_staff_id option').each(function () {
            this.disabled = Boolean(this.value && (!departmentId || this.dataset.departmentId !== departmentId || this.value === staffId));
        });
        if ($('#hr_handover_staff_id option:selected').prop('disabled')) {
            $('#hr_handover_staff_id').val('');
        }
        $('#hr_handover_staff_id').trigger('change');
    });

    // Toggle between leave duration types
    $('input[name="leave_duration_type"]').on('change', function() {
        const durationType = $(this).val();

        $('#full-days-section, #hours-only-section, #days-hours-section').hide();
        $('#start_date, #requested_days').prop('required', false);
        $('#leave_date_hours, #start_time_only, #end_time_only').prop('required', false);
        $('#start_date_combined, #start_time_combined, #end_date_combined, #end_time_combined').prop('required', false);

        if (durationType === 'full_day') {
            $('#full-days-section').show();
            $('#start_date, #requested_days').prop('required', true);
        } else if (durationType === 'partial_day') {
            $('#hours-only-section').show();
            $('#leave_date_hours, #start_time_only, #end_time_only').prop('required', true);
        } else if (durationType === 'days_and_hours') {
            $('#days-hours-section').show();
            $('#start_date_combined, #start_time_combined, #end_date_combined, #end_time_combined').prop('required', true);
        }
    });

    // Calculate hours for hours-only section
    $('#start_time_only, #end_time_only').on('change', function() {
        let startTime = $('#start_time_only').val();
        let endTime = $('#end_time_only').val();

        if (startTime && endTime) {
            let start = new Date('2000-01-01 ' + startTime);
            let end = new Date('2000-01-01 ' + endTime);

            if (end > start) {
                let diff = (end - start) / 1000 / 60 / 60;
                $('#hours_requested_only').val(diff.toFixed(2));
            } else {
                $('#hours_requested_only').val('');
                toastr.warning('End time must be after start time');
            }
        }
    });

    const initialFilters = new URLSearchParams(window.location.search);
    $('#leave-filter-status').val(initialFilters.get('status') || '');
    $('#leave-filter-department').val(initialFilters.get('department_id') || '');
    $('#leave-filter-from').val(initialFilters.get('date_from') || '');
    $('#leave-filter-to').val(initialFilters.get('date_to') || '');

    function persistLeaveFilters() {
        const params = new URLSearchParams(window.location.search);
        [['status', '#leave-filter-status'], ['department_id', '#leave-filter-department'], ['date_from', '#leave-filter-from'], ['date_to', '#leave-filter-to']].forEach(function(pair) {
            const value = $(pair[1]).val();
            if (value) params.set(pair[0], value); else params.delete(pair[0]);
        });
        const query = params.toString();
        window.history.replaceState({}, '', window.location.pathname + (query ? '?' + query : '') + window.location.hash);
    }

    // DataTable initialization
    let table = $('#leave-applications-table').DataTable({
        processing: true,
        serverSide: false,
        responsive: true,
        ajax: {
            url: "{{ url('/ajax/leave-management/leave-applications/list') }}",
            type: 'GET',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: 'json',
            data: function(params) {
                params.status = $('#leave-filter-status').val();
                params.department_id = $('#leave-filter-department').val();
                params.date_from = $('#leave-filter-from').val();
                params.date_to = $('#leave-filter-to').val();
            },
            dataSrc: function(json) {
                const period = json.staff_on_leave_period || {};
                $('#department-leave-period').text(period.from === period.to ? period.from : (period.from + ' to ' + period.to));
                const rows = json.staff_on_leave || [];
                const escapeHtml = value => $('<div>').text(value == null ? '' : value).html();
                const threshold = json.department_recall_threshold;
                if (threshold) {
                    const thresholdText = 'Today — Active Staff: ' + threshold.active_staff + ' of ' + threshold.staff_total + ' staff available (' + threshold.active_percentage + '% active). On approved leave: ' + threshold.on_leave + '. ';
                    const thresholdStatus = threshold.met
                        ? '<strong>Status: At or above 80% threshold — Staffing level is healthy.</strong>'
                        : '<strong>⚠ Understaffing Warning:</strong> Active staff at ' + threshold.active_percentage + '% (below 80% threshold). Approval or recall required!';
                    $('#department-leave-results').html('<div class="alert ' + (threshold.met ? 'alert-success' : 'alert-danger') + ' py-2 mb-2">' + escapeHtml(thresholdText) + thresholdStatus + '</div>');
                } else {
                    $('#department-leave-results').empty();
                }
                if (!rows.length) {
                    $('#department-leave-results').append('<span class="text-muted">No approved leave matches the selected department and date(s).</span>');
                } else {
                    const items = rows.map(function(staff) {
                        const staffNumber = staff.staff_number ? ' · #' + escapeHtml(staff.staff_number) : '';
                        const department = staff.department_name ? ' · ' + escapeHtml(staff.department_name) : '';
                        const position = staff.position_name ? ' · ' + escapeHtml(staff.position_name) : '';
                        return '<span class="badge bg-warning text-dark me-1 mb-1">' + escapeHtml(staff.staff_name) + staffNumber + department + position + '</span>';
                    }).join('');
                    $('#department-leave-results').append('<div class="mb-1"><strong>' + rows.length + '</strong> staff member(s) on approved leave</div><div>' + items + '</div>');
                }
                return json.leave_applications || [];
            }
        },
        columns: [
            {
                data: null,
                orderable: false,
                searchable: false,
                render: (data, type, row, meta) => meta.row + 1
            },
            {
                data: 'employee',
                title: 'Staff',
                render: data => data && data.name ? data.name : '-'
            },
            {
                data: 'employee',
                title: 'Department',
                render: data => data && data.department_name ? data.department_name : 'Unassigned'
            },
            {
                data: 'position',
                title: 'Designation',
                render: data => data && data.position_name ? data.position_name : '-'
            },
            {
                data: 'leave_type',
                title: 'Leave Type',
                render: data => data && data.type_name ? data.type_name : '-'
            },
            {
                data: 'start_date',
                title: 'Start Date',
                render: data => data ? data : '-'
            },
            {
                data: 'end_date',
                title: 'End Date',
                render: data => data ? data : '-'
            },
            
               {
    data: 'current_status_id',
    title: 'Current Status',
    render: function(data, type, row) {
        data = parseInt(data);

        if (data === 5 && row.recalled_at) {
            if (type !== 'display') return 'Approved · Recalled';
            return '<span class="badge bg-dark">Approved · Recalled</span>';
        }

        const color = [3, 5].includes(data) ? 'success' : data === 41 ? 'danger' : row.waiting_on === 'hr' ? 'info' : 'warning';
        const label = row.stage_label || 'Pending approval';
        if (type !== 'display') return label;
        return '<span class="badge bg-' + color + ' text-dark">' + $.fn.dataTable.render.text().display(label) + '</span>';
    }
},
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(id, type, row) {
                    let buttons = '<div class="d-flex flex-row gap-1 align-items-center">';

                    if (row.can_view) {
                        buttons += `
                            <a href="{{ url('/hr/leave-management/leave-applications/') }}/${row.encrypted_leave_application_id}" class="btn btn-xs btn-light" style="padding:1px 2px;" title="View Application">
                                <span class="material-symbols-outlined" style="font-size:18px;">visibility</span>
                            </a>`;
                    }

                    if (row.can_update) {
                        buttons += `
                            <button class="btn btn-xs btn-light edit-leave-application" 
                                data-id="${row.id}" 
                                data-staff_id="${row.employee && row.employee.id ? row.employee.id : ''}" 
                                data-position_id="${row.position && row.position.id ? row.position.id : ''}" 
                                data-leave_type_id="${row.leave_type && row.leave_type.id ? row.leave_type.id : ''}" 
                                data-start_date="${row.start_date ? row.start_date : ''}" 
                                data-end_date="${row.end_date ? row.end_date : ''}" 
                                data-requested_days="${row.requested_days !== undefined ? row.requested_days : ''}" 
                                data-entitled_days="${row.entitled_days !== undefined ? row.entitled_days : ''}" 
                                data-days_balance="${row.days_balance !== undefined ? row.days_balance : ''}" 
                                data-used_days="${row.used_days !== undefined ? row.used_days : ''}" 
                                data-amount="${row.amount !== undefined ? row.amount : ''}" 
                                data-allowance_type_id="${row.allowance_type_id !== undefined ? row.allowance_type_id : ''}"
                                data-is_partial_day="${row.is_partial_day ? '1' : '0'}"
                                data-hours_requested="${row.hours_requested || ''}"
                                style="padding:1px 2px;"
                                title="Edit Application">
                                <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                            </button>`;
                    }

                    if (row.can_delete) {
                        buttons += `
                            <button class="btn btn-xs btn-light delete-leave-application" 
                                data-id="${row.id}" 
                                style="padding:1px 2px;"
                                title="Delete Application">
                                <span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>
                                <span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>
                            </button>`;
                    }

                    buttons += '</div>';
                    return buttons;
                }
            }
        ],
        paging: true,
        info: true,
        lengthChange: true
    });

    $('#apply-leave-filters').on('click', function() { persistLeaveFilters(); table.ajax.reload(); });
    $('#clear-leave-filters').on('click', function() {
        $('#leave-filter-status, #leave-filter-department, #leave-filter-from, #leave-filter-to').val('');
        persistLeaveFilters();
        table.ajax.reload();
    });

    window.LeaveCsvExport.bind(table, 'applications', 'export-leave-applications', 'leave-applications-export-status');

    // Initialize FullCalendar
    let calendar;
    let calendarEl = document.getElementById('leave-calendar');

    function initCalendar() {
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listWeek'
            },
            events: function(info, successCallback, failureCallback) {
                $.ajax({
                    url: "{{ url('/ajax/leave-management/leave-applications/list') }}",
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        let events = [];
                        if (response.leave_applications && Array.isArray(response.leave_applications)) {
                            response.leave_applications.forEach(function(app) {
                                let color = '#3788d8';
                                if (app.current_status_id == 5) color = '#28a745';
                                else if (app.current_status_id == 41) color = '#dc3545';
                                else if (app.current_status_id == 1) color = '#ffc107';
                                else if (app.current_status_id == 24) color = '#17a2b8';

                                events.push({
                                    id: app.id,
                                    title: (app.employee && app.employee.name ? app.employee.name : 'Unknown') +
                                        ' - ' + (app.leave_type && app.leave_type.type_name ? app.leave_type.type_name : ''),
                                    start: app.start_date,
                                    end: app.end_date ? new Date(new Date(app.end_date).setDate(new Date(app.end_date).getDate() + 1)) : app.start_date,
                                    backgroundColor: color,
                                    borderColor: color,
                                    extendedProps: {
                                        staff_name: app.employee && app.employee.name ? app.employee.name : '-',
                                        leave_type: app.leave_type && app.leave_type.type_name ? app.leave_type.type_name : '-',
                                        position: app.position && app.position.position_name ? app.position.position_name : '-',
                                        requested_days: app.requested_days,
                                        status: app.stage_label || 'Pending approval',
                                        encrypted_id: app.encrypted_leave_application_id
                                    }
                                });
                            });
                        }
                        successCallback(events);
                    },
                    error: function() {
                        failureCallback();
                        toastr.error('Failed to load calendar events');
                    }
                });
            },
            eventClick: function(info) {
                let event = info.event;
                const status = event.extendedProps.status;
                const statusColor = status === 'Approved' ? 'success' : status === 'Rejected' ? 'danger' : 'warning';
                const statusBadge = '<span class="badge bg-' + statusColor + ' text-dark">' + $.fn.dataTable.render.text().display(status) + '</span>';

                let content = `
                    <div class="row g-2">
                        <div class="col-12">
                            <small class="text-muted">Staff:</small><br>
                            <strong>${event.extendedProps.staff_name}</strong>
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Leave Type:</small><br>
                            <strong>${event.extendedProps.leave_type}</strong>
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Position:</small><br>
                            <strong>${event.extendedProps.position}</strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Days:</small><br>
                            <strong>${event.extendedProps.requested_days}</strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Status:</small><br>
                            <strong>${statusBadge}</strong>
                        </div>
                    </div>
                `;

                $('#eventDetailsBody').html(content);

                if (event.extendedProps.encrypted_id) {
                    $('#viewDetailsLink').attr('href', "{{ url('/hr/leave-management/leave-applications/') }}/" + event.extendedProps.encrypted_id);
                    $('#viewDetailsLink').show();
                } else {
                    $('#viewDetailsLink').hide();
                }

                $('#eventDetailsModal').modal('show');
            }
        });
        calendar.render();
    }

    // Initialize calendar when tab is shown
    $('button[data-bs-target="#calendar"]').on('shown.bs.tab', function(e) {
        if (!calendar) {
            initCalendar();
        } else {
            calendar.render();
            calendar.refetchEvents();
        }
    });

    // Refresh calendar when table reloads
    let originalReload = table.ajax.reload;
    table.ajax.reload = function(callback, resetPaging) {
        originalReload.call(this, function() {
            if (calendar) calendar.refetchEvents();
            if (callback) callback();
        }, resetPaging);
    };

    $('#addLeaveApplicationModal').on('hidden.bs.modal', function () {
        $('#leaveApplicationForm')[0].reset();
        $('#leaveApplicationId').val('');
        $('#hr_handover_staff_id option[value!=""]').prop('disabled', true);
    });

    // Populate selects on modal show
    $('#addLeaveApplicationModal').on('show.bs.modal', function() {
        if ($('#leaveApplicationId').val()) return; // The edit handler loads the saved selections.
        $.ajax({
            url: "{{ url('/ajax/hr/staff/list') }}",
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                let $staff_id = $('#staff_id');
                $staff_id.empty();
                $staff_id.append($('<option>', {
                    value: '',
                    text: 'Select staff',
                    disabled: true,
                    selected: true
                }));
                if (response.staff && Array.isArray(response.staff)) {
                    response.staff.forEach(function(staff) {
                        $staff_id.append($('<option>', {
                            value: staff.id,
                            text: staff.full_names,
                            'data-department-id': staff.department_id
                        }));
                    });
                }

                if ($staff_id.hasClass("select2-hidden-accessible")) {
                    $staff_id.select2('destroy');
                }

                $staff_id.select2({
                    dropdownParent: $('#addLeaveApplicationModal'),
                    placeholder: 'Select Staff',
                    allowClear: true
                });
            },
            error: function() {
                toastr.error('Failed to load staff.');
            }
        });

        $.ajax({
            url: "{{ url('/ajax/hr/leave_types/list') }}",
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                let $leave_type_id = $('#leave_type_id');
                $leave_type_id.empty();
                $leave_type_id.append($('<option>', {
                    value: '',
                    text: 'Select leave type',
                    disabled: true,
                    selected: true
                }));
                if (response.leave_types && Array.isArray(response.leave_types)) {
                    response.leave_types.forEach(function(type) {
                        $leave_type_id.append($('<option>', {
                            value: type.id,
                            text: type.type_name
                        }));
                    });
                }

                if ($leave_type_id.hasClass("select2-hidden-accessible")) {
                    $leave_type_id.select2('destroy');
                }

                $leave_type_id.select2({
                    dropdownParent: $('#addLeaveApplicationModal'),
                    placeholder: 'Select leave type',
                    allowClear: true
                });
            },
            error: function() {
                toastr.error('Failed to load leave types.');
            }
        });
    });

    function fetchAvailableDays() {
        let staffId = $('#staff_id').val();
        let leaveTypeId = $('#leave_type_id').val();
        if (staffId && leaveTypeId) {
            $.ajax({
                url: `/ajax/hr/leave/available-days/${staffId}/${leaveTypeId}`,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success === false && response.error) {
                        $('#available_days').val('');
                        toastr.warning(response.error);
                    } else if (response.available_days !== undefined && response.available_days !== null) {
                        $('#available_days').val(response.available_days);

                        if (response.start_date && response.end_date) {
                            let startDateOnly = response.start_date.split(' ')[0];
                            let endDateOnly = response.end_date.split(' ')[0];

                            $('#start_date, #leave_date_hours, #start_date_combined, #end_date_combined').attr('min', startDateOnly).attr('max', endDateOnly);
                        }
                    } else {
                        $('#available_days').val('');
                        toastr.warning('Annual leave calendar must be created and approved for this employee');
                        $('#start_date, #leave_date_hours, #start_date_combined, #end_date_combined').removeAttr('min').removeAttr('max');
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'Failed to fetch available leave days';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMsg = xhr.responseJSON.error;
                    }
                    toastr.error(errorMsg);
                    $('#available_days').val('');
                    $('#staff_id').val('').trigger('change');
                    $('#leave_type_id').val('').trigger('change');
                    $('#start_date, #leave_date_hours, #start_date_combined, #end_date_combined').removeAttr('min').removeAttr('max');
                }
            });
        } else {
            $('#available_days').val('');
            $('#start_date, #leave_date_hours, #start_date_combined, #end_date_combined').removeAttr('min').removeAttr('max');
        }
    }

    $('#staff_id, #leave_type_id').on('change', fetchAvailableDays);

    function handleLeaveTypeSpecifics() {
        let staffId = $('#staff_id').val();
        let leaveTypeId = $('#leave_type_id').val();
        let selectedText = $('#leave_type_id option:selected').text().toLowerCase();

        let isAnnual = selectedText.includes('annual') || selectedText.includes('normal');

        $('#annualSlotPickerContainer').addClass('d-none');
        $('#annualReservedDateNotice').addClass('d-none');
        $('#sickLeaveTierAlert').addClass('d-none');

        if (!staffId || !leaveTypeId) return;
        window.annualReservedLeaveSlots = [];

        if (isAnnual) {
            $('#annualSlotPickerContainer').removeClass('d-none');
            $('#scheduled_slot_select').html('<option value="">Loading your scheduled blocks...</option>');

            $.ajax({
                url: "{{ url('/ajax/leave-management/staff-annual-slots') }}",
                type: 'GET',
                data: { staff_id: staffId, year: new Date().getFullYear() },
                success: function(res) {
                    if (res.success && res.slots && res.slots.length > 0) {
                        let options = '<option value="">-- Choose from your scheduled plan --</option>';
                        res.slots.forEach(slot => {
                            let disabledAttr = slot.is_disabled ? 'disabled style="color: #888; background-color: #f1f1f1;"' : '';
                            options += `<option value="${slot.id}" data-start="${slot.start_date}" data-end="${slot.end_date}" data-days="${slot.days}" ${disabledAttr}>${slot.display}</option>`;
                        });
                        $('#scheduled_slot_select').html(options);
                    } else {
                        $('#scheduled_slot_select').html('<option value="">No scheduled blocks found for this year</option>');
                        toastr.warning(res.message || 'You must first schedule your 21 days in the Leave Calendar Plan.');
                    }
                }
            });
        } else {
            $.get("{{ url('/ajax/leave-management/staff-annual-slots') }}", {
                staff_id: staffId,
                year: new Date().getFullYear()
            }).done(function(res) {
                let slots = (res.slots || []).filter(slot => slot.start_date && slot.end_date);
                if (!slots.length) return;
                window.annualReservedLeaveSlots = slots;
                let dates = slots.map(slot => `${slot.display}`).join('; ');
                $('#annualReservedDateText').text(`Dates reserved for annual leave cannot be used for another leave type: ${dates}`);
                $('#annualReservedDateNotice').removeClass('d-none');
            });
        }

        showTierBreakdown();
    }

    let tierRequest = null;
    function showTierBreakdown() {
        let staffId = $('#staff_id').val();
        let leaveTypeId = $('#leave_type_id').val();

        $('#sickLeaveTierAlert').addClass('d-none');
        if (!staffId || !leaveTypeId || $('input[name="leave_duration_type"]:checked').val() !== 'full_day') return;

        if (tierRequest) tierRequest.abort();
        tierRequest = $.ajax({
            url: "{{ url('/ajax/leave-management/evaluate-application') }}",
            type: 'POST',
            data: {
                staff_id: staffId,
                leave_type_id: leaveTypeId,
                start_date: $('#start_date').val() || new Date().toISOString().split('T')[0],
                requested_days: parseFloat($('#requested_days').val()) || 1,
                _token: '{{ csrf_token() }}'
            },
            success: function(res) {
                if (!res.success) return;
                let requested = parseFloat($('#requested_days').val()) || 1;
                let changed = Math.abs(res.charged_days - requested) > 0.001;
                if (res.sick_breakdown || changed) {
                    let b = res.sick_breakdown;
                    let tiers = b ? `Pay Breakdown: ${b.summary_text} (days already used this year: ${b.used_this_year}). ` : '';
                    $('#sickTierText').text(`${tiers}Leave runs to ${res.end_date}, ${res.charged_days} day(s) charged${changed ? ' (a Saturday counts as half a day)' : ''}.`);
                    $('#sickLeaveTierAlert').removeClass('d-none');
                }
            },
            error: function(xhr, status) {
                if (status === 'abort') return;
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Could not load the pay breakdown');
            }
        });
    }

    $('#staff_id, #leave_type_id').on('change', handleLeaveTypeSpecifics);
    window.annualReservedLeaveSlots = [];
    $(document).on('change', '#start_date, #leave_date_hours, #start_date_combined, #end_date_combined', function() {
        let selectedDate = $(this).val();
        let selectedType = $('#leave_type_id option:selected').text().toLowerCase();
        if (!selectedDate || selectedType.includes('annual') || selectedType.includes('normal')) return;
        let reserved = (window.annualReservedLeaveSlots || []).find(slot => selectedDate >= slot.start_date && selectedDate <= slot.end_date);
        if (reserved) {
            toastr.warning('That date is reserved for annual leave. Choose a different date.');
            $(this).val('');
        }
    });
    $('#start_date, #requested_days, input[name="leave_duration_type"]').on('change input', showTierBreakdown);

    $('#scheduled_slot_select').on('change', function() {
        let opt = $(this).find('option:selected');
        let start = opt.data('start');
        let end = opt.data('end');
        let days = opt.data('days');

        if (start && end) {
            $('input[name="leave_duration_type"][value="full_day"]').prop('checked', true).trigger('change');
            $('#start_date').val(start);
            $('#requested_days').val(days);
            toastr.info(`Selected block: ${days} working day(s)`);
        }
    });

    $('#leaveApplicationForm').on('submit', function(e) {
        e.preventDefault();
        const handoverSearch = document.getElementById('hr_handover_employee_search');
        if (handoverSearch && !handoverSearch.reportValidity()) return;

        let formData = new FormData();
        formData.append('handover_staff_id', $('#hr_handover_staff_id').val() || '');
        formData.append('handover_notes', $('#hr_handover_notes').val() || '');
        for (const file of document.getElementById('hr_handover_documents').files) formData.append('handover_documents[]', file);
        let durationType = $('input[name="leave_duration_type"]:checked').val();
        let id = $('#leaveApplicationId').val();

        formData.append('staff_id', $('#staff_id').val());
        formData.append('leave_type_id', $('#leave_type_id').val());
        formData.append('available_days', $('#available_days').val());
        formData.append('allowance_type', $('#allowance_type').val());
        formData.append('amount', $('#amount').val() || 0);
        formData.append('leave_duration_type', durationType);
        formData.append('_token', '{{ csrf_token() }}');

        if (id) {
            formData.append('id', id);
        }

        if (durationType === 'full_day') {
            formData.append('start_date', $('#start_date').val());
            formData.append('requested_days', $('#requested_days').val());
            formData.append('is_partial_day', '0');
        } else if (durationType === 'partial_day') {
            let leaveDate = $('#leave_date_hours').val();
            let startTime = $('#start_time_only').val();
            let endTime = $('#end_time_only').val();

            formData.append('start_date', leaveDate + ' ' + startTime);
            formData.append('end_date', leaveDate + ' ' + endTime);
            formData.append('start_time', startTime);
            formData.append('end_time', endTime);
            formData.append('hours_requested', $('#hours_requested_only').val());
            formData.append('requested_days', '0');
            formData.append('is_partial_day', '1');
        } else if (durationType === 'days_and_hours') {
            let startDate = $('#start_date_combined').val();
            let startTime = $('#start_time_combined').val();
            let endDate = $('#end_date_combined').val();
            let endTime = $('#end_time_combined').val();

            formData.append('start_date', startDate + ' ' + startTime);
            formData.append('end_date', endDate + ' ' + endTime);
            formData.append('start_time', startTime);
            formData.append('end_time', endTime);

            let start = new Date(startDate + ' ' + startTime);
            let end = new Date(endDate + ' ' + endTime);
            let totalHours = (end - start) / 1000 / 60 / 60;

            formData.append('hours_requested', totalHours.toFixed(2));
            formData.append('requested_days', Math.floor(totalHours / 8));
            formData.append('is_partial_day', '1');
        }

        let url = id ? "{{ url('/ajax/leave-management/leave-applications') }}/" + id : "{{ url('/ajax/leave-management/leave-applications') }}";
        let $btn = $('#saveLeaveApplicationBtn');
        let $icon = $btn.find('.material-symbols-outlined');
        let origText = $btn.find('span.ms-1').text();

        $icon.addClass('spin');
        $btn.find('span.ms-1').text('Processing...');
        $btn.prop('disabled', true);

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);

                if (res.success) {
                    toastr.success('Saved successfully');
                    $('#addLeaveApplicationModal').modal('hide');
                    $('#leaveApplicationForm')[0].reset();
                    $('#leaveApplicationId').val('');
                    $('input[name="leave_duration_type"][value="full_day"]').prop('checked', true).trigger('change');
                    table.ajax.reload(null, false);
                } else {
                    toastr.error(res.error || 'Error saving');
                }
            },
            error: function(xhr) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);

                let msg = 'Validation error';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).join('<br>');
                } else if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                }
                toastr.error(msg);
            }
        });
    });

    $(document).on('click', '.edit-leave-application', function() {
        let $btn = $(this);

        $('#leaveApplicationId').val($btn.data('id'));
        const leaveRow = table.row($btn.closest('tr')).data();
        $('#hr_handover_existing_files').text((leaveRow.handover_files || []).length
            ? 'Existing files retained: ' + leaveRow.handover_files.map(file => file.name).join(', ') : '');
        $('#hr_handover_notes').val(leaveRow.handover_notes || '');
        $('#amount').val($btn.data('amount'));
        $('#allowance_type').val($btn.data('allowance_type_id')).trigger('change');

        let startDate = $btn.data('start_date');
        let endDate = $btn.data('end_date');
        let hoursRequested = $btn.data('hours_requested');

        let startDateTime = startDate ? new Date(startDate) : null;
        let endDateTime = endDate ? new Date(endDate) : null;

        let durationType = 'full_day';

        if (startDateTime && endDateTime) {
            let startDateOnly = startDateTime.toISOString().split('T')[0];
            let endDateOnly = endDateTime.toISOString().split('T')[0];
            let startTimeOnly = startDateTime.toTimeString().slice(0, 5);
            let endTimeOnly = endDateTime.toTimeString().slice(0, 5);

            if (startDateOnly === endDateOnly && !(startTimeOnly === '08:00' && endTimeOnly === '17:00')) {
                durationType = 'partial_day';
                $('#leave_date_hours').val(startDateOnly);
                $('#start_time_only').val(startTimeOnly);
                $('#end_time_only').val(endTimeOnly);
                $('#hours_requested_only').val(hoursRequested || '');
            } else if (startDateOnly !== endDateOnly && !(startTimeOnly === '08:00' && endTimeOnly === '17:00')) {
                durationType = 'days_and_hours';
                $('#start_date_combined').val(startDateOnly);
                $('#start_time_combined').val(startTimeOnly);
                $('#end_date_combined').val(endDateOnly);
                $('#end_time_combined').val(endTimeOnly);
            } else {
                durationType = 'full_day';
                $('#start_date').val(startDateOnly);
                $('#requested_days').val($btn.data('requested_days'));
            }
        }

        $(`input[name="leave_duration_type"][value="${durationType}"]`).prop('checked', true).trigger('change');
        $('#addLeaveApplicationModal').modal('show');

        $.ajax({
            url: "{{ url('/ajax/hr/staff/list') }}",
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                let $staff_id = $('#staff_id');
                $staff_id.empty();
                $staff_id.append($('<option>', {
                    value: '',
                    text: 'Select staff',
                    disabled: true
                }));
                if (response.staff && Array.isArray(response.staff)) {
                    response.staff.forEach(function(staff) {
                        $staff_id.append($('<option>', {
                            value: staff.id,
                            text: staff.full_names,
                            'data-department-id': staff.department_id
                        }));
                    });
                }

                if ($staff_id.hasClass("select2-hidden-accessible")) {
                    $staff_id.select2('destroy');
                }

                $staff_id.select2({
                    dropdownParent: $('#addLeaveApplicationModal'),
                    placeholder: 'Select Staff',
                    allowClear: true
                });

                let selectedStaffId = $btn.data('staff_id');
                $staff_id.val(selectedStaffId).trigger('change');
                $('#hr_handover_staff_id').val(leaveRow.handover_staff_id || '').trigger('change');
            },
            error: function() {
                toastr.error('Failed to load staff.');
            }
        });

        $.ajax({
            url: "{{ url('/ajax/hr/leave_types/list') }}",
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                let $leave_type_id = $('#leave_type_id');
                $leave_type_id.empty();
                $leave_type_id.append($('<option>', {
                    value: '',
                    text: 'Select leave type',
                    disabled: true
                }));
                if (response.leave_types && Array.isArray(response.leave_types)) {
                    response.leave_types.forEach(function(type) {
                        $leave_type_id.append($('<option>', {
                            value: type.id,
                            text: type.type_name
                        }));
                    });
                }

                if ($leave_type_id.hasClass("select2-hidden-accessible")) {
                    $leave_type_id.select2('destroy');
                }

                $leave_type_id.select2({
                    dropdownParent: $('#addLeaveApplicationModal'),
                    placeholder: 'Select leave type',
                    allowClear: true
                });

                let selectedLeaveTypeId = $btn.data('leave_type_id');
                $leave_type_id.val(selectedLeaveTypeId).trigger('change');

                fetchAvailableDays();
            },
            error: function() {
                toastr.error('Failed to load leave types.');
            }
        });
    });

    $(document).on('click', '.delete-leave-application', function() {
        var $btn = $(this);
        var $icon = $btn.find('.material-symbols-outlined').first();

        if (!confirm('Are you sure you want to delete this leave application?')) {
            return;
        }

        $icon.show();
        $btn.find('.material-symbols-outlined').last().hide();
        $btn.prop('disabled', true);

        $.ajax({
            url: "{{ url('/ajax/leave-management/leave-applications') }}/" + $btn.data('id'),
            type: 'DELETE',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(res) {
                $icon.hide();
                $btn.find('.material-symbols-outlined').last().show();
                $btn.prop('disabled', false);

                if (res.success) {
                    toastr.success('Deleted');
                    table.ajax.reload(null, false);
                } else {
                    toastr.error(res.error || 'Error deleting');
                }
            },
            error: function(xhr) {
                $icon.hide();
                $btn.find('.material-symbols-outlined').last().show();
                $btn.prop('disabled', false);

                let msg = 'Delete error';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                }
                toastr.error(msg);
            }
        });
    });

    $('#addLeaveApplicationModal').on('hidden.bs.modal', function() {
        $('#leaveApplicationForm')[0].reset();
        $('#leaveApplicationId').val('');
        $('input[name="leave_duration_type"][value="full_day"]').prop('checked', true).trigger('change');
    });
});
</script>
@endsection
