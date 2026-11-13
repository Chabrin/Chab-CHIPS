import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/contract-exclusions.js', import.meta.url), 'utf8');
const root = {};
vm.runInNewContext(source, { window: root });
const { create, parse, escapeHtml } = root.ContractExclusions;
// Objects made inside the sandbox have another prototype, so compare plain data
const plain = (value) => JSON.parse(JSON.stringify(value));

test('nothing is left out to begin with', () => {
    const left = create();

    assert.deepEqual(plain(left.ids()), []);
    assert.equal(left.isLeftOut(4), false);
    assert.equal(left.inputsHtml(), '');
});

test('the saved choices are the starting point', () => {
    const left = create({ 4: 'Has a company car', 7: 'Opted out' });

    assert.equal(left.isLeftOut(4), true);
    assert.equal(left.isLeftOut('7'), true, 'an id typed as text is the same item');
    assert.equal(left.reason(4), 'Has a company car');
    assert.deepEqual(plain(left.ids()), ['4', '7']);
});

test('an item is left out and put back', () => {
    const left = create();

    left.set(4, true, '');
    assert.equal(left.isLeftOut(4), true);
    left.set(4, false);
    assert.equal(left.isLeftOut(4), false);
    assert.deepEqual(plain(left.ids()), []);
});

test('the reason is kept while it is typed and replaced when it changes', () => {
    const left = create();

    left.set(4, true, '');
    left.setReason(4, 'Opted out');
    left.setReason(4, 'Opted out in writing');

    assert.equal(left.reason(4), 'Opted out in writing');
});

test('typing a reason for an item that is not left out leaves nothing out', () => {
    const left = create();

    left.setReason(4, 'Opted out');

    assert.equal(left.isLeftOut(4), false);
});

test('putting an item back drops its reason', () => {
    const left = create({ 4: 'Opted out' });

    left.set(4, false);
    left.set(4, true, '');

    assert.equal(left.reason(4), '');
});

test('items left out with no reason are reported, blanks and spaces alike', () => {
    const left = create({ 4: 'Opted out', 5: '', 6: '   ' });

    assert.deepEqual(plain(left.withoutReason()), ['5', '6']);
});

test('each item left out is written as two hidden inputs the server reads', () => {
    const html = create({ 4: 'Opted out' }).inputsHtml();

    assert.equal(html, '<input type="hidden" name="exclusions[4][excluded]" value="1"><input type="hidden" name="exclusions[4][reason]" value="Opted out">');
});

test('a reason cannot break out of the attribute it is written into', () => {
    const html = create({ 4: '"><script>alert(1)</script>' }).inputsHtml();

    assert.ok(!html.includes('<script>'));
    assert.ok(html.includes('value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"'));
});

test('an id that is not a number is not an item, so it cannot become part of an input name', () => {
    const left = create({ 'x][excluded]': 'bad', '4': 'ok' });
    left.set('9]abc', true, 'bad');

    assert.deepEqual(plain(left.ids()), ['4']);
    assert.ok(!left.inputsHtml().includes('abc'));
});

test('items that no longer apply to all staff are dropped and named', () => {
    const left = create({ 4: 'a', 5: 'b', 6: 'c' });

    const dropped = left.keepOnly([4, '6', 8]);

    assert.deepEqual(plain(dropped), ['5']);
    assert.deepEqual(plain(left.ids()), ['4', '6']);
});

test('keeping only an empty list drops everything', () => {
    const left = create({ 4: 'a' });

    assert.deepEqual(plain(left.keepOnly([])), ['4']);
    assert.deepEqual(plain(left.ids()), []);
});

test('the page text is read as data, and anything unusable gives no choices', () => {
    assert.deepEqual(plain(parse('{"4":"Opted out"}')), { 4: 'Opted out' });
    assert.deepEqual(plain(parse('')), {});
    assert.deepEqual(plain(parse(undefined)), {});
    assert.deepEqual(plain(parse('not json')), {});
    assert.deepEqual(plain(parse('[1,2]')), {}, 'a list is not a set of choices');
    assert.deepEqual(plain(parse('null')), {});
});

test('a reason typed as something other than text is kept as text', () => {
    const left = create({ 4: 12, 5: null });

    assert.equal(left.reason(4), '12');
    assert.equal(left.reason(5), '');
});

test('escaping covers all five characters', () => {
    assert.equal(escapeHtml('<a href="x">\'&\'</a>'), '&lt;a href=&quot;x&quot;&gt;&#39;&amp;&#39;&lt;/a&gt;');
});
