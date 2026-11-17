@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/performance-review.css') }}?v={{ filemtime(public_path('assets/css/performance-review.css')) }}">
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div id="pmRoot" class="pr-page"></div>
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
        let page = @json($page);
        const base = "{{ url('/ajax/hr/performance/reviews') }}/" + page.review.id;
        const reviewUrl = "{{ url('/hr/performance/reviews') }}/" + page.review.id;
        let editing = false;

        function post(path, data, text) {
            $('#pmRoot .btn').prop('disabled', true);
            return $.ajax({ url: base + path, type: 'POST', data: data, dataType: 'json', headers: { 'X-CSRF-TOKEN': csrf } })
                .done(function(res) { page = res.page; editing = false; render(); toastr.success(text); window.scrollTo({ top: 0, behavior: 'smooth' }); })
                .fail(function(xhr) { $('#pmRoot .btn').prop('disabled', false); toastr.error(PM.message(xhr, 'This could not be saved.')); });
        }

        function facts(m) {
            const fact = function(label, value) { return '<div><div class="pr-fact-label">' + esc(label) + '</div><div class="pr-fact-value">' + esc(value) + '</div></div>'; };
            return '<div class="pr-facts">' + fact('Date and time', m.scheduled_at) + fact('Venue', m.venue || '-')
                + (m.held_at ? fact('Recorded', m.held_at) : '')
                + (m.reschedule_count ? fact('Rescheduled', m.reschedule_count + (m.reschedule_count === 1 ? ' time' : ' times')) : '') + '</div>';
        }

        function detailsHtml() {
            const m = page.meeting;
            if (!m) {
                if (page.actions.schedule) {
                    return '<h2 class="pr-h">' + PM.icon('event_available') + 'Schedule the meeting</h2><div class="pr-hint mb-3">Everyone invited is told in the app as soon as you save.</div>'
                        + PM.scheduleFormHtml(page.options, null, false)
                        + '<div class="mt-3"><button type="button" class="btn btn-primary" id="pmSchedule">Schedule and tell everyone</button></div>';
                }
                if (page.review.status === 'awaiting_self') return PM.emptyHtml('hourglass_top', 'Not yet', 'The joint review meeting is scheduled after the self-rating has been submitted.');
                if (page.review.status === 'awaiting_meeting') return PM.emptyHtml('event_upcoming', 'Not scheduled yet', 'HR schedules it, and you are told in the app when it is.');
                return PM.emptyHtml('event_busy', 'No meeting', 'No joint review meeting was held for this review.');
            }
            let html = facts(m);
            if (m.not_reached && m.not_reached.length) {
                html += '<div class="alert alert-warning py-2">' + PM.icon('notifications_off') + ' These people have no working login, so the app could not tell them. Please tell them in person: <strong>' + m.not_reached.map(esc).join(', ') + '</strong>.</div>';
            }
            html += '<h2 class="pr-h">' + PM.icon('groups') + (m.status === 'held' ? 'Who was invited' : 'Who is invited') + '</h2>' + PM.attendeesHtml(m.attendees, m.status === 'held');
            if (page.actions.reschedule) {
                html += editing
                    ? '<hr><h2 class="pr-h">' + PM.icon('event_repeat') + 'Reschedule</h2>' + PM.scheduleFormHtml(page.options, m, true)
                        + '<div class="mt-3 d-flex gap-2"><button type="button" class="btn btn-primary" id="pmSaveReschedule">Reschedule and tell everyone</button><button type="button" class="btn btn-light" id="pmCancelEdit">Cancel</button></div>'
                    : '<div class="mt-3"><button type="button" class="btn btn-outline-secondary" id="pmReschedule">' + PM.icon('event_repeat') + ' Reschedule</button></div>';
            }
            return html;
        }

        function recordHtml() {
            if (!page.actions.record || !page.meeting) return '';
            const sections = Object.keys(page.sections).map(function(key) {
                const required = key === 'performance_feedback';
                const approve = page.approvable.indexOf(key) !== -1
                    ? '<div class="form-check mt-1"><input class="form-check-input" type="checkbox" id="pmApp_' + key + '"><label class="form-check-label" for="pmApp_' + key + '">Approved by the committee</label></div>'
                    : '';
                return '<div class="mb-3"><label class="form-label" for="pmSec_' + key + '">' + esc(page.sections[key]) + (required ? ' <span class="text-danger">*</span>' : '') + '</label>'
                    + '<textarea class="form-control" id="pmSec_' + key + '" rows="3" maxlength="4000"></textarea>' + approve + '</div>';
            }).join('');
            return '<div class="card mb-3"><div class="card-body" id="pmRecordBox">'
                + '<h2 class="pr-h">' + PM.icon('fact_check') + 'Record the meeting</h2>'
                + '<div class="pr-hint mb-3">Tick everyone who came. If someone could not come, reschedule the meeting instead.</div>'
                + PM.attendanceFormHtml(page.meeting.attendees)
                + '<div class="mt-2"><button type="button" class="btn btn-sm btn-light" id="pmAll">Everyone came</button></div>'
                + '<div id="pmReqs"></div>'
                + '<hr><h2 class="pr-h">' + PM.icon('forum') + 'What was discussed</h2>' + sections
                + '<div class="mb-3"><div class="form-label">Anything else that was discussed</div><div id="pmExtras"></div>'
                + '<button type="button" class="btn btn-outline-primary btn-sm" id="pmAddExtra">' + PM.icon('add') + ' Add another item</button></div>'
                + '<button type="button" class="btn btn-primary" id="pmRecord">Record the meeting</button></div></div>';
        }

        // The rule about who must be there, shown as people are ticked; the Record button waits until it is met
        function updateRules() {
            if (!$('#pmReqs').length) return;
            const ticked = $('.pm-came:checked').map(function() { return this.value; }).get();
            const rules = PM.attendanceRules(page.meeting.attendees, ticked, page.min_committee, page.review.route);
            $('#pmReqs').html(PM.requirementsHtml(rules));
            const met = rules.every(function(rule) { return rule.ok; });
            $('#pmRecord').prop('disabled', !met).attr('title', met ? '' : 'Tick everyone needed first');
        }

        function discussedHtml() {
            const m = page.meeting;
            if (!m || m.status !== 'held') return '';
            return '<div class="card mb-3"><div class="card-body"><h2 class="pr-h">' + PM.icon('forum') + 'What was discussed</h2>' + PM.itemsHtml(m.items, page.approvable) + '</div></div>';
        }

        function render() {
            const m = page.meeting;
            const steps = PM.stepsFor({ status: page.review.status, route: page.review.route, meeting: m ? { status: m.status, when: m.scheduled_at } : null, appeal: null });
            const needs = m && m.needs && !page.actions.record ? '<div class="pr-hint mb-3">' + esc(m.needs) + '</div>' : '';
            $('#pmRoot').html(
                PM.headHtml({
                    eyebrow: 'Joint review meeting', title: page.review.staff,
                    meta: [{ icon: 'badge', text: page.review.position }, { icon: 'event', text: page.review.cycle }],
                    status: m ? { status: m.status, label: m.status_label } : { status: 'to_schedule', label: 'Not scheduled yet' },
                    back: { url: reviewUrl, text: 'Back to the review' }
                })
                + PM.stepperHtml(steps) + PM.nextHtml(PM.nextForPage(page))
                + '<div class="card mb-3"><div class="card-body">' + needs + detailsHtml() + '</div></div>'
                + recordHtml() + discussedHtml()
            );
            updateRules();
        }

        $(document).on('change', '.pm-came', updateRules);
        $(document).on('click', '#pmAll', function() { $('.pm-came').prop('checked', true); updateRules(); });
        $(document).on('click', '#pmSchedule', function() {
            const form = PM.readSchedule($('#pmRoot'));
            post('/meeting/review/schedule', { scheduled_at: form.scheduled_at, venue: form.venue, hr_user_id: form.hr_user_id, committee_staff_ids: form.committee_staff_ids }, 'The meeting is scheduled and everyone was told.');
        });
        $(document).on('click', '#pmReschedule', function() { editing = true; render(); });
        $(document).on('click', '#pmCancelEdit', function() { editing = false; render(); });
        $(document).on('click', '#pmSaveReschedule', function() {
            const form = PM.readSchedule($('#pmRoot'));
            post('/meeting/review/reschedule', { scheduled_at: form.scheduled_at, venue: form.venue, hr_user_id: form.hr_user_id, committee_staff_ids: form.committee_staff_ids, reason: form.reason }, 'The meeting was rescheduled and everyone was told.');
        });
        $(document).on('click', '#pmAddExtra', function() { $('#pmExtras').append(PM.extraRowHtml('', '')); $('#pmExtras .pm-extra-title').last().trigger('focus'); });
        $(document).on('click', '.pm-extra-remove', function() { $(this).closest('.pm-extra').remove(); });
        $(document).on('click', '#pmRecord', function() {
            if (!confirm('Record the meeting now? The people ticked as present are saved and the review moves on.')) return;
            const sections = {};
            Object.keys(page.sections).forEach(function(key) {
                sections[key] = { notes: $('#pmSec_' + key).val() || '', approved: $('#pmApp_' + key).is(':checked') ? 1 : 0 };
            });
            const box = $('#pmRecordBox');
            post('/meeting/record', { attendance: PM.readAttendance(box), sections: sections, extras: PM.readExtras(box) }, 'The meeting is recorded.');
        });

        render();
    });
</script>
@endsection
