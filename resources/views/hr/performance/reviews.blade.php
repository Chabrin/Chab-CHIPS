@extends('layouts.main')

@section('styles')
<style>
    /* Performance Review lists. Scoped to .pr-* so nothing else on the site changes. */
    #prTable thead th { background: #f6f8fb; color: #5b6b7c; font-size: .72rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; white-space: nowrap; }
    #prTable tbody td { vertical-align: middle; }
    .pr-hint { font-size: .8125rem; color: #6b7a8c; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header card-no-border d-flex align-items-start flex-wrap gap-2 py-3">
                <div class="me-auto">
                    <h5 class="mb-0">{{ $title }}</h5>
                    <div class="pr-hint mt-1">{{ $hint }}</div>
                </div>
                @if ($mode === 'all')
                <div class="d-flex gap-2 flex-wrap">
                    <select class="form-select form-select-sm" id="prCycle" aria-label="Cycle" style="min-width:12rem;"><option value="">All cycles</option></select>
                    <select class="form-select form-select-sm" id="prStatus" aria-label="Status" style="min-width:12rem;">
                        <option value="">Any status</option>
                        <option value="awaiting_self">Waiting for self-rating</option>
                        <option value="awaiting_supervisor">Waiting for supervisor</option>
                        <option value="awaiting_hr">Waiting for HR</option>
                        <option value="finalised">Finalised</option>
                    </select>
                </div>
                @endif
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle" id="prTable">
                        <thead>
                            <tr>
                                @if ($mode !== 'my')<th>Staff member</th>@endif
                                <th>Cycle</th>
                                @if ($mode === 'all')<th>Supervisor</th>@endif
                                <th>Status</th>
                                <th>Final score</th>
                                <th>Deadline</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody><tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/js/performance-review.js') }}?v={{ filemtime(public_path('assets/js/performance-review.js')) }}"></script>
<script>
    $(function() {
        const PR = window.PerformanceReview;
        const mode = @json($mode);
        const urls = {
            list: "{{ route('ajax.performance.reviews.list', ['mode' => $mode]) }}",
            cycles: "{{ $mode === 'all' ? route('ajax.performance.cycles.list') : '' }}",
            open: "{{ url('/hr/performance/reviews') }}"
        };
        const empty = {
            my: 'You have no review yet. When HR publishes a review cycle that includes you, it appears here.',
            team: 'You are not the supervisor on any review. Reviews appear here when HR names you as someone\'s supervisor in a cycle.',
            all: 'No reviews match.'
        }[mode];

        function load() {
            const query = {};
            if (mode === 'all') {
                if ($('#prCycle').val()) query.cycle_id = $('#prCycle').val();
                if ($('#prStatus').val()) query.status = $('#prStatus').val();
            }
            $.getJSON(urls.list, query).done(function(res) {
                const text = (mode === 'my' && res.linked === false)
                    ? 'Your login is not linked to a staff record, so there is no review for you here. Ask HR to link it.'
                    : empty;
                $('#prTable tbody').html(PR.listBodyHtml(res.reviews, mode, function(row) { return urls.open + '/' + row.id; }, text));
            }).fail(function(xhr) {
                const json = xhr.responseJSON || {};
                toastr.error(json.error || json.message || 'The reviews could not be loaded.');
            });
        }

        if (mode === 'all') {
            $.getJSON(urls.cycles).done(function(res) {
                res.cycles.forEach(function(cycle) {
                    $('#prCycle').append($('<option>').val(cycle.id).text(cycle.name));
                });
            });
            $('#prCycle, #prStatus').on('change', load);
        }
        load();
    });
</script>
@endsection
