@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Currency Settings</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addCurrencyModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Currency</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            {{-- toastr will be used for notifications --}}
                            <table class="display" id="basic-1">
                                <thead>
                                    <tr>
                                        <th>Currency Name</th>
                                        <th>Currency Code</th>
                                        <th>Country</th>
                                        <th>Added By</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Add/Edit Currency Modal -->
<div class="modal fade" id="addCurrencyModal" tabindex="-1" aria-labelledby="addCurrencyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCurrencyModalLabel">Add Currency</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="currencyForm">
                    <input type="hidden" name="id" id="currencyId">
                    <div class="mb-3">
                        <label for="currency_name" class="form-label">Currency Name <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="currency_name" id="currency_name" required placeholder="Enter currency name">
                    </div>
                    <div class="mb-3">
                        <label for="currency_code" class="form-label">Currency Code <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="currency_code" id="currency_code" required placeholder="Enter currency code">
                    </div>
                    <div class="mb-3">
                        <label for="country_id" class="form-label">Country</label>
                        <select class="form-control" name="country_id" id="country_id">
                            <option value="">Select Country</option>
                            @foreach($countries as $country)
                            <option value="{{ $country->id }}">{{ $country->country_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-success d-flex align-items-center" id="saveCurrencyBtn">
                            <span class="material-symbols-outlined">check_circle</span>
                            <span class="ms-1">Save</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // use toastr for notifications
    $(function() {
        var table = $('#basic-1').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/currency/list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'currencies'
            },
            columns: [{
                    data: 'currency_name'
                },
                {
                    data: 'currency_code'
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        return row.country && row.country.country_name ? row.country.country_name : 'N/A';
                    }
                },
                {
                    data: 'added_by',
                    defaultContent: ''
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return '<div class="d-flex flex-row gap-1">' +
                            '<button class="btn btn-xs btn-light edit-currency d-flex align-items-center" ' +
                            'data-id="' + row.id + '" data-currency_name="' + row.currency_name + '" data-currency_code="' + row.currency_code + '" data-country_id="' + (row.country_id || '') + '" style="padding:2px 6px;">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>' +
                            '</button>' +
                            '<button class="btn btn-xs btn-danger delete-currency d-flex align-items-center" ' +
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
        $('#currencyForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#currencyId').val();
            var url = id ? "{{ url('/ajax/currency') }}/" + id : "{{ route('ajax.currency.store') }}";
            var method = id ? 'POST' : 'POST';
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveCurrencyBtn');
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
                        $('#addCurrencyModal').modal('hide');
                        $('#currencyForm')[0].reset();
                        $('#currencyId').val('');
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
                        msg = Object.values(xhr.responseJSON.errors).join('<br>');
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });
        $(document).on('click', '.edit-currency', function() {
            $('#currencyId').val($(this).data('id'));
            $('#currency_name').val($(this).data('currency_name'));
            $('#currency_code').val($(this).data('currency_code'));
            $('#country_id').val($(this).data('country_id'));
            $('#addCurrencyModal').modal('show');
        });
        $(document).on('click', '.delete-currency', function() {
            var $btn = $(this);
            var $icon = $btn.find('.material-symbols-outlined').first();
            var name = $btn.siblings('.edit-currency').data('currency_name');
            if (!confirm('Are you sure you want to delete "' + name + '"?')) {
                return;
            }
            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);
            $.ajax({
                url: "{{ url('/ajax/currency') }}/" + $btn.data('id'),
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
        $('#addCurrencyModal').on('hidden.bs.modal', function() {
            $('#currencyForm')[0].reset();
            $('#currencyId').val('');
        });
    });
</script>
@endsection
