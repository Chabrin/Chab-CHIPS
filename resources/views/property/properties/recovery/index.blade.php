@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Recoveries Management</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('update_recoveries')
                            <a href="{{ route('property.recoveries.landlord-posting.index') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">account_balance</span>
                                <span class="ms-1">Post to Landlord Account</span>
                            </a>
                            @endpermission
                            <a href="{{ route('property.recovery.create') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Recovery</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Preloader -->
                        <div id="recoveries-preloader" class="text-center my-5">
                            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Loading recoveries...</p>
                        </div>

                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="recoveries-table" style="visibility: hidden;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Ref Number</th>
                                        <th>Recovery Date</th>
                                        <th>Amount</th>
                                        <th>Client</th>
                                        <th>Property</th>
                                        <th>Zone</th>
                                        <th>Commissionable</th>
                                        <th>Created By</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data will be loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/js/datatable/datatables/jquery.dataTables.min.js') }}"></script>
<script>
    $(document).ready(function() {
        // Initialize DataTable
        var table = $('#recoveries-table').DataTable({
            ajax: {
                url: '{{ route('ajax.property.recoveries.list') }}',
                dataSrc: 'data'
            },
            columns: [
                { 
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                { 
                    data: 'ref_number',
                    render: function(data, type, row) {
                        return `<a class="recovery-link" style="color:#3598db" href="/property/recovery/${row.encrypted_id}">${data}</a>`;
                    }
                },
                { data: 'recovery_date' },
                { 
                    data: 'amount',
                    render: function(data) {
                        return `KES ${data}`;
                    }
                },
                { data: 'client' },
                { data: 'property' },
                { data: 'zone' },
                { 
                    data: 'commisionable',
                    render: function(data) {
                        if (data === 'Yes') {
                            return '<span class="badge bg-success">Yes</span>';
                        } else {
                            return '<span class="badge bg-secondary">No</span>';
                        }
                    }
                },
                { data: 'created_by' },
                {
                    data: null,
                    orderable: false,
                    render: function(data, type, row) {
                        return `
                            <div class="d-flex flex-row gap-1">
                                <a class="recovery-link" href="/property/recovery/${row.encrypted_id}" title="View">
                                    <span class="material-symbols-outlined view-icon" style="font-size:18px;">visibility</span>
                                </a>
                                <div class="spinner-border spinner-border-sm text-primary row-spinner" role="status" style="display: none;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <a href="/property/recovery/${row.encrypted_id}/edit" title="Edit">
                                    <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                                </a>
                            </div>
                        `;
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            searching: true,
            ordering: true,
            order: [[2, 'desc']], // Sort by recovery date descending
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            initComplete: function() {
                // Hide preloader and show table
                $('#recoveries-preloader').hide();
                $('#recoveries-table').css('visibility', 'visible');
            }
        });

        // Handle row-level spinner on recovery link click
        $(document).on('click', '.recovery-link', function(e) {
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