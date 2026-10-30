@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <div>
                            <h4 class="mb-0">My Leave</h4>
                            @if($staffLinked)
                            <small class="text-muted">{{ $staffName }}</small>
                            @endif
                        </div>
                        @if($staffLinked)
                        <button class="btn btn-primary btn-sm d-flex align-items-center gap-1"
                                data-bs-toggle="modal" data-bs-target="#leaveApplicationModal">
                            <span class="material-symbols-outlined">add_circle</span>
                            New Leave Application
                        </button>
                        @endif
                    </div>

                    <div class="card-body">

                        @if(!$staffLinked)
                        <div class="alert alert-warning d-flex align-items-center gap-2">
                            <span class="material-symbols-outlined">warning</span>
                            <div>
                                Your user account is not linked to a staff record.
                                Please contact HR to have your account linked.
                            </div>
                        </div>

                        @else
                        <div class="table-responsive">
                            <table class="display" id="myLeaveTable" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Leave Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Days</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        @endif

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@if($staffLinked)
{{-- Leave Application Modal --}}
<div class="modal fade" id="leaveApplicationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Leave Application — {{ $staffName }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="leaveForm">
                    <input type="hidden" name="staff_id" value="{{ $staffId }}">
                    <div class="row">
                       <div class="col-lg-6 mb-3">
                        <label class="form-label">Leave Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="leave_type_id" id="sl_leave_type_id" required>
                            <option value="">Select leave type...</option>
                        </select>

                        <div id="preScheduledBlockContainer" class="mb-3 mt-3" style="display: none;">
                            <div style="background: #f0f7ff; border: 1px solid #b7d7f0; border-radius: 8px; padding: 12px 14px;">
                                <label class="form-label d-flex align-items-center gap-1 text-primary fw-bold" style="font-size: 13px; margin-bottom: 6px;">
                                    <span class="material-symbols-outlined" style="font-size: 16px;">event_available</span>
                                    Select Pre-Scheduled Leave Block <span class="text-danger">*</span>
                                </label>
                                <select id="scheduled_slot_select" class="form-select form-select-sm" style="background-color: #fff;">
                                    <option value="">-- Choose from your scheduled plan --</option>
                                </select>
                                <div class="form-text" style="font-size: 11px; color: #6b7280; margin-top: 4px;">
                                    Dates and working days will automatically populate from your approved annual calendar plan.
                                </div>
                            </div>
                        </div>
                    </div>
                        <div class="col-lg-6 mb-3">
                            <label class="form-label">Available Days</label>
                            <input type="number" class="form-control" id="sl_available_days" readonly>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 mb-3 d-none" id="sl_tier_alert">
                        <span id="sl_tier_text"></span>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label">Leave Duration <span class="text-danger">*</span></label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="leave_duration_type" id="sl_full_day" value="full_day" checked>
                                <label class="btn btn-outline-primary" for="sl_full_day">Full Day(s)</label>
                                <input type="radio" class="btn-check" name="leave_duration_type" id="sl_partial_day" value="partial_day">
                                <label class="btn btn-outline-primary" for="sl_partial_day">Hours Only</label>
                            </div>
                        </div>
                    </div>
                    <div id="sl_full_days_section">
                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <label class="form-label">Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="start_date" id="sl_start_date">
                            </div>
                            <div class="col-lg-6 mb-3">
                                <label class="form-label">Requested Days <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="requested_days" id="sl_requested_days" min="0.5" step="0.5">
                                <small class="text-muted">A Saturday counts as half a day.</small>
                            </div>
                        </div>
                    </div>
                    <div id="sl_hours_section" style="display:none;">
                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <label class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="leave_date_hours" id="sl_leave_date_hours">
                            </div>
                            <div class="col-lg-6 mb-3">
                                <label class="form-label">Time Range</label>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="time" class="form-control" name="start_time_only" id="sl_start_time_only" value="08:00">
                                    <span>to</span>
                                    <input type="time" class="form-control" name="end_time_only" id="sl_end_time_only" value="17:00">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="leaveSubmitBtn">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="leaveSpinner"></span>
                    Submit Application
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Annual Leave Schedule Card -->
@if($annualPlan)
    @php
        $requiredDays = floor((float) $annualPlan->entitled_days * 2) / 2;
        $scheduledDays = (float) $annualPlan->slots->sum('days_count');
        $isFullyScheduled = abs($scheduledDays - $requiredDays) < 0.001;
    @endphp

    <div class="card border-primary shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="card-title text-primary mb-1">
                    <span class="material-symbols-outlined align-middle me-1">calendar_month</span>
                    Annual Leave Schedule ({{ $currentYear }})
                </h5>
                <p class="text-muted small mb-0">
                    Mandatory {{ $requiredDays }} Working Days Requirement:
                    @if($isFullyScheduled)
                        <span class="badge bg-success">Scheduled ({{ $requiredDays }}/{{ $requiredDays }} Days)</span>
                    @else
                        <span class="badge bg-warning text-dark">Action Required ({{ $scheduledDays }}/{{ $requiredDays }} Days Scheduled)</span>
                    @endif
                    @if($annualPlan->balance !== null)
                        <span class="badge bg-info text-dark ms-2">Balance: {{ number_format((float) $annualPlan->balance, 2) }} days</span>
                    @endif
                </p>
            </div>
            <div>
                <button type="button" class="btn btn-primary d-flex align-items-center open-schedule-modal"
                    data-plan-id="{{ $annualPlan->id }}"
                    data-leave-type-id="{{ $annualPlan->leave_type_id }}"
                    data-entitled-days="{{ $requiredDays }}"
                    data-employee-name="{{ $staff->full_names ?? auth()->user()->name }}">
                    <span class="material-symbols-outlined me-1">edit_calendar</span>
                    {{ $isFullyScheduled ? 'Reschedule / View Plan' : 'Schedule My ' . $requiredDays . ' Days' }}
                </button>
            </div>
        </div>
    </div>
@endif

<!-- Shared Scheduling Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Annual Leave (<span id="modalEmployeeName"></span>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center p-3 mb-3 bg-light rounded border text-dark">
                    <div><strong class="text-dark">Target:</strong> <span id="modalTargetDays" class="text-dark">0 Days</span></div>
                    <div><strong class="text-dark">Allocated:</strong> <span id="allocatedDaysBadge" class="badge bg-secondary">0 Days</span></div>
                    <div><strong class="text-dark">Remaining:</strong> <span id="remainingDaysBadge" class="badge bg-warning text-dark">0 Days</span></div>
                </div>

                <form id="scheduleSlotsForm">
                    <input type="hidden" id="modalPlanId" name="plan_id">
                    <input type="hidden" id="modalLeaveTypeId" name="leave_type_id">
                    <div id="slotsContainer"></div>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="addSlotRowBtn">
                        <span class="material-symbols-outlined align-middle" style="font-size:16px;">add</span>
                        Add Leave Block
                    </button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveScheduleBtn" disabled>
                    Save & Submit Schedule (<span id="saveRequiredDays">0</span> Days Required)
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    const currentStaffId = "{{ $staff->id ?? Auth::user()->linkedStaff()?->id }}";

    $('#leave_type_id, select[name="leave_type_id"]').on('change', function () {
        const selectedText = $(this).find('option:selected').text().toLowerCase();
        if (selectedText.includes('annual') || selectedText.includes('normal')) {
            loadStaffAnnualSlots(currentStaffId);
        } else {
            $('#preScheduledBlockContainer').slideUp();
            $('#scheduled_slot_select').empty().append('<option value="">-- Choose from your scheduled plan --</option>');
        }
    });

    function loadStaffAnnualSlots(staffId) {
        if (!staffId) return;
        const currentYear = new Date().getFullYear();
        $.ajax({
            url: "{{ url('/ajax/leave-management/staff-annual-slots') }}",
            type: 'GET',
            data: { staff_id: staffId, year: currentYear },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.slots && res.slots.length > 0) {
                    let options = '<option value="">-- Choose from your scheduled plan --</option>';
                    res.slots.forEach(function (slot) {
                        const disabledAttr = slot.is_disabled ? 'disabled style="color:#9ca3af;"' : '';
                        options += '<option value="' + slot.id + '" data-start="' + slot.start_date + '" data-end="' + slot.end_date + '" data-days="' + slot.days + '" ' + disabledAttr + '>' + slot.display + '</option>';
                    });
                    $('#scheduled_slot_select').html(options);
                    $('#preScheduledBlockContainer').slideDown();
                } else {
                    $('#preScheduledBlockContainer').slideUp();
                }
            },
            error: function () {
                $('#preScheduledBlockContainer').slideUp();
            }
        });
    }

    $('#scheduled_slot_select').on('change', function () {
        const selectedOption = $(this).find('option:selected');
        const startDate = selectedOption.data('start');
        const endDate   = selectedOption.data('end');
        const days      = selectedOption.data('days');

        if (startDate) {
            $('input[name="start_date"], #start_date, #sl_start_date').val(startDate).trigger('change');
            $('input[name="end_date"], #end_date').val(endDate).trigger('change');
            $('input[name="requested_days"], #requested_days, #sl_requested_days').val(days).trigger('change');
            $('input[name="requested_days"], #requested_days, #sl_requested_days').prop('readonly', true);
        } else {
            $('input[name="requested_days"], #requested_days, #sl_requested_days').prop('readonly', false);
        }
    });

    $('#newLeaveModal, #leaveApplicationModal').on('hidden.bs.modal', function () {
        $('#preScheduledBlockContainer').hide();
        $('#scheduled_slot_select').val('');
        $('input[name="requested_days"], #requested_days, #sl_requested_days').prop('readonly', false);
    });
});

$(document).ready(function() {
    let currentTotalDays = 0;
    let requiredDays = 0;

    $(document).on('click', '.open-schedule-modal', function() {
        let planId = $(this).data('plan-id');
        let leaveTypeId = $(this).data('leave-type-id');
        let empName = $(this).data('employee-name');
        requiredDays = Math.floor(parseFloat($(this).data('entitled-days')) * 2) / 2;
        if (isNaN(requiredDays)) { requiredDays = 0; }

        $('#modalTargetDays').text(requiredDays + ' Days');
        $('#saveRequiredDays').text(requiredDays);
        $('#modalPlanId').val(planId);
        $('#modalLeaveTypeId').val(leaveTypeId);
        $('#modalEmployeeName').text(empName);
        $('#slotsContainer').empty();

        $.ajax({
            url: "{{ url('/ajax/leave-management/plan-slots') }}/" + planId,
            type: 'GET',
            success: function(res) {
                if (res.success && res.slots.length > 0) {
                    res.slots.forEach(slot => addSlotRow(slot.start_date, slot.end_date, slot.days_count));
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

    function addSlotRow(startVal, endVal, daysVal) {
        startVal = startVal || '';
        endVal = endVal || '';
        daysVal = daysVal || 0;
        let idx = $('#slotsContainer .slot-row').length;
        let html = '<div class="slot-row border rounded p-2 mb-2 bg-white">' +
            '<div class="row g-2 align-items-center">' +
            '<div class="col-md-4"><label class="form-label small mb-1">Start Date</label>' +
            '<input type="date" class="form-control form-control-sm slot-start" name="slots[' + idx + '][start_date]" value="' + startVal + '" required></div>' +
            '<div class="col-md-4"><label class="form-label small mb-1">End Date</label>' +
            '<input type="date" class="form-control form-control-sm slot-end" name="slots[' + idx + '][end_date]" value="' + endVal + '" required></div>' +
            '<div class="col-md-3"><label class="form-label small mb-1">Working Days</label>' +
            '<input type="text" class="form-control form-control-sm slot-days bg-light" readonly value="' + daysVal + '"></div>' +
            '<div class="col-md-1 text-end pt-3">' +
            '<button type="button" class="btn btn-sm btn-outline-danger remove-slot-btn" style="padding: 2px 6px;">&times;</button></div>' +
            '</div></div>';
        $('#slotsContainer').append(html);
    }

    $('#addSlotRowBtn').on('click', function() { addSlotRow(); });

    $(document).on('click', '.remove-slot-btn', function() {
        if ($('#slotsContainer .slot-row').length > 1) {
            $(this).closest('.slot-row').remove();
            updateRunningTotal();
        } else {
            toastr.warning('At least one block is required.');
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
        let remaining = requiredDays - total;
        $('#allocatedDaysBadge').text(total + ' Days');
        if (Math.abs(remaining) < 0.001) {
            $('#remainingDaysBadge').removeClass('bg-warning text-dark bg-danger').addClass('bg-success text-white').text('0 Days (Perfect!)');
            $('#saveScheduleBtn').prop('disabled', false);
        } else if (remaining < 0) {
            $('#remainingDaysBadge').removeClass('bg-warning bg-success').addClass('bg-danger text-white').text(Math.abs(remaining) + ' Days Exceeded');
            $('#saveScheduleBtn').prop('disabled', true);
        } else {
            $('#remainingDaysBadge').removeClass('bg-success bg-danger text-white').addClass('bg-warning text-dark').text(remaining + ' Days Remaining');
            $('#saveScheduleBtn').prop('disabled', true);
        }
    }

    $('#saveScheduleBtn').on('click', function() {
        if (Math.abs(currentTotalDays - requiredDays) > 0.001) {
            toastr.error('You must allocate exactly ' + requiredDays + ' working days.');
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
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    toastr.error(res.error || 'Failed to save');
                }
            },
            error: function(xhr) {
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to save schedule');
            }
        });
    });
});
</script>

<script>
$(function () {
    var staffId = {{ $staffId }};

    var leaveTable = $('#myLeaveTable').DataTable({
        processing: true,
        ajax: {
            url: "{{ route('ajax.leaveApplications.list') }}",
            dataSrc: function (json) {
                return (json.leave_applications || []).filter(function (a) {
                    return a.staff_id === staffId;
                });
            }
        },
       columns: [
    { data: null, orderable: false, render: function (d, t, r, m) { return m.row + 1; } },
    { data: 'leave_type', render: function (d) { return d ? d.type_name : '-'; } },
    { data: 'start_date', render: function (d) { return d ? d.substring(0, 10) : '-'; } },
    { data: 'end_date',   render: function (d) { return d ? d.substring(0, 10) : '-'; } },
    { data: 'display_days' },
    {
        data: 'current_status_id',
        render: function (s, type, row) {
            s = parseInt(s);

            var map = {
                // Newer workflow
                1:  row.waiting_on === 'hr' 
                        ? '<span class="badge bg-info">Pending HR</span>' 
                        : '<span class="badge bg-secondary">Pending Supervisor</span>',
                24: '<span class="badge bg-info">Pending HR</span>',
                5:  '<span class="badge bg-success">Approved</span>',
                41: '<span class="badge bg-danger">Rejected</span>',

                // Older / current workflow
                2:  '<span class="badge bg-secondary">Pending Approval</span>',
                3:  '<span class="badge bg-success">Approved</span>'
            };

            if (row.recalled_at) {
                var effectiveDate = row.recall_effective_date ? row.recall_effective_date.substring(0, 10) : 'Not specified';
                var reason = $('<div>').text(row.recall_reason || 'No reason provided').html();
                var refundedDays = Number(row.recalled_days || 0).toFixed(2);
                return '<span class="badge bg-dark">Recalled</span>' +
                    '<div class="small mt-1"><strong>Return date:</strong> ' + effectiveDate + '</div>' +
                    '<div class="small"><strong>Days returned to balance:</strong> ' + refundedDays + '</div>' +
                    '<div class="small text-muted">' + reason + '</div>';
            }

            return map[s] || '<span class="badge bg-light text-dark">' + s + '</span>';
        }
    },
    {
        data: null,
        orderable: false,
        render: function (data, type, row) {
            var status = parseInt(row.current_status_id);

            // Enable Leave Pass when status is Approved (3 or 5)
            if ((status === 3 || status === 5) && !row.recalled_at) {
                var url = "{{ url('/hr/leave-management/leave-pass') }}/" + row.encrypted_leave_application_id;
                return '<a href="' + url + '" target="_blank" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center" title="Download Leave Pass">' +
                    '<span class="material-symbols-outlined" style="font-size: 16px;">download</span>' +
                    '<span class="ms-1">Leave Pass</span>' +
                    '</a>';
            }

            return '<button type="button" class="btn btn-light btn-sm text-muted d-inline-flex align-items-center" disabled title="Available once approved">' +
                '<span class="material-symbols-outlined" style="font-size: 16px;">lock</span>' +
                '<span class="ms-1">Leave Pass</span>' +
                '</button>';
        }
    }
],
        order: [[2, 'desc']],
        pageLength: 25
    });

    $.get("{{ route('ajax.leaveTypes.list') }}", function (res) {
        if (res.success) {
            var opts = '<option value="">Select leave type...</option>';
            res.leave_types.forEach(function (t) {
                opts += '<option value="' + t.id + '">' + t.type_name + ' (' + t.entitled_days + ' days)</option>';
            });
            $('#sl_leave_type_id').html(opts);
        }
    });

    $('input[name="leave_duration_type"]').on('change', function () {
        if ($(this).val() === 'full_day') {
            $('#sl_full_days_section').show();
            $('#sl_hours_section').hide();
        } else {
            $('#sl_full_days_section').hide();
            $('#sl_hours_section').show();
        }
    });

    var tierRequest = null;
    function showTierBreakdown() {
        var leaveTypeId = $('#sl_leave_type_id').val();
        $('#sl_tier_alert').addClass('d-none');
        if (!leaveTypeId || $('input[name="leave_duration_type"]:checked').val() !== 'full_day') { return; }

        if (tierRequest) { tierRequest.abort(); }
        tierRequest = $.ajax({
            url: "{{ url('/ajax/leave-management/evaluate-application') }}",
            method: 'POST',
            data: {
                staff_id: staffId,
                leave_type_id: leaveTypeId,
                start_date: $('#sl_start_date').val() || new Date().toISOString().split('T')[0],
                requested_days: parseFloat($('#sl_requested_days').val()) || 1,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function (res) {
                if (!res.success) { return; }
                var requested = parseFloat($('#sl_requested_days').val()) || 1;
                var changed = Math.abs(res.charged_days - requested) > 0.001;
                if (res.sick_breakdown || changed) {
                    var b = res.sick_breakdown;
                    var tiers = b ? 'Pay breakdown: ' + b.summary_text + ' (days already used this year: ' + b.used_this_year + '). ' : '';
                    $('#sl_tier_text').text(tiers + 'Leave runs to ' + res.end_date + ', ' + res.charged_days + ' day(s) charged' + (changed ? ' (a Saturday counts as half a day)' : '') + '.');
                    $('#sl_tier_alert').removeClass('d-none');
                }
            },
            error: function (xhr, status) {
                if (status === 'abort') { return; }
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Could not load the pay breakdown');
            }
        });
    }
    $('#sl_leave_type_id, #sl_start_date, #sl_requested_days, input[name="leave_duration_type"]').on('change input', showTierBreakdown);

    $('#sl_leave_type_id').on('change', function () {
        var leaveTypeId = $(this).val();
        if (!leaveTypeId) { $('#sl_available_days').val(''); return; }
        $.get('/ajax/hr/leave/available-days/' + staffId + '/' + leaveTypeId, function (res) {
            if (res.success === false && res.error) {
                $('#sl_available_days').val('');
                toastr.warning(res.error);
                return;
            }
            $('#sl_available_days').val(res.available_days ?? 0);
        });
    });

    $('#leaveSubmitBtn').on('click', function () {
        var durationType = $('input[name="leave_duration_type"]:checked').val();
        var data = {
            staff_id: staffId,
            leave_type_id: $('#sl_leave_type_id').val(),
            available_days: $('#sl_available_days').val() || 0,
            leave_duration_type: durationType,
            _token: $('meta[name="csrf-token"]').attr('content')
        };

        if (!data.leave_type_id) { toastr.warning('Please select a leave type.'); return; }

        if (durationType === 'full_day') {
            data.start_date     = $('#sl_start_date').val();
            data.requested_days = $('#sl_requested_days').val();
            if (!data.start_date || !data.requested_days) {
                toastr.warning('Please fill in start date and number of days.');
                return;
            }
        } else {
            var date = $('#sl_leave_date_hours').val();
            if (!date) { toastr.warning('Please fill in the date.'); return; }
            data.start_date = date + ' ' + $('#sl_start_time_only').val() + ':00';
            data.end_date   = date + ' ' + $('#sl_end_time_only').val() + ':00';
        }

        $('#leaveSpinner').removeClass('d-none');
        $('#leaveSubmitBtn').prop('disabled', true);

        $.ajax({
            url: "{{ route('ajax.leaveApplications.store') }}",
            method: 'POST',
            data: data,
            success: function (res) {
                if (res.success) {
                    $('#leaveApplicationModal').modal('hide');
                    toastr.success('Leave application submitted successfully.');
                    leaveTable.ajax.reload(null, false);
                } else {
                    toastr.error(res.error || 'Failed to submit application.');
                }
            },
            error: function (xhr) {
                var errs = xhr.responseJSON && xhr.responseJSON.errors;
                if (errs) {
                    toastr.error(Object.values(errs).flat().join('<br>'));
                } else {
                    toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to submit application.');
                }
            },
            complete: function () {
                $('#leaveSpinner').addClass('d-none');
                $('#leaveSubmitBtn').prop('disabled', false);
            }
        });
    });

    $('#leaveApplicationModal').on('hidden.bs.modal', function () {
        $('#leaveForm')[0].reset();
        $('#sl_available_days').val('');
        $('#sl_full_days_section').show();
        $('#sl_hours_section').hide();
    });
});
</script>
@endif
@endsection
