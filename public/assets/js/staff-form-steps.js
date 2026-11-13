// Add Staff: the tabs are the onboarding stages. Each tab shows whether it is complete, not done yet, or has a problem; a Save & Next
// button at the bottom of every tab saves the form as a draft and opens the following tab, and Save Staff closes the last one.
// Tabs are { id, hidden } in display order. The System User tab is hidden for casual staff, so the last
// tab is worked out from what is shown rather than fixed in the markup.
(function (root) {
    'use strict';

    var STATUS_ICONS = { complete: 'check_circle', problem: 'error', pending: 'radio_button_unchecked' };
    var STATUS_WORDS = { complete: 'complete', problem: 'has a problem', pending: 'not done yet' };

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

    // What a stage shows: a problem when fields on it are marked invalid, otherwise what was recorded for it, otherwise not done yet.
    function paneStatus(recorded, hasInvalidMark) {
        if (hasInvalidMark) return 'problem';
        return recorded === 'complete' || recorded === 'problem' ? recorded : 'pending';
    }

    // How many of the shown tabs are complete. statusOf(tabId) gives each one's status.
    function progress(tabs, statusOf) {
        var list = shown(tabs);
        var done = 0;
        list.forEach(function (tab) { if (statusOf(tab.id) === 'complete') done++; });
        return { done: done, total: list.length };
    }

    function progressLabel(counts) {
        return counts.done + ' of ' + counts.total + (counts.total === 1 ? ' stage' : ' stages') + ' complete. After Save Staff you go on to Add contract.';
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

    function readRecorded(form) {
        try {
            var parsed = JSON.parse((form.dataset && form.dataset.stages) || '{}');
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    // options.saveStep(nextPaneId) keeps the form as a draft before moving on; it answers true or false (or a promise of one).
    function init(doc, showTab, options) {
        var form = doc.getElementById('staffCreateForm');
        var nav = doc.getElementById('staffCreateTabs');
        if (!form || !nav) return null;
        options = options || {};
        var buttons = Array.prototype.slice.call(nav.querySelectorAll('[data-bs-toggle="tab"]'));
        var recorded = readRecorded(form);

        showTab = showTab || function (id) {
            root.bootstrap.Tab.getOrCreateInstance(doc.getElementById(id)).show();
            nav.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        };

        // The draft saved with this form: what the page has on offer, looked up when it is needed because that script loads after this one
        function saver() {
            if (options.saveStep) return options.saveStep;
            var drafts = root.StaffDrafts && root.StaffDrafts.instance;
            return drafts && drafts.saveStep ? drafts.saveStep : null;
        }

        function paneId(buttonId) { return String(buttonId || '').replace(/-tab$/, ''); }

        function tabs() {
            return buttons.map(function (button) {
                var item = button.closest('li');
                return { id: button.id, hidden: !!item && item.style.display === 'none' };
            });
        }

        function paneById(id) {
            var panes = form.querySelectorAll('.tab-pane');
            for (var i = 0; i < panes.length; i++) {
                if (panes[i].id === id) return panes[i];
            }
            return null;
        }

        function statusOf(tabId) {
            var id = paneId(tabId);
            var pane = paneById(id);
            return paneStatus(recorded[id], !!(pane && pane.querySelector && pane.querySelector('.is-invalid')));
        }

        // The status of every tab, for the draft: tabs that are not shown are 'skipped'
        function statuses() {
            var out = {};
            tabs().forEach(function (tab) { out[paneId(tab.id)] = tab.hidden ? 'skipped' : statusOf(tab.id); });
            return out;
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

            buttons.forEach(function (button) {
                var mark = button.querySelector && button.querySelector('.js-stage-mark');
                if (!mark) return;
                var status = statusOf(button.id);
                mark.textContent = STATUS_ICONS[status];
                mark.className = 'material-symbols-outlined js-stage-mark stage-' + status;
                if (mark.setAttribute) mark.setAttribute('title', STATUS_WORDS[status].charAt(0).toUpperCase() + STATUS_WORDS[status].slice(1));
            });

            var counts = progress(all, statusOf);
            var summary = doc.getElementById('staffStageSummary');
            if (summary) summary.textContent = progressLabel(counts);
            var bar = doc.getElementById('staffStageBar');
            if (bar) {
                var percent = counts.total ? Math.round(counts.done / counts.total * 100) : 0;
                if (bar.setAttribute) bar.setAttribute('aria-valuenow', String(percent));
                var fill = bar.querySelector && bar.querySelector('.progress-bar');
                if (fill && fill.style) fill.style.width = percent + '%';
            }
        }

        form.addEventListener('click', function (event) {
            var button = event.target.closest('.js-next-step');
            if (!button) return;
            var pane = button.closest('.tab-pane');
            var unfinished = firstUnfinished(pane);
            if (unfinished) {
                recorded[pane.id] = 'problem';
                refresh();
                unfinished.reportValidity();
                return;
            }
            recorded[pane.id] = 'complete';
            refresh();
            var next = nextTab(tabs(), pane.id + '-tab');
            if (!next) return;
            var save = saver();
            if (!save) {
                showTab(next);
                return;
            }
            // Saved first; if the server refuses, the person stays here with the reason shown, and nothing is lost
            button.disabled = true;
            Promise.resolve(save(paneId(next))).then(function (saved) {
                button.disabled = false;
                if (saved !== false) showTab(next);
            }, function () {
                button.disabled = false;
            });
        });

        // Leaving a tab settles it: complete when everything needed on it is filled in, otherwise not done (or still a problem)
        nav.addEventListener('hide.bs.tab', function (event) {
            var id = paneId(event && event.target && event.target.id);
            var pane = paneById(id);
            if (!pane) return;
            var invalid = firstUnfinished(pane) !== null;
            recorded[id] = invalid ? (recorded[id] === 'problem' ? 'problem' : 'pending') : 'complete';
            refresh();
        });

        // Editing a tab that was marked complete or a problem puts it back to be decided again when it is left
        form.addEventListener('input', function (event) {
            var pane = event.target && event.target.closest && event.target.closest('.tab-pane');
            if (!pane) return;
            var invalid = firstUnfinished(pane) !== null;
            if (recorded[pane.id] === 'complete' && invalid) recorded[pane.id] = 'pending';
            else if (recorded[pane.id] === 'problem' && !invalid) recorded[pane.id] = 'pending';
            // The page clears the red marks on a field in its own listener; look again once that has run
            (root.setTimeout || function (callback) { callback(); })(refresh, 0);
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

        return { statuses: statuses, refresh: refresh };
    }

    root.StaffFormSteps = {
        nextTab: nextTab, stepLabel: stepLabel, firstUnfinished: firstUnfinished, init: init,
        paneStatus: paneStatus, progress: progress, progressLabel: progressLabel
    };

    if (root.document) {
        var start = function () { root.StaffFormSteps.instance = init(root.document); };
        if (root.document.readyState === 'loading') {
            root.document.addEventListener('DOMContentLoaded', start);
        } else {
            start();
        }
    }
})(typeof window !== 'undefined' ? window : this);
