(function ($) {
    'use strict';

    const escape = value => $('<div>').text(value ?? '').html();
    const number = value => new Intl.NumberFormat('en-KE', {maximumFractionDigits: 2}).format(Number(value));

    window.SlaBillingEditor = function (config) {
        const $modal = $('#addInvoiceableItemModal');
        const $select = $('#modal_item_select');
        const $add = $('#addInvoiceableItemBtn');
        let options = [];
        let items = (config.initialItems || []).slice();
        let editingIndex = null;
        let loading = true;

        // Restore staged choices after a server validation error, including deliberately removed items.
        if (config.submittedItems !== null && config.submittedItems !== undefined) {
            const submitted = config.submittedItems;
            items = Object.keys(submitted).filter(id => submitted[id].selected).map(id => ({
                ...(items.find(item => String(item.item_id) === String(id)) || {}),
                ...submitted[id], item_id: id,
            }));
        }

        function render() {
            $('#slaItemCount').text(items.length);
            $('#selectedInvoiceableItemsBody').html(items.length ? items.map((entry, index) => {
                const method = entry.choice === 'amount' ? 'Fixed amount' : entry.choice === 'percentage' ? '% of rent' : 'Value unset';
                const value = entry.choice === 'amount' && entry.amount !== null && entry.amount !== '' ? number(entry.amount)
                    : entry.choice === 'percentage' && entry.percentage_of_rent !== null && entry.percentage_of_rent !== '' ? number(entry.percentage_of_rent) + '%' : 'Not set';
                return `<tr><td>${escape(entry.item_name || 'Billing item')}</td><td>${escape(entry.billing_code || '—')}</td><td>${escape(entry.default_cycle_name || 'Not specified')}</td><td><span class="sla-charge-method">${method}</span></td><td class="text-end">${value}</td><td class="text-end text-nowrap"><button type="button" class="btn btn-link btn-sm sla-edit-item" data-index="${index}" aria-label="Edit ${escape(entry.item_name)}">Edit</button><button type="button" class="btn btn-link btn-sm text-danger sla-remove-item" data-index="${index}" aria-label="Remove ${escape(entry.item_name)}">Remove</button></td></tr>`;
            }).join('') : '<tr><td colspan="6" class="sla-empty"><strong>No additional charges configured</strong><p>Add a billing item if tenants pay charges in addition to rent.</p><button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addInvoiceableItemModal">Add your first billing item</button></td></tr>');

            // A marker lets an empty list survive validation redirects instead of restoring old items.
            let hidden = '<input type="hidden" name="billing_items_present" value="1">';
            items.forEach(entry => {
                const id = escape(entry.item_id);
                hidden += `<input type="hidden" name="items[${id}][selected]" value="1">`;
                if (entry.choice === 'amount' || entry.choice === 'percentage') {
                    const field = entry.choice === 'amount' ? 'amount' : 'percentage_of_rent';
                    hidden += `<input type="hidden" name="items[${id}][choice]" value="${entry.choice}"><input type="hidden" name="items[${id}][${field}]" value="${escape(entry[field])}">`;
                }
            });
            $('#invoiceableItemsHiddenInputs').html(hidden);
        }

        function updateOptions() {
            const current = $select.val();
            $select.empty().append(new Option('Search or select a billing item...', ''));
            options.forEach(item => {
                const used = items.some((entry, index) => index !== editingIndex && String(entry.item_id) === String(item.id));
                const option = new Option(item.item_name + (used ? ' (already added)' : ''), item.id);
                option.disabled = used;
                $select.append(option);
            });
            $select.val(current).trigger('change');
        }

        function loadOptions() {
            loading = true;
            $select.prop('disabled', true);
            $add.prop('disabled', true);
            $('#slaRetryItems').addClass('d-none');
            $('#modal_item_select_error').text('');
            $.getJSON(config.itemsUrl).done(response => {
                options = response.items || [];
                loading = false;
                items = items.map(entry => {
                    const item = options.find(option => String(option.id) === String(entry.item_id));
                    return item ? {...entry, item_name: item.item_name, billing_code: item.billing_code, default_cycle_name: item.default_cycle_name} : entry;
                });
                updateOptions();
                $select.prop('disabled', !options.length);
                if (!options.length) $('#modal_item_select_error').text('No tenant billing items are available. Add an item in billing setup first.');
                render();
            }).fail(() => {
                loading = false;
                $('#modal_item_select_error').text('Billing items could not be loaded. Please try again.');
                $('#slaRetryItems').removeClass('d-none');
            });
        }

        function summary() {
            const selected = options.find(item => String(item.id) === String($select.val()));
            const method = $('input[name="modal_item_choice"]:checked').val();
            let text = 'Select a billing item to see a summary.';
            if (selected) {
                const cycle = selected.default_cycle_name ? ` · Billing cycle: ${selected.default_cycle_name}` : '';
                const amount = $('#modal_item_amount').val();
                const percent = $('#modal_item_pct').val();
                text = selected.item_name + (method === 'amount' ? (amount !== '' ? ` · Fixed amount: ${number(amount)}` : ' · Enter a fixed amount')
                    : method === 'percentage' ? (percent !== '' ? ` · ${number(percent)}% of rent` : ' · Enter a percentage of rent') : ' · No preset value') + cycle;
            }
            $('#slaItemPreview').text(text);
        }

        function changeMethod() {
            const method = $('input[name="modal_item_choice"]:checked').val();
            $('#slaAmountField').prop('hidden', method !== 'amount');
            $('#slaPercentageField').prop('hidden', method !== 'percentage');
            $('#modal_item_amount').prop('disabled', method !== 'amount');
            $('#modal_item_pct').prop('disabled', method !== 'percentage');
            $('#modal_item_value_error').text('');
            $('#modal_item_amount, #modal_item_pct').removeClass('is-invalid').removeAttr('aria-invalid');
            summary();
        }

        function reset() {
            editingIndex = null;
            $('#addInvoiceableItemModalLabel').text('Add billing item');
            $add.text('Add billing item');
            $('#modal_item_select_error, #modal_item_value_error').text('');
            $('#modal_item_amount, #modal_item_pct').val('').removeClass('is-invalid').removeAttr('aria-invalid');
            $('#modal_choice_unset').prop('checked', true);
            $select.val('');
            updateOptions();
            changeMethod();
        }

        $select.on('change', function () {
            const item = options.find(option => String(option.id) === String(this.value));
            $('#modal_item_billing_code').text(item ? item.billing_code || 'Not specified' : 'Select an item');
            $('#modal_item_cycle').text(item ? item.default_cycle_name || 'Not specified' : 'Select an item');
            $add.prop('disabled', loading || !item);
            if (item) $('#modal_item_select_error').text('');
            summary();
        });
        $('input[name="modal_item_choice"]').on('change', changeMethod);
        $('#modal_item_amount, #modal_item_pct').on('input', function () {
            $(this).removeClass('is-invalid').removeAttr('aria-invalid');
            $('#modal_item_value_error').text('');
            summary();
        });
        $('#slaRetryItems').on('click', loadOptions);
        $modal.on('hidden.bs.modal', reset).on('shown.bs.modal', function () {
            if (!loading && options.length) {
                if ($select.data('select2')) $select.select2('open');
                else $select.trigger('focus');
            }
        });
        if ($.fn.select2) $select.select2({dropdownParent: $modal, width: '100%', placeholder: 'Search or select a billing item...'});

        $add.on('click', function () {
            const itemId = $select.val();
            const item = options.find(option => String(option.id) === String(itemId));
            if (!item || items.some((entry, index) => index !== editingIndex && String(entry.item_id) === String(itemId))) {
                $('#modal_item_select_error').text('Choose a billing item that has not already been added.');
                return;
            }
            const method = $('input[name="modal_item_choice"]:checked').val();
            const $value = method === 'amount' ? $('#modal_item_amount') : $('#modal_item_pct');
            if (method !== 'unset' && ($value.val().trim() === '' || !$value[0].checkValidity() || !Number.isFinite(Number($value.val())))) {
                $('#modal_item_value_error').text(method === 'amount' ? 'Enter an amount of zero or more, with up to two decimal places.' : 'Enter a percentage of zero or more, with up to two decimal places.');
                $value.addClass('is-invalid').attr('aria-invalid', 'true').trigger('focus');
                return;
            }
            const entry = {item_id: itemId, item_name: item.item_name, billing_code: item.billing_code,
                default_cycle_name: item.default_cycle_name, choice: method === 'unset' ? null : method,
                amount: method === 'amount' ? $value.val() : null, percentage_of_rent: method === 'percentage' ? $value.val() : null};
            if (editingIndex === null) items.push(entry);
            else items[editingIndex] = entry;
            render();
            $modal.modal('hide');
        });
        $(document).on('click', '.sla-remove-item', function () {
            items.splice(Number($(this).data('index')), 1);
            render();
            updateOptions();
        }).on('click', '.sla-edit-item', function () {
            reset();
            editingIndex = Number($(this).data('index'));
            const entry = items[editingIndex];
            updateOptions();
            $select.val(String(entry.item_id)).trigger('change');
            $(`input[name="modal_item_choice"][value="${entry.choice || 'unset'}"]`).prop('checked', true);
            $('#modal_item_amount').val(entry.amount ?? '');
            $('#modal_item_pct').val(entry.percentage_of_rent ?? '');
            $('#addInvoiceableItemModalLabel').text('Edit billing item');
            $add.text('Update billing item');
            changeMethod();
            $modal.modal('show');
        });
        render();
        loadOptions();
        return {setItems: function (next) { items = (next || []).slice(); render(); updateOptions(); }};
    };

    $(function () {
        // Keep saved tax values on first render; changing the treatment starts with its usual defaults.
        function syncVat(reset) {
            const treatment = $('#vat_able').val();
            $('#slaVatPercentageField').prop('hidden', treatment !== 'Yes');
            $('#slaFlatVatField').prop('hidden', treatment !== 'Flat Rate');
            $('#vat_percentage').prop('disabled', treatment !== 'Yes');
            $('#flat_rate_vat').prop('disabled', treatment !== 'Flat Rate');
            if (reset) {
                $('#vat_percentage').val(treatment === 'Yes' ? '16' : '0');
                $('#flat_rate_vat').val(treatment === 'Flat Rate' ? '' : '0');
            }
        }
        $('#vat_able').on('change', function () { syncVat(true); });
        syncVat(false);

        function showTerm() {
            const start = $('#date_start').val();
            const duration = Number($('#duration_years').val());
            const $preview = $('#slaTermPreview span:last-child');
            if (!start || !Number.isInteger(duration) || duration < 1) {
                $preview.text('Select a start date and duration to preview the end date.');
                return;
            }
            const parts = start.split('-').map(Number);
            if (parts[0] + duration > 9999) {
                $preview.text('Check the start date and duration.');
                return;
            }
            const end = new Date(start + 'T00:00:00Z');
            end.setUTCFullYear(parts[0] + duration);
            const formatted = new Intl.DateTimeFormat('en-GB', {day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC'}).format(end);
            $preview.text('Agreement end date: ' + formatted + ' · ' + duration + (duration === 1 ? ' year' : ' years') + ' from the start date.');
        }
        $('#date_start, #duration_years').on('input change', showTerm);
        showTerm();

        const input = document.getElementById('sla_documents');
        if (!input) return;
        const $picker = $(input).closest('.sla-document-picker');
        const $list = $('<ul class="sla-file-list" aria-label="Selected documents"></ul>').appendTo($picker);
        const $error = $('<p class="text-danger small mt-2 mb-0" role="alert"></p>').appendTo($picker);
        const fileError = file => !/\.(pdf|doc|docx)$/i.test(file.name) ? 'Use PDF, DOC or DOCX files.' : file.size > 10 * 1024 * 1024 ? 'Each file must be 10 MB or smaller.' : '';
        function renderFiles() {
            const files = Array.from(input.files);
            const invalid = files.find(file => fileError(file));
            input.setCustomValidity(invalid ? fileError(invalid) : '');
            $error.text(invalid ? invalid.name + ': ' + fileError(invalid) : '');
            $list.empty();
            files.forEach((file, index) => {
                const $row = $('<li>');
                $('<span class="sla-file-name">').text(file.name).appendTo($row);
                $('<small>').text(file.size >= 1024 * 1024 ? (file.size / 1024 / 1024).toFixed(1) + ' MB' : Math.max(1, Math.round(file.size / 1024)) + ' KB').appendTo($row);
                $('<button type="button" class="btn btn-link text-danger">Remove</button>').attr('aria-label', 'Remove ' + file.name).on('click', function () {
                    const transfer = new DataTransfer();
                    Array.from(input.files).forEach((entry, current) => { if (current !== index) transfer.items.add(entry); });
                    input.files = transfer.files;
                    renderFiles();
                }).appendTo($row);
                $list.append($row);
            });
        }
        $(input).on('change', renderFiles);
        renderFiles();
    });
})(jQuery);
