@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-0 fw-bold" style="color:#1a1a2e;">
            <span class="material-symbols-outlined align-middle me-1" style="font-size:22px;">groups</span>
            Headcount by Department
        </h4>
        <small class="text-muted">Active and inactive staff count per department</small>
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
                    <label class="form-label form-label-sm mb-1">Department</label>
                    <select name="department_id" class="form-select form-select-sm">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
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
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Department</th>
                            <th class="text-end">Active</th>
                            <th class="text-end">Inactive</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot id="reportFoot" class="fw-bold" style="background:#e8eaf6;"></tfoot>
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
    const LOAD_URL = "{{ route('ajax.reports.hr.headcount') }}";

    var reportChart = null;

    var reportDT = $('#reportTable').DataTable({
        processing: true,
        columns: [
            { data: null, orderable: false, render: function(d,t,r,m){ return m.row+1; } },
            { data: 'department' },
            { data: 'active', className: 'text-end', render: function(d){
                return '<span class="badge bg-success-subtle text-success">' + d + '</span>';
            }},
            { data: 'inactive', className: 'text-end', render: function(d){
                return '<span class="badge bg-secondary-subtle text-secondary">' + d + '</span>';
            }},
            { data: 'total', className: 'text-end' }
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
                var t = res.totals;
                $('#reportFoot').html('<tr><td></td><td>TOTAL</td><td class="text-end">' + t.active + '</td><td class="text-end">' + t.inactive + '</td><td class="text-end">' + t.total + '</td></tr>');
                $('#resultsInfo').text(res.count + ' department(s) found');
                if (reportChart) { reportChart.destroy(); }
                reportChart = new Chart(document.getElementById('reportChart').getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: res.data.map(function(r){ return r.department; }),
                        datasets: [
                            { label: 'Active', data: res.data.map(function(r){ return r.active; }), backgroundColor: '#27ae60', borderRadius: 3 },
                            { label: 'Inactive', data: res.data.map(function(r){ return r.inactive; }), backgroundColor: '#95a5a6', borderRadius: 3 }
                        ]
                    },
                    options: { responsive: true, indexAxis: 'y', plugins: { legend: { position: 'bottom' } }, scales: { x: { beginAtZero: true, stacked: true }, y: { stacked: true } } }
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
});
</script>
@endsection
