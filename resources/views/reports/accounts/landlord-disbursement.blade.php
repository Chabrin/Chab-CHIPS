@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 fw-bold" style="color:#1a1a2e;">
                <span class="material-symbols-outlined align-middle me-1" style="font-size:22px;">savings</span>
                Landlord Disbursement Schedule
            </h4>
            <small class="text-muted">Landlord account entries — disbursements, PV numbers and payment status</small>
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
                    <div class="col-12 col-md-3">
                        <label class="form-label form-label-sm mb-1">Landlord</label>
                        <select class="form-select form-select-sm" name="client_id" id="client_id">
                            <option value="">All Landlords</option>
                            @foreach($landlords as $landlord)
                                <option value="{{ $landlord->id }}">{{ $landlord->reference_number }} — {{ $landlord->names }}</option>
                            @endforeach
                        </select>
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
                <span id="resultsInfo" class="text-muted d-block mb-2" style="font-size:0.82rem;"></span>
                <div class="table-responsive">
                    <table id="reportTable" class="table table-sm table-bordered table-hover mb-0" style="font-size:0.82rem;">
                        <thead style="background:#1a1a2e;color:#fff;">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Landlord Ref</th>
                                <th>Landlord Name</th>
                                <th>Property</th>
                                <th>Entry Date</th>
                                <th>Entry Type</th>
                                <th class="text-end">Amount (KES)</th>
                                <th>PV Number</th>
                                <th class="text-center">Payment Status</th>
                                <th>Narration</th>
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
    const LOAD_URL   = "{{ route('ajax.reports.accounts.landlord-disbursement') }}";
    const EXPORT_URL = "{{ route('reports.accounts.landlord-disbursement.export') }}";

    var reportDT = $('#reportTable').DataTable({
        processing : true,
        columns    : [
            { data: null, orderable: false, render: function (d, t, r, m) { return m.row + 1; } },
            { data: 'landlord_ref' },
            { data: 'landlord_name' },
            { data: 'property' },
            { data: 'entry_date' },
            { data: 'entry_type' },
            { data: 'amount', className: 'text-end' },
            { data: 'pv_number' },
            {
                data: 'payment_status',
                className: 'text-center',
                render: function (d) {
                    if (!d || d === '-') { return '<span class="badge bg-secondary" style="font-size:0.72rem;">-</span>'; }
                    var lower = d.toLowerCase();
                    var cls = lower === 'paid' ? 'bg-success'
                            : lower === 'pending' ? 'bg-warning text-dark'
                            : 'bg-secondary';
                    return '<span class="badge ' + cls + '" style="font-size:0.72rem;">' + d + '</span>';
                }
            },
            { data: 'narration' }
        ],
        order      : [],
        pageLength : 25,
        lengthMenu : [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
        language   : { emptyTable: 'Apply filters and click Load Report.' }
    });

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
                $('#resultsInfo').text(res.count + ' entry/entries found');
                $('#exportBtn').prop('disabled', false);
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
