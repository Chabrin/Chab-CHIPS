@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/performance-review.css') }}?v={{ filemtime(public_path('assets/css/performance-review.css')) }}">
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid pr-page">
        <div class="pr-head">
            <div class="pr-head-main">
                <div class="pr-eyebrow">Performance review</div>
                <h1 class="pr-title">{{ $title }}</h1>
                <div class="pr-hint">{{ $hint }}</div>
            </div>
            <div class="pr-head-side pr-toolbar">
                <input type="search" class="form-control form-control-sm pr-search" id="prSearch" placeholder="Search by name, position or cycle" aria-label="Search the reviews">
                @if ($mode === 'all')
                <select class="form-select form-select-sm" id="prCycle" aria-label="Cycle" style="min-width:11rem;"><option value="">All cycles</option></select>
                <select class="form-select form-select-sm" id="prStatus" aria-label="Status" style="min-width:12rem;">
                    <option value="">Any status</option>
                    <option value="awaiting_self">Waiting for self-rating</option>
                    <option value="awaiting_meeting">Waiting for the joint review meeting</option>
                    <option value="awaiting_supervisor">Waiting for supervisor</option>
                    <option value="awaiting_hr">Waiting for HR</option>
                    <option value="finalised">Finalised</option>
                </select>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table pr-table align-middle mb-0" id="prTable">
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
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/js/performance-review.js') }}?v={{ filemtime(public_path('assets/js/performance-review.js')) }}"></script>
<script src="{{ asset('assets/js/performance-meeting.js') }}?v={{ filemtime(public_path('assets/js/performance-meeting.js')) }}"></script>
<script>
    $(function() {
        const PR = window.PerformanceReview;
        const PM = window.PerformanceMeeting;
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
        const columns = mode === 'my' ? 5 : (mode === 'all' ? 7 : 6);
        let rows = [];
        let emptyText = empty;

        function draw() {
            const query = $('#prSearch').val();
            const shown = rows.filter(function(row) { return PM.matches(row, query); });
            const text = rows.length && !shown.length ? 'Nothing matches "' + query + '". Try a different name.' : emptyText;
            $('#prTable tbody').html(PR.listBodyHtml(shown, mode, function(row) { return urls.open + '/' + row.id; }, text));
        }

        function load() {
            const query = {};
            if (mode === 'all') {
                if ($('#prCycle').val()) query.cycle_id = $('#prCycle').val();
                if ($('#prStatus').val()) query.status = $('#prStatus').val();
            }
            $('#prTable tbody').html(PM.skeletonRows(columns, 4));
            $.getJSON(urls.list, query).done(function(res) {
                emptyText = (mode === 'my' && res.linked === false)
                    ? 'Your login is not linked to a staff record, so there is no review for you here. Ask HR to link it.'
                    : empty;
                rows = res.reviews || [];
                draw();
            }).fail(function(xhr) {
                $('#prTable tbody').html('<tr><td colspan="' + columns + '">' + PM.emptyHtml('error', 'The reviews could not be loaded', 'Check your connection and reload the page.') + '</td></tr>');
                toastr.error(PM.message(xhr, 'The reviews could not be loaded.'));
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
        $('#prSearch').on('input', draw);
        load();
    });
</script>
@endsection
