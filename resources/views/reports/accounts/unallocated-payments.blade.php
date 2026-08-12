@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 fw-bold" style="color:#1a1a2e;">
                <span class="material-symbols-outlined align-middle me-1" style="font-size:22px;">pending_actions</span>
                Unallocated Payments Report
            </h4>
            <small class="text-muted">Payments still pending allocation (Status: Unallocated)</small>
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
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span id="resultsInfo" class="text-muted" style="font-size:0.82rem;"></span>
                    <span class="fw-bold" style="font-size:0.85rem;">
                        Total Unallocated: KES <span id="totalAmount">0.00</span>
                    </span>
                </div>
                <div class="table-responsive">
                    <table id="reportTable" class="table table-sm table-bordered table-hover mb-0" style="font-size:0.82rem;">
                        <thead style="background:#1a1a2e;color:#fff;">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Date</th>
                                <th>Receipt No</th>
                                <th>Tenant</th>
                                <th>Property</th>
                                <th>Unit</th>
                                <th class="text-end">Amount (KES)</th>
                                <th class="text-center">Days Unallocated</th>
                            </tr>
                        </thead>
                        <tbody id="reportBody"></tbody>
                        <tfoot>
                            <tr style="background:#f0f2ff;font-weight:600;">
                                <td colspan="6" class="text-end">Total</td>
                                <td class="text-end" id="totalRow">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
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
    const LOAD_URL   = "{{ route('ajax.reports.accounts.unallocated-payments') }}";
    const EXPORT_URL = "{{ route('reports.accounts.unallocated-payments.export') }}";

    var reportDT = $('#reportTable').DataTable({
        processing: true,
        columns: [
            { data: null, orderable: false, render: function (d, t, r, m) { return m.row + 1; } },
            { data: 'date' },
            { data: 'receipt_no' },
            { data: 'tenant' },
            { data: 'property' },
            { data: 'unit' },
            { data: 'amount', className: 'text-end' },
            {
                data: 'days_unalloc',
                className: 'text-center',
                render: function (d) {
                    if (d > 30) {
                        return '<span style="color:#dc3545;font-weight:600;">' + d + '</span>';
                    } else if (d > 7) {
                        return '<span style="color:#fd7e14;">' + d + '</span>';
                    }
                    return d;
                }
            }
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
                $('#totalAmount').text(res.total);
                $('#totalRow').text(res.total);
                $('#resultsInfo').text(res.count + ' unallocated payment(s)');
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
