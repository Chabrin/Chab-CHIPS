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
                <h1 class="pr-title">Appeals</h1>
                <div class="pr-hint">Every appeal against a finalised review. Open one to schedule the hearing with the review committee and to record its decision.</div>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table pr-table align-middle mb-0" id="paTable">
                        <thead>
                            <tr>
                                <th>Staff member</th>
                                <th>Cycle</th>
                                <th>Filed</th>
                                <th>Hearing</th>
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
        const open = "{{ url('/hr/performance/reviews') }}";
        $('#paTable tbody').html(PM.skeletonRows(6, 3));
        $.getJSON("{{ route('ajax.performance.appeals.list') }}").done(function(res) {
            $('#paTable tbody').html(PM.appealsBodyHtml(res.appeals, function(row) { return open + '/' + row.review_id + '/appeal'; }, 'No appeal has been filed. When a staff member appeals a finalised review it appears here.'));
        }).fail(function(xhr) {
            $('#paTable tbody').html('<tr><td colspan="6">' + PM.emptyHtml('error', 'The appeals could not be loaded', 'Check your connection and reload the page.') + '</td></tr>');
            toastr.error(PM.message(xhr, 'The appeals could not be loaded.'));
        });
    });
</script>
@endsection
