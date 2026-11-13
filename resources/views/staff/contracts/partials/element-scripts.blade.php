// ── Elements a contract can hold ─────────────────────────────────────────
// Recurring items only. One-time items are entered on Monthly Payroll Items, and the payroll run works out the
// statutory items (NSSF, SHA, Housing Levy, PAYE) itself, so neither is offered here.
const elementsUrl = "{{ url('/ajax/payroll/elements/list/simpler') }}?for=contract";
const allStaffUrl = "{{ url('/ajax/payroll/elements/all-staff-preview') }}";
let contractElements = [];
let loadingRows = false;
let allStaffTimer;
let nextRowIndex = $('#elementsRepeater .element-row').length;

// The company-wide items left out for this person. The choices are written into #exclusionInputs, which the server reads.
const leftOut = ContractExclusions.create(ContractExclusions.parse($('#exclusionInputs').attr('data-left-out')));

function syncExclusionInputs() {
    $('#exclusionInputs').html(leftOut.inputsHtml());
}

// The last column of the table: statutory items always apply; any other can be left out for this person, with a reason
function leftOutCell(row) {
    const $cell = $('<td>');
    if (row.statutory) {
        return $cell.append('<span class="text-muted small">Always applies</span>');
    }
    if (!row.canLeaveOut) {
        return $cell;
    }
    const on = leftOut.isLeftOut(row.id);
    const $toggle = $('<input type="checkbox" class="form-check-input left-out-toggle">')
        .attr({ id: 'leftOut' + row.id, 'data-id': row.id }).prop('checked', on);
    const $label = $('<label class="form-check-label ms-1">').attr('for', 'leftOut' + row.id).text('Leave out');
    const $reason = $('<input type="text" class="form-control form-control-sm mt-1 left-out-reason" maxlength="255" placeholder="Reason (required)">')
        .attr('aria-label', 'Reason for leaving out ' + row.name)
        .val(leftOut.reason(row.id)).toggleClass('d-none', !on).prop('required', on);
    return $cell.append($('<div class="form-check">').append($toggle, $label), $reason);
}

function initEntryType($scope) {
    $scope.find('.entry-type-select').select2({
        placeholder: 'Select entry type',
        allowClear: true,
        width: '100%'
    });
}

function populateElementSelect($select, selectedValue = null) {
    $select.empty().append($('<option>', { value: '', text: 'Select element', disabled: true, selected: true }));
    contractElements.forEach(function (el) {
        $select.append($('<option>', {
            value: el.id,
            text: el.element_name + (el.applies_to === 'all' ? ' (all staff, own amount)' : '')
        }));
    });
    if ($select.hasClass('select2-hidden-accessible')) {
        $select.select2('destroy');
    }
    $select.select2({ placeholder: 'Select Element', allowClear: true });
    if (selectedValue) {
        $select.val(String(selectedValue)).trigger('change.select2');
    }
}

function allowancesTotal() {
    let total = 0;
    $('#elementsRepeater .element-row').each(function () {
        if ($(this).find('.entry-type-select').val() === '2') {
            total += parseFloat($(this).find('input[name$="[amount]"]').val()) || 0;
        }
    });
    return total;
}

function currentGross() {
    return (parseFloat($('#basic_salary').val()) || 0) + allowancesTotal();
}

// What an element is worth from its formula, offered as the amount when the row is still empty
function defaultFor(el) {
    const value = parseFloat(el.amount) || 0;
    const formula = parseInt(el.formula_id, 10);
    if (formula === 2) return ((parseFloat($('#basic_salary').val()) || 0) * value / 100).toFixed(2);
    if (formula === 3) return (currentGross() * value / 100).toFixed(2);
    return value ? value.toFixed(2) : '';
}

// The items that apply to every staff member, worked out from the pay on this form
function refreshAllStaff() {
    clearTimeout(allStaffTimer);
    allStaffTimer = setTimeout(function () {
        $.getJSON(allStaffUrl, {
            basic: parseFloat($('#basic_salary').val()) || 0,
            allowances: allowancesTotal()
        }).done(function (res) {
            const fmt = n => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            // An item that no longer applies to all staff cannot stay left out: drop it, and say so
            const dropped = leftOut.keepOnly((res.elements || []).map(e => e.id));
            if (dropped.length) {
                syncExclusionInputs();
                toastr.info('An item that was left out for this person no longer applies to all staff, so it has been removed from the list.');
            }
            const $body = $('#allStaffItems tbody').empty();
            const rowClass = { group: 'fw-semibold', total: 'fw-bold table-light' };
            PayrollAllStaffRows.build(res.elements).forEach(function (row) {
                $body.append($('<tr>').addClass(rowClass[row.kind] || '').append(
                    $('<td>').toggleClass('ps-4', row.kind === 'tier').text(row.name),
                    $('<td>').text(row.type),
                    $('<td>').text(row.how),
                    $('<td class="text-end">').text(row.kind === 'group' ? '' : row.amount === null ? '-' : fmt(row.amount)),
                    leftOutCell(row)
                ));
            });
            if (!res.elements || !res.elements.length) {
                $body.append('<tr><td colspan="5" class="text-muted">No items apply to all staff.</td></tr>');
            }
        }).fail(function () {
            toastr.error('Failed to load the items that apply to all staff.');
        });
    }, 300);
}

function rowTemplate(index) {
    return `
        <div class="row element-row mb-2">
            <div class="mb-3 col-6">
                <label class="form-label">Element</label>
                <select class="form-select element-select" name="elements[${index}][element]"></select>
            </div>
            <div class="mb-3 col-6">
                <label class="form-label">Entry Type</label>
                <select class="form-select entry-type-select" name="elements[${index}][entry_type]">
                    <option value="" disabled selected>Select entry type</option>
                    <option value="2">Allowance</option>
                    <option value="1">Deduction</option>
                </select>
                <small class="text-muted">Set from the element you choose.</small>
            </div>
            <div class="mb-3 col-10">
                <label class="form-label">Amount</label>
                <input type="number" class="form-control" name="elements[${index}][amount]" min="0" step="0.01">
            </div>
            <div class="mb-3 col-2 d-flex align-items-end">
                <button type="button" class="btn btn-light btn-sm d-flex align-items-center remove-element-row"><span class="material-symbols-outlined me-2">remove</span> Remove</button>
            </div>
            ${ContractElementTerms.fieldsHtml(index)}
        </div>`;
}

$.getJSON(elementsUrl).done(function (res) {
    contractElements = res.elements || [];
    loadingRows = true;
    $('#elementsRepeater .element-row').each(function () {
        const $select = $(this).find('.element-select');
        populateElementSelect($select, $select.data('selected'));
    });
    loadingRows = false;
    initEntryType($('#elementsRepeater'));
    refreshAllStaff();
}).fail(function () {
    toastr.error('Failed to load payroll elements.');
});

$('#addElementRow').on('click', function () {
    const $row = $(rowTemplate(nextRowIndex++));
    $('#elementsRepeater').append($row);
    populateElementSelect($row.find('.element-select'));
    initEntryType($row);
});

$(document).on('click', '.remove-element-row', function () {
    $(this).closest('.element-row').remove();
    refreshAllStaff();
});

// Choosing an element sets its entry type and, when the amount is empty, the amount its formula gives
$(document).on('change', '.element-select', function () {
    if (loadingRows) return;
    const el = contractElements.find(e => String(e.id) === String($(this).val()));
    if (el) {
        const $row = $(this).closest('.element-row');
        $row.find('.entry-type-select').val(String(el.element_type)).trigger('change.select2');
        ContractElementTerms.syncRow($row[0]);
        const $amount = $row.find('input[name$="[amount]"]');
        if ($amount.val() === '') {
            $amount.val(defaultFor(el));
        }
    }
    refreshAllStaff();
});

$(document).on('input change', '#basic_salary, .entry-type-select, input[name$="[amount]"]', refreshAllStaff);

// How often / Which month / Part month: the month box only for "once only", and the part-month answer follows the entry type
// until someone chooses it themselves
$(document).on('change', '.frequency-select, .entry-type-select', function () {
    ContractElementTerms.syncRow($(this).closest('.element-row')[0]);
});
$(document).on('change', '.prorate-select', function () {
    ContractElementTerms.markTouched(this);
});
$(document).on('change', '.once-month-input', function () {
    $(this).removeClass('is-invalid');
});

// Leaving a company-wide item out for this person
$(document).on('change', '.left-out-toggle', function () {
    const $reason = $(this).closest('td').find('.left-out-reason');
    leftOut.set($(this).data('id'), this.checked, '');
    $reason.val('').toggleClass('d-none', !this.checked).prop('required', this.checked);
    if (this.checked) {
        $reason.trigger('focus');
    }
    syncExclusionInputs();
});
$(document).on('input', '.left-out-reason', function () {
    leftOut.setReason($(this).closest('td').find('.left-out-toggle').data('id'), $(this).val());
    syncExclusionInputs();
});

// A row left without an element is dropped instead of failing the whole form
$('#staffContractCreateForm').on('submit', function (event) {
    $('#elementsRepeater .element-row').each(function () {
        if (!$(this).find('.element-select').val()) {
            $(this).remove();
        }
    });

    // A once-only item has to fall in a month the contract runs, or the payroll run would never take it
    const start = $('#date_start').val() || '';
    const end = $('#date_end').prop('disabled') ? '' : ($('#date_end').val() || '');
    let problem = '';
    $('#elementsRepeater .once-month-input:not(:disabled)').each(function () {
        const found = ContractElementTerms.onceMonthProblem($(this).val(), start, end);
        $(this).toggleClass('is-invalid', found !== '');
        if (found && !problem) {
            problem = found;
            $(this).trigger('focus');
        }
    });
    if (problem) {
        // Stops the spinner handler that follows from locking the button on a form that was not sent
        event.preventDefault();
        event.stopImmediatePropagation();
        toastr.error(problem);
    }
});
