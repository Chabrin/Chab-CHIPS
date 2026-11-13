import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/staff-contact-format.js', import.meta.url), 'utf8');
const root = {};
vm.runInNewContext(source, { window: root });
const { formatMobile, suggestEmail, init } = root.StaffContactFormat;

// The same cases as tests/Unit/StaffContactTest.php, because the server applies the same rules
const numbers = [
    ['0712 345 678', '+254712345678'],
    ['0712345678', '+254712345678'],
    ['0112345678', '+254112345678'],
    ['254712345678', '+254712345678'],
    ['+254 712 345 678', '+254712345678'],
    ['+254-712-345-678', '+254712345678'],
    ['(0712) 345.678', '+254712345678'],
    ['+2540712345678', '+254712345678'],
    ['2540712345678', '+254712345678'],
    ['00254712345678', '+254712345678'],
    ['712345678', '+254712345678'],
    ['+256 772 123456', '+256772123456'],
    ['0712', '0712'],
    ['abc', 'abc'],
    ['0812345678', '0812345678'],
    ['', ''],
    ['   ', ''],
    [null, ''],
];

for (const [typed, expected] of numbers) {
    test(`mobile "${typed}" becomes "${expected}"`, () => {
        assert.equal(formatMobile(typed), expected);
    });
}

const names = [
    ['LUCY W KARANJA', 'lucy.karanja@chabrinagencies.co.ke'],
    ['Lucy Karanja', 'lucy.karanja@chabrinagencies.co.ke'],
    ['  JOHN   DOE  ', 'john.doe@chabrinagencies.co.ke'],
    ["MARY-ANN O'BRIEN", 'mary-ann.obrien@chabrinagencies.co.ke'],
    ['José Müller', 'jose.muller@chabrinagencies.co.ke'],
    ['J. K. Mwangi', 'j.mwangi@chabrinagencies.co.ke'],
    ['Wanjiru', ''],
    ['', ''],
    [null, ''],
];

for (const [fullNames, expected] of names) {
    test(`email for "${fullNames}" is "${expected}"`, () => {
        assert.equal(suggestEmail(fullNames, 'chabrinagencies.co.ke'), expected);
    });
}

test('the email uses whatever domain it is given', () => {
    assert.equal(suggestEmail('Lucy Karanja', 'example.co.ke'), 'lucy.karanja@example.co.ke');
});

function input(value) {
    const listeners = {};
    return { value, addEventListener(type, callback) { listeners[type] = callback; }, blur() { listeners.blur(); } };
}

function pageWith(...inputs) {
    return { querySelectorAll: (selector) => (selector === '[data-mobile-format]' ? inputs : []) };
}

test('leaving the mobile box puts the country code on a number it can read', () => {
    const mobile = input('0712 345 678');
    init(pageWith(mobile));

    mobile.blur();

    assert.equal(mobile.value, '+254712345678');
});

test('leaving the box leaves alone what it cannot read as a number', () => {
    const mobile = input('call me');
    init(pageWith(mobile));

    mobile.blur();

    assert.equal(mobile.value, 'call me');
});

test('leaving an empty box keeps it empty', () => {
    const mobile = input('');
    init(pageWith(mobile));

    mobile.blur();

    assert.equal(mobile.value, '');
});

test('every mobile box on the page is tidied', () => {
    const first = input('0711111111');
    const second = input('254722222222');
    init(pageWith(first, second));

    first.blur();
    second.blur();

    assert.deepEqual([first.value, second.value], ['+254711111111', '+254722222222']);
});

test('a page with no mobile box is left alone', () => {
    assert.doesNotThrow(() => init(pageWith()));
});
