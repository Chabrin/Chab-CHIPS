@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-0 fw-bold" style="color:#1a1a2e;">
            <span class="material-symbols-outlined align-middle me-1" style="font-size:22px;">payments</span>
            Payroll Register
        </h4>
        <small class="text-muted">Detailed payroll breakdown per employee for a given period</small>
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
                    <button type="button" class="btn btn-success btn-sm" id="exportBtn" disabled>
                        <span class="material-symbols-outlined align-middle" style="font-size:14px;">download</span> Export Excel
                    </button>
                </div>
            </div>
        </form>

        <div id="resultsSection" class="mt-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span id="resultsInfo" class="text-muted" style="font-size:0.82rem;"></span>
            </div>
            <div class="table-responsive">
                <table id="reportTable" class="table table-sm table-bordered table-hover mb-0" style="font-size:0.82rem;">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Staff No</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th class="text-end">Basic Salary</th>
                            <th class="text-end">Allowances</th>
                            <th class="text-end">Gross</th>
                            <th class="text-end">PAYE</th>
                            <th class="text-end">NSSF</th>
                            <th class="text-end">SHIF</th>
                            <th class="text-end">Housing Levy</th>
                            <th class="text-end">Total Deductions</th>
                            <th class="text-end">Net Pay</th>
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
<script>
$(function () {
    const LOAD_URL   = "{{ route('ajax.reports.hr.payroll-register') }}";
    const EXPORT_URL = "{{ route('reports.hr.payroll-register.export') }}";

    var reportDT = $('#reportTable').DataTable({
        processing: true,
        columns: [
            { data: null, orderable: false, render: function(d,t,r,m){ return m.row+1; } },
            { data: 'staff_number' },
            { data: 'full_names' },
            { data: 'department' },
            { data: 'basic_salary', className: 'text-end' },
            { data: 'allowances', className: 'text-end' },
            { data: 'gross', className: 'text-end' },
            { data: 'paye', className: 'text-end' },
            { data: 'nssf', className: 'text-end' },
            { data: 'shif', className: 'text-end' },
            { data: 'housing_levy', className: 'text-end' },
            { data: 'total_deductions', className: 'text-end' },
            { data: 'net_pay', className: 'text-end' }
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
                $('#reportFoot').html('<tr><td colspan="4">TOTALS</td><td class="text-end">' + t.basic_salary + '</td><td class="text-end">' + t.allowances + '</td><td class="text-end">' + t.gross + '</td><td class="text-end">' + t.paye + '</td><td class="text-end">' + t.nssf + '</td><td class="text-end">' + t.shif + '</td><td class="text-end">' + t.housing_levy + '</td><td class="text-end">' + t.total_deductions + '</td><td class="text-end">' + t.net_pay + '</td></tr>');
                $('#resultsInfo').text(res.count + ' employee(s) found');
                $('#exportBtn').prop('disabled', false);
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
