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

                        <div class="property-directory-filters" role="group" aria-label="Filter properties">
                            <label for="property-search">Search properties
                                <input id="property-search" class="form-control" type="search" placeholder="Name, reference, zone or client" aria-controls="propertiesTable">
                            </label>
                            <label for="property-zone">Zone
                                <select id="property-zone" class="form-select" aria-controls="propertiesTable"><option value="">All zones</option></select>
                            </label>
                            <label for="property-client">Client
                                <select id="property-client" class="form-select" aria-controls="propertiesTable"><option value="">All clients</option></select>
                            </label>
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
        const filters = [
            { select: document.getElementById('property-zone'), field: 'zone_name', column: 1 },
            { select: document.getElementById('property-client'), field: 'client_name', column: 2 }
        ];

        const table = $('#propertiesTable').DataTable({
            processing: true,
            ajax: {
                url: "{{ route($inactive ? 'property.properties.inactive.data' : 'property.properties.data') }}",
                dataSrc: function(response) {
                    filters.forEach(({ select, field }) => {
                        const selected = select.value;
                        const values = [...new Set(response.data.map(row => row[field]).filter(Boolean))]
                            .sort((a, b) => a.localeCompare(b));
                        select.options.length = 1;
                        values.forEach(value => select.add(new Option(value, value)));
                        if (selected && !values.includes(selected)) {
                            select.add(new Option(selected, selected));
                        }
                        select.value = selected;
                    });
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
                zeroRecords: 'No properties match your search and filters.'
            },
            order: [[0, 'asc']]
        });

        $('#property-search').on('input', function() {
            table.search(this.value).draw();
        });

        filters.forEach(({ select, field, column }) => {
            select.addEventListener('change', function() {
                const selected = this.value;
                table.column(column).search(selected ? (_, row) => row[field] === selected : '').draw();
            });
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
