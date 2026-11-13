// Contract form, each allowance or deduction: "How often?" (every month, or once only in a chosen month) and "Part month?" (reduced by
// the days worked, or the full amount). Also the words for them. The payroll run reads the same two choices
// (App\Models\ContractElement), so what is shown here is what the run does.
(function (root) {
    'use strict';

    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    // Quotes are escaped too, because values are also written into attributes
    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }

    // 2026-12 (or 2026-12-01) as "Dec 2026"; '' when it is not a month
    function monthLabel(value) {
        var match = /^(\d{4})-(\d{2})(?:-\d{2})?$/.exec(String(value == null ? '' : value).trim());
        if (!match || Number(match[2]) < 1 || Number(match[2]) > 12) return '';
        return MONTHS[Number(match[2]) - 1] + ' ' + match[1];
    }

    // A part month reduces an allowance by the days worked, as the run always did, and leaves a deduction whole
    function defaultProrate(entryType) {
        return String(entryType) === '2' ? '1' : '0';
    }

    // "Every month - reduced by the days worked", "Once only (Dec 2026) - full amount". prorate empty means the run's old rule for the type.
    function termsText(terms) {
        var once = terms && terms.frequency === 'once';
        var prorate = terms && terms.prorate !== null && terms.prorate !== undefined && terms.prorate !== '' ? String(terms.prorate) === '1' || terms.prorate === true : defaultProrate(terms && terms.entry_type) === '1';
        var when = once ? 'Once only' + (monthLabel(terms.once_month) ? ' (' + monthLabel(terms.once_month) + ')' : '') : 'Every month';
        return when + ' · ' + (prorate ? 'reduced by the days worked in a part month' : 'full amount in a part month');
    }

    // What is wrong with a once-only month, given the contract's dates (YYYY-MM-DD, the end may be empty), or ''
    function onceMonthProblem(month, start, end) {
        var chosen = String(month || '').slice(0, 7);
        if (!/^\d{4}-\d{2}$/.test(chosen)) return 'Choose the month this item applies in.';
        var from = String(start || '').slice(0, 7);
        var to = String(end || '').slice(0, 7);
        if (from && chosen < from) return 'The month is before the contract starts (' + monthLabel(from) + ').';
        if (to && chosen > to) return 'The month is after the contract ends (' + monthLabel(to) + ').';
        return '';
    }

    // The two questions as markup, for a row added with the Add Element button. The server-rendered rows of the edit form use
    // resources/views/staff/contracts/partials/terms-fields.blade.php, which must match.
    function fieldsHtml(index) {
        return '<div class="mb-3 col-md-4">'
            + '<label class="form-label">How often?</label>'
            + '<select class="form-select frequency-select" name="elements[' + index + '][frequency]">'
            + '<option value="monthly" selected>Every month</option><option value="once">Once only</option></select></div>'
            + '<div class="mb-3 col-md-4 once-month-field d-none">'
            + '<label class="form-label">Which month?</label>'
            + '<input type="month" class="form-control once-month-input" name="elements[' + index + '][once_month]" disabled></div>'
            + '<div class="mb-3 col-md-4 part-month-field">'
            + '<label class="form-label">Part month?</label>'
            + '<select class="form-select prorate-select" name="elements[' + index + '][prorate]">'
            + '<option value="1">Reduced by the days worked (prorated)</option><option value="0">Full amount</option></select>'
            + '<small class="text-muted">For the month the contract starts or ends.</small></div>';
    }

    // Brings a row's controls in line with its choices: the month box only for "once only", and the part-month answer
    // following the entry type (an allowance is reduced, a deduction is not) until someone has chosen it themselves.
    function syncRow(row) {
        if (!row) return;
        var frequency = row.querySelector('.frequency-select');
        var monthField = row.querySelector('.once-month-field');
        var monthInput = row.querySelector('.once-month-input');
        if (frequency && monthField && monthInput) {
            var once = frequency.value === 'once';
            monthField.classList.toggle('d-none', !once);
            monthInput.disabled = !once;
            monthInput.required = once;
        }
        var prorate = row.querySelector('.prorate-select');
        var entry = row.querySelector('.entry-type-select');
        if (prorate && entry && entry.value && !prorate.dataset.touched) {
            prorate.value = defaultProrate(entry.value);
        }
    }

    // The part-month answer has been chosen by a person, so it is no longer followed from the entry type
    function markTouched(select) {
        if (select) select.dataset.touched = '1';
    }

    root.ContractElementTerms = {
        escapeHtml: escapeHtml, monthLabel: monthLabel, defaultProrate: defaultProrate, termsText: termsText,
        onceMonthProblem: onceMonthProblem, fieldsHtml: fieldsHtml, syncRow: syncRow, markTouched: markTouched
    };
})(typeof window !== 'undefined' ? window : this);
