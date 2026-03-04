@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Movement Details - {{ $movement->reference_number }}</h4>
                        <div class="ms-auto d-flex gap-2">
                            @if($movement->current_status_id == 1)
                            @permission('edit_tenant_movements')
                            <a href="{{ route('property.movements.edit', $movement->id) }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">edit</span>
                                <span class="ms-1">Edit</span>
                            </a>
                            @endpermission
                            @endif
                            <a href="{{ route('property.movements.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Movement Status Badge -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="alert alert-light-{{ $movement->current_status_id == 1 ? 'warning' : ($movement->current_status_id == 5 ? 'success' : 'danger') }} d-flex align-items-center">
                                    <span class="material-symbols-outlined me-2" style="font-size: 32px;">
                                        {{ $movement->current_status_id == 1 ? 'pending' : ($movement->current_status_id == 5 ? 'check_circle' : 'cancel') }}
                                    </span>
                                    <div>
                                        <h5 class="mb-0">Status: 
                                            @if($movement->current_status_id == 1)
                                                <span class="badge bg-warning">Pending</span>
                                            @elseif($movement->current_status_id == 5)
                                                <span class="badge bg-success">Confirmed</span>
                                            @else
                                                <span class="badge bg-danger">Denied</span>
                                            @endif
                                        </h5>
                                        <small class="text-muted">Reference: {{ $movement->reference_number }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Movement Information -->
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-lg-6">
                                <div class="card border mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="fa fa-user me-2"></i>Tenant Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm mb-0">
                                            <tr>
                                                <td class="fw-bold" style="width: 40%;">Tenant Name:</td>
                                                <td>{{ $movement->tenant->names ?? 'N/A' }}</td>
                                            </tr>
                                        </table>

                                        @if($tenantBalance)
                                        <div class="card border-0 shadow-sm mt-3">
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
                                            <div class="card-body py-2">
                                                <div class="row text-center">
                                                    <div class="col-4 border-end">
                                                        <small class="text-muted d-block">Total Invoiced</small>
                                                        <span class="fw-bold">{{ number_format($tenantBalance['total_invoiced'], 2) }}</span>
                                                    </div>
                                                    <div class="col-4 border-end">
                                                        <small class="text-muted d-block">Total Paid</small>
                                                        <span class="fw-bold text-success">{{ number_format($tenantBalance['total_paid'], 2) }}</span>
                                                    </div>
                                                    <div class="col-4">
                                                        <small class="text-muted d-block">Balance</small>
                                                        <span class="fw-bold {{ $tenantBalance['is_in_arrears'] ? 'text-danger' : 'text-success' }}">
                                                            @if($tenantBalance['is_in_arrears'])({{ $tenantBalance['balance_formatted'] }})@else{{ $tenantBalance['balance_formatted'] }}@endif
                                                        </span>
                                                    </div>
                                                </div>
                                                <p class="text-muted small mb-0 mt-2">As of: {{ $tenantBalance['as_of_date'] }}</p>
                                            </div>
                                        </div>
                                        @else
                                        <table class="table table-sm mb-0 mt-2">
                                            <tr>
                                                <td class="fw-bold" style="width: 40%;">Current Balance:</td>
                                                <td>{{ number_format($movement->current_balance ?? 0, 2) }}</td>
                                            </tr>
                                        </table>
                                        @endif
                                    </div>
                                </div>

                                <div class="card border mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="fa fa-building me-2"></i>Property Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm mb-0">
                                            <tr>
                                                <td class="fw-bold" style="width: 40%;">Client:</td>
                                                <td>{{ $movement->client->names ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">SLA:</td>
                                                <td>{{ $movement->sla->reference_number ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Property:</td>
                                                <td>{{ $movement->property->property_name ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Zone:</td>
                                                <td>{{ $movement->zone->zone_name ?? 'N/A' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <div class="card border mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="fa fa-home me-2"></i>Unit Movement</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-5 text-center">
                                                <div class="p-3 bg-light rounded">
                                                    <span class="material-symbols-outlined d-block mb-2" style="font-size: 48px;">home</span>
                                                    <h5 class="mb-0">{{ $movement->unitFrom->unit_code ?? $movement->unitFrom->unit_name ?? 'N/A' }}</h5>
                                                    <small class="text-muted">Unit From</small>
                                                </div>
                                            </div>
                                            <div class="col-2 text-center">
                                                <span class="material-symbols-outlined" style="font-size: 32px;">arrow_forward</span>
                                            </div>
                                            <div class="col-5 text-center">
                                                <div class="p-3 bg-light rounded">
                                                    <span class="material-symbols-outlined d-block mb-2" style="font-size: 48px;">home</span>
                                                    <h5 class="mb-0">{{ $movement->unitTo->unit_code ?? $movement->unitTo->unit_name ?? 'N/A' }}</h5>
                                                    <small class="text-muted">Unit To</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="col-lg-6">
                                <div class="card border mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="fa fa-file-contract me-2"></i>Lease Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm mb-0">
                                            <tr>
                                                <td class="fw-bold" style="width: 40%;">Old Lease:</td>
                                                <td>{{ $movement->lease->ref_number ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">New Lease:</td>
                                                <td>{{ $movement->newLease->ref_number ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Start Date:</td>
                                                <td>{{ $movement->new_lease_start_date ? \Carbon\Carbon::parse($movement->new_lease_start_date)->format('d M Y') : 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">End Date:</td>
                                                <td>{{ $movement->new_lease_end_date ? \Carbon\Carbon::parse($movement->new_lease_end_date)->format('d M Y') : 'N/A' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <div class="card border mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="fa fa-receipt me-2"></i>Invoiceable Items</h6>
                                    </div>
                                    <div class="card-body">
                                        @if($movement->items->count() > 0)
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Item</th>
                                                        <th class="text-end">Amount</th>
                                                        <th>Cycle</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($movement->items as $item)
                                                    <tr>
                                                        <td>{{ $item->item->item_name ?? 'N/A' }}</td>
                                                        <td class="text-end">{{ number_format($item->amount ?? 0, 2) }}</td>
                                                        <td><span class="badge bg-secondary">{{ $item->cycle->cycle_name }}</span></td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot>
                                                    <tr class="fw-bold">
                                                        <td>Total</td>
                                                        <td class="text-end">{{ number_format($movement->items->sum('amount'), 2) }}</td>
                                                        <td></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                        @else
                                        <p class="text-muted mb-0">No invoiceable items found.</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="card border mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="fa fa-info-circle me-2"></i>Additional Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm mb-0">
                                            <tr>
                                                <td class="fw-bold" style="width: 40%;">Entry Date:</td>
                                                <td>{{ $movement->date_time ? \Carbon\Carbon::parse($movement->date_time)->format('d M Y H:i:s') : 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Created By:</td>
                                                <td>{{ $movement->createdBy->name ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Created At:</td>
                                                <td>{{ $movement->created_at ? $movement->created_at->format('d M Y H:i:s') : 'N/A' }}</td>
                                            </tr>
                                            @if($movement->updated_at && $movement->updated_at != $movement->created_at)
                                            <tr>
                                                <td class="fw-bold">Last Updated:</td>
                                                <td>{{ $movement->updated_at->format('d M Y H:i:s') }}</td>
                                            </tr>
                                            @endif
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
@endsection
