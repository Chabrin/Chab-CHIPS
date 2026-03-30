@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <!-- Header -->
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="mb-0">{{ $voucher->voucher_number }}</h6>
                    <a href="{{ route('payment.vouchers.index') }}" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size:16px;">arrow_back</span> Back
                    </a>
                </div>

                <!-- Summary Card -->
                <div class="card mb-3">
                    <div class="card-body py-2">
                        <div class="row" style="font-size:0.78rem;">
                            <div class="col-md-2">
                                <strong>Voucher No.</strong><br>
                                <span id="hdr_voucher_number">{{ $voucher->voucher_number }}</span>
                            </div>
                            <div class="col-md-2">
                                <strong>Date</strong><br>
                                <span id="hdr_date">{{ $voucher->voucher_date?->format('d/m/Y') ?? '-' }}</span>
                            </div>
                            <div class="col-md-3">
                                <strong>Landlord</strong><br>
                                <span id="hdr_vendor" title="{{ $voucher->vendor_id }}">{{ $voucher->vendor_name ?? '-' }}</span>
                            </div>
                            <div class="col-md-2">
                                <strong>Amount</strong><br>
                                <span class="fw-bold" id="hdr_amount">{{ number_format($voucher->debit_amount ?? 0, 2) }}</span>
                            </div>
                            <div class="col-md-1">
                                <strong>Status</strong><br>
                                @if($voucher->voucher_status === 'Paid')
                                <span class="badge badge-light-success">Paid</span>
                                @else
                                <span class="badge badge-light-danger">Pending</span>
                                @endif
                            </div>
                            <div class="col-md-2">
                                <strong>Prepared By</strong><br>
                                <span>{{ $voucher->prepared_by ?? '-' }}</span>
                            </div>
                        </div>
                        <div class="row mt-2" style="font-size:0.78rem;">
                            <div class="col-md-3">
                                <strong>Description</strong><br>
                                <span>{{ $voucher->description ?? '-' }}</span>
                            </div>
                            <div class="col-md-2">
                                <strong>Checked By</strong><br>
                                <span>{{ $voucher->checked_by ?? '-' }}</span>
                            </div>
                            <div class="col-md-2">
                                <strong>Checked Status</strong><br>
                                <span>{{ $voucher->checked_status ?? '-' }}</span>
                            </div>
                            <div class="col-md-2">
                                <strong>Authorized By</strong><br>
                                <span>{{ $voucher->authorized_by ?? '-' }}</span>
                            </div>
                            <div class="col-md-2">
                                <strong>Auth. Status</strong><br>
                                <span>{{ $voucher->authorized_status ?? '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lines Table -->
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between py-2">
                        <h6 class="mb-0">Voucher Lines</h6>
                        <small class="text-muted" id="lines_info" style="display:none;"><strong id="lines_count">0</strong> entries</small>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="lines-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Property</th>
                                        <th>Unit</th>
                                        <th>Description</th>
                                        <th>DR/CR</th>
                                        <th class="text-end">Amount</th>
                                        <th>GL Account</th>
                                        <th>Approved</th>
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
        var table = $('#lines-table').DataTable({
            data: [],
            columns: [{
                    data: 'entry_number'
                },
                {
                    data: 'property_code'
                },
                {
                    data: 'property_description',
                    render: function(d) {
                        return '<small>' + (d || '-') + '</small>';
                    }
                },
                {
                    data: 'entry_description',
                    render: function(d) {
                        return '<small>' + (d || '-') + '</small>';
                    }
                },
                {
                    data: 'dr_cr',
                    className: 'text-center'
                },
                {
                    data: 'amount',
                    className: 'text-end fw-bold'
                },
                {
                    data: 'gl_account'
                },
                {
                    data: 'is_approved',
                    render: function(d) {
                        if (d === 'Yes') return '<span class="text-success">Yes</span>';
                        if (d === 'No') return '<span class="text-danger">No</span>';
                        return d || '-';
                    }
                }
            ],
            paging: true,
            info: true,
            searching: true,
            ordering: true,
            order: [
                [0, 'asc']
            ],
            pageLength: 50
        });

        function loadLines() {
            $.ajax({
                url: '/ajax/payment/vouchers/{{ encrypt($voucher->id) }}/lines',
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        $('#lines_info').show();
                        $('#lines_count').text(response.count);
                        table.clear();
                        table.rows.add(response.data);
                        table.draw();
                    }
                },
                error: function() {
                    alert('Failed to load voucher lines.');
                }
            });
        }

        loadLines();
    });
</script>
@endsection