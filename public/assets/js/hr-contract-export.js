(function (root) {
    'use strict';

    function cell(value) {
        if (value === null || value === undefined) return '""';
        let text = String(value);
        // Spreadsheet apps must treat user-entered names as text, never formulas.
        if (typeof value !== 'number' && /^[\s\uFEFF]*[=+@\-]/u.test(text)) text = "'" + text;
        return '"' + text.replace(/"/g, '""') + '"';
    }

    function toCsv(contracts) {
        const rows = [[
            'Date/Time', 'Staff Number', 'Employee', 'Position', 'Start Date', 'End Date',
            'Expiry Status', 'Days Remaining', 'Basic Salary (KES)', 'Created By'
        ]];
        contracts.forEach(contract => rows.push([
            contract.date_time, contract.staff_number, contract.employee?.name,
            contract.position?.position_name, contract.date_start, contract.date_end,
            contract.expiry?.label,
            contract.expiry?.status === 'invalid_dates' ? null : contract.expiry?.days_remaining,
            contract.basic_salary, contract.created_by?.name
        ]));
        // BOM allows Excel to open names containing non-ASCII characters correctly.
        return '\uFEFF' + rows.map(row => row.map(cell).join(',')).join('\r\n') + '\r\n';
    }

    function download(table) {
        // Explicitly include all matching pages, current sort order and custom expiry filters.
        const rows = table.rows({ search: 'applied', order: 'applied', page: 'all' }).data().toArray();
        if (!rows.length) return 0;
        const address = root.URL.createObjectURL(new root.Blob([toCsv(rows)], { type: 'text/csv;charset=utf-8;' }));
        const link = root.document.createElement('a');
        const today = new Date();
        const stamp = [today.getFullYear(), String(today.getMonth() + 1).padStart(2, '0'), String(today.getDate()).padStart(2, '0')].join('-');
        link.href = address;
        link.download = 'employee-contracts-' + stamp + '.csv';
        link.hidden = true;
        root.document.body.appendChild(link);
        link.click();
        link.remove();
        root.setTimeout(() => root.URL.revokeObjectURL(address), 1000);
        return rows.length;
    }

    root.ContractCsvExport = Object.freeze({ toCsv, download });
})(window);
