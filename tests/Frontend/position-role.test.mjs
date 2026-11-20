import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/position-role.js', import.meta.url), 'utf8');
const root = {};
vm.runInNewContext(source, { window: root });
const P = root.PositionRole;

function option(value, roleName) {
    return { value, getAttribute: (name) => (name === 'data-role-name' ? roleName : null) };
}

// A stand-in for the page: the position box with its options, the read-only role box and the note under it
function page(positions, selectedIndex = 0) {
    const listeners = {};
    const position = {
        options: positions, selectedIndex,
        addEventListener: (type, fn) => { listeners[type] = fn; },
        choose(index) { this.selectedIndex = index; listeners.change(); },
    };
    const display = { value: '' };
    const note = { textContent: '' };
    const doc = { getElementById: (id) => ({ position_id: position, role_display: display, roleNote: note })[id] || null };
    return { doc, position, display, note };
}

test('a position carries the name of its role, or none', () => {
    assert.equal(P.roleNameOf(option('4', 'Accountant')), 'Accountant');
    assert.equal(P.roleNameOf(option('4', '  Accountant ')), 'Accountant');
    assert.equal(P.roleNameOf(option('5', '')), '');
    assert.equal(P.roleNameOf(option('', null)), '');
    assert.equal(P.roleNameOf(null), '');
});

test('a page without both boxes is left alone', () => {
    assert.equal(P.attach({ getElementById: () => null }), null);
});

test('choosing a position shows its role in the read-only box', () => {
    const { doc, position, display, note } = page([option('', ''), option('4', 'Accountant'), option('5', 'Field Officers')]);
    P.attach(doc);

    position.choose(1);
    assert.equal(display.value, 'Accountant');
    assert.match(note.textContent, /Set by the position/);

    position.choose(2);
    assert.equal(display.value, 'Field Officers');
});

test('no position chosen leaves the role box empty', () => {
    const { doc, position, display, note } = page([option('', ''), option('4', 'Accountant')]);
    P.attach(doc);

    position.choose(1);
    position.choose(0);

    assert.equal(display.value, '');
    assert.equal(note.textContent, 'Set by the position.');
});

test('a position that has no role yet says one is made when the staff member is saved', () => {
    const { doc, position, display, note } = page([option('', ''), option('6', '')]);
    P.attach(doc);

    position.choose(1);

    assert.equal(display.value, '');
    assert.match(note.textContent, /no role yet/);
    assert.match(note.textContent, /made for it/);
});

test('a position already chosen when the page opens shows its role straight away', () => {
    const { doc, display } = page([option('', ''), option('4', 'Accountant')], 1);

    P.attach(doc);

    assert.equal(display.value, 'Accountant');
});

test('refresh shows the role again after the page rebuilds the position list', () => {
    const { doc, position, display } = page([option('', ''), option('4', 'Accountant')], 1);
    P.attach(doc);
    assert.equal(display.value, 'Accountant');

    // The department changed: the list was rebuilt with nothing chosen. jQuery's trigger does not reach the listener, so the page calls refresh().
    position.selectedIndex = 0;
    P.refresh();

    assert.equal(display.value, '');
});
