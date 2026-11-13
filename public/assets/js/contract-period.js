// Contract form: the end date follows from the start date and the contract period, and a probation period gets its own end
// date. The server works the dates out the same way (App\Services\ContractPeriod); this saves a round trip, so keep the two
// in step. Both are tested against tests/fixtures/contract-period-cases.json.
(function (root) {
    'use strict';

    var UNITS = ['days', 'weeks', 'months', 'years'];

    // 2027-01-31 as a date, or null when it is not a real date
    function parse(text) {
        var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(text == null ? '' : text).trim());
        if (!match) return null;
        var year = Number(match[1]), month = Number(match[2]) - 1, day = Number(match[3]);
        var date = new Date(Date.UTC(year, month, day));
        return date.getUTCFullYear() === year && date.getUTCMonth() === month && date.getUTCDate() === day ? date : null;
    }

    function pad(number) {
        return (number < 10 ? '0' : '') + number;
    }

    function format(date) {
        return date.getUTCFullYear() + '-' + pad(date.getUTCMonth() + 1) + '-' + pad(date.getUTCDate());
    }

    function addDays(date, days) {
        return new Date(date.getTime() + days * 86400000);
    }

    // Months do not overflow: 31 January plus one month is 28 February, not 3 March
    function addMonths(date, months) {
        var total = date.getUTCFullYear() * 12 + date.getUTCMonth() + months;
        var year = Math.floor(total / 12), month = total % 12;
        var lastDay = new Date(Date.UTC(year, month + 1, 0)).getUTCDate();
        return new Date(Date.UTC(year, month, Math.min(date.getUTCDate(), lastDay)));
    }

    // The last day of a contract: the day before the same date one period later. '' when the start or the period is not usable.
    function endDate(start, value, unit) {
        var begin = parse(start);
        var count = Number(value);
        if (!begin || !isFinite(count) || count < 1 || Math.floor(count) !== count || UNITS.indexOf(unit) === -1) return '';

        var later;
        if (unit === 'days') later = addDays(begin, count);
        else if (unit === 'weeks') later = addDays(begin, count * 7);
        else if (unit === 'months') later = addMonths(begin, count);
        else later = addMonths(begin, count * 12);

        return format(addDays(later, -1));
    }

    function probationEnd(start, months) {
        return endDate(start, months, 'months');
    }

    var current = null;

    // Wires the contract form: #date_start, #period_value, #period_unit, #date_end, #probation_months, #probation_end.
    // Fields that only an end-dated contract has carry data-end-date-field and are hidden for an open-ended one.
    function init(doc) {
        var start = doc.getElementById('date_start');
        if (!start) return null;
        var value = doc.getElementById('period_value');
        var unit = doc.getElementById('period_unit');
        var end = doc.getElementById('date_end');
        var months = doc.getElementById('probation_months');
        var probation = doc.getElementById('probation_end');
        var note = doc.getElementById('dateEndNote');
        var openEnded = false;

        function syncEnd() {
            if (!end || openEnded) return;
            var worked = value && value.value !== '' ? endDate(start.value, value.value, unit ? unit.value : 'months') : '';
            // With a period the end date is worked out and cannot be typed over; without one it is entered as before
            if (value && value.value !== '') {
                end.value = worked;
                end.readOnly = true;
                if (note) note.textContent = 'Worked out from the start date and the period: the last day of the contract.';
            } else {
                end.readOnly = false;
                if (note) note.textContent = 'The last day of the contract.';
            }
        }

        function syncProbation() {
            if (!probation) return;
            probation.value = months && months.value !== '' ? probationEnd(start.value, months.value) : '';
        }

        function setOpenEnded(flag) {
            openEnded = !!flag;
            Array.prototype.forEach.call(doc.querySelectorAll('[data-end-date-field]'), function (field) {
                field.classList.toggle('d-none', openEnded);
                Array.prototype.forEach.call(field.querySelectorAll('input, select'), function (control) { control.disabled = openEnded; });
            });
            if (end) end.required = !openEnded;
            syncEnd();
        }

        [start, value, unit].forEach(function (control) {
            if (control) {
                control.addEventListener('input', function () { syncEnd(); syncProbation(); });
                control.addEventListener('change', function () { syncEnd(); syncProbation(); });
            }
        });
        if (months) {
            months.addEventListener('input', syncProbation);
            months.addEventListener('change', syncProbation);
        }

        var group = doc.querySelector('[data-end-date-field]');
        setOpenEnded(!!group && group.classList.contains('d-none'));
        syncProbation();

        current = { setOpenEnded: setOpenEnded, syncEnd: syncEnd, syncProbation: syncProbation };
        return current;
    }

    root.ContractPeriod = {
        parse: parse, endDate: endDate, probationEnd: probationEnd, init: init,
        setOpenEnded: function (flag) { if (current) current.setOpenEnded(flag); }
    };

    if (root.document) {
        if (root.document.readyState === 'loading') {
            root.document.addEventListener('DOMContentLoaded', function () { init(root.document); });
        } else {
            init(root.document);
        }
    }
})(typeof window !== 'undefined' ? window : this);
