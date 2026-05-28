@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Payment Vouchers</h5>
                        @permission('create_payment_vouchers')
                        <a href="{{ route('payment.vouchers.create') }}" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                            <span class="material-symbols-outlined" style="font-size:16px;">add</span> Create Payment Voucher
                        </a>
                        @endpermission
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-3 align-items-end">
                            <div class="col-md-2">
                                <label for="year_select" class="form-label mb-1">Year</label>
                                <select class="form-control form-control-sm" id="year_select">
                                    @foreach($years as $yr)
                                    <option value="{{ $yr }}" {{ $yr == $currentYear ? 'selected' : '' }}>{{ $yr }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="month_select" class="form-label mb-1">Month</label>
                                <select class="form-control form-control-sm" id="month_select">
                                    <option value="1" {{ $currentMonth == 1 ? 'selected' : '' }}>January</option>
                                    <option value="2" {{ $currentMonth == 2 ? 'selected' : '' }}>February</option>
                                    <option value="3" {{ $currentMonth == 3 ? 'selected' : '' }}>March</option>
                                    <option value="4" {{ $currentMonth == 4 ? 'selected' : '' }}>April</option>
                                    <option value="5" {{ $currentMonth == 5 ? 'selected' : '' }}>May</option>
                                    <option value="6" {{ $currentMonth == 6 ? 'selected' : '' }}>June</option>
                                    <option value="7" {{ $currentMonth == 7 ? 'selected' : '' }}>July</option>
                                    <option value="8" {{ $currentMonth == 8 ? 'selected' : '' }}>August</option>
                                    <option value="9" {{ $currentMonth == 9 ? 'selected' : '' }}>September</option>
                                    <option value="10" {{ $currentMonth == 10 ? 'selected' : '' }}>October</option>
                                    <option value="11" {{ $currentMonth == 11 ? 'selected' : '' }}>November</option>
                                    <option value="12" {{ $currentMonth == 12 ? 'selected' : '' }}>December</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex gap-2 align-items-end">
                                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="search_btn">
                                    <span class="spinner-border spinner-border-sm" role="status" id="search_spinner" style="display:none;"></span>
                                    <span class="material-symbols-outlined" id="search_icon">search</span>
                                    <span class="ms-1">Search</span>
                                </button>
                            </div>
                            <div class="col d-flex justify-content-end align-items-end">
                                <small class="text-muted me-3" id="result_info" style="display:none;">Found <strong id="result_count">0</strong> vouchers</small>
                                <div>
                                    <label for="status_filter" class="form-label mb-1">Status</label>
                                    <select class="form-control form-control-sm" id="status_filter">
                                        <option value="">All</option>
                                        <option value="Pending">Pending</option>
                                        <option value="Paid">Paid</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="vouchers-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Voucher No.</th>
                                        <th>Date</th>
                                        <th>Payee</th>
                                        <th>Description</th>
                                        <th class="text-end">Amount</th>
                                        <th>Lines</th>
                                        <th>Status</th>
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
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        var table = $('#vouchers-table').DataTable({
            data: [],
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(d, t, r, m) {
                        return m.row + 1;
                    }
                },
                {
                    data: 'voucher_number'
                },
                {
                    data: 'voucher_date'
                },
                {
                    data: 'vendor_name',
                    render: function(d, t, r) {
                        return '<small title="' + (r.vendor_id || '') + '">' + (d || '-') + '</small>';
                    }
                },
                {
                    data: 'description',
                    render: function(d) {
                        return '<small>' + (d || '-') + '</small>';
                    }
                },
                {
                    data: 'debit_amount',
                    className: 'text-end fw-bold'
                },
                {
                    data: 'entry_count',
                    className: 'text-center'
                },
                {
                    data: 'voucher_status',
                    render: function(d) {
                        if (d === 'Paid') return '<span class="badge badge-light-success">Paid</span>';
                        if (d === 'Approved') return '<span class="badge badge-light-primary">Approved</span>';
                        return '<span class="badge badge-light-danger">Pending</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(d, t, r) {
                        return '<a href="/payment/vouchers/' + r.encrypted_id + '/details" target="_blank" title="View Details" style="color:#7366ff;">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">open_in_new</span></a>';
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            searching: true,
            ordering: true,
            order: [
                [1, 'desc']
            ],
            pageLength: 10
        });

        function loadData() {
            var year = $('#year_select').val();
            var month = $('#month_select').val();

            $('#search_spinner').show();
            $('#search_icon').hide();
            $('#search_btn').prop('disabled', true);

            $.ajax({
                url: '{{ route("ajax.payment.vouchers.data") }}',
                type: 'GET',
                data: {
                    year: year,
                    month: month
                },
                success: function(response) {
                    if (response.success) {
                        $('#result_info').show();
                        $('#result_count').text(response.count);
                        table.clear();
                        table.rows.add(response.data);
                        table.draw();
                    }
                },
                error: function(xhr) {
                    var msg = 'Failed to load data.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    alert(msg);
                },
                complete: function() {
                    $('#search_spinner').hide();
                    $('#search_icon').show();
                    $('#search_btn').prop('disabled', false);
                }
            });
        }

        // Custom search for status filter on column 7
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            if (settings.nTable.id !== 'vouchers-table') return true;
            var selected = $('#status_filter').val();
            if (!selected) return true;
            var cellText = $(data[7]).text() || data[7];
            return cellText === selected;
        });

        $('#status_filter').on('change', function() {
            table.draw();
        });
        $('#search_btn').on('click', loadData);
        loadData();
    });
</script>
@endsection