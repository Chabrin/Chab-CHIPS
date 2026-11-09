// The "Recurring for all staff" table on the contract form. Each item is one row; an item that comes with a
// breakdown (NSSF: Tier I and Tier II) opens into a heading, a row per part, and a total row.
(function (root) {
    'use strict';

    // kind: item | group (heading, no amount) | tier (one part of a breakdown) | total
    function build(elements) {
        var rows = [];
        (elements || []).forEach(function (element) {
            var type = element.type === '2' ? 'Allowance' : 'Deduction';
            if (!element.breakdown || !element.breakdown.length) {
                rows.push({ kind: 'item', name: element.name, type: type, how: element.how, amount: element.amount });
                return;
            }
            rows.push({ kind: 'group', name: element.name, type: type, how: element.how, amount: null });
            element.breakdown.forEach(function (part) {
                rows.push({ kind: 'tier', name: part.name, type: type, how: part.how, amount: part.amount });
            });
            rows.push({ kind: 'total', name: 'Total ' + element.name, type: type, how: 'Added together', amount: element.amount });
        });
        return rows;
    }

    root.PayrollAllStaffRows = { build: build };
})(typeof window !== 'undefined' ? window : this);
