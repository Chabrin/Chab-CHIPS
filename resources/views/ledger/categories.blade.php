@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Account Categories</h5>
                        @permission('create_gl_categories')
                        <button type="button" id="addCatBtn" class="btn btn-primary btn-sm">
                            <i class="fa fa-plus"></i> Add Category
                        </button>
                        @endpermission
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table id="categoriesTable" class="display" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Category Name</th>
                                        <th>Group</th>
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

<!-- Category Modal -->
<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="catModalLabel">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="catForm">
                <div class="modal-body">
                    <input type="hidden" id="catId">
                    <div class="mb-3">
                        <label class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="catName" name="category_name" placeholder="e.g. Trade Receivables">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Class <span class="text-danger">*</span></label>
                        <select class="form-select" id="catClassId">
                            <option value="">-- Select Class --</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Group <span class="text-danger">*</span></label>
                        <select class="form-select" id="catGroupId" name="account_group_id" disabled>
                            <option value="">-- Select Class first --</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="catSaveBtn">
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
#categoriesTable tbody tr, #categoriesTable tbody tr.odd, #categoriesTable tbody tr.even {
    background: transparent !important;
}
#categoriesTable tbody tr:hover { background: rgba(0,0,0,0.03) !important; }
</style>
@endsection

@section('scripts')
<script>
$(document).ready(function () {
    // Populate classes dropdown once
    $.get('{{ route("ledger.classes.dropdown") }}', function (data) {
        data.forEach(c => $('#catClassId').append(`<option value="${c.id}">${c.name}</option>`));
    });

    // When class changes, reload groups
    $('#catClassId').on('change', function () {
        const classId = $(this).val();
        const sel = $('#catGroupId');
        sel.html('<option value="">-- Loading... --</option>').prop('disabled', true);
        if (!classId) { sel.html('<option value="">-- Select Class first --</option>'); return; }
        $.get('{{ route("ledger.groups.dropdown") }}', { class_id: classId }, function (data) {
            sel.html('<option value="">-- Select Group --</option>');
            data.forEach(g => sel.append(`<option value="${g.id}">${g.name}</option>`));
            sel.prop('disabled', false);
        });
    });

    const table = $('#categoriesTable').DataTable({
        processing: true,
        ajax: {
            url: '{{ route("ledger.categories.data") }}',
            dataSrc: 'data',
            error: function () { toastr.error('Failed to load categories'); }
        },
        columns: [
            { data: null, orderable: false, searchable: false, render: (d, t, r, m) => m.row + 1 },
            { data: 'category_name' },
            { data: 'group_name' },
            { data: 'class_name' },
            {
                data: null, orderable: false, searchable: false,
                render: function (d) {
                    let a = '';
                    if (d.can_update) a += `<a href="#" class="edit-cat me-2" data-row='${JSON.stringify(d)}' title="Edit"><span class="material-symbols-outlined" style="font-size:18px;">edit_square</span></a>`;
                    if (d.can_delete) a += `<a href="#" class="delete-cat" data-id="${d.encrypted_id}" data-name="${d.category_name}" title="Delete"><span class="material-symbols-outlined" style="font-size:18px;">scan_delete</span></a>`;
                    return a || '-';
                }
            }
        ],
        order: [[1, 'asc']],
        pageLength: 25
    });

    function resetModal() {
        $('#catForm')[0].reset();
        $('#catId').val('');
        $('#catClassId').val('');
        $('#catGroupId').html('<option value="">-- Select Class first --</option>').prop('disabled', true);
        $('#catModalLabel').text('Add Category');
        $('.form-control,.form-select').removeClass('is-invalid');
        $('.invalid-feedback').text('');
    }

    function setLoading(on) {
        $('#catSaveBtn .btn-label').toggleClass('d-none', on);
        $('#catSaveBtn .btn-spinner').toggleClass('d-none', !on);
        $('#catSaveBtn').prop('disabled', on);
    }

    function showErrors(errors) {
        $.each(errors, function (field, msgs) {
            const el = $(`[name="${field}"]`);
            el.addClass('is-invalid');
            el.siblings('.invalid-feedback').text(msgs[0]);
        });
    }

    $('#addCatBtn').click(function () { resetModal(); $('#catModal').modal('show'); });

    $('#categoriesTable').on('click', '.edit-cat', function (e) {
        e.preventDefault();
        const d = $(this).data('row');
        resetModal();
        $('#catId').val(d.encrypted_id);
        $('#catName').val(d.category_name !== '-' ? d.category_name : '');
        $('#catModalLabel').text('Edit Category');

        // Set class → trigger group reload → then set group
        $('#catClassId').val(d.class_id).trigger('change');
        // Wait for groups to load then set selected group
        setTimeout(() => $('#catGroupId').val(d.account_group_id), 400);
        $('#catModal').modal('show');
    });

    $('#catForm').submit(function (e) {
        e.preventDefault();
        const id = $('#catId').val();
        const url = id
            ? '{{ route("ledger.categories.update", "PLACEHOLDER") }}'.replace('PLACEHOLDER', id)
            : '{{ route("ledger.categories.store") }}';
        setLoading(true);
        $('.form-control,.form-select').removeClass('is-invalid');
        $('.invalid-feedback').text('');

        $.ajax({
            url, method: 'POST',
            data: $(this).serialize(),
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (r) {
                setLoading(false);
                $('#catModal').modal('hide');
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

    $('#categoriesTable').on('click', '.delete-cat', function (e) {
        e.preventDefault();
        const id = $(this).data('id'), name = $(this).data('name');
        if (!confirm(`Delete category "${name}"?`)) return;
        $.ajax({
            url: '{{ route("ledger.categories.delete", "PLACEHOLDER") }}'.replace('PLACEHOLDER', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: r => { table.ajax.reload(); toastr.success(r.message); },
            error: xhr => toastr.error(xhr.responseJSON?.message || 'Failed to delete category')
        });
    });
});
</script>
@endsection
