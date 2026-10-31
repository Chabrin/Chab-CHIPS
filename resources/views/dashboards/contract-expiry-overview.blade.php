<style>
    .contract-expiry-overview { margin-top:24px; }
    .contract-expiry-overview .ce-heading { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; }
    .contract-expiry-overview h5 { color:var(--body-font-color, #0b2239); margin:0 0 6px; text-transform:none; }
    .contract-expiry-overview .ce-total { display:flex; align-items:center; gap:16px; padding:16px; margin-bottom:20px; background:#f0f7fc; border:1px solid #d9ebf7; border-radius:8px; color:var(--body-font-color, #0b2239); text-decoration:none; }
    .contract-expiry-overview .ce-total strong { font-size:2rem; line-height:1.2; }
    .contract-expiry-overview .ce-total .material-symbols-outlined { color:var(--theme-default, #3498db); }
    .contract-expiry-overview .ce-total:focus-visible { outline:2px solid var(--theme-default, #3498db); outline-offset:3px; }
    .contract-expiry-overview th { white-space:nowrap; }
</style>
<section id="contract-expiry-overview" class="card contract-expiry-overview" aria-labelledby="ce-title"
    data-endpoint="{{ route('dashboard.contract-expiry-overview') }}" data-report-url="{{ route('contract.index', ['window' => '30']) }}">
    <div class="card-header card-no-border ce-heading">
        <div><h5 id="ce-title">Contracts expiring within 30 days</h5><p class="text-muted mb-0">Plan upcoming contract renewals.</p></div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" data-refresh>Refresh</button>
            <a href="{{ route('contract.index', ['window' => '30']) }}" class="btn btn-primary btn-sm">View all</a>
        </div>
    </div>
    <div class="card-body pt-0">
        <a class="ce-total" href="{{ route('contract.index', ['window' => '30']) }}">
            <span class="material-symbols-outlined" aria-hidden="true">event_upcoming</span>
            <strong data-count="due_30">&mdash;</strong><span>contracts expiring within 30 days</span>
        </a>
        <div data-status class="small text-muted mb-3" role="status" aria-live="polite">Loading contract expiry figures...</div>
        <h6>Next five contracts</h6>
        <div class="table-responsive">
            <table class="table table-hover"><thead><tr><th>Employee / staff number</th><th>Department</th><th>Contract end date</th><th>Days remaining</th><th>Report</th></tr></thead>
                <tbody data-contract-rows></tbody>
            </table>
        </div>
        <p class="small text-muted mb-0 mt-3">Current approved contracts for active and on-leave employees. Includes today through the displayed end date. Started renewals replace older contracts.</p>
    </div>
</section>
<script src="{{ asset('assets/js/contract-expiry-overview.js') }}?v={{ filemtime(public_path('assets/js/contract-expiry-overview.js')) }}"></script>
