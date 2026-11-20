// Behaviour of the Save / Cancel bar at the bottom of an HR form (resources/views/partials/form-actions.blade.php):
// - While a normal form is being sent, the Save button shows "Saving…" and cannot be clicked a second time.
// - Leaving the page with typing that was never saved asks first. A form is watched when it has the bar, or data-warn-unsaved.
// Both look at what a person did (typing, submitting), so values a page fills in by itself never count as changes.
(function (root) {
    'use strict';

    var SAVING = 'Saving…';
    var dirty = []; // the forms with typing that was not saved

    // The form an event came from, if it is one that is watched
    function watchedForm(target, doc) {
        var form = target && (target.form || (target.closest && target.closest('form')));
        if (!form) return null;
        if (form.hasAttribute('data-warn-unsaved')) return form;
        if (form.id && doc.querySelector('[data-form-actions-for="' + form.id + '"]')) return form;
        return null;
    }

    function markDirty(form) {
        if (dirty.indexOf(form) === -1) dirty.push(form);
    }

    function markClean(form) {
        var at = dirty.indexOf(form);
        if (at !== -1) dirty.splice(at, 1);
    }

    function hasUnsaved() {
        return dirty.length > 0;
    }

    // The Save button of a form shows that it is saving
    function showSaving(form, doc) {
        if (!form.id) return;
        Array.prototype.forEach.call(doc.querySelectorAll('[data-form-actions-save][form="' + form.id + '"]'), function (button) {
            var label = button.querySelector('.form-actions-label');
            var spinner = button.querySelector('.spinner-border');
            if (label) label.textContent = SAVING;
            if (spinner) spinner.classList.remove('d-none');
            button.disabled = true;
        });
    }

    function start(doc) {
        function edited(event) {
            if (!event.isTrusted) return;
            var form = watchedForm(event.target, doc);
            if (form) markDirty(form);
        }
        doc.addEventListener('input', edited, true);
        doc.addEventListener('change', edited, true);

        // On the document, so it runs after the form's own handlers: a page that stops the send (to check it, or to send it itself)
        // has already called preventDefault, and only a send that goes ahead shows "Saving…".
        doc.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form || !form.tagName || form.tagName.toLowerCase() !== 'form') return;
            // Sent or being sent: nothing to warn about. If the page stopped it (a check failed) and the person types again, it is marked again.
            markClean(form);
            if (!event.defaultPrevented) showSaving(form, doc);
        });

        root.addEventListener('beforeunload', function (event) {
            if (!hasUnsaved()) return undefined;
            event.preventDefault();
            event.returnValue = '';
            return '';
        });
    }

    root.FormActions = { markDirty: markDirty, markClean: markClean, hasUnsaved: hasUnsaved, showSaving: showSaving, start: start, SAVING: SAVING };

    if (root.document) {
        start(root.document);
    }
})(typeof window !== 'undefined' ? window : this);
