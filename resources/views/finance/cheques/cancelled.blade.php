@extends('layouts.main')

@section('styles')
<style>
#cancelled-table td, #cancelled-table th { font-size: 0.82rem; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">

                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h5 class="mb-0">Cancelled Cheques</h5>
                        <small class="text-muted">Every cheque that was cancelled, with the voucher it was for and why. A cancelled cheque number is never used again.</small>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label mb-1">Cancelled from</label>
                                <input type="date" class="form-control form-control-sm" id="dateFrom">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-1">Cancelled to</label>
                                <input type="date" class="form-control form-control-sm" id="dateTo">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1">Bank account</label>
                                <select class="form-select form-select-sm" id="bankAccount">
                                    <option value="">All accounts</option>
                                    @foreach($bankAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->account_name }} ({{ $account->account_number }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1">Cheque, voucher or reason</label>
                                <input type="text" class="form-control form-control-sm" id="searchBox" placeholder="Search">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-primary btn-sm w-100" id="applyBtn">Show cheques</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between py-2">
                        <h6 class="mb-0">Cancelled <span id="countBadge" class="badge badge-light-danger ms-1">0</span></h6>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="cancelled-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>Cheque No.</th>
                                        <th>Voucher</th>
                                        <th>Bank account</th>
                                        <th>Cheque book</th>
                                        <th>Cancelled by</th>
                                        <th>Cancelled on</th>
                                        <th>Reason</th>
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
    function esc(value) {
        return $('<div>').text(value == null ? '' : value).html();
    }

    var table = $('#cancelled-table').DataTable({
        data: [],
        columns: [
            { data: 'cheque_number', render: function (d) { return '<code>' + esc(d) + '</code>'; } },
            { data: 'voucher_number', render: function (d) { return esc(d); } },
            { data: 'bank_account', render: function (d) { return esc(d); } },
            { data: 'book_reference', render: function (d) { return esc(d); } },
            { data: 'cancelled_by', render: function (d) { return esc(d); } },
            { data: 'cancelled_at', render: function (d) { return esc(d); } },
            { data: 'reason', render: function (d) { return esc(d); } }
        ],
        order: [[5, 'desc']],
        pageLength: 25,
        language: {
            emptyTable: 'No cancelled cheques',
            loadingRecords: 'Loading...'
        }
    });

    function loadData() {
        $.ajax({
            url: '{{ route("ajax.cheque.cancelled.data") }}',
            type: 'GET',
            data: {
                date_from: $('#dateFrom').val(),
                date_to: $('#dateTo').val(),
                bank_account_id: $('#bankAccount').val(),
                search: $('#searchBox').val()
            },
            success: function (r) {
                if (r.success) {
                    table.clear().rows.add(r.data).draw();
                    $('#countBadge').text(r.data.length);
                } else {
                    toastr.error(r.message || 'Failed to load cancelled cheques');
                }
            },
            error: function () {
                toastr.error('Failed to load cancelled cheques');
            }
        });
    }

    $('#applyBtn').on('click', loadData);
    $('#searchBox').on('keydown', function (e) { if (e.key === 'Enter') { loadData(); } });
    loadData();
});
</script>
@endsection
