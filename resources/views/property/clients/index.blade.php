@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Clients Management</h5>
                        @permission('create_clients')
                        <div class="ms-auto d-flex gap-2">
                            <a href="/property/clients/create" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Client</span>
                            </a>
                        </div>
                        @endpermission
                    </div>
                    <div class="card-body">
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

                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="clients-table" style="width:100%;">
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
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        var table = $('#clients-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ route('property.clients.data') }}",
                type: 'GET',
                dataSrc: 'clients'
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
                        return `<a class="client-link" style="color:#3598db" href="/property/clients/${row.encrypted_id}">${data}</a>`;
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
                            <a class="client-link" href="/property/clients/${encryptedId}" title="View">
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
            ordering: true,
            order: [
                [2, 'asc']
            ] // Sort by names
        });

        // Handle row-level spinner on client link click
        $(document).on('click', '.client-link', function(e) {
            e.preventDefault();
            var row = $(this).closest('tr');
            var url = $(this).attr('href');

            // Hide view icon and show spinner
            row.find('.view-icon').hide();
            row.find('.row-spinner').show();

            // Disable all links in the row
            row.find('a').css('pointer-events', 'none').css('opacity', '0.6');

            // Navigate to the details page
            window.location.href = url;
        });
    });
</script>
@endsection