@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Company Bank Accounts</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button class="btn btn-primary btn-sm d-flex align-items-center" id="addAccountBtn">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Account</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="accountsTable" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Account Name</th>
                                        <th>Account Number</th>
                                        <th>Bank</th>
                                        <th>Branch</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Action</th>
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

<!-- Add / Edit Account Modal -->
<div class="modal fade" id="accountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="accountModalTitle">Add Bank Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="accountForm">
                    <input type="hidden" id="accountId" name="id">

                    <div class="mb-3">
                        <label class="form-label">Account Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="account_name" id="account_name" placeholder="e.g. Operations Current Account" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Account Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="account_number" id="account_number" placeholder="e.g. 1234567890" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Bank <span class="text-danger">*</span></label>
                        <select class="form-control" name="bank_id" id="bank_id" required>
                            <option value="">— Select Bank —</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <select class="form-control" name="branch_id" id="branch_id">
                            <option value="">— Select Branch (optional) —</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Account Type</label>
                        <input type="text" class="form-control" name="account_type" id="account_type" placeholder="e.g. Current, Savings">
                    </div>

                    <div class="mb-3" id="statusRow" style="display:none;">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="current_status_id" id="current_status_id">
                            <option value="1">Active</option>
                            <option value="2">Inactive</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="notes" rows="2" placeholder="Optional notes"></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-success d-flex align-items-center" id="saveAccountBtn">
                            <span class="material-symbols-outlined" id="saveAccountIcon">check_circle</span>
                            <span class="ms-1" id="saveAccountText">Save</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
$(function () {

    // ── DataTable ──────────────────────────────────────────────────────────────
    var table = $('#accountsTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ url('/settings/company-bank-accounts/data') }}",
            dataSrc: 'data'
        },
        columns: [
            { data: null, render: function(d, t, r, m) { return m.row + 1; }, orderable: false },
            { data: 'account_name' },
            { data: 'account_number' },
            { data: 'bank_name', defaultContent: '—' },
            { data: 'branch_name', defaultContent: '—' },
            { data: 'account_type', defaultContent: '—' },
            {
                data: null,
                render: function(d, t, r) {
                    return '<span class="badge ' + r.status_badge + '">' + r.status_label + '</span>';
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(d, t, r) {
                    return '<div class="d-flex flex-row gap-1">' +
                        '<button class="btn btn-xs btn-light edit-account d-flex align-items-center" ' +
                            'data-row=\'' + JSON.stringify(r) + '\' style="padding:2px 6px;">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>' +
                        '</button>' +
                        '<button class="btn btn-xs btn-danger delete-account d-flex align-items-center" ' +
                            'data-id="' + r.id + '" style="padding:2px 6px;">' +
                            '<span class="material-symbols-outlined del-icon" style="font-size:18px;">delete_sweep</span>' +
                            '<span class="spinner-border spinner-border-sm del-spin" style="display:none;"></span>' +
                        '</button>' +
                    '</div>';
                }
            }
        ],
        paging: true,
        info: true,
        lengthChange: true,
        lengthMenu: [[10,25,50,-1],[10,25,50,'All']]
    });

    // ── Load Banks Dropdown ────────────────────────────────────────────────────
    function loadBanks(selectedId) {
        $.get("{{ url('/ajax/company-bank-accounts/banks') }}", function(res) {
            $('#bank_id').empty().append('<option value="">— Select Bank —</option>');
            (res.banks || []).forEach(function(b) {
                $('#bank_id').append('<option value="' + b.id + '"' + (b.id == selectedId ? ' selected' : '') + '>' + b.bank_name + '</option>');
            });
            if (selectedId) {
                loadBranches(selectedId, null);
            }
        });
    }

    // ── Load Branches Dropdown ─────────────────────────────────────────────────
    function loadBranches(bankId, selectedId) {
        $('#branch_id').empty().append('<option value="">— Select Branch (optional) —</option>');
        if (!bankId) return;
        $.get("{{ url('/ajax/company-bank-accounts/branches') }}", { bank_id: bankId }, function(res) {
            (res.branches || []).forEach(function(b) {
                var label = b.branch_name + (b.branch_code ? ' (' + b.branch_code + ')' : '');
                $('#branch_id').append('<option value="' + b.id + '"' + (b.id == selectedId ? ' selected' : '') + '>' + label + '</option>');
            });
        });
    }

    $('#bank_id').on('change', function() {
        loadBranches($(this).val(), null);
    });

    // ── Add Button ─────────────────────────────────────────────────────────────
    $('#addAccountBtn').on('click', function() {
        $('#accountForm')[0].reset();
        $('#accountId').val('');
        $('#accountModalTitle').text('Add Bank Account');
        $('#statusRow').hide();
        loadBanks(null);
        $('#accountModal').modal('show');
    });

    // ── Edit Button ────────────────────────────────────────────────────────────
    $(document).on('click', '.edit-account', function() {
        var r = JSON.parse($(this).data('row'));
        $('#accountId').val(r.id);
        $('#account_name').val(r.account_name);
        $('#account_number').val(r.account_number);
        $('#account_type').val(r.account_type || '');
        $('#notes').val(r.notes || '');
        $('#accountModalTitle').text('Edit Bank Account');
        $('#statusRow').show();
        loadBanks(r.bank_id);
        // branch is loaded inside loadBanks → loadBranches chain; pre-select handled there
        setTimeout(function() { $('#branch_id option[value="' + r.branch_id + '"]').prop('selected', true); }, 600);
        $('#accountModal').modal('show');
    });

    // ── Form Submit (Add / Edit) ───────────────────────────────────────────────
    $('#accountForm').on('submit', function(e) {
        e.preventDefault();
        var id  = $('#accountId').val();
        var url = id
            ? "{{ url('/settings/company-bank-accounts/update') }}/" + id
            : "{{ url('/settings/company-bank-accounts/store') }}";

        var $btn  = $('#saveAccountBtn');
        var $icon = $('#saveAccountIcon');
        var $text = $('#saveAccountText');
        $icon.text('sync').addClass('spin');
        $text.text('Saving...');
        $btn.prop('disabled', true);

        $.ajax({
            url: url,
            type: 'POST',
            data: $(this).serialize() + '&_token={{ csrf_token() }}',
            success: function(res) {
                $icon.text('check_circle').removeClass('spin');
                $text.text('Save');
                $btn.prop('disabled', false);
                if (res.success) {
                    toastr.success('Saved successfully');
                    $('#accountModal').modal('hide');
                    $('#accountForm')[0].reset();
                    table.ajax.reload(null, false);
                } else {
                    toastr.error(res.error || 'Error saving account');
                }
            },
            error: function(xhr) {
                $icon.text('check_circle').removeClass('spin');
                $text.text('Save');
                $btn.prop('disabled', false);
                var msg = 'Validation error';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).join('\n');
                } else if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                }
                toastr.error(msg);
            }
        });
    });

    // ── Delete ─────────────────────────────────────────────────────────────────
    $(document).on('click', '.delete-account', function() {
        if (!confirm('Delete this bank account?')) return;
        var $btn  = $(this);
        var $icon = $btn.find('.del-icon');
        var $spin = $btn.find('.del-spin');
        $icon.hide(); $spin.show(); $btn.prop('disabled', true);

        $.ajax({
            url: "{{ url('/settings/company-bank-accounts/delete') }}/" + $btn.data('id'),
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
                $icon.show(); $spin.hide(); $btn.prop('disabled', false);
                if (res.success) {
                    toastr.success('Deleted');
                    table.ajax.reload(null, false);
                } else {
                    toastr.error(res.error || 'Error deleting');
                }
            },
            error: function(xhr) {
                $icon.show(); $spin.hide(); $btn.prop('disabled', false);
                var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Delete failed';
                toastr.error(msg);
            }
        });
    });

    // ── Reset modal on close ───────────────────────────────────────────────────
    $('#accountModal').on('hidden.bs.modal', function() {
        $('#accountForm')[0].reset();
        $('#accountId').val('');
        $('#statusRow').hide();
        $('#branch_id').empty().append('<option value="">— Select Branch (optional) —</option>');
    });
});
</script>
