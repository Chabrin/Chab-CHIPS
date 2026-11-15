@extends('layouts.main')

@section('styles')
<style>
.status-dot {
    width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 5px;
}
#printing-table td, #printing-table th { font-size: 0.82rem; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">

                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h5 class="mb-0">Cheque Printing</h5>
                        <small class="text-muted">Vouchers awaiting cheque/voucher printing before approval</small>
                    </div>
                    @permission('create_payment_vouchers')
                    <a href="{{ route('payment.vouchers.create') }}" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size:16px;">add</span> New Voucher
                    </a>
                    @endpermission
                </div>

                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between py-2">
                        <h6 class="mb-0">
                            Unprinted Vouchers
                            <span id="countBadge" class="badge badge-light-warning ms-1">—</span>
                        </h6>
                        <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" id="refreshBtn">
                            <span class="material-symbols-outlined" style="font-size:15px;">refresh</span> Refresh
                        </button>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="printing-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Voucher No.</th>
                                        <th>Date</th>
                                        <th>Payee</th>
                                        <th>Type</th>
                                        <th>Cheque No.</th>
                                        <th class="text-end">Amount</th>
                                        <th>Prepared By</th>
                                        <th>Status</th>
                                        <th>Actions</th>
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
$(document).ready(function () {

    var table = $('#printing-table').DataTable({
        data: [],
        columns: [
            {
                data: null, orderable: false,
                render: function (d, t, r, m) { return m.row + 1; }
            },
            {
                data: 'voucher_number',
                render: function (d) {
                    return '<span class="fw-semibold" style="font-size:0.82rem;">' + (d || '-') + '</span>';
                }
            },
            { data: 'voucher_date' },
            {
                data: 'vendor_name',
                render: function (d, t, r) {
                    return '<span style="font-size:0.82rem;">' + (d || '-') + '</span>'
                        + (r.vendor_type ? ' <small class="text-muted">(' + r.vendor_type + ')</small>' : '');
                }
            },
            {
                data: 'vendor_type',
                render: function (d) {
                    var cls = d === 'Landlord' ? 'badge-light-info' : 'badge-light-secondary';
                    return '<span class="badge ' + cls + '">' + (d || '-') + '</span>';
                }
            },
            {
                data: 'cheque_number',
                render: function (d) {
                    return d && d !== '-'
                        ? '<code style="font-size:0.78rem;">' + d + '</code>'
                        : '<span class="text-muted">—</span>';
                }
            },
            {
                data: 'debit_amount',
                className: 'text-end',
                render: function (d) {
                    return '<span class="fw-semibold">' + (d || '0.00') + '</span>';
                }
            },
            { data: 'prepared_by' },
            {
                data: 'voucher_status',
                render: function (d) {
                    var cls = d === 'Approved' ? 'badge-light-success' : 'badge-light-warning';
                    return '<span class="badge ' + cls + '">' + (d || 'Pending') + '</span>';
                }
            },
            {
                data: null, orderable: false,
                render: function (d, t, r) {
                    @permission('read_cheques_printing')
                    return '<a href="{{ route("cheque.printing.details", "PH") }}'.replace('PH', r.encrypted_id)
                        + '" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1">'
                        + '<span class="material-symbols-outlined" style="font-size:14px;">print</span> Open</a>';
                    @endpermission
                    return '<span class="text-muted" style="font-size:0.75rem;">No access</span>';
                }
            },
        ],
        order: [[0, 'asc']],
        pageLength: 25,
        language: {
            emptyTable: '<div class="text-center py-4 text-muted"><span class="material-symbols-outlined" style="font-size:36px;display:block;margin-bottom:6px;">check_circle</span>No unprinted vouchers — all caught up!</div>',
            loadingRecords: 'Loading...',
        }
    });

    function loadData() {
        $.ajax({
            url: '{{ route("ajax.cheque.printing.data") }}',
            type: 'GET',
            success: function (r) {
                if (r.success) {
                    table.clear().rows.add(r.data).draw();
                    $('#countBadge').text(r.data.length);
                }
            },
            error: function () {
                toastr.error('Failed to load unprinted vouchers');
            }
        });
    }

    loadData();

    $('#refreshBtn').on('click', loadData);
});
</script>
@endsection
