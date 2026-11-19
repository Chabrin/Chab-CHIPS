@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <section class="card" aria-labelledby="clients-list-title">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <h5 id="clients-list-title">Clients</h5>
                @permission('create_clients')
                    <a href="{{ route('property.clients.create') }}" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" aria-hidden="true">add_circle</span>
                        Add Client
                    </a>
                @endpermission
            </div>
            <div class="card-body">
                <div class="mb-3" role="search" aria-label="Client directory">
                    <div class="row g-2 align-items-end">
                        <div class="col-sm-9 col-lg-6">
                            <label for="client-search" class="form-label mb-1">Search clients</label>
                            <input id="client-search" type="search" class="form-control form-control-sm"
                                   placeholder="Name, phone, ID number or client reference" autocomplete="off" aria-controls="clients-table">
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="clear-client-filters" hidden>Clear search and filters</button>
                        </div>
                    </div>
                    <details class="mt-2">
                        <summary class="text-primary fw-semibold" id="client-filter-summary">More filters</summary>
                        <div class="row g-2 mt-1">
                            <div class="col-sm-6 col-lg-3">
                                <label for="client-filter-name" class="form-label mb-1">Client name</label>
                                <input id="client-filter-name" class="form-control form-control-sm" type="search" data-client-filter="names" aria-controls="clients-table">
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label for="client-filter-reference" class="form-label mb-1">Client reference</label>
                                <input id="client-filter-reference" class="form-control form-control-sm" type="search" data-client-filter="reference_number" aria-controls="clients-table">
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label for="client-filter-phone" class="form-label mb-1">Phone</label>
                                <input id="client-filter-phone" class="form-control form-control-sm" type="search" data-client-filter="mobile_number" aria-controls="clients-table">
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label for="client-filter-id" class="form-label mb-1">ID number</label>
                                <input id="client-filter-id" class="form-control form-control-sm" type="search" data-client-filter="national_id" aria-controls="clients-table">
                            </div>
                        </div>
                    </details>
                </div>

                <div id="client-list-error" class="alert alert-light-danger" role="alert" hidden>
                    Could not load clients.
                    <button id="retry-client-list" type="button" class="btn btn-link btn-sm p-0">Try again</button>
                </div>

                <div class="table-responsive custom-scrollbar" tabindex="0" role="region" aria-label="Client records">
                    <table class="display w-100" id="clients-table">
                        <thead>
                            <tr>
                                <th scope="col">Client name</th>
                                <th scope="col">Client reference</th>
                                <th scope="col">Phone</th>
                                <th scope="col">Registered date</th>
                                <th scope="col">Actions</th>
                                <th scope="col">ID number</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(function() {
        const directory = @js(route('property.clients.index'));
        const search = document.getElementById('client-search');
        const filters = Array.from(document.querySelectorAll('[data-client-filter]'));
        const clear = document.getElementById('clear-client-filters');
        const filterSummary = document.getElementById('client-filter-summary');
        const error = $('#client-list-error');
        const text = $.fn.dataTable.render.text();

        function clientUrl(encryptedId) {
            return directory + '/' + encodeURIComponent(encryptedId);
        }

        function displayValue(value, fallback = '—') {
            const label = String(value ?? '').trim();
            return label && !/^[.\s-]+$/.test(label) ? label : fallback;
        }

        function actionIcon(url, label, iconName) {
            return $('<a>', { href: url, title: label, 'aria-label': label, class: 'link-primary p-1' })
                .append($('<span>', { class: 'material-symbols-outlined align-middle', 'aria-hidden': 'true', text: iconName }));
        }

        const table = $('#clients-table')
            .on('xhr.dt', function(event, settings, response) {
                error.prop('hidden', response !== null);
                if (response === null) return true;
            })
            .DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('property.clients.data') }}",
                columns: [
                    {
                        data: 'names',
                        name: 'names',
                        render: function(value, type, row) {
                            if (type !== 'display') return value;
                            const name = displayValue(value, 'Name missing');
                            return $('<a>', { href: clientUrl(row.encrypted_id), class: 'link-primary fw-semibold', text: name })[0];
                        }
                    },
                    { data: 'reference_number', name: 'reference_number', className: 'text-nowrap', render: value => text.display(displayValue(value)) },
                    { data: 'mobile_number', name: 'mobile_number', className: 'text-nowrap', render: value => text.display(displayValue(value)) },
                    { data: 'registered_date', name: 'registered_date', className: 'text-nowrap', render: value => text.display(displayValue(value)) },
                    {
                        data: 'encrypted_id',
                        name: 'actions',
                        orderable: false,
                        searchable: false,
                        render: function(value, type, row) {
                            if (type !== 'display') return '';
                            const url = clientUrl(value);
                            const actions = $('<div>', { class: 'd-flex gap-1' }).append(actionIcon(url, 'Open client', 'visibility'));
                            if (row.can_update) actions.append(actionIcon(url + '/edit', 'Edit client', 'edit_document'));
                            return actions[0];
                        }
                    },
                    { data: 'national_id', name: 'national_id', visible: false, orderable: false }
                ],
                layout: {
                    topStart: null, topEnd: null,
                    bottomStart: ['info', 'pageLength'], bottomEnd: 'paging'
                },
                pageLength: 25,
                order: { name: 'names', dir: 'asc' },
                language: {
                    emptyTable: 'No clients are available.',
                    zeroRecords: 'No clients match your search or filters.',
                    info: 'Showing _START_–_END_ of _TOTAL_',
                    infoEmpty: 'Showing 0 clients',
                    infoFiltered: ' (from _MAX_ total)',
                    lengthMenu: 'Rows per page _MENU_'
                }
            });

        let searchTimer;
        function applyFilters() {
            clearTimeout(searchTimer);
            table.search(search.value.trim());
            filters.forEach(input => table.column(input.dataset.clientFilter + ':name').search(input.value.trim()));
            table.draw();
        }

        function updateFilters() {
            const active = filters.filter(input => input.value.trim()).length;
            filterSummary.textContent = active ? 'More filters (' + active + ')' : 'More filters';
            clear.hidden = !search.value && !active;
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyFilters, 250);
        }

        [search, ...filters].forEach(input => input.addEventListener('input', updateFilters));
        clear.addEventListener('click', function() {
            [search, ...filters].forEach(input => { input.value = ''; });
            updateFilters();
            applyFilters();
            search.focus();
        });
        $('#retry-client-list').on('click', applyFilters);
    });
</script>
@endsection
