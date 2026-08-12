@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Tenants Management</h5>
                        @permission('create_clients')
                        <div class="ms-auto d-flex gap-2">
                            <a href="/property/clients/create" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Tenant</span>
                            </a>
                        </div>
                        @endpermission
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="tenants-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>ID</th>
                                        <th>Names</th>
                                        <th>Phone</th>
                                        <th>ID Number</th>
                                        <th>Reference Number</th>
                                        <th>Registered Date</th>
                                        <th>Action</th>
                                    </tr>
                                    <tr class="column-filters">
                                        <th></th>
                                        <th></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" placeholder="Filter..." /></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" placeholder="Filter..." /></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" placeholder="Filter..." /></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" placeholder="Filter..." /></th>
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {
        var table = $('#tenants-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('property.tenants.data') }}",
                type: 'GET',
                dataSrc: 'data'
            },
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'id'
                },
                {
                    data: 'names',
                    render: function(data, type, row) {
                        return `<a class="tenant-link" style="color:#3598db" href="/property/clients/${row.encrypted_id}">${data}</a>`;
                    }
                },
                {
                    data: 'mobile_number'
                },
                {
                    data: 'national_id'
                },
                {
                    data: 'reference_number'
                },
                {
                    data: 'registered_date'
                },
                {
                    data: 'encrypted_id',
                    orderable: false,
                    searchable: false,
                    render: function(encryptedId, type, row) {
                        let actions = '<div class="d-flex flex-row gap-1">';

                        actions += `
                            <a class="tenant-link" href="/property/clients/${encryptedId}" title="View">
                                <span class="material-symbols-outlined view-icon" style="font-size:18px;">visibility</span>
                            </a>
                            <div class="spinner-border spinner-border-sm text-primary row-spinner" role="status" style="display: none;">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        `;

                        if (row.can_update) {
                            actions += `
                                <a href="/property/clients/${encryptedId}/edit" title="Edit">
                                    <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                                </a>
                            `;
                        }

                        actions += '</div>';
                        return actions;
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            searching: true,
            pageLength: 25,
            ordering: true,
            orderCellsTop: true,
            order: [
                [2, 'asc']
            ]
        });

        // Per-column filtering
        $('#tenants-table thead .column-filters th').each(function(i) {
            $('input', this).on('keyup change', function() {
                var col = table.column(i);
                if (col.search() !== this.value) {
                    col.search(this.value).draw();
                }
            });
        });

        // Handle row-level spinner on tenant link click
        $(document).on('click', '.tenant-link', function(e) {
            e.preventDefault();
            var row = $(this).closest('tr');
            var href = $(this).attr('href');
            row.find('.row-spinner').show();
            window.location.href = href;
        });
    });
</script>
@endsection