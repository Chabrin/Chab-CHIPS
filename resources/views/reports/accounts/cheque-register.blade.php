@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 fw-bold" style="color:#1a1a2e;">
                <span class="material-symbols-outlined align-middle me-1" style="font-size:22px;">receipt_long</span>
                Cheque Register
            </h4>
            <small class="text-muted">Cheque book leaves — status, payee, amounts and issue dates</small>
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
                        <label class="form-label form-label-sm mb-1">Status</label>
                        <select class="form-select form-select-sm" name="status" id="status">
                            <option value="">All Statuses</option>
                            <option value="available">Available</option>
                            <option value="locked">Locked</option>
                            <option value="used">Used</option>
                            <option value="cancelled">Cancelled</option>
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
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3" id="chartCard" style="display:none;">
        <div class="card-body">
            <h6 class="fw-bold mb-3" style="color:#1a1a2e;">Cheque Status Breakdown</h6>
            <div style="max-width:320px;margin:0 auto;">
                <canvas id="reportChart"></canvas>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-body">
            <span id="resultsInfo" class="text-muted d-block mb-2" style="font-size:0.82rem;"></span>
            <div class="table-responsive">
                <table id="reportTable" class="table table-sm table-bordered table-hover mb-0" style="font-size:0.82rem;">
                    <thead style="background:#1a1a2e;color:#fff;">
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Cheque Book Ref</th>
                            <th>Cheque Number</th>
                            <th>Payee / Description</th>
                            <th class="text-end">Amount (KES)</th>
                            <th>Issue Date</th>
                            <th class="text-center">Status</th>
                            <th>Issued By</th>
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
    const LOAD_URL   = "{{ route('ajax.reports.accounts.cheque-register') }}";
    const EXPORT_URL = "{{ route('reports.accounts.cheque-register.export') }}";

    var STATUS_COLORS = {
        available : 'bg-success text-white',
        locked    : 'bg-warning text-dark',
        used      : 'bg-primary text-white',
        cancelled : 'bg-danger text-white'
    };

    var reportDT = $('#reportTable').DataTable({
        processing : true,
        columns    : [
            { data: null, orderable: false, render: function (d, t, r, m) { return m.row + 1; } },
            { data: 'book_ref' },
            { data: 'cheque_no' },
            { data: 'payee' },
            { data: 'amount', className: 'text-end' },
            { data: 'issue_date' },
            {
                data: 'status',
                className: 'text-center',
                render: function (d, t, r) {
                    var cls = STATUS_COLORS[r.status_raw] || 'bg-secondary text-white';
                    return '<span class="badge ' + cls + '" style="font-size:0.72rem;">' + d + '</span>';
                }
            },
            { data: 'issued_by' }
        ],
        order      : [],
        pageLength : 25,
        lengthMenu : [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
        language   : { emptyTable: 'Apply filters and click Load Report.' }
    });

    var chartInstance = null;

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
                $('#resultsInfo').text(res.count + ' cheque leaf/leaves found');
                $('#exportBtn').prop('disabled', false);

                // Draw donut chart
                if (res.chart) {
                    $('#chartCard').show();
                    if (chartInstance) { chartInstance.destroy(); }
                    chartInstance = new Chart(document.getElementById('reportChart'), {
                        type: 'doughnut',
                        data: {
                            labels   : res.chart.labels,
                            datasets : [{
                                data            : res.chart.values,
                                backgroundColor : ['#198754','#ffc107','#0d6efd','#dc3545'],
                                borderWidth     : 1
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins   : { legend: { position: 'bottom' } }
                        }
                    });
                }
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
