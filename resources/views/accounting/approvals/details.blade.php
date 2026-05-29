@extends('layouts.main')

@section('styles')
<style>
.level-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    font-weight: 600;
    font-size: 0.85rem;
    background: #e9ecef;
    color: #495057;
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
            <!-- Approvers Section -->
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between py-2">
                        <h6 class="mb-0">
                            <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">group</span>
                            Approvers
                            <span class="badge badge-light-primary ms-1" id="approversCount">0</span>
                        </h6>
                        @permission('create_approvers')
                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#approverModal" id="addApproverBtn">
                            <span class="material-symbols-outlined" style="font-size:16px;">person_add</span> Add Approver
                        </button>
                        @endpermission
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover" id="approversTable" style="font-size:0.85rem;">
                                <thead>
                                    <tr>
                                        <th style="width:60px;">Level</th>
                                        <th>Approver</th>
                                        <th>Email</th>
                                        <th style="width:80px;">Final</th>
                                        <th style="width:80px;">Status</th>
                                        <th style="width:100px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="approversTableBody">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Info Sidebar -->
            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header card-no-border py-2">
                        <h6 class="mb-0">Workflow Info</h6>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row g-2" style="font-size:0.85rem;">
                            <div class="col-5 text-muted">Name:</div>
                            <div class="col-7 fw-semibold">{{ $workflow->workflow_name }}</div>
                            <div class="col-5 text-muted">Status:</div>
                            <div class="col-7">
                                @if($workflow->status == 1)
                                <span class="text-success">Active</span>
                                @else
                                <span class="text-danger">Inactive</span>
                                @endif
                            </div>
                            <div class="col-5 text-muted">Created By:</div>
                            <div class="col-7">{{ $workflow->addedBy->name ?? '-' }}</div>
                            <div class="col-5 text-muted">Created On:</div>
                            <div class="col-7">{{ $workflow->added_on ? $workflow->added_on->format('d-m-Y H:i') : '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header card-no-border py-2">
                        <h6 class="mb-0">Approval Levels Guide</h6>
                    </div>
                    <div class="card-body pt-0">
                        <div style="font-size:0.8rem;">
                            <p class="mb-2"><strong>Level 1</strong> - First approval (e.g., Supervisor)</p>
                            <p class="mb-2"><strong>Level 2</strong> - Second approval (e.g., Manager)</p>
                            <p class="mb-2"><strong>Level 3</strong> - Third approval (e.g., Director)</p>
                            <p class="mb-0"><strong>Final</strong> - Marks the last approver in the chain</p>
                        </div>
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
                    <span id="approverModalTitle">Add Approver</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="approverForm">
                    <input type="hidden" id="approverId" value="">
                    <div class="mb-3" id="approverSelectWrap">
                        <label for="approverSelect" class="form-label">Select User <span class="text-danger">*</span></label>
                        <select id="approverSelect" style="width:100%;"></select>
                    </div>
                    <div class="mb-3">
                        <label for="approvalLevel" class="form-label">Approval Level <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="approvalLevel">
                            <option value="1">Level 1</option>
                            <option value="2">Level 2</option>
                            <option value="3">Level 3</option>
                            <option value="4">Level 4</option>
                            <option value="5">Level 5</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isFinal" value="1">
                            <label class="form-check-label" for="isFinal">This is the final approver</label>
                        </div>
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

    // Initialize Select2 for user search
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
                        return { id: u.id, text: u.text, name: u.name, email: u.email };
                    })
                };
            }
        },
        minimumInputLength: 2,
        placeholder: 'Search by name or email...',
        allowClear: true,
        width: '100%'
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
                $('#approversTableBody').html('<tr><td colspan="6" class="text-center text-danger">Failed to load approvers</td></tr>');
            }
        });
    }

    // Render approvers table
    function renderApproversTable(data) {
        if (data.length === 0) {
            $('#approversTableBody').html('<tr><td colspan="6" class="text-center text-muted py-4">No approvers added yet. Click "Add Approver" to add one.</td></tr>');
            return;
        }

        var html = '';
        data.forEach(function(item) {
            var statusBadge = item.is_active == 1
                ? '<span class="badge badge-light-success">Active</span>'
                : '<span class="badge badge-light-secondary">Inactive</span>';
            var finalLabel = item.is_final == 1 ? 'Yes' : '-';

            html += '<tr' + (item.is_active == 0 ? ' class="text-muted"' : '') + '>';
            html += '<td><span class="level-badge">' + item.approval_level + '</span></td>';
            html += '<td><strong>' + item.approver_name + '</strong></td>';
            html += '<td><small>' + item.approver_email + '</small></td>';
            html += '<td>' + finalLabel + '</td>';
            html += '<td>' + statusBadge + '</td>';
            html += '<td>';
            html += '<div class="d-flex gap-1">';
            @permission('update_approvers')
            html += '<a href="#" class="text-primary btn-edit-approver" title="Edit" data-id="' + item.encrypted_id + '" data-level="' + item.approval_level + '" data-final="' + item.is_final + '" data-active="' + item.is_active + '" data-name="' + item.approver_name + '"><span class="material-symbols-outlined" style="font-size:18px;">edit</span></a>';
            @endpermission
            @permission('delete_approvers')
            html += '<a href="#" class="text-danger btn-remove-approver" title="Remove" data-id="' + item.encrypted_id + '" data-name="' + item.approver_name + '"><span class="material-symbols-outlined" style="font-size:18px;">person_remove</span></a>';
            @endpermission
            html += '</div>';
            html += '</td>';
            html += '</tr>';
        });
        $('#approversTableBody').html(html);
    }

    loadApprovers();

    // Add Approver Button
    $('#addApproverBtn').on('click', function() {
        $('#approverId').val('');
        $('#approverForm')[0].reset();
        $('#approverSelect').val(null).trigger('change');
        $('#approverModalTitle').text('Add Approver');
        $('#saveApproverBtnText').text('Add');
        $('#approverSelectWrap').show();
        $('#isActiveWrap').hide();
        $('#approvalLevel').val('1');
        $('#isFinal').prop('checked', false);
    });

    // Edit Approver Button
    $(document).on('click', '.btn-edit-approver', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var level = $(this).data('level');
        var isFinal = $(this).data('final');
        var isActive = $(this).data('active');
        var name = $(this).data('name');

        $('#approverId').val(id);
        $('#approvalLevel').val(level);
        $('#isFinal').prop('checked', isFinal == 1);
        $('#isActive').val(isActive);
        $('#approverModalTitle').text('Edit Approver: ' + name);
        $('#saveApproverBtnText').text('Update');
        $('#approverSelectWrap').hide();
        $('#isActiveWrap').show();
        $('#approverModal').modal('show');
    });

    // Save Approver
    $('#saveApproverBtn').on('click', function() {
        var id = $('#approverId').val();
        var isEdit = id !== '';

        var formData = {
            approval_level: $('#approvalLevel').val(),
            is_final: $('#isFinal').is(':checked') ? 1 : 0,
            _token: '{{ csrf_token() }}'
        };

        if (!isEdit) {
            var approverData = $('#approverSelect').select2('data')[0];
            if (!approverData) {
                toastr.error('Please select an approver');
                return;
            }
            formData.approver_id = approverData.id;
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

    // Remove Approver Button
    $(document).on('click', '.btn-remove-approver', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var name = $(this).data('name');
        $('#removeApproverId').val(id);
        $('#removeApproverName').text(name);
        $('#removeApproverModal').modal('show');
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
