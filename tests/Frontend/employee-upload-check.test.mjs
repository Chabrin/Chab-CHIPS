import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { webcrypto } from 'node:crypto';

const source = readFileSync(new URL('../../resources/views/staff/partials/upload-duplicate-check.blade.php', import.meta.url), 'utf8')
    .replace('<script>', '').replace('</script>', '')
    .replace("@json(route('hr.uploads.check-duplicates'))", "'/check'");

function setup(fetch) {
    const inputs = ['photo', 'documents[national_id]', 'documents[kra_pin]'].map(name => {
        const notes = [];
        return {
            name, files: [], handlers: {}, message: '',
            parentElement: { querySelector: selector => notes.find(note => selector.includes('"' + note.dataset.duplicateFeedback + '"')) },
            insertAdjacentElement: (_, note) => notes.push(note),
            addEventListener(type, handler) { this.handlers[type] = handler; },
            setCustomValidity(message) { this.message = message; },
            classList: { toggle() {} }, closest: () => null, focus() {}, notes,
        };
    });
    let submit;
    const form = {
        querySelectorAll: () => inputs,
        querySelector: () => ({ value: 'csrf-token' }),
        addEventListener: (_, handler) => { submit = handler; },
    };
    vm.runInNewContext(source, {
        document: {
            addEventListener: (_, handler) => handler(),
            getElementById: id => id === 'staffCreateForm' ? form : null,
            createElement: () => ({ dataset: {}, setAttribute() {} }),
        },
        crypto: webcrypto, fetch, window: {},
    });
    return {
        inputs,
        select(index, contents) {
            inputs[index].files = contents === null ? [] : [{
                size: contents.length,
                arrayBuffer: async () => new TextEncoder().encode(contents).buffer,
            }];
            return inputs[index].handlers.change();
        },
        submit() {
            let blocked = false;
            submit({ preventDefault: () => { blocked = true; }, stopImmediatePropagation() {} });
            return blocked;
        },
    };
}

test('same-form duplicates are blocked, and clearing the duplicate allows save', async () => {
    const ui = setup(async () => ({ ok: true, status: 200, json: async () => ({ valid: true }) }));
    await ui.select(1, 'same contents');
    await ui.select(2, 'same contents');
    assert.match(ui.inputs[2].message, /another upload field/);
    assert.equal(ui.submit(), true);
    await ui.select(2, null);
    assert.equal(ui.inputs[2].message, '');
    assert.equal(ui.submit(), false);
});

test('a stored duplicate produces inline feedback and blocks save', async () => {
    const ui = setup(async () => ({ ok: false, status: 422, json: async () => ({ errors: { photo: ['Already uploaded.'] } }) }));
    await ui.select(0, 'existing image');
    assert.equal(ui.inputs[0].message, 'Already uploaded.');
    assert.equal(ui.submit(), true);
});

test('save waits for preflight and stale responses cannot invalidate a cleared selection', async () => {
    let respond;
    const ui = setup(() => new Promise(resolve => { respond = resolve; }));
    const pending = ui.select(0, 'image');
    while (!respond) await new Promise(resolve => setTimeout(resolve, 1));
    assert.equal(ui.submit(), true);
    await ui.select(0, null);
    respond({ ok: false, status: 422, json: async () => ({ errors: { photo: ['Old error'] } }) });
    await pending;
    assert.equal(ui.inputs[0].message, '');
    assert.equal(ui.submit(), false);
});

test('network failure gives a visible fallback to save-time validation', async () => {
    const ui = setup(async () => { throw new Error('offline'); });
    await ui.select(0, 'new image');
    assert.match(ui.inputs[0].notes[0].textContent, /checked when you save/);
    assert.equal(ui.submit(), false);
});

test('preflight sends fingerprints rather than file contents', async () => {
    let payload;
    const ui = setup(async (_, options) => {
        payload = JSON.parse(options.body);
        assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf-token');
        return { ok: true, status: 200, json: async () => ({ valid: true }) };
    });
    await ui.select(0, 'private image content');
    assert.match(payload.hashes.photo, /^[a-f0-9]{64}$/);
    assert.equal(JSON.stringify(payload).includes('private image content'), false);
});
