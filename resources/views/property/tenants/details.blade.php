@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Tenant - {{ $tenant->names }}</h5>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ url('/tenants/listing') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to Search</span>
                            </a>
                            <a href="{{ url('/property/tenants/' . Crypt::encrypt($tenant->id) . '/edit') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">edit_document</span>
                                <span class="ms-1">Edit Tenant Details</span>
                            </a>
                        </div>
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

                        <!-- Summary Header -->
                        <div class="mb-3">
                            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                                <strong>Ref #:</strong> {{ $tenant->reference_number ?? 'N/A' }} |
                                <strong>PIN:</strong> {{ $tenant->national_id ?? 'N/A' }} |
                                <strong>Mobile:</strong> {{ $tenant->mobile_number ?? 'N/A' }}
                            </p>
                        </div>

                        <!-- Tabs Navigation -->
                        <ul class="nav nav-tabs mb-4" id="tenantTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">person</span>
                                    Profile
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="statistics-tab" data-bs-toggle="tab" data-bs-target="#statistics" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">analytics</span>
                                    Statistics
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">payments</span>
                                    Payments
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="leases-tab" data-bs-toggle="tab" data-bs-target="#leases" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">assignment</span>
                                    Leases
                                </button>
                            </li>
                        </ul>

                        <!-- Tabs Content -->
                        <div class="tab-content" id="tenantTabsContent">
                            <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                                <div class="row g-3">
                                    <!-- Personal Information -->
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-light py-2">
                                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">person</span>
                                                    Personal Information
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-1">
                                                    <div class="col-5"><small class="text-muted">Full Names:</small></div>
                                                    <div class="col-7"><small><strong>{{ $tenant->names ?? '-' }}</strong></small></div>

                                                    <div class="col-5"><small class="text-muted">Mobile Number:</small></div>
                                                    <div class="col-7"><small><strong>{{ $tenant->mobile_number ?? '-' }}</strong></small></div>

                                                    <div class="col-5"><small class="text-muted">PIN Number:</small></div>
                                                    <div class="col-7"><small><strong>{{ $tenant->national_id ?? '-' }}</strong></small></div>

                                                    <div class="col-5"><small class="text-muted">Reference Number:</small></div>
                                                    <div class="col-7"><small><strong>{{ $tenant->reference_number ?? '-' }}</strong></small></div>

                                                    <div class="col-5"><small class="text-muted">Email Address:</small></div>
                                                    <div class="col-7"><small>{{ $tenant->email_address ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Physical Address:</small></div>
                                                    <div class="col-7"><small>{{ $tenant->address ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Registered Date:</small></div>
                                                    <div class="col-7"><small>{{ $tenant->registered_date ? $tenant->registered_date->format('d-m-Y') : '-' }}</small></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Property & Unit Information -->
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-light py-2">
                                                <h6 class="mb-0" style="font-size: 0.9rem;">
                                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">home</span>
                                                    Property & Unit Details
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-1">
                                                    <div class="col-5"><small class="text-muted">Property:</small></div>
                                                    <div class="col-7"><small><strong>{{ $tenant->property->property_name ?? '-' }}</strong></small></div>

                                                    <div class="col-5"><small class="text-muted">Unit Name:</small></div>
                                                    <div class="col-7"><small><strong>{{ $tenant->unit->unit_name ?? '-' }}</strong></small></div>

                                                    <div class="col-5"><small class="text-muted">Unit Number:</small></div>
                                                    <div class="col-7"><small><strong>{{ $tenant->unit->unit_number ?? '-' }}</strong></small></div>

                                                    <div class="col-5"><small class="text-muted">Rent Amount:</small></div>
                                                    <div class="col-7"><small><strong>{{ $tenant->unit && $tenant->unit->rent_amount ? number_format($tenant->unit->rent_amount, 2) : '0.00' }}</strong></small></div>

                                                    <div class="col-5"><small class="text-muted">Zone:</small></div>
                                                    <div class="col-7"><small>{{ $tenant->unit->zone->zone_name ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Account Name:</small></div>
                                                    <div class="col-7"><small>{{ $tenant->account_name ?? '-' }}</small></div>

                                                    <div class="col-5"><small class="text-muted">Account Number:</small></div>
                                                    <div class="col-7"><small>{{ $tenant->account_number ?? '-' }}</small></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Account Balance Card -->
                                    @if($tenantBalance)
                                    <div class="col-12">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-header bg-light py-2 d-flex align-items-center gap-2">
                                                <span class="material-symbols-outlined" style="font-size:18px;">account_balance_wallet</span>
                                                <span class="fw-semibold small">Tenant Account Balance</span>
                                                <span class="ms-auto">
                                                    @if($tenantBalance['is_in_arrears'])
                                                        <span class="badge bg-danger">In Arrears</span>
                                                    @else
                                                        <span class="badge bg-success">Credit</span>
                                                    @endif
                                                </span>
                                            </div>
                                            <div class="card-body py-3">
                                                <div class="row text-center">
                                                    <div class="col-4 border-end">
                                                        <small class="text-muted d-block mb-1">Total Invoiced</small>
                                                        <span class="fw-bold">{{ number_format($tenantBalance['total_invoiced'], 2) }}</span>
                                                    </div>
                                                    <div class="col-4 border-end">
                                                        <small class="text-muted d-block mb-1">Total Paid</small>
                                                        <span class="fw-bold text-success">{{ number_format($tenantBalance['total_paid'], 2) }}</span>
                                                    </div>
                                                    <div class="col-4">
                                                        <small class="text-muted d-block mb-1">Balance</small>
                                                        <span class="fw-bold {{ $tenantBalance['is_in_arrears'] ? 'text-danger' : 'text-success' }}">
                                                            @if($tenantBalance['is_in_arrears'])({{ $tenantBalance['balance_formatted'] }})@else{{ $tenantBalance['balance_formatted'] }}@endif
                                                        </span>
                                                    </div>
                                                </div>
                                                <p class="text-muted small mb-0 mt-2 text-center">As of: {{ $tenantBalance['as_of_date'] }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Payments Tab -->
                            <div class="tab-pane fade" id="payments" role="tabpanel" aria-labelledby="payments-tab">
                                <div class="mb-3">
                                    <div id="payments-preloader" class="text-center my-4">
                                        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2 text-muted">Loading payments...</p>
                                    </div>

                                    <div class="table-responsive" style="visibility: hidden;" id="payments-table-wrapper">
                                        <table class="display" id="tenant-payments-table" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Payment Date</th>
                                                    <th>Reference</th>
                                                    <th>Narration</th>
                                                    <th>Tenant</th>
                                                    <th>Property</th>
                                                    <th>Unit</th>
                                                    <th class="text-end">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                            <tfoot>
                                                <tr>
                                                    <th colspan="7" style="text-align: right;">Total:</th>
                                                    <th class="text-end" id="payments-total">0.00</th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Leases Tab -->
                            <div class="tab-pane fade" id="leases" role="tabpanel" aria-labelledby="leases-tab">
                                <div class="mb-3">
                                    <div id="leases-preloader" class="text-center my-4">
                                        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2 text-muted">Loading leases...</p>
                                    </div>

                                    <div class="table-responsive" style="visibility: hidden;" id="leases-table-wrapper">
                                        <table class="display" id="tenant-leases-table" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Ref</th>
                                                    <th>Property</th>
                                                    <th>Unit</th>
                                                    <th>Start</th>
                                                    <th>End</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Statistics Tab -->
                            <div class="tab-pane fade" id="statistics" role="tabpanel" aria-labelledby="statistics-tab">
                                <div class="alert alert-info">
                                    <span class="material-symbols-outlined" style="vertical-align: middle;">info</span>
                                    Statistics will be implemented in the next phase
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .card-header {
        border-bottom: 1px solid #e0e0e0;
    }

    .card-body .row.g-1>div {
        padding-top: 0.25rem;
        padding-bottom: 0.25rem;
        line-height: 1.5;
    }
</style>
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(function() {
        var paymentsTable = $('#tenant-payments-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: '/tenants/{{ $tenant->encrypted_id }}/payments',
                dataSrc: 'data'
            },
            columns: [{
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'payment_date'
                },
                {
                    data: 'reference'
                },
                {
                    data: 'narration'
                },
                {
                    data: 'tenant'
                },
                {
                    data: 'property'
                },
                {
                    data: 'unit'
                }, {
                    data: 'amount',
                    className: 'text-end'
                }
            ],
            order: [
                [1, 'desc']
            ],
            initComplete: function() {
                $('#payments-preloader').hide();
                $('#payments-table-wrapper').css('visibility', 'visible');
            },
            footerCallback: function(row, data, start, end, display) {
                var api = this.api();

                // helper to parse formatted numbers (with commas)
                var parseValue = function(val) {
                    if (!val) return 0;
                    // remove any non-numeric characters except dot and minus
                    var v = String(val).replace(/[^0-9.\-]/g, '');
                    return parseFloat(v) || 0;
                };

                // column index for amount is last column
                var amountIdx = api.columns().count() - 1;

                // Total over this page
                var pageTotal = api.column(amountIdx, {
                    page: 'current'
                }).data().reduce(function(a, b) {
                    return parseValue(a) + parseValue(b);
                }, 0);

                // Update footer
                $(api.column(amountIdx).footer()).html(pageTotal.toFixed(2));
                $('#payments-total').text(pageTotal.toFixed(2));
            }
        });

        // Helper: format date to dd/mm/yy
        function formatDateShort(dateStr) {
            if (!dateStr) return '';
            var d = new Date(dateStr);
            if (isNaN(d.getTime())) {
                var parts = dateStr.split(' ');
                d = new Date(parts[0]);
                if (isNaN(d.getTime())) return dateStr;
            }
            var dd = ('0' + d.getDate()).slice(-2);
            var mm = ('0' + (d.getMonth() + 1)).slice(-2);
            var yy = String(d.getFullYear()).slice(-2);
            return dd + '/' + mm + '/' + yy;
        }

        // Leases DataTable
        var leasesTable = $('#tenant-leases-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: '/tenants/{{ $tenant->encrypted_id }}/leases',
                dataSrc: 'data'
            },
            columns: [{
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'ref_number'
                },
                {
                    data: 'property'
                },
                {
                    data: 'unit'
                },
                {
                    data: 'date_start',
                    render: function(data) {
                        return formatDateShort(data);
                    }
                },
                {
                    data: 'date_end',
                    render: function(data) {
                        return formatDateShort(data);
                    }
                }
            ],
            order: [
                [4, 'desc']
            ],
            initComplete: function() {
                $('#leases-preloader').hide();
                $('#leases-table-wrapper').css('visibility', 'visible');
            }
        });
    });
</script>
@endsection