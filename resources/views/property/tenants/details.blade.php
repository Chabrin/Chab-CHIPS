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
                        <!-- Summary Header -->
                        <div class="mb-3">
                            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                                <strong>Ref #:</strong> {{ $tenant->reference_number ?? 'N/A' }} |
                                <strong>PIN:</strong> {{ $tenant->pin_number ?? 'N/A' }} |
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
                                <button class="nav-link" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts" type="button" role="tab">
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">contacts</span>
                                    Contacts
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
                                <style>
                                    .info-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #e9ecef; }
                                    .info-row:last-child { border-bottom: none; }
                                    .info-label { color: #6c757d; font-size: 0.875rem; }
                                    .info-value { font-weight: 500; display: inline-flex; align-items: center; gap: 6px; }
                                    .section-divider { margin: 20px 0 15px 0; padding-top: 10px; }
                                    .section-title { font-weight: 600; font-size: 1rem; color: #333; margin-bottom: 10px; }
                                    .copy-icon { cursor: pointer; color: #5aa9e0; font-size: 16px; line-height: 1; transition: color 0.15s ease; }
                                    .copy-icon:hover { color: #3598db; }
                                    .copy-icon.copied { color: #3598db; }
                                </style>

                                <div class="row">
                                    <!-- Personal & Property Info - Left Column -->
                                    <div class="col-lg-6">
                                        <div class="section-title"><span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">person</span> {{ $tenant->client_type_id == 3 ? 'Company Information' : 'Personal Information' }}</div>
                                        <div class="info-row">
                                            <span class="info-label">{{ $tenant->client_type_id == 3 ? 'Company Name:' : 'Full Names:' }}</span>
                                            <span class="info-value">{{ $tenant->names ?? '-' }}</span>
                                        </div>
                                        @if($tenant->client_type_id != 3)
                                        <div class="info-row">
                                            <span class="info-label">Gender:</span>
                                            <span class="info-value">{{ $tenant->gender ?? '-' }}</span>
                                        </div>
                                        @endif
                                        <div class="info-row">
                                            <span class="info-label">Mobile Number:</span>
                                            <span class="info-value">
                                                {{ $tenant->mobile_number ?? '-' }}
                                                @if($tenant->mobile_number)
                                                <span class="material-symbols-outlined copy-icon" data-copy="{{ $tenant->mobile_number }}" title="Copy">content_copy</span>
                                                @endif
                                            </span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Reference Number:</span>
                                            <span class="info-value">
                                                {{ $tenant->reference_number ?? '-' }}
                                                @if($tenant->reference_number)
                                                <span class="material-symbols-outlined copy-icon" data-copy="{{ $tenant->reference_number }}" title="Copy">content_copy</span>
                                                @endif
                                            </span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">PIN Number:</span>
                                            <span class="info-value">
                                                {{ $tenant->pin_number ?? '-' }}
                                                @if($tenant->pin_number)
                                                <span class="material-symbols-outlined copy-icon" data-copy="{{ $tenant->pin_number }}" title="Copy">content_copy</span>
                                                @endif
                                            </span>
                                        </div>
                                        @if($tenant->client_type_id == 3)
                                        <div class="info-row">
                                            <span class="info-label">Registration Number:</span>
                                            <span class="info-value">
                                                {{ $tenant->company_registration_number ?? '-' }}
                                                @if($tenant->company_registration_number)
                                                <span class="material-symbols-outlined copy-icon" data-copy="{{ $tenant->company_registration_number }}" title="Copy">content_copy</span>
                                                @endif
                                            </span>
                                        </div>
                                        @else
                                        <div class="info-row">
                                            <span class="info-label">National ID:</span>
                                            <span class="info-value">
                                                {{ $tenant->national_id ?? '-' }}
                                                @if($tenant->national_id)
                                                <span class="material-symbols-outlined copy-icon" data-copy="{{ $tenant->national_id }}" title="Copy">content_copy</span>
                                                @endif
                                            </span>
                                        </div>
                                        @endif

                                        @if($tenant->client_type_id == 3)
                                        <div class="section-divider"></div>
                                        <div class="section-title"><span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">badge</span> Contact Person</div>
                                        <div class="info-row">
                                            <span class="info-label">Name:</span>
                                            <span class="info-value">{{ $tenant->contact_person_name ?? '-' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Title:</span>
                                            <span class="info-value">{{ $tenant->contact_person_title ?? '-' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Mobile:</span>
                                            <span class="info-value">{{ $tenant->contact_person_mobile ?? '-' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Email:</span>
                                            <span class="info-value">{{ $tenant->contact_person_email ?? '-' }}</span>
                                        </div>
                                        @endif

                                        <div class="section-divider"></div>
                                        <div class="section-title"><span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">home</span> Property & Unit</div>
                                        <div class="info-row">
                                            <span class="info-label">Property:</span>
                                            <span class="info-value">
                                                {{ $tenant->property->property_name ?? '-' }}
                                                @if($tenant->property && $tenant->property->property_name)
                                                <span class="material-symbols-outlined copy-icon" data-copy="{{ $tenant->property->property_name }}" title="Copy">content_copy</span>
                                                @endif
                                            </span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Unit:</span>
                                            <span class="info-value">
                                                {{ $tenant->unit->unit_name ?? $tenant->unit->unit_number ?? '-' }}
                                                @if($tenant->unit && ($tenant->unit->unit_name || $tenant->unit->unit_number))
                                                <span class="material-symbols-outlined copy-icon" data-copy="{{ $tenant->unit->unit_name ?? $tenant->unit->unit_number }}" title="Copy">content_copy</span>
                                                @endif
                                            </span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Rent Amount:</span>
                                            <span class="info-value">{{ $tenant->unit && $tenant->unit->rent_amount ? number_format($tenant->unit->rent_amount, 2) : '0.00' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Zone:</span>
                                            <span class="info-value">{{ $tenant->unit->zone->zone_name ?? '-' }}</span>
                                        </div>
                                    </div>

                                    <!-- Contact & Additional Details - Right Column -->
                                    <div class="col-lg-6">
                                        <div class="section-title"><span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">mail</span> Contact</div>
                                        <div class="info-row">
                                            <span class="info-label">Email Address:</span>
                                            <span class="info-value">{{ $tenant->email_address ?? '-' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Phone:</span>
                                            <span class="info-value">{{ $tenant->phone ?? '-' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Physical Address:</span>
                                            <span class="info-value">{{ $tenant->address ?? '-' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Registered Date:</span>
                                            <span class="info-value">{{ $tenant->registered_date ? $tenant->registered_date->format('d-m-Y') : '-' }}</span>
                                        </div>

                                        <div class="section-divider"></div>
                                        <div class="section-title"><span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">account_balance</span> Bank Account</div>
                                        @if($tenant->bankAccounts && $tenant->bankAccounts->count() > 0)
                                            @foreach($tenant->bankAccounts as $account)
                                            <div class="info-row">
                                                <span class="info-label">Bank:</span>
                                                <span class="info-value">{{ $account->bank->bank_name ?? '-' }}</span>
                                            </div>
                                            <div class="info-row">
                                                <span class="info-label">Account Name:</span>
                                                <span class="info-value">{{ $account->account_name ?? '-' }}</span>
                                            </div>
                                            <div class="info-row">
                                                <span class="info-label">Account Number:</span>
                                                <span class="info-value">{{ $account->account_number ?? '-' }}</span>
                                            </div>
                                            @endforeach
                                        @else
                                            <div class="info-row">
                                                <span class="info-value text-muted">No bank account information</span>
                                            </div>
                                        @endif

                                    </div>
                                </div>
                            </div>

                            <!-- Contacts Tab -->
                            <div class="tab-pane fade" id="contacts" role="tabpanel" aria-labelledby="contacts-tab">
                                <div class="mb-4">
                                    <h6 class="text-muted border-bottom pb-2">
                                        <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">contacts</span>
                                        Other Contacts
                                    </h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Mobile</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($tenant->otherContacts as $contact)
                                                <tr>
                                                    <td>{{ $contact->contact_names ?? '-' }}</td>
                                                    <td>{{ $contact->other_email ?? '-' }}</td>
                                                    <td>{{ $contact->other_mobile_number ?? '-' }}</td>
                                                </tr>
                                                @empty
                                                <tr>
                                                    <td colspan="3" class="text-muted text-center">No other contacts</td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <h6 class="text-muted border-bottom pb-2">
                                        <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">family_restroom</span>
                                        Next of Kin
                                    </h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Relationship</th>
                                                    <th>Mobile</th>
                                                    <th>Email</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($tenant->nextOfKins as $kin)
                                                <tr>
                                                    <td>{{ $kin->names ?? '-' }}</td>
                                                    <td>{{ $kin->relationship ?? '-' }}</td>
                                                    <td>{{ $kin->mobile_number ?? '-' }}</td>
                                                    <td>{{ $kin->email_address ?? '-' }}</td>
                                                </tr>
                                                @empty
                                                <tr>
                                                    <td colspan="4" class="text-muted text-center">No next of kin information</td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
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
                                                    <th>Service Charges</th>
                                                    <th>Contract</th>
                                                    <th>Actions</th>
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
        </div>
    </div>
</div>

<div class="modal fade" id="leaseDetailsModal" tabindex="-1" aria-labelledby="leaseDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="leaseDetailsModalLabel">
                    <span class="material-symbols-outlined" style="font-size:20px; vertical-align:middle;">description</span>
                    Lease Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="leaseDetailsModalBody"></div>
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
<script>
    $(function() {
        var paymentsTable = null;
        var leasesTable = null;
        var paymentsLoaded = false;
        var leasesLoaded = false;

        // Copy-to-clipboard for info fields
        $(document).on('click', '.copy-icon', function() {
            var $icon = $(this);
            var text = $icon.data('copy');
            if (!text) return;

            function showCopied() {
                var original = $icon.text();
                $icon.addClass('copied').text('check');
                setTimeout(function() {
                    $icon.removeClass('copied').text(original);
                }, 1200);
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(String(text)).then(showCopied);
            } else {
                var $tmp = $('<textarea>').val(text).appendTo('body').select();
                document.execCommand('copy');
                $tmp.remove();
                showCopied();
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
                        data: 'service_charges_amount',
                        render: function(data) { return data !== null && data !== undefined ? Number(data).toFixed(2) : '-'; }
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
                            var btns = '<a href="#" class="view-lease-details-btn me-1" data-id="' + row.encrypted_id + '" title="View Lease Details"><i class="fa fa-info-circle"></i></a>';
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

        // View lease details modal
        $(document).on('click', '.view-lease-details-btn', function(e) {
            e.preventDefault();
            var encryptedId = $(this).data('id');
            var $body = $('#leaseDetailsModalBody');

            $body.html('<div class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span></div>');
            $('#leaseDetailsModal').modal('show');

            $.ajax({
                url: '/property/leases/' + encryptedId + '/details',
                method: 'GET',
                dataType: 'json',
                success: function(lease) {
                    $body.html(renderLeaseDetails(lease));
                },
                error: function() {
                    $body.html('<div class="text-danger">Failed to load lease details.</div>');
                }
            });
        });

        function renderLeaseDetails(lease) {
            function field(label, value) {
                return `
                    <div class="col-6 col-md-3">
                        <div class="text-muted" style="font-size:10px; line-height:1.2;">${label}</div>
                        <div class="fw-semibold" style="font-size:12px; line-height:1.3;">${value !== null && value !== undefined && value !== '' ? value : '-'}</div>
                    </div>
                `;
            }

            var statusBadge = lease.lease_status === 'Active'
                ? '<span class="badge badge-light-success">Active</span>'
                : (lease.lease_status === 'Terminated'
                    ? '<span class="badge badge-light-danger">Terminated</span>'
                    : '<span class="badge badge-light-secondary">' + (lease.lease_status || '-') + '</span>');

            var contractBadge = lease.contract_status === 'signed'
                ? '<span class="badge badge-light-success">Signed</span>'
                : (lease.contract_status === 'sent'
                    ? '<span class="badge badge-light-warning">Sent</span>'
                    : '<span class="badge badge-light-secondary">Not Sent</span>');

            return `
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row g-2">
                            ${field('Ref Number', lease.ref_number)}
                            ${field('Status', statusBadge)}
                            ${field('Start Date', lease.date_start)}
                            ${field('End Date', lease.date_end)}
                            ${field('Duration (Years)', lease.years)}
                            ${field('Auto Renewal', lease.auto_renewal)}
                            ${field('Contract', contractBadge)}
                            ${field('Documents', lease.documents_count)}
                        </div>

                        <hr class="my-2">

                        <div class="row g-2">
                            ${field('Tenant', lease.tenant_name)}
                            ${field('Tenant Mobile', lease.tenant_mobile)}
                            ${field('Tenant Email', lease.tenant_email)}
                            ${field('Landlord', lease.landlord_name)}
                            ${field('Property', lease.property_name)}
                            ${field('Property Ref', lease.property_reference)}
                            ${field('Unit Code', lease.unit_code)}
                            ${field('Unit Name', lease.unit_name)}
                        </div>

                        <hr class="my-2">

                        <div class="row g-2">
                            ${field('Rent Amount', lease.rent_amount !== null ? Number(lease.rent_amount).toFixed(2) : '-')}
                            ${field('VAT-able', lease.vat_able)}
                            ${field('Escalation Rate', lease.escalation_rate !== null ? lease.escalation_rate + '%' : '-')}
                            ${field('Escalation Frequency', lease.escalation_frequency)}
                            ${field('Service Charges Amount', lease.service_charges_amount !== null ? Number(lease.service_charges_amount).toFixed(2) : '-')}
                            ${field('Water Meter Reading', lease.initial_water_meter_reading)}
                        </div>
                    </div>
                </div>
            `;
        }
    });
</script>
@endsection