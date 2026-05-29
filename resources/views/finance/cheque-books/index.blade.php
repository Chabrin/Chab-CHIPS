@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Cheque Books</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button class="btn btn-primary btn-sm d-flex align-items-center" id="addBookBtn">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Cheque Book</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="chequeBooksTable" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Bank Account</th>
                                        <th>Bank</th>
                                        <th>First Cheque No.</th>
                                        <th class="text-center">Total</th>
                                        <th>Created</th>
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

<!-- Add Cheque Book Modal -->
<div class="modal fade" id="addBookModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Cheque Book</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="bookForm">

                    <div class="mb-3">
                        <label class="form-label">Bank Account <span class="text-danger">*</span></label>
                        <select class="form-control" name="bank_account_id" id="bank_account_id" required>
                            <option value="">— Select Bank Account —</option>
                        </select>
                        <small class="text-muted">Only active company bank accounts are listed.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">First Cheque Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="first_cheque_number" id="first_cheque_number"
                            placeholder="e.g. 100200 or ABC001050" required>
                        <small class="text-muted">All subsequent cheque numbers will be auto-generated from this.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Number of Leaves <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="leafs" id="leafs"
                            min="1" max="500" placeholder="e.g. 50" required>
                        <small class="text-muted">Maximum 500 per book.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Book Reference <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="reference_number" id="reference_number"
                            placeholder="e.g. KB001" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Cheque Type <span class="text-danger">*</span></label>
                        <select class="form-control" name="cheque_type" id="cheque_type" required>
                            <option value="">— Select Cheque Type —</option>
                            <option value="open">Open</option>
                            <option value="closed">Closed</option>
                        </select>
                        <small class="text-muted">Only active company bank accounts are listed.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="bookNotes" rows="2"></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-success d-flex align-items-center" id="saveBookBtn">
                            <span class="material-symbols-outlined" id="saveBookIcon">check_circle</span>
                            <span class="ms-1" id="saveBookText">Create Book</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
$(function () {

    // ── DataTable ──────────────────────────────────────────────────────────────
    var table = $('#chequeBooksTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ url('/get/finance/cheque-books/data') }}",
            dataSrc: 'data'
        },
        columns: [
            { data: null, render: function(d,t,r,m) { return m.row + 1; }, orderable: false },
            { data: 'bank_account_name' },
            { data: 'bank_name', defaultContent: '—' },
            { data: 'first_cheque_number' },
            { data: 'leafs', className: 'text-center' },
            { data: 'created_at', defaultContent: '—' },
            {
                data: null, orderable: false, searchable: false,
                render: function(d,t,r) {
                return '<a href="{{ url("/finance/cheque-books") }}/' + r.encrypted_id + '" class="btn btn-xs btn-light d-flex align-items-center" style="padding:2px 6px;">' +
                        '<span class="material-symbols-outlined" style="font-size:18px;">visibility</span>' +
                    '</a>';
                }
            }
        ],
        paging: true,
        info: true,
        lengthChange: true,
        lengthMenu: [[10,25,50,-1],[10,25,50,'All']],
        order: [[0, 'desc']]
    });

    // ── Load Bank Accounts Dropdown ────────────────────────────────────────────
    function loadBankAccounts() {
        $.get("{{ url('/ajax/cheque-books/bank-accounts') }}", function(res) {
            $('#bank_account_id').empty().append('<option value="">— Select Bank Account —</option>');
            (res.accounts || []).forEach(function(a) {
                $('#bank_account_id').append('<option value="' + a.id + '">' + a.label + '</option>');
            });
        });
    }

    // ── Add Button ─────────────────────────────────────────────────────────────
    $('#addBookBtn').on('click', function() {
        $('#bookForm')[0].reset();
        loadBankAccounts();
        $('#addBookModal').modal('show');
    });

    // ── Form Submit ────────────────────────────────────────────────────────────
    $('#bookForm').on('submit', function(e) {
        e.preventDefault();
        var $btn  = $('#saveBookBtn');
        var $icon = $('#saveBookIcon');
        var $text = $('#saveBookText');
        $icon.text('sync').addClass('spin');
        $text.text('Creating...');
        $btn.prop('disabled', true);

        $.ajax({
            url: "{{ url('/finance/cheque-books/store') }}",
            type: 'POST',
            data: $(this).serialize() + '&_token={{ csrf_token() }}',
            success: function(res) {
                $icon.text('check_circle').removeClass('spin');
                $text.text('Create Book');
                $btn.prop('disabled', false);
                if (res.success) {
                    toastr.success('Cheque book created with all leaves');
                    $('#addBookModal').modal('hide');
                    $('#bookForm')[0].reset();
                    table.ajax.reload(null, false);
                    // Navigate to the new book
                    if (res.id) {
                        window.location.href = "{{ url('/finance/cheque-books') }}/" + res.encrypted_id;
                    }
                } else {
                    toastr.error(res.error || 'Error creating cheque book');
                }
            },
            error: function(xhr) {
                $icon.text('check_circle').removeClass('spin');
                $text.text('Create Book');
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

    // ── Reset modal on close ───────────────────────────────────────────────────
    $('#addBookModal').on('hidden.bs.modal', function() {
        $('#bookForm')[0].reset();
    });
});
</script>


@endsection

