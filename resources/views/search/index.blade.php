@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top: 10px;">
    <div class="container-fluid">

        {{-- Top Summary Card --}}
        <div class="row mb-3">
            <div class="col-sm-12">
                <div class="card shadow-sm border-0 border-top border-3 border-primary">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h5 class="mb-1 fw-bold text-dark">
                                    <i class="fa fa-search text-primary me-2"></i>Search Results
                                </h5>
                                @if($term !== '')
                                    <p class="text-muted small mb-0">Showing matching records across clients, staff, properties, invoices, returns, vouchers, and advances.</p>
                                @endif
                            </div>
                            @if($term !== '')
                                <span class="badge bg-light text-dark border px-3 py-2 fs-6">
                                    Term: <span class="text-primary fw-bold">{{ $term }}</span>
                                </span>
                            @endif
                        </div>

                        @if(strlen($term) < 2)
                            <div class="alert alert-light border text-muted mt-3 mb-0 py-2">
                                <i class="fa fa-info-circle text-info me-1"></i> Type at least 2 characters to search.
                            </div>
                        @elseif($clients->isEmpty() && $staff->isEmpty() && $properties->isEmpty() && $invoices->isEmpty() && $returns->isEmpty() && $vouchers->isEmpty() && $advances->isEmpty())
                            <div class="text-center py-4">
                                <i class="fa fa-folder-open text-muted mb-2" style="font-size: 2rem;"></i>
                                <p class="text-muted mb-0">No records found matching "<strong>{{ $term }}</strong>".</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Clients & Tenants --}}
        @if($clients->isNotEmpty())
        <div class="row mb-3">
            <div class="col-sm-12">
                <div class="card shadow-sm border-0 border-top border-3 border-primary">
                    <div class="card-header bg-white card-no-border py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-primary">
                            <i class="fa fa-users me-2"></i>Clients &amp; Tenants
                        </h6>
                        <span class="badge bg-primary rounded-pill px-3 py-1">{{ $clients->count() }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive custom-scrollbar">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="ps-3">Name</th>
                                        <th>Reference Number</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($clients as $client)
                                    @php
                                        $initials = strtoupper(substr($client->names ?? 'C', 0, 1) . substr($client->last_name ?? '', 0, 1));
                                        if (empty(trim($initials))) { $initials = 'CL'; }
                                    @endphp
                                    <tr>
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle d-flex align-items-center justify-content-center text-primary fw-bold me-2 flex-shrink-0"
                                                     style="width: 36px; height: 36px; background-color: #e7f1ff; font-size: 0.85rem;">
                                                    {{ $initials }}
                                                </div>
                                                <div>
                                                    <a href="{{ route('property.clients.show', $client->encrypted_id) }}" 
                                                       class="fw-semibold text-dark text-decoration-none">
                                                        {{ trim("{$client->names} {$client->second_name} {$client->last_name}") }}
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border font-monospace px-2 py-1">
                                                {{ $client->reference_number }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($client->mobile_number)
                                                <a href="tel:{{ $client->mobile_number }}" class="text-secondary text-decoration-none">
                                                    <i class="fa fa-phone text-muted me-1 small"></i>{{ $client->mobile_number }}
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($client->email_address)
                                                <a href="mailto:{{ $client->email_address }}" class="text-secondary text-decoration-none">
                                                    <i class="fa fa-envelope text-muted me-1 small"></i>{{ $client->email_address }}
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="{{ route('property.clients.show', $client->encrypted_id) }}" 
                                               class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1">
                                                View <i class="fa fa-chevron-right ms-1" style="font-size: 0.7rem;"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Staff --}}
        @if($staff->isNotEmpty())
        <div class="row mb-3">
            <div class="col-sm-12">
                <div class="card shadow-sm border-0 border-top border-3 border-info">
                    <div class="card-header bg-white card-no-border py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-info">
                            <i class="fa fa-id-badge me-2"></i>Staff Members
                        </h6>
                        <span class="badge bg-info text-white rounded-pill px-3 py-1">{{ $staff->count() }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive custom-scrollbar">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="ps-3">Name</th>
                                        <th>Staff Number</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($staff as $member)
                                    @php
                                        $parts = explode(' ', trim($member->full_names ?? 'Staff'));
                                        $initials = strtoupper(substr($parts[0] ?? 'S', 0, 1) . substr($parts[1] ?? '', 0, 1));
                                    @endphp
                                    <tr>
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle d-flex align-items-center justify-content-center text-info fw-bold me-2 flex-shrink-0"
                                                     style="width: 36px; height: 36px; background-color: #e0f7fa; font-size: 0.85rem;">
                                                    {{ $initials }}
                                                </div>
                                                <div>
                                                    <a href="{{ route('staff.show', $member->encrypted_id) }}" 
                                                       class="fw-semibold text-dark text-decoration-none">
                                                        {{ $member->full_names }}
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border font-monospace px-2 py-1">
                                                {{ $member->staff_number }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($member->mobile_number)
                                                <a href="tel:{{ $member->mobile_number }}" class="text-secondary text-decoration-none">
                                                    <i class="fa fa-phone text-muted me-1 small"></i>{{ $member->mobile_number }}
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($member->email_address)
                                                <a href="mailto:{{ $member->email_address }}" class="text-secondary text-decoration-none">
                                                    <i class="fa fa-envelope text-muted me-1 small"></i>{{ $member->email_address }}
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="{{ route('staff.show', $member->encrypted_id) }}" 
                                               class="btn btn-sm btn-outline-info rounded-pill px-3 py-1">
                                                View <i class="fa fa-chevron-right ms-1" style="font-size: 0.7rem;"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Properties --}}
        @if($properties->isNotEmpty())
        <div class="row mb-3">
            <div class="col-sm-12">
                <div class="card shadow-sm border-0 border-top border-3 border-success">
                    <div class="card-header bg-white card-no-border py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-success">
                            <i class="fa fa-building me-2"></i>Properties
                        </h6>
                        <span class="badge bg-success rounded-pill px-3 py-1">{{ $properties->count() }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive custom-scrollbar">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="ps-3">Property Name</th>
                                        <th>Reference Number</th>
                                        <th>LR Number</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($properties as $property)
                                    <tr>
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle d-flex align-items-center justify-content-center text-success fw-bold me-2 flex-shrink-0"
                                                     style="width: 36px; height: 36px; background-color: #e8f5e9; font-size: 0.95rem;">
                                                    <i class="fa fa-city"></i>
                                                </div>
                                                <div>
                                                    <a href="{{ route('property.properties.show', $property->encrypted_id) }}" 
                                                       class="fw-semibold text-dark text-decoration-none">
                                                        {{ $property->property_name }}
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border font-monospace px-2 py-1">
                                                {{ $property->reference_number }}
                                            </span>
                                        </td>
                                        <td>{{ $property->lr_number ?? '—' }}</td>
                                        <td class="text-end pe-3">
                                            <a href="{{ route('property.properties.show', $property->encrypted_id) }}" 
                                               class="btn btn-sm btn-outline-success rounded-pill px-3 py-1">
                                                View <i class="fa fa-chevron-right ms-1" style="font-size: 0.7rem;"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif


        {{-- Returns, Payment Vouchers, Advances (reference code lookups) --}}
        @foreach([
            ['title' => 'Returns', 'icon' => 'fa-undo', 'rows' => $returns],
            ['title' => 'Payment Vouchers', 'icon' => 'fa-money-check', 'rows' => $vouchers],
            ['title' => 'Client Advances', 'icon' => 'fa-hand-holding-usd', 'rows' => $advances],
        ] as $group)
        @if($group['rows']->isNotEmpty())
        <div class="row mb-3">
            <div class="col-sm-12">
                <div class="card shadow-sm border-0 border-top border-3 border-secondary">
                    <div class="card-header bg-white card-no-border py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-dark"><i class="fa {{ $group['icon'] }} text-secondary me-2"></i>{{ $group['title'] }}</h6>
                        <span class="badge bg-secondary rounded-pill px-3 py-1">{{ $group['rows']->count() }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive custom-scrollbar">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="ps-3">Reference</th>
                                        <th>Detail</th>
                                        <th class="text-end pe-3">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($group['rows'] as $row)
                                    <tr>
                                        <td class="ps-3">
                                            @if($group['title'] === 'Returns')
                                                <a href="{{ route('returns.details', $row->encrypted_id) }}" class="font-monospace fw-semibold text-dark text-decoration-none">{{ $row->return_code }}</a>
                                            @elseif($group['title'] === 'Payment Vouchers')
                                                <a href="{{ route('payment.vouchers.details', $row->encrypted_id) }}" class="font-monospace fw-semibold text-dark text-decoration-none">{{ $row->voucher_number }}</a>
                                            @else
                                                <a href="{{ route('property.advances.show', $row->encrypted_id) }}" class="font-monospace fw-semibold text-dark text-decoration-none">{{ $row->reference_number }}</a>
                                            @endif
                                        </td>
                                        <td class="text-muted small">
                                            @if($group['title'] === 'Returns')
                                                {{ $row->return_type }} &middot; {{ $row->property_code }} &middot; {{ $row->year }}-{{ str_pad($row->month, 2, '0', STR_PAD_LEFT) }} &middot; {{ $row->paid_status == 1 ? 'Paid' : ($row->approved_status == 1 ? 'Approved' : 'Not approved') }}
                                            @elseif($group['title'] === 'Payment Vouchers')
                                                {{ $row->vendor_name }} &middot; {{ $row->voucher_status }}
                                            @else
                                                {{ $row->client->names ?? '—' }} &middot; balance {{ number_format($row->current_balance ?? 0, 2) }}
                                            @endif
                                        </td>
                                        <td class="text-end pe-3 font-monospace">
                                            {{ number_format((float) ($group['title'] === 'Returns' ? $row->net_balance : ($group['title'] === 'Payment Vouchers' ? $row->debit_amount : $row->amount)), 2) }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
        @endforeach

        {{-- Invoices --}}
        @if($invoices->isNotEmpty())
        <div class="row mb-3">
            <div class="col-sm-12">
                <div class="card shadow-sm border-0 border-top border-3 border-warning">
                    <div class="card-header bg-white card-no-border py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="fa fa-file-invoice-dollar text-warning me-2"></i>Invoices
                        </h6>
                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1">{{ $invoices->count() }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive custom-scrollbar">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="ps-3">Reference Number</th>
                                        <th>Tenant</th>
                                        <th class="text-end">Total Amount</th>
                                        <th class="text-end pe-3">Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invoices as $invoice)
                                    <tr>
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle d-flex align-items-center justify-content-center text-warning fw-bold me-2 flex-shrink-0"
                                                     style="width: 36px; height: 36px; background-color: #fff8e1; font-size: 0.95rem;">
                                                    <i class="fa fa-receipt text-warning"></i>
                                                </div>
                                                <div>
                                                    @if($invoice->tenant)
                                                        <a href="{{ route('property.clients.show', $invoice->tenant->encrypted_id) }}" 
                                                           class="font-monospace fw-semibold text-dark text-decoration-none">
                                                            {{ $invoice->reference_number }}
                                                        </a>
                                                    @else
                                                        <span class="font-monospace fw-semibold text-dark">{{ $invoice->reference_number }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $invoice->tenant->names ?? $invoice->client->names ?? '—' }}</td>
                                        <td class="text-end font-monospace">{{ number_format($invoice->total_amount ?? 0, 2) }}</td>
                                        <td class="text-end pe-3 font-monospace">
                                            @if(($invoice->current_balance ?? 0) > 0)
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                                    {{ number_format($invoice->current_balance ?? 0, 2) }}
                                                </span>
                                            @else
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                    0.00
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>
@endsection