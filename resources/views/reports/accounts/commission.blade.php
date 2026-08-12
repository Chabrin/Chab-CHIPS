@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 fw-bold" style="color:#1a1a2e;">
                <span class="material-symbols-outlined align-middle me-1" style="font-size:22px;">percent</span>
                Commission Report
            </h4>
            <small class="text-muted">Processed returns — gross, commission, VAT and net amounts per property</small>
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
                <div class="row g-2 mb-3" id="totalsCards" style="display:none!important;">
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-2 text-center" style="background:#f8f9ff;">
                            <div class="text-muted" style="font-size:0.72rem;">TOTAL GROSS</div>
                            <div class="fw-bold" id="sumGross" style="font-size:0.9rem;color:#1a1a2e;">0.00</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-2 text-center" style="background:#f8f9ff;">
                            <div class="text-muted" style="font-size:0.72rem;">TOTAL COMMISSION</div>
                            <div class="fw-bold" id="sumCommission" style="font-size:0.9rem;color:#0d6efd;">0.00</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-2 text-center" style="background:#f8f9ff;">
                            <div class="text-muted" style="font-size:0.72rem;">TOTAL VAT</div>
                            <div class="fw-bold" id="sumVat" style="font-size:0.9rem;color:#fd7e14;">0.00</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-2 text-center" style="background:#e8f5e9;">
                            <div class="text-muted" style="font-size:0.72rem;">TOTAL NET</div>
                            <div class="fw-bold" id="sumNet" style="font-size:0.9rem;color:#198754;">0.00</div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3" id="chartCard" style="display:none;"><div class="card-body"><canvas id="reportChart" height="120"></canvas></div></div>
                <span id="resultsInfo" class="text-muted d-block mb-2" style="font-size:0.82rem;"></span>
                <div class="table-responsive">
                    <table id="reportTable" class="table table-sm table-bordered table-hover mb-0" style="font-size:0.82rem;">
                        <thead style="background:#1a1a2e;color:#fff;">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Period Date</th>
                                <th>Property</th>
                                <th>Landlord</th>
                                <th class="text-end">Gross Amount (KES)</th>
                                <th class="text-end">Commission (KES)</th>
                                <th class="text-end">VAT (KES)</th>
                                <th class="text-end">Net Amount (KES)</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody id="reportBody"></tbody>
                        <tfoot>
                            <tr id="totalsRow" style="background:#e8eaf6;font-weight:600;display:none;">
                                <td colspan="4" class="text-end">TOTALS</td>
                                <td class="text-end" id="footGross">0.00</td>
                                <td class="text-end" id="footCommission">0.00</td>
                                <td class="text-end" id="footVat">0.00</td>
                                <td class="text-end" id="footNet">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
</div>

<script src="{{ asset('assets/datatables/datatables.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
$(function () {
    const LOAD_URL   = "{{ route('ajax.reports.accounts.commission') }}";
    const EXPORT_URL = "{{ route('reports.accounts.commission.export') }}";

    var reportChart = null;

    var reportDT = $('#reportTable').DataTable({
        processing: true,
        columns: [
            { data: null, orderable: false, render: function (d, t, r, m) { return m.row + 1; } },
            { data: 'period_date' },
            { data: 'property' },
            { data: 'landlord' },
            { data: 'gross', className: 'text-end' },
            { data: 'commission', className: 'text-end' },
            { data: 'vat', className: 'text-end' },
            { data: 'net', className: 'text-end' },
            {
                data: 'status_id',
                className: 'text-center',
                render: function (d) {
                    return d === 17
                        ? '<span class="badge" style="background:#198754;font-size:0.72rem;">Processed</span>'
                        : '<span class="badge" style="background:#0d6efd;font-size:0.72rem;">Approved</span>';
                }
            }
        ],
        order: [],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
        language: { emptyTable: 'Apply filters and click Load Report.' }
    });

    $('#filterForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#loadBtn').prop('disabled', true)
                               .html('<span class="spinner-border spinner-border-sm me-1"></span> Loading...');
        $.ajax({
            url: LOAD_URL,
            type: 'POST',
            data: $(this).serialize(),
            success: function (res) {
                if (!res.success) { toastr.error(res.message || 'Failed to load report'); return; }
                reportDT.clear().rows.add(res.data).draw();

                // Summary cards
                $('#sumGross').text(res.totals.gross);
                $('#sumCommission').text(res.totals.commission);
                $('#sumVat').text(res.totals.vat);
                $('#sumNet').text(res.totals.net);

                // Footer totals
                $('#footGross').text(res.totals.gross);
                $('#footCommission').text(res.totals.commission);
                $('#footVat').text(res.totals.vat);
                $('#footNet').text(res.totals.net);
                $('#totalsRow').show();

                $('#resultsInfo').text(res.count + ' record(s) found');
                $('#totalsCards').css('display', '');
                $('#exportBtn').prop('disabled', false);
                // Group by property, sum commission
                var byProp = {};
                res.data.forEach(function(r) {
                    var p = r.property || 'Unknown';
                    byProp[p] = (byProp[p] || 0) + (parseFloat(r.commission) || 0);
                });
                var propLabels = Object.keys(byProp);
                var propValues = propLabels.map(function(k){ return byProp[k]; });
                if (reportChart) { reportChart.destroy(); }
                reportChart = new Chart(document.getElementById('reportChart').getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: propLabels,
                        datasets: [{ label: 'Commission (KES)', data: propValues, backgroundColor: '#16a085', borderRadius: 3 }]
                    },
                    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
                });
                $('#chartCard').show();
            },
            error: function () { toastr.error('Failed to load report'); },
            complete: function () {
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
