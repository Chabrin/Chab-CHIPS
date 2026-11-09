import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/staff-form-steps.js', import.meta.url), 'utf8');
// Stands in for the browser's MutationObserver: the tests call the callbacks when the page would
const observed = [];
const root = { MutationObserver: class { constructor(callback) { observed.push(callback); } observe() {} } };
vm.runInNewContext(source, { window: root });
const api = root.StaffFormSteps;

function element(extra = {}) {
    const classes = new Set(extra.classes || []);
    const listeners = {};
    return {
        classList: { toggle(name, force) { force ? classes.add(name) : classes.delete(name); }, contains: (name) => classes.has(name) },
        addEventListener(type, callback) { listeners[type] = callback; },
        fire(type, event) { listeners[type](event); },
        ...extra,
    };
}

function field(overrides = {}) {
    const input = element({ disabled: false, offsetParent: {}, valid: true, reported: 0, ...overrides });
    input.checkValidity = () => input.valid;
    input.reportValidity = () => { input.reported++; };
    return input;
}

/** The Add Staff form: tabs personal > documents > user, each pane closing with the Next/Save pair. */
function page(fieldsByPane = {}) {
    observed.length = 0;
    const order = ['personal', 'documents', 'user'];
    const items = {}, buttons = [], panes = [];
    const shownTabs = [];
    for (const id of order) {
        items[id] = element({ style: { display: '' } });
        buttons.push(element({ id: id + '-tab', closest: (selector) => (selector === 'li' ? items[id] : null) }));
        const next = element({ classes: [] });
        const save = element({ classes: ['d-none'], closest: () => null, disabled: false, style: { opacity: '' } });
        const note = element({ textContent: '' });
        const pane = element({
            id,
            fields: fieldsByPane[id] || [],
            querySelector: (selector) => ({ '.js-next-step': next, '.js-save-step': save, '.js-step-note': note })[selector],
            querySelectorAll: () => pane.fields,
        });
        next.closest = (selector) => (selector === '.js-next-step' ? next : selector === '.tab-pane' ? pane : null);
        panes.push(Object.assign(pane, { next, save, note }));
    }
    const nav = element({ querySelectorAll: () => buttons, scrolled: 0 });
    const form = element({ querySelectorAll: (selector) => (selector === '.tab-pane' ? panes : selector === '.js-save-step' ? panes.map((pane) => pane.save) : []) });
    const header = element({ disabled: false, style: { opacity: '' } });
    const doc = { getElementById: (id) => ({ staffCreateForm: form, staffCreateTabs: nav, createButton: header })[id] || null };
    api.init(doc, (id) => shownTabs.push(id));
    const click = (target) => form.fire('click', { target });
    const pageChanged = () => observed.forEach((callback) => callback());
    return { items, panes, nav, form, header, shownTabs, click, pageChanged, byId: (id) => panes.find((pane) => pane.id === id) };
}

const tabs = (hiddenIds = []) => ['personal-tab', 'documents-tab', 'user-tab'].map((id) => ({ id, hidden: hiddenIds.includes(id) }));

test('the next tab is the one after the current tab', () => {
    assert.equal(api.nextTab(tabs(), 'personal-tab'), 'documents-tab');
    assert.equal(api.nextTab(tabs(), 'documents-tab'), 'user-tab');
});

test('the last tab has no next tab', () => {
    assert.equal(api.nextTab(tabs(), 'user-tab'), null);
});

test('a hidden tab is skipped, so documents is last for casual staff', () => {
    assert.equal(api.nextTab(tabs(['user-tab']), 'documents-tab'), null);
    assert.equal(api.nextTab(tabs(['documents-tab']), 'personal-tab'), 'user-tab');
});

test('an unknown or hidden current tab has no next tab', () => {
    assert.equal(api.nextTab(tabs(), 'elsewhere-tab'), null);
    assert.equal(api.nextTab(tabs(['documents-tab']), 'documents-tab'), null);
});

test('the step label counts only the tabs that are shown', () => {
    assert.equal(api.stepLabel(tabs(), 'documents-tab'), 'Step 2 of 3');
    assert.equal(api.stepLabel(tabs(['personal-tab']), 'documents-tab'), 'Step 1 of 2');
    assert.equal(api.stepLabel(tabs(), 'elsewhere-tab'), '');
});

test('only the last tab shows Save Staff; the others show Next', () => {
    const { panes } = page();

    assert.deepEqual(panes.map((pane) => pane.next.classList.contains('d-none')), [false, false, true]);
    assert.deepEqual(panes.map((pane) => pane.save.classList.contains('d-none')), [true, true, false]);
    assert.deepEqual(panes.map((pane) => pane.note.textContent), ['Step 1 of 3', 'Step 2 of 3', 'Step 3 of 3']);
});

test('hiding the System User tab moves Save Staff to the Documents tab', () => {
    const { items, nav, byId } = page();

    items.user.style.display = 'none';
    nav.fire('shown.bs.tab');

    assert.equal(byId('documents').save.classList.contains('d-none'), false);
    assert.equal(byId('documents').next.classList.contains('d-none'), true);
    assert.equal(byId('user').save.classList.contains('d-none'), false);
    assert.equal(byId('personal').note.textContent, 'Step 1 of 2');
});

test('the buttons follow the System User tab being hidden by the page, without a tab change', () => {
    const { items, byId, pageChanged } = page();

    items.user.style.display = 'none';
    pageChanged();

    assert.equal(byId('documents').save.classList.contains('d-none'), false);
    assert.equal(byId('documents').next.classList.contains('d-none'), true);
});

test('showing the System User tab again puts Next back on Documents', () => {
    const { items, nav, byId } = page();
    items.user.style.display = 'none';
    nav.fire('shown.bs.tab');

    items.user.style.display = '';
    nav.fire('shown.bs.tab');

    assert.equal(byId('documents').next.classList.contains('d-none'), false);
    assert.equal(byId('documents').save.classList.contains('d-none'), true);
});

test('the Save Staff buttons are locked while the header button is saving, and freed after', () => {
    const { panes, header, pageChanged } = page();

    header.disabled = true;
    header.style.opacity = '0.6';
    pageChanged();
    assert.deepEqual(panes.map((pane) => [pane.save.disabled, pane.save.style.opacity]), [[true, '0.6'], [true, '0.6'], [true, '0.6']]);

    header.disabled = false;
    header.style.opacity = '1';
    pageChanged();
    assert.deepEqual(panes.map((pane) => [pane.save.disabled, pane.save.style.opacity]), [[false, '1'], [false, '1'], [false, '1']]);
});

test('Next opens the following tab once the required fields are filled in', () => {
    const { byId, click, shownTabs } = page({ personal: [field()] });

    click(byId('personal').next);

    assert.deepEqual(shownTabs, ['documents-tab']);
});

test('Next stays put and points at the first unfinished required field', () => {
    const empty = field({ valid: false });
    const later = field({ valid: false });
    const { byId, click, shownTabs } = page({ personal: [field(), empty, later] });

    click(byId('personal').next);

    assert.deepEqual(shownTabs, []);
    assert.equal(empty.reported, 1);
    assert.equal(later.reported, 0);
});

test('fields that cannot be seen or edited do not hold the person on the tab', () => {
    const hiddenZone = field({ valid: false, offsetParent: null });
    const disabled = field({ valid: false, disabled: true });
    const { byId, click, shownTabs } = page({ personal: [hiddenZone, disabled] });

    click(byId('personal').next);

    assert.deepEqual(shownTabs, ['documents-tab']);
    assert.equal(hiddenZone.reported + disabled.reported, 0);
});

test('Next skips the System User tab when it is hidden', () => {
    const { items, nav, byId, click, shownTabs } = page();
    items.documents.style.display = 'none';
    nav.fire('shown.bs.tab');

    click(byId('personal').next);

    assert.deepEqual(shownTabs, ['user-tab']);
});

test('clicks that are not on a Next button do nothing', () => {
    const { byId, click, shownTabs } = page();
    const elsewhere = element({ closest: () => null });

    click(elsewhere);
    click(byId('personal').save);

    assert.deepEqual(shownTabs, []);
});

test('a page without the Add Staff form is left alone', () => {
    assert.doesNotThrow(() => api.init({ getElementById: () => null }, () => {}));
});
