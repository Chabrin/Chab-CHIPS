@extends('layouts.main')

@section('styles')
<style>
#txnTable thead th { font-size: 0.78rem; white-space: nowrap; }
#txnTable tbody td { font-size: 0.8rem; vertical-align: middle; }
#txnTable td.text-end { font-variant-numeric: tabular-nums; }
.debit-val  { color: #1a56a0; font-weight: 600; }
.credit-val { color: #157347; font-weight: 600; }
.dash-val   { color: #b0b7c3; }
#txnTable tbody tr, #txnTable tbody tr.odd, #txnTable tbody tr.even {
    background: transparent !important;
}
#txnTable tbody tr:hover { background: rgba(0,0,0,0.025) !important; }
.filter-card { background: #f8fafc; border: 1px solid #e3e8ef; border-radius: 8px; padding: 14px 18px; margin-bottom: 16px; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">

                <!-- Filter bar -->
                <div class="filter-card d-flex flex-wrap align-items-end gap-3">
                    <div>
                        <label class="form-label mb-1" style="font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.4px; color:#6b7a90;">From</label>
                        <input type="date" id="filterStart" class="form-control form-control-sm" style="width:150px;">
                    </div>
                    <div>
                        <label class="form-label mb-1" style="font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.4px; color:#6b7a90;">To</label>
                        <input type="date" id="filterEnd" class="form-control form-control-sm" style="width:150px;">
                    </div>
                    <div>
                        <button id="loadBtn" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                            <span class="btn-label d-flex align-items-center gap-1">
                                <span class="material-symbols-outlined" style="font-size:17px;">filter_list</span>
                                <span>Load</span>
                            </span>
                            <span class="btn-spinner d-none d-flex align-items-center gap-1">
                                <span class="spinner-border spinner-border-sm"></span>
                                <span>Loading...</span>
                            </span>
                        </button>
                    </div>
                    <div>
                        <button id="exportBtn" class="btn btn-success btn-sm d-flex align-items-center gap-1" disabled>
                            <span class="btn-dl-label d-flex align-items-center gap-1">
                                <span class="material-symbols-outlined" style="font-size:17px;">download</span>
                                <span>Download</span>
                            </span>
                            <span class="btn-dl-spinner d-none d-flex align-items-center gap-1">
                                <span class="spinner-border spinner-border-sm"></span>
                                <span>Preparing...</span>
                            </span>
                        </button>
                    </div>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <small class="text-muted" id="rangeLabel"></small>
                        <small class="text-muted" id="rowCountLabel"></small>
                    </div>
                </div>

                <!-- Table card -->
                <div class="card">
                    <div class="card-header card-no-border py-2">
                        <h5 class="mb-0">GL Transactions</h5>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive custom-scrollbar">
                            <table id="txnTable" class="display" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Parent ID</th>
                                        <th>Date</th>
                                        <th>Reference</th>
                                        <th>Account</th>
                                        <th class="text-end">Debit</th>
                                        <th class="text-end">Credit</th>
                                        <th>Description</th>
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
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function () {
    const dataUrl = '{{ route("gl.transactions.data") }}';

    // ── DataTable ──────────────────────────────────────────────────────────────
    const table = $('#txnTable').DataTable({
        data: [],
        columns: [
            {
                data: null, orderable: false, searchable: false,
                render: (d, t, r, m) => m.row + 1
            },
            { data: 'parent_id' },
            {
                data: 'date',
                render: d => `<span style="white-space:nowrap;">${d}</span>`
            },
            {
                data: 'reference',
                render: d => d && d !== '-'
                    ? `<small class="text-muted">${d}</small>`
                    : '<span class="text-muted">—</span>'
            },
            {
                data: 'account',
                render: d => `<span class="fw-semibold">${$('<div>').text(d).html()}</span>`
            },
            {
                data: 'debit', className: 'text-end',
                render: d => d
                    ? `<span class="debit-val">${d}</span>`
                    : `<span class="dash-val">—</span>`
            },
            {
                data: 'credit', className: 'text-end',
                render: d => d
                    ? `<span class="credit-val">${d}</span>`
                    : `<span class="dash-val">—</span>`
            },
            {
                data: 'narration',
                render: d => {
                    if (!d) return '<span class="text-muted">—</span>';
                    const idx = d.indexOf('-');
                    const text = idx > -1 ? d.substring(0, idx).trim() : d;
                    return `<span title="${d.replace(/"/g, '&quot;')}">${text}</span>`;
                }
            }
        ],
        order: [[1, 'asc'], [2, 'asc']],
        pageLength: 50,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
    });

    // ── Helpers ────────────────────────────────────────────────────────────────
    function setLoading(on) {
        $('#loadBtn .btn-label').toggleClass('d-none', on);
        $('#loadBtn .btn-spinner').toggleClass('d-none', !on);
        $('#loadBtn').prop('disabled', on);
    }

    function diffDays(start, end) {
        return Math.round((new Date(end) - new Date(start)) / 86400000);
    }

    function loadData(start, end) {
        // Client-side guard: > 31 days
        if (start && end && diffDays(start, end) > 31) {
            toastr.warning('Date range cannot exceed 31 days. Please narrow your selection.');
            return;
        }

        setLoading(true);
        $('#rangeLabel').text('');
        $('#rowCountLabel').text('');

        const params = {};
        if (start) params.start = start;
        if (end)   params.end   = end;

        $.ajax({
            url: dataUrl,
            data: params,
            success: function (res) {
                setLoading(false);

                if (res.error) {
                    toastr.error(res.error);
                    return;
                }

                table.clear().rows.add(res.data).draw();

                const count = res.data.length;
                $('#rowCountLabel').text(count.toLocaleString() + ' entr' + (count === 1 ? 'y' : 'ies'));

                if (res.range) {
                    const fmt = d => {
                        const [y, m, day] = d.split('-');
                        return `${day}/${m}/${y}`;
                    };
                    $('#rangeLabel').text(fmt(res.range.start) + ' – ' + fmt(res.range.end));

                    // Sync inputs if server auto-detected range
                    if (!start) $('#filterStart').val(res.range.start);
                    if (!end)   $('#filterEnd').val(res.range.end);

                    // Enable export with current range
                    lastExportStart = res.range.start;
                    lastExportEnd   = res.range.end;
                    $('#exportBtn').prop('disabled', count === 0);
                }
            },
            error: function (xhr) {
                setLoading(false);
                const msg = xhr.responseJSON?.error || 'Failed to load transactions.';
                toastr.error(msg);
            }
        });
    }

    // ── Load button ────────────────────────────────────────────────────────────
    $('#loadBtn').on('click', function () {
        const start = $('#filterStart').val();
        const end   = $('#filterEnd').val();

        if (start && end && start > end) {
            toastr.warning('"From" date cannot be after "To" date.');
            return;
        }

        loadData(start || null, end || null);
    });

    // ── Auto-load on page open (server picks most recent month) ───────────────
    loadData(null, null);

    // ── Export button ──────────────────────────────────────────────────────────
    let lastExportStart = null;
    let lastExportEnd   = null;

    function resetExportBtn() {
        $('#exportBtn .btn-dl-label').removeClass('d-none');
        $('#exportBtn .btn-dl-spinner').addClass('d-none');
        $('#exportBtn').prop('disabled', false);
    }

    $('#exportBtn').on('click', function () {
        if (!lastExportStart || !lastExportEnd) return;

        $('#exportBtn .btn-dl-label').addClass('d-none');
        $('#exportBtn .btn-dl-spinner').removeClass('d-none');
        $('#exportBtn').prop('disabled', true);

        const url = '{{ route("gl.transactions.export") }}'
            + '?start=' + encodeURIComponent(lastExportStart)
            + '&end='   + encodeURIComponent(lastExportEnd);

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                if (!response.ok) {
                    return response.json().then(function (data) {
                        throw new Error(data.error || 'Export failed (' + response.status + ')');
                    }).catch(function () {
                        throw new Error('Export failed (' + response.status + ')');
                    });
                }
                return response.blob();
            })
            .then(function (blob) {
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'gl-transactions-' + lastExportStart + '-to-' + lastExportEnd + '.xlsx';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(a.href);
                resetExportBtn();
            })
            .catch(function (err) {
                toastr.error(err.message || 'Export failed.');
                resetExportBtn();
            });
    });
});
</script>
@endsection
