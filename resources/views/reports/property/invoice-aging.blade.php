@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 fw-bold" style="color:#1a1a2e;">
                <span class="material-symbols-outlined align-middle me-1" style="font-size:22px;">receipt_long</span>
                Invoice Aging Report
            </h4>
            <small class="text-muted">Outstanding tenant invoices bucketed by days overdue</small>
        </div>
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary">
            <span class="material-symbols-outlined align-middle" style="font-size:14px;">arrow_back</span> All Reports
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form id="filterForm">
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label form-label-sm mb-1">Property</label>
                        <select name="property_id" class="form-select form-select-sm">
                            <option value="">All Properties</option>
                            @foreach($properties as $p)
                                <option value="{{ $p->id }}">{{ $p->property_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label form-label-sm mb-1">Aging Bucket</label>
                        <select name="bucket" class="form-select form-select-sm">
                            <option value="all">All</option>
                            <option value="current">Current (&le;0 days)</option>
                            <option value="1-30">1 – 30 Days</option>
                            <option value="31-60">31 – 60 Days</option>
                            <option value="61-90">61 – 90 Days</option>
                            <option value="90+">90+ Days</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label form-label-sm mb-1">Invoice Date From</label>
                        <input type="date" name="date_from" class="form-control form-control-sm">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label form-label-sm mb-1">Invoice Date To</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-12 col-md-auto d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm" id="loadBtn">
                            <span class="material-symbols-outlined align-middle" style="font-size:14px;">refresh</span> Load Report
                        </button>
                        <button type="button" class="btn btn-success btn-sm" id="exportBtn" disabled>
                            <span class="material-symbols-outlined align-middle" style="font-size:14px;">download</span> Export Excel
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3" id="chartCard" style="display:none;">
        <div class="card-body">
            <canvas id="reportChart" height="100"></canvas>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span id="resultsInfo" class="text-muted" style="font-size:0.82rem;"></span>
            </div>
            <div class="table-responsive">
                <table id="reportTable" class="table table-sm table-bordered table-hover mb-0" style="font-size:0.82rem;">
                    <thead style="background:#1a1a2e;color:#fff;">
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Tenant</th>
                            <th>Tenant Ref</th>
                            <th>Property</th>
                            <th>Unit</th>
                            <th>Invoice Ref</th>
                            <th>Invoice Date</th>
                            <th>Due Date</th>
                            <th class="text-end">Invoice Amount (KES)</th>
                            <th class="text-end">Outstanding (KES)</th>
                            <th class="text-end">Days Overdue</th>
                            <th>Bucket</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</div>
</div>

<script src="{{ asset('assets/datatables/datatables.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
$(function () {
    const LOAD_URL   = "{{ route('ajax.reports.property.invoice-aging') }}";
    const EXPORT_URL = "{{ route('reports.property.invoice-aging.export') }}";
    var chartInstance = null;

    var reportDT = $('#reportTable').DataTable({
        processing: true,
        columns: [
            { data: null, orderable: false, render: function (d, t, r, m) { return m.row + 1; } },
            { data: 'tenant' },
            { data: 'tenant_ref' },
            { data: 'property' },
            { data: 'unit' },
            { data: 'invoice_ref' },
            { data: 'invoice_date' },
            { data: 'due_date' },
            { data: 'total', className: 'text-end' },
            { data: 'outstanding', className: 'text-end' },
            { data: 'days_overdue', className: 'text-end' },
            { data: 'bucket' },
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
                $('#resultsInfo').text('Showing ' + res.count + ' invoice' + (res.count === 1 ? '' : 's'));
                $('#exportBtn').prop('disabled', false);

                // Chart
                if (res.chart && res.chart.labels && res.chart.labels.length > 0) {
                    $('#chartCard').show();
                    var ctx = document.getElementById('reportChart').getContext('2d');
                    if (chartInstance) { chartInstance.destroy(); }
                    var bgColors = [
                        'rgba(25,135,84,0.75)',
                        'rgba(255,193,7,0.75)',
                        'rgba(253,126,20,0.75)',
                        'rgba(220,53,69,0.75)',
                        'rgba(108,117,125,0.75)',
                    ];
                    chartInstance = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: res.chart.labels,
                            datasets: [{
                                label: 'Outstanding Balance (KES)',
                                data: res.chart.values,
                                backgroundColor: bgColors,
                                borderColor: bgColors.map(function(c){ return c.replace('0.75','1'); }),
                                borderWidth: 1,
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: { legend: { display: false }, title: { display: true, text: 'Outstanding Balance by Aging Bucket (KES)' } },
                            scales: { y: { beginAtZero: true } }
                        }
                    });
                } else {
                    $('#chartCard').hide();
                }
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
