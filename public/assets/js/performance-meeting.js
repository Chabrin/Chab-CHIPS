// HR > Performance Review: the parts of the pages that are plain logic and drawing (the page header, the process tracker, what
// happens next, what was discussed, who is invited and who came, the schedule form, the lists). Kept apart from the pages so
// they can be tested. All the rules (who may do what, who must attend) are decided on the server; this only draws what the
// server sent, and shows the attendance rule as it is met so nobody finds out only after pressing save.
(function (root) {
    'use strict';

    // Quotes are escaped too, because names and notes are also written into attributes
    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }

    // Material Symbols are already loaded by the layout; the name is a fixed word, never user text
    function icon(name) {
        return '<span class="material-symbols-outlined" aria-hidden="true">' + esc(name) + '</span>';
    }

    function initials(name) {
        var parts = String(name == null ? '' : name).trim().split(/\s+/).filter(Boolean);
        if (!parts.length) { return '?'; }
        return (parts[0].charAt(0) + (parts.length > 1 ? parts[parts.length - 1].charAt(0) : '')).toUpperCase();
    }

    var TONES = {
        awaiting_self: 'amber', awaiting_meeting: 'violet', awaiting_supervisor: 'blue', awaiting_hr: 'teal', finalised: 'green',
        to_schedule: 'amber', scheduled: 'blue', held: 'green', filed: 'amber', hearing_scheduled: 'blue', decided: 'green'
    };

    // A status as a readable chip: its colour comes from the status, unknown ones are grey
    function badge(status, label) {
        return '<span class="pr-chip is-' + (TONES[status] || 'grey') + '">' + esc(label || status) + '</span>';
    }

    // ── the header of a page ──────────────────────────────────────────

    // head: { eyebrow, title, meta: [{ icon, text }], status: { status, label }, back: { url, text } }
    function headHtml(head) {
        var meta = (head.meta || []).filter(function (item) { return item && item.text; }).map(function (item) {
            return '<span>' + (item.icon ? icon(item.icon) : '') + esc(item.text) + '</span>';
        }).join('');
        var back = head.back && head.back.url ? '<a class="btn btn-light btn-sm" href="' + esc(head.back.url) + '">' + icon('arrow_back') + ' ' + esc(head.back.text || 'Back') + '</a>' : '';
        return '<div class="pr-head"><div class="pr-head-main">'
            + (head.eyebrow ? '<div class="pr-eyebrow">' + esc(head.eyebrow) + '</div>' : '')
            + '<h1 class="pr-title">' + esc(head.title) + '</h1>'
            + (meta ? '<div class="pr-meta">' + meta + '</div>' : '')
            + '</div><div class="pr-head-side">' + (head.status ? badge(head.status.status, head.status.label) : '') + back + '</div></div>';
    }

    // ── where the review is in HR's process ───────────────────────────

    var ORDER = { awaiting_self: 1, awaiting_meeting: 2, awaiting_supervisor: 3, awaiting_hr: 4, finalised: 5 };

    // The five stages. ctx: { status, route, meeting: { status, when } | null, appeal: { status, outcome_label, hearing } | null }
    function stepsFor(ctx) {
        var at = ORDER[ctx.status] || 1;
        var hr = ctx.route === 'hr';
        var state = function (n) { return n < at ? 'done' : (n === at ? 'current' : 'upcoming'); };

        var meetingSub = '';
        if (ctx.meeting) { meetingSub = ctx.meeting.status === 'held' ? 'Held' : (ctx.meeting.when || 'Scheduled'); }
        else if (at === 2) { meetingSub = 'To be scheduled'; }

        var appeal = ctx.appeal || null;
        var appealState = 'upcoming';
        var appealSub = 'Optional';
        if (at === 5) {
            appealState = 'current';
            if (appeal) {
                appealState = appeal.status === 'decided' ? 'done' : 'current';
                appealSub = appeal.status === 'decided' ? (appeal.outcome_label || 'Decided') : (appeal.hearing ? 'Hearing scheduled' : 'Filed');
            }
        }

        return [
            { key: 'self', label: 'Self-rating', state: state(1), sub: '' },
            { key: 'meeting', label: 'Joint review meeting', state: state(2), sub: meetingSub },
            { key: 'supervisor', label: 'Supervisor', state: hr ? 'skipped' : state(3), sub: hr ? 'HR rates' : '' },
            { key: 'hr', label: 'HR final review', state: state(4), sub: '' },
            { key: 'appeal', label: 'Appeal', state: appealState, sub: appealSub }
        ];
    }

    function stepperHtml(steps) {
        return '<ol class="pr-steps" aria-label="Where this review is in the process">' + steps.map(function (step, index) {
            var mark = step.state === 'done' ? icon('check') : (step.state === 'skipped' ? icon('remove') : esc(index + 1));
            return '<li class="pr-step is-' + esc(step.state) + '"' + (step.state === 'current' ? ' aria-current="step"' : '') + '>'
                + '<span class="pr-dot">' + mark + '</span><span class="pr-step-label">' + esc(step.label) + '</span>'
                + '<span class="pr-step-sub">' + esc(step.sub) + '</span></li>';
        }).join('') + '</ol>';
    }

    // ── what happens next, in words, for whoever is looking ───────────

    function can(actions, name) { return (actions || []).indexOf(name) !== -1; }

    function note(tone, iconName, title, text, action) { return { tone: tone, icon: iconName, title: title, text: text, action: action || null }; }

    function whenAt(meeting) { return meeting && meeting.when ? meeting.when + (meeting.venue ? ', ' + meeting.venue : '') : ''; }

    // The review page: role is subject, supervisor, hr or committee. urls ({ meeting, appeal }) are where the buttons go.
    function nextForReview(review, urls) {
        var role = review.role, status = review.status, stages = review.stages || {}, actions = review.actions || [];
        var meeting = stages.meeting, appeal = stages.appeal, canAppeal = stages.can || {};
        var go = function (text, key) { return urls && urls[key] ? { text: text, url: urls[key] } : null; };

        if (role === 'subject') {
            if (status === 'awaiting_self') { return note('action', 'edit_note', 'Rate yourself', 'Give yourself a rating on each criterion below and submit. Nobody sees a draft.'); }
            if (status === 'awaiting_meeting') {
                return meeting
                    ? note('info', 'event', 'Your joint review meeting is on ' + meeting.when, 'At ' + (meeting.venue || 'the venue HR gave') + ', with your supervisor, HR and the review committee. You will talk about skill gaps, training needs, how you did and your new goals.', go('Open the meeting', 'meeting'))
                    : note('info', 'schedule', 'HR will schedule your joint review meeting', 'You will be told in the app as soon as it is booked.');
            }
            if (status === 'awaiting_supervisor') { return note('info', 'hourglass_top', 'Your supervisor is completing your review', 'After the joint review meeting your supervisor rates you and sends the review to HR.'); }
            if (status === 'awaiting_hr') { return note('info', 'hourglass_top', 'HR is finalising your review', 'You will be told in the app when it is final.'); }
            if (status === 'finalised') {
                if (appeal && appeal.status === 'decided') { return note('done', 'gavel', 'The committee has decided your appeal' + (appeal.outcome_label ? ': ' + appeal.outcome_label : ''), 'Its decision is final. The reasons are on the appeal page.', go('Read the decision', 'appeal')); }
                if (appeal) { return note('info', 'gavel', 'Your appeal is with the review committee', appeal.status_label === 'Hearing scheduled' ? 'Your hearing has been scheduled. Open the appeal to see when.' : 'HR will schedule the hearing and tell you in the app.', go('Open the appeal', 'appeal')); }
                if (canAppeal.file_appeal) { return note('done', 'verified', 'Your review is final', 'If you do not accept it you can appeal. The review committee hears your appeal and its decision is final.', go('Appeal this review', 'appeal')); }
                return note('done', 'verified', 'Your review is final', '');
            }
        }

        if (role === 'supervisor') {
            if (status === 'awaiting_self') { return note('info', 'hourglass_top', 'Waiting for the self-rating', 'You will be able to act once the staff member has rated themselves and the joint review meeting is held.'); }
            if (status === 'awaiting_meeting') {
                if (can(actions, 'record_meeting')) { return note('action', 'edit_note', 'Record the joint review meeting', 'Once the meeting has been held, record who came and what was discussed. Then you can give your rating.', go('Record the meeting', 'meeting')); }
                return meeting
                    ? note('info', 'event', 'Joint review meeting on ' + meeting.when, 'You attend as the supervisor and record it afterwards.', go('Open the meeting', 'meeting'))
                    : note('info', 'schedule', 'Waiting for HR to schedule the joint review meeting', 'You will be told in the app.');
            }
            if (status === 'awaiting_supervisor') {
                return can(actions, 'supervisor')
                    ? note('action', 'rate_review', 'Give your rating', 'Rate each criterion, add your remarks and send the review to HR.')
                    : note('info', 'hourglass_top', 'Waiting for the supervisor', '');
            }
            if (status === 'awaiting_hr') { return note('info', 'hourglass_top', 'With HR for the final review', ''); }
            if (status === 'finalised') {
                if (appeal && appeal.status !== 'decided') { return note('info', 'gavel', 'This review has been appealed', 'You will be invited to the hearing. Open the appeal to read what is disputed.', go('Open the appeal', 'appeal')); }
                return note('done', 'verified', 'This review is final', '');
            }
        }

        if (role === 'hr') {
            if (status === 'awaiting_self') { return note('info', 'hourglass_top', 'Waiting for the self-rating', 'You can skip it or enter it for the staff member if they cannot.'); }
            if (status === 'awaiting_meeting') {
                if (can(actions, 'record_meeting')) { return note('action', 'edit_note', 'Record the joint review meeting', 'There is no supervisor, so HR records it once it has been held.', go('Record the meeting', 'meeting')); }
                return meeting
                    ? note('info', 'event', 'Meeting scheduled for ' + meeting.when, 'It is recorded by the supervisor after it has been held. If someone cannot come, reschedule it.', go('Open the meeting', 'meeting'))
                    : note('action', 'event_available', 'Schedule the joint review meeting', 'Pick the date, venue, HR person and committee. Everyone invited is told in the app.', go('Schedule the meeting', 'meeting'));
            }
            if (status === 'awaiting_supervisor') { return note('info', 'hourglass_top', 'Waiting for the supervisor', 'The supervisor rates after recording the joint review meeting.'); }
            if (status === 'awaiting_hr') { return can(actions, 'hr') ? note('action', 'rate_review', 'Give the final rating', 'Start from the supervisor rating and change it where you disagree.') : note('info', 'hourglass_top', 'Waiting for another HR person', 'You supervised this review, so a different HR person gives the final rating.'); }
            if (status === 'finalised') {
                if (appeal && appeal.status === 'decided') { return note('done', 'gavel', 'The appeal was decided' + (appeal.outcome_label ? ': ' + appeal.outcome_label : ''), ''); }
                if (appeal) { return note('action', 'gavel', appeal.status_label === 'Hearing scheduled' ? 'Appeal hearing scheduled' : 'An appeal needs a hearing', 'Open the appeal to ' + (appeal.status_label === 'Hearing scheduled' ? 'record the hearing and the decision.' : 'schedule the hearing with the committee.'), go(appeal.status_label === 'Hearing scheduled' ? 'Open the appeal' : 'Schedule the hearing', 'appeal')); }
                return note('done', 'verified', 'This review is final', '');
            }
        }

        if (role === 'committee') {
            var what = appeal ? 'the appeal hearing' : 'the joint review meeting';
            return note('info', 'groups', 'You are on the committee for ' + what, meeting ? 'It is on ' + whenAt(meeting) + '. Open it to see who is invited.' : 'Open it to see who is invited.', go(appeal ? 'Open the appeal' : 'Open the meeting', appeal ? 'appeal' : 'meeting'));
        }
        return null;
    }

    // The meeting page and the appeal page, from what the server said this person can do
    function nextForPage(page) {
        var a = page.actions || {}, m = page.meeting, appeal = page.appeal;

        if (page.kind === 'review') {
            if (a.schedule) { return note('action', 'event_available', 'Schedule the joint review meeting', 'Pick the date, venue, HR person and committee. Everyone invited is told in the app.'); }
            if (!m) { return null; }
            if (m.status === 'held') { return note('done', 'task_alt', 'The meeting was held', 'It was recorded ' + (m.held_at || '') + '. The review has moved on.'); }
            if (a.record) { return note('action', 'edit_note', 'Record the meeting once it has been held', 'Tick who came and write what was discussed. It can only be recorded when the people it needs were there.'); }
            if (a.reschedule) { return note('info', 'event', 'Meeting on ' + m.scheduled_at, 'It is recorded by the supervisor afterwards. If someone cannot come, reschedule it.'); }
            return note('info', 'event', 'Your meeting is on ' + m.scheduled_at, m.venue ? 'At ' + m.venue + '.' : '');
        }

        if (appeal && appeal.status === 'decided') { return note('done', 'gavel', 'The committee has decided: ' + (appeal.outcome_label || ''), 'The decision is final. The reasons are below.'); }
        if (a.file || a.file_on_behalf) { return note('action', 'gavel', a.file ? 'Appeal this review' : 'Appeal on behalf of the staff member', 'Pick the criteria you dispute and say why. The review committee hears the appeal and its decision is final.'); }
        if (appeal && !m && a.schedule) { return note('action', 'event_available', 'Schedule the appeal hearing', 'Pick the date, venue, HR person and committee. Everyone invited is told in the app.'); }
        if (appeal && m && a.record) { return note('action', 'edit_note', 'Record the hearing and the decision', 'Tick who came, then record what the committee decided and why.'); }
        if (appeal && m) { return note('info', 'event', 'Hearing on ' + m.scheduled_at, m.venue ? 'At ' + m.venue + '.' : ''); }
        if (appeal) { return note('info', 'hourglass_top', 'Waiting for HR to schedule the hearing', 'You will be told in the app.'); }
        return null;
    }

    function nextHtml(next) {
        if (!next) { return ''; }
        return '<div class="pr-next is-' + esc(next.tone) + '" role="status"><span class="pr-next-icon">' + icon(next.icon) + '</span>'
            + '<div class="pr-next-body"><div class="pr-next-title">' + esc(next.title) + '</div>' + (next.text ? '<div class="pr-next-text">' + esc(next.text) + '</div>' : '') + '</div>'
            + (next.action ? '<a class="btn btn-primary btn-sm pr-next-action" href="' + esc(next.action.url) + '">' + esc(next.action.text) + '</a>' : '') + '</div>';
    }

    // ── who is invited, who came, what was discussed ──────────────────

    function attendeesHtml(attendees, held) {
        if (!attendees || !attendees.length) {
            return '<div class="text-muted">Nobody is invited yet.</div>';
        }
        return '<ul class="pm-people">' + attendees.map(function (attendee) {
            var came = '';
            if (held) {
                came = attendee.present
                    ? '<span class="pr-chip is-green">Came</span>'
                    : '<span class="pr-chip is-grey">Did not come</span>';
            }
            return '<li><span class="pr-avatar">' + esc(initials(attendee.name)) + '</span><span><span class="pr-person-name">' + esc(attendee.name) + '</span>'
                + '<span class="pr-person-role">' + esc(attendee.role_label) + '</span></span>' + came + '</li>';
        }).join('') + '</ul>';
    }

    var SECTION_ICONS = { skill_gaps: 'trending_up', training_needs: 'school', performance_feedback: 'rate_review', goals: 'flag', other: 'add_comment' };

    // What was discussed: the four areas and any extra item. Training needs and goals say whether the committee approved them.
    function itemsHtml(items, approvable) {
        if (!items || !items.length) {
            return '<div class="text-muted">Nothing was recorded.</div>';
        }
        return items.map(function (item) {
            var tag = '';
            if (approvable && approvable.indexOf(item.section) !== -1) {
                tag = item.approved
                    ? '<span class="pr-chip is-green">Approved by the committee</span>'
                    : '<span class="pr-chip is-grey">Not approved</span>';
            }
            if (item.section === 'other') {
                tag = '<span class="pr-chip is-violet">Extra item</span>';
            }
            return '<div class="pm-item' + (item.section === 'other' ? ' is-other' : '') + '"><div class="pm-item-title">' + icon(SECTION_ICONS[item.section] || 'notes') + esc(item.title) + tag + '</div>'
                + '<div class="pm-item-notes">' + esc(item.notes) + '</div></div>';
        }).join('');
    }

    // The form HR fills to schedule or move a meeting: date and time, venue, the HR person and the committee.
    function scheduleFormHtml(options, meeting, withReason) {
        options = options || {};
        meeting = meeting || {};
        var chosen = meeting.committee_staff_ids || [];

        var committee = (options.committee || []).map(function (member) {
            var id = 'pmCommittee' + member.staff_id;
            return '<label class="pr-person" for="' + esc(id) + '"><input class="form-check-input pm-committee" type="checkbox" id="' + esc(id) + '" value="' + esc(member.staff_id) + '"'
                + (chosen.indexOf(member.staff_id) !== -1 ? ' checked' : '') + '>'
                + '<span class="pr-avatar">' + esc(initials(member.name)) + '</span><span><span class="pr-person-name">' + esc(member.name) + '</span>'
                + (member.has_login ? '' : '<span class="pr-person-role">No login, cannot be told in the app</span>') + '</span></label>';
        }).join('');
        committee = committee ? '<div class="pm-attendance">' + committee + '</div>' : '<div class="text-muted">Nobody is on the committee list yet. Add members on the Committee page.</div>';

        var hr = '<option value="">Choose the HR person</option>' + (options.hr || []).map(function (person) {
            return '<option value="' + esc(person.user_id) + '"' + (meeting.hr_user_id === person.user_id ? ' selected' : '') + '>' + esc(person.name) + '</option>';
        }).join('');

        var minimum = options.min_committee || 1;
        return '<div class="row g-3">'
            + '<div class="col-md-4"><label class="form-label" for="pmWhen">Date and time <span class="text-danger">*</span></label>'
            + '<input type="datetime-local" class="form-control" id="pmWhen" value="' + esc(meeting.scheduled_input || '') + '"></div>'
            + '<div class="col-md-4"><label class="form-label" for="pmVenue">Venue <span class="text-danger">*</span></label>'
            + '<input type="text" class="form-control" id="pmVenue" maxlength="150" placeholder="For example: Boardroom" value="' + esc(meeting.venue || '') + '"></div>'
            + '<div class="col-md-4"><label class="form-label" for="pmHr">HR person <span class="text-danger">*</span></label>'
            + '<select class="form-select" id="pmHr">' + hr + '</select></div>'
            + '<div class="col-12"><div class="form-label">Committee <span class="text-danger">*</span> <span class="pm-hint">(pick at least ' + esc(minimum) + ')</span></div>' + committee + '</div>'
            + (withReason ? '<div class="col-12"><label class="form-label" for="pmReason">Why is it being rescheduled? <span class="text-danger">*</span></label><textarea class="form-control" id="pmReason" rows="2" maxlength="1000"></textarea></div>' : '')
            + '</div>';
    }

    // Tick who came: one row for each invited person. Nobody is ticked until the person recording says so.
    function attendanceFormHtml(attendees) {
        return '<div class="pm-attendance">' + (attendees || []).map(function (attendee) {
            var id = 'pmCame' + attendee.id;
            return '<label class="pr-person" for="' + esc(id) + '"><input class="form-check-input pm-came" type="checkbox" id="' + esc(id) + '" value="' + esc(attendee.id) + '">'
                + '<span class="pr-avatar">' + esc(initials(attendee.name)) + '</span><span><span class="pr-person-name">' + esc(attendee.name) + '</span>'
                + '<span class="pr-person-role">' + esc(attendee.role_label) + '</span></span></label>';
        }).join('') + '</div>';
    }

    // The attendance rule, as the person ticks: who is still needed. The server applies the same rule when it is saved.
    // ticked: the attendee ids ticked so far (as text or numbers)
    function attendanceRules(attendees, ticked, minCommittee, route) {
        var ids = (ticked || []).map(String);
        var came = function (role) {
            return (attendees || []).filter(function (a) { return a.role === role && ids.indexOf(String(a.id)) !== -1; }).length;
        };
        var invited = function (role) { return (attendees || []).some(function (a) { return a.role === role; }); };
        var minimum = Math.max(1, Number(minCommittee) || 1);
        var rules = [{ label: 'The staff member', ok: came('staff') > 0 }];
        if (invited('supervisor')) { rules.push({ label: 'The supervisor', ok: came('supervisor') > 0 }); }
        rules.push({ label: route === 'hr' ? 'HR (standing in as the supervisor)' : 'The HR person', ok: came('hr') > 0 });
        rules.push({ label: minimum === 1 ? 'At least one committee member' : 'At least ' + minimum + ' committee members', ok: came('committee') >= minimum });
        return rules;
    }

    function requirementsHtml(rules) {
        var met = rules.every(function (rule) { return rule.ok; });
        return '<ul class="pr-reqs" aria-live="polite"><li class="pr-reqs-title" style="list-style:none">' + (met ? 'Everyone needed is here' : 'Still needed before it can be recorded') + '</li>'
            + rules.map(function (rule) {
                return '<li class="pr-req' + (rule.ok ? ' is-ok' : '') + '">' + icon(rule.ok ? 'check_circle' : 'radio_button_unchecked') + esc(rule.label) + '</li>';
            }).join('') + '</ul>';
    }

    // One extra item the recorder adds beyond the four areas
    function extraRowHtml(title, notes) {
        return '<div class="pm-extra border rounded p-2 mb-2">'
            + '<div class="d-flex gap-2 mb-2"><input type="text" class="form-control pm-extra-title" maxlength="150" placeholder="Title, for example: Workload" value="' + esc(title || '') + '" aria-label="Title of the extra item">'
            + '<button type="button" class="btn btn-outline-danger btn-sm pm-extra-remove" aria-label="Remove this extra item">Remove</button></div>'
            + '<textarea class="form-control pm-extra-notes" rows="2" maxlength="4000" placeholder="What was discussed" aria-label="What was discussed under this item">' + esc(notes || '') + '</textarea></div>';
    }

    // ── history, lists, empty and loading states ──────────────────────

    // events: [{ action, at, by, on_behalf_of, note }]; labels: action => words
    function timelineHtml(events, labels) {
        if (!events || !events.length) { return ''; }
        return '<ul class="pr-timeline">' + events.map(function (event) {
            return '<li><strong>' + esc((labels && labels[event.action]) || event.action) + '</strong> '
                + '<span class="pr-when">' + esc(event.at) + (event.by ? ' by ' + esc(event.by) : '') + (event.on_behalf_of ? ' on behalf of ' + esc(event.on_behalf_of) : '') + '</span>'
                + (event.note ? '<div class="pr-note">' + esc(event.note) + '</div>' : '') + '</li>';
        }).join('') + '</ul>';
    }

    function emptyHtml(iconName, title, text) {
        return '<div class="pr-empty">' + icon(iconName) + '<div class="pr-empty-title">' + esc(title) + '</div>' + (text ? '<div>' + esc(text) + '</div>' : '') + '</div>';
    }

    // Placeholder rows while a list loads
    function skeletonRows(columns, rows) {
        var out = '';
        for (var r = 0; r < (rows || 3); r++) {
            out += '<tr>';
            for (var c = 0; c < columns; c++) { out += '<td><span class="pr-skel" style="width:' + (c === 0 ? '70%' : '55%') + '"></span></td>'; }
            out += '</tr>';
        }
        return out;
    }

    // Whether a row matches what was typed in the search box (name, position or cycle)
    function matches(row, query) {
        var q = String(query || '').trim().toLowerCase();
        if (!q) { return true; }
        return [row.staff, row.position, row.cycle, row.kind_label, row.venue].some(function (field) { return String(field == null ? '' : field).toLowerCase().indexOf(q) !== -1; });
    }

    function meetingRowHtml(row) {
        return '<tr><td><span class="fw-semibold">' + esc(row.staff) + '</span><div class="small text-muted">' + esc(row.position) + '</div></td>'
            + '<td>' + esc(row.kind_label) + '<div class="small text-muted">' + esc(row.cycle) + '</div></td>'
            + '<td class="text-nowrap">' + (row.when ? esc(row.when) : '<span class="text-muted">-</span>') + '</td>'
            + '<td>' + (row.venue ? esc(row.venue) : '<span class="text-muted">-</span>') + '</td>'
            + '<td>' + badge(row.status, row.status_label) + '</td>'
            + '<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="' + esc(row.url) + '">Open</a></td></tr>';
    }

    function meetingsBodyHtml(rows, emptyText) {
        if (!rows || !rows.length) {
            return '<tr><td colspan="6">' + emptyHtml('event_busy', 'No meetings', emptyText) + '</td></tr>';
        }
        return rows.map(meetingRowHtml).join('');
    }

    function appealRowHtml(row, url) {
        return '<tr><td><span class="fw-semibold">' + esc(row.staff) + '</span><div class="small text-muted">' + esc(row.position) + '</div></td>'
            + '<td>' + esc(row.cycle) + '</td>'
            + '<td class="text-nowrap">' + esc(row.filed_at) + (row.on_behalf ? '<div class="small text-muted">Filed by HR</div>' : '') + '</td>'
            + '<td class="text-nowrap">' + (row.hearing_at ? esc(row.hearing_at) : '<span class="text-muted">-</span>') + '</td>'
            + '<td>' + badge(row.state, row.state_label) + (row.outcome_label ? '<div class="small mt-1">' + esc(row.outcome_label) + '</div>' : '') + '</td>'
            + '<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="' + esc(url) + '">Open</a></td></tr>';
    }

    function appealsBodyHtml(rows, urlFor, emptyText) {
        if (!rows || !rows.length) {
            return '<tr><td colspan="6">' + emptyHtml('gavel', 'No appeals', emptyText) + '</td></tr>';
        }
        return rows.map(function (row) { return appealRowHtml(row, urlFor(row)); }).join('');
    }

    // Reading the forms back (they need jQuery, which every page here already has)
    function readSchedule($scope) {
        return {
            scheduled_at: $scope.find('#pmWhen').val() || '',
            venue: $scope.find('#pmVenue').val() || '',
            hr_user_id: $scope.find('#pmHr').val() || '',
            committee_staff_ids: $scope.find('.pm-committee:checked').map(function () { return this.value; }).get(),
            reason: $scope.find('#pmReason').val() || ''
        };
    }

    function readAttendance($scope) {
        var attendance = {};
        $scope.find('.pm-came').each(function () { attendance[this.value] = this.checked ? 1 : 0; });
        return attendance;
    }

    function readExtras($scope) {
        return $scope.find('.pm-extra').map(function () {
            return { title: $(this).find('.pm-extra-title').val() || '', notes: $(this).find('.pm-extra-notes').val() || '' };
        }).get();
    }

    function message(xhr, fallback) {
        var json = (xhr && xhr.responseJSON) || {};
        if (json.errors) { return Object.keys(json.errors).map(function (key) { return json.errors[key]; }).join(' '); }
        return json.error || json.message || fallback;
    }

    root.PerformanceMeeting = {
        escapeHtml: esc, icon: icon, initials: initials, badge: badge, headHtml: headHtml, stepsFor: stepsFor, stepperHtml: stepperHtml,
        nextForReview: nextForReview, nextForPage: nextForPage, nextHtml: nextHtml, attendeesHtml: attendeesHtml, itemsHtml: itemsHtml,
        scheduleFormHtml: scheduleFormHtml, attendanceFormHtml: attendanceFormHtml, attendanceRules: attendanceRules, requirementsHtml: requirementsHtml,
        extraRowHtml: extraRowHtml, timelineHtml: timelineHtml, emptyHtml: emptyHtml, skeletonRows: skeletonRows, matches: matches,
        meetingRowHtml: meetingRowHtml, meetingsBodyHtml: meetingsBodyHtml, appealRowHtml: appealRowHtml, appealsBodyHtml: appealsBodyHtml,
        readSchedule: readSchedule, readAttendance: readAttendance, readExtras: readExtras, message: message
    };
})(typeof window !== 'undefined' ? window : this);
