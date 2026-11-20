import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/signed-contract.js', import.meta.url), 'utf8');
const root = {};
vm.runInNewContext(source, { window: root });
const S = root.SignedContract;

// A stand-in for a page element
function el(attrs = {}, classes = []) {
    const a = { ...attrs };
    const set = new Set(classes);
    const handlers = {};
    return {
        textContent: '', value: 'x', disabled: false,
        classList: {
            add: (c) => set.add(c), remove: (c) => set.delete(c), contains: (c) => set.has(c),
            toggle: (c, on) => { if (on === undefined ? !set.has(c) : on) set.add(c); else set.delete(c); },
        },
        getAttribute: (k) => (k in a ? a[k] : null),
        setAttribute: (k, v) => { a[k] = v; },
        addEventListener: (type, fn) => { handlers[type] = fn; },
        click: () => handlers.click && handlers.click(),
        attrs: a, set,
    };
}

function page({ onRecord = null, canRemove = true } = {}) {
    const els = {
        signed_contract_card: el({ 'data-documents-url': '/documents' }, onRecord ? [] : ['d-none']),
        signed_contract: el({}, []),
        signed_contract_upload: el({}, onRecord ? ['d-none'] : []),
        signed_contract_name: el(),
        signed_contract_meta: el(),
        signed_contract_note: el({}, ['d-none']),
        signed_contract_view: el({ href: '#' }),
        signed_contract_remove: canRemove ? el({ 'data-document-id': onRecord ? String(onRecord.id) : '' }) : null,
    };
    if (onRecord) {
        els.signed_contract_name.textContent = onRecord.name;
        els.signed_contract.disabled = true;
    }
    return { els, doc: { getElementById: (id) => els[id] || null } };
}

test('the date and the uploader are described in words, whichever is known', () => {
    assert.equal(S.describe({ uploaded: '12 Nov 2026', by: 'Hannah' }), 'Uploaded 12 Nov 2026 by Hannah');
    assert.equal(S.describe({ uploaded: '12 Nov 2026' }), 'Uploaded 12 Nov 2026');
    assert.equal(S.describe({ by: 'Hannah' }), 'Uploaded by Hannah');
    assert.equal(S.describe({}), '');
    assert.equal(S.describe(null), '');
});

test('the view and remove addresses are built from the documents address', () => {
    assert.equal(S.viewUrl('/documents', 12), '/documents/12/view');
    assert.equal(S.viewUrl('/documents/', 12), '/documents/12/view');
    assert.equal(S.removeUrl('/documents', 12), '/documents/12');
});

test('a page with no box is left alone', () => {
    assert.equal(S.attach({ getElementById: () => null }), null);
});

test('showing a file on record fills the card, hides the upload button and empties and disables the file box', () => {
    const { els, doc } = page();
    const box = S.attach(doc, {});

    box.show({ id: 12, name: 'signed.pdf', uploaded: '12 Nov 2026', by: 'Hannah' });

    assert.equal(els.signed_contract_card.set.has('d-none'), false);
    assert.equal(els.signed_contract_name.textContent, 'signed.pdf');
    assert.equal(els.signed_contract_meta.textContent, 'Uploaded 12 Nov 2026 by Hannah');
    assert.equal(els.signed_contract_view.attrs.href, '/documents/12/view');
    assert.equal(els.signed_contract_upload.set.has('d-none'), true);
    assert.equal(els.signed_contract.disabled, true, 'a disabled box is not sent, so the contract keeps the file on record');
    assert.equal(els.signed_contract.value, '');
});

test('the note about saving shows only for a file that is attached when the form is saved', () => {
    const { els, doc } = page();
    const box = S.attach(doc, {});

    box.show({ id: 1, name: 'a.pdf', attachedOnSave: true });
    assert.equal(els.signed_contract_note.set.has('d-none'), false);

    box.show({ id: 2, name: 'b.pdf' });
    assert.equal(els.signed_contract_note.set.has('d-none'), true);
});

test('the upload button comes back when the file is gone', () => {
    const { els, doc } = page({ onRecord: { id: 12, name: 'signed.pdf' } });
    const box = S.attach(doc, {});

    box.showUpload();

    assert.equal(els.signed_contract_card.set.has('d-none'), true);
    assert.equal(els.signed_contract_upload.set.has('d-none'), false);
    assert.equal(els.signed_contract.disabled, false);
    assert.equal(box.current(), null);
});

test('Remove asks first and does nothing when the answer is no', () => {
    const { els, doc } = page({ onRecord: { id: 12, name: 'signed.pdf' } });
    const asked = [];
    const calls = [];
    S.attach(doc, { confirm: (text) => { asked.push(text); return false; }, ajax: (o) => calls.push(o) });

    els.signed_contract_remove.click();

    assert.equal(asked.length, 1);
    assert.match(asked[0], /^Remove this contract\?/);
    assert.equal(calls.length, 0);
    assert.equal(els.signed_contract_card.set.has('d-none'), false);
});

test('a confirmed Remove sends a delete for that document and then shows the upload button', () => {
    const { els, doc } = page({ onRecord: { id: 12, name: 'signed.pdf' } });
    const calls = [];
    const notices = [];
    const removed = [];
    S.attach(doc, {
        confirm: () => true, token: 'tok',
        ajax: (o) => { calls.push(o); },
        notify: (kind, text) => notices.push([kind, text]),
        onRemoved: (file) => removed.push(file),
    });

    els.signed_contract_remove.click();

    assert.equal(calls.length, 1);
    assert.equal(calls[0].url, '/documents/12');
    assert.equal(calls[0].method, 'DELETE');
    assert.equal(calls[0].headers['X-CSRF-TOKEN'], 'tok');
    assert.equal(els.signed_contract_remove.disabled, true, 'a second click is not possible while it is being removed');

    calls[0].success({ success: true });
    calls[0].complete();

    assert.equal(els.signed_contract_card.set.has('d-none'), true);
    assert.equal(els.signed_contract_upload.set.has('d-none'), false);
    assert.equal(els.signed_contract.disabled, false);
    assert.equal(removed.length, 1);
    assert.equal(removed[0].id, '12');
    assert.deepEqual(notices[0][0], 'success');
    assert.equal(els.signed_contract_remove.disabled, false);
});

test('a refused or failed Remove leaves the card as it was and says why', () => {
    const { els, doc } = page({ onRecord: { id: 12, name: 'signed.pdf' } });
    const calls = [];
    const notices = [];
    S.attach(doc, { confirm: () => true, ajax: (o) => calls.push(o), notify: (kind, text) => notices.push([kind, text]) });

    els.signed_contract_remove.click();
    calls[0].error({ status: 403 });
    calls[0].complete();
    els.signed_contract_remove.click();
    calls[1].error({ status: 500 });
    calls[1].complete();

    assert.equal(els.signed_contract_card.set.has('d-none'), false);
    assert.equal(els.signed_contract_upload.set.has('d-none'), true);
    assert.match(notices[0][1], /permission/);
    assert.equal(notices[0][0], 'error');
    assert.match(notices[1][1], /could not be removed/);
});

test('a page without a Remove button (no permission) still shows and hides without error', () => {
    const { els, doc } = page({ onRecord: { id: 12, name: 'signed.pdf' }, canRemove: false });
    const box = S.attach(doc, {});

    assert.equal(box.current(), null, 'nothing to remove, so nothing is tracked');
    box.show({ id: 13, name: 'b.pdf' });
    assert.equal(els.signed_contract_name.textContent, 'b.pdf');
});
