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
                <h1 class="pr-title">Meetings</h1>
                <div class="pr-hint">Joint review meetings and appeal hearings you are part of. HR also sees the ones that still need to be scheduled.</div>
            </div>
            <div class="pr-head-side">
                <input type="search" class="form-control form-control-sm pr-search" id="pmSearch" placeholder="Search by name or venue" aria-label="Search the meetings">
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table pr-table align-middle mb-0" id="pmTable">
                        <thead>
                            <tr>
                                <th>Staff member</th>
                                <th>Meeting</th>
                                <th>Date and time</th>
                                <th>Venue</th>
                                <th>Status</th>
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
<script src="{{ asset('assets/js/performance-meeting.js') }}?v={{ filemtime(public_path('assets/js/performance-meeting.js')) }}"></script>
<script>
    $(function() {
        const PM = window.PerformanceMeeting;
        const empty = 'You are not part of any meeting yet. When HR schedules one for you, it appears here and you are told in the app.';
        let rows = [];

        function draw() {
            const query = $('#pmSearch').val();
            const shown = rows.filter(function(row) { return PM.matches(row, query); });
            $('#pmTable tbody').html(PM.meetingsBodyHtml(shown, rows.length && !shown.length ? 'Nothing matches "' + query + '".' : empty));
        }

        $('#pmTable tbody').html(PM.skeletonRows(6, 4));
        $.getJSON("{{ route('ajax.performance.meetings.list') }}").done(function(res) {
            rows = res.meetings || [];
            draw();
        }).fail(function(xhr) {
            $('#pmTable tbody').html('<tr><td colspan="6">' + PM.emptyHtml('error', 'The meetings could not be loaded', 'Check your connection and reload the page.') + '</td></tr>');
            toastr.error(PM.message(xhr, 'The meetings could not be loaded.'));
        });
        $('#pmSearch').on('input', draw);
    });
</script>
@endsection
