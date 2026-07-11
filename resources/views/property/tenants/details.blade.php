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

                                    <!-- Deposit Information Card -->
                                    <div class="col-12">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-header bg-light py-2 d-flex align-items-center gap-2">
                                                <span class="material-symbols-outlined" style="font-size:18px;">savings</span>
                                                <span class="fw-semibold small">Deposit Information</span>
                                            </div>
                                            <div class="card-body py-2">
                                                @if($tenant->deposits->isNotEmpty())
                                                <div class="table-responsive">
                                                    <table class="table table-sm mb-0" style="font-size:0.8rem;">
                                                        <thead>
                                                            <tr>
                                                                <th>Date</th>
                                                                <th>Reference</th>
                                                                <th>Narration</th>
                                                                <th class="text-end">Amount</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($tenant->deposits as $deposit)
                                                            <tr>
                                                                <td>{{ $deposit->deposit_date ? $deposit->deposit_date->format('d-m-Y') : '-' }}</td>
                                                                <td>{{ $deposit->reference_number ?? '-' }}</td>
                                                                <td>{{ $deposit->narration ?? '-' }}</td>
                                                                <td class="text-end">{{ number_format($deposit->amount, 2) }}</td>
                                                            </tr>
                                                            @endforeach
                                                        </tbody>
                                                        <tfoot>
                                                            <tr>
                                                                <th colspan="3" class="text-end">Total Deposit:</th>
                                                                <th class="text-end">{{ number_format($tenant->deposits->sum('amount'), 2) }}</th>
                                                            </tr>
                                                        </tfoot>
                                                    </table>
                                                </div>
                                                @else
                                                <p class="text-muted small mb-0">No deposit records found for this tenant.</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Payments Tab -->
                            <div class="tab-pane fade" id="payments" role="tabpanel" aria-labelledby="payments-tab">
                                <div class="mb-3">
                                    <div class="table-responsive">
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
                                    <div class="table-responsive">
                                        <table class="display" id="tenant-leases-table" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Ref</th>
                                                    <th>Property</th>
                                                    <th>Unit</th>
                                                    <th>Start</th>
                                                    <th>End</th>
                                                    <th>Contract</th>
                                                    <th>Actions</th>
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
        var paymentsTable = null;
        var leasesTable = null;
        var paymentsLoaded = false;
        var leasesLoaded = false;

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

        // Initialize Payments DataTable on tab click
        function initPaymentsTable() {
            if (paymentsLoaded) return;
            paymentsLoaded = true;

            paymentsTable = $('#tenant-payments-table').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: "{{ route('tenants.payments', ['encryptedId' => $tenant->encrypted_id]) }}",
                    dataSrc: 'data',
                    error: function(xhr) {
                        console.error('Failed loading tenant payments:', xhr.responseText || xhr.statusText);
                    }
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
                    },
                    {
                        data: 'amount',
                        className: 'text-end'
                    }
                ],
                order: [
                    [1, 'desc']
                ],
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
        }

        // Initialize Leases DataTable on tab click
        function initLeasesTable() {
            if (leasesLoaded) return;
            leasesLoaded = true;

            leasesTable = $('#tenant-leases-table').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: "{{ route('tenants.leases', ['encryptedId' => $tenant->encrypted_id]) }}",
                    dataSrc: 'data',
                    error: function(xhr) {
                        console.error('Failed loading tenant leases:', xhr.responseText || xhr.statusText);
                    }
                },
                columns: [{
                        data: null,
                        orderable: false,
                        render: function(data, type, row, meta) {
                            return meta.row + 1;
                        }
                    },
                    { data: 'ref_number' },
                    { data: 'property' },
                    { data: 'unit' },
                    {
                        data: 'date_start',
                        render: function(data) { return formatDateShort(data); }
                    },
                    {
                        data: 'date_end',
                        render: function(data) { return formatDateShort(data); }
                    },
                    {
                        data: 'contract_status',
                        orderable: false,
                        render: function(status) {
                            if (status === 'signed')  return '<span class="badge badge-light-success"><i class="fa fa-check me-1"></i>Signed</span>';
                            if (status === 'sent')    return '<span class="badge badge-light-warning"><i class="fa fa-envelope me-1"></i>Sent</span>';
                            return '<span class="badge badge-light-secondary">Not Sent</span>';
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        render: function(data, type, row) {
                            var btns = '';
                            if (row.has_signed_pdf) {
                                btns += '<a href="/property/leases/' + row.encrypted_id + '/view-signed-contract" target="_blank" class="btn btn-sm btn-outline-success me-1" title="View Signed Contract"><i class="fa fa-file-pdf-o"></i></a>';
                            }
                            if (row.contract_status !== 'signed') {
                                var title = row.contract_status === 'sent' ? 'Resend Contract' : 'Send Contract';
                                btns += '<button class="btn btn-sm btn-outline-info send-lease-contract-btn" data-ref="' + row.ref_number + '" title="' + title + '"><i class="fa fa-paper-plane"></i></button>';
                            }
                            return btns || '<span class="text-muted small">—</span>';
                        }
                    }
                ],
                order: [[4, 'desc']]
            });
        }

        // Listen for tab changes
        $('#tenantTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
            var target = $(e.target).attr('data-bs-target');

            if (target === '#payments') {
                initPaymentsTable();
            } else if (target === '#leases') {
                initLeasesTable();
            }
        });

        // Send / Resend lease contract from tenant page
        $(document).on('click', '.send-lease-contract-btn', function() {
            var ref = $(this).data('ref');
            var btn = $(this);
            if (!confirm('Send the lease contract to this tenant for digital signing?')) return;
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            $.ajax({
                url: '/property/leases/' + ref + '/send-contract',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(res) {
                    toastr.success(res.message);
                    leasesTable.ajax.reload(null, false);
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to send contract.');
                    btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i>');
                }
            });
        });
    });
</script>
@endsection