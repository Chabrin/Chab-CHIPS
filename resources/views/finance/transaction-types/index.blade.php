@extends('layouts.main')

@section('styles')
<style>
#txnTypeTable thead th { font-size: 0.78rem; white-space: nowrap; }
#txnTypeTable tbody td { font-size: 0.8rem; vertical-align: middle; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header card-no-border d-flex align-items-center py-2">
                <h5 class="mb-0">Transaction Types</h5>
                <div class="ms-auto">
                    @permission('create_transaction_types')
                    <button class="btn btn-primary btn-sm d-flex align-items-center gap-1" id="addTypeBtn">
                        <span class="material-symbols-outlined" style="font-size:17px;">add</span>
                        <span>Add New Type</span>
                    </button>
                    @endpermission
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive custom-scrollbar">
                    <table id="txnTypeTable" class="display" style="width:100%;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item Code</th>
                                <th>Type Name</th>
                                <th>Debit Account</th>
                                <th>Credit Account</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add / Edit Modal --}}
<div class="modal fade" id="typeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold" id="typeModalTitle">Add Transaction Type</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="typeForm">
                    <input type="hidden" id="modal_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Type Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="modal_type_name" maxlength="255" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Item Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="modal_item_code" maxlength="50" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Debit Account</label>
                            <select id="modal_debit_account_id" class="form-select form-select-sm w-100"></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Credit Account</label>
                            <select id="modal_credit_account_id" class="form-select form-select-sm w-100"></select>
                        </div>
                        <div class="col-md-12" id="statusRow" style="display:none;">
                            <label class="form-label fw-semibold">Status</label>
                            <select id="modal_status" class="form-select form-select-sm" style="max-width:200px;">
                                <option value="5">Active</option>
                                <option value="1">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="d-flex flex-wrap gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="modal_is_commissionable">
                                    <label class="form-check-label small" for="modal_is_commissionable">Commissionable</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="modal_is_recovery">
                                    <label class="form-check-label small" for="modal_is_recovery">Recovery</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="modal_is_client_expense">
                                    <label class="form-check-label small" for="modal_is_client_expense">Client Expense</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center gap-1" id="saveTypeBtn">
                    <span class="btn-save-label d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size:16px;">save</span>
                        <span id="saveBtnText">Save</span>
                    </span>
                    <span class="btn-save-spinner d-none d-flex align-items-center gap-1">
                        <span class="spinner-border spinner-border-sm"></span>
                        <span>Saving…</span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
$(document).ready(function () {
    const dataUrl   = '{{ route("ajax.finance.transaction-types.data") }}';
    const getOneUrl = '{{ url("/ajax/finance/transaction-types") }}';
    const storeUrl  = '{{ route("finance.transaction-types.store") }}';
    const updateUrl = '{{ url("/finance/transaction-types") }}';
    const acctUrl   = '{{ route("ajax.gl.accounts.search") }}';

    // ── DataTable ──────────────────────────────────────────────────────────────
    const table = $('#txnTypeTable').DataTable({
        ajax: { url: dataUrl, dataSrc: 'data' },
        columns: [
            { data: null, orderable: false, searchable: false,
              render: (d, t, r, m) => m.row + 1 },
            { data: 'item_code' },
            { data: 'name' },
            {
                data: 'debit_account_code',
                render: (d, t, row) => d
                    ? `<span style="font-size:0.8rem;">${d} — ${row.debit_account_name || ''}</span>`
                    : '<span class="text-muted">—</span>'
            },
            {
                data: 'credit_account_code',
                render: (d, t, row) => d
                    ? `<span style="font-size:0.8rem;">${d} — ${row.credit_account_name || ''}</span>`
                    : '<span class="text-muted">—</span>'
            },
            {
                data: 'current_status_id',
                render: d => d === 5
                    ? '<span class="badge bg-success-light text-success" style="font-size:0.72rem;">Active</span>'
                    : '<span class="badge bg-light text-secondary" style="font-size:0.72rem;">Inactive</span>'
            },
            {
                data: null, orderable: false, searchable: false, className: 'text-center',
                render: (d, t, row) => {
                    if (!row.can_edit) return '';
                    return `<button class="btn btn-sm btn-light edit-btn py-0 px-1" data-id="${row.id}" title="Edit">
                                <span class="material-symbols-outlined" style="font-size:17px;vertical-align:-4px;">edit_document</span>
                            </button>`;
                }
            },
        ],
        pageLength: 25,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
        order: [[1, 'asc']],
    });

    // ── Select2 helper ─────────────────────────────────────────────────────────
    function initAccountSelect(selector) {
        $(selector).select2({
            dropdownParent: $('#typeModal'),
            theme: 'default',
            width: '100%',
            placeholder: 'Search by code or name…',
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
                url: acctUrl,
                dataType: 'json',
                delay: 250,
                data: p => ({ q: p.term || '' }),
                processResults: d => ({ results: d.results }),
                cache: true,
            },
        });
    }

    initAccountSelect('#modal_debit_account_id');
    initAccountSelect('#modal_credit_account_id');

    function setAccountOption(selector, id, code, name) {
        const $s = $(selector);
        $s.empty();
        if (id) {
            const opt = new Option(code + ' — ' + name, id, true, true);
            $s.append(opt).trigger('change');
        } else {
            $s.trigger('change');
        }
    }

    // ── Open Add modal ─────────────────────────────────────────────────────────
    $('#addTypeBtn').on('click', function () {
        $('#typeModalTitle').text('Add Transaction Type');
        $('#saveBtnText').text('Save');
        $('#modal_id').val('');
        $('#typeForm')[0].reset();
        $('#modal_type_name, #modal_item_code').removeClass('is-invalid');
        setAccountOption('#modal_debit_account_id', null);
        setAccountOption('#modal_credit_account_id', null);
        $('#statusRow').hide();
        $('#typeModal').modal('show');
    });

    // ── Open Edit modal ────────────────────────────────────────────────────────
    $('#txnTypeTable').on('click', '.edit-btn', function () {
        const id = $(this).data('id');
        $('#typeModalTitle').text('Edit Transaction Type');
        $('#saveBtnText').text('Update');
        $('#modal_id').val(id);
        $('#statusRow').show();

        $.get(getOneUrl + '/' + id, function (res) {
            if (!res.success) { toastr.error('Failed to load record.'); return; }
            const d = res.data;
            $('#modal_type_name').val(d.type_name).removeClass('is-invalid');
            $('#modal_item_code').val(d.item_code).removeClass('is-invalid');
            $('#modal_status').val(d.current_status_id);
            $('#modal_is_commissionable').prop('checked', d.is_commissionable == 1);
            $('#modal_is_recovery').prop('checked', d.is_recovery == 1);
            $('#modal_is_client_expense').prop('checked', d.is_client_expense == 1);
            setAccountOption('#modal_debit_account_id',  d.debit_account_id,  d.debit_account_code,  d.debit_account_name);
            setAccountOption('#modal_credit_account_id', d.credit_account_id, d.credit_account_code, d.credit_account_name);
            $('#typeModal').modal('show');
        }).fail(() => toastr.error('Failed to load record.'));
    });

    // ── Save ───────────────────────────────────────────────────────────────────
    $('#saveTypeBtn').on('click', function () {
        const typeName = $('#modal_type_name').val().trim();
        const itemCode = $('#modal_item_code').val().trim();
        let valid = true;

        if (!typeName) {
            $('#modal_type_name').addClass('is-invalid').next('.invalid-feedback').text('Type name is required.');
            valid = false;
        } else {
            $('#modal_type_name').removeClass('is-invalid');
        }
        if (!itemCode) {
            $('#modal_item_code').addClass('is-invalid').next('.invalid-feedback').text('Item code is required.');
            valid = false;
        } else {
            $('#modal_item_code').removeClass('is-invalid');
        }
        if (!valid) return;

        const id = $('#modal_id').val();
        const isEdit = !!id;

        const payload = {
            _token:             $('meta[name="csrf-token"]').attr('content'),
            type_name:          typeName,
            item_code:          itemCode,
            debit_account_id:   $('#modal_debit_account_id').val() || '',
            credit_account_id:  $('#modal_credit_account_id').val() || '',
            is_commissionable:  $('#modal_is_commissionable').is(':checked') ? 1 : 0,
            is_recovery:        $('#modal_is_recovery').is(':checked') ? 1 : 0,
            is_client_expense:  $('#modal_is_client_expense').is(':checked') ? 1 : 0,
        };

        if (isEdit) {
            payload._method          = 'PUT';
            payload.current_status_id = $('#modal_status').val();
        }

        const url    = isEdit ? updateUrl + '/' + id : storeUrl;
        const method = 'POST';

        $('#saveTypeBtn .btn-save-label').addClass('d-none');
        $('#saveTypeBtn .btn-save-spinner').removeClass('d-none');
        $('#saveTypeBtn').prop('disabled', true);

        $.ajax({
            url, method, data: payload,
            success: function (res) {
                resetSaveBtn();
                if (res.success) {
                    toastr.success(res.message || (isEdit ? 'Updated successfully.' : 'Created successfully.'));
                    $('#typeModal').modal('hide');
                    table.ajax.reload(null, false);
                } else {
                    toastr.error(res.error || 'Save failed.');
                }
            },
            error: function (xhr) {
                resetSaveBtn();
                const errs = xhr.responseJSON?.errors;
                if (errs) {
                    toastr.error(Object.values(errs).flat().join('<br>'));
                } else {
                    toastr.error(xhr.responseJSON?.error || 'Save failed.');
                }
            },
        });

        function resetSaveBtn() {
            $('#saveTypeBtn .btn-save-label').removeClass('d-none');
            $('#saveTypeBtn .btn-save-spinner').addClass('d-none');
            $('#saveTypeBtn').prop('disabled', false);
        }
    });
});
</script>
@endsection
