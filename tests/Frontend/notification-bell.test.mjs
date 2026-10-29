import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../public/assets/js/notification-bell.js', import.meta.url), 'utf8');

function api() {
    const context = { URL };
    vm.runInNewContext(source, context);
    return context.HrNotificationBell;
}

test('the initial response establishes a baseline without replaying old notifications', () => {
    const state = api().feedState(null);
    assert.equal(state.cursor, null);
    assert.equal(state.accept({ cursor: 10, notifications: [{ id: 10 }] }).length, 0);
    assert.equal(state.cursor, 10);
    assert.deepEqual(Array.from(state.accept({ cursor: 11, notifications: [{ id: 11 }] }), n => n.id), [11]);
});

test('saved cursors survive navigation and repeated polls do not repeat toasts', () => {
    const state = api().feedState('10');
    const response = { cursor: 12, notifications: [{ id: 11 }, { id: 11 }, { id: 12, read_at: 'already read' }] };
    assert.deepEqual(Array.from(state.accept(response), n => n.id), [11]);
    assert.equal(state.accept(response).length, 0);
    assert.equal(state.cursor, 12);
});

test('bad responses preserve the cursor for a retry and backwards responses cannot rewind it', () => {
    const state = api().feedState('5');
    assert.throws(() => state.accept({ cursor: 'invalid', notifications: [] }));
    assert.equal(state.cursor, 5);
    state.accept({ cursor: 3, notifications: [] });
    assert.equal(state.cursor, 5);
    assert.equal(api().feedState('undefined').cursor, null);
});

test('notification links allow local application pages and reject scripts and external sites', () => {
    const { safeUrl } = api();
    const origin = 'http://127.0.0.1:8000';
    assert.equal(safeUrl('/hr/leave/1', '/dashboard', origin), origin + '/hr/leave/1');
    assert.equal(safeUrl(null, '/dashboard', origin), origin + '/dashboard');
    assert.equal(safeUrl('https://example.com', '/dashboard', origin), null);
    assert.equal(safeUrl('javascript:alert(1)', '/dashboard', origin), null);
});

class Element {
    constructor(tag = 'div') { this.tag = tag; this.children = []; this.dataset = {}; this.events = {}; this.attributes = {}; this.classList = { toggle() {} }; this.textContent = ''; }
    setAttribute(key, value) { this.attributes[key] = value; }
    addEventListener(key, callback) { this.events[key] = callback; }
    append(...items) { items.forEach(item => this.appendChild(item)); }
    appendChild(item) { item.parent = this; this.children.push(item); }
    get firstElementChild() { return this.children[0]; }
    remove() { if (this.parent) this.parent.children = this.parent.children.filter(child => child !== this); }
}

test('polling shows new notifications as text, updates the badge, retries failures, and resumes on focus', async () => {
    const bell = new Element('button');
    bell.dataset = { userId: '1', feedUrl: '/notifications/feed', listUrl: '/notifications', readBase: '/notifications', fallbackUrl: '/dashboard' };
    const badge = new Element();
    const list = new Element();
    const toasts = new Element();
    const elements = { 'hr-notification-bell': bell, 'hr-notification-count': badge, 'hr-notification-list': list, 'notification-toast-container': toasts };
    const events = {};
    const timers = [];
    const requests = [];
    const storage = new Map();
    const responses = [
        { cursor: 4, unread_count: 1, notifications: [] },
        new Error('offline'),
        { cursor: 5, unread_count: 2, notifications: [{ id: 5, title: '<img onerror=attack()>', message: '<script>attack()</script>', action_url: '/leave/5' }] },
    ];
    const window = {
        URL, location: { origin: 'http://127.0.0.1:8000', assign() {} },
        document: { hidden: false, addEventListener(name, fn) { events[name] = fn; }, getElementById(id) { return elements[id]; }, createElement(tag) { return new Element(tag); } },
        sessionStorage: { getItem(key) { return storage.get(key) ?? null; }, setItem(key, value) { storage.set(key, value); } },
        setTimeout(fn, delay) { timers.push({ fn, delay }); return timers.length; }, clearTimeout() {},
        addEventListener(name, fn) { events[name] = fn; },
        async fetch(url) {
            requests.push(url);
            const result = responses.shift();
            if (result instanceof Error) throw result;
            return { ok: true, async json() { return result; } };
        }
    };
    vm.runInNewContext(source, { window, URL });
    const settle = () => new Promise(resolve => setImmediate(resolve));
    events.DOMContentLoaded();
    await settle();
    assert.equal(toasts.children.length, 0);
    assert.equal(badge.textContent, '1');
    assert.equal(timers.at(-1).delay, 15000);
    await timers.at(-1).fn();
    assert.equal(storage.get('notification-cursor-1'), '4'); // failed poll has not lost the next notification
    events.focus();
    await settle();
    assert.equal(new URL(requests.at(-1)).searchParams.get('after_id'), '4');
    assert.equal(badge.textContent, '2');
    assert.equal(toasts.children.length, 1);
    assert.equal(toasts.children[0].children[1].textContent, '<img onerror=attack()>');
    assert.equal(toasts.children[0].children[2].textContent, '<script>attack()</script>');
    assert.equal(storage.get('notification-cursor-1'), '5');
});
