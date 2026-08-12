@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Approval Workflows</h4>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_approvals')
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addWorkflowModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Workflow</span>
                            </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        @include('components.error_reports')

                        @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                        @endif
                        @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif

                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="workflows-table">
                                <thead>
                                    <tr>
                                        <th>Workflow Name</th>
                                        <th>Status</th>
                                        <th style="width:120px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Workflow Modal -->
<div class="modal fade" id="addWorkflowModal" tabindex="-1" aria-labelledby="addWorkflowModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addWorkflowModalLabel">Add Approval Workflow</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="workflowForm">
                    <input type="hidden" name="id" id="workflowId">
                    <div class="row">
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="workflow_name" class="form-label">Workflow Name <span style="color:red">*</span></label>
                            <input type="text" class="form-control" name="workflow_name" id="workflow_name" required placeholder="Enter workflow name">
                        </div>
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="active" class="form-label">Status</label>
                            <select class="form-control" name="active" id="active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="description" rows="4" placeholder="Enter workflow description"></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-success d-flex align-items-center" id="saveWorkflowBtn">
                            <span class="material-symbols-outlined">check_circle</span>
                            <span class="ms-1">Save</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteWorkflowModal" tabindex="-1" aria-labelledby="deleteWorkflowModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteWorkflowModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center mb-3">
                    <span class="material-symbols-outlined text-danger me-2" style="font-size:24px;">warning</span>
                    <span>Are you sure you want to delete this workflow?</span>
                </div>
                <div class="alert alert-warning">
                    <strong>Workflow:</strong> <span id="deleteWorkflowName"></span>
                </div>
                <p class="text-muted">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined">close</span>
                    <span class="ms-1">Cancel</span>
                </button>
                <button type="button" class="btn btn-danger d-flex align-items-center" id="confirmDeleteBtn">
                    <span class="material-symbols-outlined spin" style="display:none;">sync</span>
                    <span class="material-symbols-outlined">delete_sweep</span>
                    <span class="ms-1">Delete Workflow</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

<script>
    $(function() {
        // Initialize DataTable
        var table = $('#workflows-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ route('ajax.approvals.list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'workflows',
                error: function(xhr, error, code) {
                    console.error('DataTable AJAX error:', error);
                    toastr.error('Failed to load workflows');
                }
            },
            columns: [{
                    data: 'workflow_name',
                    render: function(data, type, row) {
                        var manageUrl = '{{ route("approval.workflows.manage", ":id") }}'.replace(':id', row.encrypted_id);
                        return `<a href="${manageUrl}" class="text-decoration-none fw-bold">${data}</a> <span class="text-muted">(${row.slug || ''})</span>`;
                    }
                },
                {
                    data: 'status',
                    render: function(data, type, row) {
                        return row.active == 1 ?
                            '<span class="badge badge-light-success">Active</span>' :
                            '<span class="badge badge-light-danger">Inactive</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        var buttons = '<div class="d-flex flex-row gap-1">';
                        buttons += `
                            <button class="btn btn-xs btn-light edit-workflow d-flex align-items-center"
                                data-id="${row.id}"
                                data-workflow_name="${row.workflow_name}"
                                data-description="${row.description || ''}"
                                data-active="${row.active}"
                                style="padding:2px 6px;" title="Edit">
                                <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                            </button>
                            <button class="btn btn-xs btn-danger delete-workflow d-flex align-items-center"
                                data-id="${row.id}"
                                data-workflow_name="${row.workflow_name}"
                                style="padding:2px 6px;" title="Delete">
                                <span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>
                                <span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>
                            </button>`;
                        buttons += '</div>';
                        return buttons;
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            order: [
                [0, 'asc']
            ]
        });

        // Handle form submission
        $('#workflowForm').on('submit', function(e) {
            e.preventDefault();

            var id = $('#workflowId').val();
            var url = id ? "{{ url('/ajax/approvals') }}/" + id : "{{ route('ajax.approvals.store') }}";
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';

            var $btn = $('#saveWorkflowBtn');
            var $icon = $btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();

            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Processing...');
            $btn.prop('disabled', true);

            $.ajax({
                url: url,
                type: 'POST',
                data: formData,
                success: function(res) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);

                    if (res.success) {
                        toastr.success(res.message || 'Saved successfully');
                        $('#addWorkflowModal').modal('hide');
                        $('#workflowForm')[0].reset();
                        $('#workflowId').val('');
                        $('#addWorkflowModalLabel').text('Add Approval Workflow');
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error saving workflow');
                    }
                },
                error: function(xhr) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);

                    let msg = 'Validation error';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        msg = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });

        // Edit workflow
        $(document).on('click', '.edit-workflow', function() {
            @permission('update_approvals')
            var data = $(this).data();
            $('#workflowId').val(data.id);
            $('#workflow_name').val(data.workflow_name);
            $('#description').val(data.description);
            $('#active').val(data.active);
            $('#addWorkflowModalLabel').text('Edit Approval Workflow');
            $('#addWorkflowModal').modal('show');
            @else
            toastr.error('You do not have permission to edit workflows');
            @endpermission
        });

        // Delete workflow - show modal
        $(document).on('click', '.delete-workflow', function() {
            @permission('delete_approvals')
            var workflowName = $(this).data('workflow_name');
            var workflowId = $(this).data('id');

            $('#deleteWorkflowName').text(workflowName);
            $('#confirmDeleteBtn').data('id', workflowId);
            $('#deleteWorkflowModal').modal('show');
            @else
            toastr.error('You do not have permission to delete workflows');
            @endpermission
        });

        // Confirm delete in modal
        $('#confirmDeleteBtn').on('click', function() {
            var workflowId = $(this).data('id');
            var $btn = $(this);
            var $iconSpin = $btn.find('.material-symbols-outlined').first();
            var $iconDelete = $btn.find('.material-symbols-outlined').eq(1);

            $iconSpin.show();
            $iconDelete.hide();
            $btn.prop('disabled', true);

            $.ajax({
                url: "{{ url('/ajax/approvals') }}/" + workflowId,
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    $iconSpin.hide();
                    $iconDelete.show();
                    $btn.prop('disabled', false);
                    $('#deleteWorkflowModal').modal('hide');

                    if (res.success) {
                        toastr.success(res.message || 'Deleted successfully');
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error deleting workflow');
                    }
                },
                error: function(xhr) {
                    $iconSpin.hide();
                    $iconDelete.show();
                    $btn.prop('disabled', false);
                    $('#deleteWorkflowModal').modal('hide');
                    toastr.error(xhr.responseJSON?.error || 'Delete error');
                }
            });
        });

        // Reset modal
        $('#addWorkflowModal').on('hidden.bs.modal', function() {
            $('#workflowForm')[0].reset();
            $('#workflowId').val('');
            $('#addWorkflowModalLabel').text('Add Approval Workflow');
        });
    });
</script>

<style>
    .spin {
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }
</style>