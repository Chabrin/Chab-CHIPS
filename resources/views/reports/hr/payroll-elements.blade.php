@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-0 fw-bold" style="color:#1a1a2e;">
            <span class="material-symbols-outlined align-middle me-1" style="font-size:22px;">calculate</span>
            Payroll Elements Register
        </h4>
        <small class="text-muted">Earnings and deduction elements configured for payroll</small>
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
                    <label class="form-label form-label-sm mb-1">Position</label>
                    <select name="position_id" class="form-select form-select-sm">
                        <option value="">All Positions</option>
                        @foreach($positions as $pos)
                            <option value="{{ $pos->id }}">{{ $pos->position_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label form-label-sm mb-1">Type</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="Earning">Earning</option>
                        <option value="Deduction">Deduction</option>
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
                            <th>Code</th>
                            <th>Element Name</th>
                            <th>Type</th>
                            <th>Position</th>
                            <th>Amount / Formula</th>
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
    const LOAD_URL   = "{{ route('ajax.reports.hr.payroll-elements') }}";
    const EXPORT_URL = "{{ route('reports.hr.payroll-elements.export') }}";

    var reportDT = $('#reportTable').DataTable({
        processing: true,
        columns: [
            { data: null, orderable: false, render: function(d,t,r,m){ return m.row+1; } },
            { data: 'element_code' },
            { data: 'element_name' },
            { data: 'element_type', render: function(d){
                if (d === 'Earning') return '<span class="badge bg-success-subtle text-success">Earning</span>';
                if (d === 'Deduction') return '<span class="badge bg-danger-subtle text-danger">Deduction</span>';
                return '<span class="badge bg-secondary-subtle text-secondary">' + (d || '-') + '</span>';
            }},
            { data: 'position' },
            { data: 'amount_formula' },
            { data: 'is_active', render: function(d){
                return d === 'Active'
                    ? '<span class="badge bg-success-subtle text-success">Active</span>'
                    : '<span class="badge bg-secondary-subtle text-secondary">Inactive</span>';
            }},
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
                $('#resultsInfo').text(res.count + ' element(s) found');
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
