import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/payroll-monthly-items.js', import.meta.url), 'utf8');
const root = {};
vm.runInNewContext(source, { window: root });
const M = root.PayrollMonthlyItems;
// Arrays made inside the vm belong to another realm, so compare copies
const plain = (value) => JSON.parse(JSON.stringify(value));

const recurringRows = [
    { row_id: 1, contract_id: 10, staff: 'ALLAN LUHAMBE SOGOMI', staff_number: '274', element: 'AKIBA SACCO LOAN', type: '1', amount: 55000 },
    { row_id: 2, contract_id: 10, staff: 'ALLAN LUHAMBE SOGOMI', staff_number: '274', element: 'AKIBA SHARE CONTRIBUTION', type: '1', amount: 1000 },
    { row_id: 3, contract_id: 10, staff: 'ALLAN LUHAMBE SOGOMI', staff_number: '274', element: 'HOUSE ALLOWANCE', type: '2', amount: 20000.5 },
    { row_id: 4, contract_id: 11, staff: 'BETH WANJIRU', staff_number: 'E-9', element: 'AKIBA SACCO LOAN', type: '1', amount: 2500 },
];

const items = [
    { id: 7, staff: 'BETH WANJIRU', staff_number: 'E-9', element: 'BONUS', type: '2', amount: 5000, note: 'Q3 target' },
    { id: 8, staff: 'All staff', staff_number: null, element: 'ABSENCE', type: '1', amount: 300, note: null },
];

// ── formatting ───────────────────────────────────────────────────────

test('amounts have two decimals and thousands separators', () => {
    assert.equal(M.money(1234.5), '1,234.50');
    assert.equal(M.money(0), '0.00');
    assert.equal(M.money(null), '0.00');
});

test('text is escaped, quotes included, because names also go into attributes', () => {
    assert.equal(M.escapeHtml('<b>"A" & \'B\'</b>'), '&lt;b&gt;&quot;A&quot; &amp; &#39;B&#39;&lt;/b&gt;');
    assert.equal(M.escapeHtml(null), '');
});

// ── allowance or deduction ───────────────────────────────────────────

test('type 2 is an allowance and everything else a deduction', () => {
    assert.equal(M.typeKey('2'), 'allowance');
    assert.equal(M.typeKey(2), 'allowance');
    assert.equal(M.typeKey('1'), 'deduction');
    assert.equal(M.typeKey(''), 'deduction', 'an item whose element was deleted');
    assert.equal(M.typeName('2'), 'Allowance');
    assert.equal(M.typeName('1'), 'Deduction');
});

test('the type badge carries the colour class for its kind', () => {
    assert.equal(M.typeBadge('2'), '<span class="pm-type pm-type--allowance">Allowance</span>');
    assert.equal(M.typeBadge('1'), '<span class="pm-type pm-type--deduction">Deduction</span>');
});

// ── search and filter ────────────────────────────────────────────────

test('an empty search keeps every row', () => {
    assert.equal(M.filterRows(recurringRows, '', '').length, 4);
    assert.equal(M.filterRows(recurringRows, '   ', '').length, 4);
    assert.equal(M.filterRows(null, 'x', '').length, 0);
});

test('search looks at the staff name, staff number and item, ignoring case', () => {
    assert.deepEqual(plain(M.filterRows(recurringRows, 'beth', '').map((row) => row.row_id)), [4]);
    assert.deepEqual(plain(M.filterRows(recurringRows, 'e-9', '').map((row) => row.row_id)), [4]);
    assert.deepEqual(plain(M.filterRows(recurringRows, 'share contribution', '').map((row) => row.row_id)), [2]);
    assert.deepEqual(plain(M.filterRows(recurringRows, '274', '').map((row) => row.row_id)), [1, 2, 3]);
});

test('every word typed has to match, so a name and an item can be combined', () => {
    assert.deepEqual(plain(M.filterRows(recurringRows, 'allan akiba', '').map((row) => row.row_id)), [1, 2]);
    assert.deepEqual(plain(M.filterRows(recurringRows, 'beth house', '').map((row) => row.row_id)), []);
});

test('one-time items are also found by their note', () => {
    assert.deepEqual(plain(M.filterRows(items, 'target', '').map((row) => row.id)), [7]);
    assert.deepEqual(plain(M.filterRows(items, 'all staff', '').map((row) => row.id)), [8]);
});

test('the type filter keeps only allowances or only deductions', () => {
    assert.deepEqual(plain(M.filterRows(recurringRows, '', 'allowance').map((row) => row.row_id)), [3]);
    assert.deepEqual(plain(M.filterRows(recurringRows, '', 'deduction').map((row) => row.row_id)), [1, 2, 4]);
    assert.deepEqual(plain(M.filterRows(recurringRows, 'allan', 'deduction').map((row) => row.row_id)), [1, 2]);
});

test('the chips count every row, whatever the search says', () => {
    assert.deepEqual(plain(M.typeCounts(recurringRows)), { all: 4, allowance: 1, deduction: 3 });
    assert.deepEqual(plain(M.typeCounts([])), { all: 0, allowance: 0, deduction: 0 });
});

test('the count line says how many are shown only when some are filtered out', () => {
    assert.equal(M.shownText(12, 12), '12 items');
    assert.equal(M.shownText(1, 1), '1 item');
    assert.equal(M.shownText(3, 12), 'Showing 3 of 12');
    assert.equal(M.shownText(0, 12), 'Showing 0 of 12');
    assert.equal(M.shownText(0, 0), '');
});

// ── grouping ─────────────────────────────────────────────────────────

test('recurring rows are grouped under each staff member, in order, with what is added and taken off pay', () => {
    const groups = M.groupByStaff(recurringRows);
    assert.deepEqual(plain(groups.map((group) => [group.staff, group.rows.length, group.allowances, group.deductions])), [
        ['ALLAN LUHAMBE SOGOMI', 3, 20000.5, 56000],
        ['BETH WANJIRU', 1, 0, 2500],
    ]);
    assert.equal(groups[0].staff_number, '274');
});

test('two people with the same name stay in separate groups when their contracts differ', () => {
    const rows = [
        { row_id: 1, contract_id: 1, staff: 'JOHN DOE', staff_number: 'A', element: 'X', type: '1', amount: 1 },
        { row_id: 2, contract_id: 2, staff: 'JOHN DOE', staff_number: 'B', element: 'X', type: '1', amount: 1 },
    ];
    assert.equal(M.groupByStaff(rows).length, 2);
});

test('the group header names the person, their number, how many items and the sums', () => {
    const header = M.groupHeaderHtml(M.groupByStaff(recurringRows)[0], 4);
    assert.match(header, /^<tr class="pm-group"><th colspan="4" scope="colgroup">/);
    assert.match(header, /<span class="pm-group-name">ALLAN LUHAMBE SOGOMI<\/span>/);
    assert.match(header, /<span class="pm-number">274<\/span>/);
    assert.match(header, /3 items/);
    assert.match(header, /<span class="pm-sum pm-sum--allowance">\+ 20,000.50<\/span>/);
    assert.match(header, /<span class="pm-sum pm-sum--deduction">- 56,000.00<\/span>/);
    assert.match(M.groupHeaderHtml(M.groupByStaff(recurringRows)[1], 4), /1 item</);
    assert.doesNotMatch(M.groupHeaderHtml(M.groupByStaff(recurringRows)[1], 4), /pm-sum--allowance/, 'no allowance sum when there are none');
});

// ── unsaved edits ────────────────────────────────────────────────────

test('a row counts as edited only when what was typed differs from what is saved', () => {
    assert.equal(M.isDirty({ amount: 1000 }, undefined), false);
    assert.equal(M.isDirty({ amount: 1000 }, { amount: '1000' }), false);
    assert.equal(M.isDirty({ amount: 1000.5 }, { amount: '1000.50' }), false, 'the same number written differently');
    assert.equal(M.isDirty({ amount: 1000 }, { amount: '1200' }), true);
    assert.equal(M.isDirty({ amount: 1000 }, { amount: '' }), true, 'an emptied amount is an edit, so the server can say why it is refused');
    assert.equal(M.isDirty({ amount: 1000 }, { amount: '  ' }), true);
    assert.equal(M.isDirty({ amount: 0 }, { amount: '' }), true, 'also when the saved amount is 0, which a contract item may have');
    assert.equal(M.isDirty({ amount: 0 }, { amount: '0.00' }), false);
});

test('a changed note is an edit even when the amount is the same', () => {
    assert.equal(M.isDirty({ amount: 5, note: 'a' }, { amount: '5', note: 'b' }), true);
    assert.equal(M.isDirty({ amount: 5, note: null }, { amount: '5', note: '' }), false, 'no note saved and none typed');
    assert.equal(M.isDirty({ amount: 5, note: 'a' }, { amount: '5', note: 'a' }), false);
});

// ── rows ─────────────────────────────────────────────────────────────

test('a recurring row has the item, its type, an amount box and Save and Remove, with Save off until it is edited', () => {
    const html = M.recurringRowHtml(recurringRows[0], { canEdit: true, drafts: {} });
    assert.match(html, /^<tr data-id="1" data-key="r1">/);
    assert.match(html, /<td class="pm-item-cell">AKIBA SACCO LOAN<\/td>/);
    assert.match(html, /pm-type--deduction/);
    assert.match(html, /<input type="number" class="form-control form-control-sm pm-amount recurring-amount" min="0" step="0.01" inputmode="decimal" value="55000" data-original="55000"/);
    assert.match(html, /class="btn btn-sm btn-light pm-btn save-recurring" disabled/, 'a quiet light button, because the theme keeps a disabled blue one blue');
    assert.match(html, /class="btn btn-sm btn-outline-danger pm-icon-btn remove-recurring"/);
    assert.doesNotMatch(html, />[^<>]*ALLAN/, 'the name is in the group header, not written on every row (only in the labels read aloud)');
    assert.match(html, /aria-label="Save AKIBA SACCO LOAN for ALLAN LUHAMBE SOGOMI"/, 'but each button still says whose row it is');
});

test('a recurring row says under the item how often it applies and what a part month does', () => {
    const row = { ...recurringRows[0], terms: 'Once only (Dec 2026) \u00b7 full amount in a part month' };
    const html = M.recurringRowHtml(row, { canEdit: true, drafts: {} });

    assert.match(html, /<td class="pm-item-cell">AKIBA SACCO LOAN<span class="pm-terms">Once only \(Dec 2026\) \u00b7 full amount in a part month<\/span><\/td>/);
});

test('the terms are written as text, so they cannot add markup to the table', () => {
    const html = M.recurringRowHtml({ ...recurringRows[0], terms: '<img src=x onerror=alert(1)>' }, { canEdit: false, drafts: {} });

    assert.doesNotMatch(html, /<img/);
    assert.match(html, /&lt;img src=x onerror=alert\(1\)&gt;/);
});

test('a row with no terms shows none', () => {
    assert.doesNotMatch(M.recurringRowHtml(recurringRows[0], { canEdit: true, drafts: {} }), /pm-terms/);
    assert.doesNotMatch(M.recurringRowHtml({ ...recurringRows[0], terms: '' }, { canEdit: true, drafts: {} }), /pm-terms/);
});

test('an edited recurring row comes back tinted with its typed amount and Save on', () => {
    const html = M.recurringRowHtml(recurringRows[0], { canEdit: true, drafts: { r1: { amount: '60000' } } });
    assert.match(html, /^<tr data-id="1" data-key="r1" class="is-dirty">/);
    assert.match(html, /value="60000" data-original="55000"/);
    assert.doesNotMatch(html, /save-recurring" disabled/);
    assert.match(html, /class="btn btn-sm btn-primary pm-btn save-recurring"/, 'the blue button once there is something to save');
    assert.doesNotMatch(html, /btn-light/);
});

test('a draft that matches the saved amount is not shown as edited', () => {
    const html = M.recurringRowHtml(recurringRows[0], { canEdit: true, drafts: { r1: { amount: '55000' } } });
    assert.doesNotMatch(html, /is-dirty/);
    assert.match(html, /save-recurring" disabled/);
});

test('without permission a recurring row shows the amount as text and has no buttons', () => {
    const html = M.recurringRowHtml(recurringRows[2], { canEdit: false, drafts: {} });
    assert.match(html, /<span class="pm-amount-text">20,000.50<\/span>/);
    assert.doesNotMatch(html, /<input|<button/);
});

test('a one-time item shows its staff, number, type, amount, note and buttons', () => {
    const html = M.itemRowHtml(items[0], { canEdit: true, drafts: {} });
    assert.match(html, /^<tr data-id="7" data-key="i7">/);
    assert.match(html, /<span class="fw-semibold">BETH WANJIRU<\/span> <span class="pm-number">E-9<\/span>/);
    assert.match(html, /pm-type--allowance/);
    assert.match(html, /class="form-control form-control-sm pm-amount item-amount"[^>]*value="5000" data-original="5000"/);
    assert.match(html, /class="form-control form-control-sm item-note" maxlength="255" value="Q3 target" data-original="Q3 target"/);
    assert.match(html, /save-item" disabled/);
    assert.match(html, /pm-icon-btn delete-item/);
});

test('a one-time item for everyone has no staff number and an empty note box', () => {
    const html = M.itemRowHtml(items[1], { canEdit: true, drafts: {} });
    assert.doesNotMatch(html, /pm-number/);
    assert.match(html, /item-note" maxlength="255" value="" data-original=""/);
});

test('a typed note is kept on the row until it is saved', () => {
    const html = M.itemRowHtml(items[0], { canEdit: true, drafts: { i7: { amount: '5000', note: 'Changed' } } });
    assert.match(html, /class="is-dirty"/);
    assert.match(html, /value="Changed" data-original="Q3 target"/);
});

test('without permission a one-time item shows plain text, with a dash for no note', () => {
    const withNote = M.itemRowHtml(items[0], { canEdit: false, drafts: {} });
    assert.match(withNote, /<td class="pm-note-cell">Q3 target<\/td>/);
    assert.doesNotMatch(withNote, /<input|<button/);
    assert.match(M.itemRowHtml(items[1], { canEdit: false, drafts: {} }), /<span class="text-muted">-<\/span>/);
});

test('names, items and notes are escaped on every row', () => {
    const evil = '<img src=x onerror=alert(1)>';
    const recurringHtml = M.recurringRowHtml({ row_id: 5, contract_id: 1, staff: evil, staff_number: evil, element: evil, type: '1', amount: 1 }, { canEdit: true, drafts: {} });
    const itemHtml = M.itemRowHtml({ id: 5, staff: evil, staff_number: evil, element: evil, type: '1', amount: 1, note: evil }, { canEdit: true, drafts: {} });
    const header = M.groupHeaderHtml(M.groupByStaff([{ row_id: 5, contract_id: 1, staff: evil, staff_number: evil, element: 'x', type: '1', amount: 1 }])[0], 4);
    for (const html of [recurringHtml, itemHtml, header]) {
        assert.doesNotMatch(html, /<img/);
        assert.match(html, /&lt;img/);
    }
});

// ── whole tables ─────────────────────────────────────────────────────

test('the recurring table is a group header and its rows for each person', () => {
    const body = M.recurringBody(recurringRows, { canEdit: true, drafts: {}, query: '', type: '' });
    assert.equal((body.match(/<tr class="pm-group">/g) || []).length, 2);
    assert.equal((body.match(/<tr data-id=/g) || []).length, 4);
    assert.ok(body.indexOf('ALLAN') < body.indexOf('BETH'));
    assert.equal((body.match(/>[^<>]*ALLAN[^<>]*</g) || []).length, 1, 'the name is written once, not on every row');
});

test('filtering the recurring table drops the people left with no rows, headers included', () => {
    const body = M.recurringBody(recurringRows, { canEdit: true, drafts: {}, query: 'beth', type: '' });
    assert.equal((body.match(/<tr class="pm-group">/g) || []).length, 1);
    assert.equal((body.match(/<tr data-id=/g) || []).length, 1);
    assert.doesNotMatch(body, /ALLAN/);
});

test('with no recurring items the table says so, and with no match it offers to clear the filters', () => {
    assert.match(M.recurringBody([], { canEdit: true, drafts: {}, query: '', type: '' }), /No recurring items on the contracts in this run\./);
    assert.doesNotMatch(M.recurringBody([], { canEdit: true, drafts: {}, query: '', type: '' }), /pm-clear-filters/);
    const none = M.recurringBody(recurringRows, { canEdit: true, drafts: {}, query: 'zzz', type: '' });
    assert.match(none, /No items match your search\./);
    assert.match(none, /class="btn btn-link btn-sm p-0 align-baseline pm-clear-filters">Clear the filters</);
    assert.match(none, /<td colspan="4">/);
});

test('the one-time table lists the matching items, or says there are none', () => {
    const view = { canEdit: true, drafts: {}, query: '', type: '' };
    assert.equal((M.itemsBody(items, view).match(/<tr data-id=/g) || []).length, 2);
    assert.equal((M.itemsBody(items, { ...view, type: 'allowance' }).match(/<tr data-id=/g) || []).length, 1);
    assert.match(M.itemsBody([], view), /No one-time items for this period\./);
    assert.match(M.itemsBody(items, { ...view, query: 'zzz' }), /<td colspan="6">[^]*pm-clear-filters/);
});

// ── the header and the wording ───────────────────────────────────────

test('the header shows the period and a status colour', () => {
    const html = (status) => M.headerHtml({ period: { label: '01 Oct 2026 to 31 Oct 2026' }, run_status: status, locked: false });
    assert.match(html('Approved'), /<strong>01 Oct 2026 to 31 Oct 2026<\/strong><span class="badge badge-light-success">Approved<\/span>/);
    assert.match(html('With Accountant'), /badge-light-warning/);
    assert.match(html('Returned'), /badge-light-danger/);
    assert.match(html('Reversed'), /badge-light-danger/);
    assert.match(html('Draft'), /badge-light-secondary/);
    assert.doesNotMatch(html(null), /badge/);
    assert.doesNotMatch(html('Approved'), /pm-lock/);
});

test('a processed period shows one slim lock notice with the old wording', () => {
    const html = M.headerHtml({ period: { label: 'x' }, run_status: 'Approved', locked: true });
    assert.equal((html.match(/pm-lock/g) || []).length, 1);
    assert.match(html, /<span class="material-symbols-outlined">lock<\/span>/);
    assert.match(html, /This period has already been processed, so its one-time items are locked\. Reverse or discard the run on Payroll Processing to change them\./);
    assert.doesNotMatch(html, /alert-warning/);
});

test('the period label is escaped', () => {
    assert.doesNotMatch(M.headerHtml({ period: { label: '<script>alert(1)</script>' }, run_status: '<b>', locked: false }), /<script|<b>/);
});

test('the recurring hint is true for a processed period: it will not change that payroll', () => {
    assert.match(M.recurringHint(false), /so it applies to this period and every one after it\.$/);
    const locked = M.recurringHint(true);
    assert.match(locked, /already processed, so it will not change this payroll/);
    assert.match(locked, /reversed and run again/);
    assert.doesNotMatch(locked, /applies to this period and every one after it/);
});

// ── the check tab ────────────────────────────────────────────────────

test('the check tab has four summary tiles', () => {
    const html = M.checkSummaryHtml({ staff_in_run: 56, items: 3, one_time_allowances: 5000, one_time_deductions: 1234.5 });
    assert.equal((html.match(/class="pm-stat"/g) || []).length, 4);
    assert.match(html, /Staff in this run<\/p><div class="pm-stat-value">56</);
    assert.match(html, /One-time allowances<\/p><div class="pm-stat-value">5,000.00</);
    assert.match(html, /One-time deductions<\/p><div class="pm-stat-value">1,234.50</);
});

test('with nothing to flag the check tab says so', () => {
    assert.match(M.checkSectionsHtml([]), /pm-allclear[^]*Nothing to flag for this period\./);
    assert.match(M.checkSectionsHtml(null), /Nothing to flag/);
});

test('each finding is a card that opens to its people: warnings open, notes closed', () => {
    const html = M.checkSectionsHtml([
        { level: 'warning', title: 'No bank details', hint: 'They cannot be paid.', count: 2, rows: [{ name: 'Beth', detail: 'no account' }, { name: 'Zane', detail: 'no bank' }] },
        { level: 'info', title: 'New this month', hint: 'For your information.', count: 1, rows: [{ name: 'Cara', detail: 'joined' }] },
    ]);
    assert.match(html, /<details class="pm-section pm-section--warning" open>/);
    assert.match(html, /<details class="pm-section pm-section--info">/);
    assert.match(html, /<span class="pm-section-title">No bank details<\/span><span class="pm-section-hint">They cannot be paid\.<\/span>/);
    assert.match(html, /<span class="pm-section-count">2<\/span>/);
    assert.match(html, /<tr><td>Beth<\/td><td class="text-muted">no account<\/td><\/tr>/);
});

test('a cut-off list says how many more there are', () => {
    const html = M.checkSectionsHtml([{ level: 'warning', title: 't', hint: 'h', count: 105, rows: new Array(100).fill({ name: 'a', detail: 'b' }) }]);
    assert.match(html, /And 5 more\./);
    assert.doesNotMatch(M.checkSectionsHtml([{ level: 'warning', title: 't', hint: 'h', count: 1, rows: [{ name: 'a', detail: 'b' }] }]), /more\./);
});

test('what the check found is escaped', () => {
    const html = M.checkSectionsHtml([{ level: 'warning', title: '<i>t</i>', hint: '<i>h</i>', count: 1, rows: [{ name: '<i>n</i>', detail: '<i>d</i>' }] }]);
    assert.doesNotMatch(html, /<i>/);
});

test('the check tab badge counts the findings and is amber when any is a warning', () => {
    assert.deepEqual(plain(M.checkBadge({ sections: [] })), { count: 0, tone: 'info' });
    assert.deepEqual(plain(M.checkBadge({ sections: [{ level: 'info' }] })), { count: 1, tone: 'info' });
    assert.deepEqual(plain(M.checkBadge({ sections: [{ level: 'info' }, { level: 'warning' }] })), { count: 2, tone: 'warning' });
    assert.deepEqual(plain(M.checkBadge(null)), { count: 0, tone: 'info' });
});
