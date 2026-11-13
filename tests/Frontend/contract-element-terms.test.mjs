import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/contract-element-terms.js', import.meta.url), 'utf8');
const root = {};
vm.runInNewContext(source, { window: root });
const T = root.ContractElementTerms;
const partial = readFileSync(new URL('../../resources/views/staff/contracts/partials/terms-fields.blade.php', import.meta.url), 'utf8');

test('a month reads as a short name and the year', () => {
    assert.equal(T.monthLabel('2026-12'), 'Dec 2026');
    assert.equal(T.monthLabel('2027-01-01'), 'Jan 2027');
    assert.equal(T.monthLabel('2026-06'), 'Jun 2026');
});

test('anything that is not a month gives nothing', () => {
    for (const bad of ['', null, undefined, '2026', '2026-13', '2026-00', 'soon', '12-2026']) {
        assert.equal(T.monthLabel(bad), '', String(bad));
    }
});

test('an allowance is reduced in a part month and a deduction is not, as the payroll run has always done', () => {
    assert.equal(T.defaultProrate('2'), '1');
    assert.equal(T.defaultProrate(2), '1');
    assert.equal(T.defaultProrate('1'), '0');
    assert.equal(T.defaultProrate(''), '0');
});

test('the words for an every-month item', () => {
    assert.equal(T.termsText({ frequency: 'monthly', prorate: true, entry_type: '2' }), 'Every month · reduced by the days worked in a part month');
    assert.equal(T.termsText({ frequency: 'monthly', prorate: false, entry_type: '2' }), 'Every month · full amount in a part month');
    assert.equal(T.termsText({ prorate: '1', entry_type: '1' }), 'Every month · reduced by the days worked in a part month', 'no frequency means every month');
});

test('the words for a once-only item name its month', () => {
    assert.equal(T.termsText({ frequency: 'once', once_month: '2026-12-01', prorate: false }), 'Once only (Dec 2026) · full amount in a part month');
    assert.equal(T.termsText({ frequency: 'once', once_month: '', prorate: true }), 'Once only · reduced by the days worked in a part month', 'no month, nothing in brackets');
});

test('with no answer to the part-month question the old rule for the type is described', () => {
    assert.match(T.termsText({ frequency: 'monthly', prorate: null, entry_type: '2' }), /reduced by the days worked/);
    assert.match(T.termsText({ frequency: 'monthly', prorate: null, entry_type: '1' }), /full amount/);
    assert.match(T.termsText({ frequency: 'monthly', prorate: '', entry_type: '1' }), /full amount/);
    assert.match(T.termsText({ frequency: 'monthly', prorate: undefined, entry_type: '2' }), /reduced/);
});

test('an explicit answer beats the type', () => {
    assert.match(T.termsText({ prorate: false, entry_type: '2' }), /full amount/);
    assert.match(T.termsText({ prorate: true, entry_type: '1' }), /reduced/);
    assert.match(T.termsText({ prorate: '0', entry_type: '2' }), /full amount/);
});

test('a once-only month has to fall inside the contract', () => {
    assert.equal(T.onceMonthProblem('2027-06', '2027-01-01', '2027-12-31'), '');
    assert.equal(T.onceMonthProblem('2027-01', '2027-01-15', '2027-12-31'), '', 'the month the contract starts is fine');
    assert.equal(T.onceMonthProblem('2027-12', '2027-01-01', '2027-12-31'), '', 'and the month it ends');
    assert.equal(T.onceMonthProblem('2026-12', '2027-01-01', '2027-12-31'), 'The month is before the contract starts (Jan 2027).');
    assert.equal(T.onceMonthProblem('2028-01', '2027-01-01', '2027-12-31'), 'The month is after the contract ends (Dec 2027).');
});

test('a contract with no end date has no last month', () => {
    assert.equal(T.onceMonthProblem('2035-01', '2027-01-01', ''), '');
    assert.equal(T.onceMonthProblem('2035-01', '2027-01-01', null), '');
    assert.equal(T.onceMonthProblem('2026-01', '2027-01-01', null), 'The month is before the contract starts (Jan 2027).');
});

test('no month chosen is a problem, and a missing contract date is not', () => {
    assert.equal(T.onceMonthProblem('', '2027-01-01', '2027-12-31'), 'Choose the month this item applies in.');
    assert.equal(T.onceMonthProblem(null, '', ''), 'Choose the month this item applies in.');
    assert.equal(T.onceMonthProblem('2027-05', '', ''), '');
});

// ── the fields of a new row ──────────────────────────────────────────────────

test('a new row asks both questions with the names the server reads', () => {
    const html = T.fieldsHtml(3);
    assert.match(html, /name="elements\[3\]\[frequency\]"/);
    assert.match(html, /name="elements\[3\]\[once_month\]"/);
    assert.match(html, /name="elements\[3\]\[prorate\]"/);
    assert.match(html, /<option value="monthly" selected>Every month<\/option><option value="once">Once only<\/option>/);
    assert.match(html, /<option value="1">Reduced by the days worked \(prorated\)<\/option><option value="0">Full amount<\/option>/);
});

test('the month box starts hidden and switched off, so it is not sent unless it is wanted', () => {
    const html = T.fieldsHtml(0);
    assert.match(html, /once-month-field d-none/);
    assert.match(html, /<input type="month" class="form-control once-month-input" name="elements\[0\]\[once_month\]" disabled>/);
});

test('the markup for a new row and the one for a saved row use the same fields and classes', () => {
    const html = T.fieldsHtml(0);
    for (const token of ['frequency-select', 'once-month-field', 'once-month-input', 'part-month-field', 'prorate-select', 'How often?', 'Which month?', 'Part month?',
        'Every month', 'Once only', 'Reduced by the days worked (prorated)', 'Full amount', 'For the month the contract starts or ends.']) {
        assert.ok(html.includes(token), 'the script writes ' + token);
        assert.ok(partial.includes(token), 'the page template writes ' + token);
    }
    for (const field of ['frequency', 'once_month', 'prorate']) {
        assert.ok(partial.includes('[' + field + ']'), field);
    }
});

// ── a row follows its choices ────────────────────────────────────────────────

function fakeRow({ frequency = 'monthly', entry = '', prorate = '1', touched = false } = {}) {
    const classes = (initial) => { const set = new Set(initial); return { contains: (c) => set.has(c), toggle: (c, on) => (on ? set.add(c) : set.delete(c)), set }; };
    const els = {
        '.frequency-select': { value: frequency },
        '.once-month-field': { classList: classes(['d-none']) },
        '.once-month-input': { disabled: true, required: false },
        '.prorate-select': { value: prorate, dataset: touched ? { touched: '1' } : {} },
        '.entry-type-select': { value: entry },
    };
    return { els, querySelector: (selector) => els[selector] || null };
}

test('choosing once only shows the month and asks for it', () => {
    const row = fakeRow({ frequency: 'once' });
    T.syncRow(row);
    assert.equal(row.els['.once-month-field'].classList.contains('d-none'), false);
    assert.equal(row.els['.once-month-input'].disabled, false);
    assert.equal(row.els['.once-month-input'].required, true);
});

test('choosing every month hides the month and does not send it', () => {
    const row = fakeRow({ frequency: 'monthly' });
    row.els['.once-month-field'].classList.toggle('d-none', false);
    row.els['.once-month-input'].disabled = false;
    row.els['.once-month-input'].required = true;
    T.syncRow(row);
    assert.equal(row.els['.once-month-field'].classList.contains('d-none'), true);
    assert.equal(row.els['.once-month-input'].disabled, true);
    assert.equal(row.els['.once-month-input'].required, false);
});

test('the part-month answer follows the entry type until someone chooses it', () => {
    const allowance = fakeRow({ entry: '2', prorate: '0' });
    T.syncRow(allowance);
    assert.equal(allowance.els['.prorate-select'].value, '1', 'an allowance is reduced');

    const deduction = fakeRow({ entry: '1', prorate: '1' });
    T.syncRow(deduction);
    assert.equal(deduction.els['.prorate-select'].value, '0', 'a deduction is not');
});

test('a part-month answer someone chose is left alone', () => {
    const row = fakeRow({ entry: '1', prorate: '1', touched: true });
    T.syncRow(row);
    assert.equal(row.els['.prorate-select'].value, '1');
});

test('with no entry type yet the part-month answer is left as it is', () => {
    const row = fakeRow({ entry: '', prorate: '1' });
    T.syncRow(row);
    assert.equal(row.els['.prorate-select'].value, '1');
});

test('choosing the part-month answer by hand marks it', () => {
    const select = { dataset: {} };
    T.markTouched(select);
    assert.equal(select.dataset.touched, '1');
    assert.doesNotThrow(() => T.markTouched(null));
});

test('a missing row is ignored', () => {
    assert.doesNotThrow(() => T.syncRow(null));
});

test('text is escaped, quotes included', () => {
    assert.equal(T.escapeHtml('<b>"A" & \'B\'</b>'), '&lt;b&gt;&quot;A&quot; &amp; &#39;B&#39;&lt;/b&gt;');
    assert.equal(T.escapeHtml(null), '');
});
