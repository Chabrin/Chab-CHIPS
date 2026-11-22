@extends('layouts.main')

@section('styles')
<style>
#flow-table td, #flow-table th { font-size: 0.8rem; vertical-align: top; }
.flow-approval { white-space: nowrap; font-size: 0.75rem; }
.flow-reason { color: #b02a37; font-size: 0.75rem; margin-top: 2px; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">

                <div class="mb-3">
                    <h5 class="mb-0">Returns Flow</h5>
                    <small class="text-muted">Where every return is: generated, each approval level with who acted and when, the payment voucher, and whether it is paid. View only.</small>
                </div>

                <div class="card mb-3">
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label mb-1">Year</label>
                                <select class="form-select form-select-sm" id="fYear">
                                    <option value="">All</option>
                                    @foreach($years as $year)
                                    <option value="{{ $year }}" {{ $year == date('Y') ? 'selected' : '' }}>{{ $year }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-1">Month</label>
                                <select class="form-select form-select-sm" id="fMonth">
                                    <option value="">All</option>
                                    @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1">Stage</label>
                                <select class="form-select form-select-sm" id="fStage">
                                    <option value="">All stages</option>
                                    @foreach($stages as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1">Return, landlord or property</label>
                                <input type="text" class="form-control form-control-sm" id="fSearch" placeholder="Search">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-primary btn-sm w-100" id="applyBtn">Show returns</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header card-no-border py-2">
                        <h6 class="mb-0">Returns <span id="countBadge" class="badge badge-light-primary ms-1">0</span>
                            <small class="text-muted ms-2" id="limitNote" style="display:none;">Showing the first 2,000. Narrow the filters to see the rest.</small></h6>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="flow-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>Return</th>
                                        <th>Landlord</th>
                                        <th>Property</th>
                                        <th>Period</th>
                                        <th class="text-end">Net</th>
                                        <th>Stage</th>
                                        <th>Generated</th>
                                        <th>Approvals</th>
                                        <th>Voucher</th>
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
    function esc(value) { return $('<div>').text(value == null ? '' : value).html(); }
    function money(n) { return Number(n).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function stageBadge(stage, paid) {
        var cls = 'badge-light-secondary';
        if (stage === 'Paid') { cls = 'badge-light-success'; }
        else if (stage === 'Rejected') { cls = 'badge-light-danger'; }
        else if (stage.indexOf('Awaiting') === 0 || stage === 'Voucher awaiting approval') { cls = 'badge-light-warning'; }
        else if (stage === 'Approved, no voucher yet' || stage === 'Voucher approved') { cls = 'badge-light-primary'; }
        return '<span class="badge ' + cls + '">' + esc(stage) + '</span>';
    }

    var table = $('#flow-table').DataTable({
        data: [],
        columns: [
            { data: 'return_code', render: function (d, t, r) { return '<code>' + esc(d) + '</code>' + (r.type && r.type !== 'MAINRETURN' ? '<br><small class="text-muted">' + esc(r.type) + '</small>' : ''); } },
            { data: 'landlord', render: function (d) { return esc(d); } },
            { data: 'property', render: function (d) { return esc(d); } },
            { data: 'period', render: function (d) { return esc(d); } },
            { data: 'net_balance', className: 'text-end', render: function (d, t) { return t === 'display' ? money(d) : d; } },
            { data: 'stage', render: function (d, t, r) { return stageBadge(d) + (r.rejection ? '<div class="flow-reason">' + esc(r.rejection) + '</div>' : ''); } },
            { data: 'generated', render: function (d) { return '<span class="flow-approval">' + esc(d) + '</span>'; } },
            { data: 'approvals', orderable: false, render: function (d, t, r) {
                if (!d || !d.length) { return '<span class="text-muted">-</span>'; }
                return d.map(function (a) { return '<div class="flow-approval">' + esc(a) + '</div>'; }).join('');
            } },
            { data: 'voucher', render: function (d) { return esc(d); } }
        ],
        order: [],
        pageLength: 25,
        language: { emptyTable: 'No returns for these filters', loadingRecords: 'Loading...' }
    });

    function loadData() {
        $.ajax({
            url: '{{ route("ajax.returns.flow.data") }}',
            type: 'GET',
            data: { year: $('#fYear').val(), month: $('#fMonth').val(), stage: $('#fStage').val(), search: $('#fSearch').val() },
            success: function (r) {
                if (r.success) {
                    table.clear().rows.add(r.data).draw();
                    $('#countBadge').text(r.data.length);
                    $('#limitNote').toggle(!!r.limit_reached);
                } else {
                    toastr.error(r.message || 'Failed to load the returns flow');
                }
            },
            error: function () { toastr.error('Failed to load the returns flow'); }
        });
    }

    $('#applyBtn').on('click', loadData);
    $('#fSearch').on('keydown', function (e) { if (e.key === 'Enter') { loadData(); } });
    loadData();
});
</script>
@endsection
