@extends('layouts.main')

@section('styles')
<style>
#cheques-table td, #cheques-table th { font-size: 0.82rem; vertical-align: middle; }
#cheques-table th.sortable { cursor: pointer; white-space: nowrap; }
#cheques-table th.sortable .arrow { font-size: 0.7rem; color: #8e9bae; margin-left: 3px; }
.chq-summary { font-size: 0.85rem; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">

                <div class="mb-3">
                    <h5 class="mb-0">Cheques</h5>
                    <small class="text-muted">Every cheque written for a payment voucher: its number, bank, payee, amount and voucher. Open a voucher from its row.</small>
                </div>

                <div class="card mb-3">
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label mb-1">Search</label>
                                <input type="text" class="form-control form-control-sm" id="fSearch" placeholder="Cheque no., voucher no., payee or narration">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-1">Status</label>
                                <select class="form-select form-select-sm" id="fStatus">
                                    <option value="">All</option>
                                    @foreach($statuses as $status)
                                    <option value="{{ $status }}">{{ $status }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-1">Printed</label>
                                <select class="form-select form-select-sm" id="fPrinted">
                                    <option value="">All</option>
                                    <option value="yes">Printed</option>
                                    <option value="no">Not printed</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1">Bank account</label>
                                <select class="form-select form-select-sm" id="fBank">
                                    <option value="">All accounts</option>
                                    @foreach($bankAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->account_name }} ({{ $account->account_number }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-1">Per page</label>
                                <select class="form-select form-select-sm" id="fPerPage">
                                    @foreach($perPage as $n)
                                    <option value="{{ $n }}" {{ $n === 50 ? 'selected' : '' }}>{{ $n }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-1">Voucher date from</label>
                                <input type="date" class="form-control form-control-sm" id="fFrom">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-1">Voucher date to</label>
                                <input type="date" class="form-control form-control-sm" id="fTo">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-1">Amount from</label>
                                <input type="number" class="form-control form-control-sm" id="fMin" min="0" step="0.01">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-1">Amount to</label>
                                <input type="number" class="form-control form-control-sm" id="fMax" min="0" step="0.01">
                            </div>
                            <div class="col-md-4 d-flex gap-2">
                                <button type="button" class="btn btn-primary btn-sm" id="applyBtn">Show cheques</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="clearBtn">Clear filters</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between py-2">
                        <div class="chq-summary">
                            <strong id="sumCount">0</strong> cheques &middot; total <strong id="sumAmount">0.00</strong>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="prevBtn">Previous</button>
                            <span class="chq-summary">Page <span id="pageNo">1</span> of <span id="pageCount">1</span></span>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="nextBtn">Next</button>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive custom-scrollbar">
                            <table class="table table-sm" id="cheques-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th class="sortable" data-sort="cheque">Cheque No. <span class="arrow"></span></th>
                                        <th>Bank</th>
                                        <th class="sortable" data-sort="payee">Payee <span class="arrow"></span></th>
                                        <th class="sortable text-end" data-sort="amount">Amount <span class="arrow"></span></th>
                                        <th class="sortable" data-sort="voucher">Voucher <span class="arrow"></span></th>
                                        <th class="sortable" data-sort="date">Voucher date <span class="arrow"></span></th>
                                        <th>Status</th>
                                        <th>Printed</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="chequeRows"><tr><td colspan="9" class="text-muted">Loading...</td></tr></tbody>
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
    var canPrint = @json($canPrint);
    var state = { page: 1, sort: 'date', dir: 'desc', pages: 1 };
    var detailsUrl = '{{ url("/payment/vouchers") }}';
    var printUrl = '{{ url("/cheque/printing") }}';

    function esc(v) { return $('<div>').text(v == null ? '' : v).html(); }
    function money(n) { return Number(n).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function statusBadge(status) {
        var cls = { Approved: 'badge-light-primary', Paid: 'badge-light-success', Pending: 'badge-light-warning', Rejected: 'badge-light-danger', Cancelled: 'badge-light-secondary' }[status] || 'badge-light-secondary';
        return '<span class="badge ' + cls + '">' + esc(status) + '</span>';
    }

    function params() {
        return {
            search: $('#fSearch').val(), status: $('#fStatus').val(), printed: $('#fPrinted').val(), bank_account_id: $('#fBank').val(),
            date_from: $('#fFrom').val(), date_to: $('#fTo').val(), amount_min: $('#fMin').val(), amount_max: $('#fMax').val(),
            per_page: $('#fPerPage').val(), sort: state.sort, dir: state.dir, page: state.page
        };
    }

    function load() {
        $('#chequeRows').html('<tr><td colspan="9" class="text-muted">Loading...</td></tr>');
        $.get('{{ route("ajax.payments.cheques.data") }}', params(), function (r) {
            if (!r.success) { $('#chequeRows').html('<tr><td colspan="9" class="text-danger">' + esc(r.message || 'Could not load the cheques.') + '</td></tr>'); return; }
            state.pages = r.pages;
            $('#sumCount').text(Number(r.total).toLocaleString());
            $('#sumAmount').text(money(r.amount));
            $('#pageNo').text(r.page);
            $('#pageCount').text(r.pages);
            $('#prevBtn').prop('disabled', r.page <= 1);
            $('#nextBtn').prop('disabled', r.page >= r.pages);

            var html = '';
            r.rows.forEach(function (row) {
                var actions = '<a class="btn btn-outline-primary btn-sm" href="' + detailsUrl + '/' + row.encrypted_voucher_id + '/details" target="_blank">View voucher</a>';
                if (canPrint && (row.status === 'Pending' || row.status === 'Approved')) {
                    actions += ' <a class="btn btn-outline-secondary btn-sm" href="' + printUrl + '/' + row.encrypted_voucher_id + '/details" target="_blank" title="Open the voucher to print its cheque">Print</a>';
                }
                html += '<tr><td><code>' + esc(row.cheque_number) + '</code>' + (row.lines > 1 ? ' <small class="text-muted">' + row.lines + ' lines</small>' : '') + '</td>'
                    + '<td>' + esc(row.bank) + '</td><td>' + esc(row.payee) + '</td><td class="text-end">' + money(row.amount) + '</td>'
                    + '<td>' + esc(row.voucher_number) + '</td><td>' + esc(row.voucher_date) + '</td><td>' + statusBadge(row.status) + '</td>'
                    + '<td>' + (row.printed ? 'Yes' : 'No') + '</td><td class="text-end text-nowrap">' + actions + '</td></tr>';
            });
            $('#chequeRows').html(html || '<tr><td colspan="9" class="text-muted">No cheques match these filters.</td></tr>');
        }).fail(function () {
            $('#chequeRows').html('<tr><td colspan="9" class="text-danger">Could not load the cheques.</td></tr>');
        });
    }

    function arrows() {
        $('#cheques-table th.sortable .arrow').text('');
        $('#cheques-table th.sortable[data-sort="' + state.sort + '"] .arrow').text(state.dir === 'asc' ? '▲' : '▼');
    }

    $('#cheques-table th.sortable').on('click', function () {
        var sort = $(this).data('sort');
        state.dir = state.sort === sort && state.dir === 'asc' ? 'desc' : 'asc';
        state.sort = sort; state.page = 1; arrows(); load();
    });
    $('#applyBtn').on('click', function () { state.page = 1; load(); });
    $('#fSearch').on('keydown', function (e) { if (e.key === 'Enter') { state.page = 1; load(); } });
    $('#fStatus, #fPrinted, #fBank, #fPerPage').on('change', function () { state.page = 1; load(); });
    $('#clearBtn').on('click', function () {
        $('#fSearch, #fFrom, #fTo, #fMin, #fMax').val('');
        $('#fStatus, #fPrinted, #fBank').val('');
        state.page = 1; load();
    });
    $('#prevBtn').on('click', function () { if (state.page > 1) { state.page--; load(); } });
    $('#nextBtn').on('click', function () { if (state.page < state.pages) { state.page++; load(); } });

    arrows();
    load();
});
</script>
@endsection
