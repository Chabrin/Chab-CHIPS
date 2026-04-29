@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Account Groups</h5>
                        @permission('create_gl_groups')
                        <button type="button" id="addGroupBtn" class="btn btn-primary btn-sm">
                            <i class="fa fa-plus"></i> Add Group
                        </button>
                        @endpermission
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table id="groupsTable" class="display" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Group Name</th>
                                        <th>Class</th>
                                        <th style="width:90px;">Actions</th>
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

<!-- Group Modal -->
<div class="modal fade" id="groupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="groupModalLabel">Add Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="groupForm">
                <div class="modal-body">
                    <input type="hidden" id="groupId">
                    <div class="mb-3">
                        <label class="form-label">Group Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="groupName" name="group_name" placeholder="e.g. Current Assets">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Class <span class="text-danger">*</span></label>
                        <select class="form-select" id="groupClassId" name="class_id">
                            <option value="">-- Select Class --</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="groupSaveBtn">
                        <span class="btn-label">Save</span>
                        <span class="btn-spinner d-none">
                            <span class="spinner-border spinner-border-sm"></span> Saving...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
#groupsTable tbody tr, #groupsTable tbody tr.odd, #groupsTable tbody tr.even {
    background: transparent !important;
}
#groupsTable tbody tr:hover { background: rgba(0,0,0,0.03) !important; }
</style>
@endsection

@section('scripts')
<script>
$(document).ready(function () {
    // Load classes dropdown once
    $.get('{{ route("ledger.classes.dropdown") }}', function (data) {
        const sel = $('#groupClassId');
        data.forEach(c => sel.append(`<option value="${c.id}">${c.name}</option>`));
    });

    const table = $('#groupsTable').DataTable({
        processing: true,
        ajax: {
            url: '{{ route("ledger.groups.data") }}',
            dataSrc: 'data',
            error: function () { toastr.error('Failed to load groups'); }
        },
        columns: [
            { data: null, orderable: false, searchable: false, render: (d, t, r, m) => m.row + 1 },
            { data: 'group_name' },
            { data: 'class_name' },
            {
                data: null, orderable: false, searchable: false,
                render: function (d) {
                    let a = '';
                    if (d.can_update) a += `<a href="#" class="text-warning edit-group me-2" data-row='${JSON.stringify(d)}' title="Edit"><span class="material-symbols-outlined" style="font-size:18px;">edit_document</span></a>`;
                    if (d.can_delete) a += `<a href="#" class="text-danger delete-group" data-id="${d.encrypted_id}" data-name="${d.group_name}" title="Delete"><span class="material-symbols-outlined" style="font-size:18px;">delete</span></a>`;
                    return a || '-';
                }
            }
        ],
        order: [[1, 'asc']],
        pageLength: 25
    });

    function resetModal() {
        $('#groupForm')[0].reset();
        $('#groupId').val('');
        $('#groupModalLabel').text('Add Group');
        $('.form-control,.form-select').removeClass('is-invalid');
        $('.invalid-feedback').text('');
    }

    function setLoading(on) {
        $('#groupSaveBtn .btn-label').toggleClass('d-none', on);
        $('#groupSaveBtn .btn-spinner').toggleClass('d-none', !on);
        $('#groupSaveBtn').prop('disabled', on);
    }

    function showErrors(errors) {
        $.each(errors, function (field, msgs) {
            const el = $(`[name="${field}"]`);
            el.addClass('is-invalid');
            el.siblings('.invalid-feedback').text(msgs[0]);
        });
    }

    $('#addGroupBtn').click(function () { resetModal(); $('#groupModal').modal('show'); });

    $('#groupsTable').on('click', '.edit-group', function (e) {
        e.preventDefault();
        const d = $(this).data('row');
        resetModal();
        $('#groupId').val(d.encrypted_id);
        $('#groupName').val(d.group_name !== '-' ? d.group_name : '');
        $('#groupClassId').val(d.class_id);
        $('#groupModalLabel').text('Edit Group');
        $('#groupModal').modal('show');
    });

    $('#groupForm').submit(function (e) {
        e.preventDefault();
        const id = $('#groupId').val();
        const url = id
            ? '{{ route("ledger.groups.update", "PLACEHOLDER") }}'.replace('PLACEHOLDER', id)
            : '{{ route("ledger.groups.store") }}';
        setLoading(true);
        $('.form-control,.form-select').removeClass('is-invalid');
        $('.invalid-feedback').text('');

        $.ajax({
            url, method: 'POST',
            data: $(this).serialize(),
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (r) {
                setLoading(false);
                $('#groupModal').modal('hide');
                table.ajax.reload();
                toastr.success(r.message);
            },
            error: function (xhr) {
                setLoading(false);
                if (xhr.status === 422) showErrors(xhr.responseJSON.errors);
                else toastr.error(xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });

    $('#groupsTable').on('click', '.delete-group', function (e) {
        e.preventDefault();
        const id = $(this).data('id'), name = $(this).data('name');
        if (!confirm(`Delete group "${name}"?`)) return;
        $.ajax({
            url: '{{ route("ledger.groups.delete", "PLACEHOLDER") }}'.replace('PLACEHOLDER', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: r => { table.ajax.reload(); toastr.success(r.message); },
            error: xhr => toastr.error(xhr.responseJSON?.message || 'Failed to delete group')
        });
    });
});
</script>
@endsection
