import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/form-actions.js', import.meta.url), 'utf8');

// A fresh copy of the script for each test, with a fake page it can listen to
function setup(formIds = ['contractForm']) {
    const listeners = {};
    const windowListeners = {};
    const buttons = {};
    const forms = {};
    formIds.forEach((id) => {
        forms[id] = { id, tagName: 'FORM', hasAttribute: () => false };
        const label = { textContent: 'Save' };
        const spinner = { classList: { _hidden: true, remove() { this._hidden = false; }, add() { this._hidden = true; } } };
        buttons[id] = { disabled: false, querySelector: (sel) => (sel === '.form-actions-label' ? label : sel === '.spinner-border' ? spinner : null), label, spinner };
    });
    const doc = {
        addEventListener: (type, fn) => { (listeners[type] = listeners[type] || []).push(fn); },
        querySelector: (sel) => {
            const match = /data-form-actions-for="([^"]+)"/.exec(sel);
            return match && forms[match[1]] ? {} : null;
        },
        querySelectorAll: (sel) => {
            const match = /\[form="([^"]+)"\]/.exec(sel);
            return match && buttons[match[1]] ? [buttons[match[1]]] : [];
        },
    };
    const win = { addEventListener: (type, fn) => { (windowListeners[type] = windowListeners[type] || []).push(fn); } };
    vm.runInNewContext(source, { window: win });
    win.FormActions.start(doc);
    const fire = (type, event) => (listeners[type] || []).forEach((fn) => fn(event));
    const leave = () => {
        const event = { prevented: false, returnValue: undefined, preventDefault() { this.prevented = true; } };
        (windowListeners.beforeunload || []).forEach((fn) => fn(event));
        return event;
    };
    // typing in a field of the form
    const type = (id, trusted = true) => fire('input', { isTrusted: trusted, target: { form: forms[id] } });
    return { win, fire, leave, type, forms, buttons };
}

test('leaving with nothing typed is not stopped', () => {
    const { leave } = setup();
    assert.equal(leave().prevented, false);
});

test('leaving after typing in a watched form asks first', () => {
    const { type, leave } = setup();
    type('contractForm');
    assert.equal(leave().prevented, true);
});

test('a value a page fills in by itself is not a change', () => {
    const { type, leave } = setup();
    type('contractForm', false);
    assert.equal(leave().prevented, false);
});

test('typing in a form that has no bar and is not marked is not watched', () => {
    const { fire, leave } = setup();
    fire('input', { isTrusted: true, target: { form: { id: 'searchForm', hasAttribute: () => false } } });
    assert.equal(leave().prevented, false);
});

test('a form marked data-warn-unsaved is watched even without the bar', () => {
    const { fire, leave } = setup();
    fire('input', { isTrusted: true, target: { form: { id: 'staffEditForm', hasAttribute: (name) => name === 'data-warn-unsaved' } } });
    assert.equal(leave().prevented, true);
});

test('sending the form clears the warning, and the button shows Saving…', () => {
    const { type, fire, leave, forms, buttons } = setup();
    type('contractForm');

    fire('submit', { target: forms.contractForm, defaultPrevented: false });

    assert.equal(leave().prevented, false);
    assert.equal(buttons.contractForm.label.textContent, 'Saving…');
    assert.equal(buttons.contractForm.disabled, true);
    assert.equal(buttons.contractForm.spinner.classList._hidden, false);
});

test('a send the page stopped (a check failed) does not lock the button', () => {
    const { type, fire, forms, buttons } = setup();
    type('contractForm');

    fire('submit', { target: forms.contractForm, defaultPrevented: true });

    assert.equal(buttons.contractForm.disabled, false);
    assert.equal(buttons.contractForm.label.textContent, 'Save');
});

test('typing again after a stopped send warns again', () => {
    const { type, fire, leave, forms } = setup();
    type('contractForm');
    fire('submit', { target: forms.contractForm, defaultPrevented: true });
    assert.equal(leave().prevented, false);

    type('contractForm');

    assert.equal(leave().prevented, true);
});

test('two forms are tracked on their own', () => {
    const { type, fire, leave, forms } = setup(['a', 'b']);
    type('a');
    type('b');
    fire('submit', { target: forms.a, defaultPrevented: false });
    assert.equal(leave().prevented, true, 'b is still unsaved');
    fire('submit', { target: forms.b, defaultPrevented: false });
    assert.equal(leave().prevented, false);
});
