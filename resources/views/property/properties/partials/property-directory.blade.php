<div class="page-body" id="property-listing">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <section class="card property-directory" aria-labelledby="property-directory-title">
                    <header class="property-directory-header">
                        <div>
                            <p class="property-directory-eyebrow">Management</p>
                            <h1 id="property-directory-title">{{ $inactive ? 'Inactive Properties' : 'Properties' }}</h1>
                        </div>
                        <div class="property-directory-actions">
                            @if($inactive)
                                <a href="{{ route('property.properties.index') }}" class="btn property-secondary-button">Active Properties</a>
                            @else
                                @permission('create_properties')
                                <button type="button" class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addPropertyModal">
                                    <span class="material-symbols-outlined" aria-hidden="true">add_circle</span>
                                    <span class="ms-1">Add Property</span>
                                </button>
                                @endpermission
                            @endif
                            @permission('update_properties')
                            <a href="{{ route('property.activation.index') }}" class="btn property-secondary-button d-inline-flex align-items-center gap-2">
                                <span class="material-symbols-outlined" aria-hidden="true">toggle_on</span>Manage Activation
                            </a>
                            @endpermission
                        </div>
                    </header>
                    <div class="property-directory-body">
                        @if(session('success'))
                        <div class="alert alert-light-success alert-dismissible fade show" role="alert">
                            <strong>Success!</strong> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif
                        @if(session('error'))
                        <div class="alert alert-light-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        <div class="property-directory-filters property-search-controls" role="search" aria-label="Search properties">
                            <div class="property-search-field">
                                <label for="property-search">Search properties</label>
                                <div class="property-search-input">
                                    <input id="property-search" class="form-control" type="search" placeholder="Property name, reference, client or zone" autocomplete="off" aria-controls="propertiesTable">
                                    <button id="property-search-clear" class="btn property-search-clear" type="button" aria-label="Clear search" title="Clear search" hidden>
                                        <span class="material-symbols-outlined" aria-hidden="true">close</span>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label for="property-zone">Zone</label>
                                <select id="property-zone" class="form-select" aria-controls="propertiesTable"><option value="">All zones</option></select>
                            </div>
                            <div>
                                <label for="property-client" id="property-client-label">Client</label>
                                <select id="property-client" class="form-select" aria-controls="propertiesTable"><option value=""></option></select>
                            </div>
                        </div>
                        <div id="property-active-filters" class="property-active-filters" role="group" aria-label="Active property filters" hidden>
                            <button class="btn property-filter-chip" type="button" data-remove-filter="zone" hidden>
                                <span class="property-filter-name"></span>
                                <span class="material-symbols-outlined" aria-hidden="true">close</span>
                            </button>
                            <button class="btn property-filter-chip" type="button" data-remove-filter="client" hidden>
                                <span class="property-filter-name"></span>
                                <span class="material-symbols-outlined" aria-hidden="true">close</span>
                            </button>
                            <button id="property-filters-clear" class="btn property-filters-clear" type="button" hidden>Clear filters</button>
                        </div>
                        <div class="table-responsive custom-scrollbar">
                            <table id="propertiesTable" class="display" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th class="property-name-column">Property</th>
                                        <th>Zone</th>
                                        <th>Client</th>
                                        <th class="property-action-column">Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        const escapeText = $.fn.dataTable.render.text().display;
        const search = document.getElementById('property-search');
        const clearSearch = document.getElementById('property-search-clear');
        const activeFilters = document.getElementById('property-active-filters');
        const clearFilters = document.getElementById('property-filters-clear');
        const zone = document.getElementById('property-zone');
        const client = document.getElementById('property-client');
        const filters = [
            { select: zone, label: 'Zone', name: 'zone_name', value: row => row.zone_name, chip: activeFilters.querySelector('[data-remove-filter="zone"]') },
            { select: client, label: 'Client', name: 'client_name', value: row => String(row.client_id ?? '-'), chip: activeFilters.querySelector('[data-remove-filter="client"]') }
        ];
        let searchTerms = [];

        function normalize(value) {
            return String(value ?? '').normalize('NFD').replace(/\p{M}/gu, '')
                .toLowerCase().replace(/[^\p{L}\p{N}]+/gu, ' ').trim();
        }

        function words(value) {
            return normalize(value).split(' ').filter(Boolean);
        }

        function renderActiveFilters() {
            let count = 0;
            filters.forEach(({ select, label, chip }) => {
                chip.hidden = !select.value;
                if (chip.hidden) return;

                const name = select.selectedOptions[0].text;
                chip.querySelector('.property-filter-name').textContent = `${label}: ${name}`;
                chip.setAttribute('aria-label', `Remove ${label.toLowerCase()} filter: ${name}`);
                count++;
            });
            activeFilters.hidden = count === 0;
            clearFilters.hidden = count < 2;
        }

        const $client = $(client).select2({
            placeholder: 'All clients',
            width: '100%',
            dropdownParent: $(client.parentElement),
            matcher: function(params, option) {
                const name = normalize(option.text);
                return words(params.term).every(term => name.includes(term)) ? option : null;
            }
        });
        const clientControl = client.nextElementSibling.querySelector('[role="combobox"]');
        clientControl.setAttribute('aria-labelledby', `property-client-label ${clientControl.getAttribute('aria-labelledby')}`);

        const table = $('#propertiesTable').DataTable({
            processing: true,
            ajax: {
                url: "{{ route($inactive ? 'property.properties.inactive.data' : 'property.properties.data') }}",
                dataSrc: function(response) {
                    filters.forEach(({ select, name, value }) => {
                        const selected = select.value;
                        const options = new Map(response.data.map(row => [value(row), row[name]]));
                        select.options.length = 1;
                        [...options].sort(([, a], [, b]) => a.localeCompare(b))
                            .forEach(([id, text]) => select.add(new Option(text, id)));
                        select.value = options.has(selected) ? selected : '';
                    });
                    $client.trigger('change.select2');
                    renderActiveFilters();
                    return response.data;
                }
            },
            columns: [
                {
                    data: 'property_name',
                    render: function(name, type, row) {
                        if (type === 'filter') return name + ' ' + row.reference_number;
                        if (type !== 'display') return name;

                        return '<a href="/property/listings/' + encodeURIComponent(row.encrypted_id) + '">' +
                            escapeText(name) + '</a><span class="property-reference">' +
                            escapeText(row.reference_number) + '</span>';
                    }
                },
                {
                    data: 'zone_name',
                    render: $.fn.dataTable.render.text()
                },
                {
                    data: 'client_name',
                    render: function(name, type, row) {
                        if (type !== 'display') return name;
                        if (row.client_encrypted_id && row.can_read_client) {
                            return '<a href="/property/clients/' + encodeURIComponent(row.client_encrypted_id) +
                                '">' + escapeText(name) + '</a>';
                        }
                        return escapeText(name);
                    }
                },
                {
                    data: 'encrypted_id',
                    orderable: false,
                    searchable: false,
                    render: function(encryptedId, type, row) {
                        if (type !== 'display') return encryptedId;
                        const id = encodeURIComponent(encryptedId);
                        const actions = [];
                        if (row.can_read) {
                            actions.push('<a class="directory-icon-action" href="/property/listings/' + id +
                                '" title="View property" aria-label="View property">' +
                                '<span class="material-symbols-outlined" aria-hidden="true">visibility</span></a>');
                        }
                        @unless($inactive)
                        if (row.can_update) {
                            actions.push('<a class="directory-icon-action" href="/property/properties/' + id +
                                '/edit" title="Edit property" aria-label="Edit property">' +
                                '<span class="material-symbols-outlined" aria-hidden="true">edit_square</span></a>');
                        }
                        @endunless
                        return actions.length ? '<div class="directory-row-actions">' + actions.join('') + '</div>' : '—';
                    }
                }
            ],
            pageLength: 10,
            layout: {
                topStart: null,
                topEnd: null,
                bottomStart: 'info',
                bottomEnd: ['pageLength', 'paging']
            },
            language: {
                lengthMenu: 'Rows per page _MENU_',
                info: '_START_–_END_ of _TOTAL_ properties',
                infoEmpty: '0 properties',
                emptyTable: @json($inactive ? 'No inactive properties are available.' : 'No properties are available.'),
                zeroRecords: 'No matching properties. Try a different search or remove a filter.'
            },
            order: [[0, 'asc']]
        });

        table.search.fixed('property-directory', function(_, row) {
            if (filters.some(({ select, value }) => select.value && select.value !== value(row))) return false;
            if (!searchTerms.length) return true;

            const searchable = normalize([row.property_name, row.reference_number, row.zone_name, row.client_name].join(' '));
            const compactReference = normalize(row.reference_number).replaceAll(' ', '');
            return searchTerms.every(term => searchable.includes(term) || compactReference.includes(term));
        });

        function updateSearch() {
            searchTerms = words(search.value);
            clearSearch.hidden = !search.value;
            table.draw();
        }

        search.addEventListener('input', updateSearch);
        clearSearch.addEventListener('click', function() {
            search.value = '';
            updateSearch();
            search.focus();
        });

        function updateFilters() {
            renderActiveFilters();
            table.draw();
        }

        filters.forEach(({ select, chip }) => {
            $(select).on('change', updateFilters);
            chip.addEventListener('click', function() {
                $(select).val('').trigger('change');
                (select === client ? clientControl : select).focus();
            });
        });

        clearFilters.addEventListener('click', function() {
            zone.value = '';
            $client.val('').trigger('change');
            search.focus();
        });

        @unless($inactive)
        $(document).on('propertyCreated', function(e, res) {
            if (res.redirect_url) {
                window.location.href = res.redirect_url;
            } else {
                table.ajax.reload();
            }
        });
        @endunless
    });
</script>
