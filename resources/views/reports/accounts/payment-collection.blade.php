@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 fw-bold" style="color:#1a1a2e;">
                <span class="material-symbols-outlined align-middle me-1" style="font-size:22px;">payments</span>
                Payment Collection Report
            </h4>
            <small class="text-muted">All tenant payments received within a specified period</small>
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
                        <label class="form-label form-label-sm mb-1">Date From <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-sm" name="date_from" id="date_from" required>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label form-label-sm mb-1">Date To <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-sm" name="date_to" id="date_to" required>
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
                    <div class="col-12 col-md-2">
                        <label class="form-label form-label-sm mb-1">Channel</label>
                        <select class="form-select form-select-sm" name="channel_id" id="channel_id">
                            <option value="">All Channels</option>
                            @foreach($channels as $ch)
                                <option value="{{ $ch->id }}">{{ $ch->channel_name }}</option>
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
                <div class="card border-0 shadow-sm mb-3" id="chartCard" style="display:none;"><div class="card-body"><canvas id="reportChart" height="120"></canvas></div></div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span id="resultsInfo" class="text-muted" style="font-size:0.82rem;"></span>
                    <span class="fw-bold" style="font-size:0.85rem;">
                        Total Collected: KES <span id="totalAmount">0.00</span>
                    </span>
                </div>
                <div class="table-responsive">
                    <table id="reportTable" class="table table-sm table-bordered table-hover mb-0" style="font-size:0.82rem;">
                        <thead style="background:#1a1a2e;color:#fff;">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Date</th>
                                <th>Receipt No</th>
                                <th>Tenant Name</th>
                                <th>Property</th>
                                <th>Unit</th>
                                <th class="text-end">Amount (KES)</th>
                                <th>Channel</th>
                                <th>Narration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="reportBody"></tbody>
                        <tfoot>
                            <tr style="background:#f0f2ff;font-weight:600;">
                                <td colspan="6" class="text-end">Total</td>
                                <td class="text-end" id="totalRow">0.00</td>
                                <td colspan="3"></td>
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
    const LOAD_URL   = "{{ route('ajax.reports.accounts.payment-collection') }}";
    const EXPORT_URL = "{{ route('reports.accounts.payment-collection.export') }}";

    var reportChart = null;
    var STATUS_CLASSES = {11: 'bg-warning text-dark', 12: 'bg-info text-white', 13: 'bg-success text-white'};

    var reportDT = $('#reportTable').DataTable({
        processing: true,
        columns: [
            { data: null, orderable: false, render: function (d, t, r, m) { return m.row + 1; } },
            { data: 'date' },
            { data: 'receipt_no' },
            { data: 'tenant_name' },
            { data: 'property' },
            { data: 'unit' },
            { data: 'amount', className: 'text-end' },
            { data: 'channel' },
            { data: 'narration' },
            {
                data: 'status',
                render: function (d, t, r) {
                    var cls = STATUS_CLASSES[r.status_id] || 'bg-secondary text-white';
                    return '<span class="badge ' + cls + '" style="font-size:0.72rem;">' + d + '</span>';
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
                $('#totalAmount').text(res.total);
                $('#totalRow').text(res.total);
                $('#resultsInfo').text(res.count + ' payment(s) found');
                $('#exportBtn').prop('disabled', false);
                // Group by date, sum amounts
                var byDate = {};
                res.data.forEach(function(r) {
                    var d = r.date || 'Unknown';
                    byDate[d] = (byDate[d] || 0) + (parseFloat(r.amount) || 0);
                });
                var dateLabels = Object.keys(byDate).sort();
                var dateValues = dateLabels.map(function(k){ return byDate[k]; });
                if (reportChart) { reportChart.destroy(); }
                reportChart = new Chart(document.getElementById('reportChart').getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: dateLabels,
                        datasets: [{ label: 'Amount (KES)', data: dateValues, borderColor: '#27ae60', backgroundColor: 'rgba(39,174,96,0.1)', fill: true, tension: 0.3, pointRadius: 3 }]
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
