// Citizenship and residency on the staff forms (resources/views/staff/partials/citizenship-fields.blade.php).
// - Kenya: Resident is set (HR can change it) and the National ID is required.
// - Any other country: residency must be chosen, and the passport number, work permit or Alien ID and the permit's expiry date are required
//   (the National ID is then optional).
// The server applies the same rules (App\Services\StaffResidency); this only saves HR a failed save.
(function (root) {
    'use strict';

    // What the country chosen asks for
    function rulesFor(nationality) {
        var country = String(nationality || '').trim().toLowerCase();
        var known = country !== '';
        var home = country === 'kenya';
        return {
            known: known,
            home: home,
            // Until a country is chosen the form behaves as it always did
            nationalIdRequired: !known || home,
            permitRequired: known && !home,
            defaultResidency: home ? 'RESIDENT' : null
        };
    }

    // Mark a field required (or not) the way its form does: the required attribute (Add Staff) or data-required (Edit Staff)
    function setRequired(doc, el, on, attribute) {
        if (!el) return;
        if (attribute === 'required') {
            el.required = !!on;
        } else if (on) {
            el.setAttribute('data-required', 'true');
        } else {
            el.removeAttribute('data-required');
            if (el.classList) el.classList.remove('is-invalid');
        }
        // The red star beside the label
        var label = doc.querySelector('label[for="' + el.id + '"]');
        if (!label) return;
        var star = label.querySelector('.js-required-star');
        if (on && !star) {
            star = doc.createElement('span');
            star.className = 'text-danger js-required-star';
            star.textContent = ' *';
            label.appendChild(star);
        } else if (!on && star) {
            star.remove();
        }
        // The one the page already printed beside National ID
        var printed = label.querySelector('.text-danger:not(.js-required-star)');
        if (printed) printed.classList.toggle('d-none', !on);
    }

    function attach(doc) {
        var country = doc.getElementById('nationality');
        var residency = doc.getElementById('residency_status');
        if (!country || !residency) return null;
        var attribute = country.getAttribute('data-required-attribute') || 'required';
        var permit = doc.getElementById('permitFields');
        var note = doc.getElementById('residencyNote');

        function apply(fromUser) {
            var rules = rulesFor(country.value);

            if (fromUser) {
                if (rules.defaultResidency && (residency.value === '' || residency.getAttribute('data-auto') === '1')) {
                    // Kenya means Resident, unless HR has chosen otherwise
                    residency.value = rules.defaultResidency;
                    residency.setAttribute('data-auto', '1');
                } else if (!rules.home && residency.getAttribute('data-auto') === '1') {
                    // Another country: residency was only set for Kenya, so HR has to choose it
                    residency.value = '';
                    residency.removeAttribute('data-auto');
                }
            }

            setRequired(doc, doc.getElementById('national_id'), rules.nationalIdRequired, attribute);
            ['passport', 'work_permit_number', 'work_permit_expiry'].forEach(function (id) {
                setRequired(doc, doc.getElementById(id), rules.permitRequired, attribute);
            });
            if (permit) permit.classList.toggle('d-none', !rules.permitRequired);
            if (note) {
                note.textContent = rules.permitRequired
                    ? 'Choose Resident or Non-resident. A passport, work permit (or Alien ID) and its expiry date are needed.'
                    : 'Kenya is set to Resident. You can change it.';
            }
        }

        country.addEventListener('input', function () { apply(true); });
        country.addEventListener('change', function () { apply(true); });
        residency.addEventListener('change', function () { residency.removeAttribute('data-auto'); });
        apply(false); // what is already on the form (a saved record, a draft) is shown as it is, not changed

        return { apply: apply };
    }

    root.StaffCitizenship = { rulesFor: rulesFor, attach: attach };

    if (root.document) {
        if (root.document.readyState === 'loading') {
            root.document.addEventListener('DOMContentLoaded', function () { attach(root.document); });
        } else {
            attach(root.document);
        }
    }
})(typeof window !== 'undefined' ? window : this);
