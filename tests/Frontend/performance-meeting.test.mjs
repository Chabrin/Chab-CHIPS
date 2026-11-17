import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/performance-meeting.js', import.meta.url), 'utf8');
const root = {};
vm.runInNewContext(source, { window: root });
const PM = root.PerformanceMeeting;

const states = (steps) => steps.map((step) => `${step.key}:${step.state}`).join(' ');

test('text is escaped, quotes included, because names and notes also go into attributes', () => {
    assert.equal(PM.escapeHtml('<b>"A" & \'B\'</b>'), '&lt;b&gt;&quot;A&quot; &amp; &#39;B&#39;&lt;/b&gt;');
    assert.equal(PM.escapeHtml(null), '');
});

test('initials come from the first and last name, and a missing name is a question mark', () => {
    assert.equal(PM.initials('Felix Wanjiru Officer'), 'FO');
    assert.equal(PM.initials('amos'), 'A');
    assert.equal(PM.initials('  '), '?');
    assert.equal(PM.initials(null), '?');
});

test('a status is a readable chip in its own colour, and an unknown one is grey', () => {
    assert.match(PM.badge('held', 'Held'), /pr-chip is-green">Held</);
    assert.match(PM.badge('to_schedule', 'Not scheduled yet'), /is-amber/);
    assert.match(PM.badge('mystery', 'Odd <b>'), /is-grey">Odd &lt;b&gt;</);
});

test('the page header shows the title, only the facts that exist, the status and the way back', () => {
    const html = PM.headHtml({ eyebrow: 'Appeal', title: 'Felix <Officer>', meta: [{ icon: 'badge', text: 'Field Officer' }, { icon: 'flag', text: '' }, null], status: { status: 'filed', label: 'Waiting for a hearing' }, back: { url: '/r/5', text: 'Back to the review' } });

    assert.ok(html.includes('Felix &lt;Officer&gt;'));
    assert.equal((html.match(/<span>/g) || []).length, 1, 'the empty fact is left out');
    assert.ok(html.includes('Waiting for a hearing') && html.includes('href="/r/5"') && html.includes('Back to the review'));
    assert.ok(!PM.headHtml({ title: 'x' }).includes('btn-light'), 'no back link when none is given');
});

// ── where the review is in the process ──────────────────────────────

test('the tracker follows the review through the five stages', () => {
    assert.equal(states(PM.stepsFor({ status: 'awaiting_self', route: 'supervisor' })), 'self:current meeting:upcoming supervisor:upcoming hr:upcoming appeal:upcoming');
    assert.equal(states(PM.stepsFor({ status: 'awaiting_meeting', route: 'supervisor' })), 'self:done meeting:current supervisor:upcoming hr:upcoming appeal:upcoming');
    assert.equal(states(PM.stepsFor({ status: 'awaiting_supervisor', route: 'supervisor' })), 'self:done meeting:done supervisor:current hr:upcoming appeal:upcoming');
    assert.equal(states(PM.stepsFor({ status: 'awaiting_hr', route: 'supervisor' })), 'self:done meeting:done supervisor:done hr:current appeal:upcoming');
    assert.equal(states(PM.stepsFor({ status: 'finalised', route: 'supervisor' })), 'self:done meeting:done supervisor:done hr:done appeal:current');
});

test('when there is no supervisor the supervisor stage is skipped and says HR rates', () => {
    const steps = PM.stepsFor({ status: 'awaiting_hr', route: 'hr' });

    assert.equal(states(steps), 'self:done meeting:done supervisor:skipped hr:current appeal:upcoming');
    assert.equal(steps[2].sub, 'HR rates');
});

test('the meeting stage says when it is, that it was held, or that it still has to be scheduled', () => {
    assert.equal(PM.stepsFor({ status: 'awaiting_meeting', meeting: null })[1].sub, 'To be scheduled');
    assert.equal(PM.stepsFor({ status: 'awaiting_meeting', meeting: { status: 'scheduled', when: '01 Feb 2027 10:00' } })[1].sub, '01 Feb 2027 10:00');
    assert.equal(PM.stepsFor({ status: 'awaiting_supervisor', meeting: { status: 'held', when: '01 Feb 2027 10:00' } })[1].sub, 'Held');
});

test('the appeal stage is optional until the review is final, then follows the appeal', () => {
    assert.equal(PM.stepsFor({ status: 'awaiting_hr' })[4].sub, 'Optional');
    assert.equal(PM.stepsFor({ status: 'finalised' })[4].sub, 'Optional');

    const filed = PM.stepsFor({ status: 'finalised', appeal: { status: 'filed', hearing: false } })[4];
    assert.deepEqual([filed.state, filed.sub], ['current', 'Filed']);
    const hearing = PM.stepsFor({ status: 'finalised', appeal: { status: 'filed', hearing: true } })[4];
    assert.deepEqual([hearing.state, hearing.sub], ['current', 'Hearing scheduled']);
    const decided = PM.stepsFor({ status: 'finalised', appeal: { status: 'decided', outcome_label: 'Upheld' } })[4];
    assert.deepEqual([decided.state, decided.sub], ['done', 'Upheld']);
});

test('the tracker draws every stage, marks the current one for screen readers and escapes the words', () => {
    const html = PM.stepperHtml([{ key: 'self', label: 'Self-rating', state: 'done', sub: '' }, { key: 'meeting', label: 'Meet <ing>', state: 'current', sub: 'Tomorrow' }, { key: 'x', label: 'Later', state: 'skipped', sub: '' }]);

    assert.equal((html.match(/<li /g) || []).length, 3);
    assert.equal((html.match(/aria-current="step"/g) || []).length, 1);
    assert.ok(html.includes('Meet &lt;ing&gt;') && html.includes('Tomorrow'));
    assert.match(html, /is-done[^>]*><span class="pr-dot"><span class="material-symbols-outlined"[^>]*>check</);
});

// ── what happens next ───────────────────────────────────────────────

const review = (over) => ({ role: 'subject', status: 'awaiting_self', actions: [], stages: {}, ...over });
const meeting = { status: 'scheduled', when: '01 Feb 2027 10:00', venue: 'Boardroom' };

test('the staff member is told what to do at each stage', () => {
    assert.equal(PM.nextForReview(review({})).title, 'Rate yourself');
    assert.equal(PM.nextForReview(review({ status: 'awaiting_meeting' })).title, 'HR will schedule your joint review meeting');
    const booked = PM.nextForReview(review({ status: 'awaiting_meeting', stages: { meeting } }));
    assert.equal(booked.title, 'Your joint review meeting is on 01 Feb 2027 10:00');
    assert.match(booked.text, /Boardroom/);
    assert.equal(PM.nextForReview(review({ status: 'awaiting_supervisor' })).tone, 'info');
    assert.equal(PM.nextForReview(review({ status: 'awaiting_hr' })).title, 'HR is finalising your review');
});

test('a final review tells the staff member they may appeal, then follows their appeal to its decision', () => {
    const final = PM.nextForReview(review({ status: 'finalised', stages: { can: { file_appeal: true } } }));
    assert.equal(final.title, 'Your review is final');
    assert.match(final.text, /you can appeal/);

    const filed = PM.nextForReview(review({ status: 'finalised', stages: { appeal: { status: 'filed', status_label: 'Filed' } } }));
    assert.match(filed.title, /with the review committee/);
    assert.match(PM.nextForReview(review({ status: 'finalised', stages: { appeal: { status: 'filed', status_label: 'Hearing scheduled' } } })).text, /scheduled/);

    const decided = PM.nextForReview(review({ status: 'finalised', stages: { appeal: { status: 'decided', outcome_label: 'Upheld' } } }));
    assert.deepEqual([decided.tone, decided.title], ['done', 'The committee has decided your appeal: Upheld']);
});

test('the supervisor is asked to record the meeting, then to rate', () => {
    const record = PM.nextForReview(review({ role: 'supervisor', status: 'awaiting_meeting', actions: ['record_meeting'], stages: { meeting } }));
    assert.deepEqual([record.tone, record.title], ['action', 'Record the joint review meeting']);

    const waiting = PM.nextForReview(review({ role: 'supervisor', status: 'awaiting_meeting', stages: { meeting } }));
    assert.equal(waiting.title, 'Joint review meeting on 01 Feb 2027 10:00');

    assert.equal(PM.nextForReview(review({ role: 'supervisor', status: 'awaiting_supervisor', actions: ['supervisor'] })).title, 'Give your rating');
    assert.equal(PM.nextForReview(review({ role: 'supervisor', status: 'awaiting_supervisor', actions: [] })).tone, 'info');
});

test('HR is told to schedule, to record when there is no supervisor, to rate and to hold the hearing', () => {
    assert.equal(PM.nextForReview(review({ role: 'hr', status: 'awaiting_meeting', actions: ['schedule_meeting'] })).title, 'Schedule the joint review meeting');
    assert.equal(PM.nextForReview(review({ role: 'hr', status: 'awaiting_meeting', actions: ['schedule_meeting', 'record_meeting'], stages: { meeting } })).title, 'Record the joint review meeting');
    assert.equal(PM.nextForReview(review({ role: 'hr', status: 'awaiting_meeting', actions: ['schedule_meeting'], stages: { meeting } })).title, 'Meeting scheduled for 01 Feb 2027 10:00');
    assert.equal(PM.nextForReview(review({ role: 'hr', status: 'awaiting_hr', actions: ['hr'] })).title, 'Give the final rating');
    assert.equal(PM.nextForReview(review({ role: 'hr', status: 'awaiting_hr', actions: [] })).title, 'Waiting for another HR person');
    const appeal = PM.nextForReview(review({ role: 'hr', status: 'finalised', stages: { appeal: { status: 'filed', status_label: 'Filed' } } }));
    assert.deepEqual([appeal.tone, appeal.title], ['action', 'An appeal needs a hearing']);
});

test('a committee member is told which meeting they sit on', () => {
    const note = PM.nextForReview(review({ role: 'committee', status: 'awaiting_meeting', stages: { meeting } }));

    assert.equal(note.title, 'You are on the committee for the joint review meeting');
    assert.match(note.text, /01 Feb 2027 10:00, Boardroom/);
    assert.equal(PM.nextForReview(review({ role: 'committee', status: 'finalised', stages: { appeal: { status: 'filed' } } })).title, 'You are on the committee for the appeal hearing');
});

test('the meeting and appeal pages say what the person can do on them', () => {
    const page = (over) => ({ kind: 'review', actions: {}, meeting: null, appeal: null, ...over });

    assert.equal(PM.nextForPage(page({ actions: { schedule: true } })).title, 'Schedule the joint review meeting');
    assert.equal(PM.nextForPage(page({ actions: { record: true }, meeting: { status: 'scheduled', scheduled_at: '01 Feb' } })).title, 'Record the meeting once it has been held');
    assert.equal(PM.nextForPage(page({ meeting: { status: 'held', held_at: '01 Feb' } })).tone, 'done');
    assert.equal(PM.nextForPage(page({ meeting: { status: 'scheduled', scheduled_at: '01 Feb 10:00', venue: 'Boardroom' } })).title, 'Your meeting is on 01 Feb 10:00');
    assert.equal(PM.nextForPage(page({ kind: 'appeal', actions: { file: true } })).title, 'Appeal this review');
    assert.equal(PM.nextForPage(page({ kind: 'appeal', actions: { schedule: true }, appeal: { status: 'filed' } })).title, 'Schedule the appeal hearing');
    assert.equal(PM.nextForPage(page({ kind: 'appeal', actions: { record: true }, appeal: { status: 'filed' }, meeting: { status: 'scheduled' } })).title, 'Record the hearing and the decision');
    assert.equal(PM.nextForPage(page({ kind: 'appeal', appeal: { status: 'decided', outcome_label: 'Rejected' } })).tone, 'done');
    assert.equal(PM.nextForPage(page({ kind: 'appeal' })), null);
});

test('the note is drawn with its tone and icon, and nothing when there is none', () => {
    const html = PM.nextHtml({ tone: 'action', icon: 'edit_note', title: 'Do <this>', text: 'Now' });

    assert.match(html, /pr-next is-action/);
    assert.ok(html.includes('Do &lt;this&gt;') && html.includes('edit_note'));
    assert.equal(PM.nextHtml(null), '');
});

// ── the attendance rule, shown as people are ticked ─────────────────

const people = [
    { id: 1, role: 'staff', name: 'Felix' }, { id: 2, role: 'supervisor', name: 'Zane' },
    { id: 3, role: 'hr', name: 'Director' }, { id: 4, role: 'committee', name: 'Zola' }, { id: 5, role: 'committee', name: 'Alan' }
];
// The script runs in its own context, so its arrays are copied before they are compared
const unmet = (rules) => Array.from(rules).filter((rule) => !rule.ok).map((rule) => rule.label);

test('the attendance rule lists who is still needed until the staff member, supervisor, HR and a committee member are ticked', () => {
    assert.deepEqual(unmet(PM.attendanceRules(people, [], 1, 'supervisor')), ['The staff member', 'The supervisor', 'The HR person', 'At least one committee member']);
    assert.deepEqual(unmet(PM.attendanceRules(people, ['1', '3'], 1, 'supervisor')), ['The supervisor', 'At least one committee member']);
    assert.deepEqual(unmet(PM.attendanceRules(people, [1, 2, 3, 5], 1, 'supervisor')), [], 'ids can be numbers or text');
});

test('with no supervisor HR stands in, and the committee minimum can be higher than one', () => {
    const noSupervisor = people.filter((person) => person.role !== 'supervisor');

    assert.deepEqual(Array.from(PM.attendanceRules(noSupervisor, [], 1, 'hr')).map((rule) => rule.label), ['The staff member', 'HR (standing in as the supervisor)', 'At least one committee member']);
    assert.deepEqual(unmet(PM.attendanceRules(people, [1, 2, 3, 4], 2, 'supervisor')), ['At least 2 committee members']);
    assert.deepEqual(unmet(PM.attendanceRules(people, [1, 2, 3, 4, 5], 2, 'supervisor')), []);
    assert.deepEqual(unmet(PM.attendanceRules(people, [1, 2, 3, 4], 0, 'supervisor')), [], 'a minimum below one still means one');
});

test('the rule is shown as ticks and circles, and says when everyone needed is there', () => {
    const waiting = PM.requirementsHtml(PM.attendanceRules(people, ['1'], 1, 'supervisor'));
    assert.match(waiting, /Still needed before it can be recorded/);
    assert.equal((waiting.match(/check_circle/g) || []).length, 1);
    assert.equal((waiting.match(/radio_button_unchecked/g) || []).length, 3);

    const met = PM.requirementsHtml(PM.attendanceRules(people, [1, 2, 3, 4], 1, 'supervisor'));
    assert.match(met, /Everyone needed is here/);
    assert.ok(!met.includes('radio_button_unchecked'));
});

// ── forms, lists and the rest ───────────────────────────────────────

test('what was discussed shows every item, escapes the notes and marks the committee approvals and the extra items', () => {
    const html = PM.itemsHtml([
        { section: 'training_needs', title: 'Training needs', notes: 'Customer care <script>x</script>', approved: true },
        { section: 'goals', title: 'Goals', notes: 'Collect 95%', approved: false },
        { section: 'performance_feedback', title: 'Performance feedback', notes: 'Steady', approved: false },
        { section: 'other', title: 'Workload', notes: 'Two zones', approved: false }
    ], ['training_needs', 'goals']);

    assert.ok(!html.includes('<script>'), 'notes can not inject markup');
    assert.ok(html.includes('&lt;script&gt;'));
    assert.equal((html.match(/Approved by the committee/g) || []).length, 1);
    assert.equal((html.match(/Not approved/g) || []).length, 1, 'only the goals were left unapproved; feedback is not an approval');
    assert.equal((html.match(/Extra item/g) || []).length, 1);
    assert.equal((html.match(/pm-item is-other/g) || []).length, 1);
    assert.match(html, />school</, 'each area has its own icon');
});

test('an empty record says nothing was recorded', () => {
    assert.match(PM.itemsHtml([], []), /Nothing was recorded/);
    assert.match(PM.itemsHtml(null, []), /Nothing was recorded/);
});

test('the attendance list shows who came only once the meeting was held', () => {
    const list = [{ id: 1, name: 'Felix Officer', role_label: 'Staff member', present: true }, { id: 2, name: 'Zane', role_label: 'Supervisor', present: false }];

    const before = PM.attendeesHtml(list, false);
    assert.ok(before.includes('Felix Officer') && before.includes('Staff member') && before.includes('>FO<'));
    assert.ok(!before.includes('Came') && !before.includes('Did not come'));

    const after = PM.attendeesHtml(list, true);
    assert.equal((after.match(/>Came</g) || []).length, 1);
    assert.equal((after.match(/Did not come/g) || []).length, 1);
    assert.match(PM.attendeesHtml([], false), /Nobody is invited yet/);
});

test('the schedule form lists the committee and the HR people, ticks the ones already chosen and says how many to pick', () => {
    const options = { committee: [{ staff_id: 3, name: 'Zola', has_login: true }, { staff_id: 1, name: 'Mary', has_login: false }], hr: [{ user_id: 2000, name: 'Director' }, { user_id: 1060, name: 'Hana' }], min_committee: 2 };
    const meeting = { scheduled_input: '2027-02-01T10:00', venue: 'Room "A"', hr_user_id: 1060, committee_staff_ids: [3] };

    const html = PM.scheduleFormHtml(options, meeting, true);

    assert.ok(html.includes('value="2027-02-01T10:00"'));
    assert.ok(html.includes('value="Room &quot;A&quot;"'), 'the venue is escaped inside the attribute');
    assert.match(html, /<option value="1060" selected>Hana<\/option>/);
    assert.match(html, /id="pmCommittee3"[^>]* checked/);
    assert.doesNotMatch(html, /id="pmCommittee1"[^>]* checked/);
    assert.match(html, /Mary[\s\S]*No login, cannot be told in the app/);
    assert.match(html, /pick at least 2/);
    assert.match(html, /id="pmReason"/, 'a reschedule asks why');
    assert.doesNotMatch(PM.scheduleFormHtml(options, null, false), /id="pmReason"/);
    assert.match(PM.scheduleFormHtml({ committee: [], hr: [] }, null, false), /Nobody is on the committee list yet/);
});

test('the attendance form has a row for each invited person and none ticked', () => {
    const html = PM.attendanceFormHtml([{ id: 7, name: 'Felix', role_label: 'Staff member' }, { id: 8, name: 'Zane', role_label: 'Supervisor' }]);

    assert.equal((html.match(/class="form-check-input pm-came"/g) || []).length, 2);
    assert.ok(!html.includes(' checked'));
    assert.ok(html.includes('value="7"') && html.includes('value="8"'));
});

test('an extra item row has a title, notes and a remove button, and keeps what it is given', () => {
    const html = PM.extraRowHtml('Work "load"', 'Two <zones>');

    assert.ok(html.includes('pm-extra-title') && html.includes('pm-extra-notes') && html.includes('pm-extra-remove'));
    assert.ok(html.includes('value="Work &quot;load&quot;"'));
    assert.ok(html.includes('Two &lt;zones&gt;'));
});

test('the history is a timeline with the words for each action, who did it and any note', () => {
    const html = PM.timelineHtml([{ action: 'meeting_held', at: '01 Feb 2027 10:00', by: 'Mark <M>', on_behalf_of: null, note: null }, { action: 'corrected', at: '02 Feb', by: 'Amos', on_behalf_of: 'Felix', note: 'Typo' }, { action: 'odd_one', at: '03 Feb' }], { meeting_held: 'Joint review meeting held', corrected: 'Final rating corrected' });

    assert.equal((html.match(/<li>/g) || []).length, 3);
    assert.ok(html.includes('Joint review meeting held') && html.includes('Mark &lt;M&gt;'));
    assert.ok(html.includes('on behalf of Felix') && html.includes('Typo'));
    assert.ok(html.includes('odd_one'), 'an action with no words still shows');
    assert.equal(PM.timelineHtml([], {}), '');
});

test('an empty state has an icon, a title and the reason, and a loading state has placeholder rows', () => {
    assert.match(PM.emptyHtml('gavel', 'No <appeals>', 'None filed'), /gavel[\s\S]*No &lt;appeals&gt;[\s\S]*None filed/);
    const rows = PM.skeletonRows(4, 3);
    assert.equal((rows.match(/<tr>/g) || []).length, 3);
    assert.equal((rows.match(/pr-skel/g) || []).length, 12);
});

test('searching matches the name, position, cycle, kind or venue, in any case', () => {
    const row = { staff: 'Felix Officer', position: 'Field Officer', cycle: 'Q1 2027', kind_label: 'Appeal hearing', venue: 'Boardroom' };

    assert.ok(PM.matches(row, ''));
    assert.ok(PM.matches(row, 'felix'));
    assert.ok(PM.matches(row, 'FIELD'));
    assert.ok(PM.matches(row, 'q1'));
    assert.ok(PM.matches(row, 'hearing'));
    assert.ok(PM.matches(row, ' board '));
    assert.ok(!PM.matches(row, 'zane'));
    assert.ok(PM.matches({ staff: 'Only a name' }, 'name'), 'missing fields do not break it');
});

test('the meetings list row links to the right page and says when it is not scheduled yet', () => {
    const row = { review_id: 5, kind_label: 'Joint review meeting', staff: 'Felix <Officer>', position: 'Field Officer', cycle: 'Q1', status: 'to_schedule', status_label: 'Not scheduled yet', when: null, venue: null, url: '/hr/performance/reviews/5/meeting' };

    const html = PM.meetingRowHtml(row);

    assert.ok(html.includes('Felix &lt;Officer&gt;'));
    assert.ok(html.includes('href="/hr/performance/reviews/5/meeting"'));
    assert.ok(html.includes('Not scheduled yet'));
    assert.match(PM.meetingsBodyHtml([], 'Nothing here'), /colspan="6"[\s\S]*Nothing here/);
});

test('the appeals list row shows the hearing, the state and the outcome once decided', () => {
    const html = PM.appealRowHtml({ review_id: 9, staff: 'Felix', position: 'Field Officer', cycle: 'Q1', filed_at: '10 Nov 2026', on_behalf: true, hearing_at: '20 Nov 2026 09:00', state: 'decided', state_label: 'Decided', outcome_label: 'Upheld' }, '/hr/performance/reviews/9/appeal');

    assert.ok(html.includes('Filed by HR'));
    assert.ok(html.includes('20 Nov 2026 09:00'));
    assert.ok(html.includes('Decided') && html.includes('Upheld'));
    assert.ok(html.includes('href="/hr/performance/reviews/9/appeal"'));
    assert.match(PM.appealsBodyHtml([], () => '', 'None'), /No appeals[\s\S]*None/);
});

test('an error message from the server is shown as it was sent', () => {
    assert.equal(PM.message({ responseJSON: { error: 'The staff member must be there.' } }, 'fallback'), 'The staff member must be there.');
    assert.equal(PM.message({ responseJSON: { errors: { a: ['One.'], b: ['Two.'] } } }, 'fallback'), 'One. Two.');
    assert.equal(PM.message({}, 'fallback'), 'fallback');
    assert.equal(PM.message(null, 'fallback'), 'fallback');
});

// ── the buttons in the note ─────────────────────────────────────────

test('the note carries the button for what the person has to do, when the page knows where it goes', () => {
    const urls = { meeting: '/r/5/meeting', appeal: '/r/5/appeal' };
    const action = (note) => (note.action ? [note.action.text, note.action.url] : null);

    assert.deepEqual(action(PM.nextForReview(review({ status: 'finalised', stages: { can: { file_appeal: true } } }), urls)), ['Appeal this review', '/r/5/appeal']);
    assert.deepEqual(action(PM.nextForReview(review({ status: 'awaiting_meeting', stages: { meeting } }), urls)), ['Open the meeting', '/r/5/meeting']);
    assert.deepEqual(action(PM.nextForReview(review({ role: 'supervisor', status: 'awaiting_meeting', actions: ['record_meeting'], stages: { meeting } }), urls)), ['Record the meeting', '/r/5/meeting']);
    assert.deepEqual(action(PM.nextForReview(review({ role: 'hr', status: 'awaiting_meeting', actions: ['schedule_meeting'] }), urls)), ['Schedule the meeting', '/r/5/meeting']);
    assert.deepEqual(action(PM.nextForReview(review({ role: 'hr', status: 'finalised', stages: { appeal: { status: 'filed', status_label: 'Filed' } } }), urls)), ['Schedule the hearing', '/r/5/appeal']);
    assert.deepEqual(action(PM.nextForReview(review({ role: 'hr', status: 'finalised', stages: { appeal: { status: 'filed', status_label: 'Hearing scheduled' } } }), urls)), ['Open the appeal', '/r/5/appeal']);
    assert.deepEqual(action(PM.nextForReview(review({ role: 'committee', status: 'awaiting_meeting', stages: { meeting } }), urls)), ['Open the meeting', '/r/5/meeting']);
});

test('without addresses there is no button, and a note with nothing to do has none either', () => {
    assert.equal(PM.nextForReview(review({ status: 'finalised', stages: { can: { file_appeal: true } } })).action, null);
    assert.equal(PM.nextForReview(review({ status: 'awaiting_hr' }), { meeting: '/m', appeal: '/a' }).action, null);
});

test('the button is drawn as a link in the note, escaped', () => {
    const html = PM.nextHtml({ tone: 'action', icon: 'gavel', title: 'Appeal', text: '', action: { text: 'Go <now>', url: '/a?x=1&y="2"' } });

    assert.match(html, /<a class="btn btn-primary btn-sm pr-next-action" href="\/a\?x=1&amp;y=&quot;2&quot;">Go &lt;now&gt;<\/a>/);
    assert.ok(!PM.nextHtml({ tone: 'info', icon: 'x', title: 'No button', text: '' }).includes('<a '));
});
