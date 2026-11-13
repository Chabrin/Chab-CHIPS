import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/payroll-review-grid.js', import.meta.url), 'utf8');
const root = {};
vm.runInNewContext(source, { window: root });
const grid = root.PayrollReviewGrid;
const columns = grid.columns();
const column = (key) => columns.find((candidate) => candidate.data === key);
const show = (key, value) => column(key).render(value, 'display');

test('amounts have two decimals and thousands separators', () => {
    assert.equal(grid.money(1234.5), '1,234.50');
    assert.equal(grid.money(0), '0.00');
    assert.equal(grid.money(null), '0.00');
    assert.equal(grid.money(-300), '-300.00');
    assert.equal(grid.money(12345678.9), '12,345,678.90');
});

test('text is escaped, quotes included, because names also go into attributes', () => {
    assert.equal(grid.escapeHtml('<b>"A" & \'B\'</b>'), '&lt;b&gt;&quot;A&quot; &amp; &#39;B&#39;&lt;/b&gt;');
    assert.equal(grid.escapeHtml(null), '');
    assert.equal(grid.escapeHtml(undefined), '');
    assert.equal(grid.escapeHtml(42), '42');
});

test('the grid has the fourteen columns in the old order', () => {
    assert.deepEqual(JSON.parse(JSON.stringify(columns.map((candidate) => candidate.data))), [
        'staff_number', 'name', 'department', 'basic', 'gross', 'paye', 'nssf', 'sha', 'housing_levy', 'other_deductions', 'net_pay', 'change', 'flags', null,
    ]);
});

test('every amount column is right-aligned so the decimals line up', () => {
    for (const key of ['basic', 'gross', 'paye', 'nssf', 'sha', 'housing_levy', 'other_deductions', 'net_pay', 'change']) {
        assert.match(column(key).className, /(^| )num( |$)/, key);
    }
    for (const key of ['staff_number', 'name', 'department', 'flags']) {
        assert.doesNotMatch(column(key).className || '', /num/, key);
    }
});

test('net pay alone is the highlighted column', () => {
    const highlighted = columns.filter((candidate) => /col-net/.test(candidate.className || ''));

    assert.deepEqual(JSON.parse(JSON.stringify(highlighted.map((candidate) => candidate.data))), ['net_pay']);
});

test('amounts show formatted but sort and search on the plain number', () => {
    assert.equal(column('gross').render(1234.5, 'display'), '1,234.50');
    assert.equal(column('gross').render(1234.5, 'sort'), 1234.5);
    assert.equal(column('gross').render(1234.5, 'filter'), 1234.5);
});

test('negative net pay is shown in red and a normal one is not', () => {
    assert.equal(show('net_pay', -50), '<span class="text-danger">-50.00</span>');
    assert.equal(show('net_pay', 50000), '<span class="">50,000.00</span>');
    assert.equal(column('net_pay').render(-50, 'sort'), -50);
});

test('the change from the last payroll shows its sign, goes quiet at zero and is a dash with nothing to compare', () => {
    assert.equal(grid.changeText(1200), '+1,200.00');
    assert.equal(grid.changeText(-300), '-300.00');
    assert.equal(grid.changeText(0), '<span class="text-muted">0.00</span>');
    assert.equal(grid.changeText(null), '<span class="text-muted">-</span>');
    assert.equal(grid.changeText(undefined), '<span class="text-muted">-</span>');
});

test('the change column sorts a missing change as zero', () => {
    assert.equal(column('change').render(null, 'sort'), 0);
    assert.equal(column('change').render(250, 'sort'), 250);
});

test('names are bold on screen and escaped; sorting uses the plain name', () => {
    assert.equal(show('name', 'Jane <Doe>'), '<span class="fw-semibold">Jane &lt;Doe&gt;</span>');
    assert.equal(column('name').render('Jane <Doe>', 'sort'), 'Jane <Doe>');
});

test('staff number and department are escaped', () => {
    assert.equal(show('staff_number', 'E-<1>'), 'E-&lt;1&gt;');
    assert.equal(show('department', 'R&D'), 'R&amp;D');
});

test('flags are badges, or a dash when there are none', () => {
    assert.equal(show('flags', ['Negative pay', 'No bank details']), '<span class="badge badge-light-danger">Negative pay</span> <span class="badge badge-light-danger">No bank details</span>');
    assert.equal(show('flags', []), '<span class="text-muted">-</span>');
    assert.equal(column('flags').render(['Negative pay', 'No bank details'], 'filter'), 'Negative pay, No bank details');
});

const actionColumn = columns[columns.length - 1];

test('every row has an icon button to view the payslip', () => {
    const html = actionColumn.render({ id: 12, name: 'Jane Doe' });

    assert.equal(actionColumn.orderable, false);
    assert.equal(actionColumn.searchable, false);
    assert.match(html, /<button type="button" class="btn btn-sm btn-outline-primary pr-icon-btn view-payslip"/);
    assert.match(html, /data-id="12"/);
    assert.match(html, /title="View payslip" aria-label="View payslip for Jane Doe"/);
    assert.match(html, /material-symbols-outlined">visibility</);
});

test('with no staff link there is nothing to edit and no delete anywhere', () => {
    const html = actionColumn.render({ id: 12, name: 'Jane Doe', edit_url: null });

    assert.equal((html.match(/<button/g) || []).length, 1);
    assert.doesNotMatch(html, /<a /);
    assert.doesNotMatch(html, /edit|delete|remove/i);
});

test('with a staff link the row also offers an edit icon that opens the record in a new tab', () => {
    const html = actionColumn.render({ id: 12, name: 'Jane Doe', edit_url: 'http://localhost/hr/employees/abc123/edit' });

    assert.match(html, /<a href="http:\/\/localhost\/hr\/employees\/abc123\/edit" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary pr-icon-btn"/);
    assert.match(html, /title="Edit staff record \(opens in a new tab\)" aria-label="Edit staff record for Jane Doe"/);
    assert.match(html, /material-symbols-outlined">edit</);
    assert.match(html, /visibility/, 'viewing the payslip is still there');
});

test('the edit link is escaped so it cannot break out of its attribute', () => {
    const html = actionColumn.render({ id: 1, name: 'A', edit_url: 'http://x/?a=1&b="2"' });

    assert.match(html, /href="http:\/\/x\/\?a=1&amp;b=&quot;2&quot;"/);
});

test('an edit link on a name with quotes is labelled safely', () => {
    const html = actionColumn.render({ id: 1, name: 'Mary" onclick="x', edit_url: 'http://x/edit' });

    assert.doesNotMatch(html, /" onclick="/);
});

test('flags are coloured by what they are: serious ones red, several contracts amber', () => {
    const row = { alerts: ['negative_net', 'no_bank', 'multiple_contracts'] };
    const html = column('flags').render(['Negative pay', 'No bank details', 'Several contracts'], 'display', row);

    assert.equal(html, '<span class="badge badge-light-danger">Negative pay</span> <span class="badge badge-light-danger">No bank details</span> <span class="badge badge-light-warning">Several contracts</span>');
});

test('a flag with no matching alert falls back to red', () => {
    assert.equal(column('flags').render(['Something'], 'display', { alerts: [] }), '<span class="badge badge-light-danger">Something</span>');
});

test('staff numbers are text, so they stay on the left instead of lining up like amounts', () => {
    assert.equal(column('staff_number').type, 'string');
});

test('a name with quotes cannot break out of the button attributes', () => {
    const html = columns[columns.length - 1].render({ id: 1, name: 'Mary" onclick="alert(1)' });

    assert.doesNotMatch(html, /" onclick="/);
    assert.match(html, /data-name="Mary&quot; onclick=&quot;alert\(1\)"/);
});
