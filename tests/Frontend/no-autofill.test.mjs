import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/no-autofill.js', import.meta.url), 'utf8');
const root = {};
vm.runInNewContext(source, { window: root });
const N = root.NoAutofill;

// A stand-in for a page element: attributes, a type, and a closest() that answers for the selectors given
function el(tag, attrs = {}, closestMatches = []) {
    const a = { ...attrs };
    return {
        tagName: tag.toUpperCase(),
        type: a.type !== undefined ? a.type : (tag === 'input' ? 'text' : ''),
        getAttribute: (key) => (key in a ? a[key] : null),
        hasAttribute: (key) => key in a,
        setAttribute: (key, value) => { a[key] = value; },
        closest: (selector) => (closestMatches.some((match) => selector.includes(match)) ? {} : null),
        querySelectorAll: () => [],
        attrs: a,
    };
}

test('only the HR pages are covered', () => {
    for (const path of ['/hr/employees/create', '/hr/contracts/12/edit', '/hr/staff/positions', '/payroll/monthly-items', '/payroll/processing',
        '/users', '/hrsettings/leavetypes', '/discipline/tracker/create/incident', '/add/employee', '/employee/edit/5', '/confirm/users',
        'hr/employees', '/HR/Employees?draft=3']) {
        assert.equal(N.isHrPath(path), true, path);
    }
    for (const path of ['/property/clients/create', '/finance/vouchers', '/login', '/dashboard', '/reports/hr/staff-directory', '/', '', '/hrx/employees', '/usersettings']) {
        assert.equal(N.isHrPath(path), false, path);
    }
});

test('text fields, text areas and drop-downs get autocomplete off', () => {
    assert.equal(N.settingFor(el('input', { type: 'text' })), 'off');
    assert.equal(N.settingFor(el('input', { type: 'email' })), 'off');
    assert.equal(N.settingFor(el('input', { type: 'tel' })), 'off');
    assert.equal(N.settingFor(el('input', { type: 'number' })), 'off');
    assert.equal(N.settingFor(el('input', { type: 'date' })), 'off');
    assert.equal(N.settingFor(el('textarea')), 'off');
    assert.equal(N.settingFor(el('select')), 'off');
});

test('password fields are marked new-password so a saved password is never filled in', () => {
    assert.equal(N.settingFor(el('input', { type: 'password' })), 'new-password');
});

test('fields that take no typed text are left alone', () => {
    for (const type of ['hidden', 'file', 'checkbox', 'radio', 'submit', 'button', 'image', 'reset', 'range', 'color']) {
        assert.equal(N.settingFor(el('input', { type })), '', type);
    }
});

test('search boxes keep their suggestions', () => {
    assert.equal(N.settingFor(el('input', { type: 'search' })), '');
    assert.equal(N.settingFor(el('input', { type: 'text', 'data-allow-suggestions': '' })), '');
    assert.equal(N.settingFor(el('input', { type: 'text' }, ['.dataTables_filter'])), '', 'the DataTables search box');
    assert.equal(N.settingFor(el('input', { type: 'text' }, ['[role="search"]'])), '');
});

test('applying to a page sets the form and every field in it', () => {
    const name = el('input', { type: 'text', name: 'full_names' });
    const id = el('input', { type: 'text', name: 'national_id' });
    const secret = el('input', { type: 'password' });
    const hidden = el('input', { type: 'hidden' });
    const search = el('input', { type: 'search' });
    const select = el('select', { name: 'bank_name' });
    const form = el('form', {});
    const page = el('div');
    page.querySelectorAll = (selector) => (selector === 'form' ? [form] : [name, id, secret, hidden, search, select]);

    N.apply(page);

    assert.equal(form.attrs.autocomplete, 'off');
    assert.equal(name.attrs.autocomplete, 'off');
    assert.equal(id.attrs.autocomplete, 'off');
    assert.equal(select.attrs.autocomplete, 'off');
    assert.equal(secret.attrs.autocomplete, 'new-password');
    assert.equal('autocomplete' in hidden.attrs, false);
    assert.equal('autocomplete' in search.attrs, false);
});

test('a form marked data-allow-suggestions and everything in it is left alone', () => {
    const field = el('input', { type: 'text' }, ['form[data-allow-suggestions]']);
    const form = el('form', { 'data-allow-suggestions': '' });
    const page = el('div');
    page.querySelectorAll = (selector) => (selector === 'form' ? [form] : [field]);

    N.apply(page);

    assert.equal('autocomplete' in form.attrs, false);
    assert.equal('autocomplete' in field.attrs, false);
});

test('a field added later, such as a new contract row, is covered when it is applied to', () => {
    const added = el('input', { type: 'number', name: 'amount' });

    N.apply(added);

    assert.equal(added.attrs.autocomplete, 'off');
});

test('nothing happens on a page that is not an HR page', () => {
    const field = el('input', { type: 'text' });
    const page = el('div');
    page.querySelectorAll = () => [];
    const doc = { body: {}, querySelectorAll: () => [field], tagName: 'HTML' };

    N.start(doc, '/property/clients/create');

    assert.equal('autocomplete' in field.attrs, false);
});
