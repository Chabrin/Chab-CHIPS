@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <section class="card" aria-labelledby="payment-page-title">
            <div class="card-header">
                <h5 id="payment-page-title">Tenant Payments (Allocated)</h5>
            </div>
            <div class="card-body">
                <form id="payment_filters" data-today="{{ now()->toDateString() }}" novalidate>
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-4 col-md-6">
                            <label for="property_select" class="form-label mb-1">Property</label>
                            <select class="form-select form-select-sm" id="property_select" required>
                                <option value=""></option>
                                @foreach($properties as $prop)
                                    <option value="{{ $prop->id }}">{{ $prop->property_name }}{{ $prop->reference_number ? ' (' . $prop->reference_number . ')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label for="period_select" class="form-label mb-1">Period</label>
                            <select class="form-select form-select-sm" id="period_select">
                                <option value="custom">Custom dates</option>
                                <option value="this_month">This month</option>
                                <option value="last_month">Last month</option>
                                <option value="last_90_days">Last 90 days</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-6">
                            <label for="date_from" class="form-label mb-1">From</label>
                            <input type="date" class="form-control form-control-sm" id="date_from" required>
                        </div>
                        <div class="col-lg-2 col-6">
                            <label for="date_to" class="form-label mb-1">To</label>
                            <input type="date" class="form-control form-control-sm" id="date_to" required>
                        </div>
                        <div class="col-lg-auto">
                            <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" id="search_btn">
                                <span class="material-symbols-outlined" aria-hidden="true">search</span>
                                Search payments
                            </button>
                        </div>
                    </div>
                    <p class="text-danger mt-2 mb-0" id="payment_error" role="alert" hidden></p>
                    <p class="text-muted mt-3 mb-0" id="payment_status" role="status">Choose a property and date range to see allocated payments.</p>
                </form>
            </div>
        </section>

        <section class="card" id="results_area" aria-labelledby="payments-results-title" hidden>
            <div class="card-header">
                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <h5 id="payments-results-title">Allocated payments</h5>
                    <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1" id="export_btn" disabled>
                        <span class="material-symbols-outlined" aria-hidden="true">download</span>
                        Export period to Excel
                    </button>
                </div>
            </div>
            <div class="card-body">
                <p class="mb-3" id="payment_scope"></p>
                <div class="row g-3 align-items-end mb-3">
                    <div class="col-sm">
                        <dl class="d-flex flex-wrap gap-4 mb-0">
                            <div>
                                <dt class="text-muted fw-normal">Allocation records</dt>
                                <dd class="fw-semibold mb-0" id="result_count"></dd>
                            </div>
                            <div>
                                <dt class="text-muted fw-normal">Total allocated for period (KES)</dt>
                                <dd class="fw-semibold mb-0" id="allocated_total"></dd>
                            </div>
                        </dl>
                    </div>
                    <div class="col-sm-5 col-lg-4">
                        <label for="payment_search" class="form-label mb-1">Search within results</label>
                        <input type="search" class="form-control form-control-sm" id="payment_search"
                               placeholder="Tenant, narration, channel or remarks" aria-controls="payments-table">
                    </div>
                </div>

                <div class="table-responsive custom-scrollbar" tabindex="0" role="region" aria-label="Allocated payment records">
                    <table class="display w-100" id="payments-table">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Date</th>
                                <th scope="col">Tenant</th>
                                <th scope="col">Narration</th>
                                <th scope="col" class="text-end">Allocated amount</th>
                                <th scope="col" class="text-end">Transaction amount</th>
                                <th scope="col">Payment mode</th>
                                <th scope="col">Channel</th>
                                <th scope="col">Remarks</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(function() {
        const form = $('#payment_filters');
        const propertyPicker = $('#property_select').select2({
            placeholder: 'Search by property name or reference',
            allowClear: true,
            width: '100%'
        });
        const selection = propertyPicker.next('.select2').find('.select2-selection');
        const propertyLabel = $('label[for="property_select"]').attr('id', 'payment_property_label');
        selection.attr('aria-labelledby', propertyLabel.attr('id') + ' ' + selection.attr('aria-labelledby'));

        const from = $('#date_from');
        const to = $('#date_to');
        const period = $('#period_select');
        const resultSearch = $('#payment_search');
        const results = $('#results_area');
        const status = $('#payment_status');
        const errorMessage = $('#payment_error');
        const searchButton = $('#search_btn');
        const exportButton = $('#export_btn');
        let loadedFilters;

        const text = $.fn.dataTable.render.text();
        const number = new Intl.NumberFormat('en-KE');
        const money = new Intl.NumberFormat('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const shortDate = new Intl.DateTimeFormat('en-GB', {
            day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC'
        });
        const onlyDate = value => new Date(value + 'T00:00:00Z');
        const dateInput = date => date.toISOString().slice(0, 10);

        const today = onlyDate(form.data('today'));
        const year = today.getUTCFullYear();
        const month = today.getUTCMonth();
        const firstThisMonth = new Date(Date.UTC(year, month, 1));
        const firstLastMonth = new Date(Date.UTC(year, month - 1, 1));
        const lastLastMonth = new Date(Date.UTC(year, month, 0));
        const ninetyDaysAgo = new Date(Date.UTC(year, month, today.getUTCDate() - 89));
        const ranges = {
            this_month: [firstThisMonth, today],
            last_month: [firstLastMonth, lastLastMonth],
            last_90_days: [ninetyDaysAgo, today]
        };

        period.on('change', function() {
            const range = ranges[this.value];
            if (range) {
                from.val(dateInput(range[0]));
                to.val(dateInput(range[1]));
            }
        });
        from.add(to).on('input', () => period.val('custom'));
        form.on('change', () => errorMessage.prop('hidden', true));

        const table = $('#payments-table').DataTable({
            columns: [
                { data: null, defaultContent: '', orderable: false, searchable: false },
                {
                    data: 'date_time',
                    className: 'text-nowrap',
                    render: function(value, type) {
                        if (type === 'sort' || type === 'type') {
                            const match = /^(\d{2})-(\d{2})-(\d{4}) (\d{2}:\d{2})$/.exec(value || '');
                            return match ? match[3] + match[2] + match[1] + match[4] : '';
                        }
                        return type === 'display' ? text.display(value) : value;
                    }
                },
                { data: 'tenant_name', render: text },
                { data: 'narration', render: text },
                {
                    data: { _: 'amount', sort: 'amount_raw', type: 'amount_raw' },
                    className: 'text-end text-nowrap'
                },
                {
                    data: { _: 'trans_amount', sort: 'trans_amount_raw', type: 'trans_amount_raw' },
                    className: 'text-end text-nowrap'
                },
                { data: 'payment_mode', render: text },
                { data: 'payment_channel', render: text },
                { data: 'trans_remarks', render: text }
            ],
            layout: { topEnd: null },
            order: [[1, 'asc']],
            pageLength: 50,
            language: {
                emptyTable: 'No allocated payments were found for this property and period.',
                zeroRecords: 'No allocated payments match your search.'
            },
            drawCallback: function() {
                const api = this.api();
                const start = api.page.info().start;
                api.rows({ page: 'current' }).nodes().each((row, index) => {
                    row.cells[0].textContent = start + index + 1;
                });
            }
        });
        resultSearch.on('input', function() {
            table.search(this.value).draw();
        });

        function showError(message, field) {
            errorMessage.text(message).prop('hidden', false);
            if (field) field.trigger('focus');
        }

        form.on('submit', function(event) {
            event.preventDefault();
            if (searchButton.prop('disabled')) return;

            const propertyId = propertyPicker.val();
            const dateFrom = from.val();
            const dateTo = to.val();

            if (!propertyId) {
                showError('Choose a property.', selection);
                return;
            }
            if (!dateFrom || !dateTo) {
                showError('Enter both a start date and an end date.', dateFrom ? to : from);
                return;
            }
            if (dateFrom > dateTo) {
                showError('The end date must be on or after the start date.', to);
                return;
            }

            const filters = { property_id: propertyId, date_from: dateFrom, date_to: dateTo };
            const propertyName = propertyPicker.find(':selected').text();
            loadedFilters = null;
            exportButton.prop('disabled', true);
            searchButton.prop('disabled', true);
            results.prop('hidden', true);
            errorMessage.prop('hidden', true);
            status.removeClass('visually-hidden').text('Loading allocated payments\u2026');

            $.ajax({
                url: '{{ route("ajax.payments.allocated.search") }}',
                type: 'POST',
                dataType: 'json',
                data: { ...filters, _token: '{{ csrf_token() }}' }
            }).done(function(response) {
                if (!response.success || !Array.isArray(response.payments)) {
                    status.text('');
                    showError('Could not load allocated payments. Search again to retry.');
                    return;
                }

                const payments = response.payments;
                const total = payments.reduce((sum, payment) => sum + Math.round(Number(payment.amount_raw ?? 0) * 10000), 0);
                const scope = [propertyName, shortDate.format(onlyDate(dateFrom)) + ' to ' + shortDate.format(onlyDate(dateTo))];
                $('#payment_scope').text(scope.join(' \u00b7 '));
                $('#result_count').text(number.format(payments.length));
                $('#allocated_total').text(money.format(total / 10000));
                resultSearch.val('').prop('disabled', payments.length === 0);
                results.prop('hidden', false);
                table.clear().search('');
                table.rows.add(payments).draw();
                table.columns.adjust();

                loadedFilters = filters;
                exportButton.prop('disabled', payments.length === 0);
                status.text(payments.length
                    ? number.format(payments.length) + ' allocation records loaded.'
                    : 'No allocated payments were found for this property and period.');
            }).fail(function(xhr) {
                status.text('');
                const message = xhr.responseJSON?.message || 'Could not load allocated payments. Search again to retry.';
                showError(message);
            }).always(function() {
                searchButton.prop('disabled', false);
                status.addClass('visually-hidden');
            });
        });

        exportButton.on('click', function() {
            if (loadedFilters) {
                window.location.href = '{{ route("ajax.payments.allocated.export") }}?' + new URLSearchParams(loadedFilters);
            }
        });
    });
</script>
@endsection
