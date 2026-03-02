@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Recovery Details</h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('property.recoveries.edit', Crypt::encrypt($recovery->id)) }}" class="btn btn-warning btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">edit</span>
                                <span>Edit</span>
                            </a>
                            <a href="{{ route('property.recoveries.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        @endif

                        <!-- Recovery Information -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="border-bottom pb-2 mb-3">Recovery Information</h5>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Reference Number</label>
                                <p class="fw-bold">{{ $recovery->ref_number ?? '-' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Recovery Date</label>
                                <p class="fw-bold">{{ $recovery->recovery_date ? \Carbon\Carbon::parse($recovery->recovery_date)->format('d-m-Y') : '-' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Amount</label>
                                <p class="fw-bold">KES {{ number_format($recovery->amount ?? 0, 2) }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Transaction Type</label>
                                <p class="fw-bold">{{ $recovery->type_id ?? '-' }}</p>
                            </div>
                        </div>

                        <!-- Client & Property Information -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="border-bottom pb-2 mb-3">Client & Property Information</h5>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Client</label>
                                <p class="fw-bold">{{ $recovery->client->names ?? '-' }}</p>
                                <small class="text-muted">{{ $recovery->client->reference_number ?? '' }}</small>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Property</label>
                                <p class="fw-bold">{{ $recovery->property->property_name ?? '-' }}</p>
                                <small class="text-muted">{{ $recovery->property->reference_number ?? '' }}</small>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Zone</label>
                                <p class="fw-bold">{{ $recovery->zone->zone_name ?? '-' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">SLA Reference</label>
                                <p class="fw-bold">{{ $recovery->sla_id ?? '-' }}</p>
                            </div>
                        </div>

                        <!-- Tenant & Lease Information -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="border-bottom pb-2 mb-3">Tenant & Lease Information</h5>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Unit ID</label>
                                <p class="fw-bold">{{ $recovery->unit_id ?? '-' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Tenant ID</label>
                                <p class="fw-bold">{{ $recovery->tenant_id ?? '-' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Lease ID</label>
                                <p class="fw-bold">{{ $recovery->lease_id ?? '-' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Offset Invoice ID</label>
                                <p class="fw-bold">{{ $recovery->offset_invoice_id ?? '-' }}</p>
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="border-bottom pb-2 mb-3">Additional Information</h5>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Commissionable</label>
                                <p class="fw-bold">
                                    @if($recovery->commisionable === 'Yes')
                                        <span class="badge bg-success">Yes</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Return Type</label>
                                <p class="fw-bold">{{ $recovery->return_type_id ?? '-' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Created By</label>
                                <p class="fw-bold">{{ $recovery->createdBy->name ?? '-' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Date Created</label>
                                <p class="fw-bold">{{ $recovery->date_time ? \Carbon\Carbon::parse($recovery->date_time)->format('d-m-Y H:i') : '-' }}</p>
                            </div>
                        </div>

                        <!-- Narration -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="border-bottom pb-2 mb-3">Narration</h5>
                            </div>
                            <div class="col-md-12 mb-3">
                                <div class="p-3 bg-light rounded">
                                    {{ $recovery->narration ?? 'No narration provided' }}
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
