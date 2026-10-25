@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Staff Positions</h4>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_staff_positions')
                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="addPositionBtn">
                                <span class="material-symbols-outlined" style="font-size:18px;">add_circle</span>
                                <span class="ms-1">Add Position</span>
                            </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="positions-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Position Name</th>
                                        <th>Department</th>
                                        <th>Slug</th>
                                        <th>Basic Salary</th>
                                        <th>Reports To</th>
                                        <th>Staff Count</th>
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

<!-- Add Position Modal -->
<div class="modal fade" id="addPositionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Position</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addPositionForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add_department_id" class="form-label">Department <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_department_id" name="department_id" required>
                            <option value="">— Select Department —</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="add_position_name" class="form-label">Position Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_position_name" name="position_name" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="add_slug_name" class="form-label">Slug <span class="text-muted small">(auto-generated)</span></label>
                        <input type="text" class="form-control" id="add_slug_name" readonly tabindex="-1">
                    </div>
                    <div class="mb-3">
                        <label for="add_basic_salary" class="form-label">Basic Salary</label>
                        <input type="number" step="0.01" class="form-control" id="add_basic_salary" name="basic_salary">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="add_reports_to_id" class="form-label">Reports To</label>
                        <select class="form-select" id="add_reports_to_id" name="reports_to_id">
                            <option value="">— None (Top Level) —</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined" style="font-size:18px;">cancel</span>
                        <span class="ms-1">Cancel</span>
                    </button>
                    <button type="submit" class="btn btn-primary d-flex align-items-center" id="addPositionSaveBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="addSaveSpinner"></span>
                        <span class="material-symbols-outlined" style="font-size:18px;" id="addSaveIcon">save</span>
                        <span class="ms-1" id="addSaveBtnText">Save</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Position Modal -->
<div class="modal fade" id="editPositionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Position</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editPositionForm">
                <div class="modal-body">
                    <input type="hidden" id="edit_position_id" name="position_id">
                    <div class="mb-3">
                        <label for="edit_department_id" class="form-label">Department <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_department_id" name="department_id" required>
                            <option value="">— Select Department —</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_position_name" class="form-label">Position Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_position_name" name="position_name" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_slug_name" class="form-label">Slug <span class="text-muted small">(auto-generated)</span></label>
                        <input type="text" class="form-control" id="edit_slug_name" readonly tabindex="-1">
                    </div>
                    <div class="mb-3">
                        <label for="edit_basic_salary" class="form-label">Basic Salary</label>
                        <input type="number" step="0.01" class="form-control" id="edit_basic_salary" name="basic_salary">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_reports_to_id" class="form-label">Reports To</label>
                        <select class="form-select" id="edit_reports_to_id" name="reports_to_id">
                            <option value="">— None (Top Level) —</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined" style="font-size:18px;">cancel</span>
                        <span class="ms-1">Cancel</span>
                    </button>
                    <button type="submit" class="btn btn-primary d-flex align-items-center" id="editPositionSaveBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="editSaveSpinner"></span>
                        <span class="material-symbols-outlined" style="font-size:18px;" id="editSaveIcon">save</span>
                        <span class="ms-1" id="editSaveBtnText">Update</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    var positionsTable;

    $(document).ready(function() {
        loadPositionsTable();

        // Add position button
        $('#addPositionBtn').on('click', function(e) {
            e.preventDefault();
            resetAddForm();
            loadDropdowns();
            $('#addPositionModal').modal('show');
        });

        // Edit position
        $(document).on('click', '.edit-position', function(e) {
            e.preventDefault();
            var positionId = $(this).data('id');
            loadPositionData(positionId);
        });

        // Add form submission
        $('#addPositionForm').on('submit', function(e) {
            e.preventDefault();
            saveAddPosition();
        });

        // Edit form submission
        $('#editPositionForm').on('submit', function(e) {
            e.preventDefault();
            saveEditPosition();
        });

        // Live slug preview
        $('#add_position_name').on('input', function() {
            $('#add_slug_name').val(slugify($(this).val()));
        });
        $('#edit_position_name').on('input', function() {
            $('#edit_slug_name').val(slugify($(this).val()));
        });

        // Delete position
        $(document).on('click', '.delete-position', function(e) {
            e.preventDefault();
            var positionId = $(this).data('id');
            var positionName = $(this).data('name');
            deletePosition(positionId, positionName);
        });
    });

    function slugify(text) {
        return (text || '')
            .toString()
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    function loadPositionsTable() {
        positionsTable = $('#positions-table').DataTable({
            ajax: {
                url: '/ajax/hr/positions/list',
                dataSrc: 'positions'
            },
            columns: [
                {
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'position_name',
                    render: function(data, type, row) {
                        if (row.can_read) {
                            return '<a href="/hrsettings/positions/' + row.encrypted_id + '">' + data + '</a>';
                        }
                        return data;
                    }
                },
                {
                    data: 'department_name',
                    defaultContent: '-'
                },
                {
                    data: 'slug_name',
                    defaultContent: '-'
                },
                {
                    data: 'basic_salary',
                    render: function(data) {
                        return data ? parseFloat(data).toLocaleString('en-US', {
                            minimumFractionDigits: 2
                        }) : '-';
                    }
                },
                {
                    data: 'reports_to_name',
                    defaultContent: '-'
                },
                {
                    data: 'staff_count',
                    defaultContent: '0'
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        var actions = '<div class="d-flex flex-row gap-1">';
                        if (row.can_read) {
                            actions += '<a href="/hrsettings/positions/' + row.encrypted_id + '" title="View"><span class="material-symbols-outlined" style="font-size:18px;">visibility</span></a>';
                        }
                        if (row.can_update) {
                            actions += '<a href="#" class="edit-position" data-id="' + row.id + '" title="Edit"><span class="material-symbols-outlined" style="font-size:18px;">edit_document</span></a>';
                        }
                        if (row.can_delete) {
                            actions += '<a href="#" class="delete-position text-primary" data-id="' + row.id + '" data-name="' + row.position_name + '" title="Delete"><span class="material-symbols-outlined" style="font-size:18px;">delete</span></a>';
                        }
                        actions += '</div>';
                        return actions;
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            order: [[1, 'asc']]
        });
    }

    // Load Reports To and Departments dropdowns
    function loadDropdowns(currentId = null, selectedReportsTo = null, selectedDept = null) {
        $.get('/ajax/hr/positions/dropdowns', { current_id: currentId }, function(res) {
            var reportsOptions = '<option value="">— None (Top Level) —</option>';
            (res.positions || []).forEach(function(pos) {
                var selected = (selectedReportsTo && selectedReportsTo == pos.id) ? 'selected' : '';
                reportsOptions += `<option value="${pos.id}" ${selected}>${pos.position_name}</option>`;
            });

            var deptOptions = '<option value="">— Select Department —</option>';
            (res.departments || []).forEach(function(dept) {
                var selected = (selectedDept && selectedDept == dept.id) ? 'selected' : '';
                deptOptions += `<option value="${dept.id}" ${selected}>${dept.department_name}</option>`;
            });

            if (currentId) {
                $('#edit_reports_to_id').html(reportsOptions);
                $('#edit_department_id').html(deptOptions);
                // Update stays locked until the current Reports To value is loaded, so an early save cannot wipe it.
                $('#editPositionSaveBtn').prop('disabled', false);
            } else {
                $('#add_reports_to_id').html(reportsOptions);
                $('#add_department_id').html(deptOptions);
            }
        }).fail(function(xhr) {
            console.error('Dropdowns failed', xhr.responseJSON || xhr.statusText);
            toastr.error('Could not load dropdown options');
        });
    }

    function loadPositionData(positionId) {
        $.get('/ajax/hr/positions/get', { id: positionId }, function(data) {
            var p = data.position;

            $('#edit_position_id').val(p.id);
            $('#edit_position_name').val(p.position_name);
            $('#edit_slug_name').val(p.slug_name);
            $('#edit_basic_salary').val(p.basic_salary);

            $('#editPositionSaveBtn').prop('disabled', true);
            loadDropdowns(p.id, p.reports_to_id, p.department_id);
            $('#editPositionModal').modal('show');
        });
    }

    function deletePosition(positionId, positionName) {
        Swal.fire({
            title: 'Delete Position?',
            text: 'Are you sure you want to delete "' + positionName + '"? This cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3598db',
            cancelButtonColor: '#8a94a3',
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            Swal.fire({
                title: 'Deleting...',
                allowOutsideClick: false,
                didOpen: function() { Swal.showLoading(); }
            });

            $.ajax({
                url: '/ajax/hr/positions/' + positionId,
                method: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Deleted!',
                            text: response.message || 'Position deleted successfully',
                            icon: 'success',
                            confirmButtonColor: '#3598db'
                        });
                        positionsTable.ajax.reload();
                    } else {
                        Swal.fire({
                            title: 'Cannot Delete',
                            text: response.error || 'Failed to delete position',
                            icon: 'error',
                            confirmButtonColor: '#3598db'
                        });
                    }
                },
                error: function(xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'An error occurred while deleting';
                    Swal.fire({
                        title: 'Cannot Delete',
                        text: message,
                        icon: 'error',
                        confirmButtonColor: '#3598db'
                    });
                }
            });
        });
    }

    function saveAddPosition() {
        var $btn =$('#addPositionSaveBtn');
        var $spinner =$('#addSaveSpinner');
        var $icon =$('#addSaveIcon');
        var $btnText =$('#addSaveBtnText');

        $btn.prop('disabled', true);
        $icon.addClass('d-none');$spinner.removeClass('d-none');
        $btnText.text('Saving...');$('#addPositionForm .is-invalid').removeClass('is-invalid');
        $('#addPositionForm .invalid-feedback').text('');

        var formData = {
            department_id: $('#add_department_id').val(),
            position_name: $('#add_position_name').val(),
            basic_salary: $('#add_basic_salary').val() || null,
            reports_to_id: $('#add_reports_to_id').val() || null,
            _token: '{{ csrf_token() }}'
        };

        $.ajax({
            url: '/ajax/hr/positions',
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message || 'Position added successfully');
                    $('#addPositionModal').modal('hide');
                    positionsTable.ajax.reload();
                } else {
                    toastr.error(response.error || 'Failed to add position');
                }
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    var errors = xhr.responseJSON.errors || {};
                    $.each(errors, function(field, messages) {
                        var $field =$('#add_' + field);
                        $field.addClass('is-invalid');$field.next('.invalid-feedback').text(messages[0]);
                    });
                } else {
                    toastr.error('An error occurred while saving');
                }
            },
            complete: function() {
                $btn.prop('disabled', false);$spinner.addClass('d-none');
                $icon.removeClass('d-none');$btnText.text('Save');
            }
        });
    }

    function saveEditPosition() {
        var $btn =$('#editPositionSaveBtn');
        var $spinner =$('#editSaveSpinner');
        var $icon =$('#editSaveIcon');
        var $btnText =$('#editSaveBtnText');
        var positionId = $('#edit_position_id').val();

        $btn.prop('disabled', true);
        $icon.addClass('d-none');$spinner.removeClass('d-none');
        $btnText.text('Updating...');$('#editPositionForm .is-invalid').removeClass('is-invalid');
        $('#editPositionForm .invalid-feedback').text('');

        var formData = {
            department_id: $('#edit_department_id').val(),
            position_name: $('#edit_position_name').val(),
            basic_salary: $('#edit_basic_salary').val() || null,
            reports_to_id: $('#edit_reports_to_id').val() || null,
            _token: '{{ csrf_token() }}'
        };

        $.ajax({
            url: '/ajax/hr/positions/' + positionId,
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message || 'Position updated successfully');
                    $('#editPositionModal').modal('hide');
                    positionsTable.ajax.reload();
                } else {
                    toastr.error(response.error || 'Failed to update position');
                }
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    var errors = xhr.responseJSON.errors || {};
                    $.each(errors, function(field, messages) {
                        var $field =$('#edit_' + field);
                        $field.addClass('is-invalid');$field.next('.invalid-feedback').text(messages[0]);
                    });
                } else {
                    toastr.error('An error occurred while updating');
                }
            },
            complete: function() {
                $btn.prop('disabled', false);$spinner.addClass('d-none');
                $icon.removeClass('d-none');$btnText.text('Update');
            }
        });
    }

    function resetAddForm() {
        $('#addPositionForm')[0].reset();
        $('#addPositionForm .is-invalid').removeClass('is-invalid');
        $('#addPositionForm .invalid-feedback').text('');
        $('#add_slug_name').val('');
        $('#add_department_id').val('');
    }
</script>
@endsection