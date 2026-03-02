@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Bank Settings</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addBankModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Bank</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="banksTable">
                                <thead>
                                    <tr>
                                        <th>Bank Code</th>
                                        <th>Name</th>
                                        <th>SWIFT Code</th>
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

<!-- Add/Edit Bank Modal -->
<div class="modal fade" id="addBankModal" tabindex="-1" aria-labelledby="addBankModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addBankModalLabel">Add Bank</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="bankForm">
                    <input type="hidden" name="id" id="bankId">
                    <div class="mb-3">
                        <label for="bank_code" class="form-label">Bank Code <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="bank_code" id="bank_code" required placeholder="Enter bank code">
                    </div>
                    <div class="mb-3">
                        <label for="name" class="form-label">Name <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="name" id="name" required placeholder="Enter bank name">
                    </div>
                    <div class="mb-3">
                        <label for="swift_code" class="form-label">SWIFT Code</label>
                        <input type="text" class="form-control" name="swift_code" id="swift_code" placeholder="Enter SWIFT code">
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-success d-flex align-items-center" id="saveBankBtn">
                            <span class="material-symbols-outlined">check_circle</span>
                            <span class="ms-1">Save</span>
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
    $(function() {
        var table = $('#banksTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/settings/banks/list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'banks'
            },
            columns: [{
                    data: 'bank_code'
                },
                {
                    data: 'name'
                },
                {
                    data: 'swift_code',
                    defaultContent: ''
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return '<div class="d-flex flex-row gap-1">' +
                            '<button class="btn btn-xs btn-light edit-bank d-flex align-items-center" ' +
                            'data-id="' + row.id + '" data-bank_code="' + (row.bank_code || '') + '" data-name="' + (row.name || '') + '" data-swift_code="' + (row.swift_code || '') + '" style="padding:2px 6px;">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>' +
                            '</button>' +
                            '<button class="btn btn-xs btn-danger delete-bank d-flex align-items-center" ' +
                            'data-id="' + row.id + '" style="padding:2px 6px;">' +
                            '<span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>' +
                            '</button>' +
                            '</div>';
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ]
        });

        $('#bankForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#bankId').val();
            var url = id ? "{{ url('/settings/banks/update') }}/" + id : "{{ url('/settings/banks/store') }}";
            var method = 'POST';
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveBankBtn');
            var $icon = $btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();
            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Processing...');
            $btn.prop('disabled', true);
            $.ajax({
                url: url,
                type: method,
                data: formData,
                success: function(res) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('Saved successfully');
                        $('#addBankModal').modal('hide');
                        $('#bankForm')[0].reset();
                        $('#bankId').val('');
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error saving');
                    }
                },
                error: function(xhr) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);
                    let msg = 'Validation error';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        msg = Object.values(xhr.responseJSON.errors).join('\n');
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });

        $(document).on('click', '.edit-bank', function() {
            $('#bankId').val($(this).data('id'));
            $('#bank_code').val($(this).data('bank_code'));
            $('#name').val($(this).data('name'));
            $('#swift_code').val($(this).data('swift_code'));
            $('#addBankModal').modal('show');
        });

        $(document).on('click', '.delete-bank', function() {
            var $btn = $(this);
            var $icon = $btn.find('.material-symbols-outlined').first();
            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);
            $.ajax({
                url: "{{ url('/settings/banks/delete') }}/" + $btn.data('id'),
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('Deleted');
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error deleting');
                    }
                },
                error: function(xhr) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);
                    let msg = 'Delete error';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });

        $('#addBankModal').on('hidden.bs.modal', function() {
            $('#bankForm')[0].reset();
            $('#bankId').val('');
        });
    });
</script>