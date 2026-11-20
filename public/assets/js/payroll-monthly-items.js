// The Monthly Payroll Items page: how its tiles, tables, filters and wording are drawn. Kept apart from the page so it can be tested.
// Recurring items are grouped under each staff member, an edit only enables Save for its own row, and unsaved edits are kept
// (the "drafts") while the lists are filtered or reloaded.
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

    // ── what an item is ────────────────────────────────────────────────

    // Element type 2 is an allowance; anything else is taken off pay
    function typeKey(type) {
        return String(type) === '2' ? 'allowance' : 'deduction';
    }

    function typeName(type) {
        return typeKey(type) === 'allowance' ? 'Earnings' : 'Deduction';
    }

    function typeBadge(type) {
        return '<span class="pm-type pm-type--' + typeKey(type) + '">' + typeName(type) + '</span>';
    }

    // ── searching and filtering ────────────────────────────────────────

    function lower(value) {
        return String(value == null ? '' : value).toLowerCase();
    }

    // Every word typed must be found somewhere in the staff name, staff number, item or note, so "allan akiba" finds Allan's Akiba items
    function matches(row, query) {
        var words = lower(query).split(/\s+/).filter(Boolean);
        if (!words.length) return true;
        var haystack = [row.staff, row.staff_number, row.element, row.note].map(lower).join(' ');
        return words.every(function (word) { return haystack.indexOf(word) !== -1; });
    }

    // type is '', 'allowance' or 'deduction'
    function filterRows(rows, query, type) {
        return (rows || []).filter(function (row) {
            return (!type || typeKey(row.type) === type) && matches(row, query);
        });
    }

    function typeCounts(rows) {
        var counts = { all: 0, allowance: 0, deduction: 0 };
        (rows || []).forEach(function (row) {
            counts.all += 1;
            counts[typeKey(row.type)] += 1;
        });
        return counts;
    }

    // "12 items" while nothing is filtered out, "Showing 3 of 12" when something is, nothing when the list is empty
    function shownText(shown, total) {
        if (!total) return '';
        if (shown === total) return total + (total === 1 ? ' item' : ' items');
        return 'Showing ' + shown + ' of ' + total;
    }

    // One group per staff member, in the order they first appear, with what is added and taken off pay
    function groupByStaff(rows) {
        var groups = [];
        var byKey = {};
        (rows || []).forEach(function (row) {
            var key = row.contract_id != null ? 'c' + row.contract_id : 's' + row.staff + '|' + row.staff_number;
            if (!byKey[key]) {
                byKey[key] = { key: key, staff: row.staff, staff_number: row.staff_number, rows: [], allowances: 0, deductions: 0 };
                groups.push(byKey[key]);
            }
            byKey[key].rows.push(row);
            byKey[key][typeKey(row.type) === 'allowance' ? 'allowances' : 'deductions'] += Number(row.amount) || 0;
        });
        return groups;
    }

    // ── unsaved edits ──────────────────────────────────────────────────

    function has(object, key) {
        return Object.prototype.hasOwnProperty.call(object, key);
    }

    // Is what was typed different from what is saved? An emptied amount counts, so the row can be saved and the server can say why not.
    function isDirty(original, draft) {
        if (!draft) return false;
        if (has(draft, 'amount')) {
            var text = String(draft.amount == null ? '' : draft.amount).trim();
            if (text === '' || Number(text) !== Number(original.amount)) return true;
        }
        if (has(draft, 'note') && String(draft.note == null ? '' : draft.note) !== String(original.note == null ? '' : original.note)) return true;
        return false;
    }

    function draftValue(draft, key, saved) {
        return draft && has(draft, key) ? draft[key] : saved;
    }

    // ── table rows ─────────────────────────────────────────────────────

    function rowClass(dirty) {
        return dirty ? ' class="is-dirty"' : '';
    }

    // Save is a quiet light button until the row is edited, then the blue one. (The theme keeps a disabled primary button blue, so it can't carry the difference.)
    function saveButton(kind, dirty, label) {
        return '<button type="button" class="btn btn-sm ' + (dirty ? 'btn-primary' : 'btn-light') + ' pm-btn save-' + kind + '"' + (dirty ? '' : ' disabled') + ' title="Save this change" aria-label="Save ' + label + '">'
            + '<span class="material-symbols-outlined">check</span> Save</button>';
    }

    function removeButton(kind, title, label) {
        return '<button type="button" class="btn btn-sm btn-outline-danger pm-icon-btn ' + kind + '" title="' + title + '" aria-label="Remove ' + label + '">'
            + '<span class="material-symbols-outlined">delete</span></button>';
    }

    function amountCell(canEdit, className, value, original, label) {
        if (!canEdit) return '<td class="pm-amount-cell"><span class="pm-amount-text">' + money(value) + '</span></td>';
        return '<td class="pm-amount-cell"><input type="number" class="form-control form-control-sm pm-amount ' + className + '" min="0" step="0.01" inputmode="decimal"'
            + ' value="' + escapeHtml(value) + '" data-original="' + escapeHtml(original) + '" aria-label="Amount for ' + label + '"></td>';
    }

    // view: { canEdit, drafts }
    function recurringRowHtml(row, view) {
        var draft = view.drafts && view.drafts['r' + row.row_id];
        var dirty = isDirty(row, draft);
        var label = escapeHtml(row.element) + ' for ' + escapeHtml(row.staff);
        return '<tr data-id="' + escapeHtml(row.row_id) + '" data-key="r' + escapeHtml(row.row_id) + '"' + rowClass(dirty) + '>'
            + '<td class="pm-item-cell">' + escapeHtml(row.element) + (row.terms ? '<span class="pm-terms">' + escapeHtml(row.terms) + '</span>' : '') + '</td>'
            + '<td>' + typeBadge(row.type) + '</td>'
            + amountCell(view.canEdit, 'recurring-amount', draftValue(draft, 'amount', row.amount), row.amount, label)
            + '<td class="pm-actions-cell">' + (view.canEdit
                ? '<div class="d-inline-flex gap-1">' + saveButton('recurring', dirty, label) + removeButton('remove-recurring', 'Remove from the contract', label) + '</div>'
                : '') + '</td>'
            + '</tr>';
    }

    function itemRowHtml(item, view) {
        var draft = view.drafts && view.drafts['i' + item.id];
        var dirty = isDirty(item, draft);
        var label = escapeHtml(item.element) + ' for ' + escapeHtml(item.staff);
        var note = draftValue(draft, 'note', item.note);
        return '<tr data-id="' + escapeHtml(item.id) + '" data-key="i' + escapeHtml(item.id) + '"' + rowClass(dirty) + '>'
            + '<td>' + '<span class="fw-semibold">' + escapeHtml(item.staff) + '</span>' + (item.staff_number ? ' <span class="pm-number">' + escapeHtml(item.staff_number) + '</span>' : '') + '</td>'
            + '<td>' + escapeHtml(item.element) + '</td>'
            + '<td>' + typeBadge(item.type) + '</td>'
            + amountCell(view.canEdit, 'item-amount', draftValue(draft, 'amount', item.amount), item.amount, label)
            + '<td class="pm-note-cell">' + (view.canEdit
                ? '<input type="text" class="form-control form-control-sm item-note" maxlength="255" value="' + escapeHtml(note) + '" data-original="' + escapeHtml(item.note) + '" aria-label="Note for ' + label + '">'
                : (note ? escapeHtml(note) : '<span class="text-muted">-</span>')) + '</td>'
            + '<td class="pm-actions-cell">' + (view.canEdit
                ? '<div class="d-inline-flex gap-1">' + saveButton('item', dirty, label) + removeButton('delete-item', 'Delete this item', label) + '</div>'
                : '') + '</td>'
            + '</tr>';
    }

    function groupHeaderHtml(group, columns) {
        var parts = [];
        if (group.allowances) parts.push('<span class="pm-sum pm-sum--allowance">+ ' + money(group.allowances) + '</span>');
        if (group.deductions) parts.push('<span class="pm-sum pm-sum--deduction">- ' + money(group.deductions) + '</span>');
        return '<tr class="pm-group"><th colspan="' + columns + '" scope="colgroup"><div class="pm-group-line">'
            + '<span class="pm-group-name">' + escapeHtml(group.staff) + '</span>'
            + (group.staff_number ? '<span class="pm-number">' + escapeHtml(group.staff_number) + '</span>' : '')
            + '<span class="pm-group-count">' + group.rows.length + (group.rows.length === 1 ? ' item' : ' items') + '</span>'
            + '<span class="pm-group-sums">' + parts.join('') + '</span>'
            + '</div></th></tr>';
    }

    function emptyRow(columns, text, clearButton) {
        return '<tr class="pm-empty"><td colspan="' + columns + '"><span class="material-symbols-outlined">inbox</span> ' + escapeHtml(text)
            + (clearButton ? ' <button type="button" class="btn btn-link btn-sm p-0 align-baseline pm-clear-filters">Clear the filters</button>' : '') + '</td></tr>';
    }

    // view: { canEdit, drafts, query, type }
    function recurringBody(rows, view) {
        if (!rows || !rows.length) return emptyRow(4, 'No recurring items on the contracts in this run.');
        var shown = filterRows(rows, view.query, view.type);
        if (!shown.length) return emptyRow(4, 'No items match your search.', true);
        return groupByStaff(shown).map(function (group) {
            return groupHeaderHtml(group, 4) + group.rows.map(function (row) { return recurringRowHtml(row, view); }).join('');
        }).join('');
    }

    function itemsBody(items, view) {
        if (!items || !items.length) return emptyRow(6, 'No one-time items for this period.');
        var shown = filterRows(items, view.query, view.type);
        if (!shown.length) return emptyRow(6, 'No items match your search.', true);
        return shown.map(function (item) { return itemRowHtml(item, view); }).join('');
    }

    // ── the period header and the check ────────────────────────────────

    var STATUS_TONES = { 'approved': 'success', 'with accountant': 'warning', 'returned': 'danger', 'reversed': 'danger' };

    function lockNotice() {
        return 'This period has already been processed, so its one-time items are locked. Reverse or discard the run on Payroll Processing to change them.';
    }

    function recurringHint(locked) {
        var base = 'These are the recurring items on each contract in this run. A change here is saved to the contract, ';
        return locked
            ? base + 'but this period is already processed, so it will not change this payroll. It applies to the periods after it, and to this one if its run is reversed and run again.'
            : base + 'so it applies to this period and every one after it.';
    }

    function headerHtml(state) {
        var html = '<div class="d-flex align-items-center flex-wrap gap-2"><strong>' + escapeHtml(state.period.label) + '</strong>';
        if (state.run_status) {
            html += '<span class="badge badge-light-' + (STATUS_TONES[lower(state.run_status)] || 'secondary') + '">' + escapeHtml(state.run_status) + '</span>';
        }
        html += '</div>';
        if (state.locked) {
            html += '<div class="pm-lock" role="status"><span class="material-symbols-outlined">lock</span><span>' + escapeHtml(lockNotice()) + '</span></div>';
        }
        return html;
    }

    function tile(label, value) {
        return '<div class="pm-stat"><p class="pm-stat-label">' + escapeHtml(label) + '</p><div class="pm-stat-value">' + escapeHtml(value) + '</div></div>';
    }

    function checkSummaryHtml(summary) {
        return tile('Staff in this run', summary.staff_in_run)
            + tile('One-time items entered', summary.items)
            + tile('One-time earnings', money(summary.one_time_allowances))
            + tile('One-time deductions', money(summary.one_time_deductions));
    }

    function checkSectionsHtml(sections) {
        if (!sections || !sections.length) {
            return '<div class="pm-allclear"><span class="material-symbols-outlined">check_circle</span> Nothing to flag for this period.</div>';
        }
        return sections.map(function (section) {
            var level = section.level === 'warning' ? 'warning' : 'info';
            var rows = (section.rows || []).map(function (row) {
                return '<tr><td>' + escapeHtml(row.name) + '</td><td class="text-muted">' + escapeHtml(row.detail) + '</td></tr>';
            }).join('');
            if (section.count > (section.rows || []).length) {
                rows += '<tr><td colspan="2" class="text-muted">And ' + (section.count - section.rows.length) + ' more.</td></tr>';
            }
            return '<details class="pm-section pm-section--' + level + '"' + (level === 'warning' ? ' open' : '') + '>'
                + '<summary><span class="pm-section-head"><span class="pm-section-title">' + escapeHtml(section.title) + '</span>'
                + '<span class="pm-section-hint">' + escapeHtml(section.hint) + '</span></span>'
                + '<span class="pm-section-count">' + escapeHtml(section.count) + '</span>'
                + '<span class="material-symbols-outlined pm-chevron">expand_more</span></summary>'
                + '<table class="table table-sm mb-0"><tbody>' + rows + '</tbody></table></details>';
        }).join('');
    }

    // The small number on the Check tab: how many things were flagged, amber when any of them is a warning
    function checkBadge(check) {
        var sections = (check && check.sections) || [];
        return {
            count: sections.length,
            tone: sections.some(function (section) { return section.level === 'warning'; }) ? 'warning' : 'info'
        };
    }

    root.PayrollMonthlyItems = {
        money: money, escapeHtml: escapeHtml,
        typeKey: typeKey, typeName: typeName, typeBadge: typeBadge,
        matches: matches, filterRows: filterRows, typeCounts: typeCounts, shownText: shownText, groupByStaff: groupByStaff,
        isDirty: isDirty,
        recurringRowHtml: recurringRowHtml, itemRowHtml: itemRowHtml, groupHeaderHtml: groupHeaderHtml,
        recurringBody: recurringBody, itemsBody: itemsBody,
        lockNotice: lockNotice, recurringHint: recurringHint, headerHtml: headerHtml,
        checkSummaryHtml: checkSummaryHtml, checkSectionsHtml: checkSectionsHtml, checkBadge: checkBadge
    };
})(typeof window !== 'undefined' ? window : this);
