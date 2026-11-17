import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/performance-review.js', import.meta.url), 'utf8');
const root = {};
vm.runInNewContext(source, { window: root });
const PR = root.PerformanceReview;

test('text is escaped, quotes included, because names and comments also go into attributes', () => {
    assert.equal(PR.escapeHtml('<b>"A" & \'B\'</b>'), '&lt;b&gt;&quot;A&quot; &amp; &#39;B&#39;&lt;/b&gt;');
    assert.equal(PR.escapeHtml(null), '');
    assert.equal(PR.escapeHtml(0), '0');
});

test('the score is the weighted average of the ratings, and an unrated criterion is left out, not counted as zero', () => {
    assert.equal(PR.scoreOf([{ weight: 3, hr_rating: 8 }, { weight: 1, hr_rating: 4 }], 'hr'), 7);
    assert.equal(PR.scoreOf([{ weight: 1, self_rating: 8 }, { weight: 1, self_rating: 4 }], 'self'), 6);
    assert.equal(PR.scoreOf([{ weight: 1, hr_rating: 8 }, { weight: 1, hr_rating: null }], 'hr'), 8);
    assert.equal(PR.scoreOf([{ weight: 1, hr_rating: '' }], 'hr'), null);
    assert.equal(PR.scoreOf([], 'hr'), null);
    assert.equal(PR.scoreOf(null, 'hr'), null);
});

test('the score rounds to two places like the server does', () => {
    assert.equal(PR.scoreOf([{ weight: 1, hr_rating: 7 }, { weight: 1, hr_rating: 8 }, { weight: 1, hr_rating: 8 }], 'hr'), 7.67);
});

test('a missing weight counts as 1 and a weight of 0 or less is ignored', () => {
    assert.equal(PR.scoreOf([{ self_rating: 4 }, { self_rating: 8 }], 'self'), 6);
    assert.equal(PR.scoreOf([{ weight: 0, self_rating: 1 }, { weight: 2, self_rating: 9 }], 'self'), 9);
});

test('a review is complete only when every criterion has a rating', () => {
    assert.equal(PR.isComplete([{ self_rating: 5 }, { self_rating: 9 }], 'self'), true);
    assert.equal(PR.isComplete([{ self_rating: 5 }, { self_rating: null }], 'self'), false);
    assert.equal(PR.isComplete([{ self_rating: 5 }, {}], 'self'), false);
    assert.equal(PR.isComplete([], 'self'), false);
});

test('a score is shown out of 10 with no needless decimals, or as a dash when there is none', () => {
    assert.equal(PR.formatScore(7), '7 / 10');
    assert.equal(PR.formatScore(7.5), '7.5 / 10');
    assert.equal(PR.formatScore(7.67), '7.67 / 10');
    assert.equal(PR.formatScore(10), '10 / 10');
    assert.equal(PR.formatScore(null), '-');
    assert.equal(PR.formatScore(undefined), '-');
});

test('the rating box offers 1 to 10 and keeps the chosen one', () => {
    const html = PR.ratingSelect('self', 12, 7, false);
    assert.match(html, /^<select class="form-select form-select-sm pr-rating" data-who="self" data-item="12" aria-label="Rating from 1 to 10">/);
    assert.equal((html.match(/<option/g) || []).length, 11, 'blank plus ten');
    assert.match(html, /<option value="7" selected>7<\/option>/);
    assert.equal((html.match(/ selected/g) || []).length, 1);
    assert.match(PR.ratingSelect('hr', 1, null, false), /<option value="">-<\/option>/);
    assert.doesNotMatch(PR.ratingSelect('hr', 1, null, false), / selected/);
    assert.match(PR.ratingSelect('hr', 1, 3, true), / disabled /);
});

test('the rating box cannot be broken by odd values in its attributes', () => {
    assert.doesNotMatch(PR.ratingSelect('"><script>', '"><img>', 5, false), /<script>|<img>/);
});

test('each status has its own badge colour and unknown ones still show', () => {
    assert.match(PR.statusBadge('finalised', 'Finalised'), /pr-chip is-green/);
    assert.match(PR.statusBadge('awaiting_hr', 'Waiting for HR'), /pr-chip is-teal/);
    assert.match(PR.statusBadge('awaiting_self', 'Waiting for self-rating'), /pr-chip is-amber/);
    assert.match(PR.statusBadge('awaiting_meeting', 'Waiting for the joint review meeting'), /pr-chip is-violet/);
    assert.match(PR.statusBadge('awaiting_supervisor', 'Waiting for supervisor'), /pr-chip is-blue/);
    assert.match(PR.statusBadge('mystery', 'Odd'), /pr-chip is-grey.*Odd/);
    assert.match(PR.statusBadge('finalised', '<b>x</b>'), /&lt;b&gt;x&lt;\/b&gt;/);
});

const row = { id: 5, cycle: 'Q1 2027', staff: 'Felix Officer', position: 'Field Officer', supervisor: 'Zane', route: 'supervisor', status: 'awaiting_hr', status_label: 'Waiting for HR', final_score: null, deadline: '30 Apr 2027' };

test('the my-review row has no staff column, and the all-reviews row has a supervisor column', () => {
    const mine = PR.listRowHtml(row, 'my', '/r/5');
    assert.doesNotMatch(mine, /Felix Officer/);
    assert.equal((mine.match(/<td/g) || []).length, 5);
    const all = PR.listRowHtml(row, 'all', '/r/5');
    assert.match(all, /Felix Officer/);
    assert.match(all, /<td>Zane<\/td>/);
    assert.equal((all.match(/<td/g) || []).length, 7);
    const team = PR.listRowHtml(row, 'team', '/r/5');
    assert.equal((team.match(/<td/g) || []).length, 6);
    assert.doesNotMatch(team, /Zane/);
});

test('a review that goes to HR says HR rates, and a finalised one shows its score', () => {
    assert.match(PR.listRowHtml({ ...row, supervisor: null, route: 'hr' }, 'all', '/r/5'), /HR rates/);
    assert.match(PR.listRowHtml({ ...row, status: 'finalised', status_label: 'Finalised', final_score: 8 }, 'my', '/r/5'), /8 \/ 10/);
    assert.match(PR.listRowHtml(row, 'my', '/r/5'), /<span class="text-muted">-<\/span>/);
});

test('names in a row are escaped and the link is the one given', () => {
    const html = PR.listRowHtml({ ...row, staff: '<img src=x>' }, 'all', '/r/5?a="b"');
    assert.doesNotMatch(html, /<img src=x>/);
    assert.match(html, /href="\/r\/5\?a=&quot;b&quot;"/);
});

test('an empty list says why, across the right number of columns', () => {
    assert.match(PR.listBodyHtml([], 'my', () => '', 'Nothing yet'), /colspan="5".*Nothing yet/);
    assert.match(PR.listBodyHtml([], 'team', () => '', 'x'), /colspan="6"/);
    assert.match(PR.listBodyHtml([], 'all', () => '', 'x'), /colspan="7"/);
    assert.match(PR.listBodyHtml(null, 'all', () => '', '<b>'), /&lt;b&gt;/);
});

test('the list body has a row for each review and links each to its own page', () => {
    const html = PR.listBodyHtml([row, { ...row, id: 6 }], 'all', (r) => '/reviews/' + r.id, 'x');
    assert.equal((html.match(/<tr data-id/g) || []).length, 2);
    assert.match(html, /href="\/reviews\/5"/);
    assert.match(html, /href="\/reviews\/6"/);
});
