@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Tenant Payments (Allocated)</h5>
                    </div>
                    <div class="card-body">
                        <!-- Filters - condensed single row -->
                        <div class="row">
                            <div class="col-md-4">
                                <label for="property_select" class="form-label mb-1">Property <span class="text-danger">*</span></label>
                                <select class="form-control form-control-sm" id="property_select" style="width:100%;">
                                    <option value="">-- Select Property --</option>
                                    @foreach($properties as $prop)
                                    <option value="{{ $prop->id }}">{{ $prop->property_name }} ({{ $prop->reference_number ?? '' }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="date_from" class="form-label mb-1">From <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm" id="date_from">
                            </div>
                            <div class="col-md-4">
                                <label for="date_to" class="form-label mb-1">To <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm" id="date_to">
                            </div>
                            
                        </div>
                        <div class=" d-flex gap-2 justify-content-end align-items-end">
                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="search_btn">
                                <span class="spinner-border spinner-border-sm" role="status" id="search_spinner" style="display:none;"></span>
                                <span class="material-symbols-outlined" id="search_icon">search</span>
                                <span class="ms-1">Search</span>
                            </button>
                            <button type="button" class="btn btn-success btn-sm d-flex align-items-center" id="export_btn" disabled>
                                <span class="spinner-border spinner-border-sm" role="status" id="export_spinner" style="display:none;"></span>
                                <span class="material-symbols-outlined" id="export_icon">download</span>
                                <span class="ms-1">Excel</span>
                            </button>
                        </div>
                        <small class="text-muted d-block mb-2">Select a property and date range, then search.</small>

                        <div id="results_area">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted" id="result_info" style="display:none;">Found <strong id="result_count">0</strong> records</small>
                            </div>
                            <div class="table-responsive custom-scrollbar">
                                <table class="display" id="payments-table" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Date</th>
                                            <th>Tenant</th>
                                            <th>Narration</th>
                                            <th class="text-end">Allocated</th>
                                            <th class="text-end">Trans Amt</th>
                                            <th>Mode</th>
                                            <th>Channel</th>
                                            <th>Remarks</th>
                                        </tr>
                                        <tr class="column-filters">
                                            <th></th>
                                            <th></th>
                                            <th><input type="text" class="form-control form-control-sm" placeholder="-- Search --" /></th>
                                            <th><input type="text" class="form-control form-control-sm" placeholder="-- Search --" /></th>
                                            <th></th>
                                            <th></th>
                                            <th></th>
                                            <th></th>
                                            <th><input type="text" class="form-control form-control-sm" placeholder="-- Search --" /></th>
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
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        // Init DataTable empty on page load
        var tableColumns = [{
                data: null,
                orderable: false,
                searchable: false,
                render: function(d, t, r, m) {
                    return m.row + 1;
                }
            },
            {
                data: 'date_time'
            },
            {
                data: 'tenant_name'
            },
            {
                data: 'narration',
                render: function(d) {
                    return '<small>' + (d || '-') + '</small>';
                }
            },
            {
                data: 'amount',
                className: 'text-end'
            },
            {
                data: 'trans_amount',
                className: 'text-end'
            },
            {
                data: 'payment_mode'
            },
            {
                data: 'payment_channel'
            },
            {
                data: 'trans_remarks',
                render: function(d) {
                    return '<small>' + (d || '-') + '</small>';
                }
            }
        ];

        var table = $('#payments-table').DataTable({
            data: [],
            columns: tableColumns,
            paging: true,
            info: true,
            lengthChange: true,
            searching: true,
            ordering: true,
            orderCellsTop: true,
            order: [
                [1, 'asc']
            ],
            pageLength: 50
        });

        // Client-side column filters
        $('#payments-table thead .column-filters input').on('keyup change', function() {
            var colIdx = $(this).closest('th').index();
            table.column(colIdx).search(this.value).draw();
        });

        // Init Select2 for property
        $('#property_select').select2({
            placeholder: '-- Select Property --',
            allowClear: true,
            width: '100%'
        });

        function validateDates(from, to) {
            if (new Date(to) < new Date(from)) {
                alert('The end date must be on or after the start date.');
                return false;
            }
            return true;
        }

        $('#date_from').on('change', function() {
            $('#date_to').attr('min', this.value);
        });

        // Search button
        $('#search_btn').on('click', function() {
            var propertyId = $('#property_select').val();
            var dateFrom = $('#date_from').val();
            var dateTo = $('#date_to').val();

            if (!propertyId) {
                alert('Please select a property.');
                return;
            }
            if (!dateFrom || !dateTo) {
                alert('Please select both dates.');
                return;
            }
            if (!validateDates(dateFrom, dateTo)) return;

            $('#search_spinner').show();
            $('#search_icon').hide();
            $('#search_btn').prop('disabled', true);

            $.ajax({
                url: '{{ route("ajax.payments.allocated.search") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    property_id: propertyId,
                    date_from: dateFrom,
                    date_to: dateTo
                },
                success: function(response) {
                    if (response.success) {
                        $('#result_info').show();
                        $('#result_count').text(response.count);
                        $('#export_btn').prop('disabled', response.count === 0);

                        table.clear();
                        table.rows.add(response.payments);
                        table.draw();
                    } else {
                        alert(response.message || 'Search failed.');
                    }
                },
                error: function(xhr) {
                    var msg = 'Search failed.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    alert(msg);
                },
                complete: function() {
                    $('#search_spinner').hide();
                    $('#search_icon').show();
                    $('#search_btn').prop('disabled', false);
                }
            });
        });

        // Export to Excel
        $('#export_btn').on('click', function() {
            var propertyId = $('#property_select').val();
            var dateFrom = $('#date_from').val();
            var dateTo = $('#date_to').val();
            if (!propertyId || !dateFrom || !dateTo) return;
            if (!validateDates(dateFrom, dateTo)) return;

            $('#export_spinner').show();
            $('#export_icon').hide();
            $('#export_btn').prop('disabled', true);

            window.location.href = '{{ route("ajax.payments.allocated.export") }}?property_id=' + propertyId + '&date_from=' + dateFrom + '&date_to=' + dateTo;

            setTimeout(function() {
                $('#export_spinner').hide();
                $('#export_icon').show();
                $('#export_btn').prop('disabled', false);
            }, 3000);
        });
    });
</script>
@endsection
