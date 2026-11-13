// The employee grid on the payroll review page: how each column is drawn. Kept apart from the page so it can be tested.
// Amounts line up on the right (the "num" class), Net Pay is the highlighted column, and the only row action is View.
(function (root) {
    'use strict';

    function money(amount) {
        return Number(amount || 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Quotes are escaped too, because names are also written into attributes
    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }

    // Change from the previous approved payroll: +1,200.00 / -300.00, a quiet 0.00 when nothing moved, a dash when there was no earlier payroll
    function changeText(value) {
        if (value === null || value === undefined) return '<span class="text-muted">-</span>';
        if (Number(value) === 0) return '<span class="text-muted">' + money(0) + '</span>';
        return (Number(value) > 0 ? '+' : '') + money(value);
    }

    function viewButton(row) {
        var name = escapeHtml(row.name);
        return '<button type="button" class="btn btn-sm btn-outline-primary pr-icon-btn view-payslip" data-id="' + escapeHtml(row.id) + '" data-name="' + name + '"'
            + ' title="View payslip" aria-label="View payslip for ' + name + '"><span class="material-symbols-outlined">visibility</span></button>';
    }

    // View the payslip, and open the staff record in a new tab to fix what the alerts point at (only when the server gave a link)
    function actionButtons(row) {
        var name = escapeHtml(row.name);
        var edit = row.edit_url
            ? '<a href="' + escapeHtml(row.edit_url) + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary pr-icon-btn"'
                + ' title="Edit staff record (opens in a new tab)" aria-label="Edit staff record for ' + name + '"><span class="material-symbols-outlined">edit</span></a>'
            : '';
        return '<div class="d-inline-flex gap-1">' + viewButton(row) + edit + '</div>';
    }

    // Negative pay and missing bank details are the serious ones; several contracts is a heads-up
    var FLAG_TONES = { negative_net: 'danger', no_bank: 'danger', multiple_contracts: 'warning' };

    function amount(key) {
        return { data: key, className: 'num', render: function (value, type) { return type === 'display' ? money(value) : value; } };
    }

    function columns() {
        return [
            { data: 'staff_number', type: 'string', className: 'text-nowrap', render: function (value, type) { return type === 'display' ? escapeHtml(value) : value; } },
            { data: 'name', render: function (value, type) { return type === 'display' ? '<span class="fw-semibold">' + escapeHtml(value) + '</span>' : value; } },
            { data: 'department', render: function (value, type) { return type === 'display' ? escapeHtml(value) : value; } },
            amount('basic'),
            amount('gross'),
            amount('paye'),
            amount('nssf'),
            amount('sha'),
            amount('housing_levy'),
            amount('other_deductions'),
            {
                data: 'net_pay',
                className: 'num col-net',
                render: function (value, type) {
                    return type === 'display' ? '<span class="' + (value < 0 ? 'text-danger' : '') + '">' + money(value) + '</span>' : value;
                }
            },
            { data: 'change', className: 'num', render: function (value, type) { return type === 'display' ? changeText(value) : (value === null || value === undefined ? 0 : value); } },
            {
                data: 'flags',
                className: 'text-nowrap',
                render: function (flags, type, row) {
                    if (type !== 'display') return (flags || []).join(', ');
                    var keys = (row && row.alerts) || [];
                    return (flags || []).map(function (flag, index) {
                        return '<span class="badge badge-light-' + (FLAG_TONES[keys[index]] || 'danger') + '">' + escapeHtml(flag) + '</span>';
                    }).join(' ') || '<span class="text-muted">-</span>';
                }
            },
            { data: null, orderable: false, searchable: false, className: 'text-center', render: actionButtons }
        ];
    }

    root.PayrollReviewGrid = { money: money, escapeHtml: escapeHtml, changeText: changeText, columns: columns };
})(typeof window !== 'undefined' ? window : this);
