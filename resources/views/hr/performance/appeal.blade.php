@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/performance-review.css') }}?v={{ filemtime(public_path('assets/css/performance-review.css')) }}">
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div id="paRoot" class="pr-page"></div>
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
            $('#paRoot .btn').prop('disabled', true);
            return $.ajax({ url: base + path, type: 'POST', data: data, dataType: 'json', headers: { 'X-CSRF-TOKEN': csrf } })
                .done(function(res) { page = res.page; editing = false; render(); toastr.success(text); window.scrollTo({ top: 0, behavior: 'smooth' }); })
                .fail(function(xhr) { $('#paRoot .btn').prop('disabled', false); toastr.error(PM.message(xhr, 'This could not be saved.')); });
        }

        function ratingsLine(row) {
            const show = function(value) { return value === null || value === undefined ? '-' : value; };
            return '<div class="pa-ratings"><span>Self <strong>' + esc(show(row.self_rating)) + '</strong></span><span>Supervisor <strong>' + esc(show(row.supervisor_rating)) + '</strong></span><span>Final <strong>' + esc(show(row.hr_rating)) + '</strong></span></div>';
        }

        // The form to appeal: each criterion can be picked, and a picked one needs a reason
        function fileFormHtml(onBehalf) {
            if (!page.criteria.length) return '';
            const rows = page.criteria.map(function(row) {
                return '<div class="pa-pick-card"><div class="form-check">'
                    + '<input class="form-check-input pa-pick" type="checkbox" id="paPick' + row.id + '" data-item="' + row.id + '">'
                    + '<label class="form-check-label pm-item-title" for="paPick' + row.id + '">' + esc(row.name) + '</label></div>'
                    + ratingsLine(row)
                    + '<textarea class="form-control mt-2 pa-why" rows="2" maxlength="2000" data-item="' + row.id + '" placeholder="Tick the criterion, then say why you dispute this rating" aria-label="Why the rating on ' + esc(row.name) + ' is disputed" disabled></textarea></div>';
            }).join('');
            const late = onBehalf && !page.appeal_window_open
                ? '<div class="mb-3"><label class="form-label" for="paLate">Why is HR accepting this appeal after the appeal period? <span class="text-danger">*</span></label><textarea class="form-control" id="paLate" rows="2" maxlength="2000"></textarea></div>'
                : '';
            return '<div class="pr-hint mb-3">Pick the criteria you dispute and say why for each one. The review committee reads this before the hearing.</div>' + rows
                + '<div class="mb-3"><label class="form-label" for="paReason">Anything else you want the committee to know</label><textarea class="form-control" id="paReason" rows="3" maxlength="2000"></textarea></div>'
                + late
                + '<button type="button" class="btn btn-primary" id="paFile" data-onbehalf="' + (onBehalf ? 1 : 0) + '">' + (onBehalf ? 'File the appeal for the staff member' : 'Submit my appeal') + '</button>';
        }

        function appealHtml() {
            const a = page.appeal;
            if (!a) {
                if (page.actions.file) {
                    return '<h2 class="pr-h">' + PM.icon('gavel') + 'Appeal this review</h2><div class="pr-hint mb-2">You can appeal until <strong>' + esc(page.appeal_deadline) + '</strong>. The decision of the review committee is final.</div>' + fileFormHtml(false);
                }
                if (page.actions.file_on_behalf) {
                    return '<h2 class="pr-h">' + PM.icon('gavel') + 'Appeal on behalf of the staff member</h2><div class="pr-hint mb-2">' + (page.appeal_window_open ? 'The appeal period runs until ' + esc(page.appeal_deadline) + '.' : 'The appeal period ended on ' + esc(page.appeal_deadline) + ', so a reason for accepting it late is needed.') + '</div>' + fileFormHtml(true);
                }
                if (!page.review.is_finalised) return PM.emptyHtml('hourglass_top', 'Not yet', 'A review can be appealed once HR has finalised it.');
                return PM.emptyHtml('gavel', 'No appeal', 'No appeal has been filed for this review.');
            }
            let html = '<h2 class="pr-h">' + PM.icon('rule') + 'What is disputed</h2><div class="pr-hint mb-2">Filed ' + esc(a.filed_at) + (a.on_behalf ? ' by HR for the staff member' : '') + '.</div>'
                + (a.late_reason ? '<div class="alert alert-secondary py-2">Accepted after the appeal period: ' + esc(a.late_reason) + '</div>' : '')
                + a.criteria.map(function(row) {
                    return '<div class="pm-item"><div class="pm-item-title">' + esc(row.name) + '</div>' + ratingsLine(row) + '<div class="pm-item-notes mt-1">' + esc(row.reason) + '</div></div>';
                }).join('');
            if (a.reason) html += '<div class="pm-item is-other"><div class="pm-item-title">Anything else</div><div class="pm-item-notes">' + esc(a.reason) + '</div></div>';
            return html;
        }

        function hearingHtml() {
            if (!page.appeal) return '';
            const m = page.meeting;
            let html = '<div class="card mb-3"><div class="card-body"><h2 class="pr-h">' + PM.icon('groups') + 'Hearing with the review committee</h2>';
            if (!m) {
                if (page.actions.schedule) {
                    html += '<div class="pr-hint mb-3">Everyone invited is told in the app as soon as you save.</div>' + PM.scheduleFormHtml(page.options, null, false)
                        + '<div class="mt-3"><button type="button" class="btn btn-primary" id="pmSchedule">Schedule the hearing and tell everyone</button></div>';
                } else {
                    html += PM.emptyHtml('event_upcoming', 'Not scheduled yet', 'HR schedules the hearing, and you are told in the app when it is.');
                }
                return html + '</div></div>';
            }
            html += '<div class="pr-hint mb-3">' + esc(m.needs) + '</div>'
                + '<div class="pr-facts"><div><div class="pr-fact-label">Date and time</div><div class="pr-fact-value">' + esc(m.scheduled_at) + '</div></div>'
                + '<div><div class="pr-fact-label">Venue</div><div class="pr-fact-value">' + esc(m.venue || '-') + '</div></div>'
                + (m.reschedule_count ? '<div><div class="pr-fact-label">Rescheduled</div><div class="pr-fact-value">' + esc(m.reschedule_count) + (m.reschedule_count === 1 ? ' time' : ' times') + '</div></div>' : '') + '</div>';
            if (m.not_reached && m.not_reached.length) {
                html += '<div class="alert alert-warning py-2">' + PM.icon('notifications_off') + ' These people have no working login, so the app could not tell them. Please tell them in person: <strong>' + m.not_reached.map(esc).join(', ') + '</strong>.</div>';
            }
            html += '<h2 class="pr-h">' + PM.icon('groups') + 'Who is invited</h2>' + PM.attendeesHtml(m.attendees, m.status === 'held');
            if (page.actions.reschedule) {
                html += editing
                    ? '<hr><h2 class="pr-h">' + PM.icon('event_repeat') + 'Reschedule</h2>' + PM.scheduleFormHtml(page.options, m, true)
                        + '<div class="mt-3 d-flex gap-2"><button type="button" class="btn btn-primary" id="pmSaveReschedule">Reschedule and tell everyone</button><button type="button" class="btn btn-light" id="pmCancelEdit">Cancel</button></div>'
                    : '<div class="mt-3"><button type="button" class="btn btn-outline-secondary" id="pmReschedule">' + PM.icon('event_repeat') + ' Reschedule</button></div>';
            }
            return html + '</div></div>';
        }

        function ratingOptions(selected) {
            let html = '';
            for (let rating = 1; rating <= 10; rating++) html += '<option value="' + rating + '"' + (String(selected) === String(rating) ? ' selected' : '') + '>' + rating + '</option>';
            return html;
        }

        // HR records the hearing and the committee's decision
        function decisionFormHtml() {
            if (!page.actions.record || !page.meeting) return '';
            const changes = page.appeal.criteria.map(function(row) {
                return '<div class="pm-item"><div class="pm-item-title">' + esc(row.name) + '</div>' + ratingsLine(row)
                    + '<div class="row g-2 mt-1"><div class="col-sm-3"><label class="form-label small mb-0" for="paNew' + row.item_id + '">Final rating after the decision</label>'
                    + '<select class="form-select form-select-sm pa-new" id="paNew' + row.item_id + '" data-item="' + row.item_id + '">' + ratingOptions(row.hr_rating) + '</select></div>'
                    + '<div class="col-sm-9"><label class="form-label small mb-0" for="paNewComment' + row.item_id + '">Comment (optional)</label>'
                    + '<textarea class="form-control form-control-sm pa-new-comment" id="paNewComment' + row.item_id + '" data-item="' + row.item_id + '" rows="2" maxlength="2000">' + esc(row.hr_comment || '') + '</textarea></div></div></div>';
            }).join('');
            const outcome = function(id, value, title, text) {
                return '<label class="pr-person" for="' + id + '"><input class="form-check-input" type="radio" name="paOutcome" id="' + id + '" value="' + value + '"><span><span class="pr-person-name">' + title + '</span><span class="pr-person-role">' + text + '</span></span></label>';
            };
            return '<div class="card mb-3"><div class="card-body" id="paDecideBox"><h2 class="pr-h">' + PM.icon('fact_check') + 'Record the hearing and the decision</h2>'
                + '<div class="pr-hint mb-3">Tick everyone who came. The committee\'s decision is final.</div>'
                + PM.attendanceFormHtml(page.meeting.attendees)
                + '<div class="mt-2"><button type="button" class="btn btn-sm btn-light" id="pmAll">Everyone came</button></div>'
                + '<div id="pmReqs"></div><hr>'
                + '<div class="mb-3"><div class="form-label">The committee\'s decision <span class="text-danger">*</span></div><div class="pm-attendance">'
                + outcome('paOutUpheld', 'upheld', 'Upheld', 'The ratings are changed')
                + outcome('paOutPartly', 'partly_upheld', 'Partly upheld', 'Some ratings are changed')
                + outcome('paOutRejected', 'rejected', 'Rejected', 'The ratings stand') + '</div></div>'
                + '<div id="paChanges" style="display:none;"><div class="pr-hint mb-2">Set the new final rating on each criterion that changes. A criterion left as it is does not change.</div>' + changes + '</div>'
                + '<div class="mb-3"><label class="form-label" for="paDecisionReason">The committee\'s reasons <span class="text-danger">*</span></label><textarea class="form-control" id="paDecisionReason" rows="4" maxlength="2000"></textarea><div class="pr-hint">The staff member reads this.</div></div>'
                + '<button type="button" class="btn btn-primary" id="paDecide">Record the decision</button></div></div>';
        }

        function updateRules() {
            if (!$('#pmReqs').length) return;
            const ticked = $('.pm-came:checked').map(function() { return this.value; }).get();
            const rules = PM.attendanceRules(page.meeting.attendees, ticked, page.min_committee, page.review.route);
            $('#pmReqs').html(PM.requirementsHtml(rules));
            const met = rules.every(function(rule) { return rule.ok; });
            $('#paDecide').prop('disabled', !met).attr('title', met ? '' : 'Tick everyone needed first');
        }

        function decisionHtml() {
            const a = page.appeal;
            if (!a || a.status !== 'decided') return '';
            const score = function(value) { return value === null || value === undefined ? '-' : value; };
            return '<div class="card mb-3"><div class="card-body"><h2 class="pr-h">' + PM.icon('gavel') + 'The committee\'s decision</h2>'
                + '<div class="mb-2"><span class="pr-chip is-green">' + esc(a.outcome_label) + '</span> <span class="pr-hint">' + esc(a.decided_at) + '</span></div>'
                + '<div class="pm-item-notes mb-3">' + esc(a.decision_reason) + '</div>'
                + '<div class="pr-scores"><div class="pr-score"><div class="pr-score-label">Final score before</div><div class="pr-score-value">' + esc(score(a.score_before)) + '</div></div>'
                + '<div class="pr-score is-final"><div class="pr-score-label">Final score after</div><div class="pr-score-value">' + esc(score(a.score_after)) + '</div></div></div></div></div>';
        }

        function render() {
            const a = page.appeal;
            const m = page.meeting;
            const state = !a ? null : (a.status === 'decided' ? ['decided', 'Decided'] : (m ? ['hearing_scheduled', 'Hearing scheduled'] : ['filed', 'Waiting for a hearing']));
            const steps = PM.stepsFor({ status: page.review.status, route: page.review.route, meeting: null, appeal: a ? { status: a.status, outcome_label: a.outcome_label, hearing: !!m } : null });
            $('#paRoot').html(
                PM.headHtml({
                    eyebrow: 'Appeal', title: page.review.staff,
                    meta: [{ icon: 'badge', text: page.review.position }, { icon: 'event', text: page.review.cycle }],
                    status: state ? { status: state[0], label: state[1] } : null,
                    back: { url: reviewUrl, text: 'Back to the review' }
                })
                + PM.stepperHtml(steps) + PM.nextHtml(PM.nextForPage(page))
                + '<div class="card mb-3"><div class="card-body" id="paBody">' + appealHtml() + '</div></div>'
                + hearingHtml() + decisionFormHtml() + decisionHtml()
            );
            updateRules();
        }

        $(document).on('change', '.pm-came', updateRules);
        $(document).on('click', '#pmAll', function() { $('.pm-came').prop('checked', true); updateRules(); });

        $(document).on('change', '.pa-pick', function() {
            const textarea = $('.pa-why[data-item="' + $(this).attr('data-item') + '"]');
            textarea.prop('disabled', !this.checked);
            if (this.checked) textarea.trigger('focus'); else textarea.val('');
        });

        $(document).on('click', '#paFile', function() {
            const grounds = {};
            $('.pa-pick:checked').each(function() {
                const id = $(this).attr('data-item');
                grounds[id] = $('.pa-why[data-item="' + id + '"]').val() || '';
            });
            const onBehalf = $(this).attr('data-onbehalf') === '1';
            if (!confirm(onBehalf ? 'File this appeal for the staff member now?' : 'Submit your appeal now? You can appeal only once, and the committee decision is final.')) return;
            const data = { grounds: grounds, reason: $('#paReason').val() || '' };
            if (onBehalf) data.late_reason = $('#paLate').val() || '';
            post(onBehalf ? '/appeal/on-behalf' : '/appeal', data, 'The appeal is filed and everyone involved was told.');
        });

        $(document).on('click', '#pmSchedule', function() {
            const form = PM.readSchedule($('#paRoot'));
            post('/meeting/appeal/schedule', { scheduled_at: form.scheduled_at, venue: form.venue, hr_user_id: form.hr_user_id, committee_staff_ids: form.committee_staff_ids }, 'The hearing is scheduled and everyone was told.');
        });
        $(document).on('click', '#pmReschedule', function() { editing = true; render(); });
        $(document).on('click', '#pmCancelEdit', function() { editing = false; render(); });
        $(document).on('click', '#pmSaveReschedule', function() {
            const form = PM.readSchedule($('#paRoot'));
            post('/meeting/appeal/reschedule', { scheduled_at: form.scheduled_at, venue: form.venue, hr_user_id: form.hr_user_id, committee_staff_ids: form.committee_staff_ids, reason: form.reason }, 'The hearing was rescheduled and everyone was told.');
        });

        $(document).on('change', 'input[name="paOutcome"]', function() { $('#paChanges').toggle($('input[name="paOutcome"]:checked').val() !== 'rejected'); });

        $(document).on('click', '#paDecide', function() {
            const outcome = $('input[name="paOutcome"]:checked').val() || '';
            if (!confirm('Record the committee decision now? It is final.')) return;
            const ratings = {};
            if (outcome && outcome !== 'rejected') {
                $('.pa-new').each(function() {
                    const id = $(this).attr('data-item');
                    ratings[id] = { rating: $(this).val(), comment: $('.pa-new-comment[data-item="' + id + '"]').val() || '' };
                });
            }
            post('/appeal/decide', { attendance: PM.readAttendance($('#paDecideBox')), outcome: outcome, decision_reason: $('#paDecisionReason').val() || '', ratings: ratings }, 'The decision is recorded and everyone was told.');
        });

        render();
    });
</script>
@endsection
