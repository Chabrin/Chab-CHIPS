@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Account Classes</h5>
                        @permission('create_gl_classes')
                        <button type="button" id="addClassBtn" class="btn btn-primary btn-sm">
                            <i class="fa fa-plus"></i> Add Class
                        </button>
                        @endpermission
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table id="classesTable" class="display" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Class Name</th>
                                        <th>Balance Nature</th>
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

<!-- Class Modal -->
<div class="modal fade" id="classModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="classModalLabel">Add Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="classForm">
                <div class="modal-body">
                    <input type="hidden" id="classId">
                    <div class="mb-3">
                        <label class="form-label">Class Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="className" name="name" placeholder="e.g. Assets">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Balance Nature <span class="text-danger">*</span></label>
                        <select class="form-select" id="classBalance" name="balance_nature_id">
                            <option value="">-- Select --</option>
                            <option value="Debit">Debit</option>
                            <option value="Credit">Credit</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="classSaveBtn">
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
#classesTable tbody tr, #classesTable tbody tr.odd, #classesTable tbody tr.even {
    background: transparent !important;
}
#classesTable tbody tr:hover { background: rgba(0,0,0,0.03) !important; }
</style>
@endsection

@section('scripts')
<script>
$(document).ready(function () {
    const table = $('#classesTable').DataTable({
        processing: true,
        ajax: {
            url: '{{ route("ledger.classes.data") }}',
            dataSrc: 'data',
            error: function () { toastr.error('Failed to load classes'); }
        },
        columns: [
            { data: null, orderable: false, searchable: false, render: (d, t, r, m) => m.row + 1 },
            { data: 'name' },
            {
                data: 'balance_nature_id',
                render: (d) => d === 'Debit'
                    ? '<span class="badge bg-primary">Debit</span>'
                    : '<span class="badge bg-success">Credit</span>'
            },
            {
                data: null, orderable: false, searchable: false,
                render: function (d) {
                    let a = '';
                    if (d.can_update) a += `<a href="#" class="text-warning edit-class me-2" data-row='${JSON.stringify(d)}' title="Edit"><span class="material-symbols-outlined" style="font-size:18px;">edit_document</span></a>`;
                    if (d.can_delete) a += `<a href="#" class="text-danger delete-class" data-id="${d.encrypted_id}" data-name="${d.name}" title="Delete"><span class="material-symbols-outlined" style="font-size:18px;">delete</span></a>`;
                    return a || '-';
                }
            }
        ],
        order: [[1, 'asc']],
        pageLength: 25
    });

    function resetModal() {
        $('#classForm')[0].reset();
        $('#classId').val('');
        $('#classModalLabel').text('Add Class');
        $('.form-control,.form-select').removeClass('is-invalid');
        $('.invalid-feedback').text('');
    }

    function setLoading(on) {
        $('#classSaveBtn .btn-label').toggleClass('d-none', on);
        $('#classSaveBtn .btn-spinner').toggleClass('d-none', !on);
        $('#classSaveBtn').prop('disabled', on);
    }

    function showErrors(errors) {
        $.each(errors, function (field, msgs) {
            const el = $(`[name="${field}"]`);
            el.addClass('is-invalid');
            el.siblings('.invalid-feedback').text(msgs[0]);
        });
    }

    $('#addClassBtn').click(function () { resetModal(); $('#classModal').modal('show'); });

    $('#classesTable').on('click', '.edit-class', function (e) {
        e.preventDefault();
        const d = $(this).data('row');
        resetModal();
        $('#classId').val(d.encrypted_id);
        $('#className').val(d.name !== '-' ? d.name : '');
        $('#classBalance').val(d.balance_nature_id !== '-' ? d.balance_nature_id : '');
        $('#classModalLabel').text('Edit Class');
        $('#classModal').modal('show');
    });

    $('#classForm').submit(function (e) {
        e.preventDefault();
        const id = $('#classId').val();
        const url = id
            ? '{{ route("ledger.classes.update", "PLACEHOLDER") }}'.replace('PLACEHOLDER', id)
            : '{{ route("ledger.classes.store") }}';
        setLoading(true);
        $('.form-control,.form-select').removeClass('is-invalid');
        $('.invalid-feedback').text('');

        $.ajax({
            url, method: 'POST',
            data: $(this).serialize(),
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (r) {
                setLoading(false);
                $('#classModal').modal('hide');
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

    $('#classesTable').on('click', '.delete-class', function (e) {
        e.preventDefault();
        const id = $(this).data('id'), name = $(this).data('name');
        if (!confirm(`Delete class "${name}"?`)) return;
        $.ajax({
            url: '{{ route("ledger.classes.delete", "PLACEHOLDER") }}'.replace('PLACEHOLDER', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: r => { table.ajax.reload(); toastr.success(r.message); },
            error: xhr => toastr.error(xhr.responseJSON?.message || 'Failed to delete class')
        });
    });
});
</script>
@endsection
