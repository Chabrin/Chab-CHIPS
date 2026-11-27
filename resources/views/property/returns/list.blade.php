@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/returns-list.css') }}?v={{ filemtime(public_path('assets/css/returns-list.css')) }}">
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row"><div class="col-sm-12"><div class="card returns-list-card">
            <div class="card-header card-no-border"><h5 class="mb-0">Returns List (Unpaid)</h5></div>
            <div class="card-body">
                @if(request()->filled('generation'))
                <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>Showing returns from this generation.</span>
                    <a href="{{ route('returns.batches.index', ['year' => $currentYear, 'month' => $currentMonth]) }}">View all returns for this month</a>
                </div>
                @endif
                <div class="returns-list-filters mb-4">
                    <div class="returns-filter"><label for="year_select" class="form-label mb-1">Year</label><select class="form-control form-control-sm" id="year_select">
                        @foreach($years as $yr)
                        <option value="{{ $yr }}" {{ $yr == $currentYear ? 'selected' : '' }}>{{ $yr }}</option>
                        @endforeach
                    </select></div>
                    <div class="returns-filter"><label for="month_select" class="form-label mb-1">Month</label><select class="form-control form-control-sm" id="month_select">
                        @foreach(range(1, 12) as $month)
                        <option value="{{ $month }}" {{ $month == $currentMonth ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $month, 1)) }}</option>
                        @endforeach
                    </select></div>
                    <div class="returns-filter"><label for="property_select" class="form-label mb-1">Property</label><select class="form-control form-control-sm" id="property_select"><option value="">All properties</option></select></div>
                    <div class="returns-filter"><label for="status_filter" class="form-label mb-1">Status</label><select class="form-control form-control-sm" id="status_filter">
                        <option value="">All statuses</option><option value="draft">Draft</option><option value="awaiting">Awaiting approval</option><option value="returned">Returned for changes</option><option value="rejected">Rejected</option><option value="approved">Approved</option><option value="processing">Finalising approval</option><option value="review">Needs review</option>
                    </select></div>
                    <div class="returns-filter"><label for="level_filter" class="form-label mb-1">Approval level</label><select class="form-control form-control-sm" id="level_filter"><option value="">All levels</option></select></div>
                    <div class="returns-filter-action"><button type="button" class="btn btn-primary btn-sm" id="search_btn"><span class="spinner-border spinner-border-sm" id="search_spinner" hidden aria-hidden="true"></span><span id="search_label">Search</span></button></div>
                </div>
                <div id="returns_error" class="alert alert-danger" role="alert" hidden></div>
                <div id="workflow_notice" class="alert alert-warning" role="status" hidden>Approval workflow is not configured. Historical levels are shown where available.</div>
                <div class="nav nav-tabs mb-3" role="tablist" aria-label="Return grouping">
                    <button type="button" class="nav-link active" id="individual_returns_tab" role="tab" aria-selected="true" aria-controls="returns_panel" data-return-group="individual">Individual Returns <span class="badge badge-light-secondary ms-1" id="individual_count">0</span></button>
                    <button type="button" class="nav-link" id="merged_returns_tab" role="tab" aria-selected="false" aria-controls="returns_panel" tabindex="-1" data-return-group="merged">Merged Returns <span class="badge badge-light-secondary ms-1" id="merged_count">0</span></button>
                </div>
                <section id="returns_panel" role="tabpanel" aria-labelledby="individual_returns_tab" tabindex="0">
                    <p class="text-muted mb-3" id="returns_view_help">Individual returns awaiting approval or payment.</p>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 returns-list-summary">
                        <div role="status" aria-live="polite"><span id="result_count">0</span> returns shown <span class="text-muted mx-2" aria-hidden="true">&middot;</span> Net total: <strong id="returns_net_total">0.00</strong><small class="text-muted d-block" id="component_total_note" hidden>Linked property components are excluded from this total.</small></div>
                        <button type="button" class="btn btn-primary btn-sm" id="forward_btn" hidden>Forward drafts to zone manager (<span id="draft_count">0</span>)</button>
                    </div>
                    <div class="table-responsive custom-scrollbar">
                        <table class="display" id="returns-table" style="width:100%;">
                            <thead>
                                <tr><th><span class="visually-hidden">Expand properties</span></th><th>#</th><th>Client Name</th><th>Property</th><th>Type</th><th class="text-end">Net</th><th>Units</th><th>Approval progress</th><th>Action</th></tr>
                                <tr class="column-filters"><th></th><th></th><th><input type="text" class="form-control form-control-sm col-filter" placeholder="Filter..." aria-label="Filter clients or return codes"></th><th><input type="text" class="form-control form-control-sm col-filter" placeholder="Filter..." aria-label="Filter property names"></th><th><input type="text" class="form-control form-control-sm col-filter" placeholder="Filter..." aria-label="Filter return types"></th><th></th><th></th><th></th><th></th></tr>
                            </thead><tbody></tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div></div></div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/js/returns-list.js') }}?v={{ filemtime(public_path('assets/js/returns-list.js')) }}"></script>
<script>
    $(function () {
        ReturnsList.init($, {
            dataUrl: @json(route('ajax.returns.batches.data')), forwardUrl: @json(route('ajax.returns.forward.many')),
            canForward: @json(app(\App\Services\ReturnAccessService::class)->canForward((int) auth()->id())),
            generation: @json(request()->query('generation')), year: @json((string) $currentYear), month: @json((string) $currentMonth)
        });
    });
</script>
@endsection
