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
.section-divider {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    color: #3598db;
    margin-bottom: 8px;
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
#lines-table td, #lines-table th { font-size: 0.8rem; }
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
                            <div class="col-auto">
                                <div class="hdr-label">Payee Type</div>
                                <div class="hdr-value">
                                    <span class="badge badge-light-info">{{ $voucher->vendor_type ?? '-' }}</span>
                                </div>
                            </div>
                            <div class="col">
                                <div class="hdr-label">Payee</div>
                                <div class="hdr-value fw-semibold" title="{{ $voucher->vendor_id }}">{{ $voucher->vendor_name ?? '-' }}</div>
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

                        <!-- Extra info panel -->
                        <div id="extraInfoPanel" class="border-top mt-3 pt-3" style="display:none;">
                            <div class="row g-0">

                                <!-- Left: Voucher meta -->
                                <div class="col-md-7 pe-4">
                                    <div class="section-divider">Voucher Info</div>
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <div class="extra-label">Description</div>
                                            <div class="extra-value">{{ $voucher->description ?? '-' }}</div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="extra-label">Prepared By</div>
                                            <div class="extra-value">{{ $voucher->prepared_by ?? '-' }}</div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="extra-label">Checked By</div>
                                            <div class="extra-value">{{ $voucher->checked_by ?? '-' }}</div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="extra-label">Check Status</div>
                                            <div class="extra-value">{{ $voucher->checked_status ?? 'No' }}</div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="extra-label">Auth. By</div>
                                            <div class="extra-value">{{ $voucher->authorized_by ?? '-' }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Divider -->
                                <div class="col-auto d-none d-md-flex px-3">
                                    <div style="width:1px; background:#e9ecef;"></div>
                                </div>

                                <!-- Right: Payee details -->
                                <div class="col-md-4 ps-md-3 pt-3 pt-md-0">
                                    <div class="section-divider">Payee Details</div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="extra-label">Name</div>
                                            <div class="extra-value fw-semibold">{{ $voucher->vendor_name ?? '-' }}</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="extra-label">Type</div>
                                            <div class="extra-value">{{ $voucher->vendor_type ?? '-' }}</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="extra-label">Ref / ID</div>
                                            <div class="extra-value">{{ $voucher->vendor_id ?? '-' }}</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="extra-label">Month / Year</div>
                                            <div class="extra-value">
                                                @php
                                                    $monthName = \Carbon\Carbon::createFromDate(null, $voucher->month, null)->format('F');
                                                @endphp
                                                {{ $monthName }} {{ $voucher->year }}
                                            </div>
                                        </div>
                                    </div>
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
                                        <th>Description</th>
                                        <th>Payee</th>
                                        <th>Source Account No.</th>
                                        <th>Source Bank / Account</th>
                                        <th>Cheque No.</th>
                                        <th class="text-end">Amount</th>
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
            columns: [
                {
                    data: null,
                    orderable: false,
                    render: function(d, t, r, m) { return m.row + 1; }
                },
                {
                    data: 'entry_description',
                    render: function(d) { return '<span style="font-size:0.8rem;">' + (d || '-') + '</span>'; }
                },
                {
                    data: 'payee_name',
                    render: function(d) {
                        return d && d !== '-'
                            ? '<span class="fw-semibold" style="font-size:0.8rem;">' + d + '</span>'
                            : '<span class="text-muted">—</span>';
                    }
                },
                {
                    data: 'account_number',
                    render: function(d) {
                        return d && d !== '-'
                            ? '<code style="font-size:0.78rem;">' + d + '</code>'
                            : '<span class="text-muted">—</span>';
                    }
                },
                {
                    data: 'account_bank',
                    render: function(d) {
                        return d && d !== '-'
                            ? '<small>' + d + '</small>'
                            : '<span class="text-muted">—</span>';
                    }
                },
                {
                    data: 'cheque_number',
                    render: function(d) {
                        return d && d !== '-'
                            ? '<span class="badge badge-light-warning fw-semibold">' + d + '</span>'
                            : '<span class="text-muted">—</span>';
                    }
                },
                {
                    data: 'amount',
                    className: 'text-end fw-bold'
                },
                {
                    data: 'is_approved',
                    className: 'text-center',
                    render: function(d) {
                        if (d === 'Yes') return '<span class="badge badge-light-success">Yes</span>';
                        if (d === 'No')  return '<span class="badge badge-light-danger">No</span>';
                        return d || '—';
                    }
                }
            ],
            paging: true,
            info: true,
            searching: true,
            ordering: true,
            order: [[0, 'asc']],
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
                    toastr.error('Failed to load voucher lines.');
                }
            });
        }

        loadLines();
    });
</script>
@endsection
