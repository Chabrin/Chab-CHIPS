@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Chart of Accounts</h5>
                        @permission('create_gl_accounts')
                        <button type="button" id="addAccountBtn" class="btn btn-primary btn-sm">
                            <i class="fa fa-plus"></i> Add Account
                        </button>
                        @endpermission
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table id="accountsTable" class="display" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Code</th>
                                        <th>Account Name</th>
                                        <th>Category</th>
                                        <th>Group</th>
                                        <th>Class</th>
                                        <th>Balance</th>
                                        <th>Status</th>
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

<!-- Account Modal -->
<div class="modal fade" id="accountModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="accountModalLabel">Add Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="accountForm">
                <div class="modal-body">
                    <input type="hidden" id="accountId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Account Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="accountCode" name="account_code" placeholder="e.g. 1001">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="accountName" name="account_name" placeholder="e.g. Cash at Bank">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Class <span class="text-danger">*</span></label>
                            <select class="form-select" id="accountClassId" name="class_id">
                                <option value="">-- Select Class --</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Group <span class="text-danger">*</span></label>
                            <select class="form-select" id="accountGroupId" name="account_group_id" disabled>
                                <option value="">-- Select Class first --</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="accountCategoryId" name="category_id" disabled>
                                <option value="">-- Select Group first --</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Balance Nature <span class="text-danger">*</span></label>
                            <select class="form-select" id="accountNormally" name="normally">
                                <option value="">-- Select --</option>
                                <option value="Debit">Debit</option>
                                <option value="Credit">Credit</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="accountStatus" name="current_status_id">
                                <option value="1">Active</option>
                                <option value="2">Inactive</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="accountSaveBtn">
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
#accountsTable tbody tr, #accountsTable tbody tr.odd, #accountsTable tbody tr.even {
    background: transparent !important;
}
#accountsTable tbody tr:hover { background: rgba(0,0,0,0.03) !important; }
</style>
@endsection

@section('scripts')
<script>
$(document).ready(function () {
    // Load classes dropdown once
    $.get('{{ route("ledger.classes.dropdown") }}', function (data) {
        data.forEach(c => $('#accountClassId').append(`<option value="${c.id}">${c.name}</option>`));
    });

    // Class → groups cascade
    $('#accountClassId').on('change', function () {
        const classId = $(this).val();
        $('#accountGroupId').html('<option value="">-- Loading... --</option>').prop('disabled', true);
        $('#accountCategoryId').html('<option value="">-- Select Group first --</option>').prop('disabled', true);
        if (!classId) { $('#accountGroupId').html('<option value="">-- Select Class first --</option>'); return; }
        $.get('{{ route("ledger.groups.dropdown") }}', { class_id: classId }, function (data) {
            const sel = $('#accountGroupId');
            sel.html('<option value="">-- Select Group --</option>');
            data.forEach(g => sel.append(`<option value="${g.id}">${g.name}</option>`));
            sel.prop('disabled', false);
        });
    });

    // Group → categories cascade
    $('#accountGroupId').on('change', function () {
        const groupId = $(this).val();
        $('#accountCategoryId').html('<option value="">-- Loading... --</option>').prop('disabled', true);
        if (!groupId) { $('#accountCategoryId').html('<option value="">-- Select Group first --</option>'); return; }
        $.get('{{ route("ledger.categories.dropdown") }}', { group_id: groupId }, function (data) {
            const sel = $('#accountCategoryId');
            sel.html('<option value="">-- Select Category --</option>');
            data.forEach(c => sel.append(`<option value="${c.id}">${c.name}</option>`));
            sel.prop('disabled', false);
        });
    });

    const table = $('#accountsTable').DataTable({
        processing: true,
        ajax: {
            url: '{{ route("ledger.accounts.data") }}',
            dataSrc: 'data',
            error: function () { toastr.error('Failed to load accounts'); }
        },
        columns: [
            { data: null, orderable: false, searchable: false, render: (d, t, r, m) => m.row + 1 },
            { data: 'account_code' },
            { data: 'account_name' },
            { data: 'category_name' },
            { data: 'group_name' },
            { data: 'class_name' },
            { data: 'normally' },
            { data: 'status' },
            {
                data: null, orderable: false, searchable: false,
                render: function (d) {
                    let a = '';
                    if (d.can_update) a += `<a href="#" class="edit-account me-2" data-row='${JSON.stringify(d)}' title="Edit"><span class="material-symbols-outlined" style="font-size:18px;">edit_square</span></a>`;
                    if (d.can_delete) a += `<a href="#" class="delete-account" data-id="${d.encrypted_id}" data-name="${d.account_name}" title="Delete"><span class="material-symbols-outlined" style="font-size:18px;">scan_delete</span></a>`;
                    return a || '-';
                }
            }
        ],
        order: [[1, 'asc']],
        pageLength: 50
    });

    function resetModal() {
        $('#accountForm')[0].reset();
        $('#accountId').val('');
        $('#accountClassId').val('');
        $('#accountGroupId').html('<option value="">-- Select Class first --</option>').prop('disabled', true);
        $('#accountCategoryId').html('<option value="">-- Select Group first --</option>').prop('disabled', true);
        $('#accountStatus').val('1');
        $('#accountModalLabel').text('Add Account');
        $('.form-control,.form-select').removeClass('is-invalid');
        $('.invalid-feedback').text('');
    }

    function setLoading(on) {
        $('#accountSaveBtn .btn-label').toggleClass('d-none', on);
        $('#accountSaveBtn .btn-spinner').toggleClass('d-none', !on);
        $('#accountSaveBtn').prop('disabled', on);
    }

    function showErrors(errors) {
        $.each(errors, function (field, msgs) {
            const el = $(`[name="${field}"]`);
            el.addClass('is-invalid');
            el.siblings('.invalid-feedback').text(msgs[0]);
        });
    }

    $('#addAccountBtn').click(function () { resetModal(); $('#accountModal').modal('show'); });

    $('#accountsTable').on('click', '.edit-account', function (e) {
        e.preventDefault();
        const d = $(this).data('row');
        resetModal();
        $('#accountId').val(d.encrypted_id);
        $('#accountCode').val(d.account_code !== '-' ? d.account_code : '');
        $('#accountName').val(d.account_name !== '-' ? d.account_name : '');
        $('#accountNormally').val(d.normally !== '-' ? d.normally : '');
        $('#accountStatus').val(d.current_status_id);
        $('#accountModalLabel').text('Edit Account');

        // Cascading selects: class → group → category
        $('#accountClassId').val(d.class_id).trigger('change');
        setTimeout(() => {
            $('#accountGroupId').val(d.account_group_id).trigger('change');
            setTimeout(() => $('#accountCategoryId').val(d.category_id), 400);
        }, 400);

        $('#accountModal').modal('show');
    });

    $('#accountForm').submit(function (e) {
        e.preventDefault();
        const id = $('#accountId').val();
        const url = id
            ? '{{ route("ledger.accounts.update", "PLACEHOLDER") }}'.replace('PLACEHOLDER', id)
            : '{{ route("ledger.accounts.store") }}';
        setLoading(true);
        $('.form-control,.form-select').removeClass('is-invalid');
        $('.invalid-feedback').text('');

        $.ajax({
            url, method: 'POST',
            data: $(this).serialize(),
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (r) {
                setLoading(false);
                $('#accountModal').modal('hide');
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

    $('#accountsTable').on('click', '.delete-account', function (e) {
        e.preventDefault();
        const id = $(this).data('id'), name = $(this).data('name');
        if (!confirm(`Delete account "${name}"?`)) return;
        $.ajax({
            url: '{{ route("ledger.accounts.delete", "PLACEHOLDER") }}'.replace('PLACEHOLDER', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: r => { table.ajax.reload(); toastr.success(r.message); },
            error: xhr => toastr.error(xhr.responseJSON?.message || 'Failed to delete account')
        });
    });
});
</script>
@endsection
