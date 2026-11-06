(function (global) {
    'use strict';

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }
    function displayText(value) {
        return value == null || String(value).trim() === '' || value === '-' ? '<span class="client-list-missing">Not provided</span>' : escapeHtml(value);
    }
    function clientUrl(base, encryptedId) {
        return base.replace(/\/$/, '') + '/' + encodeURIComponent(encryptedId);
    }
    function clientActions(base, row) {
        var url = escapeHtml(clientUrl(base, row.encrypted_id));
        // Viewing follows the existing list/detail route access checks. The legacy
        // can_read Gate flag is not used by this list (permissions_as_gates is off).
        var actions = ['<a href="' + url + '" class="client-row-action" aria-label="View ' + escapeHtml(row.names || 'client') + '"><span class="material-symbols-outlined" aria-hidden="true">visibility</span>View</a>'];
        if (row.can_update) actions.push('<a href="' + url + '/edit" class="client-row-action" aria-label="Edit ' + escapeHtml(row.names || 'client') + '"><span class="material-symbols-outlined" aria-hidden="true">edit_document</span>Edit</a>');
        return actions.length ? '<div class="client-row-actions">' + actions.join('') + '</div>' : '<span class="client-list-missing">Not available</span>';
    }
    global.ChipsClientsList = { escapeHtml: escapeHtml, displayText: displayText, clientUrl: clientUrl, clientActions: clientActions };
    if (typeof document === 'undefined' || !global.jQuery) return;

    global.jQuery(function ($) {
        var page = document.getElementById('clients-list');
        if (!page) return;
        var search = page.querySelector('#clientListSearch');
        var sort = page.querySelector('#clientListSort');
        var filters = page.querySelector('#clientListFilters');
        var fields = Array.from(page.querySelectorAll('[data-client-filter]'));
        var toggle = page.querySelector('#clientListFilterToggle');
        var filterCount = page.querySelector('#clientListFilterCount');
        var clear = page.querySelector('#clientListClear');
        var results = page.querySelector('#clientListResults');
        var error = page.querySelector('#clientListError');
        var errorDetail = page.querySelector('#clientListErrorDetail');
        var tableArea = page.querySelector('#clientListTableArea');
        var pending = null, sequence = 0, timer = null, hasLoaded = false, failed = false;
        var orders = { name: [2, 'asc'], reverse: [2, 'desc'], newest: [6, 'desc'], oldest: [6, 'asc'] };

        function updateFilterControls() {
            var count = fields.filter(function (field) { return field.value.trim() !== ''; }).length;
            filterCount.hidden = count === 0;
            filterCount.textContent = count;
            filterCount.setAttribute('aria-label', count + ' active filters');
            clear.hidden = !search.value.trim() && !count;
        }
        function renderCell(value, type) { return type === 'display' ? displayText(value) : value || ''; }
        var table = $(page.querySelector('#clients-table')).DataTable({
            serverSide: true,
            autoWidth: false,
            dom: 't<"client-list-footer"lip>',
            ajax: function (requestData, callback) {
                var current = ++sequence;
                if (pending && pending.readyState !== 4) pending.abort();
                error.hidden = true;
                tableArea.setAttribute('aria-busy', 'true');
                results.textContent = hasLoaded ? 'Updating clients…' : 'Loading clients…';
                function fail(status) {
                    if (current !== sequence) return;
                    failed = true;
                    tableArea.hidden = true;
                    tableArea.setAttribute('aria-busy', 'false');
                    callback({ draw: Number(requestData.draw), recordsTotal: 0, recordsFiltered: 0, data: [] });
                    results.textContent = '';
                    errorDetail.textContent = status === 401 || status === 419 ? 'Your session has expired. Sign in again, then retry.' : status === 403 ? 'You do not have permission to view clients.' : 'The list could not be loaded. Please try again.';
                    error.hidden = false;
                }
                pending = $.ajax({ url: page.dataset.dataUrl, method: 'GET', data: requestData, dataType: 'json', timeout: 20000 })
                    .done(function (response) {
                        if (current !== sequence) return;
                        if (!response || !Array.isArray(response.data) || !Number.isFinite(Number(response.recordsTotal)) || !Number.isFinite(Number(response.recordsFiltered))) { fail(); return; }
                        failed = false; hasLoaded = true;
                        tableArea.hidden = false;
                        tableArea.setAttribute('aria-busy', 'false');
                        callback(response);
                        if (table) table.columns.adjust();
                    })
                    .fail(function (xhr, textStatus) { if (textStatus !== 'abort') fail(xhr.status); });
            },
            columns: [
                { data: null, visible: false, orderable: false, searchable: false, defaultContent: '' },
                { data: 'id', visible: false, searchable: false },
                { data: 'names', className: 'client-list-name', render: function (value, type, row) {
                    if (type !== 'display') return value || '';
                    var label = displayText(value);
                    return '<a href="' + escapeHtml(clientUrl(page.dataset.clientsUrl, row.encrypted_id)) + '">' + label + '</a>';
                } },
                { data: 'mobile_number', className: 'client-list-nowrap', render: renderCell },
                { data: 'national_id', className: 'client-list-nowrap', render: renderCell },
                { data: 'reference_number', className: 'client-list-nowrap', render: renderCell },
                { data: 'registered_date', className: 'client-list-nowrap', render: renderCell },
                { data: 'encrypted_id', orderable: false, searchable: false, render: function (value, type, row) { return type === 'display' ? clientActions(page.dataset.clientsUrl, row) : ''; } }
            ],
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            order: [[2, 'asc']],
            language: {
                emptyTable: page.querySelector('.client-add-button') ? 'No clients yet. Use Add Client to register the first client.' : 'No clients available.',
                zeroRecords: 'No clients match your search. Try another name or clear the filters.',
                info: 'Showing _START_–_END_ of _TOTAL_ clients',
                infoEmpty: 'Showing 0 clients',
                infoFiltered: '(from _MAX_ total)',
                lengthMenu: 'Show _MENU_ per page',
                loadingRecords: 'Loading clients…',
                thousands: ','
            },
            drawCallback: function () {
                if (failed || !hasLoaded) return;
                var info = this.api().page.info();
                var total = info.recordsTotal.toLocaleString();
                var found = info.recordsDisplay.toLocaleString();
                results.textContent = info.recordsDisplay === info.recordsTotal ? total + (info.recordsTotal === 1 ? ' client' : ' clients') : found + ' of ' + total + ' clients';
            }
        });
        function applyFilters() {
            global.clearTimeout(timer);
            table.search(search.value.trim());
            fields.forEach(function (field) { table.column(Number(field.dataset.clientFilter)).search(field.value.trim()); });
            updateFilterControls();
            table.draw();
        }
        function scheduleSearch() {
            global.clearTimeout(timer);
            updateFilterControls();
            timer = global.setTimeout(applyFilters, 350);
        }
        [search].concat(fields).forEach(function (field) {
            field.addEventListener('input', scheduleSearch);
            field.addEventListener('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); applyFilters(); } });
        });
        toggle.addEventListener('click', function () {
            filters.hidden = !filters.hidden;
            toggle.setAttribute('aria-expanded', String(!filters.hidden));
            if (!filters.hidden) fields[0].focus();
        });
        clear.addEventListener('click', function () {
            search.value = '';
            fields.forEach(function (field) { field.value = ''; });
            applyFilters();
            search.focus();
        });
        sort.addEventListener('change', function () { if (orders[sort.value]) table.order([orders[sort.value]]).draw(); });
        $(page.querySelector('#clients-table')).on('order.dt', function () {
            var order = table.order()[0];
            if (!order) return;
            var key = Object.keys(orders).find(function (name) { return orders[name][0] === Number(order[0]) && orders[name][1] === order[1]; });
            sort.querySelector('[value="custom"]').hidden = Boolean(key);
            sort.value = key || 'custom';
        });
        page.querySelector('#clientListRetry').addEventListener('click', function () { table.ajax.reload(null, false); });
        updateFilterControls();
    });
})(typeof window !== 'undefined' ? window : globalThis);
