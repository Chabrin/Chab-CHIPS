// Staff contact details: mobile numbers start with the country code (+254712345678) and company emails are
// firstname.surname@company domain. The server applies the same rules (App\Support\StaffContact); this only
// saves HR a round trip, so keep the two in step.
(function (root) {
    'use strict';

    var COUNTRY_CODE = '254';

    // 0712 345 678, 254712345678 and +254 712 345 678 all become +254712345678. Anything else is returned tidied but unchanged.
    function formatMobile(value) {
        var number = String(value == null ? '' : value).trim().replace(/[\s\-().]/g, '');
        if (number === '') return '';
        if (number.indexOf('00') === 0) number = '+' + number.slice(2);

        // +254 0712..., a zero left in after the country code
        var leftInZero = number.match(new RegExp('^\\+?' + COUNTRY_CODE + '0([17]\\d{8})$'));
        if (leftInZero) return '+' + COUNTRY_CODE + leftInZero[1];
        if (number.charAt(0) === '+') return number;
        if (new RegExp('^' + COUNTRY_CODE + '[17]\\d{8}$').test(number)) return '+' + number;
        var local = number.match(/^0([17]\d{8})$/);
        if (local) return '+' + COUNTRY_CODE + local[1];
        if (/^[17]\d{8}$/.test(number)) return '+' + COUNTRY_CODE + number;

        return number;
    }

    function letters(word) {
        return word.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z-]/g, '').replace(/^-+|-+$/g, '');
    }

    // firstname.surname@domain from full names (first word and last word), or '' when there is only one name
    function suggestEmail(fullNames, domain) {
        var words = String(fullNames == null ? '' : fullNames).trim().split(/\s+/).map(letters).filter(Boolean);
        return words.length < 2 ? '' : words[0] + '.' + words[words.length - 1] + '@' + domain;
    }

    // Tidy a mobile number when the person leaves the box, if it can be read as one
    function init(doc) {
        var inputs = doc.querySelectorAll('[data-mobile-format]');
        Array.prototype.forEach.call(inputs, function (input) {
            input.addEventListener('blur', function () {
                var formatted = formatMobile(input.value);
                if (formatted.charAt(0) === '+') input.value = formatted;
            });
        });
    }

    root.StaffContactFormat = { formatMobile: formatMobile, suggestEmail: suggestEmail, init: init };

    if (root.document) {
        if (root.document.readyState === 'loading') {
            root.document.addEventListener('DOMContentLoaded', function () { init(root.document); });
        } else {
            init(root.document);
        }
    }
})(typeof window !== 'undefined' ? window : this);
