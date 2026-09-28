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
                            <table class="display" id="banksTable" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>Bank Name</th>
                                        <th>Address</th>
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
                        <label for="bank_name" class="form-label">Bank Name <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="bank_name" id="bank_name" required placeholder="Enter bank name">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <input type="text" class="form-control" name="address" id="address" placeholder="Enter bank address">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-primary d-flex align-items-center" id="saveBankBtn">
                            <span class="material-symbols-outlined">check_circle</span>
                            <span class="ms-1">Save</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                    data: 'bank_name',
                    render: data => data || '-'
                },
                {
                    data: 'address',
                    render: data => data || '-'
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return '<div class="d-flex flex-row gap-1">' +
                            '<a href="javascript:void(0)" class="text-primary edit-bank" ' +
                            'data-id="' + row.id + '" data-bank_name="' + (row.bank_name || '') + '" data-address="' + (row.address || '') + '" title="Edit">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>' +
                            '</a>' +
                            '<a href="javascript:void(0)" class="text-primary delete-bank" ' +
                            'data-id="' + row.id + '" data-name="' + (row.bank_name || '') + '" title="Delete">' +
                            '<span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>' +
                            '</a>' +
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
            $('.invalid-feedback').text('').hide();
            $('.form-control').removeClass('is-invalid');
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
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        $.each(xhr.responseJSON.errors, function(field, messages) {
                            const input = $(`[name="${field}"]`);
                            input.addClass('is-invalid');
                            input.siblings('.invalid-feedback').text(messages[0]).show();
                        });
                    } else {
                        toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Error saving');
                    }
                }
            });
        });

        $(document).on('click', '.edit-bank', function() {
            $('#bankId').val($(this).data('id'));
            $('#bank_name').val($(this).data('bank_name'));
            $('#address').val($(this).data('address'));
            $('.invalid-feedback').text('').hide();
            $('.form-control').removeClass('is-invalid');
            $('#addBankModal').modal('show');
        });

        $(document).on('click', '.delete-bank', function() {
            var $btn = $(this);
            var name = $btn.data('name');
            Swal.fire({
                title: 'Delete bank?',
                text: `Are you sure you want to delete "${name}"? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (!result.isConfirmed) return;

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
                        toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Delete error');
                    }
                });
            });
        });

        $('#addBankModal').on('hidden.bs.modal', function() {
            $('#bankForm')[0].reset();
            $('#bankId').val('');
            $('.invalid-feedback').text('').hide();
            $('.form-control').removeClass('is-invalid');
        });
    });
</script>
@endsection
