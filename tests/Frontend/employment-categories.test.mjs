import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/employment-categories.js', import.meta.url), 'utf8');
const root = {};
vm.runInNewContext(source, { window: root });
const EC = root.EmploymentCategories;

const casual = { id: 3, code: 'Casual', name: 'Casual', active: true, staff_number_prefix: 'C', gets_login: false, has_end_date: true, staff_count: 12 };
const permanent = { id: 1, code: 'Permanent', name: 'Permanent & Pensionable', active: true, staff_number_prefix: '', gets_login: true, has_end_date: false, staff_count: 280 };
const both = { update: true, delete: true };

test('text is escaped, quotes included, because names also go into attributes', () => {
    assert.equal(EC.escapeHtml('<b>"A" & \'B\'</b>'), '&lt;b&gt;&quot;A&quot; &amp; &#39;B&#39;&lt;/b&gt;');
    assert.equal(EC.escapeHtml(null), '');
    assert.equal(EC.escapeHtml(0), '0');
});

test('the staff number example shows the prefix, or plain numbers when there is none', () => {
    assert.equal(EC.numberExample('CT'), 'CT-001');
    assert.equal(EC.numberExample(''), '1, 2, 3...');
    assert.equal(EC.numberExample(null), '1, 2, 3...');
});

test('a row shows the name, prefix, login, end date, staff count and status', () => {
    const html = EC.rowHtml(casual, both);
    assert.match(html, /^<tr data-id="3">/);
    assert.match(html, /<span class="fw-semibold">Casual<\/span>/);
    assert.match(html, /<code>C<\/code> <span class="small text-muted">C-001<\/span>/);
    assert.match(html, /<td>No<\/td><td>Yes<\/td>/, 'no login, has an end date');
    assert.match(html, /<td class="text-end">12<\/td>/);
    assert.match(html, /<span class="small text-success">On<\/span>/);
});

test('a category with no prefix says plain numbers, and one with no end date says probation only', () => {
    const html = EC.rowHtml(permanent, both);
    assert.match(html, /none \(plain numbers\)/);
    assert.match(html, /No \(probation only\)/);
    assert.match(html, /<td>Yes<\/td><td><span class="text-muted">No \(probation only\)/, 'gets a login, has no end date');
    assert.match(html, /<td class="text-end">280<\/td>/);
});

test('a switched-off category is greyed and its switch is unticked', () => {
    const html = EC.rowHtml({ ...casual, active: false }, both);
    assert.match(html, /^<tr data-id="3" class="ec-off">/);
    assert.doesNotMatch(html, /type="checkbox" role="switch" checked/);
    assert.match(html, /<span class="small text-muted">Off<\/span>/);
});

test('the switch and the buttons follow what the user may do', () => {
    const full = EC.rowHtml(casual, both);
    assert.match(full, /class="form-check-input ec-toggle" type="checkbox" role="switch" checked aria-label="Offer Casual for new staff"/);
    assert.match(full, /ec-edit/);
    assert.match(full, /ec-delete/);

    const readOnly = EC.rowHtml(casual, { update: false, delete: false });
    assert.match(readOnly, /role="switch" checked disabled/, 'the switch shows its state but cannot be changed');
    assert.doesNotMatch(readOnly, /ec-edit|ec-delete/);

    const noDelete = EC.rowHtml(casual, { update: true, delete: false });
    assert.match(noDelete, /ec-edit/);
    assert.doesNotMatch(noDelete, /ec-delete/);
});

test('each button says which category it is for, for screen readers', () => {
    const html = EC.rowHtml(casual, both);
    assert.match(html, /aria-label="Edit Casual"/);
    assert.match(html, /aria-label="Delete Casual"/);
});

test('names, prefixes and counts are escaped', () => {
    const evil = '<img src=x onerror=alert(1)>';
    const html = EC.rowHtml({ ...casual, id: evil, name: evil, staff_number_prefix: evil, staff_count: evil }, both);
    assert.doesNotMatch(html, /<img/);
    assert.match(html, /&lt;img/);
});

test('the table body has a row per category, or says there are none', () => {
    assert.equal((EC.bodyHtml([casual, permanent], both).match(/<tr data-id=/g) || []).length, 2);
    assert.match(EC.bodyHtml([], both), /No employment categories yet\./);
    assert.match(EC.bodyHtml(null, both), /colspan="7"/);
});
