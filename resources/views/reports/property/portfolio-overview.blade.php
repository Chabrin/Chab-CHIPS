@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 fw-bold" style="color:#1a1a2e;">
                <span class="material-symbols-outlined align-middle me-1" style="font-size:22px;">apartment</span>
                Property Portfolio Overview
            </h4>
            <small class="text-muted">Summary of all properties: unit counts, occupancy rates, and rent values</small>
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
                    <div class="col-12 col-md-3">
                        <label class="form-label form-label-sm mb-1">Zone <span class="text-muted">(optional)</span></label>
                        <select name="zone_id" id="zone_id" class="form-select form-select-sm">
                            <option value="">All Zones</option>
                            @foreach($zones as $z)
                                <option value="{{ $z->id }}">{{ $z->zone_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label form-label-sm mb-1">Date From</label>
                        <input type="date" name="date_from" class="form-control form-control-sm">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label form-label-sm mb-1">Date To</label>
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

            <div id="resultsSection" class="mt-3">
                <div class="card border-0 shadow-sm mb-3" id="chartCard" style="display:none;"><div class="card-body"><canvas id="reportChart" height="120"></canvas></div></div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span id="resultsInfo" class="text-muted" style="font-size:0.82rem;"></span>
                </div>
                <div class="table-responsive">
                    <table id="reportTable" class="table table-sm table-bordered table-hover mb-0" style="font-size:0.82rem;">
                        <thead style="background:#1a1a2e;color:#fff;">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Ref</th>
                                <th>Property Name</th>
                                <th class="text-center">Total Units</th>
                                <th class="text-center">Occupied</th>
                                <th class="text-center">Vacant</th>
                                <th class="text-center">Occupancy %</th>
                                <th class="text-end">Total Rent Value (KES)</th>
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

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script src="{{ asset('assets/datatables/datatables.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
$(function () {
    const LOAD_URL   = "{{ route('ajax.reports.property.portfolio') }}";
    const EXPORT_URL = "{{ route('reports.property.portfolio.export') }}";

    var reportChart = null;

    var reportDT = $('#reportTable').DataTable({
        processing: true,
        columns: [
            { data: null, orderable: false, render: function (d, t, r, m) { return m.row + 1; } },
            { data: 'reference' },
            { data: 'property' },
            { data: 'total_units', className: 'text-center' },
            { data: 'occupied', className: 'text-center' },
            { data: 'vacant', className: 'text-center' },
            { data: 'occupancy_pct', className: 'text-center' },
            { data: 'total_rent', className: 'text-end' },
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
                $('#resultsInfo').text('Showing ' + res.count + ' propert' + (res.count === 1 ? 'y' : 'ies'));
                $('#exportBtn').prop('disabled', false);
                if (reportChart) { reportChart.destroy(); }
                reportChart = new Chart(document.getElementById('reportChart').getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: res.data.map(function(r){ return r.property; }),
                        datasets: [{ label: 'Occupancy %', data: res.data.map(function(r){ return parseFloat(r.occupancy_pct) || 0; }), backgroundColor: '#27ae60', borderRadius: 3 }]
                    },
                    options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, max: 100 } } }
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
