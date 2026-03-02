@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Tenant Invoice Items</h5>
                        <a href="{{ route('invoices.tenant.items.create') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                            <span class="material-symbols-outlined">add</span>
                            <span class="ms-1">Create Invoice</span>
                        </a>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-2">
                                <label for="status_filter" class="form-label">Status</label>
                                <select class="form-select form-select-sm" id="status_filter" name="status_filter">
                                    <option value="">All Statuses</option>
                                    <option value="1">Pending</option>
                                    <option value="6">Partially Paid</option>
                                    <option value="8">Approved</option>
                                    <option value="9">Fully Paid</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" class="form-control form-control-sm" id="date_from" name="date_from">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" class="form-control form-control-sm" id="date_to" name="date_to">
                            </div>
                            <div class="col-md-4 d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-primary btn-sm" id="filter_btn">
                                    <span class="spinner-border spinner-border-sm" role="status" id="filter_spinner" style="display: none;"></span>
                                    <span class="material-symbols-outlined" id="filter_icon" style="font-size: 16px; vertical-align: middle;">filter_list</span>
                                    <span class="ms-1">Filter</span>
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" id="clear_btn">
                                    <span class="spinner-border spinner-border-sm" role="status" id="clear_spinner" style="display: none;"></span>
                                    <span class="material-symbols-outlined" id="clear_icon" style="font-size: 16px; vertical-align: middle;">clear</span>
                                    <span class="ms-1">Clear</span>
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="invoice-items-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Reference</th>
                                        <th>Tenant</th>
                                        <th>Property</th>
                                        <th>Unit</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-center">Status</th>
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
@endsection
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        var table = $('#invoice-items-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: '{{ route("ajax.invoices.items.search") }}',
                type: 'POST',
                data: function(d) {
                    d._token = '{{ csrf_token() }}';
                    d.status_id = $('#status_filter').val();
                    d.date_from = $('#date_from').val();
                    d.date_to = $('#date_to').val();
                },
                dataSrc: 'invoices'
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
                    data: 'date_time'
                },
                {
                    data: 'reference_number'
                },
                {
                    data: 'tenant_name',
                    render: function(data, type, row) {
                        if (row.tenant_encrypted_id) {
                            return '<a href="/tenants/' + row.tenant_encrypted_id + '" target="_blank" class="property-link">' + data + '</a>';
                        }
                        return data;
                    }
                },
                {
                    data: 'property_name',
                    render: function(data, type, row) {
                        if (row.property_encrypted_id) {
                            return '<a href="/property/listings/' + row.property_encrypted_id + '" target="_blank" class="property-link">' + data + '</a>';
                        }
                        return data;
                    }
                },
                {
                    data: 'unit_name'
                },
                {
                    data: 'total_amount',
                    className: 'text-end'
                },
                {
                    data: 'status_name',
                    className: 'text-center'
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            searching: true,
            ordering: true,
            order: [
                [1, 'desc']
            ]
        });

        // Handle filter button click
        $('#filter_btn').on('click', function() {
            $('#filter_spinner').show();
            $('#filter_icon').hide();
            $('#filter_btn').prop('disabled', true);

            table.ajax.reload(function() {
                $('#filter_spinner').hide();
                $('#filter_icon').show();
                $('#filter_btn').prop('disabled', false);
            });
        });

        // Handle clear button click
        $('#clear_btn').on('click', function() {
            $('#clear_spinner').show();
            $('#clear_icon').hide();
            $('#clear_btn').prop('disabled', true);

            // Reset filters
            $('#status_filter').val('');
            $('#date_from').val('');
            $('#date_to').val('');

            table.ajax.reload(function() {
                $('#clear_spinner').hide();
                $('#clear_icon').show();
                $('#clear_btn').prop('disabled', false);
            });
        });
    });
</script>