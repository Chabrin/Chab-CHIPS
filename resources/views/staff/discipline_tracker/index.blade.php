@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Staff Discipline Tracker</h4>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_discipline_records')
                                <a class="btn btn-primary btn-sm d-flex align-items-center" href="{{ route('discipline.tracker.create') }}">
                                    <span class="material-symbols-outlined">add_circle</span>
                                    <span class="ms-1">Add Discipline Record</span>
                                </a>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="discipline-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Staff</th>
                                        <th>Position</th>
                                        <th>Incident Date</th>
                                        <th>Incident</th>
                                        <th>Action Taken</th>
                                        <th>Status</th>
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


<script>
$(document).ready(function() {
    // DataTable
    let table = $('#discipline-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ route('ajax.discipline.tracker.list') }}",
            type: 'GET',
            dataType: 'json',
            dataSrc: 'records'
        },
        columns: [
            { data: null, render: (data, type, row, meta) => meta.row + 1 },
            { 
                data: 'staff', 
                title: 'Staff',
                render: function(data, type, row) {
                    if (data && data.name) {
                        if (row.can_read && data.encrypted_id) {
                            return `<a href="/hr/employees/${data.encrypted_id}" target="_blank">${data.name}</a>`;
                        }
                        return data.name;
                    }
                    return '-';
                }
            },
            { data: 'position', render: (data) => data ? data.position_name : '' },
            { data: 'incident_date_time' },
            { data: 'incident', render: (data) => data ? data.incident_name : '' },
            { data: 'action_taken', render: (data) => data ? data.action_name : '' },
            { 
                data: 'current_status_id', 
                title: 'Status',
                render: function(data) {
                    if (data == 1) {
                        return '<span class="badge bg-warning">Pending</span>';
                    } else if (data == 5) {
                        return '<span class="badge bg-success">Confirmed</span>';
                    }
                    return '-';
                }
            },
            { data: 'created_by', render: (data) => data ? data.name : '' },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(id, type, row) {
                    let buttons = '<div class="d-flex flex-row gap-1">';
                    // View button
                    if(row.can_read) {
                        buttons += `
                            <a  href="/hr/employees/${row.staff.encrypted_id}" title="View">
                                <span class="material-symbols-outlined view-icon" style="font-size:18px;">visibility</span>
                            </a>
                        `;
                    }
                    if (row.can_update) {
                        buttons += `
                            <a href="/hr/discipline/tracker/edit/${row.id}" title="Edit">
                                <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                            </a>
                        `;
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

    $('#disciplineModal').on('show.bs.modal', function () {
        // Staff
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

                // Check if Select2 is already initialized and destroy it
                if ($staff_id.hasClass("select2-hidden-accessible")) {
                    $staff_id.select2('destroy');
                }
                
                // Initialize Select2
                $staff_id.select2({
                    dropdownParent: $('#addLeaveApplicationModal'), // Make sure this modal exists
                    placeholder: 'Select Staff',
                    allowClear: true
                });
            },
            error: function() {
                toastr.error('Failed to load staff.');
            }
        });

        $.ajax({
            url: "{{ url('/ajax/payroll/elements/staff_positions/list') }}",
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                let $staff_positions = $('#staff_positions');
                $staff_positions.empty();
                $staff_positions.append($('<option>', {
                    value: '',
                    text: 'Select staff positions',
                    disabled: true,
                    selected: true
                }));
                if (response.staff_positions && Array.isArray(response.staff_positions)) {
                    response.staff_positions.forEach(function(position) {
                        $staff_positions.append($('<option>', {
                            value: position.id,
                            text: position.position_name
                        }));
                    });
                }

                // Check if Select2 is already initialized and destroy it
                if ($staff_positions.hasClass("select2-hidden-accessible")) {
                    $staff_positions.select2('destroy');
                }
                
                // Initialize Select2
                $staff_positions.select2({
                    placeholder: 'Select Position',
                    allowClear: true
                });
            },
            error: function() {
                toastr.error('Failed to load staff positions.');
            }
        });
    });

    // Save (add/update)
    $('#disciplineForm').on('submit', function(e) {
        e.preventDefault();
        var id = $('#disciplineId').val();
        var url = id
            ? "{{ url('/ajax/hr/discipline/tracker') }}/" + id
            : "{{ url('/ajax/hr/discipline/tracker') }}";
        var method = 'POST';
        var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
        var $btn = $('#saveDisciplineBtn');
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
                    $('#disciplineModal').modal('hide');
                    $('#disciplineForm')[0].reset();
                    $('#disciplineId').val('');
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

    // Edit
    $(document).on('click', '.edit-discipline', function() {
        $('#disciplineId').val($(this).data('id'));
        $('#staff_id').val($(this).data('staff_id')).trigger('change');
        $('#position_id').val($(this).data('position_id')).trigger('change');
        $('#incident_date_time').val($(this).data('incident_date_time'));
        $('#incident_id').val($(this).data('incident_id'));
        $('#action_taken_id').val($(this).data('action_taken_id'));
        $('#description').val($(this).data('description'));
        $('#current_status_id').val($(this).data('current_status_id'));
        $('#disciplineModal').modal('show');
    });

    // Delete
    $(document).on('click', '.delete-discipline', function() {
        var $btn = $(this);
        var $icon = $btn.find('.material-symbols-outlined').first();
        $icon.show();
        $btn.find('.material-symbols-outlined').last().hide();
        $btn.prop('disabled', true);
        $.ajax({
            url: "{{ url('/ajax/hr/discipline/tracker') }}/" + $btn.data('id'),
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
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

    // Reset modal on close
    $('#disciplineModal').on('hidden.bs.modal', function() {
        $('#disciplineForm')[0].reset();
        $('#disciplineId').val('');
    });
});
</script>
@endsection
