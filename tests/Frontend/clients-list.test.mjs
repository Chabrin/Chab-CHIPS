import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const context = {};
vm.runInNewContext(readFileSync(new URL('../../public/assets/js/clients-list.js', import.meta.url), 'utf8'), context);
const { displayText, clientUrl, clientActions } = context.ChipsClientsList;

test('stored client and contact values render as text without replacing legacy placeholders', () => {
    assert.equal(displayText('<img src=x onerror="run()">'), '&lt;img src=x onerror=&quot;run()&quot;&gt;');
    assert.equal(displayText('[LANDLORD NAME]'), '[LANDLORD NAME]');
    assert.equal(displayText('.'), '.');
    assert.equal(displayText('00001234'), '00001234');
    assert.match(displayText(null), /Not provided/);
});

test('encrypted identifiers stay in one URL segment', () => {
    assert.equal(clientUrl('/property/clients/', 'abc/+="<>'), '/property/clients/abc%2F%2B%3D%22%3C%3E');
});

test('View preserves existing route access and Edit follows the server flag; client names are escaped', () => {
    const row = { names: 'EXAMPLE" onclick="run()', encrypted_id: 'abc', can_read: true, can_update: false };
    let html = clientActions('/property/clients', row);
    assert.match(html, />View<\/a>/);
    assert.doesNotMatch(html, />Edit<\/a>/);
    assert.match(html, /EXAMPLE&quot; onclick=&quot;run\(\)/);
    assert.doesNotMatch(html, / onclick="/);
    html = clientActions('/property/clients', { ...row, can_update: true });
    assert.match(html, /href="\/property\/clients\/abc\/edit"/);
    assert.match(html, />Edit<\/a>/);
    html = clientActions('/property/clients', { ...row, can_read: false });
    // Laravel Gates are disabled for Laratrust permissions in this application.
    // Keep the View action that already existed; route middleware checks access.
    assert.match(html, />View<\/a>/);
    assert.doesNotMatch(html, />Edit<\/a>/);
});
