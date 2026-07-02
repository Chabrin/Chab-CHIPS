@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-0 fw-bold" style="color:#1a1a2e;">
            <span class="material-symbols-outlined align-middle me-1" style="font-size:22px;">calendar_month</span>
            Leave Calendar
        </h4>
        <small class="text-muted">Planned leave periods by employee and month</small>
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
                    <label class="form-label form-label-sm mb-1">Year</label>
                    <input type="number" name="year" class="form-control form-control-sm" value="{{ now()->year }}" min="2000" max="2100">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label form-label-sm mb-1">Month</label>
                    <select name="month" class="form-select form-select-sm">
                        <option value="">All Months</option>
                        @foreach(range(1,12) as $m)
                            <option value="{{ $m }}">{{ \Carbon\Carbon::create()->month($m)->format('F') }}</option>
                        @endforeach
                    </select>
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
                            <th>Leave Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th class="text-end">Days</th>
                            <th class="text-end">Balance</th>
                            <th>Status</th>
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
<script>
$(function () {
    const LOAD_URL = "{{ route('ajax.reports.hr.leave-calendar') }}";

    var reportDT = $('#reportTable').DataTable({
        processing: true,
        columns: [
            { data: null, orderable: false, render: function(d,t,r,m){ return m.row+1; } },
            { data: 'staff_number' },
            { data: 'full_names' },
            { data: 'department' },
            { data: 'leave_type' },
            { data: 'start_date' },
            { data: 'end_date' },
            { data: 'days', className: 'text-end' },
            { data: 'balance', className: 'text-end' },
            { data: 'status', render: function(d){
                var statusMap = { 'Active': 'bg-success-subtle text-success', 'Completed': 'bg-info-subtle text-info', 'Pending': 'bg-warning-subtle text-warning' };
                var badgeClass = statusMap[d] || 'bg-secondary-subtle text-secondary';
                return '<span class="badge ' + badgeClass + '">' + d + '</span>';
            }}
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
                $('#resultsInfo').text(res.count + ' record(s) found');
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
