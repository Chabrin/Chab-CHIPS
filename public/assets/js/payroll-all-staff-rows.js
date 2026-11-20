// The "Recurring for all staff" table on the contract form. Each item is one row; an item that comes with a
// breakdown (NSSF: Tier I and Tier II) opens into a heading, a row per part, and a total row.
(function (root) {
    'use strict';

    // kind: item | group (heading, no amount) | tier (one part of a breakdown) | total
    // An item or heading carries its id and whether the payroll run always applies it (statutory); only the others can be
    // left out for one person (canLeaveOut). The parts and the total are never items of their own.
    function build(elements) {
        var rows = [];
        (elements || []).forEach(function (element) {
            var type = element.type === '2' ? 'Earnings' : 'Deduction';
            var identity = {
                id: element.id === undefined ? null : element.id,
                statutory: element.statutory === true,
                canLeaveOut: element.statutory !== true && element.id !== undefined && element.id !== null
            };
            if (!element.breakdown || !element.breakdown.length) {
                rows.push(Object.assign({ kind: 'item', name: element.name, type: type, how: element.how, amount: element.amount }, identity));
                return;
            }
            rows.push(Object.assign({ kind: 'group', name: element.name, type: type, how: element.how, amount: null }, identity));
            element.breakdown.forEach(function (part) {
                rows.push({ kind: 'tier', name: part.name, type: type, how: part.how, amount: part.amount });
            });
            rows.push({ kind: 'total', name: 'Total ' + element.name, type: type, how: 'Added together', amount: element.amount });
        });
        return rows;
    }

    root.PayrollAllStaffRows = { build: build };
})(typeof window !== 'undefined' ? window : this);
