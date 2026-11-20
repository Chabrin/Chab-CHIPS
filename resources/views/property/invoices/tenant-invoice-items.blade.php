@extends('layouts.main')

@section('styles')
<style>
    /* Custom styling for date filter-field radio buttons */
    .btn-outline-primary.date-filter-radio {
        color: #3598db;
        border-color: #3598db;
    }

    .btn-outline-primary.date-filter-radio:hover {
        color: #fff;
        background-color: #3598db;
        border-color: #3598db;
    }

    .btn-check:checked+.btn-outline-primary.date-filter-radio {
        color: #fff;
        background-color: #3598db;
        border-color: #3598db;
    }

    .invoice-filters-panel {
        background-color: #f8f9fc;
        border: 1px solid #e5e9f2;
        border-radius: 8px;
    }
</style>
@endsection

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
                        <div class="invoice-filters-panel d-flex flex-wrap align-items-end gap-2 px-2 py-2 mb-3">
                            <div class="btn-group" role="group" aria-label="Date filter field">
                                <input type="radio" class="btn-check" name="date_filter_type" id="date_filter_generated" value="date_time" checked autocomplete="off">
                                <label class="btn btn-outline-primary date-filter-radio btn-sm" for="date_filter_generated">
                                    <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">event_available</span>
                                    Date Generated
                                </label>

                                <input type="radio" class="btn-check" name="date_filter_type" id="date_filter_due" value="date_due" autocomplete="off">
                                <label class="btn btn-outline-primary date-filter-radio btn-sm" for="date_filter_due">
                                    <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">event_upcoming</span>
                                    Date Due
                                </label>
                            </div>

                            <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" style="width:150px;" title="Date From" placeholder="Date From">

                            <span class="text-muted">&ndash;</span>

                            <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" style="width:150px;" title="Date To" placeholder="Date To">

                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="filter_btn">
                                <span class="spinner-border spinner-border-sm me-1" role="status" id="filter_spinner" style="display: none;"></span>
                                <span class="material-symbols-outlined" id="filter_icon" style="font-size: 16px; vertical-align: middle;">filter_list</span>
                                <span class="ms-1">Filter</span>
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm d-flex align-items-center" id="clear_btn">
                                <span class="spinner-border spinner-border-sm me-1" role="status" id="clear_spinner" style="display: none;"></span>
                                <span class="material-symbols-outlined" id="clear_icon" style="font-size: 16px; vertical-align: middle;">clear</span>
                                <span class="ms-1">Clear</span>
                            </button>

                            <small class="text-muted ms-auto">Max 30-day range &middot; blank shows the 100 most recent</small>
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
                                    </tr>
                                    <tr class="column-filters">
                                        <th></th>
                                        <th><input type="date" class="form-control form-control-sm col-filter" data-col="f_date"></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-col="f_reference" placeholder="Search"></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-col="f_tenant" placeholder="Search"></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-col="f_property" placeholder="Search"></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-col="f_unit" placeholder="Search"></th>
                                        <th><input type="number" step="0.01" class="form-control form-control-sm col-filter" data-col="f_amount" placeholder="Exact amount"></th>
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

@section('scripts')
<script>
    $(document).ready(function() {
        var table = $('#invoice-items-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("ajax.invoices.items.search") }}',
                type: 'POST',
                data: function(d) {
                    d._token = '{{ csrf_token() }}';
                    d.date_filter_type = $('input[name="date_filter_type"]:checked').val();
                    d.date_from = $('#date_from').val();
                    d.date_to = $('#date_to').val();
                    $('.col-filter').each(function() {
                        d[$(this).data('col')] = $(this).val();
                    });
                },
                dataSrc: function(json) {
                    if (json.error) {
                        toastr.error(json.error);
                    }
                    return json.data || [];
                }
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
                    data: 'reference_number',
                    render: function(data, type, row) {
                        if (type !== 'display') return data;
                        return $('<a>').attr('href', row.details_url).text(data || 'View invoice')[0].outerHTML;
                    }
                },
                {
                    data: 'tenant_name',
                    orderable: false,
                    render: function(data, type, row) {
                        if (row.tenant_encrypted_id) {
                            return '<a href="/tenants/' + row.tenant_encrypted_id + '" target="_blank" class="property-link">' + data + '</a>';
                        }
                        return data;
                    }
                },
                {
                    data: 'property_name',
                    orderable: false,
                    render: function(data, type, row) {
                        if (row.property_encrypted_id) {
                            return '<a href="/property/listings/' + row.property_encrypted_id + '" target="_blank" class="property-link">' + data + '</a>';
                        }
                        return data;
                    }
                },
                {
                    data: 'unit_name',
                    orderable: false
                },
                {
                    data: 'total_amount',
                    className: 'text-end'
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            searching: false,
            ordering: true,
            orderCellsTop: true,
            order: [
                [1, 'desc']
            ]
        });

        // Handle filter button click
        $('#filter_btn').on('click', function() {
            var dateFrom = $('#date_from').val();
            var dateTo = $('#date_to').val();

            // Quick client-side check so an obviously-too-wide range doesn't even
            // need a round trip — the server still enforces this authoritatively.
            if (dateFrom || dateTo) {
                if (!dateFrom || !dateTo) {
                    toastr.error('Please provide both a start and end date to search.');
                    return;
                }
                var days = (new Date(dateTo) - new Date(dateFrom)) / (1000 * 60 * 60 * 24);
                if (days < 0) {
                    toastr.error('Date To cannot be earlier than Date From.');
                    return;
                }
                if (days > 30) {
                    toastr.error('Date range cannot exceed 30 days. Please narrow your search.');
                    return;
                }
            }

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
            $('#date_filter_generated').prop('checked', true);
            $('#date_from').val('');
            $('#date_to').val('');
            $('.col-filter').val('');

            table.ajax.reload(function() {
                $('#clear_spinner').hide();
                $('#clear_icon').show();
                $('#clear_btn').prop('disabled', false);
            });
        });

        // Per-column top filters — don't let clicks/typing inside them trigger
        // DataTables' column-sort handler on the enclosing <th>.
        $('.column-filters').on('click keydown', 'input', function(e) {
            e.stopPropagation();
        });

        var colFilterTimer = null;
        $('.col-filter').on('keyup change', function() {
            clearTimeout(colFilterTimer);
            colFilterTimer = setTimeout(function() {
                table.ajax.reload();
            }, 400);
        });
    });
</script>
@endsection
