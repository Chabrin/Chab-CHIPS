@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card card-no-border">
                    <div class="card-header pb-3 d-flex justify-content-between align-items-center">
                        <h5>Service Agreement Details</h5>
                        <div class="d-flex gap-2">
                            @permission('edit_service_agreements')
                            <a href="{{ route('property.sla.edit', Crypt::encrypt($sla->id)) }}" class="btn btn-primary btn-sm">
                                <i class="fa fa-edit"></i> Edit
                            </a>
                            @endpermission
                            <a href="{{ route('service.agreements.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fa fa-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>
                    <div class="card-body custom-scrollbar">
                        <!-- Basic Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="border-bottom pb-2 mb-3">Basic Information</h6>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">Reference Number</label>
                                <div class="fw-bold">{{ $sla->reference_number ?? '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">Start Date</label>
                                <div class="fw-bold">{{ $sla->date_start ? \Carbon\Carbon::parse($sla->date_start)->format('Y-m-d') : '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">End Date</label>
                                <div class="fw-bold">{{ $sla->date_end ? \Carbon\Carbon::parse($sla->date_end)->format('Y-m-d') : '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">Status</label>
                                <div class="fw-bold">
                                    @if($sla->current_status_id == 5)
                                        <span class="badge bg-success">Active</span>
                                    @elseif($sla->current_status_id == 6)
                                        <span class="badge bg-danger">Terminated</span>
                                    @elseif($sla->current_status_id == 7)
                                        <span class="badge bg-warning">Expired</span>
                                    @else
                                        <span class="badge bg-secondary">Unknown</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">Duration (Years)</label>
                                <div class="fw-bold">{{ $sla->years ?? '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">Auto Renewal</label>
                                <div class="fw-bold">
                                    @if($sla->auto_renewal == 'Yes')
                                        <span class="badge bg-success">Yes</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Client & Property Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="border-bottom pb-2 mb-3">Client & Property Information</h6>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">Client Name</label>
                                <div class="fw-bold">{{ $sla->client->names ?? '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">Client Reference</label>
                                <div class="fw-bold">{{ $sla->client->reference_number ?? '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">Property</label>
                                <div class="fw-bold">{{ $sla->property->property_name ?? '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">Property Reference</label>
                                <div class="fw-bold">{{ $sla->property->reference_number ?? '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">Zone</label>
                                <div class="fw-bold">{{ $sla->zone->zone_name ?? '-' }}</div>
                            </div>
                        </div>

                        <!-- Financial Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="border-bottom pb-2 mb-3">Financial Information</h6>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">Commission Percentage</label>
                                <div class="fw-bold text-primary">{{ $sla->commission_percentage ?? '0' }}%</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">VAT</label>
                                <div class="fw-bold">{{ $sla->vat ?? 'No' }}</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">VAT Percentage</label>
                                <div class="fw-bold">{{ $sla->vat_per ?? '0' }}%</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">Flat Rate VAT</label>
                                <div class="fw-bold">{{ $sla->flat_rate_vat ?? '0' }}%</div>
                            </div>
                        </div>

                        <!-- Invoice Charges -->
                        @if($sla->invoiceClients && $sla->invoiceClients->count() > 0)
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="border-bottom pb-2 mb-3">Invoice Charges</h6>
                            </div>
                            <div class="col-12">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Item</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($sla->invoiceClients as $index => $charge)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $charge->item->item_name ?? '-' }}</td>
                                                <td>{{ $charge->amount ? number_format($charge->amount, 2) : '0.00' }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Billing Charges -->
                        @if($sla->billCharges && $sla->billCharges->count() > 0)
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="border-bottom pb-2 mb-3">Billing Charges</h6>
                            </div>
                            <div class="col-12">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Item</th>
                                                <th>Cost Per Unit</th>
                                                <th>Commissionable</th>
                                                <th>Commission %</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($sla->billCharges as $index => $charge)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $charge->item->item_name ?? '-' }}</td>
                                                <td>{{ $charge->cost_per_unit ? number_format($charge->cost_per_unit, 2) : '0.00' }}</td>
                                                <td>{{ $charge->commissionable ?? 'No' }}</td>
                                                <td>{{ $charge->comm_percentage ?? '0' }}%</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Return Types -->
                        @if($sla->returnTypes && $sla->returnTypes->count() > 0)
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="border-bottom pb-2 mb-3">Return Types</h6>
                            </div>
                            @foreach($sla->returnTypes as $returnType)
                            <div class="col-12 mb-3">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <strong>{{ $returnType->returnType->return_type_name ?? '-' }}</strong>
                                        <span class="text-muted ms-2">(Cycle: {{ $returnType->cycle_name ?? '-' }})</span>
                                    </div>
                                    <div class="card-body">
                                        @if($returnType->invoiceableItems && $returnType->invoiceableItems->count() > 0)
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Item</th>
                                                        <th>Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($returnType->invoiceableItems as $index => $item)
                                                    <tr>
                                                        <td>{{ $index + 1 }}</td>
                                                        <td>{{ $item->invoiceableItem->item_name ?? '-' }}</td>
                                                        <td>{{ $item->amount ? number_format($item->amount, 2) : '0.00' }}</td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        @else
                                        <p class="text-muted mb-0">No items defined</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @endif

                        <!-- Documents -->
                        @if($sla->serviceAgreementDocuments && $sla->serviceAgreementDocuments->count() > 0)
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="border-bottom pb-2 mb-3">Documents</h6>
                            </div>
                            <div class="col-12">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Document</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($sla->serviceAgreementDocuments as $index => $document)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>
                                                    @if($document->sla_documents)
                                                        {{ basename($document->sla_documents) }}
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($document->sla_documents && Storage::disk('public')->exists($document->sla_documents))
                                                        <a href="{{ Storage::url($document->sla_documents) }}" 
                                                           target="_blank" 
                                                           class="btn btn-sm btn-info">
                                                            <i class="fa fa-download"></i> Download
                                                        </a>
                                                    @else
                                                        <span class="text-muted">Not available</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Timestamps -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="border-bottom pb-2 mb-3">Audit Information</h6>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">Created At</label>
                                <div class="fw-bold">{{ $sla->created_at ? $sla->created_at->format('Y-m-d H:i:s') : '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">Updated At</label>
                                <div class="fw-bold">{{ $sla->updated_at ? $sla->updated_at->format('Y-m-d H:i:s') : '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">Created By</label>
                                <div class="fw-bold">{{ $sla->createdBy->name ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
