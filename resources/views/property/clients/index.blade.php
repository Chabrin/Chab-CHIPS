@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/client-list.css') }}?v={{ filemtime(public_path('assets/css/client-list.css')) }}">
@endsection

@section('content')
<div class="page-body" id="clients-directory">
    <div class="container-fluid">
        <section class="card client-list-card" aria-labelledby="clients-list-title">
            <header class="client-list-header">
                <div>
                    <p class="client-list-eyebrow">Management</p>
                    <h1 id="clients-list-title">Clients</h1>
                </div>
                @permission('create_clients')
                    <a href="{{ route('property.clients.create') }}" class="btn btn-primary client-list-add">
                        <i class="fa fa-plus-circle" aria-hidden="true"></i>
                        Add Client
                    </a>
                @endpermission
            </header>
            <div class="client-list-body">
                <div class="client-list-search-area" role="search" aria-label="Client directory">
                    <div class="client-list-toolbar">
                        <div class="client-list-search-field">
                            <label for="client-search">Search clients</label>
                            <div class="client-list-search-control">
                                <i class="fa fa-search" aria-hidden="true"></i>
                                <input id="client-search" type="search" class="form-control"
                                       placeholder="Name, phone, ID number or client reference" autocomplete="off" aria-controls="clients-table">
                            </div>
                        </div>
                        <div class="client-list-toolbar-actions">
                            <button type="button" class="btn btn-sm client-list-filter-toggle"
                                    data-bs-toggle="collapse" data-bs-target="#client-list-filter-panel"
                                    aria-expanded="false" aria-controls="client-list-filter-panel">
                                <i class="fa fa-sliders" aria-hidden="true"></i>
                                <span id="client-filter-summary">More filters</span>
                            </button>
                            <button type="button" class="btn btn-sm client-list-clear" id="clear-client-filters" hidden>Clear search and filters</button>
                        </div>
                    </div>
                    <div class="collapse" id="client-list-filter-panel">
                        <div class="client-list-filter-grid">
                            <div>
                                <label for="client-filter-name">Client name</label>
                                <input id="client-filter-name" class="form-control" type="search" data-client-filter="names" aria-controls="clients-table">
                            </div>
                            <div>
                                <label for="client-filter-reference">Client reference</label>
                                <input id="client-filter-reference" class="form-control" type="search" data-client-filter="reference_number" aria-controls="clients-table">
                            </div>
                            <div>
                                <label for="client-filter-phone">Phone</label>
                                <input id="client-filter-phone" class="form-control" type="search" data-client-filter="mobile_number" aria-controls="clients-table">
                            </div>
                            <div>
                                <label for="client-filter-id">ID number</label>
                                <input id="client-filter-id" class="form-control" type="search" data-client-filter="national_id" aria-controls="clients-table">
                            </div>
                        </div>
                    </div>
                </div>

                <div id="client-list-error" class="alert alert-light-danger" role="alert" hidden>
                    Could not load clients.
                    <button id="retry-client-list" type="button" class="btn btn-sm client-list-clear">Try again</button>
                </div>

                <div class="table-responsive custom-scrollbar" tabindex="0" role="region" aria-label="Client records">
                    <table class="w-100" id="clients-table">
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
            return $('<a>', { href: url, title: label, 'aria-label': label, class: 'client-list-icon-action' })
                .append($('<i>', { class: 'fa fa-' + iconName, 'aria-hidden': 'true' }));
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
                            const name = displayValue(value, '');
                            return $('<a>', {
                                href: clientUrl(row.encrypted_id),
                                class: name ? 'client-list-name' : 'client-list-name client-list-name-missing',
                                text: name || 'Name missing'
                            })[0];
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
                            const actions = $('<div>', { class: 'client-list-row-actions' }).append(actionIcon(url, 'Open client', 'eye'));
                            if (row.can_update) actions.append(actionIcon(url + '/edit', 'Edit client', 'pencil'));
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
