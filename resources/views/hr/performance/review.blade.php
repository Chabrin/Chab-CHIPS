@extends('layouts.main')

@section('styles')
<style>
    /* Performance review form. Scoped to .pr-* so nothing else on the site changes. */
    .pr-hint { font-size: .8125rem; color: #6b7a8c; }
    .pr-meta { display: flex; flex-wrap: wrap; gap: .25rem 1.5rem; font-size: .875rem; color: #44536a; }
    .pr-meta strong { color: #1f2d3d; }
    .pr-scores { display: flex; flex-wrap: wrap; gap: .75rem; }
    .pr-score { border: 1px solid #e3e8ef; border-radius: .5rem; padding: .5rem .9rem; min-width: 9rem; background: #fafbfd; }
    .pr-score .pr-score-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: #6b7a8c; }
    .pr-score .pr-score-value { font-size: 1.25rem; font-weight: 600; color: #1f2d3d; }
    #prCriteria thead th { background: #f6f8fb; color: #5b6b7c; font-size: .72rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; white-space: nowrap; }
    #prCriteria td { vertical-align: top; }
    #prCriteria .pr-rating { width: 5.5rem; }
    #prCriteria .pr-comment { min-width: 12rem; font-size: .8125rem; }
    .pr-shown { font-weight: 600; font-size: 1.05rem; }
    .pr-shown-comment { font-size: .8125rem; color: #44536a; white-space: pre-wrap; }
    .pr-weight { font-size: .72rem; color: #8a97a8; }
    .pr-metric { border: 1px solid #e3e8ef; border-radius: .5rem; padding: .5rem .75rem; background: #fff; }
    .pr-metric .pr-metric-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: #6b7a8c; }
    .pr-metric .pr-metric-value { font-weight: 600; }
    .pr-flag-good { border-left: 4px solid #2e9e5b; } .pr-flag-watch { border-left: 4px solid #e0a100; } .pr-flag-concern { border-left: 4px solid #d6433b; } .pr-flag-none { border-left: 4px solid #c9d1dc; }
    .pr-history li { font-size: .8125rem; margin-bottom: .35rem; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div id="prRoot"></div>
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
<script>
    $(function() {
        const PR = window.PerformanceReview;
        const esc = PR.escapeHtml;
        const csrf = $('meta[name="csrf-token"]').attr('content');
        let review = @json($review);
        const base = "{{ url('/ajax/hr/performance/reviews') }}/" + review.id;
        const reasonModal = new bootstrap.Modal(document.getElementById('prReasonModal'));
        let onReason = null;

        const COLUMN_TITLES = { self: 'Self-rating', supervisor: 'Supervisor\'s rating', hr: 'HR final rating' };

        function can(action) { return review.actions.indexOf(action) !== -1; }

        function columns() {
            if (review.role === 'subject') return review.is_finalised ? ['self', 'hr'] : ['self'];
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
            return '<div class="pr-score"><div class="pr-score-label">' + esc(label) + '</div><div class="pr-score-value" data-score="' + esc(label) + '">' + esc(PR.formatScore(score)) + '</div></div>';
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

        function historyHtml() {
            if (!review.history || !review.history.length) return '';
            const LABELS = { created: 'Review created', self_saved: 'Self-rating saved', self_submitted: 'Self-rating submitted', self_skipped: 'Self-rating skipped', supervisor_saved: 'Supervisor rating saved', supervisor_submitted: 'Supervisor rating submitted', hr_saved: 'HR rating saved', finalised: 'Finalised', returned_to_supervisor: 'Sent back to the supervisor', supervisor_changed: 'Supervisor changed', corrected: 'Final rating corrected' };
            return '<div class="card mb-3"><div class="card-body"><h6>History</h6><ul class="list-unstyled pr-history mb-0">'
                + review.history.map(function(event) {
                    return '<li><strong>' + esc(LABELS[event.action] || event.action) + '</strong> '
                        + '<span class="text-muted">' + esc(event.at) + (event.by ? ' by ' + esc(event.by) : '') + (event.on_behalf_of ? ' on behalf of ' + esc(event.on_behalf_of) : '') + '</span>'
                        + (event.note ? '<div class="text-muted">' + esc(event.note) + '</div>' : '') + '</li>';
                }).join('') + '</ul></div></div>';
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
            let meta = '<div class="pr-meta"><span><strong>' + esc(review.staff.name) + '</strong></span><span>' + esc(review.staff.position || '') + '</span>'
                + '<span>' + esc(review.cycle.name) + ' (' + esc(review.cycle.period) + ')</span>'
                + (review.cycle.deadline ? '<span>Deadline <strong>' + esc(review.cycle.deadline) + '</strong></span>' : '') + '</div>';
            if (review.role !== 'subject') {
                meta += '<div class="pr-meta mt-1"><span>Supervisor: <strong>' + esc(review.supervisor || (review.route === 'hr' ? 'none, HR rates' : '-')) + '</strong></span>'
                    + (review.route_reason ? '<span class="text-muted">' + esc(review.route_reason) + '</span>' : '') + '</div>';
            }

            // A score box is always there for the column being edited, so the person sees it change as they rate
            let scores = '';
            if (review.is_finalised && review.final) {
                scores += scoreBox('Final score', review.final.score);
            }
            if (cols.indexOf('self') !== -1 && (review.scores.self != null || editable('self'))) scores += scoreBox('Self score', review.scores.self);
            if (review.role !== 'subject' && (review.scores.supervisor != null || editable('supervisor'))) scores += scoreBox('Supervisor score', review.scores.supervisor);
            if (review.role === 'hr' && !review.is_finalised && (review.scores.hr != null || editable('hr'))) scores += scoreBox('HR score so far', review.scores.hr);

            let head = '<th>Criterion</th>' + cols.map(function(who) { return '<th>' + esc(columnTitle(who)) + (editable(who) ? ' <span class="pr-weight">(1 to ' + PR.MAX + ')</span>' : '') + '</th>'; }).join('');
            let rows = review.items.map(function(item) {
                return '<tr data-item="' + item.id + '"><td><div class="fw-semibold">' + esc(item.name) + '</div>'
                    + (item.description ? '<div class="pr-hint">' + esc(item.description) + '</div>' : '')
                    + '<div class="pr-weight">Weight ' + esc(item.weight) + '</div></td>'
                    + cols.map(function(who) { return '<td>' + cell(item, who) + '</td>'; }).join('') + '</tr>';
            }).join('');
            if (!rows) rows = '<tr><td colspan="' + (cols.length + 1) + '" class="text-center text-muted">This review has no criteria.</td></tr>';

            const status = review.is_finalised ? '' : '<div class="pr-hint mb-2">' + esc(review.status_label) + '.</div>';
            let notes = '';
            if (review.self_skipped) notes += '<div class="alert alert-secondary py-2">The self-rating was skipped by HR.</div>';
            if (review.role === 'subject' && !review.is_finalised) notes += '<div class="pr-hint mb-2">You see your own ratings here. The final rating appears once HR finalises the review.</div>';

            const html = '<div class="card mb-3"><div class="card-header card-no-border d-flex flex-wrap gap-2 align-items-start py-3">'
                + '<div class="me-auto"><h5 class="mb-1">Performance review</h5>' + meta + '</div>'
                + '<div class="d-flex gap-2 align-items-center">' + PR.statusBadge(review.status, review.status_label)
                + '<a class="btn btn-light btn-sm" href="' + esc(review.back_url) + '">Back to the list</a></div></div>'
                + '<div class="card-body pt-0">' + notes + (scores ? '<div class="pr-scores mb-3">' + scores + '</div>' : '') + status
                + '<div class="table-responsive"><table class="table align-middle" id="prCriteria"><thead><tr>' + head + '</tr></thead><tbody>' + rows + '</tbody></table></div>'
                + summaryBox('prSelfSummary', 'Overall comment from the staff member', review.self_summary, can('self') || can('self_on_behalf'))
                + (review.role !== 'subject' ? summaryBox('prSupervisorSummary', 'Overall comment from the supervisor', review.supervisor_summary, can('supervisor')) : '')
                + summaryBox('prFinalComment', review.role === 'hr' ? 'HR final comment' : 'HR comment', review.is_finalised || review.role === 'hr' ? (review.final ? review.final.comment : review.final_comment) : null, can('hr') || can('correct'))
                + '<div class="d-flex flex-wrap gap-2" id="prButtons">' + buttons() + '</div></div></div>'
                + metricsHtml() + (review.role === 'hr' ? historyHtml() : '');

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
                if (!confirm('Finalise this review? The staff member will see the final rating.')) return;
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
