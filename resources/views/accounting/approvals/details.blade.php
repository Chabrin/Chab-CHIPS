@extends('layouts.main')

@section('styles')
<style>
.role-label {
    font-weight: 600;
    font-size: 0.85rem;
}
.approvers-cell {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
.approver-name-tag {
    display: inline-flex;
    align-items: center;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 3px;
    padding: 2px 8px;
    font-size: 0.8rem;
    white-space: nowrap;
}
.approver-name-tag .remove-single {
    margin-left: 6px;
    color: #dc3545;
    cursor: pointer;
    font-size: 14px;
    line-height: 1;
}
.approver-name-tag .remove-single:hover {
    color: #a71d2a;
}
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="mb-1">{{ $workflow->workflow_name }}</h5>
                <small class="text-muted">{{ $workflow->description ?? 'No description' }}</small>
            </div>
            <div class="d-flex gap-2">
                @if($workflow->status == 1)
                <span class="badge badge-light-success">Active</span>
                @else
                <span class="badge badge-light-danger">Inactive</span>
                @endif
                <a href="{{ route('accounting.approvals.index') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                    <span class="material-symbols-outlined" style="font-size:16px;">arrow_back</span> Back
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Approvers Section - Full Width -->
            <div class="col-12">
                <div class="card mb-3">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between py-2">
                        <h6 class="mb-0">
                            <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">group</span>
                            Approvers
                            <span class="badge badge-light-primary ms-1" id="approversCount">0</span>
                        </h6>
                        @permission('create_approvers')
                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#approverModal" id="addApproverBtn">
                            <span class="material-symbols-outlined" style="font-size:16px;">person_add</span> Add Approvers
                        </button>
                        @endpermission
                    </div>
                    <div class="card-body pt-0">
                        <table class="table table-sm" id="approversTable" style="font-size:0.85rem;">
                            <thead>
                                <tr>
                                    <th style="width:150px;">Role</th>
                                    <th>Approvers</th>
                                </tr>
                            </thead>
                            <tbody id="approversTableBody">
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-4">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Approver Modal -->
<div class="modal fade" id="approverModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <span class="material-symbols-outlined" style="font-size:20px;vertical-align:middle;">person_add</span>
                    <span id="approverModalTitle">Add Approvers</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="approverForm">
                    <input type="hidden" id="approverId" value="">
                    <div class="mb-3" id="approverSelectWrap">
                        <label for="approverSelect" class="form-label">Select Users <span class="text-danger">*</span></label>
                        <select id="approverSelect" style="width:100%;" multiple="multiple"></select>
                        <small class="text-muted">You can select multiple users for the same role</small>
                    </div>
                    <div class="mb-3">
                        <label for="approvalLevel" class="form-label">Approval Role <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="approvalLevel">
                            <option value="1">Checker</option>
                            <option value="2">Verifier</option>
                            <option value="3">Approver (Final)</option>
                        </select>
                        <small class="text-muted">Only the Approver role is marked as final</small>
                    </div>
                    <div class="mb-3" id="isActiveWrap" style="display:none;">
                        <label class="form-label">Status</label>
                        <select class="form-select form-select-sm" id="isActive">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="saveApproverBtn">
                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">save</span>
                    <span id="saveApproverBtnText">Add</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Remove Approver Confirmation Modal -->
<div class="modal fade" id="removeApproverModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <span class="material-symbols-outlined text-danger" style="font-size:20px;vertical-align:middle;">warning</span>
                    Remove Approver
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Remove this approver from the workflow?</p>
                <p class="mb-0 fw-bold" id="removeApproverName"></p>
            </div>
            <div class="modal-footer">
                <input type="hidden" id="removeApproverId" value="">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="confirmRemoveApproverBtn">Remove</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
    var workflowEncryptedId = '{{ Crypt::encrypt($workflow->id) }}';

    // Level to role name mapping
    function getLevelLabel(level) {
        var labels = { 1: 'Checker', 2: 'Verifier', 3: 'Approver' };
        return labels[level] || 'Level ' + level;
    }

    // Initialize Select2 for user search (multiple selection) - names only
    $('#approverSelect').select2({
        dropdownParent: $('#approverModal'),
        ajax: {
            url: '{{ route("ajax.accounting.approvals.users.search") }}',
            dataType: 'json',
            delay: 300,
            data: function(params) {
                return { search: params.term };
            },
            processResults: function(data) {
                if (!data.success) return { results: [] };
                return {
                    results: data.data.map(function(u) {
                        return { id: u.id, text: u.name };
                    })
                };
            }
        },
        minimumInputLength: 2,
        placeholder: 'Search by name...',
        allowClear: true,
        width: '100%',
        multiple: true
    });

    // Load approvers
    function loadApprovers() {
        $.ajax({
            url: '/ajax/accounting/approvals/' + workflowEncryptedId + '/approvers',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderApproversTable(response.data);
                    $('#approversCount').text(response.data.filter(a => a.is_active == 1).length);
                }
            },
            error: function() {
                $('#approversTableBody').html('<tr><td colspan="2" class="text-center text-danger">Failed to load approvers</td></tr>');
            }
        });
    }

    // Render approvers table - grouped by level
    function renderApproversTable(data) {
        if (data.length === 0) {
            $('#approversTableBody').html('<tr><td colspan="2" class="text-center text-muted py-4">No approvers added yet. Click "Add Approvers" to add one.</td></tr>');
            return;
        }

        // Group by level
        var grouped = {};
        data.forEach(function(item) {
            var level = item.approval_level;
            if (!grouped[level]) {
                grouped[level] = { approvers: [] };
            }
            grouped[level].approvers.push(item);
        });

        // Sort levels and render
        var levels = Object.keys(grouped).sort(function(a, b) { return a - b; });
        var html = '';

        levels.forEach(function(level) {
            var group = grouped[level];
            var levelLabel = getLevelLabel(parseInt(level));
            // Add (Final) indicator for Approver role
            if (parseInt(level) === 3) {
                levelLabel += ' <small class="text-muted">(Final)</small>';
            }

            // Build approver name tags
            var nameTags = '<div class="approvers-cell">';
            group.approvers.forEach(function(item) {
                var tagStyle = item.is_active == 0 ? ' style="opacity:0.5;text-decoration:line-through;"' : '';
                nameTags += '<span class="approver-name-tag"' + tagStyle + '>' + item.approver_name;
                @permission('delete_approvers')
                nameTags += '<span class="material-symbols-outlined remove-single" title="Remove" data-id="' + item.encrypted_id + '" data-name="' + item.approver_name + '">close</span>';
                @endpermission
                nameTags += '</span>';
            });
            nameTags += '</div>';

            html += '<tr>';
            html += '<td><span class="role-label">' + levelLabel + '</span></td>';
            html += '<td>' + nameTags + '</td>';
            html += '</tr>';
        });

        $('#approversTableBody').html(html);
    }

    // Handle remove single approver (via X button on name tag)
    $(document).on('click', '.remove-single', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var id = $(this).data('id');
        var name = $(this).data('name');
        $('#removeApproverId').val(id);
        $('#removeApproverName').text(name);
        $('#removeApproverModal').modal('show');
    });

    loadApprovers();

    // Add Approver Button
    $('#addApproverBtn').on('click', function() {
        $('#approverId').val('');
        $('#approverForm')[0].reset();
        $('#approverSelect').val(null).trigger('change');
        $('#approverModalTitle').text('Add Approvers');
        $('#saveApproverBtnText').text('Add');
        $('#approverSelectWrap').show();
        $('#isActiveWrap').hide();
        $('#approvalLevel').val('1');
    });

    // Save Approver
    $('#saveApproverBtn').on('click', function() {
        var id = $('#approverId').val();
        var isEdit = id !== '';

        var formData = {
            approval_level: $('#approvalLevel').val(),
            _token: '{{ csrf_token() }}'
        };

        if (!isEdit) {
            var selectedUsers = $('#approverSelect').select2('data');
            if (!selectedUsers || selectedUsers.length === 0) {
                toastr.error('Please select at least one user');
                return;
            }
            formData.approver_ids = selectedUsers.map(function(u) { return u.id; });
        } else {
            formData.is_active = $('#isActive').val();
        }

        $('#saveApproverBtn').prop('disabled', true);
        $('#saveApproverBtnText').text(isEdit ? 'Updating...' : 'Adding...');

        var url = isEdit
            ? '/ajax/accounting/approvals/approvers/' + id
            : '/ajax/accounting/approvals/' + workflowEncryptedId + '/approvers';

        var method = isEdit ? 'PUT' : 'POST';

        $.ajax({
            url: url,
            type: method,
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#approverModal').modal('hide');
                    loadApprovers();
                } else {
                    var msg = response.message || 'Failed to save approver';
                    if (response.errors) {
                        msg = Object.values(response.errors).flat().join('<br>');
                    }
                    toastr.error(msg);
                }
            },
            error: function(xhr) {
                var msg = 'Failed to save approver';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                toastr.error(msg);
            },
            complete: function() {
                $('#saveApproverBtn').prop('disabled', false);
                $('#saveApproverBtnText').text(isEdit ? 'Update' : 'Add');
            }
        });
    });

    // Confirm Remove Approver
    $('#confirmRemoveApproverBtn').on('click', function() {
        var id = $('#removeApproverId').val();

        $.ajax({
            url: '/ajax/accounting/approvals/approvers/' + id,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#removeApproverModal').modal('hide');
                    loadApprovers();
                } else {
                    toastr.error(response.message || 'Failed to remove approver');
                }
            },
            error: function(xhr) {
                var msg = 'Failed to remove approver';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                toastr.error(msg);
            }
        });
    });

    // Reset modal on close
    $('#approverModal').on('hidden.bs.modal', function() {
        $('#approverForm')[0].reset();
        $('#approverId').val('');
        $('#approverSelect').val(null).trigger('change');
    });
});
</script>
@endsection
