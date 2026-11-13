import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/assets/js/contract-period.js', import.meta.url), 'utf8');
const root = {};
vm.runInNewContext(source, { window: root });
const P = root.ContractPeriod;
// The same table the PHP service is tested against
const cases = JSON.parse(readFileSync(new URL('../fixtures/contract-period-cases.json', import.meta.url), 'utf8'));

for (const c of cases) {
    test(`${c.value} ${c.unit} from ${c.start} ends on ${c.end}${c.note ? ' (' + c.note + ')' : ''}`, () => {
        assert.equal(P.endDate(c.start, c.value, c.unit), c.end);
    });
}

test('a probation period of months ends the same way', () => {
    assert.equal(P.probationEnd('2027-01-01', 3), '2027-03-31');
    assert.equal(P.probationEnd('2027-06-15', 6), '2027-12-14');
});

test('a start that is not a real date gives nothing', () => {
    for (const start of ['', null, undefined, 'soon', '2027-02-30', '2027-13-01', '27-01-01', '2027-1-1']) {
        assert.equal(P.endDate(start, 12, 'months'), '', String(start));
    }
});

test('a period that is not usable gives nothing', () => {
    assert.equal(P.endDate('2027-01-01', '', 'months'), '');
    assert.equal(P.endDate('2027-01-01', 0, 'months'), '');
    assert.equal(P.endDate('2027-01-01', -3, 'months'), '');
    assert.equal(P.endDate('2027-01-01', 1.5, 'months'), '');
    assert.equal(P.endDate('2027-01-01', 'abc', 'months'), '');
    assert.equal(P.endDate('2027-01-01', 12, 'fortnights'), '');
    assert.equal(P.endDate('2027-01-01', 12, ''), '');
});

test('a period typed into a box as text still works', () => {
    assert.equal(P.endDate('2027-01-01', '12', 'months'), '2027-12-31');
});

test('only real dates parse, and a date stays the date written', () => {
    assert.equal(P.parse('2027-02-28').toISOString().slice(0, 10), '2027-02-28');
    assert.equal(P.parse('2028-02-29').toISOString().slice(0, 10), '2028-02-29');
    assert.equal(P.parse('2027-02-29'), null);
});
