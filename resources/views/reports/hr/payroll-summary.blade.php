@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-0 fw-bold" style="color:#1a1a2e;">
            <span class="material-symbols-outlined align-middle me-1" style="font-size:22px;">summarize</span>
            Payroll Summary
        </h4>
        <small class="text-muted">Departmental payroll totals for a given period</small>
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
                    <label class="form-label form-label-sm mb-1">Period <span class="text-danger">*</span></label>
                    <input type="month" name="period" class="form-control form-control-sm" value="{{ now()->format('Y-m') }}" required>
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
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Department</th>
                            <th class="text-end">Staff Count</th>
                            <th class="text-end">Total Basic</th>
                            <th class="text-end">Total Gross</th>
                            <th class="text-end">Total PAYE</th>
                            <th class="text-end">Total NSSF</th>
                            <th class="text-end">Total SHIF</th>
                            <th class="text-end">Housing Levy</th>
                            <th class="text-end">Total Deductions</th>
                            <th class="text-end">Total Net Pay</th>
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
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script src="{{ asset('assets/datatables/datatables.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
$(function () {
    const LOAD_URL   = "{{ route('ajax.reports.hr.payroll-summary') }}";
    const EXPORT_URL = "{{ route('reports.hr.payroll-summary.export') }}";

    var reportChart = null;

    var reportDT = $('#reportTable').DataTable({
        processing: true,
        columns: [
            { data: null, orderable: false, render: function(d,t,r,m){ return m.row+1; } },
            { data: 'department' },
            { data: 'staff_count', className: 'text-end' },
            { data: 'total_basic', className: 'text-end' },
            { data: 'total_gross', className: 'text-end' },
            { data: 'total_paye', className: 'text-end' },
            { data: 'total_nssf', className: 'text-end' },
            { data: 'total_shif', className: 'text-end' },
            { data: 'total_housing', className: 'text-end' },
            { data: 'total_deductions', className: 'text-end' },
            { data: 'total_net_pay', className: 'text-end' }
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
                $('#reportFoot').html('<tr><td></td><td>GRAND TOTAL</td><td class="text-end">' + t.staff_count + '</td><td class="text-end">' + t.total_basic + '</td><td class="text-end">' + t.total_gross + '</td><td class="text-end">' + t.total_paye + '</td><td class="text-end">' + t.total_nssf + '</td><td class="text-end">' + t.total_shif + '</td><td class="text-end">' + t.total_housing + '</td><td class="text-end">' + t.total_deductions + '</td><td class="text-end">' + t.total_net_pay + '</td></tr>');
                $('#resultsInfo').text(res.count + ' department(s) found');
                $('#exportBtn').prop('disabled', false);
                if (reportChart) { reportChart.destroy(); }
                reportChart = new Chart(document.getElementById('reportChart').getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: res.data.map(function(r){ return r.department; }),
                        datasets: [
                            { label: 'Gross', data: res.data.map(function(r){ return parseFloat(r.total_gross) || 0; }), backgroundColor: '#3498db', borderRadius: 3 },
                            { label: 'Net Pay', data: res.data.map(function(r){ return parseFloat(r.total_net_pay) || 0; }), backgroundColor: '#27ae60', borderRadius: 3 }
                        ]
                    },
                    options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
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
