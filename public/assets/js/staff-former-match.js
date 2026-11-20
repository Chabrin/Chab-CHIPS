// Add Staff: as soon as a National ID is typed, say if that person is already on record. Someone who worked here before is not added a
// second time: HR opens their record and uses Rehire, so they keep their staff number. The address to ask is on the field
// (data-former-url); only the name, staff number, status and a link come back.
(function (root) {
    'use strict';

    // What to tell HR about the match: { tone, text, linkText } or null when there is nothing to say
    function describe(match) {
        if (!match || !match.found) return null;
        var who = match.name + ' (staff number ' + match.staff_number + ', ' + match.status + ')';
        if (match.can_rehire) {
            return {
                tone: 'warning',
                text: 'This ID is already on record: ' + who + '. They worked here before, so do not add them again. Open their record and use Rehire, so they keep their staff number.',
                linkText: 'Open their record to rehire'
            };
        }
        return {
            tone: 'danger',
            text: 'This ID is already on record: ' + who + '. A person cannot be added twice.',
            linkText: 'Open their record'
        };
    }

    // Show or clear the message under the field
    function render(doc, box, match) {
        var info = describe(match);
        box.textContent = '';
        box.classList.remove('alert-warning', 'alert-danger');
        if (!info) {
            box.classList.add('d-none');
            return;
        }
        box.classList.add('alert-' + info.tone);
        box.appendChild(doc.createTextNode(info.text + ' '));
        var link = doc.createElement('a');
        link.href = match.url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = info.linkText;
        box.appendChild(link);
        box.classList.remove('d-none');
    }

    function attach(doc, fetchFn) {
        var input = doc.getElementById('national_id');
        var box = doc.getElementById('formerMatch');
        if (!input || !box || !input.getAttribute('data-former-url')) return null;
        var latest = 0;

        function check() {
            var value = input.value.trim();
            var ask = ++latest;
            if (value.length < 4) {
                render(doc, box, null);
                return;
            }
            fetchFn(input.getAttribute('data-former-url') + '?national_id=' + encodeURIComponent(value), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (match) {
                    // An answer for an ID that was typed over since is ignored
                    if (ask === latest) render(doc, box, match);
                })
                .catch(function () { if (ask === latest) render(doc, box, null); });
        }

        input.addEventListener('change', check);
        input.addEventListener('blur', check);
        return { check: check };
    }

    root.StaffFormerMatch = { describe: describe, render: render, attach: attach };

    if (root.document && root.fetch) {
        var start = function () { attach(root.document, root.fetch.bind(root)); };
        if (root.document.readyState === 'loading') {
            root.document.addEventListener('DOMContentLoaded', start);
        } else {
            start();
        }
    }
})(typeof window !== 'undefined' ? window : this);
