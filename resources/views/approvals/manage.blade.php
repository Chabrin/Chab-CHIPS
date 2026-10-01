@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">
                            Manage Workflow:
                            <span class="fs-6 text-muted ms-1">({{ $workflow->slug }})</span>
                        </h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('approval.workflows.index') }}" class="btn btn-secondary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to Workflows</span>
                            </a>

                            @permission('create_approvals')
                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="addApproverBtn">
                                <span class="material-symbols-outlined">person_add</span>
                                <span class="ms-1">Add Approver</span>
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

                        <!-- Workflow Info Section -->
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <p class="text-muted mb-0">Approvers in {{ $workflow->description ?? 'No description available' }}</p>
                            </div>
                            <div class="col-md-4 text-end">
                                @if($workflow->active)
                                <span class="badge bg-success">Active</span>
                                @else
                                <span class="badge bg-danger">Inactive</span>
                                @endif
                            </div>
                        </div>

                        <!-- Approvers Table -->
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="approversTable">
                                <thead>
                                    <tr>
                                        <th>Level</th>
                                        <th>Approver</th>
                                        <th>Type</th>
                                        <th>Required</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                        @permission('update_approvals|delete_approvals')
                                        <th>Actions</th>
                                        @endpermission
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- DataTable will populate this -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div><!-- Add Approver Modal -->
<div class="modal fade" id="addApproverModal" tabindex="-1" aria-labelledby="addApproverModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addApproverModalLabel">Add New Approver</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addApproverForm">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="approver_type" class="form-label required">Approver Type</label>
                            <select class="form-select" id="approver_type" name="approver_type" required>
                                <option value="">Select Type</option>
                                <option value="user">Individual User</option>
                                <!-- <option value="role">Role</option> -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="approver_id" class="form-label required">Approver</label>
                            <select class="form-select" id="approver_id" name="approver_id" required disabled>
                                <option value="">Select Approver</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="hierarchy_level" class="form-label required">Hierarchy Level</label>
                            <select class="form-select" id="hierarchy_level" name="hierarchy_level" required>
                                <option value="">Select Level</option>
                                <option value="1">Level 1</option>
                                <option value="2">Level 2</option>
                                <option value="3">Level 3</option>
                                <option value="4">Level 4</option>
                                <option value="5">Level 5</option>
                            </select>
                            <div class="form-text">Level 1 = First approver, Level 2 = Second approver, etc.</div>
                        </div>
                    </div>
                    <!-- Hidden field for is_required - always checked -->
                    <input type="hidden" id="is_required" name="is_required" value="1">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"
                                placeholder="Optional notes about this approver..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined">close</span>
                        <span class="ms-1">Cancel</span>
                    </button>
                    <button type="submit" class="btn btn-success d-flex align-items-center" id="saveApproverBtn">
                        <span class="material-symbols-outlined">person_add</span>
                        <span class="ms-1">Add Approver</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Approver Modal -->
<div class="modal fade" id="editApproverModal" tabindex="-1" aria-labelledby="editApproverModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editApproverModalLabel">Edit Approver Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editApproverForm">
                <div class="modal-body">
                    <input type="hidden" id="edit_approver_id" name="approver_id">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_hierarchy_level" class="form-label required">Hierarchy Level</label>
                            <select class="form-select" id="edit_hierarchy_level" name="hierarchy_level" required>
                                <option value="">Select Level</option>
                                <option value="1">Level 1</option>
                                <option value="2">Level 2</option>
                                <option value="3">Level 3</option>
                                <option value="4">Level 4</option>
                                <option value="5">Level 5</option>
                            </select>
                        </div>
                    </div>
                    <!-- Hidden field for is_required - always checked -->
                    <input type="hidden" id="edit_is_required" name="is_required" value="1">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined">close</span>
                        <span class="ms-1">Cancel</span>
                    </button>
                    <button type="submit" class="btn btn-primary d-flex align-items-center">
                        <span class="material-symbols-outlined">save</span>
                        <span class="ms-1">Update</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Remove Approver Modal -->
<div class="modal fade" id="removeApproverModal" tabindex="-1" aria-labelledby="removeApproverModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="removeApproverModalLabel">Confirm Remove Approver</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center mb-3">
                    <span class="material-symbols-outlined text-warning me-3" style="font-size: 48px;">warning</span>
                    <div>
                        <h6 class="mb-1">Are you sure you want to remove this approver?</h6>
                        <p class="mb-0 text-muted">This action cannot be undone.</p>
                    </div>
                </div>
                <div class="bg-light p-3 rounded">
                    <p class="mb-1"><strong>Approver:</strong> <span id="remove_approver_name"></span></p>
                    <p class="mb-1"><strong>Level:</strong> <span id="remove_approver_level"></span></p>
                    <p class="mb-0"><strong>Type:</strong> <span id="remove_approver_type"></span></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined">close</span>
                    <span class="ms-1">Cancel</span>
                </button>
                <button type="button" class="btn btn-danger d-flex align-items-center" id="confirmRemoveBtn">
                    <span class="material-symbols-outlined">person_remove</span>
                    <span class="ms-1">Remove Approver</span>
                </button>
            </div>
        </div>
    </div>
</div>




<script>
    // Function to get table columns
    function getTableColumns() {
        var columns = [{
                data: 'hierarchy_level',
                render: function(data, type, row) {
                    return '<span class="badge badge-light-primary">Level ' + data + '</span>';
                }
            },
            {
                data: 'approver_name'
            },
            {
                data: 'approver_type',
                render: function(data, type, row) {
                    return data === 'user' ?
                        '<span class="">Individual</span>' :
                        '<span class="">Role (Any user in this role)</span>';
                }
            },
            {
                data: 'is_required',
                render: function(data, type, row) {
                    return data ?
                        '<span class="badge badge-light-success">Required</span>' :
                        '<span class="badge badge-light-secondary">Optional</span>';
                }
            },
            {
                data: 'active',
                render: function(data, type, row) {
                    return data ?
                        '<span class="badge badge-light-success">Active</span>' :
                        '<span class="badge badge-light-danger">Inactive</span>';
                }
            },
            {
                data: 'notes',
                render: function(data, type, row) {
                    return data ? (data.length > 30 ? data.substr(0, 30) + '...' : data) : '-';
                }
            }
        ];

        // Add actions column - permissions handled by Blade directives in table header
        columns.push({
            data: null,
            orderable: false,
            searchable: false,
            render: function(data, type, row) {
                var actions = '<div class="d-flex flex-row gap-1">';

                // Show both buttons - server-side permissions control visibility via table header
                actions += `
                    <button class="btn btn-xs btn-light edit-approver d-flex align-items-center"
                        data-id="${row.id}"
                        data-hierarchy_level="${row.hierarchy_level}"
                        data-is_required="${row.is_required ? 1 : 0}"
                        data-notes="${row.notes || ''}"
                        style="padding:2px 6px;" title="Edit">
                        <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
                    </button>`;

                actions += `
                    <button class="btn btn-xs btn-danger remove-approver d-flex align-items-center"
                        data-id="${row.id}"
                        data-approver_name="${row.approver_name}"
                        data-hierarchy_level="${row.hierarchy_level}"
                        data-approver_type="${row.approver_type}"
                        style="padding:2px 6px;" title="Remove">
                        <span class="material-symbols-outlined" style="font-size:18px;">person_remove</span>
                    </button>`;

                actions += '</div>';
                return actions;
            }
        });

        return columns;
    }

    $(document).ready(function() {
        var workflowId = '{{ encrypt($workflow->id) }}';
        var approversTable;
        var availableUsers = [];
        var availableRoles = [];

        // Initialize DataTable
        approversTable = $('#approversTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: '{{ route("approval.workflows.approvers", ":id") }}'.replace(':id', workflowId),
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'approvers',
                error: function(xhr, error, code) {
                    console.error('DataTable AJAX error:', error);
                    toastr.error('Failed to load approvers');
                }
            },
            columns: getTableColumns(),
            order: [
                [0, 'asc'],
                [1, 'asc']
            ],
            pageLength: 25,
            lengthMenu: [
                [10, 25, 50, -1],
                [10, 25, 50, "All"]
            ]
        });

        // Load available approvers
        function loadAvailableApprovers() {
            $.ajax({
                url: '{{ route("approval.workflows.available-approvers", ":id") }}'.replace(':id', workflowId),
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        availableUsers = response.users;
                        availableRoles = response.roles;
                    }
                },
                error: function(xhr) {
                    toastr.error('Failed to load available approvers');
                }
            });
        }

        // Show add approver modal
        $('#addApproverBtn').click(function() {
            loadAvailableApprovers();
            $('#addApproverForm')[0].reset();
            $('#approver_id').prop('disabled', true);
            $('#addApproverModal').modal('show');
        });

        // Handle approver type change
        $('#approver_type').change(function() {
            var type = $(this).val();
            var approverSelect = $('#approver_id');

            approverSelect.empty().append('<option value="">Select Approver</option>');

            if (type === 'user') {
                availableUsers.forEach(function(user) {
                    approverSelect.append(`<option value="${user.id}">${user.name}</option>`);
                });
                approverSelect.prop('disabled', false);
            } else if (type === 'role') {
                availableRoles.forEach(function(role) {
                    approverSelect.append(`<option value="${role.id}">${role.name}</option>`);
                });
                approverSelect.prop('disabled', false);
            } else {
                approverSelect.prop('disabled', true);
            }
        });

        // Handle add approver form submission
        $('#addApproverForm').submit(function(e) {
            e.preventDefault();

            var formData = {
                approver_type: $('#approver_type').val(),
                approver_id: $('#approver_id').val(),
                hierarchy_level: $('#hierarchy_level').val(),
                is_required: 1, // Always set to 1 (required)
                notes: $('#notes').val()
            };

            $.ajax({
                url: '{{ route("approval.workflows.add-approver", ":id") }}'.replace(':id', workflowId),
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: formData,
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        $('#addApproverModal').modal('hide');
                        approversTable.ajax.reload();
                    } else {
                        toastr.error(response.error);
                    }
                },
                error: function(xhr) {
                    const errors = xhr.responseJSON?.errors;
                    if (errors) {
                        Object.values(errors).forEach(function(errorArray) {
                            errorArray.forEach(function(error) {
                                toastr.error(error);
                            });
                        });
                    } else {
                        toastr.error(xhr.responseJSON?.error || 'Failed to add approver');
                    }
                }
            });
        });

        // Handle edit approver
        $(document).on('click', '.edit-approver', function() {
            const data = $(this).data();

            $('#edit_approver_id').val(data.id);
            $('#edit_hierarchy_level').val(data.hierarchy_level);
            $('#edit_notes').val(data.notes);

            $('#editApproverModal').modal('show');
        });

        // Handle edit approver form submission
        $('#editApproverForm').submit(function(e) {
            e.preventDefault();

            var approverId = $('#edit_approver_id').val();
            var formData = {
                hierarchy_level: $('#edit_hierarchy_level').val(),
                is_required: 1, // Always set to 1 (required)
                notes: $('#edit_notes').val(),
                _method: 'PUT'
            };

            $.ajax({
                url: '{{ route("approval.workflows.update-approver", [":id", ":approverId"]) }}'
                    .replace(':id', workflowId)
                    .replace(':approverId', approverId),
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: formData,
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        $('#editApproverModal').modal('hide');
                        approversTable.ajax.reload();
                    } else {
                        toastr.error(response.error);
                    }
                },
                error: function(xhr) {
                    const errors = xhr.responseJSON?.errors;
                    if (errors) {
                        Object.values(errors).forEach(function(errorArray) {
                            errorArray.forEach(function(error) {
                                toastr.error(error);
                            });
                        });
                    } else {
                        toastr.error(xhr.responseJSON?.error || 'Failed to update approver');
                    }
                }
            });
        });

        // Handle remove approver
        var removeApproverId;
        $(document).on('click', '.remove-approver', function() {
            var data = $(this).data();
            removeApproverId = data.id;

            $('#remove_approver_name').text(data.approver_name);
            $('#remove_approver_level').text('Level ' + data.hierarchy_level);
            $('#remove_approver_type').text(data.approver_type === 'user' ? 'Individual User' : 'Role');

            $('#removeApproverModal').modal('show');
        });

        // Confirm remove approver
        $('#confirmRemoveBtn').click(function() {
            $.ajax({
                url: '{{ route("approval.workflows.remove-approver", [":id", ":approverId"]) }}'
                    .replace(':id', workflowId)
                    .replace(':approverId', removeApproverId),
                type: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        $('#removeApproverModal').modal('hide');
                        approversTable.ajax.reload();
                    } else {
                        toastr.error(response.error);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.error || 'Failed to remove approver');
                }
            });
        });
    });
</script>
@endsection
