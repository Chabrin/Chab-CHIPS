@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h5 class="mb-0">Vendors</h5>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <small class="text-muted" id="result_info" style="display:none;">
                                Showing <strong id="result_count">0</strong> vendor(s)
                            </small>
                            @permission('create_vendors')
                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addVendorModal">
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">add_circle</span>
                                Add Vendor
                            </button>
                            @endpermission
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row g-3 align-items-end mb-3">
                            <div class="col-auto">
                                <label class="form-label mb-1">Vendor Type</label>
                                <select id="vendorTypeFilter" class="form-select form-select-sm">
                                    <option value="">All Types</option>
                                    @foreach($vendorTypes as $code => $label)
                                        <option value="{{ $code }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-auto">
                                <label class="form-label mb-1">Status</label>
                                <select id="vendorStatusFilter" class="form-select form-select-sm">
                                    <option value="">All Statuses</option>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="vendors-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Vendor Code</th>
                                        <th>Vendor Name</th>
                                        <th>Type</th>
                                        <th>Account Number</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                    <tr class="column-filters">
                                        <th></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-filter="filter_code" placeholder="-- Search --" /></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-filter="filter_name" placeholder="-- Search --" /></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
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

<!-- Add Vendor Modal -->
<div class="modal fade" id="addVendorModal" tabindex="-1" aria-labelledby="addVendorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addVendorModalLabel">
                    <span class="material-symbols-outlined" style="font-size:20px; vertical-align:middle;">store</span>
                    Add Vendor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addVendorForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Vendor Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="newVendorCode" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vendor Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="newVendorName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vendor Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="newVendorType" required>
                            <option value="">-- Select Type --</option>
                            @foreach($vendorTypes as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address Line 1</label>
                        <input type="text" class="form-control" id="newVendorAddress1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address Line 2</label>
                        <input type="text" class="form-control" id="newVendorAddress2">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Address Line 3</label>
                        <input type="text" class="form-control" id="newVendorAddress3">
                    </div>
                    <div id="addVendorError" class="alert alert-danger small mt-3 mb-0" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary d-flex align-items-center" id="addVendorBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="addVendorSpinner"></span>
                        Save Vendor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<style>
    .col-filter-wrap { position: relative; }
    .col-filter-wrap .col-filter { padding-right: 22px; }
    .col-filter-wrap .filter-spinner {
        position: absolute; right: 6px; top: 50%; margin-top: -7px;
        width: 14px; height: 14px; border: 2px solid #ccc;
        border-top-color: var(--accent, #3598db); border-radius: 50%;
        animation: vendorfilterspin .6s linear infinite; display: none;
    }
    .col-filter-wrap.loading .filter-spinner { display: block; }
    @keyframes vendorfilterspin { to { transform: rotate(360deg); } }
</style>
<script>
    $(document).ready(function () {
        var table = $('#vendors-table').DataTable({
            data: [],
            columns: [
                { data: null, orderable: false, searchable: false, render: function (d, t, r, m) { return m.row + 1; } },
                { data: 'vendor_code' },
                { data: 'vendor_name' },
                { data: 'vendor_type' },
                { data: 'account_number' },
                { data: 'current_balance', className: 'text-end' },
                {
                    data: 'status',
                    render: function (status) {
                        var cls = status === 'Active' ? 'bg-primary' : 'bg-secondary';
                        return '<span class="badge ' + cls + '">' + status + '</span>';
                    }
                },
                {
                    data: 'encrypted_id',
                    orderable: false,
                    searchable: false,
                    render: function (encId, type, row) {
                        var html = '<div class="d-flex flex-row gap-2">' +
                            '<a href="{{ url('system/vendors') }}/' + encId + '" class="text-primary" title="View / Edit">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">visibility</span></a>';
                        @permission('delete_vendors')
                        html += '<a href="#" class="text-primary delete-vendor-btn" data-id="' + encId + '" data-name="' + $('<div>').text(row.vendor_name).html() + '" title="Delete">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">delete</span></a>';
                        @endpermission
                        html += '</div>';
                        return html;
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            searching: false,
            ordering: true,
            orderCellsTop: true,
            order: [],
            pageLength: 50
        });

        $('#vendors-table thead .column-filters input.col-filter').each(function () {
            $(this).wrap('<div class="col-filter-wrap"></div>');
            $(this).after('<span class="filter-spinner"></span>');
        });

        var debounceTimer;

        function loadData() {
            var $activeFilters = $('#vendors-table thead .column-filters .col-filter-wrap').filter(function () {
                return $(this).find('input').val().trim().length >= 3;
            });
            $activeFilters.addClass('loading');

            var params = {
                filter_type: $('#vendorTypeFilter').val(),
                filter_status: $('#vendorStatusFilter').val()
            };
            $('#vendors-table thead .column-filters input.col-filter').each(function () {
                var key = $(this).data('filter');
                var val = $(this).val().trim();
                if (val.length >= 3) params[key] = val;
            });

            $.ajax({
                url: '{{ route("ajax.vendors.data") }}',
                type: 'GET',
                data: params,
                success: function (response) {
                    $activeFilters.removeClass('loading');
                    if (response.success) {
                        $('#result_info').show();
                        $('#result_count').text(response.count);
                        table.clear();
                        table.rows.add(response.data);
                        table.draw();
                    }
                },
                error: function (xhr) {
                    $activeFilters.removeClass('loading');
                    var msg = 'Failed to load vendors.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    toastr.error(msg);
                }
            });
        }

        $('#vendors-table thead .column-filters input.col-filter').on('keyup', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(loadData, 400);
        });

        $('#vendorTypeFilter, #vendorStatusFilter').on('change', loadData);

        loadData();

        // Add Vendor
        $('#addVendorForm').on('submit', function (e) {
            e.preventDefault();

            var btn = $('#addVendorBtn');
            var spinner = $('#addVendorSpinner');
            var errorBox = $('#addVendorError');
            btn.prop('disabled', true);
            spinner.removeClass('d-none');
            errorBox.hide().empty();

            $.ajax({
                url: '{{ route("vendors.store") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    vendor_code: $('#newVendorCode').val(),
                    vendor_name: $('#newVendorName').val(),
                    vendor_type: $('#newVendorType').val(),
                    address_line_1: $('#newVendorAddress1').val(),
                    address_line_2: $('#newVendorAddress2').val(),
                    address_line_3: $('#newVendorAddress3').val()
                },
                dataType: 'json',
                success: function (response) {
                    toastr.success(response.message);
                    $('#addVendorModal').modal('hide');
                    $('#addVendorForm')[0].reset();
                    loadData();
                },
                error: function (xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to create vendor.';
                    errorBox.text(message).show();
                },
                complete: function () {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });

        $('#addVendorModal').on('hidden.bs.modal', function () {
            $('#addVendorForm')[0].reset();
            $('#addVendorError').hide().empty();
        });

        // Delete Vendor
        $('#vendors-table tbody').on('click', '.delete-vendor-btn', function (e) {
            e.preventDefault();
            var encId = $(this).data('id');
            var name = $(this).data('name');

            Swal.fire({
                title: 'Delete "' + name + '"?',
                text: 'This permanently removes the vendor record.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3598db',
                cancelButtonColor: '#8a94a3',
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                $.ajax({
                    url: '{{ url('system/vendors') }}/' + encId,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    dataType: 'json',
                    success: function (response) {
                        toastr.success(response.message);
                        loadData();
                    },
                    error: function (xhr) {
                        var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to delete vendor.';
                        toastr.error(message);
                    }
                });
            });
        });
    });
</script>
@endsection
