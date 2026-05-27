@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Approval Workflows</h5>
                        @permission('create_approval_workflows')
                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#workflowModal" id="addWorkflowBtn">
                            <span class="material-symbols-outlined" style="font-size:16px;">add</span> New Workflow
                        </button>
                        @endpermission
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="workflowsTable" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Workflow Name</th>
                                        <th>Description</th>
                                        <th>Approvers</th>
                                        <th>Levels</th>
                                        <th>Status</th>
                                        <th>Added By</th>
                                        <th>Added On</th>
                                        <th>Actions</th>
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

<!-- Workflow Modal (Create/Edit) -->
<div class="modal fade" id="workflowModal" tabindex="-1" aria-labelledby="workflowModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="workflowModalLabel">
                    <span class="material-symbols-outlined" style="font-size:20px;vertical-align:middle;">account_tree</span>
                    <span id="workflowModalTitle">New Workflow</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="workflowForm">
                    <input type="hidden" id="workflowId" value="">
                    <div class="mb-3">
                        <label for="workflowName" class="form-label">Workflow Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="workflowName" name="workflow_name" required maxlength="255" placeholder="e.g. Payment Voucher Approval">
                    </div>
                    <div class="mb-3">
                        <label for="workflowDescription" class="form-label">Description</label>
                        <textarea class="form-control form-control-sm" id="workflowDescription" name="description" rows="2" maxlength="500" placeholder="Optional description"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="workflowStatus" class="form-label">Status</label>
                        <select class="form-select form-select-sm" id="workflowStatus" name="status">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="saveWorkflowBtn">
                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">save</span>
                    <span id="saveWorkflowBtnText">Save</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteWorkflowModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <span class="material-symbols-outlined text-danger" style="font-size:20px;vertical-align:middle;">warning</span>
                    Confirm Delete
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this workflow?</p>
                <p class="mb-0 fw-bold" id="deleteWorkflowName"></p>
                <small class="text-muted">This will also delete all associated approvers.</small>
            </div>
            <div class="modal-footer">
                <input type="hidden" id="deleteWorkflowId" value="">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteWorkflowBtn">
                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">delete</span> Delete
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#workflowsTable').DataTable({
        data: [],
        columns: [
            { data: null, orderable: false, render: (d, t, r, m) => m.row + 1 },
            { data: 'workflow_name', render: (d) => '<strong>' + d + '</strong>' },
            { data: 'description', render: (d) => '<small class="text-muted">' + d + '</small>' },
            { data: 'approvers_count', className: 'text-center' },
            { data: 'levels_count', className: 'text-center' },
            { 
                data: 'status',
                render: function(d, t, r) {
                    return d == 1 
                        ? '<span class="badge badge-light-success">Active</span>'
                        : '<span class="badge badge-light-danger">Inactive</span>';
                }
            },
            { data: 'added_by_name' },
            { data: 'added_on' },
            {
                data: null,
                orderable: false,
                render: function(d, t, r) {
                    let html = '<div class="d-flex gap-1">';
                    html += '<a href="/accounting/approvals/' + r.encrypted_id + '" class="text-primary" title="View & Setup Approvers"><span class="material-symbols-outlined" style="font-size:18px;">settings</span></a>';
                    @permission('update_approval_workflows')
                    html += '<a href="#" class="text-success btn-edit-workflow" data-id="' + r.encrypted_id + '" data-name="' + r.workflow_name + '" data-description="' + (r.description || '') + '" data-status="' + r.status + '" title="Edit"><span class="material-symbols-outlined" style="font-size:18px;">edit</span></a>';
                    @endpermission
                    @permission('delete_approval_workflows')
                    html += '<a href="#" class="text-danger btn-delete-workflow" data-id="' + r.encrypted_id + '" data-name="' + r.workflow_name + '" title="Delete"><span class="material-symbols-outlined" style="font-size:18px;">delete</span></a>';
                    @endpermission
                    html += '</div>';
                    return html;
                }
            }
        ],
        paging: true,
        info: true,
        searching: true,
        ordering: true,
        order: [[1, 'asc']],
        pageLength: 25
    });

    // Load workflows data
    function loadWorkflows() {
        $.ajax({
            url: '{{ route("ajax.accounting.approvals.data") }}',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    table.clear();
                    table.rows.add(response.data);
                    table.draw();
                }
            },
            error: function(xhr) {
                toastr.error('Failed to load workflows');
            }
        });
    }

    loadWorkflows();

    // Add Workflow Button
    $('#addWorkflowBtn').on('click', function() {
        $('#workflowId').val('');
        $('#workflowForm')[0].reset();
        $('#workflowModalTitle').text('New Workflow');
        $('#saveWorkflowBtnText').text('Save');
        $('#workflowStatus').val('1');
    });

    // Edit Workflow Button
    $(document).on('click', '.btn-edit-workflow', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var name = $(this).data('name');
        var description = $(this).data('description');
        var status = $(this).data('status');

        $('#workflowId').val(id);
        $('#workflowName').val(name);
        $('#workflowDescription').val(description);
        $('#workflowStatus').val(status);
        $('#workflowModalTitle').text('Edit Workflow');
        $('#saveWorkflowBtnText').text('Update');
        $('#workflowModal').modal('show');
    });

    // Save Workflow
    $('#saveWorkflowBtn').on('click', function() {
        var id = $('#workflowId').val();
        var isEdit = id !== '';

        var formData = {
            workflow_name: $('#workflowName').val(),
            description: $('#workflowDescription').val(),
            status: $('#workflowStatus').val(),
            _token: '{{ csrf_token() }}'
        };

        if (!formData.workflow_name) {
            toastr.error('Workflow name is required');
            return;
        }

        $('#saveWorkflowBtn').prop('disabled', true);
        $('#saveWorkflowBtnText').text(isEdit ? 'Updating...' : 'Saving...');

        var url = isEdit
            ? '/ajax/accounting/approvals/' + id
            : '{{ route("ajax.accounting.approvals.store") }}';

        var method = isEdit ? 'PUT' : 'POST';

        $.ajax({
            url: url,
            type: method,
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#workflowModal').modal('hide');
                    loadWorkflows();
                } else {
                    var msg = response.message || 'Failed to save workflow';
                    if (response.errors) {
                        msg = Object.values(response.errors).flat().join('<br>');
                    }
                    toastr.error(msg);
                }
            },
            error: function(xhr) {
                var msg = 'Failed to save workflow';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                }
                toastr.error(msg);
            },
            complete: function() {
                $('#saveWorkflowBtn').prop('disabled', false);
                $('#saveWorkflowBtnText').text(isEdit ? 'Update' : 'Save');
            }
        });
    });

    // Delete Workflow Button
    $(document).on('click', '.btn-delete-workflow', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var name = $(this).data('name');
        $('#deleteWorkflowId').val(id);
        $('#deleteWorkflowName').text(name);
        $('#deleteWorkflowModal').modal('show');
    });

    // Confirm Delete
    $('#confirmDeleteWorkflowBtn').on('click', function() {
        var id = $('#deleteWorkflowId').val();

        $.ajax({
            url: '/ajax/accounting/approvals/' + id,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#deleteWorkflowModal').modal('hide');
                    loadWorkflows();
                } else {
                    toastr.error(response.message || 'Failed to delete workflow');
                }
            },
            error: function(xhr) {
                toastr.error('Failed to delete workflow');
            }
        });
    });

    // Reset modal on close
    $('#workflowModal').on('hidden.bs.modal', function() {
        $('#workflowForm')[0].reset();
        $('#workflowId').val('');
    });
});
</script>
@endsection
