@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Workdays Settings</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_workdays')
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addWorkdayModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Workday</span>
                            </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="workdays-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Week Day</th>
                                        <th>Work Type</th>
                                        <th>Current Status</th>
                                        <th>Created By</th>
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
    </div>
</div>
<!-- Add/Edit Workday Modal -->
<div class="modal fade" id="addWorkdayModal" tabindex="-1" aria-labelledby="addWorkdayModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addWorkdayModalLabel">Workday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="workdayForm">
                    <input type="hidden" name="id" id="workdayId">
                    <div class="mb-3">
                        <label for="week_day" class="form-label">Week Day <span style="color:red">*</span></label>
                        <select class="form-select" name="week_day" id="week_day" required>
                            <option value="" disabled selected>Select week day</option>
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                            <option value="6">Saturday</option>
                            <option value="7">Sunday</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="work_type_id" class="form-label">Work Type <span style="color:red">*</span></label>
                        <select class="form-select" name="work_type_id" id="work_type_id" required>
                            <option value="" disabled selected>Select work type</option>
                            <option value="1">Full Day</option>
                            <option value="2">Half Day</option>
                            <option value="3">Non-Working Day</option>
                        </select>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        @permission('create_workdays')
                        <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center" id="saveWorkdayBtn">
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
@endsection
<script>
    // use toastr for notifications
    $(document).ready(function() {
        let table = $('#workdays-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/hr/workdays/list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'workdays'
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'week_day',
                    render: function(data) {
                        // Map week_day number to name
                        const days = {
                            "1": "Monday",
                            "2": "Tuesday",
                            "3": "Wednesday",
                            "4": "Thursday",
                            "5": "Friday",
                            "6": "Saturday",
                            "7": "Sunday"
                        };
                        return days[data] || data;
                    }
                },
                {
                    data: 'work_type_id',
                    render: function(data) {
                        // Map work_type_id to type name
                        const types = {
                            "1": "Full Day",
                            "2": "Half Day",
                            "3": "Non-Working Day"
                        };
                        return types[data] || data;
                    }
                },
                {
                    data: 'current_status_id',
                    render: function(data, type, row) {
                        // Map status id to name
                        const status = {
                            "1": "Inactive",
                            "5": "Active"
                        };
                        return status[row.current_status_id] || row.current_status_id;
                    }
                },
                {
                    data: 'created_by',
                    render: function(data) {
                        // Use created_by.name if available, otherwise show '-'
                        return data && data.name ? data.name : '-';
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        if (row.can_update) {
                            buttons += `
                                <button class="btn btn-xs btn-light edit-workday" 
                                    data-id="${row.id}" 
                                    data-week_day="${row.week_day}" 
                                    data-work_type_id="${row.work_type_id}" 
                                    style="padding:1px 2px;">
                                    <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                                </button>`;
                        }
                        if (row.can_delete) {
                            buttons += `
                                <button class="btn btn-xs btn-light delete-workday" 
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

        $('#workdayForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#workdayId').val();
            var url = id ? "{{ url('/ajax/hr/workdays') }}/" + id : "{{ url('/ajax/hr/workdays') }}";
            var method = 'POST';
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveWorkdayBtn');
            var $icon = $btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();

            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Processing...');
            $btn.prop('disabled', true);

            $.ajax({
                url: url,
                type: method,
                data: formData,
                success: function(res) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);

                    if (res.success) {
                        toastr.success('Saved successfully');
                        $('#addWorkdayModal').modal('hide');
                        $('#workdayForm')[0].reset();
                        $('#workdayId').val('');
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

        $(document).on('click', '.edit-workday', function() {
            $('#workdayId').val($(this).data('id'));
            $('#week_day').val($(this).data('week_day'));
            $('#work_type_id').val($(this).data('work_type_id'));
            $('#addWorkdayModal').modal('show');
        });

        $(document).on('click', '.delete-workday', function() {
            var $btn = $(this);
            var $icon = $btn.find('.material-symbols-outlined').first();

            if (!confirm('Are you sure you want to delete this workday?')) {
                return;
            }

            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);

            $.ajax({
                url: "{{ url('/ajax/hr/workdays') }}/" + $btn.data('id'),
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

        $('#addWorkdayModal').on('hidden.bs.modal', function() {
            $('#workdayForm')[0].reset();
            $('#workdayId').val('');
        });
    });
</script>
