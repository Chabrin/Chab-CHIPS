// Switches off the browser's list of earlier typed values (and autofill) on every form of the HR pages: staff, contracts, payroll,
// leave, discipline, HR settings and system users. Without it a browser offers the names, ID numbers, phone numbers and bank
// details typed for the previous employee, and one person's details can end up on another person's record.
//
// - Search boxes keep their suggestions (type=search, the DataTables search box, anything with data-allow-suggestions).
// - A whole form can be left out with data-allow-suggestions on the <form>.
// - Password fields are marked new-password, so a browser never fills a saved password into a form that sets one for someone else.
// - Fields added later (a modal, a new contract row) are covered too.
//
// Chrome, Edge and Firefox are all asked the same way (autocomplete="off" on the form and on each field). Chrome sometimes keeps
// offering its own saved name, phone or email even then; if HR still sees that, only this file needs to change.
(function (root) {
    'use strict';

    // The first part of the page address (without the leading slash) of every page this applies to
    var HR_PREFIXES = ['hr', 'hrsettings', 'discipline', 'payroll', 'users', 'confirm/users', 'add/employee', 'add/user', 'employee'];

    // Fields that never take typed text
    var NOT_TEXT = /^(hidden|file|checkbox|radio|submit|button|image|reset|range|color)$/i;

    // Whether this page address is one of the HR pages
    function isHrPath(path) {
        var clean = String(path || '').replace(/^\/+/, '').replace(/[?#].*$/, '').toLowerCase();
        return HR_PREFIXES.some(function (prefix) {
            return clean === prefix || clean.indexOf(prefix + '/') === 0;
        });
    }

    // Whether this field is a search box (or sits in one) that may keep its suggestions
    function isSearchField(field) {
        var type = String(field.type || '').toLowerCase();
        if (type === 'search' || (field.hasAttribute && field.hasAttribute('data-allow-suggestions'))) return true;
        if (field.closest && field.closest('[data-allow-suggestions], .dataTables_filter, .dt-search, [role="search"]')) return true;
        return false;
    }

    // What autocomplete should be for this field, or '' to leave it as it is
    function settingFor(field) {
        var tag = String(field.tagName || '').toLowerCase();
        var type = String(field.type || '').toLowerCase();
        if (tag === 'input' && type === 'password') return 'new-password';
        if (tag === 'input' && NOT_TEXT.test(type)) return '';
        if (isSearchField(field)) return '';
        return 'off';
    }

    // Whether this form is one that may keep its suggestions
    function formAllowed(form) {
        return !!(form.hasAttribute && form.hasAttribute('data-allow-suggestions')) || !!(form.getAttribute && /search/i.test(form.getAttribute('role') || ''));
    }

    // Apply to a form, a field, or everything inside an element
    function apply(element) {
        if (!element || !element.querySelectorAll) return;
        var forms = String(element.tagName || '').toLowerCase() === 'form' ? [element] : [];
        Array.prototype.push.apply(forms, element.querySelectorAll('form'));
        forms.forEach(function (form) {
            if (!formAllowed(form)) form.setAttribute('autocomplete', 'off');
        });
        var fields = /^(input|select|textarea)$/i.test(element.tagName || '') ? [element] : [];
        Array.prototype.push.apply(fields, element.querySelectorAll('input, select, textarea'));
        fields.forEach(function (field) {
            var inAllowedForm = field.closest && field.closest('form[data-allow-suggestions]');
            var setting = inAllowedForm ? '' : settingFor(field);
            if (setting) field.setAttribute('autocomplete', setting);
        });
    }

    function start(doc, path) {
        if (!doc || !isHrPath(path)) return;
        apply(doc);
        if (root.MutationObserver && doc.body) {
            new root.MutationObserver(function (changes) {
                changes.forEach(function (change) {
                    Array.prototype.forEach.call(change.addedNodes || [], function (node) {
                        if (node.nodeType === 1) apply(node);
                    });
                });
            }).observe(doc.body, { childList: true, subtree: true });
        }
    }

    root.NoAutofill = { isHrPath: isHrPath, settingFor: settingFor, apply: apply, start: start };

    if (root.document) {
        var run = function () { start(root.document, root.__NO_AUTOFILL_PATH || (root.location && root.location.pathname)); };
        if (root.document.readyState === 'loading') {
            root.document.addEventListener('DOMContentLoaded', run);
        } else {
            run();
        }
    }
})(typeof window !== 'undefined' ? window : this);
