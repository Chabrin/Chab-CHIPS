import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/hr-contract-export.js', import.meta.url), 'utf8');
function setup() {
    const saved = { clicked: false };
    const root = {
        Blob,
        URL: { createObjectURL(blob) { saved.blob = blob; return 'blob:test'; }, revokeObjectURL(url) { saved.revoked = url; } },
        setTimeout(callback) { callback(); },
        document: {
            body: { appendChild(link) { saved.link = link; } },
            createElement() { return { click() { saved.clicked = true; }, remove() { saved.removed = true; } }; },
        },
    };
    vm.runInNewContext(source, {window: root});
    return { api: root.ContractCsvExport, saved };
}
const contract = {
    date_time: '2026-10-27T09:00:00Z', staff_number: '004', employee: {name: 'Claire, "Example"'},
    position: {position_name: 'Developer'}, date_start: '2026-01-01', date_end: '2026-10-26',
    expiry: {label: 'Expired', status: 'expired', days_remaining: -1}, basic_salary: '60000.00', created_by: {name: 'HR'},
};

test('CSV includes raw contract values and safely quotes names', () => {
    const {api} = setup();
    const csv = api.toCsv([contract]);
    assert.ok(csv.startsWith('\uFEFF'));
    assert.match(csv, /"Claire, ""Example"""/);
    assert.match(csv, /"004"/);
    assert.match(csv, /"Expired","-1","60000.00"/);
    assert.equal(csv.includes('encrypted_id'), false);
});

test('missing fields are blank, zero values are retained and invalid dates have no day count', () => {
    const {api} = setup();
    const csv = api.toCsv([{ basic_salary: 0, expiry: {status: 'invalid_dates', label: 'Invalid dates', days_remaining: -10} }]);
    assert.match(csv, /"Invalid dates","","0"/);
    assert.equal(csv.includes('undefined'), false);
    assert.equal(csv.includes('null'), false);
});

test('formula-like names are neutralized and embedded newlines are quoted', () => {
    const {api} = setup();
    for (const name of ['=HYPERLINK("bad")', '+SUM(1,2)', '-10+20', '@SUM(1)', '\t=1+1']) {
        assert.ok(api.toCsv([{employee:{name}}]).includes('"\''));
    }
    assert.ok(api.toCsv([{employee:{name:'First\nSecond'}}]).includes('"First\nSecond"'));
});

test('download requests all filtered pages in the current order and releases the download URL', async () => {
    const {api, saved} = setup();
    const table = { rows(options) {
        assert.deepEqual({...options}, {search:'applied', order:'applied', page:'all'});
        return {data: () => ({toArray: () => [contract, {...contract, staff_number:'005'}]})};
    } };
    assert.equal(api.download(table), 2);
    assert.equal(saved.clicked, true);
    assert.match(saved.link.download, /^employee-contracts-\d{4}-\d{2}-\d{2}\.csv$/);
    assert.equal(saved.revoked, 'blob:test');
    assert.equal(saved.removed, true);
    assert.ok((await saved.blob.text()).includes('"005"'));
});

test('no matching rows do not create a download', () => {
    const {api, saved} = setup();
    assert.equal(api.download({rows: () => ({data: () => ({toArray: () => []})})}), 0);
    assert.equal(saved.clicked, false);
    assert.equal(saved.blob, undefined);
});
