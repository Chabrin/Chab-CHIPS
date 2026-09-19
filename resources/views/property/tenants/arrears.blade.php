@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h5 class="mb-0">Tenant Arrears</h5>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <small class="text-muted" id="result_info" style="display:none;">
                                Showing <strong id="result_count">0</strong> invoice(s) (max 1000) ·
                                Total arrears: <strong id="result_total">0.00</strong>
                            </small>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row g-3 align-items-end mb-3">
                            <div class="col-auto">
                                <label for="arrearsStartDate" class="form-label mb-1">Due Date From</label>
                                <input type="date" id="arrearsStartDate" class="form-control form-control-sm">
                            </div>
                            <div class="col-auto">
                                <label for="arrearsEndDate" class="form-label mb-1">Due Date To</label>
                                <input type="date" id="arrearsEndDate" class="form-control form-control-sm">
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="arrearsApplyDateBtn">
                                    <span class="spinner-border spinner-border-sm me-2 d-none" id="arrearsDateSpinner"></span>
                                    <span class="material-symbols-outlined me-1" style="font-size:18px;">filter_alt</span>
                                    Apply
                                </button>
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-outline-primary btn-sm d-flex align-items-center" id="arrearsClearDateBtn">
                                    <span class="material-symbols-outlined me-1" style="font-size:18px;">backspace</span>
                                    Clear
                                </button>
                            </div>
                            <div class="col-auto">
                                <p class="text-muted small fst-italic mb-0">Date range is limited to 60 days. Leave blank to see the top 1000 unpaid invoices overall.</p>
                            </div>
                        </div>

                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="arrears-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Tenant</th>
                                        <th>Mobile</th>
                                        <th>Property</th>
                                        <th>Unit</th>
                                        <th>Item</th>
                                        <th>Reference</th>
                                        <th>Due Date</th>
                                        <th>Total Amount</th>
                                        <th>Balance</th>
                                    </tr>
                                    <tr class="column-filters">
                                        <th></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-filter="filter_tenant" placeholder="-- Search --" /></th>
                                        <th></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-filter="filter_property" placeholder="-- Search --" /></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-filter="filter_unit" placeholder="-- Search --" /></th>
                                        <th></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-filter="filter_reference" placeholder="-- Search --" /></th>
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
@endsection

@section('scripts')
<style>
    .col-filter-wrap {
        position: relative;
    }

    .col-filter-wrap .col-filter {
        padding-right: 22px;
    }

    .col-filter-wrap .filter-spinner {
        position: absolute;
        right: 6px;
        top: 50%;
        margin-top: -7px;
        width: 14px;
        height: 14px;
        border: 2px solid #ccc;
        border-top-color: var(--accent, #3598db);
        border-radius: 50%;
        animation: arrearsfilterspin .6s linear infinite;
        display: none;
    }

    .col-filter-wrap.loading .filter-spinner {
        display: block;
    }

    @keyframes arrearsfilterspin {
        to { transform: rotate(360deg); }
    }
</style>
<script>
    $(document).ready(function () {
        var table = $('#arrears-table').DataTable({
            data: [],
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (d, t, r, m) { return m.row + 1; }
                },
                { data: 'tenant_name' },
                { data: 'mobile_number' },
                { data: 'property_name' },
                { data: 'unit_code' },
                { data: 'return_type_id' },
                { data: 'reference_number' },
                { data: 'date_due' },
                { data: 'total_amount', className: 'text-end' },
                { data: 'current_balance', className: 'text-end fw-semibold' }
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

        // Wrap each filter input with a spinner element
        $('#arrears-table thead .column-filters input.col-filter').each(function () {
            $(this).wrap('<div class="col-filter-wrap"></div>');
            $(this).after('<span class="filter-spinner"></span>');
        });

        // Keep the "To" date within 60 days of "From", and vice versa
        function syncDateBounds() {
            var start = $('#arrearsStartDate').val();
            var end = $('#arrearsEndDate').val();
            if (start) {
                var maxEnd = new Date(start);
                maxEnd.setDate(maxEnd.getDate() + 60);
                $('#arrearsEndDate').attr('min', start).attr('max', maxEnd.toISOString().slice(0, 10));
            }
            if (end) {
                var minStart = new Date(end);
                minStart.setDate(minStart.getDate() - 60);
                $('#arrearsStartDate').attr('max', end).attr('min', minStart.toISOString().slice(0, 10));
            }
        }
        $('#arrearsStartDate, #arrearsEndDate').on('change', syncDateBounds);

        var debounceTimer;

        function loadData() {
            var $activeFilters = $('#arrears-table thead .column-filters .col-filter-wrap').filter(function () {
                return $(this).find('input').val().trim().length >= 3;
            });
            $activeFilters.addClass('loading');

            var params = {};
            $('#arrears-table thead .column-filters input.col-filter').each(function () {
                var key = $(this).data('filter');
                var val = $(this).val().trim();
                if (val.length >= 3) params[key] = val;
            });

            var startDate = $('#arrearsStartDate').val();
            var endDate = $('#arrearsEndDate').val();
            if (startDate && endDate) {
                params.start_date = startDate;
                params.end_date = endDate;
            }

            $.ajax({
                url: '{{ route("ajax.tenant.arrears.data") }}',
                type: 'GET',
                data: params,
                success: function (response) {
                    $activeFilters.removeClass('loading');
                    if (response.success) {
                        $('#result_info').show();
                        $('#result_count').text(response.count);
                        $('#result_total').text(response.total_balance);
                        table.clear();
                        table.rows.add(response.data);
                        table.draw();
                    }
                },
                error: function (xhr) {
                    $activeFilters.removeClass('loading');
                    var msg = 'Failed to load arrears.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    toastr.error(msg);
                }
            });
        }

        // Column filters with debounce (3+ chars)
        $('#arrears-table thead .column-filters input.col-filter').on('keyup', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(loadData, 400);
        });

        $('#arrearsApplyDateBtn').on('click', function () {
            var startDate = $('#arrearsStartDate').val();
            var endDate = $('#arrearsEndDate').val();
            if ((startDate && !endDate) || (!startDate && endDate)) {
                toastr.error('Choose both a start and end date, or clear both.');
                return;
            }
            var spinner = $('#arrearsDateSpinner');
            spinner.removeClass('d-none');
            loadData();
            setTimeout(function () { spinner.addClass('d-none'); }, 300);
        });

        $('#arrearsClearDateBtn').on('click', function () {
            $('#arrearsStartDate, #arrearsEndDate').val('').removeAttr('min').removeAttr('max');
            loadData();
        });

        // Initial load — top 1000 unpaid invoices, no date filter
        loadData();
    });
</script>
@endsection
