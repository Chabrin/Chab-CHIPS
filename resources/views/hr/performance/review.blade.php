@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/performance-review.css') }}?v={{ filemtime(public_path('assets/css/performance-review.css')) }}">
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div id="prRoot" class="pr-page"></div>
    </div>
</div>

<div class="modal fade" id="prReasonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="prReasonForm" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="prReasonTitle">Reason</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3" id="prSupervisorPick" style="display:none;">
                    <label for="prSupervisorSelect" class="form-label">New supervisor</label>
                    <select class="form-select" id="prSupervisorSelect"></select>
                </div>
                <label for="prReasonText" class="form-label" id="prReasonLabel">Reason <span class="text-danger">*</span></label>
                <textarea class="form-control" id="prReasonText" rows="3" maxlength="1000"></textarea>
                <div class="invalid-feedback">A reason is needed.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="prReasonConfirm">Confirm</button>
            </div>
        </form>
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
        const esc = PR.escapeHtml;
        const stageUrls = {
            meeting: "{{ url('/hr/performance/reviews') }}/" + @json($review['id']) + "/meeting",
            appeal: "{{ url('/hr/performance/reviews') }}/" + @json($review['id']) + "/appeal"
        };
        const csrf = $('meta[name="csrf-token"]').attr('content');
        let review = @json($review);
        const base = "{{ url('/ajax/hr/performance/reviews') }}/" + review.id;
        const reasonModal = new bootstrap.Modal(document.getElementById('prReasonModal'));
        let onReason = null;

        const COLUMN_TITLES = { self: 'Self-rating', supervisor: 'Supervisor\'s rating', hr: 'HR final rating' };

        function can(action) { return review.actions.indexOf(action) !== -1; }

        function columns() {
            if (review.role === 'subject') return review.is_finalised ? ['self', 'supervisor', 'hr'] : ['self'];
            if (review.role === 'supervisor') return review.is_finalised ? ['self', 'supervisor', 'hr'] : ['self', 'supervisor'];
            return ['self', 'supervisor', 'hr'];
        }

        function editable(who) {
            if (who === 'self') return can('self') || can('self_on_behalf');
            if (who === 'supervisor') return can('supervisor');
            return can('hr') || can('correct');
        }

        function columnTitle(who) {
            if (who === 'self' && can('self_on_behalf')) return 'Self-rating (entered by HR)';
            if (who === 'hr' && review.role !== 'hr') return 'Final rating';
            return COLUMN_TITLES[who];
        }

        function cell(item, who) {
            const rating = item[who + '_rating'];
            const comment = item[who + '_comment'];
            if (editable(who)) {
                // HR starts from the supervisor's rating, which it can change; nothing is saved until it presses a button
                const start = (who === 'hr' && (rating === null || rating === undefined) && item.supervisor_rating != null) ? item.supervisor_rating : rating;
                return PR.ratingSelect(who, item.id, start, false)
                    + '<textarea class="form-control pr-comment mt-1" data-who="' + who + '" data-item="' + item.id + '" rows="2" maxlength="2000" placeholder="Comment (optional)">' + esc(comment || '') + '</textarea>';
            }
            if (rating === undefined) return '<span class="text-muted small">Not shared yet</span>';
            return '<div class="pr-shown">' + (rating === null ? '<span class="text-muted">-</span>' : esc(rating)) + '</div>'
                + (comment ? '<div class="pr-shown-comment">' + esc(comment) + '</div>' : '');
        }

        function scoreBox(label, score) {
            return '<div class="pr-score' + (label === 'Final score' ? ' is-final' : '') + '"><div class="pr-score-label">' + esc(label) + '</div><div class="pr-score-value" data-score="' + esc(label) + '">' + esc(PR.formatScore(score)) + '</div></div>';
        }

        function metricsHtml() {
            const metrics = review.metrics;
            if (review.role === 'subject' || !metrics || !metrics.items || !metrics.items.length) return '';
            return '<div class="card mb-3"><div class="card-body">'
                + '<h6 class="mb-1">Records for this period</h6>'
                + '<div class="pr-hint mb-2">' + esc(metrics.period || '') + '. For reference when rating; they do not change any score by themselves.</div>'
                + '<div class="row g-2">' + metrics.items.map(function(metric) {
                    return '<div class="col-12 col-md-6 col-xl-4"><div class="pr-metric pr-flag-' + esc(metric.flag || 'none') + '">'
                        + '<div class="pr-metric-label">' + esc(metric.label) + '</div>'
                        + '<div class="pr-metric-value">' + esc(metric.display) + '</div>'
                        + (metric.note ? '<div class="pr-hint">' + esc(metric.note) + '</div>' : '')
                        + '</div></div>';
                }).join('') + '</div></div></div>';
        }

        // Where the joint review meeting and the appeal have got to. The pages they link to hold the details and the forms.
        function stageCard(title, iconName, line, url, buttonText, body) {
            return '<div class="card mb-3"><div class="card-body"><div class="d-flex flex-wrap gap-2 align-items-start">'
                + '<div class="me-auto"><h2 class="pr-h">' + PM.icon(iconName) + esc(title) + '</h2><div class="pr-hint">' + line + '</div></div>'
                + '<a class="btn btn-outline-primary btn-sm" href="' + esc(url) + '">' + esc(buttonText) + '</a></div>' + (body || '') + '</div></div>';
        }

        function stagesHtml() {
            const stages = review.stages || {};
            const can = stages.can || {};
            let html = '';
            if (stages.meeting) {
                const m = stages.meeting;
                const feedback = m.items && m.items.length ? '<div class="mt-3"><div class="pr-hint mb-2">What was discussed at the meeting</div>' + PM.itemsHtml(m.items, ['training_needs', 'goals']) + '</div>' : '';
                html += stageCard('Joint review meeting', 'groups', esc(m.status_label) + ': ' + esc(m.when) + (m.venue ? ', ' + esc(m.venue) : ''), stageUrls.meeting, 'Open the meeting', feedback);
            } else if (can.meeting) {
                html += stageCard('Joint review meeting', 'groups', 'Not scheduled yet. HR schedules it with the committee, and everyone invited is told in the app.', stageUrls.meeting, 'Open the meeting');
            }
            if (stages.appeal) {
                html += stageCard('Appeal', 'gavel', esc(stages.appeal.status_label) + (stages.appeal.outcome_label ? ': ' + esc(stages.appeal.outcome_label) : ''), stageUrls.appeal, 'Open the appeal');
            } else if (review.is_finalised && can.appeal) {
                html += stageCard('Appeal', 'gavel', can.file_appeal ? 'If you do not accept this review you can appeal it. The review committee hears your appeal and its decision is final.' : 'No appeal has been filed.', stageUrls.appeal, can.file_appeal ? 'Appeal this review' : 'Open the appeal');
            }
            return html;
        }

        function historyHtml() {
            if (!review.history || !review.history.length) return '';
            const LABELS = { created: 'Review created', self_saved: 'Self-rating saved', self_submitted: 'Self-rating submitted', self_skipped: 'Self-rating skipped', supervisor_saved: 'Supervisor rating saved', supervisor_submitted: 'Supervisor rating submitted', hr_saved: 'HR rating saved', finalised: 'Finalised', returned_to_supervisor: 'Sent back to the supervisor', supervisor_changed: 'Supervisor changed', corrected: 'Final rating corrected', meeting_scheduled: 'Joint review meeting scheduled', meeting_rescheduled: 'Joint review meeting rescheduled', meeting_held: 'Joint review meeting held', appeal_filed: 'Appeal filed', hearing_scheduled: 'Appeal hearing scheduled', hearing_rescheduled: 'Appeal hearing rescheduled', appeal_decided: 'Appeal decided' };
            return '<div class="card mb-3"><div class="card-body"><h2 class="pr-h">' + PM.icon('history') + 'History</h2>' + PM.timelineHtml(review.history, LABELS) + '</div></div>';
        }

        function summaryBox(id, label, value, isEditable) {
            if (isEditable) {
                return '<div class="mb-3"><label class="form-label" for="' + id + '">' + esc(label) + '</label><textarea class="form-control" id="' + id + '" rows="3" maxlength="2000">' + esc(value || '') + '</textarea></div>';
            }
            if (!value) return '';
            return '<div class="mb-3"><div class="form-label">' + esc(label) + '</div><div class="pr-shown-comment">' + esc(value) + '</div></div>';
        }

        function buttons() {
            let html = '';
            if (can('self') || can('self_on_behalf')) {
                html += '<button type="button" class="btn btn-light pr-act" data-act="save">Save draft</button> <button type="button" class="btn btn-primary pr-act" data-act="submit">Submit self-rating</button> ';
            }
            if (can('supervisor')) {
                html += '<button type="button" class="btn btn-light pr-act" data-act="save">Save draft</button> <button type="button" class="btn btn-primary pr-act" data-act="submit">Submit my rating</button> ';
            }
            if (can('hr')) {
                html += '<button type="button" class="btn btn-light pr-act" data-act="save">Save draft</button> <button type="button" class="btn btn-primary pr-act" data-act="finalise">Finalise review</button> ';
            }
            if (can('correct')) {
                html += '<button type="button" class="btn btn-primary pr-act" data-act="correct">Save correction</button> ';
            }
            if (can('return')) html += '<button type="button" class="btn btn-outline-secondary pr-act" data-act="return">Send back to supervisor</button> ';
            if (can('skip_self')) html += '<button type="button" class="btn btn-outline-secondary pr-act" data-act="skip">Skip the self-rating</button> ';
            if (can('reassign')) html += '<button type="button" class="btn btn-outline-secondary pr-act" data-act="reassign">Change supervisor</button> ';
            return html;
        }

        function render() {
            const cols = columns();
            const stages = review.stages || {};
            const supervisorLine = review.role !== 'subject' ? 'Supervisor: ' + (review.supervisor || (review.route === 'hr' ? 'none, HR rates' : '-')) : '';
            const top = PM.headHtml({
                    eyebrow: 'Performance review', title: review.staff.name,
                    meta: [
                        { icon: 'badge', text: review.staff.position },
                        { icon: 'event', text: review.cycle.name + ' (' + review.cycle.period + ')' },
                        { icon: 'flag', text: review.cycle.deadline ? 'Deadline ' + review.cycle.deadline : '' },
                        { icon: 'supervisor_account', text: supervisorLine }
                    ],
                    status: { status: review.status, label: review.status_label },
                    back: { url: review.back_url, text: 'Back to the list' }
                })
                + PM.stepperHtml(PM.stepsFor({
                    status: review.status, route: review.has_supervisor === false ? 'hr' : 'supervisor',
                    meeting: stages.meeting ? { status: stages.meeting.status, when: stages.meeting.when } : null,
                    appeal: stages.appeal ? { status: stages.appeal.status, outcome_label: stages.appeal.outcome_label, hearing: stages.appeal.status_label === 'Hearing scheduled' } : null
                }))
                + PM.nextHtml(PM.nextForReview(review, stageUrls));

            // A score box is always there for the column being edited, so the person sees it change as they rate
            let scores = '';
            if (review.is_finalised && review.final) {
                scores += scoreBox('Final score', review.final.score);
            }
            if (cols.indexOf('self') !== -1 && (review.scores.self != null || editable('self'))) scores += scoreBox('Self score', review.scores.self);
            if ((review.role !== 'subject' || review.is_finalised) && (review.scores.supervisor != null || editable('supervisor'))) scores += scoreBox('Supervisor score', review.scores.supervisor);
            if (review.role === 'hr' && !review.is_finalised && (review.scores.hr != null || editable('hr'))) scores += scoreBox('HR score so far', review.scores.hr);

            let head = '<th>Criterion</th>' + cols.map(function(who) { return '<th>' + esc(columnTitle(who)) + (editable(who) ? ' <span class="pr-weight">(1 to ' + PR.MAX + ')</span>' : '') + '</th>'; }).join('');
            let rows = review.items.map(function(item) {
                return '<tr data-item="' + item.id + '"><td><div class="fw-semibold">' + esc(item.name) + '</div>'
                    + (item.description ? '<div class="pr-hint">' + esc(item.description) + '</div>' : '')
                    + '<div class="pr-weight">Weight ' + esc(item.weight) + '</div></td>'
                    + cols.map(function(who) { return '<td>' + cell(item, who) + '</td>'; }).join('') + '</tr>';
            }).join('');
            if (!rows) rows = '<tr><td colspan="' + (cols.length + 1) + '" class="text-center text-muted">This review has no criteria.</td></tr>';

            let notes = '';
            if (review.self_skipped) notes += '<div class="alert alert-secondary py-2">The self-rating was skipped by HR.</div>';
            if (review.role === 'subject' && !review.is_finalised) notes += '<div class="pr-hint mb-2">You see your own ratings here. Your supervisor\'s rating and the final rating appear once HR finalises the review.</div>';

            const html = top + '<div class="card mb-3"><div class="card-body">' + notes + (scores ? '<div class="pr-scores mb-3">' + scores + '</div>' : '')
                + '<div class="table-responsive"><table class="table align-middle" id="prCriteria"><thead><tr>' + head + '</tr></thead><tbody>' + rows + '</tbody></table></div>'
                + summaryBox('prSelfSummary', 'Overall comment from the staff member', review.self_summary, can('self') || can('self_on_behalf'))
                + (review.role !== 'subject' || review.is_finalised ? summaryBox('prSupervisorSummary', 'Overall comment from the supervisor', review.supervisor_summary, can('supervisor')) : '')
                + summaryBox('prFinalComment', review.role === 'hr' ? 'HR final comment' : 'HR comment', review.is_finalised || review.role === 'hr' ? (review.final ? review.final.comment : review.final_comment) : null, can('hr') || can('correct'))
                + '<div class="d-flex flex-wrap gap-2" id="prButtons">' + buttons() + '</div></div></div>'
                + stagesHtml() + metricsHtml() + (review.role === 'hr' ? historyHtml() : '');

            $('#prRoot').html(html);
            ['self', 'supervisor', 'hr'].forEach(function(who) { if (editable(who)) recompute(who); });
        }

        function collect(who) {
            const ratings = {};
            $('#prCriteria tbody tr[data-item]').each(function() {
                const id = $(this).attr('data-item');
                ratings[id] = {
                    rating: $(this).find('.pr-rating[data-who="' + who + '"]').val() || '',
                    comment: $(this).find('.pr-comment[data-who="' + who + '"]').val() || ''
                };
            });
            return ratings;
        }

        function api(path, data) {
            return $.ajax({ url: base + path, type: 'POST', data: data, dataType: 'json', headers: { 'X-CSRF-TOKEN': csrf } });
        }

        function message(xhr, fallback) {
            const json = xhr.responseJSON || {};
            if (json.errors) return Object.values(json.errors).flat().join(' ');
            return json.error || json.message || fallback;
        }

        function done(res, text) {
            review = res.review;
            review.back_url = backUrl;
            render();
            toastr.success(text);
        }

        function send(path, data, text) {
            $('#prButtons .btn').prop('disabled', true);
            return api(path, data).done(function(res) { done(res, text); }).fail(function(xhr) {
                $('#prButtons .btn').prop('disabled', false);
                toastr.error(message(xhr, 'This could not be saved.'));
            });
        }

        function askReason(title, label, callback, pickSupervisor) {
            $('#prReasonTitle').text(title);
            $('#prReasonLabel').html(label + ' <span class="text-danger">*</span>');
            $('#prReasonText').val('').removeClass('is-invalid');
            $('#prSupervisorPick').toggle(!!pickSupervisor);
            if (pickSupervisor) {
                const options = ['<option value="">HR takes over and rates it</option>'];
                (review.supervisor_candidates || []).forEach(function(candidate) {
                    options.push('<option value="' + candidate.id + '"' + (candidate.has_login ? '' : ' disabled') + '>' + esc(candidate.name) + (candidate.has_login ? '' : ' (no login)') + '</option>');
                });
                $('#prSupervisorSelect').html(options.join(''));
            }
            onReason = callback;
            reasonModal.show();
        }

        $('#prReasonForm').on('submit', function(event) {
            event.preventDefault();
            const reason = $.trim($('#prReasonText').val());
            if (!reason) { $('#prReasonText').addClass('is-invalid'); return; }
            reasonModal.hide();
            if (onReason) onReason(reason, $('#prSupervisorSelect').val());
        });

        // The live score for the column being edited, from the ratings currently chosen in the boxes
        function recompute(who) {
            const items = review.items.map(function(item) {
                const copy = { weight: item.weight };
                copy[who + '_rating'] = $('#prCriteria tr[data-item="' + item.id + '"] .pr-rating[data-who="' + who + '"]').val() || null;
                return copy;
            });
            const label = who === 'self' ? 'Self score' : (who === 'supervisor' ? 'Supervisor score' : 'HR score so far');
            $('.pr-score-value[data-score="' + label + '"]').text(PR.formatScore(PR.scoreOf(items, who)));
        }

        $(document).on('change', '#prCriteria .pr-rating', function() { recompute($(this).attr('data-who')); });

        $(document).on('click', '.pr-act', function() {
            const act = $(this).attr('data-act');

            if (act === 'save' || act === 'submit') {
                const submit = act === 'submit' ? 1 : 0;
                const who = can('supervisor') ? 'supervisor' : (can('hr') ? 'hr' : 'self');
                if (submit && !confirm('Submit now? You cannot change it afterwards.')) return;
                if (who === 'self') {
                    return send(can('self_on_behalf') ? '/self-on-behalf' : '/self', { ratings: collect('self'), summary: $('#prSelfSummary').val(), submit: submit }, submit ? 'Self-rating submitted.' : 'Draft saved.');
                }
                if (who === 'supervisor') {
                    return send('/supervisor', { ratings: collect('supervisor'), summary: $('#prSupervisorSummary').val(), submit: submit }, submit ? 'Your rating was submitted to HR.' : 'Draft saved.');
                }
            }
            if (act === 'save' && can('hr')) {
                return send('/hr', { ratings: collect('hr'), comment: $('#prFinalComment').val(), finalise: 0 }, 'Draft saved.');
            }
            if (act === 'finalise') {
                if (!confirm('Finalise this review? The staff member will see the supervisor\'s rating and the final rating.')) return;
                return send('/hr', { ratings: collect('hr'), comment: $('#prFinalComment').val(), finalise: 1 }, 'The review is finalised.');
            }
            if (act === 'correct') {
                return askReason('Correct the final rating', 'Why is it being corrected?', function(reason) {
                    send('/correct', { ratings: collect('hr'), comment: $('#prFinalComment').val(), reason: reason }, 'The correction was saved.');
                });
            }
            if (act === 'return') {
                return askReason('Send back to the supervisor', 'What should the supervisor look at again?', function(reason) {
                    send('/return', { reason: reason }, 'The review was sent back to the supervisor.');
                });
            }
            if (act === 'skip') {
                return askReason('Skip the self-rating', 'Why is the self-rating being skipped?', function(reason) {
                    send('/skip-self', { reason: reason }, 'The self-rating was skipped.');
                });
            }
            if (act === 'reassign') {
                return askReason('Change the supervisor', 'Why is the supervisor changing?', function(reason, supervisorId) {
                    send('/reassign', { supervisor_staff_id: supervisorId, reason: reason }, 'The supervisor was changed.');
                }, true);
            }
        });

        const backUrl = {
            subject: "{{ route('performance.my') }}",
            supervisor: "{{ route('performance.team') }}",
            hr: "{{ route('performance.all') }}"
        }[review.role];
        review.back_url = backUrl;
        render();
    });
</script>
@endsection
