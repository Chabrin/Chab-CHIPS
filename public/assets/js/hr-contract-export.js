(function (root) {
    'use strict';

    function cell(value) {
        if (value === null || value === undefined) return '""';
        let text = String(value);
        // Spreadsheet apps must treat user-entered names as text, never formulas.
        if (typeof value !== 'number' && /^[\s\uFEFF]*[=+@\-]/u.test(text)) text = "'" + text;
        return '"' + text.replace(/"/g, '""') + '"';
    }

    const windowLabels = { '7': 'Expiring within 7 days', '14': 'Expiring within 14 days', '30': 'Expiring within 30 days', expired: 'Already expired', expired30: 'Expired in the last 30 days', all: 'All contracts including history' };

    function matches(contract, window, expiryStatus, selectedId) {
        if (selectedId && String(contract.id) !== String(selectedId)) return false;
        if (expiryStatus && contract.expiry?.status !== expiryStatus) return false;
        if (window === 'all') return true;
        if (!contract.is_current_approved || contract.expiry?.days_remaining === null || contract.expiry?.days_remaining === undefined) return false;
        const days = Number(contract.expiry.days_remaining);
        if (!Number.isFinite(days)) return false;
        if (window === 'expired') return days < 0;
        if (window === 'expired30') return days < 0 && days >= -30;
        return ['7', '14', '30'].includes(window) && days >= 0 && days <= Number(window);
    }

    function validDate(value) {
        if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
        const date = new Date(value + 'T00:00:00Z');
        return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 10) === value;
    }

    function validateFilters(filters) {
        if (filters.scope === 'review' || filters.period === 'all') return '';
        if (!validDate(filters.from) || !validDate(filters.to)) return 'Choose a valid From date and To date.';
        return filters.from > filters.to ? 'The To date must be on or after the From date.' : '';
    }

    function matchesFilters(contract, filters) {
        if (filters.selectedId && String(contract.id) !== String(filters.selectedId)) return false;
        if (filters.department === '__missing__' ? !!contract.department : (filters.department && contract.department !== filters.department)) return false;
        const search = String(filters.search || '').trim().toLocaleLowerCase();
        if (search && ![contract.employee?.name, contract.staff_number].some(value => String(value || '').toLocaleLowerCase().includes(search))) return false;
        if (filters.scope === 'review') return ['missing_dates', 'invalid_dates'].includes(contract.expiry?.status);
        if (validateFilters(filters)) return false;
        if (filters.scope !== 'history' && !contract.is_current_approved) return false;
        if (filters.period === 'all') return true;
        return validDate(contract.date_end) && validDate(contract.date_start) && contract.date_start <= contract.date_end && contract.date_end >= filters.from && contract.date_end <= filters.to;
    }

    function reportDescription(report) {
        const f = report.filters || {};
        const scope = {current:'Current approved contracts', history:'All contracts in the existing listing', review:'Records needing date review'}[f.scope] || '';
        return [f.scope === 'review' ? 'No date range: missing or invalid dates' : (f.period === 'all' ? 'All dates' : (f.from && f.to ? f.from + ' to ' + f.to + ' (inclusive)' : windowLabels[report.window] || report.window)),
            scope, 'Department: ' + (f.department === '__missing__' ? 'Not set' : f.department || 'All'), 'Employee search: ' + (f.search || 'None'),
            f.selectedId ? 'Contract: ' + f.selectedId : '', report.table_search ? 'Table search: ' + report.table_search : ''].filter(Boolean).join(' | ');
    }

    const headings = ['Staff Number', 'Employee', 'Department', 'Position', 'Start Date', 'End Date', 'Approval Status', 'Expiry Status', 'Days Remaining / Overdue'];
    function values(contract) {
        return [contract.staff_number, contract.employee?.name, contract.department || 'Not set', contract.position?.position_name,
            contract.date_start, contract.date_end, contract.approval_status, contract.expiry?.label,
            contract.expiry?.status === 'invalid_dates' ? null : contract.expiry?.days_remaining];
    }

    function reportColumns(report) {
        return report.kind === 'full' ? ['Date/Time', ...headings, 'Basic Salary (KES)', 'Created By'] : headings;
    }

    function reportValues(contract, report) {
        return report.kind === 'full' ? [contract.date_time, ...values(contract), contract.basic_salary, contract.created_by?.name] : values(contract);
    }

    function toCsv(contracts, report = {}) {
        const rows = [[...reportColumns(report), 'Report Generated At (Africa/Nairobi)', 'Report Filters', 'Matching Contracts']];
        contracts.forEach(contract => rows.push([...reportValues(contract, report), report.generated_at, reportDescription(report), contracts.length]));
        // BOM allows Excel to open names containing non-ASCII characters correctly.
        return '\uFEFF' + rows.map(row => row.map(cell).join(',')).join('\r\n') + '\r\n';
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[char]));
    }

    function printHtml(contracts, report = {}) {
        const title = report.kind === 'full' ? 'Employee contracts' : 'Employee contract expiry report';
        return '<!doctype html><html><head><meta charset="utf-8"><title>' + title + '</title><style>' +
            'body{font-family:Arial,sans-serif;color:#0b2239;margin:24px;font-size:11px}h1{font-size:20px}table{width:100%;border-collapse:collapse}th,td{padding:7px;border:1px solid #ccd6df;text-align:left;overflow-wrap:anywhere}th{background:#edf5fb}thead{display:table-header-group}tr{break-inside:avoid}@page{size:A4 landscape;margin:12mm}' +
            '</style></head><body><h1>' + title + '</h1><p>' + escapeHtml(reportDescription(report)) + '</p><p>' +
            escapeHtml('Generated ' + (report.generated_at || 'Unavailable') + ' (Africa/Nairobi) | Matching contracts: ' + contracts.length) +
            '</p><p>Negative days indicate overdue contracts. Current scope covers active/on-leave employees and excludes superseded contracts.</p><table><thead><tr>' +
            reportColumns(report).map(title => '<th>' + escapeHtml(title) + '</th>').join('') + '</tr></thead><tbody>' +
            contracts.map(contract => '<tr>' + reportValues(contract, report).map(value => '<td>' + escapeHtml(value ?? 'Not set') + '</td>').join('') + '</tr>').join('') +
            '</tbody></table></body></html>';
    }

    function print(table, report = {}) {
        const rows = table.rows({search:'applied', order:'applied', page:'all'}).data().toArray();
        if (!rows.length) return 0;
        const preview = root.open('', '_blank');
        if (!preview) throw new Error('Allow the report window to open, then try again.');
        preview.opener = null;
        preview.document.write(printHtml(rows, report));
        preview.document.close();
        preview.focus();
        root.setTimeout(() => preview.print(), 250);
        return rows.length;
    }

    function download(table, report = {}) {
        // Explicitly include all matching pages, current sort order and custom expiry filters.
        const rows = table.rows({ search: 'applied', order: 'applied', page: 'all' }).data().toArray();
        if (!rows.length) return 0;
        const address = root.URL.createObjectURL(new root.Blob([toCsv(rows, report)], { type: 'text/csv;charset=utf-8;' }));
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

    root.ContractCsvExport = Object.freeze({ toCsv, download, matches, matchesFilters, validateFilters, reportDescription, printHtml, print, windowLabels });
})(window);
