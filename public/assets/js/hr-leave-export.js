(function (root) {
    'use strict';

    function status(value, waitingOn) {
        if (Number(value) === 1) return waitingOn === 'hr' ? 'Pending HR' : 'Pending Supervisor';
        return ({5: 'Approved', 24: 'Pending HR', 41: 'Rejected', 49: 'Completed'})[Number(value)] || 'Unknown (' + (value ?? '') + ')';
    }

    function cell(value) {
        let text = value === null || value === undefined ? '' : String(value);
        if (typeof value !== 'number' && /^[\s\uFEFF]*[=+@\-]/u.test(text)) text = "'" + text;
        return '"' + text.replace(/"/g, '""') + '"';
    }

    function toCsv(kind, records) {
        const rows = kind === 'applications'
            ? [['Employee', 'Position', 'Leave Category', 'Start Date', 'End Date', 'Requested Days', 'Recorded Used Days', 'Status']]
            : [['Employee', 'Leave Category', 'Plan Start', 'Plan End', 'Entitled Days', 'Balance Days', 'Plan Status']];
        records.forEach(record => {
            if (kind === 'applications') {
                rows.push([record.employee?.name, record.position?.position_name, record.leave_type?.type_name,
                    record.start_date, record.end_date, record.requested_days, record.used_days,
                    status(record.current_status_id, record.waiting_on)]);
            } else {
                // Plan approval is separate from the application's supervisor/HR stages.
                const planStatus = ({1:'Pending', 5:'Approved', 24:'Partially approved', 41:'Rejected'})[Number(record.status)] || 'Unknown (' + (record.status ?? '') + ')';
                rows.push([record.employee_name, record.leave_type_name, record.start_date, record.end_date,
                    record.entitled_days, record.balance, planStatus]);
            }
        });
        return '\uFEFF' + rows.map(row => row.map(cell).join(',')).join('\r\n') + '\r\n';
    }

    function download(table, kind) {
        const records = table.rows({search:'applied', order:'applied', page:'all'}).data().toArray();
        if (!records.length) return 0;
        const address = root.URL.createObjectURL(new root.Blob([toCsv(kind, records)], {type:'text/csv;charset=utf-8;'}));
        const link = root.document.createElement('a');
        const now = new Date();
        const stamp = [now.getFullYear(), String(now.getMonth() + 1).padStart(2,'0'), String(now.getDate()).padStart(2,'0')].join('-');
        link.href = address;
        link.download = (kind === 'applications' ? 'leave-applications-' : 'annual-leave-plans-') + stamp + '.csv';
        link.hidden = true;
        root.document.body.appendChild(link);
        link.click(); link.remove();
        root.setTimeout(() => root.URL.revokeObjectURL(address), 1000);
        return records.length;
    }

    function bind(table, kind, buttonId, messageId) {
        const button = root.document.getElementById(buttonId);
        const message = root.document.getElementById(messageId);
        if (!button || !message) return;
        let loading = true, failed = false;
        const refresh = () => {
            const count = table.rows({search:'applied', page:'all'}).count();
            button.disabled = failed || loading || count === 0;
            message.textContent = failed ? 'Unable to load records for export. Refresh to try again.' : (loading ? 'Loading records…' : (count ? 'Exports the current list search across all pages.' : 'No matching records to export.'));
        };
        table.on('processing.dt', (_, settings, processing) => { loading = processing; refresh(); });
        table.on('xhr.dt', (_, settings, json) => {
            failed = !json;
            refresh();
        });
        table.on('draw.dt', () => { loading = false; refresh(); });
        button.addEventListener('click', () => {
            if (button.disabled) return;
            try {
                const count = download(table, kind);
                message.textContent = count ? `Downloaded ${count} matching record(s) across all pages.` : 'No matching records to export.';
            } catch (error) { message.textContent = 'Unable to export. Please try again.'; }
        });
    }

    root.LeaveCsvExport = Object.freeze({toCsv, download, bind});
})(window);
