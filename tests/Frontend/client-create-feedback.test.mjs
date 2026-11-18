import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const context = {};
vm.runInNewContext(readFileSync(new URL('../../public/assets/js/client-create.js', import.meta.url), 'utf8'), context);
const { normalizePhone, errorItems, saveFailure, escapeHtml } = context.ChipsClientCreate;

test('pasted local and international mobile numbers retain all digits', () => {
    for (const value of ['0712345678', '0712 345 678', '+254 712 345 678', '254712345678', '712345678']) {
        assert.equal(normalizePhone(value), '254712345678');
    }
    assert.equal(normalizePhone('0112345678'), '254112345678');
});

test('invalid and overlong numbers remain visible for correction instead of silently losing digits', () => {
    for (const value of ['2547123456789', '0712', 'call me', '123']) assert.equal(normalizePhone(value), value);
    assert.equal(normalizePhone(''), '');
});

test('all server field errors retain their field keys for tab navigation', () => {
    const items = errorItems({ banks: ['Add a bank account.'], next_of_kin: ['Add next of kin.'], mobile_number: ['Wrong format.', 'Wrong length.'] });
    assert.deepEqual(JSON.parse(JSON.stringify(items)), [
        { key: 'banks', message: 'Add a bank account.' }, { key: 'next_of_kin', message: 'Add next of kin.' },
        { key: 'mobile_number', message: 'Wrong format.' }, { key: 'mobile_number', message: 'Wrong length.' },
    ]);
});

test('only an explicit server rollback is described as not saved', () => {
    assert.equal(saveFailure(500, { save_state: 'not_saved' }), 'not_saved');
    assert.equal(saveFailure(500, { save_state: 'unconfirmed' }), 'unconfirmed');
    assert.equal(saveFailure(502, {}), 'unconfirmed');
    assert.equal(saveFailure(undefined, undefined), 'unconfirmed');
    assert.equal(saveFailure(422, { errors: {} }), 'validation');
    assert.equal(saveFailure(419, {}), 'session');
    assert.equal(saveFailure(403, {}), 'permission');
});

test('client names, bank details and returned errors render as text', () => {
    assert.equal(escapeHtml('<img src=x onerror="run()"> & O\'Neil'), '&lt;img src=x onerror=&quot;run()&quot;&gt; &amp; O&#39;Neil');
});
