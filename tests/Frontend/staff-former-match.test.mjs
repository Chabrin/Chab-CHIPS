import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/staff-former-match.js', import.meta.url), 'utf8');
const root = {};
vm.runInNewContext(source, { window: root });
const F = root.StaffFormerMatch;

const former = { found: true, name: 'MARY WANJIKU', staff_number: 'PER017', status: 'Terminated', can_rehire: true, url: '/hr/employees/abc' };
const current = { found: true, name: 'JOHN OTIENO', staff_number: 'PER018', status: 'Active', can_rehire: false, url: '/hr/employees/def' };

// A stand-in for the box under the field, and for the page that creates the link
function box() {
    const classes = new Set(['d-none']);
    const children = [];
    return {
        textContent: '', classes, children,
        classList: { add: (...c) => c.forEach((x) => classes.add(x)), remove: (...c) => c.forEach((x) => classes.delete(x)) },
        appendChild: (node) => children.push(node),
    };
}
const doc = {
    createTextNode: (text) => ({ text }),
    createElement: () => ({ href: '', target: '', rel: '', textContent: '' }),
};

test('a former staff member is told to be rehired, not added again', () => {
    const info = F.describe(former);
    assert.equal(info.tone, 'warning');
    assert.match(info.text, /MARY WANJIKU \(staff number PER017, Terminated\)/);
    assert.match(info.text, /worked here before/);
    assert.match(info.text, /Rehire/);
    assert.match(info.text, /keep their staff number/);
    assert.equal(info.linkText, 'Open their record to rehire');
});

test('someone still working here cannot be added twice', () => {
    const info = F.describe(current);
    assert.equal(info.tone, 'danger');
    assert.match(info.text, /JOHN OTIENO \(staff number PER018, Active\)/);
    assert.match(info.text, /cannot be added twice/);
    assert.doesNotMatch(info.text, /Rehire/);
});

test('no match says nothing', () => {
    assert.equal(F.describe({ found: false }), null);
    assert.equal(F.describe(null), null);
});

test('a match fills the box with the message and a link to the record, and shows it', () => {
    const b = box();
    F.render(doc, b, former);

    assert.equal(b.classes.has('d-none'), false);
    assert.equal(b.classes.has('alert-warning'), true);
    assert.equal(b.children.length, 2);
    assert.match(b.children[0].text, /already on record/);
    assert.equal(b.children[1].href, '/hr/employees/abc');
    assert.equal(b.children[1].target, '_blank');
    assert.equal(b.children[1].rel, 'noopener');
});

test('a clean ID clears and hides the box', () => {
    const b = box();
    F.render(doc, b, former);
    F.render(doc, b, { found: false });

    assert.equal(b.classes.has('d-none'), true);
    assert.equal(b.classes.has('alert-warning'), false);
    assert.equal(b.textContent, '');
});

test('the box never takes the name as markup', () => {
    const b = box();
    F.render(doc, b, { ...former, name: '<img src=x onerror=alert(1)>' });

    assert.equal(b.children[0].text.includes('<img src=x onerror=alert(1)>'), true, 'it is text, not parsed');
    assert.equal(b.children.every((node) => node.innerHTML === undefined), true);
});

test('an answer for an ID that was typed over is ignored', async () => {
    const calls = [];
    const input = {
        value: '12345678', _l: {},
        getAttribute: () => '/ajax/hr/staff/former-match',
        addEventListener(type, fn) { this._l[type] = fn; },
    };
    const b = box();
    const page = { getElementById: (id) => (id === 'national_id' ? input : id === 'formerMatch' ? b : null), createTextNode: doc.createTextNode, createElement: doc.createElement };
    let resolveFirst;
    const fetchFn = (url) => {
        calls.push(url);
        if (calls.length === 1) return new Promise((resolve) => { resolveFirst = () => resolve({ ok: true, json: async () => former }); });
        return Promise.resolve({ ok: true, json: async () => current });
    };
    const attached = F.attach(page, fetchFn);

    attached.check();               // the first ID is slow to answer
    input.value = '87654321';       // meanwhile another ID is typed
    attached.check();
    await new Promise((resolve) => setTimeout(resolve, 5));
    resolveFirst();
    await new Promise((resolve) => setTimeout(resolve, 5));

    assert.match(calls[0], /national_id=12345678$/);
    assert.match(calls[1], /national_id=87654321$/);
    assert.match(b.children[0].text, /JOHN OTIENO/, 'the answer for the ID now in the box is what shows');
});

test('an ID that is too short is not looked up', () => {
    const calls = [];
    const input = { value: '12', getAttribute: () => '/x', addEventListener() {} };
    const b = box();
    const attached = F.attach({ getElementById: (id) => (id === 'national_id' ? input : b) }, (url) => { calls.push(url); return Promise.resolve({ ok: true, json: async () => former }); });

    attached.check();

    assert.equal(calls.length, 0);
});

test('a page without the field is left alone', () => {
    assert.equal(F.attach({ getElementById: () => null }, () => {}), null);
});
