import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/staff-citizenship.js', import.meta.url), 'utf8');
const root = {};
vm.runInNewContext(source, { window: root });
const C = root.StaffCitizenship;
// The script runs in its own context, so its objects are compared as plain data
const plain = (value) => JSON.parse(JSON.stringify(value));

// ---- a stand-in for the form -------------------------------------------------------------------------------------------

function field(id, value = '', attrs = {}) {
    const a = { ...attrs };
    const handlers = {};
    return {
        id, value, required: false, attrs: a, classes: new Set(),
        classList: { add(c) { this._s.add(c); }, remove(c) { this._s.delete(c); }, toggle(c, on) { if (on) this._s.add(c); else this._s.delete(c); }, contains(c) { return this._s.has(c); }, _s: null },
        getAttribute: (k) => (k in a ? a[k] : null),
        setAttribute: (k, v) => { a[k] = String(v); },
        removeAttribute: (k) => { delete a[k]; },
        addEventListener: (type, fn) => { handlers[type] = fn; },
        fire: (type) => handlers[type] && handlers[type](),
    };
}

function form({ nationality = '', residency = '', mode = 'required' } = {}) {
    const els = {};
    const make = (id, value, attrs) => {
        const el = field(id, value, attrs);
        el.classList._s = el.classes;
        els[id] = el;
        return el;
    };
    make('nationality', nationality, { 'data-required-attribute': mode });
    make('residency_status', residency);
    make('national_id', '');
    make('passport', '');
    make('work_permit_number', '');
    make('work_permit_expiry', '');
    make('permitFields', '');
    make('residencyNote', '');
    if (mode === 'data-required') {
        els.national_id.setAttribute('data-required', 'true');
    } else {
        els.national_id.required = true;
    }
    // labels, each with the red star the page printed (national_id only)
    const labels = {};
    ['national_id', 'passport', 'work_permit_number', 'work_permit_expiry'].forEach((id) => {
        const stars = [];
        const printed = id === 'national_id' ? { classList: { _s: new Set(), toggle(c, on) { if (on) this._s.add(c); else this._s.delete(c); } } } : null;
        labels[id] = {
            stars,
            printed,
            querySelector: (sel) => (sel === '.js-required-star' ? stars[0] || null : sel.startsWith('.text-danger:not') ? printed : null),
            appendChild: (node) => { node.remove = () => stars.splice(stars.indexOf(node), 1); stars.push(node); },
        };
    });
    const doc = {
        getElementById: (id) => els[id] || null,
        querySelector: (sel) => { const m = /label\[for="([^"]+)"\]/.exec(sel); return m ? labels[m[1]] || null : null; },
        createElement: () => ({ className: '', textContent: '' }),
    };
    return { doc, els, labels };
}

const hasStar = (labels, id) => labels[id].stars.length > 0;

// ---- what the country asks for -----------------------------------------------------------------------------------------

test('Kenya asks for the National ID and sets Resident; another country asks for the permit instead', () => {
    assert.deepEqual(plain(C.rulesFor('Kenya')), { known: true, home: true, nationalIdRequired: true, permitRequired: false, defaultResidency: 'RESIDENT' });
    assert.deepEqual(plain(C.rulesFor(' kenya ')), plain(C.rulesFor('Kenya')));
    assert.deepEqual(plain(C.rulesFor('Uganda')), { known: true, home: false, nationalIdRequired: false, permitRequired: true, defaultResidency: null });
});

test('before a country is chosen the form behaves as it always did', () => {
    assert.deepEqual(plain(C.rulesFor('')), { known: false, home: false, nationalIdRequired: true, permitRequired: false, defaultResidency: null });
    assert.equal(C.rulesFor(null).nationalIdRequired, true);
});

// ---- the form ----------------------------------------------------------------------------------------------------------

test('a page without these fields is left alone', () => {
    assert.equal(C.attach({ getElementById: () => null }), null);
});

test('a saved record from another country shows the permit fields and asks for them, without changing what is saved', () => {
    const { doc, els, labels } = form({ nationality: 'Uganda', residency: 'NON_RESIDENT' });
    C.attach(doc);

    assert.equal(els.permitFields.classes.has('d-none'), false);
    assert.equal(els.passport.required, true);
    assert.equal(els.work_permit_number.required, true);
    assert.equal(els.work_permit_expiry.required, true);
    assert.equal(els.national_id.required, false, 'the National ID is optional for them');
    assert.equal(hasStar(labels, 'passport'), true);
    assert.equal(els.residency_status.value, 'NON_RESIDENT', 'the saved residency is not touched');
    assert.match(els.residencyNote.textContent, /work permit/);
});

test('a saved Kenyan record hides the permit fields and needs the National ID', () => {
    const { doc, els } = form({ nationality: 'Kenya', residency: 'RESIDENT' });
    C.attach(doc);

    assert.equal(els.permitFields.classes.has('d-none'), true);
    assert.equal(els.national_id.required, true);
    assert.equal(els.passport.required, false);
    assert.equal(els.residency_status.value, 'RESIDENT');
});

test('choosing Kenya sets Resident, and choosing another country afterwards asks HR to choose', () => {
    const { doc, els } = form();
    C.attach(doc);

    els.nationality.value = 'Kenya';
    els.nationality.fire('input');
    assert.equal(els.residency_status.value, 'RESIDENT');
    assert.equal(els.residency_status.getAttribute('data-auto'), '1');

    els.nationality.value = 'Uganda';
    els.nationality.fire('input');
    assert.equal(els.residency_status.value, '', 'it was only set for Kenya, so HR has to choose');
    assert.equal(els.permitFields.classes.has('d-none'), false);
    assert.equal(els.work_permit_expiry.required, true);
    assert.equal(els.national_id.required, false);
});

test('a residency HR chose is kept when the country changes', () => {
    const { doc, els } = form();
    C.attach(doc);
    els.nationality.value = 'Kenya';
    els.nationality.fire('input');

    els.residency_status.value = 'NON_RESIDENT'; // HR changes it
    els.residency_status.fire('change');
    els.nationality.value = 'Uganda';
    els.nationality.fire('input');

    assert.equal(els.residency_status.value, 'NON_RESIDENT');
});

test('Kenya does not overwrite a residency that is already chosen and not automatic', () => {
    const { doc, els } = form({ residency: 'NON_RESIDENT' });
    C.attach(doc);

    els.nationality.value = 'Kenya';
    els.nationality.fire('input');

    assert.equal(els.residency_status.value, 'NON_RESIDENT');
});

test('Edit Staff marks required fields with data-required, and the red star follows the rule', () => {
    const { doc, els, labels } = form({ nationality: 'Kenya', residency: 'RESIDENT', mode: 'data-required' });
    C.attach(doc);
    assert.equal(els.national_id.getAttribute('data-required'), 'true');
    assert.equal(els.passport.getAttribute('data-required'), null);

    els.nationality.value = 'Uganda';
    els.nationality.fire('change');

    assert.equal(els.national_id.getAttribute('data-required'), null, 'no longer required');
    assert.equal(els.passport.getAttribute('data-required'), 'true');
    assert.equal(els.work_permit_expiry.getAttribute('data-required'), 'true');
    assert.equal(hasStar(labels, 'work_permit_expiry'), true);
    assert.equal(labels.national_id.printed.classList._s.has('d-none'), true, 'the printed star beside National ID is hidden');
});

test('going back to Kenya hides the permit fields and drops their stars', () => {
    const { doc, els, labels } = form({ nationality: 'Uganda', residency: 'NON_RESIDENT' });
    C.attach(doc);

    els.nationality.value = 'Kenya';
    els.nationality.fire('input');

    assert.equal(els.permitFields.classes.has('d-none'), true);
    assert.equal(hasStar(labels, 'passport'), false);
    assert.equal(els.passport.required, false);
    assert.equal(els.national_id.required, true);
});
