@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">HR Leave Types Settings</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_leave_types')
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addLeaveTypeModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Leave Type</span>
                            </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="leave-type-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Type Name</th>
                                        <th>Entitled Days</th>
                                        <th>Salary Rate (%)</th>
                                        <th>Counts Weekends</th>
                                        <th>Counts Holidays</th>
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

<!-- Add/Edit Leave Type Modal -->
<div class="modal fade" id="addLeaveTypeModal" tabindex="-1" aria-labelledby="addLeaveTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addLeaveTypeModalLabel">Add Leave Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="leaveTypeForm">
                    <input type="hidden" name="id" id="leaveTypeId">
                    <div class="mb-3">
                        <label for="type_name" class="form-label">Type Name <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="type_name" id="type_name" required placeholder="Enter leave type name">
                    </div>
                    <div class="mb-3">
                        <label for="entitled_days" class="form-label">Entitled Days <span style="color:red">*</span></label>
                        <input type="number" class="form-control" name="entitled_days" id="entitled_days" required min="0" placeholder="Enter entitled days">
                    </div>
                    <div class="mb-3">
                        <label for="salary_rate" class="form-label">Salary Rate (%) <span style="color:red">*</span></label>
                        <input type="number" class="form-control" name="salary_rate" id="salary_rate" required min="0" max="100" step="0.01" placeholder="Enter salary rate percentage">
                    </div>

                  <!-- Day Counting Rules -->
                    <div class="p-3 mb-3 rounded" style="background-color: #f8f9fa; border: 1px solid #e9ecef;">
                        <label class="form-label fw-bold text-dark mb-2">Day Counting Rules</label>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="counts_weekends" id="counts_weekends" value="1" style="cursor: pointer;">
                            <label class="form-check-label fw-semibold text-dark" for="counts_weekends" style="cursor: pointer;">
                                Count Weekends as Leave Days
                            </label>
                            <small class="d-block text-muted" style="font-size: 12px; margin-top: 2px;">
                                Turn <strong>ON</strong> for Maternity/Paternity; leave <strong>OFF</strong> for Annual Leave (excludes Sat/Sun).
                            </small>
                        </div>

                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="counts_public_holidays" id="counts_public_holidays" value="1" style="cursor: pointer;">
                            <label class="form-check-label fw-semibold text-dark" for="counts_public_holidays" style="cursor: pointer;">
                                Count Public Holidays as Leave Days
                            </label>
                            <small class="d-block text-muted" style="font-size: 12px; margin-top: 2px;">
                                Turn <strong>ON</strong> for Maternity/Paternity; leave <strong>OFF</strong> for Annual Leave (excludes gazetted holidays).
                            </small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        @permission('create_leave_types')
                        <button type="submit" class="btn btn-primary d-flex align-items-center" id="saveLeaveTypeBtn">
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

<script>
    $(document).ready(function() {
        let table = $('#leave-type-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/hr/leave-type/list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'leave_types'
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
                    data: 'type_name'
                },
                {
                    data: 'entitled_days'
                },
                {
                    data: 'salary_rate',
                    render: function(data) {
                        return parseFloat(data).toFixed(2);
                    }
                },
                {
                    data: 'counts_weekends',
                    render: function(data) {
                        return data ? '<span class="badge bg-warning text-dark">Includes Weekends</span>' 
                                    : '<span class="badge bg-light text-muted border">Excludes Weekends</span>';
                    }
                },
                {
                    data: 'counts_public_holidays',
                    render: function(data) {
                        return data ? '<span class="badge bg-warning text-dark">Includes Holidays</span>' 
                                    : '<span class="badge bg-light text-muted border">Excludes Holidays</span>';
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
                                <button class="btn btn-xs btn-light edit-leave-type" 
                                    data-id="${row.id}" 
                                    data-type_name="${row.type_name}" 
                                    data-current_status_id="${row.current_status_id}" 
                                    data-entitled_days="${row.entitled_days}" 
                                    data-salary_rate="${row.salary_rate}" 
                                    data-leave_type_id="${row.leave_type_id || ''}" 
                                    data-counts_weekends="${row.counts_weekends ? 1 : 0}" 
                                    data-counts_public_holidays="${row.counts_public_holidays ? 1 : 0}" 
                                    style="padding:1px 2px;">
                                    <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                                </button>`;
                        }

                        if (row.can_delete) {
                            buttons += `
                                <button class="btn btn-xs btn-light delete-leave-type" 
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

        $('#leaveTypeForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#leaveTypeId').val();
            var url = id ? "{{ url('/ajax/hr/leave-type') }}/" + id : "{{ route('ajax.hr.leave_type.store') }}";
            var method = 'POST';

            // Ensure unchecked checkboxes send 0
            var formData = $(this).serializeArray();
            if (!formData.some(item => item.name === 'counts_weekends')) {
                formData.push({ name: 'counts_weekends', value: 0 });
            }
            if (!formData.some(item => item.name === 'counts_public_holidays')) {
                formData.push({ name: 'counts_public_holidays', value: 0 });
            }
            formData.push({ name: '_token', value: '{{ csrf_token() }}' });

            var $btn =$('#saveLeaveTypeBtn');
            var $icon =$btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();

            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Processing...');$btn.prop('disabled', true);

            $.ajax({
                url: url,
                type: method,
                data: $.param(formData),
                success: function(res) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);$btn.prop('disabled', false);

                    if (res.success) {
                        toastr.success('Saved successfully');
                        $('#addLeaveTypeModal').modal('hide');
                        $('#leaveTypeForm')[0].reset();
                        $('#leaveTypeId').val('');
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error saving');
                    }
                },
                error: function(xhr) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);$btn.prop('disabled', false);

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

        $(document).on('click', '.edit-leave-type', function() {
            $('#leaveTypeId').val($(this).data('id'));
            $('#type_name').val($(this).data('type_name'));
            $('#current_status_id').val($(this).data('current_status_id'));
            $('#entitled_days').val($(this).data('entitled_days'));
            $('#salary_rate').val($(this).data('salary_rate'));
            $('#leave_type_id').val($(this).data('leave_type_id'));
            
            // Checkbox state setup
            $('#counts_weekends').prop('checked', $(this).data('counts_weekends') == 1);
            $('#counts_public_holidays').prop('checked', $(this).data('counts_public_holidays') == 1);

            $('#addLeaveTypeModal').modal('show');
        });

        $(document).on('click', '.delete-leave-type', function() {
            var $btn =$(this);
            var $icon =$btn.find('.material-symbols-outlined').first();

            if (!confirm('Are you sure you want to delete this leave type?')) {
                return;
            }

            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();$btn.prop('disabled', true);

            $.ajax({
                url: "{{ url('/ajax/hr/leave-type') }}/" + $btn.data('id'),
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();$btn.prop('disabled', false);

                    if (res.success) {
                        toastr.success('Deleted');
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error deleting');
                    }
                },
                error: function(xhr) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();$btn.prop('disabled', false);

                    let msg = 'Delete error';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });

        $('#addLeaveTypeModal').on('hidden.bs.modal', function() {
            $('#leaveTypeForm')[0].reset();
            $('#leaveTypeId').val('');
            $('#counts_weekends').prop('checked', false);
            $('#counts_public_holidays').prop('checked', false);
        });
    });
</script>
@endsection