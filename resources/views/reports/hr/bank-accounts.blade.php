@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-0 fw-bold" style="color:#1a1a2e;">
            <span class="material-symbols-outlined align-middle me-1" style="font-size:22px;">account_balance</span>
            Staff Bank Account Register
        </h4>
        <small class="text-muted">Active staff bank account details for payroll disbursement</small>
    </div>
    <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary">
        <span class="material-symbols-outlined align-middle" style="font-size:14px;">arrow_back</span> All Reports
    </a>
</div>

<div class="alert alert-warning border-warning d-flex align-items-center gap-2 py-2" role="alert">
    <span class="material-symbols-outlined" style="font-size:18px;">lock</span>
    <span style="font-size:0.85rem;"><strong>Sensitive Information:</strong> This report contains confidential payroll bank account details. Access is restricted to authorised personnel only.</span>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form id="filterForm">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label form-label-sm mb-1">Department</label>
                    <select name="department_id" class="form-select form-select-sm">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                        @endforeach
                    </select>
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
                            <th>Bank</th>
                            <th>Account Name</th>
                            <th>Account Number</th>
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
<script src="{{ asset('assets/datatables/datatables.min.js') }}"></script>
<script>
$(function () {
    const LOAD_URL   = "{{ route('ajax.reports.hr.bank-accounts') }}";
    const EXPORT_URL = "{{ route('reports.hr.bank-accounts.export') }}";

    var reportDT = $('#reportTable').DataTable({
        processing: true,
        columns: [
            { data: null, orderable: false, render: function(d,t,r,m){ return m.row+1; } },
            { data: 'staff_number' },
            { data: 'full_names' },
            { data: 'department' },
            { data: 'bank_name' },
            { data: 'account_name' },
            { data: 'account_number' },
        ],
        order: [],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
        language: { emptyTable: 'Select a department and click Load Report.' }
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
