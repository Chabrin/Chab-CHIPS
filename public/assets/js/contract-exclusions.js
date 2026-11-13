// Contract form, "Recurring for all staff": the company-wide items HR leaves out for this one person, each with a reason.
// The table is drawn again whenever the pay on the form changes, so what has been chosen is kept here and written into
// hidden inputs (exclusions[ID][excluded] and exclusions[ID][reason]) that the server reads. The saved choices are in the
// hidden inputs from the first moment, so a form sent before the table has loaded cannot put an item back by accident.
(function (root) {
    'use strict';

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }

    // The ids are numbers; anything else is not an item
    function cleanId(id) {
        var text = String(id == null ? '' : id).trim();
        return /^\d+$/.test(text) ? text : '';
    }

    // The saved choices as the page carries them ({"4":"Has a company car"}); nothing usable gives no choices
    function parse(text) {
        try {
            var data = JSON.parse(text);
            return data && typeof data === 'object' && !Array.isArray(data) ? data : {};
        } catch (error) {
            return {};
        }
    }

    function create(initial) {
        var left = {};
        Object.keys(initial || {}).forEach(function (id) {
            if (cleanId(id)) left[cleanId(id)] = String(initial[id] == null ? '' : initial[id]);
        });

        return {
            isLeftOut: function (id) {
                return Object.prototype.hasOwnProperty.call(left, cleanId(id));
            },
            reason: function (id) {
                return this.isLeftOut(id) ? left[cleanId(id)] : '';
            },
            // Leave an item out (with its reason so far) or put it back
            set: function (id, on, reason) {
                if (!cleanId(id)) return;
                if (on) left[cleanId(id)] = String(reason == null ? '' : reason);
                else delete left[cleanId(id)];
            },
            setReason: function (id, reason) {
                if (this.isLeftOut(id)) left[cleanId(id)] = String(reason == null ? '' : reason);
            },
            // The items that no longer apply to all staff cannot be left out: drop them and say which
            keepOnly: function (ids) {
                var known = {};
                (ids || []).forEach(function (id) { known[cleanId(id)] = true; });
                var dropped = Object.keys(left).filter(function (id) { return !known[id]; });
                dropped.forEach(function (id) { delete left[id]; });
                return dropped;
            },
            ids: function () {
                return Object.keys(left);
            },
            // The ids of items left out with no reason yet
            withoutReason: function () {
                return Object.keys(left).filter(function (id) { return left[id].trim() === ''; });
            },
            inputsHtml: function () {
                return Object.keys(left).map(function (id) {
                    return '<input type="hidden" name="exclusions[' + id + '][excluded]" value="1">'
                        + '<input type="hidden" name="exclusions[' + id + '][reason]" value="' + escapeHtml(left[id]) + '">';
                }).join('');
            }
        };
    }

    root.ContractExclusions = { create: create, parse: parse, escapeHtml: escapeHtml };
})(typeof window !== 'undefined' ? window : this);
