@extends('layouts.main')
@section('content')
<style>
    .contracts-page { --contracts-border:#d9e5ef; --contracts-muted:#64748b; --contracts-navy:#0b2239; }
    .contracts-page .contracts-shell { border:1px solid var(--contracts-border); border-radius:10px; overflow:hidden; }
    .contracts-page .contracts-heading { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px; border-bottom:1px solid var(--contracts-border); padding:24px; }
    .contracts-page .contracts-title { display:flex; align-items:center; gap:14px; }
    .contracts-page .contracts-title-icon { display:grid; place-items:center; width:46px; height:46px; flex-shrink:0; background:#edf6fc; border-radius:10px; color:var(--theme-default,#3498db); }
    .contracts-page .contracts-heading h4 { text-transform:none; margin:0 0 5px; color:var(--contracts-navy); }
    .contracts-page .contracts-heading p { font-size:.875rem; letter-spacing:normal; }
    .contracts-page .contracts-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .contracts-page .contracts-actions .btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; white-space:nowrap; }
    .contracts-page .contracts-actions .material-symbols-outlined { font-size:18px; }
    .contracts-page .contracts-body { padding:24px; }
    .contracts-page .contract-summary { background:#f0f7fc; border:1px solid #d9ebf7; border-left:4px solid var(--theme-default,#3498db); border-radius:8px; padding:16px 20px; display:flex; flex-wrap:wrap; gap:16px; align-items:center; margin-bottom:24px; }
    .contracts-page .contract-summary-title { font-weight:600; color:var(--contracts-navy); margin-bottom:3px; }
    .contracts-page .contract-summary-copy { flex:1; min-width:180px; }
    .contracts-page .contract-summary-copy small { letter-spacing:normal; }
    .contracts-page .contract-summary-total { text-align:right; }
    .contracts-page .contract-summary strong { display:block; font-size:2rem; line-height:1.2; color:var(--contracts-navy); }
    .contracts-page .contract-summary-total span { color:var(--contracts-muted); font-size:.8rem; }
    .contracts-page .contract-filters { background:#fff; border:1px solid var(--contracts-border); border-radius:8px; padding:20px; margin-bottom:24px; }
    .contracts-page .contract-section-heading { display:flex; align-items:center; gap:8px; margin-bottom:16px; color:var(--contracts-navy); }
    .contracts-page .contract-section-heading h5 { font-size:1rem; text-transform:none; margin:0; }
    .contracts-page .contract-section-heading .material-symbols-outlined { font-size:20px; color:var(--theme-default,#3498db); }
    .contracts-page .contract-filters label { display:block; margin-bottom:7px; font-size:.875rem; font-weight:600; letter-spacing:normal; color:var(--contracts-navy); }
    .contracts-page .form-control,.contracts-page .form-select { background-color:#fff; border:1px solid #cdd9e5; border-radius:6px; min-height:42px; font-size:.875rem; }
    .contracts-page .form-control:disabled,.contracts-page .form-select:disabled { background:#f4f6f8; color:#64748b; }
    .contracts-page .contract-filter-footer { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-top:18px; padding-top:16px; border-top:1px solid #edf1f5; }
    .contracts-page .contract-help { color:var(--contracts-muted); font-size:.8rem; letter-spacing:normal; }
    .contracts-page .contract-help summary { cursor:pointer; color:var(--contracts-navy); }
    .contracts-page .contract-help p { max-width:760px; margin:10px 0 0; line-height:1.6; }
    .contracts-page .contract-list-heading { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin:24px 0 12px; }
    .contracts-page .contract-list-heading h5 { margin:0; font-size:1.05rem; text-transform:none; color:var(--contracts-navy); }
    .contracts-page .contract-list-heading p { font-size:.8rem; margin:4px 0 0; color:var(--contracts-muted); letter-spacing:normal; }
    .contracts-page .contract-downloads { padding:12px 0; }
    .contracts-page #contract-active-filters { font-size:.8rem; line-height:1.6; color:#475569; background:#f7f9fb; padding:10px 12px; border:1px solid #e5ecf2; border-radius:6px; overflow-wrap:anywhere; letter-spacing:normal; }
    .contracts-page #contract-active-filters:empty { display:none; }
    .contracts-page #contract-data-review { background:#fffbef; border:1px solid #f0e2b5; border-left:4px solid #d69b25; color:#75541a; font-size:.85rem; padding:12px 16px; letter-spacing:normal; }
    .contracts-page #contracts-export-status { color:var(--contracts-muted); }
    .contracts-page .contract-table-scroll { border:1px solid var(--contracts-border); border-radius:8px; }
    .contracts-page #contracts_table { width:100%!important; min-width:0; table-layout:fixed; margin:0!important; font-size:.8125rem; line-height:1.45; }
    .contracts-page #contracts_table th { white-space:normal; overflow-wrap:normal; word-break:normal; background:#f0f5fa; color:var(--contracts-navy); font-weight:600; padding-top:14px; padding-bottom:14px; }
    .contracts-page #contracts_table td { vertical-align:middle; white-space:normal; overflow-wrap:anywhere; padding:12px 6px; }
    .contracts-page #contracts_table th:last-child,.contracts-page #contracts_table td:last-child { position:sticky; right:0; background:#fff; box-shadow:-3px 0 6px rgba(11,34,57,.05); min-width:0; z-index:1; }
    .contracts-page #contracts_table th:last-child { background:#f0f5fa; z-index:2; }
    .contracts-page #contracts_table th { padding:12px 10px 12px 5px!important; font-size:.75rem; }
    .contracts-page #contracts_table .dt-column-order { right:0; width:10px; }
    .contracts-page #contracts_table th:first-child,.contracts-page #contracts_table td:first-child { padding-left:2px!important; padding-right:2px!important; font-size:.7rem; }
    .contracts-page #contracts_table .badge { white-space:normal; max-width:100%; line-height:1.25; padding:4px 6px; }
    .contracts-page #contracts_table tbody tr:hover td { background:#f5faff; }
    .contracts-page #contracts_table .staff-link,.contracts-page #contracts_table td:last-child a { display:inline-flex; align-items:center; justify-content:center; padding:4px; border-radius:5px; }
    .contracts-page #contracts_table td:last-child a:hover { background:#e5f2fc; }
    .contracts-page .contract-table-hint { font-size:.75rem; color:var(--contracts-muted); letter-spacing:normal; margin:0 0 10px; }
    .contracts-page .dt-container .dt-layout-row { margin:12px 0; }
    .contracts-page #contracts_table_wrapper { display:flow-root; }
    .contracts-page #contracts_table_wrapper .dt-length { float:left; padding:12px 0; }
    .contracts-page #contracts_table_wrapper .dt-search { float:right; padding:12px 0; }
    .contracts-page #contracts_table_wrapper .dt-length,.contracts-page #contracts_table_wrapper .dt-search { display:flex; align-items:center; gap:8px; }
    .contracts-page #contracts_table { clear:both; }
    .contracts-page #contracts_table_wrapper .dt-info { float:left; padding:14px 0; }
    .contracts-page #contracts_table_wrapper .dt-paging { float:right; padding:14px 0; }
    .contracts-page #contracts_table th:nth-child(1) { width:3%!important; }
    .contracts-page #contracts_table th:nth-child(2) { width:10%!important; }
    .contracts-page #contracts_table th:nth-child(3) { width:12%!important; }
    .contracts-page #contracts_table th:nth-child(4) { width:10%!important; }
    .contracts-page #contracts_table th:nth-child(5) { width:8%!important; }
    .contracts-page #contracts_table th:nth-child(6) { width:8%!important; }
    .contracts-page #contracts_table th:nth-child(7) { width:8%!important; }
    .contracts-page #contracts_table th:nth-child(8) { width:8%!important; }
    .contracts-page #contracts_table th:nth-child(9) { width:7%!important; }
    .contracts-page #contracts_table th:nth-child(10) { width:6%!important; }
    .contracts-page #contracts_table th:nth-child(11) { width:8%!important; }
    .contracts-page #contracts_table th:nth-child(12) { width:5%!important; }
    .contracts-page #contracts_table th:nth-child(13) { width:7%!important; }
    @media(max-width:991px) { .contracts-page #contracts_table { min-width:1050px; table-layout:auto; } }
    @media(max-width:767px) {
        .contracts-page .contracts-heading,.contracts-page .contracts-body { padding:16px; }
        .contracts-page .contract-filters { padding:16px; }
        .contracts-page .contracts-title { align-items:flex-start; }
        .contracts-page .contracts-title-icon { width:38px; height:38px; }
        .contracts-page .contracts-heading h4 { font-size:1.2rem; }
        .contracts-page .contract-downloads { width:100%; }
        .contracts-page .contract-downloads .btn { flex:1 1 auto; }
        .contracts-page .contract-filter-footer .contracts-actions { width:100%; }
        .contracts-page .contract-filter-footer .btn { flex:1 1 auto; }
        .contracts-page #contracts_table_wrapper .dt-search,.contracts-page #contracts_table_wrapper .dt-length,.contracts-page #contracts_table_wrapper .dt-info,.contracts-page #contracts_table_wrapper .dt-paging { float:none; }
    }
</style>
<div class="page-body contracts-page" style="padding-top:10px;">
    <div class="container-fluid"><div class="card contracts-shell">
        <div class="card-header card-no-border contracts-heading">
            <div class="contracts-title"><span class="contracts-title-icon material-symbols-outlined" aria-hidden="true">description</span><div><h4>Employee contracts</h4><p class="text-muted mb-0">Manage staff contracts and stay ahead of upcoming renewals.</p></div></div>
            <div class="contracts-actions">

                @permission('update_contracts')
                    <a class="btn btn-secondary btn-sm" href="{{ route('contract.bulk.edit') }}"><span class="material-symbols-outlined align-middle" style="font-size:18px;" aria-hidden="true">table_edit</span> Bulk Edit</a>
                @endpermission
                @permission('create_contracts')
                    <a class="btn btn-primary btn-sm" href="{{ route('contract.create') }}"><span class="material-symbols-outlined align-middle" style="font-size:18px;" aria-hidden="true">add_circle</span> Add Contracts</a>
                @endpermission
            </div>
        </div>
        <div class="card-body contracts-body">
            <div class="contract-summary" aria-label="Upcoming contract renewals">
                <span class="material-symbols-outlined text-primary" aria-hidden="true">event_upcoming</span>
                <div class="contract-summary-copy"><div class="contract-summary-title">Upcoming renewals &middot; next 30 days</div><small id="contracts-default-period" class="d-block text-muted"></small><small class="text-muted">Current approved contracts across all departments. Your full listing remains below.</small></div>
                <div class="contract-summary-total"><strong id="contracts-expiring-count">&mdash;</strong><span>expiring contracts</span></div>
            </div>
            <form id="contract-filters" class="contract-filters">
                <div class="contract-section-heading"><span class="material-symbols-outlined" aria-hidden="true">filter_alt</span><h5>Find contracts</h5></div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="contract-employee-search">Employee name or staff number</label><input type="search" id="contract-employee-search" class="form-control" placeholder="Search employee...">
                    </div>
                    <div class="col-md-4">
                        <label for="contract-department">Department</label><select id="contract-department" class="form-select"><option value="">All departments</option></select>
                    </div>
                    <div class="col-md-4">
                        <label for="contract-scope">Contract scope</label>
                        <select id="contract-scope" class="form-select"><option value="history">All contracts in existing listing</option><option value="current">Current approved contracts</option><option value="review">Records needing date review</option></select>
                    </div>
                    <div class="col-md-4">
                        <label for="contract-report-window">Expiry filter</label>
                        <select id="contract-report-window" class="form-select"><option value="all">All contracts / all dates</option><option value="30">Next 30 days</option><option value="custom">Custom range</option></select>
                    </div>
                    <div class="col-md-4">
                        <label for="contract-from">From expiry date</label><input type="date" id="contract-from" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label for="contract-to">To expiry date</label><input type="date" id="contract-to" class="form-control" required>
                    </div>
                </div>
                <div class="contract-filter-footer">
                    <details class="contract-help"><summary>How these filters work</summary>
                <p class="contract-help" id="contract-scope-help">Dates filter contract end dates, including both selected dates. Current scope covers active/on-leave employees; started renewals replace older contracts. History includes other approval states for those employees.</p>
                    </details>
                    <div class="contracts-actions"><button type="button" id="contracts-reset" class="btn btn-outline-secondary btn-sm" disabled>Reset to all contracts</button><button type="submit" id="contracts-apply" class="btn btn-primary btn-sm" disabled><span class="material-symbols-outlined" aria-hidden="true">search</span> Apply filters</button></div>
                </div>
                <div id="contract-filter-error" class="text-danger mt-2" role="alert"></div>
                <div id="contract-filter-pending" class="small text-muted mt-2" role="status"></div>
            </form>
            <div id="contract-data-review" class="alert alert-warning d-none"></div>
            <div class="contract-list-heading"><div><h5>Contract listing</h5><p>All contract details, with expiry tracking alongside.</p></div><div class="contracts-actions contract-downloads">
                @permission('read_contracts')
                    <button type="button" id="contracts-refresh" class="btn btn-outline-primary btn-sm"><span class="material-symbols-outlined" aria-hidden="true">refresh</span> Refresh report</button>
                    <button type="button" id="contracts-export" class="btn btn-outline-primary btn-sm" disabled><span class="material-symbols-outlined align-middle" style="font-size:18px;" aria-hidden="true">download</span> Export CSV</button>
                    <button type="button" id="contracts-expiry-export" class="btn btn-outline-primary btn-sm" disabled title="Select Next 30 days or Custom range and Current approved contracts">Expiry report CSV</button>
                    <button type="button" id="contracts-print" class="btn btn-outline-primary btn-sm" disabled><span class="material-symbols-outlined" aria-hidden="true">print</span> Print / Save as PDF</button>
                @endpermission
            </div></div>
            <div id="contract-active-filters" class="mb-2" role="status"></div>
            <div id="contract-report-generated" class="small text-muted mb-2" role="status">Loading report...</div>
            <details class="contract-help mb-3"><summary>About downloads and the expiry report</summary><p>Export CSV and Print include the full contract columns. For the separate expiry report, select Next 30 days or Custom range with Current approved contracts, then Apply filters.</p></details>
            <div id="contracts-export-status" class="small text-muted mb-3" role="status" aria-live="polite"></div>
            <div id="contract-alert-filter" class="alert alert-info d-none">Showing the contract linked from your notification. <button type="button" class="btn btn-link btn-sm" id="clear-contract-alert">Clear individual contract filter</button></div>
            <div id="contract-load-error" class="alert alert-danger d-none" role="alert">Unable to load contracts. Click Refresh report to try again. Downloads are unavailable until refreshed.</div>
            <p class="contract-table-hint">Original contract details appear first, followed by the added tracking fields. On smaller screens, scroll across for all columns.</p>
            <div class="table-responsive custom-scrollbar contract-table-scroll">
                <table class="display" id="contracts_table" data-endpoint="{{ url('/ajax/hr/contracts/list') }}">
                    <thead><tr><th>#</th><th>Date/Time</th><th>Staff</th><th>Position</th><th>Date Start</th><th>Date End</th><th>Basic Salary</th><th>Created By</th><th>Department</th><th>Approval Status</th><th>Expiry Status</th><th>Days Remaining</th><th>Action</th></tr></thead><tbody></tbody>
                </table>
            </div>
        </div>
    </div></div>
</div>
@endsection

@section('scripts')
{{-- DataTables bundles jQuery; bind the report only after that bundle has loaded. --}}
<script src="{{ asset('assets/js/hr-contract-export.js') }}?v={{ filemtime(public_path('assets/js/hr-contract-export.js')) }}"></script>
<script src="{{ asset('assets/js/hr-contract-report.js') }}?v={{ filemtime(public_path('assets/js/hr-contract-report.js')) }}"></script>
@endsection
