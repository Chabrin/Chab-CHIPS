import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const context = { window: {}, Intl, Map, Set };
vm.runInNewContext(readFileSync(new URL('../../public/assets/js/returns-list.js', import.meta.url), 'utf8'), context);
const list = context.window.ReturnsList;
const child = (id, property, key, level) => ({ id, property_code: property, property_codes: [property], status_key: key,
    current_level: level, is_draft: key === 'draft', group: 'individual', stage: key === 'awaiting' ? `Awaiting level ${level} of 3` : key,
    property_name: property, encrypted_id: `encrypted-${id}`, return_code: `RETURN-${id}`, net_amount: '100.00', net_amount_raw: 100, return_type: 'MAINRETURN' });
const merged = { id: 10, group: 'merged', return_code: 'MASTER-1', stage: 'Awaiting property approvals', status_key: 'awaiting',
    property_codes: ['P1', 'P2'], net_amount_raw: 175, include_in_total: true,
    children: [child(1, 'P1', 'draft'), child(2, 'P2', 'awaiting', 2)] };

test('approval filters target real levels rather than broad verification badges', () => {
    assert.equal(list.matchesStatus(child(1, 'P1', 'awaiting', 2), 'level:2'), true);
    assert.equal(list.matchesStatus(child(1, 'P1', 'awaiting', 2), 'level:1'), false);
    assert.equal(list.matchesStatus(child(1, 'P1', 'returned', 1), 'awaiting'), false);
    assert.equal(list.matchesStatus(child(1, 'P1', 'returned', 1), 'returned'), true);
    assert.equal(list.matchesStatus(child(1, 'P1', 'approved'), 'level:2'), false);
});

test('a merged row can be found by its component approval level', () => {
    assert.equal(list.matchesRow(merged, '', 'level:2'), true);
    assert.equal(list.matchesRow(merged, '', 'draft'), true);
    assert.equal(list.matchesRow(merged, '', 'approved'), false);
});

test('property and level filters must match the same property within a merged return', () => {
    assert.equal(list.matchesRow(merged, 'P2', 'level:2'), true);
    assert.equal(list.matchesRow(merged, 'P1', 'level:2'), false);
    assert.equal(list.matchesRow(merged, 'P1', 'draft'), true);
    assert.equal(list.matchesRow(merged, 'P3', ''), false);
});

test('list totals count the merged amount once and omit isolated linked components', () => {
    const rows = [merged, { ...child(3, 'P3', 'approved'), net_amount_raw: 50 },
        { ...child(4, 'P4', 'approved'), net_amount_raw: 1000, include_in_total: false }];
    const total = list.totals(rows);
    assert.equal(total.count, 3);
    assert.equal(total.net, 225);
    assert.equal(total.excludesComponents, true);
});

test('bulk forwarding includes only visible matching draft components and deduplicates ids', () => {
    assert.equal(list.drafts([merged], 'P2', '').length, 0);
    assert.equal(list.drafts([merged], '', 'level:2').length, 0);
    assert.equal(list.drafts([merged], 'P1', 'draft').length, 1);
    assert.equal(list.drafts([merged, merged.children[0]], '', '').length, 1);
    assert.equal(list.drafts([{ ...merged, children: [merged.children[1]] }], '', '').length, 0);
});

test('expanded rows provide links and preserve each property status', () => {
    const html = list.childrenHtml(merged);
    assert.match(html, /Awaiting level 2 of 3/);
    assert.match(html, /\/returns\/encrypted-2\/details/);
    assert.match(html, /Property returns in MASTER-1/);
    assert.match(html, /not added to the list total/);
});

test('return names and approval reasons are escaped before rendering', () => {
    const row = { ...child(1, '<img src=x onerror=alert(1)>', 'returned', 1), status_note: '<script>alert(1)</script>' };
    const html = list.childrenHtml({ ...merged, return_code: '<script>master</script>', children: [row] });
    assert.doesNotMatch(html, /<script>|<img/);
    assert.match(html, /&lt;script&gt;/);
    assert.match(html, /&lt;img/);
});

test('missing legacy links show a review message without inventing component records', () => {
    const html = list.childrenHtml({ ...merged, children: [], hidden_child_count: 2 });
    assert.match(html, /No linked property returns are available/);
    assert.match(html, /outside your permitted properties/);
    assert.doesNotMatch(html, /encrypted-/);
});

test('separate status, level and property filters must all match the same child', () => {
    const row = { ...merged, children: [child(1, 'P1', 'returned', 1), child(2, 'P2', 'awaiting', 2)] };
    assert.equal(list.matchesRow(row, '', 'returned', '2'), false);
    assert.equal(list.matchesRow(row, 'P1', 'awaiting', '2'), false);
    assert.equal(list.matchesRow(row, 'P2', 'awaiting', '2'), true);
    assert.equal(list.matchesRow(row, 'P1', 'returned', '1'), true);
    assert.equal(list.drafts([merged], '', '', '2').length, 0);
});

test('approval progress shows the next configured approver and escapes their name', () => {
    const html = list.statusHtml({ ...child(1, 'P1', 'awaiting', 1), next_approver: '<Finance Reviewer>' });
    assert.match(html, /Awaiting level 1 of 3/);
    assert.match(html, /Next:/);
    assert.match(html, /&lt;Finance Reviewer&gt;/);
    assert.doesNotMatch(html, /<Finance Reviewer>/);
});
