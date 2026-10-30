import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/assets/js/focused-navigation.js', import.meta.url), 'utf8');

function selected(path, hrefs, options = {}) {
    const location = new URL(`http://example.test${path}`);
    function link(href, dashboard = false, index = -1) {
        const attributes = new Map([['href', href]]);
        return {
            getAttribute(name) { return attributes.get(name) ?? null; },
            setAttribute(name, value) { attributes.set(name, value); },
            get href() { return new URL(href, location.href).href; },
            dataset: { navigationKey: options.keys?.[index] },
            classList: { contains(name) { return dashboard && name === 'fn-dashboard'; } },
            closest() { return null; },
        };
    }
    const dashboard = link('/dashboard', true);
    const items = hrefs.map((href, index) => link(href, false, index));
    const inert = { hidden: false, addEventListener() {}, replaceChildren() {}, querySelector() { return null; } };
    const nav = {
        dataset: { currentDestination: options.alias },
        querySelector(selector) {
            if (selector === '.fn-dashboard') return dashboard;
            return inert;
        },
        querySelectorAll(selector) { return selector === 'a.fn-page' ? items : []; },
        addEventListener() {},
        closest() { return null; },
    };
    const document = { getElementById() { return nav; } };
    const sessionStorage = { getItem() { return options.previous ? JSON.stringify(options.previous) : null; }, removeItem() {}, setItem() {} };
    vm.runInNewContext(source, { document, location, URL, sessionStorage, requestAnimationFrame(callback) { callback(); } });
    return [dashboard, ...items].filter(item => item.getAttribute('aria-current') === 'page').map(item => options.returnKeys ? item.dataset.navigationKey : item.getAttribute('href'));
}

test('Dashboard wins over duplicate or empty menu links', () => {
    assert.deepEqual(selected('/dashboard', ['', '#', '/dashboard', 'http://[']), ['/dashboard']);
});

test('a malformed menu URL cannot stop other destinations matching', () => {
    assert.deepEqual(selected('/hr/leave', ['http://[', '/hr/leave']), ['/hr/leave']);
});

test('ambiguous duplicate destinations are not incorrectly highlighted', () => {
    assert.deepEqual(selected('/client/advances', ['/client/advances', '/client/advances']), []);
});

test('matching supports a unique exact page and a nested page', () => {
    assert.deepEqual(selected('/hr/leave', ['/hr/leave', '/hr/other']), ['/hr/leave']);
    assert.deepEqual(selected('/hr/leave/123', ['/hr/leave', '/hr']), ['/hr/leave']);
});

test('query-specific destinations require the correct query', () => {
    assert.deepEqual(selected('/reports?type=staff', ['/reports?type=staff', '/reports?type=property']), ['/reports?type=staff']);
    assert.deepEqual(selected('/reports?type=other', ['/reports?type=staff', '/reports?type=property']), []);
    assert.deepEqual(selected('/reports?page=2', ['/reports']), ['/reports']);
});

test('unknown pages and ambiguous nested destinations remain unselected', () => {
    assert.deepEqual(selected('/unknown', ['/hr/leave']), []);
    assert.deepEqual(selected('/client/advances/123', ['/client/advances', '/client/advances']), []);
});

test('verified related-page hint wins over a generic prefix but only selects a permitted menu item', () => {
    assert.deepEqual(selected('/hr/employees/termination/123', ['/hr/employees', '/hr/employee/termination'], {alias:'/hr/employee/termination'}), ['/hr/employee/termination']);
    assert.deepEqual(selected('/hr/employees/termination/123', ['/hr/employees'], {alias:'/hr/employee/termination'}), []);
    assert.deepEqual(selected('/hr/employees', ['/hr/employees', '/hr/employee/termination'], {alias:'/hr/employee/termination'}), ['/hr/employees']);
});

test('duplicate URLs restore the item clicked in this tab only when the key belongs to the current page', () => {
    const options = {keys:['management:advances','accounting:advances'], returnKeys:true, previous:{pathname:'/client/advances',key:'accounting:advances'}};
    assert.deepEqual(selected('/client/advances', ['/client/advances','/client/advances'], options), ['accounting:advances']);
    assert.deepEqual(selected('/client/advances', ['/client/advances','/client/advances'], {...options, previous:{pathname:'/another-page',key:'accounting:advances'}}), []);
    assert.deepEqual(selected('/client/advances', ['/client/advances','/client/advances'], {...options, previous:{pathname:'/client/advances',key:'not-permitted'}}), []);
});
