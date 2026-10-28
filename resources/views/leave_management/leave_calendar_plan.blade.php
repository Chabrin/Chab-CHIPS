@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Annual Leave Calendar Planning (Mandatory 21 Days)</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('read_leave_calendar_plans')
                                <button type="button" id="export-leave-plans" class="btn btn-outline-success btn-sm d-flex align-items-center" disabled title="Export the current plan list search across all pages">
                                    <span class="material-symbols-outlined" style="font-size:18px;">download</span>
                                    <span class="ms-1">Export CSV</span>
                                </button>
                            @endpermission
                            @permission('create_leave_calendar_plans')
                                <button class="btn btn-success btn-sm d-flex align-items-center" id="generateAnnualPlansBtn">
                                    <span class="material-symbols-outlined">autorenew</span>
                                    <span class="ms-1">Initialize Year Plans</span>
                                </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        @permission('read_leave_calendar_plans')
                        <div id="leave-plans-export-status" class="small text-muted mb-2" role="status" aria-live="polite"></div>
                        @endpermission
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="leave-calendar-plan-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Entitled</th>
                                        <th>Balance</th>
                                        <th>Scheduled Slots</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Loaded via DataTables AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Slots Modal (With Pro-rata and Dynamic Target Support) -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Annual Leave (<span id="modalEmployeeName"></span>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Counter Tracker -->
                <div class="d-flex justify-content-between align-items-center p-3 mb-3 bg-light rounded border text-dark">
                    <div><strong class="text-dark">Target:</strong> <span id="modalTargetDaysBadge" class="fw-bold text-dark">21 Days</span></div>
                    <div><strong class="text-dark">Allocated:</strong> <span id="allocatedDaysBadge" class="badge bg-secondary">0 Days</span></div>
                    <div><strong class="text-dark">Remaining:</strong> <span id="remainingDaysBadge" class="badge bg-warning text-dark">21 Days</span></div>
                </div>

                <form id="scheduleSlotsForm">
                    <input type="hidden" id="modalPlanId" name="plan_id">
                    <input type="hidden" id="modalLeaveTypeId" name="leave_type_id">

                    <div id="slotsContainer">
                        <!-- Dynamic Slot Rows inserted here -->
                    </div>

                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="addSlotRowBtn">
                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">add</span>
                        Add Leave Block
                    </button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveScheduleBtn" disabled>
                    Save & Submit Schedule
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Generate Annual Plans Modal (With Integrated Holiday Setup) -->
<div class="modal fade" id="generateAnnualPlansModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Initialize Annual Leave Quotas & Holidays</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3">
                    Initializes statutory annual quotas for active staff (including pro-rata calculation for mid-year hires). Confirm or adjust public holidays for this year below so calculations exclude them accurately.
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="plan_year" class="form-label fw-bold">Target Year <span class="text-danger">*</span></label>
                        <select class="form-select" id="plan_year" required>
                            <option value="{{ date('Y') }}" selected>{{ date('Y') }} (Current Year)</option>
                            <option value="{{ date('Y') + 1 }}">{{ date('Y') + 1 }} (Next Year)</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="override_existing" value="1">
                            <label class="form-check-label" for="override_existing">
                                Override existing plans for this year
                            </label>
                            <div class="form-text">Leave unticked to create only the plans that are missing. Ticking it resets everyone's plan for the year.</div>
                        </div>
                    </div>
                </div>

                <hr class="my-3">

                <!-- Configured Holidays Section -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0">
                        Public Holidays for <span id="initHolidaysYearLabel">{{ date('Y') }}</span>
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddNewHolidayRow">
                        <span class="material-symbols-outlined" style="font-size:15px; vertical-align:middle;">add</span> Add Holiday
                    </button>
                </div>

                <div class="table-responsive border rounded" style="max-height: 220px; overflow-y: auto;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Holiday Name</th>
                                <th style="width: 180px;">Holiday Date</th>
                                <th style="width: 50px;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="initHolidaysList">
                            <!-- Populated dynamically via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmGeneratePlans">Initialize Quotas & Apply Holidays</button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/js/hr-leave-export.js') }}?v={{ filemtime(public_path('assets/js/hr-leave-export.js')) }}"></script>
<script>
$(document).ready(function() {
    let currentTotalDays = 0;
    let requiredPlanDays = 21;

    let table = $('#leave-calendar-plan-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ url('/ajax/leave-management/leave-calendar-plan/list') }}",
            type: 'GET',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            dataSrc: function(json) {
                let flat = [];
                (json.leave_calendar_plans || []).forEach(emp => {
                    (emp.leave_types || []).forEach(lt => {
                        flat.push({
                            employee_name: emp.employee ? (emp.employee.full_names || emp.employee.name) : '-',
                            plan_id: lt.id,
                            leave_type_id: lt.leave_type_id,
                            leave_type_name: lt.leave_type ? lt.leave_type.type_name : 'Annual Leave',
                            start_date: lt.start_date,
                            end_date: lt.end_date,
                            entitled_days: lt.entitled_days,
                            balance: lt.balance,
                            status: lt.current_status_id
                        });
                    });
                });
                return flat;
            }
        },
        columns: [
            { data: 'employee_name' },
            { data: 'leave_type_name' },
            { data: 'entitled_days', className: 'text-center' },
            { 
                data: 'balance', 
                className: 'text-center',
                render: data => `<span class="badge bg-${data > 0 ? 'info' : 'secondary'}">${data}</span>`
            },
            {
                data: null,
                render: () => '<span class="text-muted small">Managed in blocks</span>'
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    return `
                        <button class="btn btn-sm btn-outline-primary open-schedule-modal" 
                            data-plan-id="${row.plan_id}" 
                            data-leave-type-id="${row.leave_type_id}"
                            data-entitled-days="${row.entitled_days}"
                            data-employee-name="${row.employee_name}">
                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">calendar_month</span>
                            Schedule ${Math.floor(row.entitled_days * 2) / 2} Days
                        </button>
                    `;
                }
            }
        ]
    });

    // -------------------------------------------------------------
    window.LeaveCsvExport.bind(table, 'plans', 'export-leave-plans', 'leave-plans-export-status');

    // Staff Scheduling / Rescheduling Modal
    // -------------------------------------------------------------
    $(document).on('click', '.open-schedule-modal', function() {
        let planId = $(this).data('plan-id');
        let leaveTypeId = $(this).data('leave-type-id');
        let empName = $(this).data('employee-name');

        // Saturdays count half, so schedules are made in half days (5.25 entitled = schedule 5)
        requiredPlanDays = Math.floor(parseFloat($(this).data('entitled-days')) * 2) / 2;
        if (isNaN(requiredPlanDays)) { requiredPlanDays = 21; }

        $('#modalPlanId').val(planId);
        $('#modalLeaveTypeId').val(leaveTypeId);
        $('#modalEmployeeName').text(empName);
        $('#modalTargetDaysBadge').text(requiredPlanDays + ' Days');
        $('#saveScheduleBtn').text(`Save & Submit Schedule (${requiredPlanDays} Days Required)`);
        $('#slotsContainer').empty();

        // Load existing slots if any, or append first empty slot
        $.ajax({
            url: "{{ url('/ajax/leave-management/plan-slots') }}/" + planId,
            type: 'GET',
            success: function(res) {
                if (res.success && res.slots && res.slots.length > 0) {
                    res.slots.forEach(slot => {
                        addSlotRow(slot.start_date, slot.end_date, slot.days_count);
                    });
                } else {
                    addSlotRow();
                }
                updateRunningTotal();
            },
            error: function() {
                addSlotRow();
                updateRunningTotal();
            }
        });

        $('#scheduleModal').modal('show');
    });

    function addSlotRow(start = '', end = '', days = 0) {
        let index = $('#slotsContainer .slot-row').length;
        let html = `
            <div class="slot-row border rounded p-2 mb-2 bg-white position-relative">
                <div class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Start Date</label>
                        <input type="date" class="form-control form-control-sm slot-start" name="slots[${index}][start_date]" value="${start}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">End Date</label>
                        <input type="date" class="form-control form-control-sm slot-end" name="slots[${index}][end_date]" value="${end}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Working Days</label>
                        <input type="text" class="form-control form-control-sm slot-days bg-light" readonly value="${days}">
                    </div>
                    <div class="col-md-1 text-end pt-3">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-slot-btn" style="padding: 2px 6px;">
                            &times;
                        </button>
                    </div>
                </div>
            </div>
        `;
        $('#slotsContainer').append(html);
    }

    $('#addSlotRowBtn').on('click', function() {
        addSlotRow();
    });

    $(document).on('click', '.remove-slot-btn', function() {
        if ($('#slotsContainer .slot-row').length > 1) {
            $(this).closest('.slot-row').remove();
            updateRunningTotal();
        } else {
            toastr.warning('At least one leave block is required.');
        }
    });

    $(document).on('change', '.slot-start, .slot-end', function() {
        let row = $(this).closest('.slot-row');
        let start = row.find('.slot-start').val();
        let end = row.find('.slot-end').val();
        let leaveTypeId = $('#modalLeaveTypeId').val();

        if (start && end) {
            if (end < start) {
                toastr.error('End date cannot be earlier than start date');
                row.find('.slot-days').val(0);
                updateRunningTotal();
                return;
            }

            $.ajax({
                url: "{{ url('/ajax/leave-management/calculate-slot-days') }}",
                type: 'POST',
                data: {
                    start_date: start,
                    end_date: end,
                    leave_type_id: leaveTypeId,
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    row.find('.slot-days').val(res.days);
                    updateRunningTotal();
                }
            });
        }
    });

    function updateRunningTotal() {
        let total = 0;
        $('.slot-days').each(function() {
            total += parseFloat($(this).val()) || 0;
        });

        currentTotalDays = total;
        let remaining = requiredPlanDays - total;

        $('#allocatedDaysBadge').text(total + ' Days');
        
        if (Math.abs(remaining) < 0.001) {
            $('#remainingDaysBadge').removeClass('bg-warning text-dark bg-danger').addClass('bg-success text-white').text('0 Days (Requirement Met!)');
            $('#saveScheduleBtn').prop('disabled', false).removeClass('btn-secondary').addClass('btn-primary');
        } else if (remaining < 0) {
            $('#remainingDaysBadge').removeClass('bg-warning bg-success').addClass('bg-danger text-white').text(Math.abs(remaining) + ' Days Exceeded');
            $('#saveScheduleBtn').prop('disabled', true);
        } else {
            $('#remainingDaysBadge').removeClass('bg-success bg-danger text-white').addClass('bg-warning text-dark').text(remaining + ' Days Remaining');
            $('#saveScheduleBtn').prop('disabled', true);
        }
    }

    $('#saveScheduleBtn').on('click', function() {
        if (currentTotalDays !== requiredPlanDays) {
            toastr.error(`You must allocate exactly ${requiredPlanDays} working days.`);
            return;
        }

        let formData = $('#scheduleSlotsForm').serialize();

        $.ajax({
            url: "{{ url('/ajax/leave-management/save-annual-schedule') }}",
            type: 'POST',
            data: formData + '&_token={{ csrf_token() }}',
            success: function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#scheduleModal').modal('hide');
                    table.ajax.reload(null, false);
                } else {
                    toastr.error(res.error || 'Failed to save schedule');
                }
            },
            error: function(xhr) {
                let msg = 'Failed to save schedule';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                }
                toastr.error(msg);
            }
        });
    });

    // -------------------------------------------------------------
    // Annual Initialization & Holiday Management
    // -------------------------------------------------------------
    function fetchHolidaysForInit(year) {
        $('#initHolidaysYearLabel').text(year);
        $('#initHolidaysList').html('<tr><td colspan="3" class="text-center text-muted py-2">Loading holidays...</td></tr>');

        $.ajax({
            url: "{{ url('/ajax/leave-management/get-year-holidays') }}",
            type: 'GET',
            data: { year: year },
            success: function(res) {
                $('#initHolidaysList').empty();
                if (res.success && res.holidays && res.holidays.length > 0) {
                    res.holidays.forEach(function(h) {
                        appendInitHolidayRow(h.holiday_name, h.holiday_date);
                    });
                } else {
                    let defaultHolidays = [
                        { name: "New Year's Day", date: `${year}-01-01` },
                        { name: "Eid al-Fitr (Estimated)", date: `${year}-03-20` },
                        { name: "Good Friday", date: `${year}-04-03` },
                        { name: "Easter Monday", date: `${year}-04-06` },
                        { name: "Labour Day", date: `${year}-05-01` },
                        { name: "Eid al-Adha (Estimated)", date: `${year}-05-27` },
                        { name: "Madaraka Day", date: `${year}-06-01` },
                        { name: "Mazingira Day", date: `${year}-10-10` },
                        { name: "Mashujaa Day", date: `${year}-10-20` },
                        { name: "Jamhuri Day", date: `${year}-12-12` },
                        { name: "Christmas Day", date: `${year}-12-25` },
                        { name: "Boxing Day", date: `${year}-12-26` }
                    ];
                    defaultHolidays.forEach(h => appendInitHolidayRow(h.name, h.date));
                }
            },
            error: function() {
                $('#initHolidaysList').html('<tr><td colspan="3" class="text-center text-danger py-2">Error loading holidays.</td></tr>');
            }
        });
    }

    function appendInitHolidayRow(name = '', date = '') {
        let row = `
            <tr class="holiday-row">
                <td>
                    <input type="text" class="form-control form-control-sm h-name" value="${name}" placeholder="Holiday name" required>
                </td>
                <td>
                    <input type="date" class="form-control form-control-sm h-date" value="${date}" required>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-holiday" style="padding: 2px 7px;">
                        &times;
                    </button>
                </td>
            </tr>
        `;
        $('#initHolidaysList').append(row);
    }

    $('#generateAnnualPlansBtn').on('click', function() {
        let year = $('#plan_year').val() || new Date().getFullYear();
        fetchHolidaysForInit(year);
        $('#generateAnnualPlansModal').modal('show');
    });

    $('#plan_year').on('change', function() {
        fetchHolidaysForInit($(this).val());
    });

    $('#btnAddNewHolidayRow').on('click', function() {
        let yr = $('#plan_year').val() || new Date().getFullYear();
        appendInitHolidayRow('', `${yr}-01-01`);
    });

    $(document).on('click', '.btn-remove-holiday', function() {$(this).closest('tr').remove();
    });

    $('#confirmGeneratePlans').on('click', function() {
        let btn = $(this);
        let year = $('#plan_year').val();
        let override = $('#override_existing').is(':checked') ? 1 : 0;

        let holidays = [];
        $('#initHolidaysList .holiday-row').each(function() {
            let hName = $(this).find('.h-name').val().trim();
            let hDate = $(this).find('.h-date').val();
            if (hName && hDate) {
                holidays.push({ name: hName, date: hDate });
            }
        });

        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Initializing...');

        $.ajax({
            url: "{{ url('/ajax/hr/leave-management/leave-calendar-plan/generate-annual') }}",
            type: 'POST',
            data: {
                year: year,
                override: override,
                holidays: holidays,
                _token: '{{ csrf_token() }}'
            },
            success: function(res) {
                btn.prop('disabled', false).text('Initialize Quotas & Apply Holidays');
                if (res.success) {
                    toastr.success(res.message);
                    $('#generateAnnualPlansModal').modal('hide');
                    table.ajax.reload(null, false);
                } else {
                    toastr.error(res.error || 'Failed to initialize plans');
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).text('Initialize Quotas & Apply Holidays');
                toastr.error(xhr.responseJSON?.error || 'Error during plan initialization');
            }
        });
    });
});
</script>
@endsection
