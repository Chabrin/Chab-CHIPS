@extends('layouts.main')
@section('content')
<style>
    .spin {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .calendar-row.selected {
        background-color: #e3f2fd !important;
    }
</style>
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <!-- Payroll Calendar Section -->
        <div class="row mb-3">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Payroll Calendar (Summary)</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_payroll_calendar')
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addCalendarModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Calendar Month</span>
                            </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="calendar-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Payroll Month</th>
                                        <th>Periods Count</th>
                                        <th>Status</th>
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

        <!-- Payroll Periods Section -->
        <div class="row" id="periodsSection" style="display:none;">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Payroll Periods - <span id="selectedCalendarMonth"></span></h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_payroll_calendar')
                            <button class="btn btn-primary btn-sm d-flex align-items-center" id="addPeriodBtn">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Period</span>
                            </button>
                            @endpermission
                            <button class="btn btn-light btn-sm d-flex align-items-center" id="closePeriodsBtn">
                                <span class="material-symbols-outlined">close</span>
                                <span class="ms-1">Close</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="periods-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Period #</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Duration</th>
                                        <th>Status</th>
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

<!-- Add/Edit Calendar Modal -->
<div class="modal fade" id="addCalendarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="calendarModalTitle">Add Payroll Calendar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="calendarForm">
                    <input type="hidden" id="calendarId">
                    <div class="mb-3">
                        <label for="payroll_month_input" class="form-label">Payroll Month <span style="color:red">*</span></label>
                        <input type="month" class="form-control" id="payroll_month_input" required>
                        <small class="text-muted">Select the payroll month and year</small>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm" id="saveCalendarBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="calendar_save_spinner"></span>
                            <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Period Modal -->
<div class="modal fade" id="addPeriodModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="periodModalTitle">Add Payroll Period</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="periodForm">
                    <input type="hidden" id="periodId">
                    <input type="hidden" id="periodCalendarId">
                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <label for="period_start_date" class="form-label">Start Date <span style="color:red">*</span></label>
                            <input type="date" class="form-control" id="period_start_date" required>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label for="period_end_date" class="form-label">End Date <span style="color:red">*</span></label>
                            <input type="date" class="form-control" id="period_end_date" required>
                        </div>
                    </div>
                    <div class="alert alert-info small mb-3">
                        <span class="material-symbols-outlined" style="font-size:16px;">info</span>
                        Period cannot exceed 32 days
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm" id="savePeriodBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="period_save_spinner"></span>
                            <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        let currentCalendarId = null;
        let currentCalendarMonthDate = null;
        let calendarTable, periodsTable;

        // Initialize Calendar Table
        calendarTable = $('#calendar-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/hr/payroll/calendar/list') }}",
                dataSrc: 'calendars'
            },
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: (data, type, row, meta) => meta.row + 1
                },
                {
                    data: 'month_formatted',
                    render: data => data || '-'
                },
                {
                    data: 'periods_count',
                    render: data => `<span class="badge badge-light-success">${data}</span>`
                },
                {
                    data: 'current_status_id',
                    render: data => data == 5 ? '<span class="badge badge-light-success">Active</span>' : '<span class="badge badge-light-danger">Inactive</span>'
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        buttons += `<button class="btn btn-xs btn-info view-periods" data-id="${row.id}" data-month="${row.month_formatted}" data-month-date="${row.payroll_month_date}" style="padding:1px 2px;" title="View Periods">
                            <span class="material-symbols-outlined" style="font-size:18px;">calendar_month</span>
                        </button>`;
                        if (row.can_update) {
                            buttons += `<button class="btn btn-xs btn-light edit-calendar" data-id="${row.id}" data-payroll_month_date="${row.payroll_month_date}" style="padding:1px 2px;">
                                <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                            </button>`;
                        }
                        if (row.can_delete) {
                            buttons += `<button class="btn btn-xs btn-light delete-calendar" data-id="${row.id}" style="padding:1px 2px;">
                                <span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>
                                <span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>
                            </button>`;
                        }
                        buttons += '</div>';
                        return buttons;
                    }
                }
            ],
            pageLength: 10,
            order: [
                [1, 'desc']
            ]
        });

        // Initialize Periods Table
        periodsTable = $('#periods-table').DataTable({
            processing: true,
            serverSide: false,
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: (data, type, row, meta) => meta.row + 1
                },
                {
                    data: 'period_number',
                    render: data => data || '-'
                },
                {
                    data: 'start_date',
                    render: data => data ? new Date(data).toLocaleDateString() : '-'
                },
                {
                    data: 'end_date',
                    render: data => data ? new Date(data).toLocaleDateString() : '-'
                },
                {
                    data: 'duration_days',
                    render: data => data ? `${data} days` : '-'
                },
                {
                    data: 'current_status_id',
                    render: data => data == 5 ? '<span class="badge badge-light-success">Active</span>' : '<span class="badge badge-light-danger">Inactive</span>'
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        if (row.can_update) {
                            buttons += `<button class="btn btn-xs btn-light edit-period" data-id="${row.id}" data-start="${row.start_date}" data-end="${row.end_date}" style="padding:1px 2px;">
                                <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                            </button>`;
                        }
                        if (row.can_delete) {
                            buttons += `<button class="btn btn-xs btn-light delete-period" data-id="${row.id}" style="padding:1px 2px;">
                                <span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>
                                <span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>
                            </button>`;
                        }
                        buttons += '</div>';
                        return buttons;
                    }
                }
            ],
            pageLength: 10
        });

        // View Periods
        $(document).on('click', '.view-periods', function() {
            currentCalendarId = $(this).data('id');
            currentCalendarMonthDate = String($(this).data('month-date'));
            const month = $(this).data('month');
            $('#selectedCalendarMonth').text(month);
            $('#periodsSection').slideDown();
            loadPeriods(currentCalendarId);
        });

        // Close Periods Section
        $('#closePeriodsBtn').on('click', function() {
            $('#periodsSection').slideUp();
            currentCalendarId = null;
            currentCalendarMonthDate = null;
        });

        // Load Periods
        function loadPeriods(calendarId) {
            $.ajax({
                url: `/ajax/hr/payroll/calendar/${calendarId}/periods`,
                type: 'GET',
                success: function(res) {
                    periodsTable.clear();
                    if (res.periods && res.periods.length > 0) {
                        periodsTable.rows.add(res.periods);
                    }
                    periodsTable.draw();
                },
                error: function() {
                    toastr.error('Failed to load periods');
                }
            });
        }

        // Calendar Form Submit
        $('#calendarForm').on('submit', function(e) {
            e.preventDefault();
            const id = $('#calendarId').val();
            const url = id ? `/ajax/hr/payroll/calendar/${id}` : '/ajax/hr/payroll/calendar';

            const monthInput = $('#payroll_month_input').val();
            const payroll_month_date = monthInput.replace('-', '');

            const $btn = $('#saveCalendarBtn');
            const $spinner = $('#calendar_save_spinner');
            $spinner.removeClass('d-none');
            $btn.prop('disabled', true);

            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    payroll_month_date: payroll_month_date,
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    $spinner.addClass('d-none');
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('Saved successfully');
                        $('#addCalendarModal').modal('hide');
                        $('#calendarForm')[0].reset();
                        calendarTable.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error saving');
                    }
                },
                error: function(xhr) {
                    $spinner.addClass('d-none');
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

        // Edit Calendar
        $(document).on('click', '.edit-calendar', function() {
            const payrollMonthDate = String($(this).data('payroll_month_date'));
            if (payrollMonthDate && payrollMonthDate.length === 6) {
                const year = payrollMonthDate.substring(0, 4);
                const month = payrollMonthDate.substring(4, 6);
                $('#payroll_month_input').val(year + '-' + month);
            }
            $('#calendarId').val($(this).data('id'));
            $('#calendarModalTitle').text('Edit Payroll Calendar');
            $('#addCalendarModal').modal('show');
        });

        // Delete Calendar
        $(document).on('click', '.delete-calendar', function() {
            const $btn = $(this);
            const $icon = $btn.find('.material-symbols-outlined').first();
            if (!confirm('Delete this calendar and all its periods?')) return;

            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);

            $.ajax({
                url: `/ajax/hr/payroll/calendar/${$btn.data('id')}`,
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
                        calendarTable.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error deleting');
                    }
                },
                error: function() {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);
                    toastr.error('Delete error');
                }
            });
        });

        // Add Period Button
        $('#addPeriodBtn').on('click', function() {
            $('#periodForm')[0].reset();
            $('#periodId').val('');
            $('#periodCalendarId').val(currentCalendarId);
            $('#periodModalTitle').text('Add Payroll Period');

            // Pre-fill and constrain dates to the calendar's month
            if (currentCalendarMonthDate && currentCalendarMonthDate.length === 6) {
                const year = parseInt(currentCalendarMonthDate.substring(0, 4));
                const month = parseInt(currentCalendarMonthDate.substring(4, 6));
                const firstDay = `${year}-${String(month).padStart(2, '0')}-01`;
                const lastDayNum = new Date(year, month, 0).getDate();
                const lastDay = `${year}-${String(month).padStart(2, '0')}-${String(lastDayNum).padStart(2, '0')}`;
                $('#period_start_date').val(firstDay).attr('min', firstDay).attr('max', lastDay);
                $('#period_end_date').val(lastDay).attr('min', firstDay).attr('max', lastDay);
            }

            $('#addPeriodModal').modal('show');
        });

        // Period Form Submit
        $('#periodForm').on('submit', function(e) {
            e.preventDefault();
            const periodId = $('#periodId').val();
            const calendarId = $('#periodCalendarId').val() || currentCalendarId;
            const url = periodId ?
                `/ajax/hr/payroll/calendar/${calendarId}/periods/${periodId}` :
                `/ajax/hr/payroll/calendar/${calendarId}/periods`;

            const $btn = $('#savePeriodBtn');
            const $spinner = $('#period_save_spinner');
            $spinner.removeClass('d-none');
            $btn.prop('disabled', true);

            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    start_date: $('#period_start_date').val(),
                    end_date: $('#period_end_date').val(),
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    $spinner.addClass('d-none');
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('Saved successfully');
                        $('#addPeriodModal').modal('hide');
                        $('#periodForm')[0].reset();
                        loadPeriods(calendarId);
                        calendarTable.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error saving');
                    }
                },
                error: function(xhr) {
                    $spinner.addClass('d-none');
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

        // Edit Period
        $(document).on('click', '.edit-period', function() {
            $('#periodId').val($(this).data('id'));
            $('#periodCalendarId').val(currentCalendarId);
            $('#period_start_date').val($(this).data('start'));
            $('#period_end_date').val($(this).data('end'));
            $('#periodModalTitle').text('Edit Payroll Period');
            $('#addPeriodModal').modal('show');
        });

        // Delete Period
        $(document).on('click', '.delete-period', function() {
            const $btn = $(this);
            const $icon = $btn.find('.material-symbols-outlined').first();
            if (!confirm('Delete this period?')) return;

            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);

            $.ajax({
                url: `/ajax/hr/payroll/calendar/${currentCalendarId}/periods/${$btn.data('id')}`,
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
                        loadPeriods(currentCalendarId);
                        calendarTable.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error deleting');
                    }
                },
                error: function() {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);
                    toastr.error('Delete error');
                }
            });
        });

        // Reset modal on close
        $('#addCalendarModal').on('hidden.bs.modal', function() {
            $('#calendarForm')[0].reset();
            $('#calendarId').val('');
            $('#calendarModalTitle').text('Add Payroll Calendar');
        });

        $('#addPeriodModal').on('hidden.bs.modal', function() {
            $('#periodForm')[0].reset();
            $('#periodId').val('');
        });
    });
</script>
@endsection