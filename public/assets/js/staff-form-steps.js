// Add Staff: a Next button at the bottom of every tab, and Save Staff on the last one.
// Tabs are { id, hidden } in display order. The System User tab is hidden for casual staff, so the last
// tab is worked out from what is shown rather than fixed in the markup.
(function (root) {
    'use strict';

    function shown(tabs) {
        return tabs.filter(function (tab) { return !tab.hidden; });
    }

    function indexOf(tabs, id) {
        for (var i = 0; i < tabs.length; i++) {
            if (tabs[i].id === id) return i;
        }
        return -1;
    }

    // The tab after `current`, or null when there is none (the last tab, or a hidden one).
    function nextTab(tabs, current) {
        var list = shown(tabs);
        var index = indexOf(list, current);
        return index >= 0 && index < list.length - 1 ? list[index + 1].id : null;
    }

    function stepLabel(tabs, current) {
        var list = shown(tabs);
        var index = indexOf(list, current);
        return index < 0 ? '' : 'Step ' + (index + 1) + ' of ' + list.length;
    }

    // The first required field on the tab that is still empty or invalid. Fields that cannot be seen
    // (a zone picker for another position, say) are ignored, as the browser could not focus them either.
    function firstUnfinished(pane) {
        var fields = pane.querySelectorAll('input, select, textarea');
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            if (!field.disabled && field.offsetParent !== null && !field.checkValidity()) return field;
        }
        return null;
    }

    function init(doc, showTab) {
        var form = doc.getElementById('staffCreateForm');
        var nav = doc.getElementById('staffCreateTabs');
        if (!form || !nav) return;
        var buttons = Array.prototype.slice.call(nav.querySelectorAll('[data-bs-toggle="tab"]'));

        showTab = showTab || function (id) {
            root.bootstrap.Tab.getOrCreateInstance(doc.getElementById(id)).show();
            nav.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        };

        function tabs() {
            return buttons.map(function (button) {
                var item = button.closest('li');
                return { id: button.id, hidden: !!item && item.style.display === 'none' };
            });
        }

        function refresh() {
            var all = tabs();
            var panes = form.querySelectorAll('.tab-pane');
            for (var i = 0; i < panes.length; i++) {
                var pane = panes[i];
                var last = nextTab(all, pane.id + '-tab') === null;
                var next = pane.querySelector('.js-next-step');
                var save = pane.querySelector('.js-save-step');
                var note = pane.querySelector('.js-step-note');
                if (next) next.classList.toggle('d-none', last);
                if (save) save.classList.toggle('d-none', !last);
                if (note) note.textContent = stepLabel(all, pane.id + '-tab');
            }
        }

        form.addEventListener('click', function (event) {
            var button = event.target.closest('.js-next-step');
            if (!button) return;
            var pane = button.closest('.tab-pane');
            var unfinished = firstUnfinished(pane);
            if (unfinished) {
                unfinished.reportValidity();
                return;
            }
            var next = nextTab(tabs(), pane.id + '-tab');
            if (next) showTab(next);
        });

        // Saving locks and unlocks the Save Staff button in the header; the one closing the last tab follows it
        var header = doc.getElementById('createButton');
        function followHeaderButton() {
            var saves = form.querySelectorAll('.js-save-step');
            for (var i = 0; i < saves.length; i++) {
                saves[i].disabled = header.disabled;
                saves[i].style.opacity = header.style.opacity;
            }
        }

        nav.addEventListener('shown.bs.tab', refresh);
        if (header && root.MutationObserver) {
            new root.MutationObserver(followHeaderButton).observe(header, { attributes: true, attributeFilter: ['disabled', 'style'] });
        }
        if (root.MutationObserver) {
            new root.MutationObserver(refresh).observe(nav, { attributes: true, attributeFilter: ['style'], subtree: true });
        }
        refresh();
    }

    root.StaffFormSteps = { nextTab: nextTab, stepLabel: stepLabel, firstUnfinished: firstUnfinished, init: init };

    if (root.document) {
        if (root.document.readyState === 'loading') {
            root.document.addEventListener('DOMContentLoaded', function () { init(root.document); });
        } else {
            init(root.document);
        }
    }
})(typeof window !== 'undefined' ? window : this);
