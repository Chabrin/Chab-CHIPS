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
                            @permission('create_leave_applications')
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addLeaveApplicationModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Leave Application</span>
                            </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
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
                                <div class="table-responsive custom-scrollbar">
                                    <table class="display" id="leave-applications-table">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Staff</th>
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

                    <!-- Sick Leave Pay Tier Notice -->
                    <div class="row mb-3 d-none" id="sickLeaveTierAlert">
                        <div class="col-12">
                            <div class="alert alert-info py-2 mb-0" id="sickTierContent">
                                <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">info</span>
                                <span id="sickTierText">Calculating pay tier breakdown...</span>
                            </div>
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
                            {{-- <div class="col-lg-12">
                                <div class="alert alert-info">
                                    <strong>Total Duration:</strong>
                                    <span id="total_days_display">0</span> days and 
                                    <span id="total_hours_display">0</span> hours
                                </div>
                            </div> --}}
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
                <a href="#" id="viewDetailsLink" class="btn btn-sm btn-primary d-flex align-items-center"><span class="material-symbols-outlined">visibility</span>View Full Details</a>
            </div>
        </div>
    </div>
</div>

<style>
    /* Calendar Styling */
    #leave-calendar {
        max-width: 100%;
    }

    #leave-calendar .fc {
        font-size: 0.9rem;
    }

    #leave-calendar .fc-toolbar-title {
        font-size: 1.25rem;
        font-weight: 600;
    }

    #leave-calendar .fc-button {
        padding: 0.4rem 0.8rem;
        font-size: 0.875rem;
    }

    #leave-calendar .fc-daygrid-day-number {
        padding: 4px;
        font-size: 0.85rem;
    }

    #leave-calendar .fc-col-header-cell {
        padding: 8px 4px;
        font-weight: 600;
        background-color: #f8f9fa;
    }

    #leave-calendar .fc-daygrid-day {
        min-height: 100px;
    }

    #leave-calendar .fc-event {
        font-size: 0.8rem;
        padding: 2px 4px;
        margin: 1px;
        border-radius: 3px;
        cursor: pointer;
    }

    #leave-calendar .fc-event-title {
        font-weight: 500;
    }

    /* Hover effect for events */
    #leave-calendar .fc-event:hover {
        opacity: 0.85;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: all 0.2s ease;
    }

    /* Status color indicators */
    #leave-calendar .fc-event.status-approved {
        background-color: #28a745;
        border-color: #28a745;
    }

    #leave-calendar .fc-event.status-pending {
        background-color: #ffc107;
        border-color: #ffc107;
        color: #000;
    }

    #leave-calendar .fc-event.status-rejected {
        background-color: #dc3545;
        border-color: #dc3545;
    }

    /* Today highlight */
    #leave-calendar .fc-day-today {
        background-color: #fff3cd !important;
    }

    /* Tab styling */
    .nav-link {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 20;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        #leave-calendar .fc-toolbar {
            flex-direction: column;
            gap: 10px;
        }

        #leave-calendar .fc-toolbar-chunk {
            display: flex;
            justify-content: center;
        }

        #leave-calendar .fc-daygrid-day {
            min-height: 70px;
        }

        #leave-calendar .fc-event {
            font-size: 0.7rem;
        }
    }
</style>



<script>
    $(document).ready(function() {
        // Toggle between leave duration types
        $('input[name="leave_duration_type"]').on('change', function() {
            const durationType = $(this).val();

            // Hide all sections first
            $('#full-days-section, #hours-only-section, #days-hours-section').hide();

            // Clear and reset required attributes
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
        // Handle Quick Approve / Reject from the Applications Table
   $(document).on('click', '.btn-quick-approve, .btn-quick-reject', function() {
        let $btn =$(this);
        let isApprove = $btn.hasClass('btn-quick-approve');
        let action = isApprove ? 'approve' : 'reject';
        let statusId = isApprove ? 5 : 41; // 5 = Approved, 41 = Rejected
        let applicationId = $btn.data('id');

        if (!confirm(`Are you sure you want to ${action} this leave application?`)) {
            return;
        }

        $btn.prop('disabled', true);

        $.ajax({
            url: "{{ url('/ajax/leave-management/leave-applications/status-update') }}",
            type: 'POST',
            data: {
                id: applicationId,
                status: statusId,
                _token: '{{ csrf_token() }}'
            },
            success: function(res) {
                if (res.success) {
                    toastr.success(`Application ${action}d successfully`);
                    table.ajax.reload(null, false);
                } else {
                    toastr.error(res.error || `Failed to ${action} leave application`);
                    $btn.prop('disabled', false);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false);
                let msg = 'Error updating status';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                toastr.error(msg);
            }
        });
    });

        // Calculate hours for hours-only section
        $('#start_time_only, #end_time_only').on('change', function() {
            let startTime = $('#start_time_only').val();
            let endTime = $('#end_time_only').val();

            if (startTime && endTime) {
                let start = new Date('2000-01-01 ' + startTime);
                let end = new Date('2000-01-01 ' + endTime);

                if (end > start) {
                    let diff = (end - start) / 1000 / 60 / 60; // difference in hours
                    $('#hours_requested_only').val(diff.toFixed(2));
                } else {
                    $('#hours_requested_only').val('');
                    toastr.warning('End time must be after start time');
                }
            }
        });

        // // Calculate total days and hours for combined section
        // function calculateCombinedDuration() {
        //     let startDate = $('#start_date_combined').val();
        //     let startTime = $('#start_time_combined').val();
        //     let endDate = $('#end_date_combined').val();
        //     let endTime = $('#end_time_combined').val();
        //     let leaveTypeId = $('#leave_type_id').val();

        //     if (startDate && startTime && endDate && endTime) {
        //         let start = new Date(startDate + ' ' + startTime);
        //         let end = new Date(endDate + ' ' + endTime);

        //         if (end > start) {
        //             // Fetch leave type, public holidays, and work days configuration
        //             $.when(
        //                 $.ajax({
        //                     url: `/ajax/hr/leave_types/list`,
        //                     type: 'GET',
        //                     dataType: 'json'
        //                 }),
        //                 $.ajax({
        //                     url: "{{ url('/ajax/leave-management/public-holidays') }}",
        //                     type: 'GET',
        //                     dataType: 'json'
        //                 }),
        //                 $.ajax({
        //                     url: "{{ url('/ajax/hr/workdays/list') }}",
        //                     type: 'GET',
        //                     dataType: 'json'
        //                 })
        //             ).done(function(leaveTypesResp, holidaysResp, workDaysResp) {
        //                 let leaveTypes = leaveTypesResp[0].leave_types || [];
        //                 let publicHolidays = holidaysResp[0].public_holidays || [];
        //                 let workDays = workDaysResp[0].work_days || [];

        //                 // Find the current leave type
        //                 let currentLeaveType = leaveTypes.find(lt => lt.id == leaveTypeId);

        //                 // Check if this is maternity leave (adjust ID as needed)
        //                 // Typically maternity leave type_name contains "maternity" or has specific ID
        //                 let isMaternityLeave = currentLeaveType && 
        //                     (currentLeaveType.type_name.toLowerCase().includes('maternity') || 
        //                      currentLeaveType.id == 5); // Adjust ID based on your system

        //                 // Build map of non-working days (day 7 = Sunday in ISO format)
        //                 let nonWorkingDays = new Set();
        //                 workDays.forEach(function(wd) {
        //                     if (wd.work_type_id == 3) { // Non-working day
        //                         nonWorkingDays.add(wd.week_day);
        //                     }
        //                 });

        //                 // Helper function to check if a date is a public holiday
        //                 function isPublicHoliday(date) {
        //                     let checkDay = date.getDate();
        //                     let checkMonth = date.getMonth() + 1;

        //                     return publicHolidays.some(function(holiday) {
        //                         let holidayStart = new Date(holiday.start_date);
        //                         let holidayEnd = new Date(holiday.end_date);

        //                         let holidayStartDay = holidayStart.getDate();
        //                         let holidayStartMonth = holidayStart.getMonth() + 1;
        //                         let holidayEndDay = holidayEnd.getDate();
        //                         let holidayEndMonth = holidayEnd.getMonth() + 1;

        //                         if (holidayStartMonth == holidayEndMonth) {
        //                             return checkMonth == holidayStartMonth && 
        //                                    checkDay >= holidayStartDay && 
        //                                    checkDay <= holidayEndDay;
        //                         }

        //                         if (checkMonth == holidayStartMonth && checkDay >= holidayStartDay) {
        //                             return true;
        //                         }
        //                         if (checkMonth == holidayEndMonth && checkDay <= holidayEndDay) {
        //                             return true;
        //                         }
        //                         if (holidayStartMonth < holidayEndMonth) {
        //                             return checkMonth > holidayStartMonth && checkMonth < holidayEndMonth;
        //                         } else {
        //                             return checkMonth > holidayStartMonth || checkMonth < holidayEndMonth;
        //                         }
        //                     });
        //                 }

        //                 let current = new Date(start);
        //                 let totalWorkingHours = 0;

        //                 const workStartHour = 8;
        //                 const workEndHour = 17;
        //                 const hoursPerDay = workEndHour - workStartHour;

        //                 // Loop through each day
        //                 while (current < end) {
        //                     let dayOfWeek = current.getDay(); // 0=Sunday, 1=Monday, ..., 6=Saturday
        //                     let isoDayOfWeek = dayOfWeek === 0 ? 7 : dayOfWeek; // Convert to ISO (1-7)

        //                     let isNonWorkingDay = nonWorkingDays.has(isoDayOfWeek);
        //                     let isHoliday = isPublicHoliday(current);

        //                     // For maternity leave: count all days except public holidays
        //                     // For other leave: exclude non-working days and public holidays
        //                     let shouldCount = isMaternityLeave ? !isHoliday : (!isNonWorkingDay && !isHoliday);

        //                     if (shouldCount) {
        //                         let dayStart = new Date(current);
        //                         let dayEnd = new Date(current);

        //                         dayStart.setHours(workStartHour, 0, 0, 0);
        //                         dayEnd.setHours(workEndHour, 0, 0, 0);

        //                         if (current.toDateString() === start.toDateString()) {
        //                             dayStart = new Date(start);
        //                         }

        //                         let nextDay = new Date(current);
        //                         nextDay.setDate(nextDay.getDate() + 1);
        //                         nextDay.setHours(0, 0, 0, 0);

        //                         if (end < nextDay) {
        //                             dayEnd = new Date(end);
        //                         }

        //                         if (dayStart < dayEnd) {
        //                             if (dayStart.getHours() < workStartHour) {
        //                                 dayStart.setHours(workStartHour, 0, 0, 0);
        //                             }
        //                             if (dayEnd.getHours() > workEndHour || 
        //                                 (dayEnd.getHours() === workEndHour && dayEnd.getMinutes() > 0)) {
        //                                 dayEnd.setHours(workEndHour, 0, 0, 0);
        //                             }

        //                             if (dayStart.getHours() < workEndHour) {
        //                                 let hoursThisDay = (dayEnd - dayStart) / (1000 * 60 * 60);
        //                                 totalWorkingHours += Math.max(0, hoursThisDay);
        //                             }
        //                         }
        //                     }

        //                     current.setDate(current.getDate() + 1);
        //                     current.setHours(0, 0, 0, 0);
        //                 }

        //                 let fullDays = Math.floor(totalWorkingHours / hoursPerDay);
        //                 let remainingHours = totalWorkingHours % hoursPerDay;

        //                 $('#total_days_display').text(fullDays);
        //                 $('#total_hours_display').text(remainingHours.toFixed(2));
        //             }).fail(function() {
        //                 toastr.error('Failed to load configuration for calculation');
        //                 $('#total_days_display').text('0');
        //                 $('#total_hours_display').text('0');
        //             });
        //         } else {
        //             $('#total_days_display').text('0');
        //             $('#total_hours_display').text('0');
        //             toastr.warning('End date/time must be after start date/time');
        //         }
        //     }
        // }

        // $('#start_date_combined, #start_time_combined, #end_date_combined, #end_time_combined').on('change', calculateCombinedDuration);

        // // Also trigger recalculation when leave type changes
        // $('#leave_type_id').on('change', function() {
        //     // Recalculate if dates are already filled
        //     if ($('#start_date_combined').val() && $('#end_date_combined').val()) {
        //         calculateCombinedDuration();
        //     }
        // });


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
                dataSrc: 'leave_applications'
            },
            columns: [{
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
                    render: function(data) {
                        if (data == 1) {
                            return '<span class="badge bg-warning">Pending</span>';
                        } else if (data == 24) {
                            return '<span class="badge bg-info">Partially Approved</span>';
                        } else if (data == 5) {
                            return '<span class="badge bg-success">Approved</span>';
                        } else if (data == 41) {
                            return '<span class="badge bg-danger">Rejected</span>';
                        } else {
                            return '<span class="badge bg-secondary">Unknown</span>';
                        }
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        if (row.can_view) {
                            buttons += `
                                <a href="{{ url('/hr/leave-management/leave-applications/') }}/${row.encrypted_leave_application_id}" class="btn btn-xs btn-light" style="padding:1px 2px;">
                                    <span class="material-symbols-outlined" style="font-size:18px;">visibility</span>
                                </a>`;
                        }

                        // Show quick Approve & Reject buttons for Pending / Partially Approved applications
                        if (row.can_update && (row.current_status_id == 1 || row.current_status_id == 24)) {
                            buttons += `
                                <button class="btn btn-xs btn-outline-success btn-quick-approve" data-id="${row.id}" title="Approve Leave" style="padding:1px 4px;">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">check_circle</span>
                                </button>
                                <button class="btn btn-xs btn-outline-danger btn-quick-reject" data-id="${row.id}" title="Reject Leave" style="padding:1px 4px;">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">cancel</span>
                                </button>
                            `;
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
                                    style="padding:1px 2px;">
                                    <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                                </button>`;
                        }
                        if (row.can_delete) {
                            buttons += `
                                <button class="btn btn-xs btn-light delete-leave-application" 
                                    data-id="${row.id}" style="padding:1px 2px;">
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
                                            status: app.current_status_id == 5 ? 'Approved' : (app.current_status_id == 41 ? 'Rejected' : 'Pending'),
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
                                <strong>
                                    ${event.extendedProps.status === 'Approved' ? '<span class="badge bg-success">Approved</span>' : 
                                      event.extendedProps.status === 'Rejected' ? '<span class="badge bg-danger">Rejected</span>' : 
                                      '<span class="badge bg-warning">Pending</span>'}
                                </strong>
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

        // Initialize calendar when calendar tab is shown
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

        // Populate selects on modal show
        $('#addLeaveApplicationModal').on('show.bs.modal', function() {
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
                                text: staff.full_names
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

                            // Set the date range constraints
                            if (response.start_date && response.end_date) {
                                let startDateOnly = response.start_date.split(' ')[0]; // Extract date part (YYYY-MM-DD)
                                let endDateOnly = response.end_date.split(' ')[0]; // Extract date part (YYYY-MM-DD)

                                // Set min and max attributes for all date inputs
                                $('#start_date').attr('min', startDateOnly).attr('max', endDateOnly);
                                $('#leave_date_hours').attr('min', startDateOnly).attr('max', endDateOnly);
                                $('#start_date_combined').attr('min', startDateOnly).attr('max', endDateOnly);
                                $('#end_date_combined').attr('min', startDateOnly).attr('max', endDateOnly);
                            }
                        } else {
                            $('#available_days').val('');
                            toastr.warning('Annual leave calendar must be created and approved for this employee');

                            // Clear staff and leave type selections
                            // $('#staff_id').val('').trigger('change');
                            // $('#leave_type_id').val('').trigger('change');

                            // Clear min/max constraints
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

                        // Clear staff and leave type selections
                        $('#staff_id').val('').trigger('change');
                        $('#leave_type_id').val('').trigger('change');

                        // Clear min/max constraints
                        $('#start_date, #leave_date_hours, #start_date_combined, #end_date_combined').removeAttr('min').removeAttr('max');
                    }
                });
            } else {
                $('#available_days').val('');
                // Clear min/max constraints when no staff/leave type selected
                $('#start_date, #leave_date_hours, #start_date_combined, #end_date_combined').removeAttr('min').removeAttr('max');
            }
        }

        $('#staff_id, #leave_type_id').on('change', fetchAvailableDays);
        // Check for Annual Leave Block selection or Sick Leave Tier evaluation
        function handleLeaveTypeSpecifics() {
            let staffId = $('#staff_id').val();
            let leaveTypeId = $('#leave_type_id').val();
            let selectedText = $('#leave_type_id option:selected').text().toLowerCase();

            let isAnnual = selectedText.includes('annual') || selectedText.includes('normal');
            let isSick = selectedText.includes('sick');

            // Reset containers
            $('#annualSlotPickerContainer').addClass('d-none');
            $('#sickLeaveTierAlert').addClass('d-none');

            if (!staffId || !leaveTypeId) return;

            // 1. Annual Leave Flow: Fetch scheduled blocks
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
                                options += `<option value="${slot.id}" data-start="${slot.start_date}" data-end="${slot.end_date}" data-days="${slot.days}">${slot.display}</option>`;
                            });
                            $('#scheduled_slot_select').html(options);
                        } else {
                            $('#scheduled_slot_select').html('<option value="">No scheduled blocks found for this year</option>');
                            toastr.warning(res.message || 'You must first schedule your 21 days in the Leave Calendar Plan.');
                        }
                    }
                });
            }

            // 2. Sick Leave Flow: Check and show Tier status
            if (isSick) {
                $('#sickLeaveTierAlert').removeClass('d-none');
                $('#sickTierText').text('Evaluating sick pay tiers for the current year...');

                let startDate = $('#start_date').val() || new Date().toISOString().split('T')[0];
                let endDate = $('#end_date').val() || startDate;

                $.ajax({
                    url: "{{ url('/ajax/leave-management/evaluate-application') }}",
                    type: 'POST',
                    data: {
                        staff_id: staffId,
                        leave_type_id: leaveTypeId,
                        start_date: startDate,
                        end_date: endDate,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(res) {
                        if (res.success && res.sick_breakdown) {
                            let b = res.sick_breakdown;
                            $('#sickTierText').html(`<strong>Sick Pay Breakdown:</strong> ${b.summary_text} (Used this year: ${b.used_this_year} days)`);
                        }
                    }
                });
            }
        }

        $('#staff_id, #leave_type_id').on('change', handleLeaveTypeSpecifics);

        // When a scheduled block is selected from the dropdown
        $('#scheduled_slot_select').on('change', function() {
            let opt = $(this).find('option:selected');
            let start = opt.data('start');
            let end = opt.data('end');
            let days = opt.data('days');

            if (start && end) {
                // Ensure full day duration is checked
                $('input[name="leave_duration_type"][value="full_day"]').prop('checked', true).trigger('change');
                
                $('#start_date').val(start);
                $('#requested_days').val(days);
                toastr.info(`Selected block: ${days} working day(s)`);
            }
        });

        $('#leaveApplicationForm').on('submit', function(e) {
            e.preventDefault();

            let formData = new FormData();
            let durationType = $('input[name="leave_duration_type"]:checked').val();
            let id = $('#leaveApplicationId').val();

            // Common fields
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
                // Full days only
                formData.append('start_date', $('#start_date').val());
                formData.append('requested_days', $('#requested_days').val());
                formData.append('is_partial_day', '0');

            } else if (durationType === 'partial_day') {
                // Hours only
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
                // Days + Hours combined
                let startDate = $('#start_date_combined').val();
                let startTime = $('#start_time_combined').val();
                let endDate = $('#end_date_combined').val();
                let endTime = $('#end_time_combined').val();

                formData.append('start_date', startDate + ' ' + startTime);
                formData.append('end_date', endDate + ' ' + endTime);
                formData.append('start_time', startTime);
                formData.append('end_time', endTime);

                // Calculate total hours
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
            $('#amount').val($btn.data('amount'));
            $('#allowance_type').val($btn.data('allowance_type_id')).trigger('change');

            // Determine leave duration type from the data
            let startDate = $btn.data('start_date');
            let endDate = $btn.data('end_date');
            let isPartialDay = $btn.data('is_partial_day');
            let hoursRequested = $btn.data('hours_requested');

            // Parse dates
            let startDateTime = startDate ? new Date(startDate) : null;
            let endDateTime = endDate ? new Date(endDate) : null;

            let durationType = 'full_day';

            if (startDateTime && endDateTime) {
                let startDateOnly = startDateTime.toISOString().split('T')[0];
                let endDateOnly = endDateTime.toISOString().split('T')[0];
                let startTimeOnly = startDateTime.toTimeString().slice(0, 5);
                let endTimeOnly = endDateTime.toTimeString().slice(0, 5);

                // Check if same day and has custom times (not 08:00 to 17:00)
                if (startDateOnly === endDateOnly && !(startTimeOnly === '08:00' && endTimeOnly === '17:00')) {
                    durationType = 'partial_day';

                    // Set partial day fields
                    $('#leave_date_hours').val(startDateOnly);
                    $('#start_time_only').val(startTimeOnly);
                    $('#end_time_only').val(endTimeOnly);
                    $('#hours_requested_only').val(hoursRequested || '');

                } else if (startDateOnly !== endDateOnly && !(startTimeOnly === '08:00' && endTimeOnly === '17:00')) {
                    durationType = 'days_and_hours';

                    // Set days + hours fields
                    $('#start_date_combined').val(startDateOnly);
                    $('#start_time_combined').val(startTimeOnly);
                    $('#end_date_combined').val(endDateOnly);
                    $('#end_time_combined').val(endTimeOnly);

                    // Trigger calculation
                    calculateCombinedDuration();

                } else {
                    durationType = 'full_day';

                    // Set full day fields
                    $('#start_date').val(startDateOnly);
                    $('#requested_days').val($btn.data('requested_days'));
                }
            }

            // Set the radio button and trigger change to show/hide appropriate sections
            $(`input[name="leave_duration_type"][value="${durationType}"]`).prop('checked', true).trigger('change');

            $('#addLeaveApplicationModal').modal('show');

            // Load staff list
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
                                text: staff.full_names
                            }));
                        });
                    }

                    // Destroy and reinitialize select2
                    if ($staff_id.hasClass("select2-hidden-accessible")) {
                        $staff_id.select2('destroy');
                    }

                    $staff_id.select2({
                        dropdownParent: $('#addLeaveApplicationModal'),
                        placeholder: 'Select Staff',
                        allowClear: true
                    });

                    // Set selected value
                    let selectedStaffId = $btn.data('staff_id');
                    $staff_id.val(selectedStaffId).trigger('change');
                },
                error: function() {
                    toastr.error('Failed to load staff.');
                }
            });

            // Load leave types
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

                    // Destroy and reinitialize select2
                    if ($leave_type_id.hasClass("select2-hidden-accessible")) {
                        $leave_type_id.select2('destroy');
                    }

                    $leave_type_id.select2({
                        dropdownParent: $('#addLeaveApplicationModal'),
                        placeholder: 'Select leave type',
                        allowClear: true
                    });

                    // Set selected value
                    let selectedLeaveTypeId = $btn.data('leave_type_id');
                    $leave_type_id.val(selectedLeaveTypeId).trigger('change');

                    // Fetch available days after setting leave type
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
