@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">GL Periods</h5>
                        @permission('create_gl_periods')
                        <button type="button" id="addPeriodBtn" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                            <span class="material-symbols-outlined" style="font-size:18px;">add_circle</span>
                            <span>Add Period</span>
                        </button>
                        @endpermission
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table id="periodsTable" class="display" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Period Start</th>
                                        <th>Period End</th>
                                        <th>Added By</th>
                                        <th>Added On</th>
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

<!-- Add / Edit Period Modal -->
<div class="modal fade" id="periodModal" tabindex="-1" aria-labelledby="periodModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="periodModalLabel">Add Period</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="periodForm">
                <div class="modal-body">
                    <input type="hidden" id="periodId">
                    <div class="mb-3">
                        <label class="form-label">Period Start <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="periodStart" name="period_start">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Period End <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="periodEnd" name="period_end">
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                        <span class="ms-1">Cancel</span>
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center" id="periodSaveBtn">
                        <span class="btn-label d-flex align-items-center gap-1">
                            <span class="material-symbols-outlined" style="font-size:18px;">check_circle</span>
                            <span>Save</span>
                        </span>
                        <span class="btn-spinner d-none d-flex align-items-center gap-1">
                            <span class="spinner-border spinner-border-sm"></span>
                            <span>Saving...</span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deletePeriodModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Delete this GL period?</p>
                <p class="text-muted small mb-0" id="deletePeriodLabel"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm d-flex align-items-center gap-1" id="confirmDeleteBtn">
                    <span class="btn-label d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size:18px;">scan_delete</span>
                        <span>Delete</span>
                    </span>
                    <span class="btn-spinner d-none d-flex align-items-center gap-1">
                        <span class="spinner-border spinner-border-sm"></span>
                        <span>Deleting...</span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
#periodsTable tbody tr, #periodsTable tbody tr.odd, #periodsTable tbody tr.even {
    background: transparent !important;
}
#periodsTable tbody tr:hover { background: rgba(0,0,0,0.03) !important; }
</style>
@endsection

@section('scripts')
<script>
$(document).ready(function () {
    const storeUrl  = '{{ route("gl.periods.store") }}';
    const updateUrl = '{{ route("gl.periods.update", "PLACEHOLDER") }}';
    const deleteUrl = '{{ route("gl.periods.delete", "PLACEHOLDER") }}';
    const dataUrl   = '{{ route("gl.periods.data") }}';

    // ── DataTable ──────────────────────────────────────────────────────────────
    const table = $('#periodsTable').DataTable({
        processing: true,
        ajax: {
            url: dataUrl,
            dataSrc: 'data',
            error: function () { toastr.error('Failed to load GL periods'); }
        },
        columns: [
            { data: null, orderable: false, searchable: false, render: (d, t, r, m) => m.row + 1 },
            { data: 'period_start_display' },
            { data: 'period_end_display' },
            {
                data: 'added_by_name',
                render: function (d) {
                    return d && d !== '-'
                        ? '<span class="fw-semibold" style="font-size:0.8rem;">' + d + '</span>'
                        : '<span class="text-muted">—</span>';
                }
            },
            {
                data: 'added_on_display',
                render: function (d) {
                    return d && d !== '-'
                        ? '<small class="text-muted">' + d + '</small>'
                        : '<span class="text-muted">—</span>';
                }
            },
            {
                data: null, orderable: false, searchable: false,
                render: function (d) {
                    let a = '';
                    if (d.can_update) {
                        a += `<a href="#" class="edit-period me-2" data-row='${JSON.stringify(d)}' title="Edit">
                                <span class="material-symbols-outlined" style="font-size:18px; color:#3598db;">edit_square</span>
                              </a>`;
                    }
                    if (d.can_delete) {
                        a += `<a href="#" class="delete-period" data-id="${d.encrypted_id}"
                                data-label="${d.period_start_display} – ${d.period_end_display}" title="Delete">
                                <span class="material-symbols-outlined" style="font-size:18px; color:#dc3545;">scan_delete</span>
                              </a>`;
                    }
                    return a || '-';
                }
            }
        ],
        order: [[1, 'desc']],
        pageLength: 25
    });

    // ── Helpers ────────────────────────────────────────────────────────────────
    function resetModal() {
        $('#periodForm')[0].reset();
        $('#periodId').val('');
        $('#periodModalLabel').text('Add Period');
        $('#periodForm .form-control').removeClass('is-invalid');
        $('#periodForm .invalid-feedback').text('');
    }

    function setSaveLoading(on) {
        $('#periodSaveBtn .btn-label').toggleClass('d-none', on);
        $('#periodSaveBtn .btn-spinner').toggleClass('d-none', !on);
        $('#periodSaveBtn').prop('disabled', on);
    }

    function setDeleteLoading(on) {
        $('#confirmDeleteBtn .btn-label').toggleClass('d-none', on);
        $('#confirmDeleteBtn .btn-spinner').toggleClass('d-none', !on);
        $('#confirmDeleteBtn').prop('disabled', on);
    }

    function showErrors(errors) {
        $.each(errors, function (field, msgs) {
            const el = $('#periodForm [name="' + field + '"]');
            el.addClass('is-invalid');
            el.siblings('.invalid-feedback').text(msgs[0]);
        });
    }

    // ── Add ────────────────────────────────────────────────────────────────────
    $('#addPeriodBtn').on('click', function () {
        resetModal();
        $('#periodModal').modal('show');
    });

    // ── Edit ───────────────────────────────────────────────────────────────────
    $('#periodsTable').on('click', '.edit-period', function (e) {
        e.preventDefault();
        const d = $(this).data('row');
        resetModal();
        $('#periodId').val(d.encrypted_id);
        $('#periodStart').val(d.period_start);
        $('#periodEnd').val(d.period_end);
        $('#periodModalLabel').text('Edit Period');
        $('#periodModal').modal('show');
    });

    // ── Save (Add / Edit) ──────────────────────────────────────────────────────
    $('#periodForm').on('submit', function (e) {
        e.preventDefault();
        const id  = $('#periodId').val();
        const url = id ? updateUrl.replace('PLACEHOLDER', id) : storeUrl;

        $('#periodForm .form-control').removeClass('is-invalid');
        $('#periodForm .invalid-feedback').text('');
        setSaveLoading(true);

        $.ajax({
            url, method: 'POST',
            data: $(this).serialize(),
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (r) {
                setSaveLoading(false);
                $('#periodModal').modal('hide');
                table.ajax.reload(null, false);
                toastr.success(r.message);
            },
            error: function (xhr) {
                setSaveLoading(false);
                if (xhr.status === 422) {
                    showErrors(xhr.responseJSON.errors);
                } else {
                    toastr.error(xhr.responseJSON?.message || 'An error occurred');
                }
            }
        });
    });

    // ── Delete ─────────────────────────────────────────────────────────────────
    let pendingDeleteId = null;

    $('#periodsTable').on('click', '.delete-period', function (e) {
        e.preventDefault();
        pendingDeleteId = $(this).data('id');
        $('#deletePeriodLabel').text($(this).data('label'));
        setDeleteLoading(false);
        $('#deletePeriodModal').modal('show');
    });

    $('#confirmDeleteBtn').on('click', function () {
        if (!pendingDeleteId) return;
        setDeleteLoading(true);

        $.ajax({
            url: deleteUrl.replace('PLACEHOLDER', pendingDeleteId),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (r) {
                setDeleteLoading(false);
                $('#deletePeriodModal').modal('hide');
                table.ajax.reload(null, false);
                toastr.success(r.message);
                pendingDeleteId = null;
            },
            error: function (xhr) {
                setDeleteLoading(false);
                toastr.error(xhr.responseJSON?.message || 'Failed to delete period');
            }
        });
    });

    $('#periodModal').on('hidden.bs.modal', function () { resetModal(); });
});
</script>
@endsection
