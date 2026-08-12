@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Tax Computations</h4>
                        <div class="ms-auto">
                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addComputationModal">
                                <span class="material-symbols-outlined">add</span>
                                <span class="ms-1">Add Computation</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="computationsTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Computation Name</th>
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
<div class="modal fade" id="addComputationModal" tabindex="-1" role="dialog" aria-labelledby="addComputationLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addComputationLabel">Add Tax Computation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="computationForm">
                <div class="modal-body">
                    <input type="hidden" id="computationId" name="id">
                    <div class="form-group mb-3">
                        <label for="computationName" class="form-label">Computation Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="computationName" name="computation_name" required maxlength="255">
                    </div>
                    <div class="form-group">
                        <label for="computationStatus" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-control" id="computationStatus" name="current_status_id" required>
                            <option value="5">Active</option>
                            <option value="6">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveComputationBtn">
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
    var table = $('#computationsTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: '{{ url("/ajax/finance/tax-settings/computations/list") }}',
            dataSrc: function(json) {
                return json.data || [];
            }
        },
        columns: [
            { data: 'id' },
            { data: 'computation_name' },
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
                    return '<button class="btn btn-sm btn-warning editComputation" data-id="' + row.id + '" data-bs-toggle="modal" data-bs-target="#addComputationModal"><span class="material-symbols-outlined">edit</span></button> ' +
                           '<button class="btn btn-sm btn-danger deleteComputation" data-id="' + row.id + '"><span class="material-symbols-outlined">delete</span></button>';
                }
            }
        ],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]]
    });

    // Add/Edit Computation
    $('#computationForm').on('submit', function(e) {
        e.preventDefault();
        var id = $('#computationId').val();
        var url = id ? '/ajax/finance/tax-settings/computations/' + id : '/ajax/finance/tax-settings/computations';
        var method = id ? 'POST' : 'POST';

        $.ajax({
            url: url,
            method: method,
            data: $(this).serialize() + '&_token={{ csrf_token() }}',
            success: function(res) {
                if (res.success) {
                    toastr.success(res.message || 'Saved successfully');
                    $('#addComputationModal').modal('hide');
                    $('#computationForm')[0].reset();
                    $('#computationId').val('');
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

    // Edit Computation
    $(document).on('click', '.editComputation', function() {
        var id = $(this).data('id');
        $.get('/ajax/finance/tax-settings/computations/' + id, function(res) {
            if (res.success) {
                $('#computationId').val(res.data.id);
                $('#computationName').val(res.data.computation_name);
                $('#computationStatus').val(res.data.current_status_id);
            }
        });
    });

    // Delete Computation
    $(document).on('click', '.deleteComputation', function() {
        var id = $(this).data('id');
        if (confirm('Are you sure you want to delete this?')) {
            $.ajax({
                url: '/ajax/finance/tax-settings/computations/' + id,
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
    $('#addComputationModal').on('hidden.bs.modal', function() {
        $('#computationForm')[0].reset();
        $('#computationId').val('');
        $('#addComputationLabel').text('Add Tax Computation');
    });

    // Update modal title when editing
    $(document).on('click', '.editComputation', function() {
        $('#addComputationLabel').text('Edit Tax Computation');
    });
});
</script>
@endsection
