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
                <h1 class="pr-title">Review committee</h1>
                <div class="pr-hint">The people HR can pick for a joint review meeting. The same committee hears appeals. When you schedule a meeting you pick which of them attend.</div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h2 class="pr-h"><span class="material-symbols-outlined" aria-hidden="true">groups</span>Members</h2>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <select class="form-select flex-grow-1" id="pcStaff" aria-label="Staff member to add" style="min-width:14rem;"><option value="">Choose a staff member to add</option></select>
                            <button type="button" class="btn btn-primary text-nowrap" id="pcAdd">Add to the committee</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table pr-table align-middle mb-0" id="pcTable">
                                <thead><tr><th>Name</th><th>Can be told in the app</th><th></th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h2 class="pr-h"><span class="material-symbols-outlined" aria-hidden="true">tune</span>Rules HR can change</h2>
                        <div class="mb-3">
                            <label class="form-label" for="pcMin">Fewest committee members who must attend</label>
                            <input type="number" class="form-control" id="pcMin" min="1" max="50" step="1">
                            <div class="pr-hint mt-1">A meeting or hearing can only be recorded as held when at least this many committee members came.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="pcDays">Appeal period in days</label>
                            <input type="number" class="form-control" id="pcDays" min="1" max="365" step="1">
                            <div class="pr-hint mt-1">How long after a review is finalised the staff member can appeal on their own. HR can still file an appeal for them later, with a reason.</div>
                        </div>
                        <button type="button" class="btn btn-primary" id="pcSave">Save</button>
                    </div>
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
        const esc = PM.escapeHtml;
        const csrf = $('meta[name="csrf-token"]').attr('content');
        const urls = {
            list: "{{ route('ajax.performance.committee.list') }}",
            add: "{{ route('ajax.performance.committee.add') }}",
            settings: "{{ route('ajax.performance.committee.settings') }}",
            remove: "{{ url('/ajax/hr/performance/committee') }}"
        };

        function load() {
            $('#pcTable tbody').html(PM.skeletonRows(3, 3));
            $.getJSON(urls.list).done(function(res) {
                $('#pcStaff').html('<option value="">Choose a staff member to add</option>' + res.candidates.map(function(staff) {
                    return '<option value="' + esc(staff.staff_id) + '">' + esc(staff.name) + '</option>';
                }).join(''));
                $('#pcMin').val(res.settings.min_committee_present);
                $('#pcDays').val(res.settings.appeal_window_days);
                $('#pcTable tbody').html(res.members.length ? res.members.map(function(member) {
                    return '<tr><td><span class="d-inline-flex align-items-center gap-2"><span class="pr-avatar">' + esc(PM.initials(member.name)) + '</span><span class="fw-semibold">' + esc(member.name) + '</span>'
                        + (member.active ? '' : ' <span class="pr-chip is-grey">Left</span>') + '</span></td>'
                        + '<td>' + (member.has_login ? '<span class="pr-chip is-green">Yes</span>' : '<span class="pr-chip is-amber">No login: tell them in person</span>') + '</td>'
                        + '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger pc-remove" data-id="' + esc(member.id) + '">Remove</button></td></tr>';
                }).join('') : '<tr><td colspan="3">' + PM.emptyHtml('groups', 'Nobody is on the committee yet', 'Add the people who will sit on it using the box above.') + '</td></tr>');
            }).fail(function(xhr) {
                $('#pcTable tbody').html('<tr><td colspan="3">' + PM.emptyHtml('error', 'The committee could not be loaded', 'Check your connection and reload the page.') + '</td></tr>');
                toastr.error(PM.message(xhr, 'The committee could not be loaded.'));
            });
        }

        $('#pcAdd').on('click', function() {
            const id = $('#pcStaff').val();
            if (!id) { toastr.error('Choose a staff member first.'); return; }
            $.ajax({ url: urls.add, type: 'POST', data: { staff_id: id }, dataType: 'json', headers: { 'X-CSRF-TOKEN': csrf } })
                .done(function(res) { toastr.success(res.message); load(); })
                .fail(function(xhr) { toastr.error(PM.message(xhr, 'This could not be saved.')); });
        });

        $(document).on('click', '.pc-remove', function() {
            if (!confirm('Remove this person from the committee?')) return;
            $.ajax({ url: urls.remove + '/' + $(this).attr('data-id'), type: 'DELETE', dataType: 'json', headers: { 'X-CSRF-TOKEN': csrf } })
                .done(function(res) { toastr.success(res.message); load(); })
                .fail(function(xhr) { toastr.error(PM.message(xhr, 'This could not be removed.')); });
        });

        $('#pcSave').on('click', function() {
            $.ajax({ url: urls.settings, type: 'POST', data: { min_committee_present: $('#pcMin').val(), appeal_window_days: $('#pcDays').val() }, dataType: 'json', headers: { 'X-CSRF-TOKEN': csrf } })
                .done(function(res) { toastr.success(res.message); })
                .fail(function(xhr) { toastr.error(PM.message(xhr, 'This could not be saved.')); });
        });

        load();
    });
</script>
@endsection
