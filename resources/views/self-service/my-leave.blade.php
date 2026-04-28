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
                        {{-- ── Not linked ─────────────────────────────────────────────── --}}
                        <div class="alert alert-warning d-flex align-items-center gap-2">
                            <span class="material-symbols-outlined">warning</span>
                            <div>
                                Your user account is not linked to a staff record.
                                Please contact HR to have your account linked.
                            </div>
                        </div>

                        @else
                        {{-- ── Leave table ──────────────────────────────────────────── --}}
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
{{-- ── Leave Application Modal ────────────────────────────────────────── --}}
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
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label class="form-label">Available Days</label>
                            <input type="number" class="form-control" id="sl_available_days" readonly>
                        </div>
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

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
$(function () {
    var staffId = {{ $staffId }};

    // ── DataTable ────────────────────────────────────────────────────────────
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
                render: function (s) {
                    var map = {
                        1:  '<span class="badge bg-secondary">Pending</span>',
                        5:  '<span class="badge bg-success">Approved</span>',
                        28: '<span class="badge bg-danger">Rejected</span>'
                    };
                    return map[s] || '<span class="badge bg-light text-dark">' + s + '</span>';
                }
            }
        ],
        order: [[2, 'desc']],
        pageLength: 25
    });

    // ── Load leave types ─────────────────────────────────────────────────────
    $.get("{{ route('ajax.leaveTypes.list') }}", function (res) {
        if (res.success) {
            var opts = '<option value="">Select leave type...</option>';
            res.leave_types.forEach(function (t) {
                opts += '<option value="' + t.id + '">' + t.type_name + ' (' + t.entitled_days + ' days)</option>';
            });
            $('#sl_leave_type_id').html(opts);
        }
    });

    // ── Duration toggle ──────────────────────────────────────────────────────
    $('input[name="leave_duration_type"]').on('change', function () {
        if ($(this).val() === 'full_day') {
            $('#sl_full_days_section').show();
            $('#sl_hours_section').hide();
        } else {
            $('#sl_full_days_section').hide();
            $('#sl_hours_section').show();
        }
    });

    // ── Available days ───────────────────────────────────────────────────────
    $('#sl_leave_type_id').on('change', function () {
        var leaveTypeId = $(this).val();
        if (!leaveTypeId) { $('#sl_available_days').val(''); return; }
        $.get('/ajax/leave-management/calendar-plan/available-leave-days/' + staffId + '/' + leaveTypeId, function (res) {
            $('#sl_available_days').val(res.available_days ?? 0);
        });
    });

    // ── Submit ────────────────────────────────────────────────────────────────
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

    // ── Reset modal on close ──────────────────────────────────────────────────
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
