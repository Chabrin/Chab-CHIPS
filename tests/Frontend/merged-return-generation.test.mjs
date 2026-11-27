import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const view = readFileSync(new URL('../../resources/views/property/returns/generate-returns.blade.php', import.meta.url), 'utf8');
const script = view.match(/<script>([\s\S]*?)<\/script>/)[1].replace(/\{\{[\s\S]*?\}\}/g, '/test-endpoint');

function page() {
    const elements = new Map(), requests = [];
    let shown = false;
    class Element {
        constructor(key) { this.key = key; this.props = {}; this.attrs = {}; this.events = {}; this.value = ''; this.content = ''; this.children = []; }
        select2() { return this; }
        next() { return $(this.key + '-control'); }
        find(selector) {
            if (selector === ':selected') {
                const item = $(this.key + '-selected');
                item.content = this.value;
                return item;
            }
            return $(this.key + selector);
        }
        attr(name, value) { if (value === undefined) return this.attrs[name]; this.attrs[name] = value; return this; }
        prop(name, value) { if (value === undefined) return this.props[name]; this.props[name] = value; return this; }
        val(value) { if (value === undefined) return this.value; this.value = value; return this; }
        text(value) { if (value === undefined) return this.content; this.content = value; return this; }
        on(name, callback) { this.events[name] = callback; return this; }
        trigger(name) { this.events[name]?.({ preventDefault() {} }); return this; }
        empty() { this.children = []; return this; }
        appendTo(target) { target.children.push(this.content); return this; }
        add(other) {
            const group = [this, ...(other.group || [other])];
            return { group, add(next) { return Element.prototype.add.call(this, next); },
                on(name, callback) { group.flatMap(item => item.group || [item]).forEach(item => item.on(name, callback)); return this; },
                prop(name, value) { group.flatMap(item => item.group || [item]).forEach(item => item.prop(name, value)); return this; } };
        }
    }
    function $(key) {
        if (typeof key === 'function') { key(); return; }
        if (typeof key === 'string' && key.startsWith('<')) return new Element(key);
        if (!elements.has(key)) elements.set(key, new Element(key));
        return elements.get(key);
    }
    $('#return_type').val('main');
    $('#property_selection').val('one');
    $('#property_select').val('1');
    $('#returns_month').val('2026-11').attr('min', '2025-11').attr('max', '2026-12');
    $('#generateReturnsForm[name="_token"]').val('csrf');
    $.ajax = options => {
        const callbacks = {};
        const request = { options, done(callback) { callbacks.done = callback; return this; },
            fail(callback) { callbacks.fail = callback; return this; }, always(callback) { callbacks.always = callback; return this; },
            resolve(response) { callbacks.done?.(response); callbacks.always?.(); },
            reject(message) { callbacks.fail?.({ responseJSON: { message } }); callbacks.always?.(); } };
        requests.push(request);
        return request;
    };
    vm.runInNewContext(script, { $, Intl, Date, document: { getElementById() { return {}; } },
        bootstrap: { Modal: class { show() { shown = true; } } } });
    return { $, requests, shown: () => shown,
        selectType(type) { $('#return_type').val(type).trigger('change'); },
        review() { $('#generateReturnsForm').trigger('submit'); },
        generate() { $('#generate-btn').trigger('click'); } };
}

const state = ready => ({ success: true, ready, batch_reference: 'BATCH-1', approved: ready ? 2 : 1, total: 2,
    preview_token: 'a'.repeat(64), properties: [{ name: 'Property A', status: 'Approved' },
        { name: 'Property B', status: ready ? 'Approved' : 'awaiting approval level 2' }],
    blockers: ready ? [] : ['Property B: awaiting approval level 2'], rent_net: 300, utility_net: 0, deductions: 25, net: 275 });

test('merged mode restricts selection to a single property identifying its batch', () => {
    const p = page();
    p.$('#property_selection').val('all');
    p.selectType('merged');
    assert.equal(p.$('#property_selection').val(), 'one');
    assert.equal(p.$('#property_selectionoption[value="all"]').prop('disabled'), true);
    assert.match(p.$('#return_type_help').text(), /Every required individual return/);
});

test('an incomplete batch shows outstanding approvals and disables generation', () => {
    const p = page();
    p.selectType('merged'); p.review();
    assert.equal(p.requests.length, 1);
    assert.equal(p.requests[0].options.method, undefined, 'Review uses a read-only GET.');
    p.requests[0].resolve(state(false));
    assert.equal(p.shown(), true);
    assert.equal(p.$('#generate-btn').prop('disabled'), true);
    assert.match(p.$('#generation_error').text(), /Property B: awaiting approval level 2/);
    p.generate();
    assert.equal(p.requests.length, 1, 'Blocked batches cannot submit a generation request.');
});

test('a ready batch previews deductions and submits its review token only once', () => {
    const p = page();
    p.selectType('merged'); p.review();
    p.requests[0].resolve(state(true));
    assert.equal(p.$('#merge_net').text(), '275.00');
    assert.equal(p.$('#merge_deductions').text(), '25.00');
    assert.equal(p.$('#generate-btn').prop('disabled'), false);
    p.generate(); p.generate();
    assert.equal(p.requests.length, 2);
    assert.equal(p.requests[1].options.method, 'POST');
    assert.equal(p.requests[1].options.data.return_type, 'merged');
    assert.equal(p.requests[1].options.data.preview_token, 'a'.repeat(64));
});

test('a readiness error makes no generation request and restores the review button', () => {
    const p = page();
    p.selectType('merged'); p.review();
    p.requests[0].reject('Choose a configured batch.');
    assert.equal(p.shown(), false);
    assert.equal(p.$('#review_returns').prop('disabled'), false);
    assert.equal(p.$('#return_form_error').text(), 'Choose a configured batch.');
});

test('main generation stays independent and does not request a batch merge', () => {
    const p = page();
    p.review();
    assert.equal(p.requests.length, 0);
    assert.doesNotMatch(p.$('#review_note').text(), /linked properties are generated together/);
    p.generate();
    assert.equal(p.requests[0].options.data.return_type, 'main');
    assert.equal(p.requests[0].options.data.preview_token, undefined);
});
