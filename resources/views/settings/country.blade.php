@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Country Settings</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addCountryModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Country</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            {{-- toastr will be used for notifications --}}
                            <table class="display" id="country-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Short Name</th>
                                        <th>Name</th>
                                        <th>Phone Code</th>
                                        <th>Risk Level</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data loaded via DataTables AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Add/Edit Country Modal -->
<div class="modal fade" id="addCountryModal" tabindex="-1" aria-labelledby="addCountryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCountryModalLabel">Add Country</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="countryForm">
                    <input type="hidden" name="id" id="countryId">
                    <div class="mb-3">
                        <label for="short_name" class="form-label">Short Name <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="short_name" id="short_name" required maxlength="3" placeholder="Enter short name">
                    </div>
                    <div class="mb-3">
                        <label for="name" class="form-label">Name <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="name" id="name" required maxlength="50" placeholder="Enter country name">
                    </div>
                    <div class="mb-3">
                        <label for="phone_code" class="form-label">Phone Code <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="phone_code" id="phone_code" required maxlength="10" placeholder="Enter phone code">
                    </div>
                    <div class="mb-3">
                        <label for="risk_level" class="form-label">Risk Level</label>
                        <input type="text" class="form-control" name="risk_level" id="risk_level" maxlength="255" placeholder="Enter risk level">
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-success d-flex align-items-center" id="saveCountryBtn">
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
    $(document).ready(function() {
        let table = $('#country-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/country/list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'countries'
            },
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'short_name'
                },
                {
                    data: 'name'
                },
                {
                    data: 'phone_code'
                },
                {
                    data: 'risk_level',
                    defaultContent: ''
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        return `
                <div class="d-flex flex-row gap-1">
                    <button class="btn btn-xs btn-light edit-country d-flex align-items-center" 
                        data-id="${row.id}" data-short_name="${row.short_name}" data-name="${row.name}" data-phone_code="${row.phone_code}" data-risk_level="${row.risk_level || ''}" style="padding:2px 6px;">
                        <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                    </button>
                    <button class="btn btn-xs btn-danger delete-country d-flex align-items-center" 
                        data-id="${row.id}" style="padding:2px 6px;">
                        <span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>
                        <span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>
                    </button>
                </div>`;
                    }
                }
            ],
            paging: true,
            pageLength: 10,
            lengthMenu: [
                [10, 25, 50, 100],
                [10, 25, 50, 100]
            ]
        });
        $('#countryForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#countryId').val();
            var url = id ? "{{ url('/ajax/country') }}/" + id : "{{ route('ajax.country.store') }}";
            var method = id ? 'POST' : 'POST';
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveCountryBtn');
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
                        $('#addCountryModal').modal('hide');
                        $('#countryForm')[0].reset();
                        $('#countryId').val('');
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
        $(document).on('click', '.edit-country', function() {
            $('#countryId').val($(this).data('id'));
            $('#short_name').val($(this).data('short_name'));
            $('#name').val($(this).data('name'));
            $('#phone_code').val($(this).data('phone_code'));
            $('#risk_level').val($(this).data('risk_level'));
            $('#addCountryModal').modal('show');
        });
        $(document).on('click', '.delete-country', function() {
            var $btn = $(this);
            var $icon = $btn.find('.material-symbols-outlined').first();
            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);
            $.ajax({
                url: "{{ url('/ajax/country') }}/" + $btn.data('id'),
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
        $('#addCountryModal').on('hidden.bs.modal', function() {
            $('#countryForm')[0].reset();
            $('#countryId').val('');
        });
    });
</script>
@endsection