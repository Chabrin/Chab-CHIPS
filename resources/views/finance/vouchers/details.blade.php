@extends('layouts.main')

@section('styles')
<style>
#extraInfoPanel {
    animation: fadeSlideIn 0.18s ease;
}
@keyframes fadeSlideIn {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.info-toggle-btn {
    background: none;
    border: none;
    padding: 0;
    line-height: 1;
    cursor: pointer;
}
.info-toggle-btn .material-symbols-outlined {
    font-size: 30px;
    color: #3598db;
    font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
    transition: color 0.15s;
}
.info-toggle-btn:hover .material-symbols-outlined,
.info-toggle-btn.active .material-symbols-outlined {
    color: #1a6fa8;
    font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
.hdr-label {
    font-size: 0.67rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #8e9bae;
    margin-bottom: 2px;
}
.hdr-value {
    font-size: 0.85rem;
    color: #222;
}
.extra-label {
    font-size: 0.67rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #8e9bae;
    margin-bottom: 2px;
}
.extra-value {
    font-size: 0.82rem;
    color: #333;
}
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">

                <!-- Page header -->
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="mb-0 text-muted">Voucher Details</h6>
                    <a href="{{ route('payment.vouchers.index') }}" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size:16px;">arrow_back</span> Back
                    </a>
                </div>

                <!-- Summary Card -->
                <div class="card mb-3">
                    <div class="card-body py-3 position-relative">

                        <!-- Info icon — top right -->
                        <button type="button" id="toggleInfoBtn" class="info-toggle-btn position-absolute" style="top:10px; right:14px;" title="More details">
                            <span class="material-symbols-outlined">tips_and_updates</span>
                        </button>

                        <!-- Primary fields -->
                        <div class="row g-3 align-items-end pe-5">
                            <div class="col-auto">
                                <div class="hdr-label">Voucher No.</div>
                                <div class="hdr-value fw-bold">{{ $voucher->voucher_number }}</div>
                            </div>
                            <div class="col-auto">
                                <div class="hdr-label">Date</div>
                                <div class="hdr-value">{{ $voucher->voucher_date?->format('d/m/Y') ?? '-' }}</div>
                            </div>
                            <div class="col">
                                <div class="hdr-label">Landlord</div>
                                <div class="hdr-value" title="{{ $voucher->vendor_id }}">{{ $voucher->vendor_name ?? '-' }}</div>
                            </div>
                            <div class="col-auto">
                                <div class="hdr-label">Amount</div>
                                <div class="hdr-value fw-bold">{{ number_format($voucher->debit_amount ?? 0, 2) }}</div>
                            </div>
                            <div class="col-auto">
                                <div class="hdr-label">Status</div>
                                <div class="hdr-value">
                                    @if($voucher->voucher_status === 'Paid')
                                    <span class="badge badge-light-success">Paid</span>
                                    @else
                                    <span class="badge badge-light-danger">Pending</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Extra info panel (hidden by default) -->
                        <div id="extraInfoPanel" class="border-top mt-3 pt-3" style="display:none;">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="extra-label">Description</div>
                                    <div class="extra-value">{{ $voucher->description ?? '-' }}</div>
                                </div>
                                <div class="col-md-2">
                                    <div class="extra-label">Checked By</div>
                                    <div class="extra-value">{{ $voucher->checked_by ?? '-' }}</div>
                                </div>
                                <div class="col-md-2">
                                    <div class="extra-label">Checked Status</div>
                                    <div class="extra-value">{{ $voucher->checked_status ?? 'No' }}</div>
                                </div>
                                <div class="col-md-2">
                                    <div class="extra-label">Authorized By</div>
                                    <div class="extra-value">{{ $voucher->authorized_by ?? '-' }}</div>
                                </div>
                                <div class="col-md-2">
                                    <div class="extra-label">Auth. Status</div>
                                    <div class="extra-value">{{ $voucher->authorized_status ?? 'No' }}</div>
                                </div>
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

        // Info icon toggle
        $('#toggleInfoBtn').on('click', function() {
            const $panel = $('#extraInfoPanel');
            const $btn   = $(this);
            $panel.toggle();
            $btn.toggleClass('active', $panel.is(':visible'));
        });

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
            pageLength: 25
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
