import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/views/dashboards/leave-overview.blade.php', import.meta.url), 'utf8')
    .split('<script>')[1].split('</script>')[0]
    .replace("@json(route('dashboard.leave-overview'))", "'/overview'");

function setup(fetch) {
    const elements = new Map();
    const element = () => ({
        value: '', textContent: '', children: [], handlers: {}, selectedIndex: 0,
        classList: { add() {}, remove() {}, toggle() {} },
        appendChild(child) { this.children.push(child); },
        replaceChildren(...children) { this.children = children; this.value = ''; },
        add(child) { this.children.push(child); },
        get options() { return this.children; },
        addEventListener(name, handler) { this.handlers[name] = handler; },
        checkValidity() { return Number(this.value) >= 2000 && Number(this.value) <= 2100; },
    });
    const get = id => { if (!elements.has(id)) elements.set(id, element()); return elements.get(id); };
    get('lo-year').value = '2026';
    vm.runInNewContext(source, {
        document: { addEventListener: (_, callback) => callback(), getElementById: get, createElement: element },
        Option: function(text, value) { this.text = text; this.textContent = text; this.value = String(value); },
        URLSearchParams, fetch, window: {},
    });
    return { get, async ready() { await new Promise(resolve => setTimeout(resolve, 0)); }, async load() {
        get('lo-employee').value = '1'; get('lo-employee').selectedIndex = 1;
        return get('lo-employee').handlers.change();
    } };
}

const payload = {
    categories: [
        {id: 1, name: '<script>example</script>', entitled: 21, approved: 1.5, pending: 1, balance: 19.5, available: 18.5, warnings: []},
        {id: 2, name: 'Sick', entitled: null, approved: 0, pending: 0, balance: null, available: null, warnings: ['Missing setup']},
    ],
    days: [
        {request_id: 1, category_id: 1, category: 'Annual', date: '2026-10-24', days: 0.5, status: 'Approved', tier: 1, pay_rate: 100},
        {request_id: 2, category_id: 2, category: 'Sick', date: '2026-11-01', days: 1, status: 'Pending — preview', tier: null, pay_rate: null},
    ],
};
const ok = data => ({ ok: true, json: async () => data });
const options = { employees: [{id: 1, name: 'Test Employee', number: 'TEST001'}] };

test('category figures render as text and missing values stay unavailable', async () => {
    const ui = setup(async url => ok(url.includes('?') ? payload : options));
    await ui.ready(); await ui.load();
    const rows = ui.get('lo-balances').children;
    assert.equal(rows[0].children[0].textContent, '<script>example</script>');
    assert.equal(rows[0].children[2].textContent, '1.5');
    assert.equal(rows[1].children[1].textContent, '—');
    assert.equal(ui.get('lo-warning-list').children[0].textContent, 'Sick: Missing setup');
});

test('category and month filters show only matching daily rows without changing annual figures', async () => {
    const ui = setup(async url => ok(url.includes('?') ? payload : options));
    await ui.ready(); await ui.load();
    ui.get('lo-month').value = '10'; ui.get('lo-month').handlers.change();
    assert.equal(ui.get('lo-days').children.length, 1);
    assert.equal(ui.get('lo-balances').children.length, 2);
    ui.get('lo-category').value = '2'; ui.get('lo-category').handlers.change();
    assert.equal(ui.get('lo-balances').children.length, 1);
    assert.equal(ui.get('lo-days').children[0].children[0].textContent, 'No leave days match these filters.');
});

test('a failed account request displays an error rather than stale results', async () => {
    const ui = setup(async url => url.includes('?') ? {ok: false} : ok(options));
    await ui.ready(); await ui.load();
    assert.match(ui.get('lo-status').textContent, /Unable to load/);
});

test('stale responses cannot replace the results after changing the selected employee', async () => {
    let resolveOld;
    const ui = setup(url => url.includes('?') ? new Promise(resolve => {resolveOld = resolve;}) : Promise.resolve(ok(options)));
    await ui.ready();
    const pending = ui.load();
    ui.get('lo-employee').value = '';
    await ui.get('lo-employee').handlers.change();
    resolveOld(ok(payload)); await pending;
    assert.equal(ui.get('lo-status').textContent, 'Select an employee to load their leave account.');
    assert.equal(ui.get('lo-balances').children.length, 0);
});
