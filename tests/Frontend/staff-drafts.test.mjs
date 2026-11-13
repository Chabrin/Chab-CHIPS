import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/staff-drafts.js', import.meta.url), 'utf8');
const root = {};
vm.runInNewContext(source, { window: root });
const D = root.StaffDrafts;

const mary = { id: 12, title: 'MARY WANJIKU', updated_label: '11 Nov 2026, 13:05', files: 2, resume_url: 'http://localhost/hr/employees/create?draft=12' };
const untitled = { id: 13, title: 'Untitled draft', updated_label: '10 Nov 2026, 09:00', files: 0, resume_url: 'http://localhost/hr/employees/create?draft=13' };

test('text is escaped, quotes included, because titles also go into attributes', () => {
    assert.equal(D.escapeHtml('<b>"A" & \'B\'</b>'), '&lt;b&gt;&quot;A&quot; &amp; &#39;B&#39;&lt;/b&gt;');
    assert.equal(D.escapeHtml(null), '');
});

test('only the password boxes are secret', () => {
    assert.equal(D.isSecret('password'), true);
    assert.equal(D.isSecret('password_confirmation'), true);
    for (const name of ['login_email', 'full_names', 'national_id', 'passport', 'pin_number', 'account_number', 'my_password', 'password2']) {
        assert.equal(D.isSecret(name), false, name);
    }
});

test('the files count reads naturally', () => {
    assert.equal(D.filesText(0), '');
    assert.equal(D.filesText(1), ' · 1 file');
    assert.equal(D.filesText(3), ' · 3 files');
});

test('with no drafts the window says how to make one', () => {
    for (const none of [[], null, undefined]) {
        const html = D.listHtml(none, '');
        assert.match(html, /You have no saved drafts/);
        assert.match(html, /<strong>Save as Draft<\/strong>/);
        assert.doesNotMatch(html, /list-group/);
    }
});

test('each draft is a row with its title, when it was saved, Resume and Discard', () => {
    const html = D.listHtml([mary, untitled], '');
    assert.equal((html.match(/data-draft-id="/g) || []).length, 4, 'a row and a Discard button each');
    assert.match(html, /<div class="fw-semibold">MARY WANJIKU<\/div>/);
    assert.match(html, /Last saved 11 Nov 2026, 13:05 · 2 files/);
    assert.match(html, /Last saved 10 Nov 2026, 09:00<\/div>/, 'no files, nothing said about them');
    assert.match(html, /<a class="btn btn-sm btn-primary" href="http:\/\/localhost\/hr\/employees\/create\?draft=12">Resume<\/a>/);
    assert.match(html, /js-discard-draft" data-draft-id="12" aria-label="Discard the draft for MARY WANJIKU">Discard</);
});

test('the draft open on this page is marked', () => {
    assert.match(D.listHtml([mary, untitled], 12), /MARY WANJIKU <span class="badge bg-info">open now<\/span>/);
    assert.match(D.listHtml([mary, untitled], '12'), /open now/, 'the id may come from a box as text');
    assert.equal((D.listHtml([mary, untitled], 12).match(/open now/g) || []).length, 1);
    assert.doesNotMatch(D.listHtml([mary, untitled], ''), /open now/);
    assert.doesNotMatch(D.listHtml([mary, untitled], 99), /open now/);
});

test('names, dates and addresses in the list are escaped', () => {
    const evil = '<img src=x onerror=alert(1)>';
    const html = D.listHtml([{ id: '1"><script>', title: evil, updated_label: evil, files: 1, resume_url: 'javascript:alert("x")' }], '');
    assert.doesNotMatch(html, /<img|<script/);
    assert.match(html, /&lt;img/);
    assert.doesNotMatch(html, /href="javascript:alert\("x"\)"/, 'quotes in an address cannot close the attribute');
});

test('a failed save shows its own message, or the first field problem', () => {
    assert.equal(D.failureMessage({ message: 'There is nothing to save yet. Fill in something first.' }), 'There is nothing to save yet. Fill in something first.');
    assert.equal(D.failureMessage({ message: 'x', errors: { 'documents.cv': ['The cv must be a file of type: pdf, doc, docx.'] } }), 'The cv must be a file of type: pdf, doc, docx.');
    assert.equal(D.failureMessage({ errors: { photo: 'The photo is too big.' } }), 'The photo is too big.');
    assert.equal(D.failureMessage({}), 'The draft could not be saved.');
    assert.equal(D.failureMessage(null), 'The draft could not be saved.');
});

test('a draft that holds a staff number says so, escaped, and one that does not says nothing', () => {
    const html = D.listHtml([{ ...mary, staff_number: 'CT-007' }, { ...untitled, staff_number: '<b>1</b>' }, { id: 14, title: 'NO NUMBER', updated_label: 'x', files: 0, resume_url: '/r' }], '');

    assert.match(html, /Staff No\. CT-007 · Last saved 11 Nov 2026, 13:05 · 2 files/);
    assert.match(html, /Staff No\. &lt;b&gt;1&lt;\/b&gt; · /);
    assert.doesNotMatch(html, /<b>1<\/b>/);
    assert.match(html, /<div class="small text-muted">Last saved x<\/div>/, 'no number, no "Staff No."');
});

// ── Save as Draft and Save & Next, with the page stood in for ──

function harness(responseFor) {
    const sent = [];
    class FormData {
        constructor() { this.values = {}; }
        keys() { return Object.keys(this.values)[Symbol.iterator](); }
        delete(name) { delete this.values[name]; }
        set(name, value) { this.values[name] = value; }
    }
    const classes = new Set(['d-none']);
    const note = { classList: { remove: (name) => classes.delete(name), toggle: (name, force) => (force ? classes.add(name) : classes.delete(name)) } };
    const staffNumber = { value: '' };
    const draftId = { value: '' };
    const form = { dataset: { draftSaveUrl: '/save' }, querySelectorAll: () => [], querySelector: (selector) => (selector === '.tab-pane.active' ? { id: 'employment' } : null), addEventListener() {} };
    const doc = { getElementById: (id) => ({ staffCreateForm: form, draft_id: draftId, staff_number: staffNumber, staffNumberNote: note })[id] || null };
    const win = {
        FormData, URL, addEventListener() {}, location: { href: 'http://localhost/hr/employees/create' }, history: { replaceState() {} },
        StaffFormSteps: { instance: { statuses: () => ({ personal: 'complete', employment: 'pending', user: 'skipped' }) } },
        fetch: async (url, options) => { sent.push({ url, body: options.body.values }); return responseFor(); },
    };
    const sandbox = { FormData };
    vm.runInNewContext(source, { window: sandbox });
    return { instance: sandbox.StaffDrafts.init(doc, win), sent, staffNumber, draftId, noteHidden: () => classes.has('d-none') };
}

const saved = (extra = {}) => async () => ({ ok: true, status: 200, json: async () => ({ success: true, draft_id: 77, staff_number: '121', saved_at_label: '10:05', message: 'Draft saved.', ...extra }) });

test('Save & Next keeps the form as a draft that reopens on the tab it moves to, with every stage', async () => {
    const { instance, sent } = harness(saved());

    assert.equal(await instance.saveStep('documents'), true);

    assert.equal(sent.length, 1);
    assert.equal(sent[0].url, '/save');
    assert.equal(sent[0].body.last_tab, 'documents');
    assert.deepEqual(JSON.parse(sent[0].body.stages), { personal: 'complete', employment: 'pending', user: 'skipped' });
});

test('Save as Draft reopens on the tab that is open', async () => {
    const { instance, sent } = harness(saved());

    assert.equal(await instance.save(), true);

    assert.equal(sent[0].body.last_tab, 'employment');
});

test('a save shows the draft id and the staff number the draft holds, with the note that it can change', async () => {
    const { instance, staffNumber, draftId, noteHidden } = harness(saved({ staff_number: 'CT-004' }));
    assert.equal(noteHidden(), true);

    await instance.saveStep('banking');

    assert.equal(draftId.value, 77);
    assert.equal(staffNumber.value, 'CT-004');
    assert.equal(noteHidden(), false);
});

test('a save that answers without a number leaves the number on the form as it was', async () => {
    const { instance, staffNumber, noteHidden } = harness(saved({ staff_number: null }));
    staffNumber.value = '121';

    await instance.saveStep('banking');

    assert.equal(staffNumber.value, '121');
    assert.equal(noteHidden(), true);
});

test('a refused save answers false and changes nothing on the form', async () => {
    const { instance, staffNumber, draftId } = harness(async () => ({ ok: false, status: 422, json: async () => ({ success: false, message: 'There is nothing to save yet. Fill in something first.' }) }));
    staffNumber.value = '121';

    assert.equal(await instance.saveStep('banking'), false);

    assert.equal(staffNumber.value, '121');
    assert.equal(draftId.value, '');
});

test('a save that never reaches the server answers false', async () => {
    const { instance } = harness(async () => { throw new Error('offline'); });

    assert.equal(await instance.saveStep('banking'), false);
});

test('an expired session answers false so Save & Next stays on the tab', async () => {
    const { instance } = harness(async () => ({ ok: false, status: 419, json: async () => ({}) }));

    assert.equal(await instance.saveStep('banking'), false);
});

test('the form data sent leaves the passwords out and keeps everything else', () => {
    const fields = [['_token', 'abc'], ['full_names', 'Mary'], ['password', 'secret'], ['password_confirmation', 'secret'], ['draft_id', '5'], ['login_email', 'm@x']];
    const deleted = [];
    const data = {
        keys: () => fields.map(([name]) => name)[Symbol.iterator](),
        delete: (name) => { deleted.push(name); },
    };
    class FormData { constructor() { return data; } }
    const sandbox = {}; vm.runInNewContext(source, { window: sandbox });
    sandbox.FormData = FormData;
    const result = sandbox.StaffDrafts.draftFormData({});
    assert.equal(result, data);
    assert.deepEqual(deleted.sort(), ['password', 'password_confirmation']);
});
