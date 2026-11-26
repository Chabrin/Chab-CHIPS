import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const view = readFileSync(new URL('../../resources/views/property/clients/details.blade.php', import.meta.url), 'utf8');
const start = view.indexOf('// Keep the saved choice separate');
const end = view.indexOf('function loadReturnsConfigOptions()', start);
const script = view.slice(start, end)
    .replace(/@json\([^\n]+\)/g, '"with_rent"')
    .replace(/\{\{[^\n]+?\}\}/g, 'test-endpoint');

function page() {
    const radios = ['with_rent', 'separate_report', 'separate_return'].map(value => ({ value, checked: value === 'with_rent', disabled: false }));
    const status = { message: '', classes: {} };
    const document = {}, window = {}, handlers = {}, requests = [];
    function $(target) {
        if (target === document || target === window) return { on(name, selector, callback) { handlers[name] = callback || selector; } };
        if (target === '#utilityReturnsSaveStatus') return {
            text(message) { status.message = message; return this; },
            toggleClass(name, active) { status.classes[name] = active; return this; },
        };
        const elements = typeof target === 'string' ? radios : [target];
        return {
            each(callback) { elements.forEach(element => callback.call(element)); return this; },
            prop(name, value) { elements.forEach(element => { element[name] = value; }); return this; },
            val() { return elements[0].value; },
        };
    }
    $.ajax = request => { requests.push(request); };
    const context = { $, document, window, toastr: { success() {}, error() {} }, console, loadReturnsConfigOptions() {}, renderReturnsBatchCards() {} };
    vm.runInNewContext(script, context);
    return {
        context, requests, status, handlers, radios,
        selected: () => radios.find(radio => radio.checked)?.value,
        load: () => context.loadReturnsConfig(),
        change(mode) {
            radios.forEach(radio => { radio.checked = radio.value === mode; });
            handlers.change.call(radios.find(radio => radio.value === mode));
            return requests.at(-1);
        },
        saved(request, mode) { request.success({ utility_returns: mode }); request.complete(); },
    };
}

test('a late initial GET cannot reset a newer successfully saved choice', () => {
    const p = page();
    p.load();
    const oldLoad = p.requests[0];
    const save = p.change('separate_return');
    p.saved(save, 'separate_return');
    oldLoad.success({ data: [], utility_returns: 'with_rent' });
    assert.equal(p.selected(), 'separate_return');
    assert.match(p.status.message, /^Saved/);
});

test('loads started before or during a save cannot override the pending choice', () => {
    const p = page();
    p.load();
    const save = p.change('separate_report');
    p.requests[0].success({ utility_returns: 'with_rent' });
    p.load();
    const duringSave = p.requests.at(-1);
    p.saved(save, 'separate_report');
    duringSave.success({ utility_returns: 'with_rent' });
    assert.equal(p.selected(), 'separate_report');
});

test('failed saves restore the confirmed choice, show an error, and allow retry', () => {
    const p = page();
    p.saved(p.change('separate_report'), 'separate_report');
    const save = p.change('separate_return');
    save.error({ responseJSON: { message: 'Failed' } });
    save.complete();
    assert.equal(p.selected(), 'separate_report');
    assert.equal(p.status.classes['text-danger'], true);
    assert.ok(p.radios.every(radio => !radio.disabled));
    p.saved(p.change('separate_return'), 'separate_return');
    assert.equal(p.selected(), 'separate_return');
    assert.equal(p.status.classes['text-danger'], false);
});

test('only the latest uncached load updates the choice, and loading failures can be retried', () => {
    const p = page();
    p.load();
    const first = p.requests[0];
    first.error({ responseText: 'network error' });
    p.load();
    const second = p.requests.at(-1);
    assert.equal(second.cache, false);
    second.success({ utility_returns: 'separate_return' });
    first.success({ utility_returns: 'with_rent' });
    assert.equal(p.selected(), 'separate_return');
});

test('navigation warns while saving, and browser Back refreshes the saved setting', () => {
    const p = page();
    let prevented = false;
    const event = { originalEvent: {}, preventDefault() { prevented = true; } };
    const save = p.change('separate_report');
    assert.ok(p.radios.every(radio => radio.disabled));
    p.handlers.beforeunload(event);
    assert.equal(prevented, true);
    p.saved(save, 'separate_report');
    prevented = false;
    p.handlers.beforeunload(event);
    assert.equal(prevented, false);
    p.handlers.pageshow({ originalEvent: { persisted: true } });
    p.requests.at(-1).success({ utility_returns: 'separate_return' });
    assert.equal(p.selected(), 'separate_return');
});

test('initial radios use the saved client value and every tab visit reloads configuration', () => {
    for (const mode of ['with_rent', 'separate_report', 'separate_return']) {
        const input = view.match(new RegExp(`<input[^\\n]+value="${mode}"[^\\n]+`))?.[0];
        assert.ok(input?.includes('@checked('));
        assert.ok(input.includes('$client->utility_returns'));
    }
    const tabHandler = view.slice(view.indexOf("$('#returns-config-tab').on('click'"));
    assert.match(tabHandler.slice(0, tabHandler.indexOf('});')), /loadReturnsConfig\(\)/);
    assert.ok(!view.includes('returnsConfigLoaded'));
});
