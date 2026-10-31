import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../public/assets/js/handover-employee-picker.js', import.meta.url), 'utf8');

class PickerEvent {
    constructor(type, properties = {}) { this.type = type; Object.assign(this, properties); }
    preventDefault() { this.defaultPrevented = true; }
    stopPropagation() { this.propagationStopped = true; }
}

class Element {
    constructor(tag = 'div') {
        this.tagName = tag.toUpperCase();
        this.children = [];
        this.events = new Map();
        this.jqueryEvents = [];
        this.attributes = new Map();
        this.value = '';
        this.textContent = '';
        this.hidden = false;
        this.validityMessage = '';
        this.classes = new Set();
        this.classList = { toggle: (name, on) => on ? this.classes.add(name) : this.classes.delete(name) };
    }
    set innerHTML(value) { throw new Error('Employee names must be rendered as text, not HTML: ' + value); }
    addEventListener(type, callback) {
        if (!this.events.has(type)) this.events.set(type, []);
        this.events.get(type).push(callback);
    }
    dispatchEvent(event) {
        event.target ??= this;
        for (const callback of this.events.get(event.type) || []) callback(event);
        for (const handler of this.jqueryEvents.filter(handler => handler.type === event.type)) handler.callback(event);
        return !event.defaultPrevented;
    }
    emit(type, properties = {}) { const event = new PickerEvent(type, properties); this.dispatchEvent(event); return event; }
    setAttribute(key, value) { this.attributes.set(key, String(value)); }
    removeAttribute(key) { this.attributes.delete(key); }
    getAttribute(key) { return this.attributes.get(key) ?? null; }
    setCustomValidity(message) { this.validityMessage = message; }
    replaceChildren(...children) { this.children = []; children.forEach(child => this.appendChild(child)); }
    appendChild(child) { child.parent = this; this.children.push(child); }
    contains(element) { return element === this || this.children.some(child => child.contains(element)); }
    focus() { this.focused = true; this.emit('focus'); }
    scrollIntoView() {}
}

function fixture({ restoredValue = '', defaultValue = '', employees } = {}) {
    const document = new Element('document');
    const picker = new Element();
    const form = new Element('form');
    const modal = new Element();
    const select = new Element('select');
    const input = new Element('input');
    const clear = new Element('button');
    const results = new Element();
    const hint = new Element();
    const search = new Element();
    const label = new Element('label');
    input.id = 'handover_search';
    results.id = 'handover_results';
    select.options = [{ value: '', textContent: 'Choose an employee...' }, ...(employees || [
        { value: '11', textContent: 'ALEXANDER WAMBUA MUSAU' },
        { value: '12', textContent: 'Alex Muthoni' },
        { value: '13', textContent: 'Elaine Njeri' },
    ])].map(option => ({ disabled: false, ...option, defaultSelected: option.value === defaultValue }));
    select.value = restoredValue;
    select.form = form;
    const selectors = {
        select, label, '[data-handover-input]': input, '[data-handover-clear]': clear,
        '[data-handover-results]': results, '[data-handover-hint]': hint, '[data-handover-search]': search,
    };
    picker.querySelector = selector => selectors[selector] ?? null;
    picker.closest = selector => selector === '.modal' ? modal : null;
    Object.values(selectors).forEach(element => picker.appendChild(element));
    document.querySelectorAll = selector => selector === '[data-handover-picker]' ? [picker] : [];
    document.createElement = tag => new Element(tag);
    const timers = [];
    const jquery = element => ({
        on(eventNames, callback) {
            for (const name of eventNames.split(/\s+/)) {
                const [type, ...namespaces] = name.split('.');
                element.jqueryEvents.push({ type, namespaces, callback });
            }
            return this;
        },
        trigger(name) {
            const [type, ...namespaces] = name.split('.');
            const event = new PickerEvent(type, { target: element });
            // jQuery namespaced triggers only call handlers containing every requested namespace.
            for (const handler of element.jqueryEvents.filter(handler => handler.type === type && namespaces.every(namespace => handler.namespaces.includes(namespace)))) {
                handler.callback(event);
            }
            return this;
        },
    });
    const window = { document, Event: PickerEvent, jQuery: jquery, setTimeout(callback) { timers.push(callback); } };
    vm.runInNewContext(source, { window });
    document.emit('DOMContentLoaded');
    const flushTimers = () => { while (timers.length) timers.shift()(); };
    const query = value => { input.value = value; input.emit('input'); };
    const key = name => input.emit('keydown', { key: name });
    return { document, picker, form, modal, select, input, clear, results, hint, search, label, jquery, query, key, flushTimers };
}

test('a fresh form starts empty even when the browser restored the first employee', () => {
    const ui = fixture({ restoredValue: '11' });
    assert.equal(ui.select.value, '');
    assert.equal(ui.input.value, '');
    assert.equal(ui.clear.hidden, true);
    assert.equal(ui.results.hidden, true);
    assert.equal(ui.select.hidden, true);
    assert.equal(ui.search.hidden, false);
    assert.equal(ui.label.htmlFor, ui.input.id);
});

test('typing filters names but never chooses an employee or selects the first match on Enter', () => {
    const ui = fixture();
    ui.query('a');
    assert.equal(ui.results.hidden, true);
    ui.query('alex');
    assert.deepEqual(ui.results.children.map(result => result.textContent), ['ALEXANDER WAMBUA MUSAU', 'Alex Muthoni']);
    assert.equal(ui.select.value, '');
    assert.equal(ui.input.getAttribute('aria-activedescendant'), null);
    assert.match(ui.input.validityMessage, /Choose an employee/);
    assert.equal(ui.key('Enter').defaultPrevented, true);
    assert.equal(ui.select.value, '');
    assert.equal(ui.input.value, 'alex');
});

test('an explicit result click chooses the corresponding employee and clearing stays empty', () => {
    const ui = fixture();
    ui.query('alex');
    ui.results.children[1].emit('click');
    assert.equal(ui.select.value, '12');
    assert.equal(ui.input.value, 'Alex Muthoni');
    assert.equal(ui.input.validityMessage, '');
    assert.equal(ui.results.hidden, true);
    assert.equal(ui.clear.hidden, false);
    ui.clear.emit('click');
    assert.equal(ui.select.value, '');
    assert.equal(ui.input.value, '');
    assert.equal(ui.input.validityMessage, '');
    assert.equal(ui.clear.hidden, true);
    ui.input.emit('focus');
    ui.key('Enter');
    assert.equal(ui.select.value, '');
    assert.equal(ui.results.hidden, true);
});

test('ArrowDown and Enter explicitly select a result, while Escape closes without selecting', () => {
    const ui = fixture();
    ui.query('alex');
    ui.key('ArrowDown');
    ui.key('ArrowDown');
    assert.equal(ui.results.children[1].getAttribute('aria-selected'), 'true');
    assert.equal(ui.input.getAttribute('aria-activedescendant'), ui.results.children[1].id);
    ui.key('Enter');
    assert.equal(ui.select.value, '12');
    ui.clear.emit('click');
    ui.query('elaine');
    assert.equal(ui.key('Escape').propagationStopped, true);
    assert.equal(ui.results.hidden, true);
    ui.key('Enter');
    assert.equal(ui.select.value, '');
});

test('editing a chosen name immediately discards its id and requires a new explicit choice', () => {
    const ui = fixture({ defaultValue: '12', restoredValue: '11' });
    assert.equal(ui.select.value, '12');
    assert.equal(ui.input.value, 'Alex Muthoni');
    ui.query('Elaine');
    assert.equal(ui.select.value, '');
    assert.match(ui.input.validityMessage, /Choose an employee/);
    ui.results.children[0].emit('click');
    assert.equal(ui.select.value, '13');
    assert.equal(ui.input.validityMessage, '');
    ui.query('');
    assert.equal(ui.select.value, '');
    assert.equal(ui.input.validityMessage, '');
});

test('disabled applicants are excluded, including a result disabled after search', () => {
    const ui = fixture();
    ui.select.options.find(option => option.value === '11').disabled = true;
    ui.query('alex');
    assert.deepEqual(ui.results.children.map(result => result.textContent), ['Alex Muthoni']);
    ui.select.options.find(option => option.value === '12').disabled = true;
    ui.results.children[0].emit('click');
    assert.equal(ui.select.value, '');
    ui.query('alex');
    assert.equal(ui.results.hidden, true);
    assert.match(ui.hint.textContent, /No matching employees/);
});

test('programmatic jQuery changes and native form reset update the displayed selection', () => {
    const ui = fixture();
    ui.select.value = '13';
    ui.jquery(ui.select).trigger('change');
    assert.equal(ui.input.value, 'Elaine Njeri');
    ui.form.emit('reset');
    // Native reset applies defaults after the reset event, before the queued synchronization.
    ui.select.value = '';
    ui.flushTimers();
    assert.equal(ui.input.value, '');
    assert.equal(ui.clear.hidden, true);
    assert.equal(ui.input.validityMessage, '');
    ui.select.value = '11';
    ui.select.options.find(option => option.value === '11').disabled = true;
    ui.jquery(ui.select).trigger('change');
    assert.equal(ui.input.value, '');
    assert.equal(ui.select.value, '');
});

test('unmatched names explain the result and employee markup is displayed as plain text', () => {
    const ui = fixture({ employees: [{ value: '14', textContent: '<img src=x onerror=attack()> Élaine Njeri' }] });
    ui.query('Nobody');
    assert.equal(ui.results.children.length, 0);
    assert.equal(ui.results.hidden, true);
    assert.match(ui.hint.textContent, /No matching employees/);
    ui.query('elaine njeri');
    assert.equal(ui.results.children.length, 1);
    assert.equal(ui.results.children[0].textContent, '<img src=x onerror=attack()> Élaine Njeri');
    assert.equal(ui.results.children[0].children.length, 0);
    ui.results.children[0].emit('click');
    assert.equal(ui.select.value, '14');
    assert.equal(ui.input.value, '<img src=x onerror=attack()> Élaine Njeri');
});
