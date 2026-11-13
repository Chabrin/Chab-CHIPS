import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/payroll-all-staff-rows.js', import.meta.url), 'utf8');
const root = {};
vm.runInNewContext(source, { window: root });
// Rows are built inside the sandbox, so compare them as plain data
const build = (elements) => JSON.parse(JSON.stringify(root.PayrollAllStaffRows.build(elements)));

const paye = { name: 'PAYE', type: '1', how: 'From the tax bands, after the items above', amount: 5845.3, breakdown: null };
const nssf = {
    name: 'NSSF', type: '1', how: 'Tier I and Tier II on gross pay', amount: 3000,
    breakdown: [
        { name: 'Tier I (NSSF)', how: '6% of pay up to 9,000', amount: 540 },
        { name: 'Tier II (Britam)', how: '6% of pay from 9,000 up to 108,000', amount: 2460 },
    ],
};

test('an item without a breakdown is one row', () => {
    assert.deepEqual(build([paye]), [{ kind: 'item', name: 'PAYE', type: 'Deduction', how: paye.how, amount: 5845.3, id: null, statutory: false, canLeaveOut: false }]);
});

const welfare = { id: 4, name: 'Welfare', type: '1', statutory: false, how: 'Fixed amount', amount: 200, breakdown: null };

test('an item carries its id so it can be left out for one person', () => {
    assert.deepEqual(build([welfare])[0], { kind: 'item', name: 'Welfare', type: 'Deduction', how: 'Fixed amount', amount: 200, id: 4, statutory: false, canLeaveOut: true });
});

test('a statutory item can never be left out', () => {
    const rows = build([{ ...welfare, id: 9, name: 'SHA', statutory: true }]);

    assert.equal(rows[0].statutory, true);
    assert.equal(rows[0].canLeaveOut, false);
});

test('an item with no id cannot be left out, because there is nothing to name it by', () => {
    assert.equal(build([{ ...welfare, id: undefined }])[0].canLeaveOut, false);
    assert.equal(build([{ ...welfare, id: null }])[0].canLeaveOut, false);
});

test('the NSSF heading is statutory and its parts and total carry no id of their own', () => {
    const rows = build([{ ...nssf, id: 7, statutory: true }]);

    assert.equal(rows[0].statutory, true);
    assert.equal(rows[0].canLeaveOut, false);
    assert.equal(rows[0].id, 7);
    assert.deepEqual(rows.slice(1).map((row) => 'id' in row || 'canLeaveOut' in row), [false, false, false]);
});

test('only an item that is not statutory is offered for leaving out', () => {
    const rows = build([welfare, { ...welfare, id: 5, name: 'Transport', type: '2' }, { ...paye, id: 2, statutory: true }]);

    assert.deepEqual(rows.map((row) => [row.name, row.canLeaveOut]), [['Welfare', true], ['Transport', true], ['PAYE', false]]);
});

test('NSSF opens into a heading, each tier and a total', () => {
    const rows = build([nssf]);

    assert.deepEqual(rows.map((row) => [row.kind, row.name, row.amount]), [
        ['group', 'NSSF', null],
        ['tier', 'Tier I (NSSF)', 540],
        ['tier', 'Tier II (Britam)', 2460],
        ['total', 'Total NSSF', 3000],
    ]);
});

test('the tiers say what they are worked out from', () => {
    const rows = build([nssf]);

    assert.equal(rows[1].how, '6% of pay up to 9,000');
    assert.equal(rows[2].how, '6% of pay from 9,000 up to 108,000');
});

test('the heading has no amount of its own, so NSSF is not counted twice', () => {
    assert.equal(build([nssf])[0].amount, null);
});

test('the other items keep their order around the NSSF rows', () => {
    const rows = build([paye, nssf, { ...paye, name: 'SHA' }]);

    assert.deepEqual(rows.map((row) => row.name), ['PAYE', 'NSSF', 'Tier I (NSSF)', 'Tier II (Britam)', 'Total NSSF', 'SHA']);
});

test('with no pay entered the tiers and the total have no amount', () => {
    const empty = { ...nssf, amount: null, breakdown: nssf.breakdown.map((part) => ({ ...part, amount: null })) };

    assert.deepEqual(build([empty]).map((row) => row.amount), [null, null, null, null]);
});

test('an allowance is labelled as one', () => {
    assert.equal(build([{ ...paye, type: '2' }])[0].type, 'Allowance');
});

test('an empty breakdown list is treated as a plain item', () => {
    assert.equal(build([{ ...nssf, breakdown: [] }]).length, 1);
});

test('no items gives no rows', () => {
    assert.deepEqual(build([]), []);
    assert.deepEqual(build(undefined), []);
});
