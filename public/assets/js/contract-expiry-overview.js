(function (root) {
    'use strict';

    function mount(section) {
        const document = section.ownerDocument || root.document;
        const status = section.querySelector('[data-status]');
        const rows = section.querySelector('[data-contract-rows]');
        const refresh = section.querySelector('[data-refresh]');
        const fields = ['due_30'];
        let revision = 0;

        function messageRow(message) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 5; cell.textContent = message;
            row.appendChild(cell); rows.appendChild(row);
        }

        async function load() {
            const current = ++revision;
            refresh.disabled = true;
            status.textContent = 'Refreshing contract expiry figures…';
            rows.replaceChildren();
            fields.forEach(field => { section.querySelector('[data-count="' + field + '"]').textContent = '—'; });
            try {
                const response = await root.fetch(section.dataset.endpoint, {headers:{Accept:'application/json'}, cache:'no-store'});
                if (!response.ok) throw new Error('Request failed');
                const data = await response.json();
                if (current !== revision) return;
                if (!data.summary || !Array.isArray(data.upcoming) || fields.some(field => !Number.isInteger(data.summary[field]) || data.summary[field] < 0)) throw new Error('Invalid report');
                fields.forEach(field => { section.querySelector('[data-count="' + field + '"]').textContent = data.summary[field].toLocaleString(); });
                status.textContent = 'Updated ' + data.summary.generated_at + ' (Africa/Nairobi). Period: ' + data.summary.as_of + ' to ' + data.summary.through + ', inclusive.';
                if (!data.upcoming.length) messageRow('No current approved contracts expire within the next 30 days.');
                data.upcoming.slice(0,5).forEach(contract => {
                    const row = document.createElement('tr');
                    const days = contract.days_remaining;
                    [contract.employee + (contract.staff_number ? ' · ' + contract.staff_number : ''), contract.department || 'Not set', contract.end_date,
                        days === 0 ? 'Expires today' : days + (days === 1 ? ' day' : ' days')].forEach(value => {
                        const cell = document.createElement('td'); cell.textContent = value; row.appendChild(cell);
                    });
                    const cell = document.createElement('td');
                    const link = document.createElement('a');
                    const target = new URL(contract.report_url || section.dataset.reportUrl, root.location.origin);
                    link.href = target.origin === root.location.origin && ['http:', 'https:'].includes(target.protocol) ? target.href : section.dataset.reportUrl;
                    link.textContent = 'View'; link.setAttribute('aria-label', 'View contract report for ' + contract.employee);
                    cell.appendChild(link); row.appendChild(cell); rows.appendChild(row);
                });
            } catch (error) {
                if (current !== revision) return;
                fields.forEach(field => { section.querySelector('[data-count="' + field + '"]').textContent = 'Unavailable'; });
                rows.replaceChildren();
                status.textContent = 'Unable to load contract expiry figures. Click Refresh to try again.';
                messageRow('Contract details could not be loaded.');
            } finally { if (current === revision) refresh.disabled = false; }
        }
        refresh.addEventListener('click', load);
        return {refresh:load};
    }

    root.ContractExpiryOverview = {mount};
    if (root.document) root.document.addEventListener('DOMContentLoaded', function () {
        const section = root.document.getElementById('contract-expiry-overview');
        if (section) mount(section).refresh();
    });
})(window);
