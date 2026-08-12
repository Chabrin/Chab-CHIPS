@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Tax Default Types</h4>
                        <div class="ms-auto">
                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addDefaultTypeModal">
                                <span class="material-symbols-outlined">add</span>
                                <span class="ms-1">Add Default Type</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="defaultTypesTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Type Name</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th>Date Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="addDefaultTypeModal" tabindex="-1" role="dialog" aria-labelledby="addDefaultTypeLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDefaultTypeLabel">Add Tax Default Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="defaultTypeForm">
                <div class="modal-body">
                    <input type="hidden" id="defaultTypeId" name="id">
                    <div class="form-group mb-3">
                        <label for="typeName" class="form-label">Type Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="typeName" name="type_name" required maxlength="255">
                    </div>
                    <div class="form-group">
                        <label for="typeStatus" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-control" id="typeStatus" name="current_status_id" required>
                            <option value="5">Active</option>
                            <option value="6">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveDefaultTypeBtn">
                        <span class="material-symbols-outlined" style="font-size: 18px; margin-right: 4px;">check_circle</span>
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="{{ asset('assets/js/dataTables.min.js') }}"></script>
<script>
$(function() {
    var table = $('#defaultTypesTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: '{{ url("/ajax/finance/tax-settings/default-types/list") }}',
            dataSrc: function(json) {
                return json.data || [];
            }
        },
        columns: [
            { data: 'id' },
            { data: 'type_name' },
            { 
                data: 'current_status_id',
                render: function(data) {
                    return data === 5 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
                }
            },
            { data: 'created_by' },
            { 
                data: 'created_at',
                render: function(data) {
                    return data ? new Date(data).toLocaleDateString() : '-';
                }
            },
            {
                data: null,
                render: function(data, type, row) {
                    return '<button class="btn btn-sm btn-warning editDefaultType" data-id="' + row.id + '" data-bs-toggle="modal" data-bs-target="#addDefaultTypeModal"><span class="material-symbols-outlined">edit</span></button> ' +
                           '<button class="btn btn-sm btn-danger deleteDefaultType" data-id="' + row.id + '"><span class="material-symbols-outlined">delete</span></button>';
                }
            }
        ],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]]
    });

    // Add Default Type
    $('#defaultTypeForm').on('submit', function(e) {
        e.preventDefault();
        var id = $('#defaultTypeId').val();
        var url = id ? '/ajax/finance/tax-settings/default-types/' + id : '/ajax/finance/tax-settings/default-types';
        var method = id ? 'POST' : 'POST';

        $.ajax({
            url: url,
            method: method,
            data: $(this).serialize() + '&_token={{ csrf_token() }}',
            success: function(res) {
                if (res.success) {
                    toastr.success(res.message || 'Saved successfully');
                    $('#addDefaultTypeModal').modal('hide');
                    $('#defaultTypeForm')[0].reset();
                    $('#defaultTypeId').val('');
                    table.ajax.reload();
                } else {
                    toastr.error(res.error || 'Error saving');
                }
            },
            error: function(xhr) {
                var msg = 'Error saving';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                }
                toastr.error(msg);
            }
        });
    });

    // Edit Default Type
    $(document).on('click', '.editDefaultType', function() {
        var id = $(this).data('id');
        $.get('/ajax/finance/tax-settings/default-types/' + id, function(res) {
            if (res.success) {
                $('#defaultTypeId').val(res.data.id);
                $('#typeName').val(res.data.type_name);
                $('#typeStatus').val(res.data.current_status_id);
            }
        });
    });

    // Delete Default Type
    $(document).on('click', '.deleteDefaultType', function() {
        var id = $(this).data('id');
        if (confirm('Are you sure you want to delete this?')) {
            $.ajax({
                url: '/ajax/finance/tax-settings/default-types/' + id,
                method: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function(res) {
                    if (res.success) {
                        toastr.success('Deleted successfully');
                        table.ajax.reload();
                    } else {
                        toastr.error(res.error || 'Error deleting');
                    }
                },
                error: function() {
                    toastr.error('Error deleting');
                }
            });
        }
    });

    // Reset form when modal is closed
    $('#addDefaultTypeModal').on('hidden.bs.modal', function() {
        $('#defaultTypeForm')[0].reset();
        $('#defaultTypeId').val('');
        $('#addDefaultTypeLabel').text('Add Tax Default Type');
    });

    // Update modal title when editing
    $(document).on('click', '.editDefaultType', function() {
        $('#addDefaultTypeLabel').text('Edit Tax Default Type');
    });
});
</script>
@endsection
