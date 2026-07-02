@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h5 class="mb-0">Tenant Management</h5>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <small class="text-muted" id="result_info" style="display:none;">
                                Showing <strong id="result_count">0</strong> tenants (max 1000)
                            </small>
                            <div class="btn-group btn-group-sm" role="group" id="status-filter">
                                <button type="button" class="btn btn-outline-secondary active" data-status="active">Active</button>
                                <button type="button" class="btn btn-outline-secondary" data-status="exited">Exited</button>
                                <button type="button" class="btn btn-outline-secondary" data-status="all">All</button>
                            </div>
                            <a href="{{ route('units.tenants.create') }}" class="btn btn-sm btn-outline-secondary">
                                <span class="material-symbols-outlined">add_circle</span> Onboard tenant
                            </a>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="tenants-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Names</th>
                                        <th>Phone</th>
                                        <th>ID Number</th>
                                        <th>Property</th>
                                        <th>Landlord</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                    <tr class="column-filters">
                                        <th></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-filter="filter_names" placeholder="-- Search --" /></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-filter="filter_phone" placeholder="-- Search --" /></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-filter="filter_id_number" placeholder="-- Search --" /></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-filter="filter_property" placeholder="-- Search --" /></th>
                                        <th><input type="text" class="form-control form-control-sm col-filter" data-filter="filter_landlord" placeholder="-- Search --" /></th>
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
@endsection

@section('scripts')
<style>
    .col-filter-wrap {
        position: relative;
    }

    .col-filter-wrap .col-filter {
        padding-right: 22px;
    }

    .col-filter-wrap .filter-spinner {
        position: absolute;
        right: 6px;
        top: 50%;
        margin-top: -7px;
        width: 14px;
        height: 14px;
        border: 2px solid #ccc;
        border-top-color: var(--accent, #3598db);
        border-radius: 50%;
        animation: filterspin .6s linear infinite;
        display: none;
    }

    .col-filter-wrap.loading .filter-spinner {
        display: block;
    }

    @keyframes filterspin {
        to { transform: rotate(360deg); }
    }

    tr.tenant-exited td {
        color: #9ca3af !important;
    }

    tr.tenant-exited td a .material-symbols-outlined {
        color: #9ca3af !important;
    }
</style>
<script>
    $(document).ready(function () {
        var currentStatus = 'active';

        var table = $('#tenants-table').DataTable({
            data: [],
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (d, t, r, m) { return m.row + 1; }
                },
                { data: 'names' },
                { data: 'mobile_number' },
                { data: 'national_id' },
                { data: 'property_name' },
                { data: 'landlord_name' },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (d, t, r) {
                        if (r.is_exited) {
                            var label = r.exit_date ? 'Exited · ' + r.exit_date : 'Exited';
                            var title = r.exit_reason ? ' title="' + $('<div>').text(r.exit_reason).html() + '"' : '';
                            return '<span class="badge bg-danger-light text-danger fw-normal"' + title + '>' + label + '</span>';
                        }
                        return '<span class="badge bg-success-light text-success fw-normal">Active</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (d, t, r) {
                        return '<a href="/tenants/' + r.encrypted_id + '" title="View Details">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;color:var(--accent);">folder_eye</span></a>';
                    }
                }
            ],
            createdRow: function (row, data) {
                if (data.is_exited) {
                    $(row).addClass('tenant-exited');
                }
            },
            paging: true,
            info: true,
            lengthChange: true,
            searching: false,
            ordering: true,
            orderCellsTop: true,
            order: [[1, 'asc']],
            pageLength: 50
        });

        // Wrap each filter input with a spinner element
        $('#tenants-table thead .column-filters input.col-filter').each(function () {
            $(this).wrap('<div class="col-filter-wrap"></div>');
            $(this).after('<span class="filter-spinner"></span>');
        });

        var debounceTimer;

        function loadData() {
            var $activeFilters = $('#tenants-table thead .column-filters .col-filter-wrap').filter(function () {
                return $(this).find('input').val().trim().length >= 3;
            });
            $activeFilters.addClass('loading');

            var params = { status: currentStatus };
            $('#tenants-table thead .column-filters input.col-filter').each(function () {
                var key = $(this).data('filter');
                var val = $(this).val().trim();
                if (val.length >= 3) params[key] = val;
            });

            $.ajax({
                url: '{{ route("ajax.tenants.listing.data") }}',
                type: 'GET',
                data: params,
                success: function (response) {
                    $activeFilters.removeClass('loading');
                    if (response.success) {
                        $('#result_info').show();
                        $('#result_count').text(response.count);
                        table.clear();
                        table.rows.add(response.data);
                        table.draw();
                    }
                },
                error: function (xhr) {
                    $activeFilters.removeClass('loading');
                    var msg = 'Failed to load tenants.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    toastr.error(msg);
                }
            });
        }

        // Status filter tabs
        $('#status-filter button').on('click', function () {
            $('#status-filter button').removeClass('active');
            $(this).addClass('active');
            currentStatus = $(this).data('status');
            loadData();
        });

        // Column filters with debounce (3+ chars)
        $('#tenants-table thead .column-filters input.col-filter').on('keyup', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(loadData, 400);
        });

        // Initial load
        loadData();
    });
</script>
@endsection
