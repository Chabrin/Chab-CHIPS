// HR Settings > Employment Categories: how each row of the list is drawn. Kept apart from the page so it can be tested.
(function (root) {
    'use strict';

    // Quotes are escaped too, because names are also written into attributes
    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }

    // What the staff number looks like for this prefix: "CT-001", or a plain number when there is no prefix
    function numberExample(prefix) {
        return prefix ? prefix + '-001' : '1, 2, 3...';
    }

    // can: { update, delete } - what the signed-in user may do
    function rowHtml(category, can) {
        var name = escapeHtml(category.name);
        var off = !category.active;
        var status = '<div class="form-check form-switch m-0 d-inline-block">'
            + '<input class="form-check-input ec-toggle" type="checkbox" role="switch"' + (category.active ? ' checked' : '') + (can.update ? '' : ' disabled')
            + ' aria-label="Offer ' + name + ' for new staff"></div> '
            + '<span class="small ' + (off ? 'text-muted' : 'text-success') + '">' + (off ? 'Off' : 'On') + '</span>';

        var actions = '';
        if (can.update) {
            actions += '<button type="button" class="btn btn-sm btn-outline-primary ec-icon-btn ec-edit" title="Edit" aria-label="Edit ' + name + '"><span class="material-symbols-outlined">edit</span></button> ';
        }
        if (can['delete']) {
            actions += '<button type="button" class="btn btn-sm btn-outline-danger ec-icon-btn ec-delete" title="Delete" aria-label="Delete ' + name + '"><span class="material-symbols-outlined">delete</span></button>';
        }

        return '<tr data-id="' + escapeHtml(category.id) + '"' + (off ? ' class="ec-off"' : '') + '>'
            + '<td><span class="fw-semibold">' + name + '</span></td>'
            + '<td class="text-nowrap">' + (category.staff_number_prefix ? '<code>' + escapeHtml(category.staff_number_prefix) + '</code> <span class="small text-muted">' + escapeHtml(numberExample(category.staff_number_prefix)) + '</span>' : '<span class="text-muted">none (plain numbers)</span>') + '</td>'
            + '<td>' + (category.gets_login ? 'Yes' : 'No') + '</td>'
            + '<td>' + (category.has_end_date ? 'Yes' : '<span class="text-muted">No (probation only)</span>') + '</td>'
            + '<td class="text-end">' + escapeHtml(category.staff_count) + '</td>'
            + '<td class="text-nowrap">' + status + '</td>'
            + '<td class="text-end text-nowrap">' + actions + '</td>'
            + '</tr>';
    }

    function bodyHtml(categories, can) {
        if (!categories || !categories.length) {
            return '<tr><td colspan="7" class="text-center text-muted py-4">No employment categories yet.</td></tr>';
        }
        return categories.map(function (category) { return rowHtml(category, can); }).join('');
    }

    root.EmploymentCategories = { escapeHtml: escapeHtml, numberExample: numberExample, rowHtml: rowHtml, bodyHtml: bodyHtml };
})(typeof window !== 'undefined' ? window : this);
