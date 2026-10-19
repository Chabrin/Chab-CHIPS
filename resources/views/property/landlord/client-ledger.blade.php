@extends('layouts.main')

@section('styles')
<style>
#ledgerTable thead th { font-size: 0.78rem; white-space: nowrap; }
#ledgerTable tbody td { font-size: 0.8rem; vertical-align: middle; }
#ledgerTable td.text-end { font-variant-numeric: tabular-nums; }
#ledgerTable tbody tr, #ledgerTable tbody tr.odd, #ledgerTable tbody tr.even { background: transparent !important; }
#ledgerTable tbody tr:hover { background: rgba(0,0,0,0.025) !important; }
.filter-card { background: #f8fafc; border: 1px solid #e3e8ef; border-radius: 8px; padding: 14px 18px; margin-bottom: 16px; }
.filter-card label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; color: #6b7a90; margin-bottom: 4px; }
/* Centre the modal in the content area (right of the sidebar, below the header), like the approval modals */
@media (min-width: 992px) {
    #ledgerModal { padding-left: var(--sidebar-width, 170px) !important; padding-top: var(--header-height, 54px) !important; }
}
#ledgerTable tbody tr.has-account { cursor: pointer; }
#entriesTable thead th { font-size: 0.78rem; white-space: nowrap; }
#entriesTable tbody td { font-size: 0.8rem; vertical-align: middle; }
#entriesTable td.text-end { font-variant-numeric: tabular-nums; }
.bal-pos { color: #157347; font-weight: 600; }
.bal-neg { color: #b02a37; font-weight: 600; }
.dash-val { color: #b0b7c3; }
#ledgerTable_wrapper .dataTables_filter, #ledgerTable_wrapper .dt-search { display: none; }
.stat-chip { font-size: 0.78rem; color: #6b7a90; }
.stat-chip strong { color: #1f2d3d; }
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
                        <label class="d-block" for="filterSearch">Search account</label>
                        <input type="search" id="filterSearch" class="form-control form-control-sm" style="width:260px;" placeholder="Account no. (LAN-…), name or phone" autocomplete="off">
                    </div>
                    <div>
                        <label class="d-block" for="filterAccount">Account status</label>
                        <select id="filterAccount" class="form-select form-select-sm" style="width:170px;">
                            <option value="all">All landlords</option>
                            <option value="open">Account open</option>
                            <option value="none">No account yet</option>
                        </select>
                    </div>
                    <div>
                        <label class="d-block" for="filterFee">Registration fee</label>
                        <select id="filterFee" class="form-select form-select-sm" style="width:170px;">
                            <option value="all">All</option>
                            <option value="unpaid">Unpaid</option>
                        </select>
                    </div>
                    <div>
                        <label class="d-block" for="filterBalance">Balance</label>
                        <select id="filterBalance" class="form-select form-select-sm" style="width:210px;">
                            <option value="all">All</option>
                            <option value="owed_to">We owe the landlord</option>
                            <option value="owed_by">Landlord owes us</option>
                        </select>
                    </div>
                    <div>
                        <button id="exportBtn" class="btn btn-success btn-sm d-flex align-items-center gap-1" disabled>
                            <span class="material-symbols-outlined" style="font-size:17px;">download</span>
                            <span>Excel</span>
                        </button>
                    </div>
                    <div class="ms-auto d-flex align-items-center gap-3">
                        <span class="stat-chip" id="statShown"></span>
                        <span class="stat-chip" id="statUnpaid"></span>
                    </div>
                </div>

                <!-- Table card -->
                <div class="card">
                    <div class="card-header card-no-border py-2">
                        <h5 class="mb-0">Client Ledgers</h5>
                        <small class="text-muted">One row per landlord. Click a row to see every entry on the account.</small>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive custom-scrollbar">
                            <table id="ledgerTable" class="display" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Account no.</th>
                                        <th>Client</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th>Opened</th>
                                        <th>Last activity</th>
                                        <th class="text-end">Entries</th>
                                        <th class="text-end">Balance</th>
                                        <th class="text-end">Unpaid reg. fee</th>
                                        <th></th>
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

<!-- Ledger entries modal -->
<div class="modal fade" id="ledgerModal" tabindex="-1" aria-labelledby="ledgerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2 px-3">
                <div class="flex-grow-1">
                    <h6 class="mb-0" id="ledgerModalLabel">Client Ledger</h6>
                    <small class="text-muted" id="ledgerModalSub"></small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="stat-chip">Balance: <strong id="ledgerModalBalance">-</strong></span>
                    <a id="ledgerModalStatement" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1" href="#">
                        <span class="material-symbols-outlined" style="font-size:17px;">description</span><span>Statement</span>
                    </a>
                    <a id="ledgerModalExcel" class="btn btn-success btn-sm d-flex align-items-center gap-1 disabled" href="#">
                        <span class="material-symbols-outlined" style="font-size:17px;">download</span><span>Excel</span>
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body pt-2">
                <table id="entriesTable" class="display" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Seq</th>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Narration</th>
                            <th class="text-end">Dr</th>
                            <th class="text-end">Cr</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function () {
    const dataUrl = '{{ route("ajax.client.ledger.data") }}';
    const exportUrl = '{{ route("client.ledger.export") }}';
    const statementUrl = '{{ route("landlord.account.statement") }}';
    const entriesUrl = '{{ route("ajax.client.ledger.entries") }}';
    const entriesExportUrl = '{{ route("client.ledger.entries.export") }}';

    const esc = s => $('<div>').text(s == null ? '' : s).html();
    const money = n => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const day = d => { const [y, m, dd] = d.split('-'); return dd + '/' + m + '/' + y; };
    const dash = '<span class="dash-val">—</span>';

    const table = $('#ledgerTable').DataTable({
        data: [],
        columns: [
            { data: null, orderable: false, searchable: false, render: (d, t, r, m) => m.row + 1 },
            { data: 'reference_number', render: (d, t) => t === 'display' ? `<span class="fw-semibold" style="white-space:nowrap;">${esc(d)}</span>` : d },
            { data: 'names', render: (d, t) => t === 'display' ? esc(d) : d },
            { data: 'mobile_number', render: d => d ? esc(d) : dash },
            {
                data: 'has_account', searchable: false,
                render: (d, t) => t === 'display'
                    ? (d ? '<span class="badge bg-success">Open</span>' : '<span class="badge bg-secondary">Not opened</span>')
                    : (d ? 'Open' : 'Not opened')
            },
            { data: 'opened', searchable: false, render: (d, t) => d ? (t === 'display' ? day(d) : d) : (t === 'display' ? dash : '') },
            { data: 'last_activity', searchable: false, render: (d, t) => d ? (t === 'display' ? day(d) : d) : (t === 'display' ? dash : '') },
            { data: 'entries', searchable: false, className: 'text-end', render: (d, t) => t === 'display' && !d ? dash : d },
            {
                data: 'balance', searchable: false, className: 'text-end',
                render: (d, t, r) => {
                    if (t !== 'display') return r.has_account ? d : null;
                    if (!r.has_account) return dash;
                    return `<span class="${d > 0 ? 'bal-pos' : (d < 0 ? 'bal-neg' : '')}">${money(d)}</span>`;
                }
            },
            {
                data: 'unpaid_fee', searchable: false, className: 'text-end',
                render: (d, t) => t === 'display' ? (d > 0 ? `<span class="bal-neg">${money(d)}</span>` : dash) : d
            },
            {
                data: null, orderable: false, searchable: false, className: 'text-end',
                render: (d, t, r) => r.has_account
                    ? `<a class="btn btn-outline-primary btn-sm py-0 px-2" href="${statementUrl}?landlord=${encodeURIComponent(r.encrypted_id)}">Statement</a>`
                    : ''
            }
        ],
        order: [],
        pageLength: 50,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
        language: { emptyTable: 'No landlords match these filters.' },
        createdRow: (row, data) => { if (data.has_account) $(row).addClass('has-account').attr('title', 'Click to see every entry'); }
    });

    function filters() {
        return { account: $('#filterAccount').val(), fee: $('#filterFee').val(), balance: $('#filterBalance').val() };
    }

    function load() {
        $('#exportBtn').prop('disabled', true);
        table.clear().draw();
        $('#statShown').text('Loading…');
        $('#statUnpaid').text('');

        $.ajax({
            url: dataUrl,
            data: filters(),
            success: function (res) {
                if (!res.success) {
                    toastr.error(res.message || 'Failed to load client ledgers');
                    $('#statShown').text('');
                    return;
                }
                table.clear().rows.add(res.data).draw();

                const open = res.data.filter(r => r.has_account).length;
                const unpaid = res.data.reduce((sum, r) => sum + r.unpaid_fee, 0);
                $('#statShown').html(`<strong>${res.data.length.toLocaleString()}</strong> landlords · <strong>${open.toLocaleString()}</strong> with an account`);
                $('#statUnpaid').html(unpaid > 0 ? `Unpaid registration fees: <strong>${money(unpaid)}</strong>` : '');
                $('#exportBtn').prop('disabled', res.data.length === 0);
            },
            error: function () {
                $('#statShown').text('');
                toastr.error('Error loading client ledgers');
            }
        });
    }

    $('#filterAccount, #filterFee, #filterBalance').on('change', load);

    $('#filterSearch').on('input', function () {
        table.search(this.value).draw();
    });

    $('#exportBtn').on('click', function () {
        window.location.href = exportUrl + '?' + $.param(filters());
    });

    // ── Row click: every ledger entry for that landlord ───────────────────────
    const entriesTable = $('#entriesTable').DataTable({
        data: [],
        columns: [
            { data: 'seq', visible: false },
            { data: 'date', orderData: 0, render: (d, t, r) => esc(r.date_formatted) },
            { data: 'reference', render: d => d && d !== '-' ? `<small class="text-muted">${esc(d)}</small>` : dash },
            { data: 'narration', render: d => d ? esc(d) : dash },
            { data: 'debit', className: 'text-end', render: (d, t) => t === 'display' ? (d > 0 ? `<span style="color:#1a56a0;font-weight:600;">${money(d)}</span>` : dash) : d },
            { data: 'credit', className: 'text-end', render: (d, t) => t === 'display' ? (d > 0 ? `<span style="color:#157347;font-weight:600;">${money(d)}</span>` : dash) : d },
            { data: 'balance', className: 'text-end', render: (d, t) => t === 'display' ? money(d) : d }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
        language: { emptyTable: 'No entries on this account.' }
    });

    let openRequest = null;

    function openLedger(r) {
        if (openRequest) openRequest.abort();

        const key = encodeURIComponent(r.encrypted_id);
        $('#ledgerModalLabel').text(r.reference_number + ' · ' + r.names);
        $('#ledgerModalSub').text(r.mobile_number || '');
        $('#ledgerModalBalance').text('…');
        $('#ledgerModalStatement').attr('href', statementUrl + '?landlord=' + key);
        $('#ledgerModalExcel').addClass('disabled').attr('href', entriesExportUrl + '?client=' + key);
        entriesTable.clear().draw();
        $('#ledgerModal').modal('show');

        openRequest = $.ajax({
            url: entriesUrl,
            data: { client: r.encrypted_id },
            success: function (res) {
                if (!res.success) {
                    toastr.error(res.message || 'Failed to load ledger entries');
                    return;
                }
                entriesTable.clear().rows.add(res.data).draw();
                $('#ledgerModalBalance').html(`<span class="${res.balance > 0 ? 'bal-pos' : (res.balance < 0 ? 'bal-neg' : '')}">${money(res.balance)}</span>`);
                $('#ledgerModalExcel').toggleClass('disabled', res.data.length === 0);
            },
            error: function (xhr, status) {
                if (status === 'abort') return;
                $('#ledgerModalBalance').text('-');
                toastr.error('Error loading ledger entries');
            }
        });
    }

    $('#ledgerTable tbody').on('click', 'tr.has-account', function (e) {
        if ($(e.target).closest('a, button').length) return;
        openLedger(table.row(this).data());
    });

    $('#ledgerModal').on('shown.bs.modal', function () {
        entriesTable.columns.adjust();
    });

    load();
});
</script>
@endsection
