@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 fw-bold" style="color:#1a1a2e;">
                <span class="material-symbols-outlined align-middle me-1" style="font-size:22px;">currency_exchange</span>
                Recovery Register
            </h4>
            <small class="text-muted">Rent paid to the landlord but not received: by reason, landlord, tenant and age</small>
        </div>
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary">
            <span class="material-symbols-outlined align-middle" style="font-size:14px;">arrow_back</span> All Reports
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form id="filterForm">
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-2">
                        <label class="form-label form-label-sm mb-1">Date From</label>
                        <input type="date" class="form-control form-control-sm" name="date_from" id="date_from">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label form-label-sm mb-1">Date To</label>
                        <input type="date" class="form-control form-control-sm" name="date_to" id="date_to">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label form-label-sm mb-1">Reason</label>
                        <select class="form-select form-select-sm" name="type_id" id="type_id">
                            <option value="">All reasons</option>
                            @foreach($reasons as $reason)
                                <option value="{{ $reason->id }}">{{ $reason->type_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label form-label-sm mb-1">Status</label>
                        <select class="form-select form-select-sm" name="status" id="status">
                            <option value="">All</option>
                            <option value="1">Pending</option>
                            <option value="5">Confirmed / Approved</option>
                            <option value="41">Rejected</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label form-label-sm mb-1">Property</label>
                        <select class="form-select form-select-sm" name="property_id" id="property_id">
                            <option value="">All Properties</option>
                            @foreach($properties as $prop)
                                <option value="{{ $prop->id }}">{{ $prop->reference_number }} — {{ $prop->property_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-auto d-flex gap-2">
                        <button type="submit" id="loadBtn" class="btn btn-primary btn-sm">
                            <span class="material-symbols-outlined align-middle" style="font-size:14px;">refresh</span> Load Report
                        </button>
                        <button type="button" id="exportBtn" class="btn btn-success btn-sm" disabled>
                            <span class="material-symbols-outlined align-middle" style="font-size:14px;">download</span> Export Excel
                        </button>
                    </div>
                </div>
            </form>

            <div id="resultsSection" class="mt-3">
                <span id="resultsInfo" class="text-muted d-block mb-2" style="font-size:0.82rem;"></span>
                <div class="table-responsive">
                    <table id="reportTable" class="table table-sm table-bordered table-hover mb-0" style="font-size:0.82rem;">
                        <thead style="background:#1a1a2e;color:#fff;">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Reference</th>
                                <th>Recovery Date</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Landlord</th>
                                <th>Property</th>
                                <th>Unit</th>
                                <th>Tenant</th>
                                <th>Invoice</th>
                                <th>Narration</th>
                                <th class="text-end">Amount (KES)</th>
                                <th class="text-center">Commissionable</th>
                                <th>Receipt</th>
                                <th>Returns Batch</th>
                                <th class="text-end">Age (days)</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <div id="summarySection" class="row g-3 mt-2 d-none">
                    @foreach(['reason' => 'By reason', 'age' => 'By age', 'landlord' => 'By landlord', 'tenant' => 'By tenant'] as $key => $title)
                    <div class="col-12 col-md-6">
                        <div class="border rounded">
                            <div class="px-2 py-1 fw-bold" style="background:#f1f3f9;font-size:0.82rem;">{{ $title }}</div>
                            <div style="max-height:220px;overflow:auto;">
                                <table class="table table-sm mb-0" style="font-size:0.8rem;">
                                    <thead><tr><th>{{ ucfirst($key) }}</th><th class="text-end">Count</th><th class="text-end">Amount (KES)</th></tr></thead>
                                    <tbody id="summary-{{ $key }}"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

</div>
</div>

<script src="{{ asset('assets/datatables/datatables.min.js') }}"></script>
<script>
$(function () {
    const LOAD_URL   = "{{ route('ajax.reports.accounts.recovery-register') }}";
    const EXPORT_URL = "{{ route('reports.accounts.recovery-register.export') }}";

    // The columns hold free text (narration, names): show it as text, never as HTML
    function esc(d) { return $('<div>').text(d == null ? '' : d).html(); }

    var reportDT = $('#reportTable').DataTable({
        processing : true,
        columns    : [
            { data: null, orderable: false, render: function (d, t, r, m) { return m.row + 1; } },
            { data: 'reference', render: esc },
            { data: 'recovery_date', render: esc },
            { data: 'reason', render: esc },
            { data: 'status', render: esc },
            { data: 'landlord', render: esc },
            { data: 'property', render: esc },
            { data: 'unit', render: esc },
            { data: 'tenant', render: esc },
            { data: 'invoice', render: esc },
            { data: 'narration', render: esc },
            { data: 'amount', className: 'text-end', render: esc },
            {
                data: 'commissionable',
                className: 'text-center',
                render: function (d) {
                    return d === 'Yes'
                        ? '<span class="badge bg-success" style="font-size:0.72rem;">Yes</span>'
                        : '<span class="badge bg-secondary" style="font-size:0.72rem;">No</span>';
                }
            },
            { data: 'receipt', render: esc },
            { data: 'returns_batch', render: esc },
            { data: 'age_days', className: 'text-end', defaultContent: '-' }
        ],
        order      : [],
        pageLength : 25,
        lengthMenu : [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
        language   : { emptyTable: 'Apply filters and click Load Report.' }
    });

    function renderSummary(summary) {
        var groups = { reason: summary.by_reason, age: summary.by_age, landlord: summary.by_landlord, tenant: summary.by_tenant };
        Object.keys(groups).forEach(function (key) {
            var $body = $('#summary-' + key).empty();
            groups[key].forEach(function (g) {
                $body.append($('<tr>').append(
                    $('<td>').text(g.label),
                    $('<td class="text-end">').text(g.count),
                    $('<td class="text-end">').text(Number(g.amount).toLocaleString('en-KE', { minimumFractionDigits: 2 }))
                ));
            });
        });
        $('#summarySection').removeClass('d-none');
    }

    $('#filterForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#loadBtn').prop('disabled', true)
                               .html('<span class="spinner-border spinner-border-sm me-1"></span> Loading...');
        $.ajax({
            url     : LOAD_URL,
            type    : 'POST',
            data    : $(this).serialize(),
            success : function (res) {
                if (!res.success) { toastr.error(res.message || 'Failed to load report'); return; }
                reportDT.clear().rows.add(res.data).draw();
                $('#resultsInfo').text(res.count + ' recovery record(s) found, KES ' + Number(res.summary.total.amount).toLocaleString('en-KE', { minimumFractionDigits: 2 }));
                renderSummary(res.summary);
                $('#exportBtn').prop('disabled', false);
            },
            error    : function () { toastr.error('Failed to load report'); },
            complete : function () {
                $btn.prop('disabled', false)
                    .html('<span class="material-symbols-outlined align-middle" style="font-size:14px;">refresh</span> Load Report');
            }
        });
    });

    $('#exportBtn').on('click', function () {
        var $form = $('<form>', { method: 'POST', action: EXPORT_URL });
        $form.append($('#filterForm').find('input, select').clone());
        $('body').append($form);
        $form.submit();
        $form.remove();
    });
});
</script>
@endsection
