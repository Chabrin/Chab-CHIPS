@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card card-no-border">
                    <div class="card-header pb-3 d-flex justify-content-between align-items-center">
                        <h5>Client Advance Details</h5>
                        <a href="{{ route('property.advances.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fa fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                    <div class="card-body custom-scrollbar">
                        <!-- Basic Information -->
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Basic Information</h6>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Reference Number</label>
                                <div class="fw-bold">{{ $advance->reference_number ?? '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Date & Time</label>
                                <div class="fw-bold">{{ $advance->date_time ? $advance->date_time->format('Y-m-d H:i:s') : '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Requisition Date</label>
                                <div class="fw-bold">{{ $advance->requisition_date ? $advance->requisition_date->format('Y-m-d') : '-' }}</div>
                            </div>
                        </div>

                        <!-- Client & Property Information -->
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Client & Property Information</h6>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Client Name</label>
                                <div class="fw-bold">{{ $advance->client->client_name ?? '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Client Reference</label>
                                <div class="fw-bold">{{ $advance->client->reference_number ?? '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Property</label>
                                <div class="fw-bold">{{ $advance->property->property_name ?? '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Zone</label>
                                <div class="fw-bold">{{ $advance->zone->zone_name ?? '-' }}</div>
                            </div>
                        </div>

                        <!-- Financial Information -->
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Financial Information</h6>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Amount</label>
                                <div class="fw-bold text-primary">{{ $advance->amount ? number_format($advance->amount, 2) : '0.00' }}</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Interest Rate</label>
                                <div class="fw-bold">{{ $advance->interest_rate ?? '0' }}%</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Interest Amount</label>
                                <div class="fw-bold">{{ $advance->interest_amount ? number_format($advance->interest_amount, 2) : '0.00' }}</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Total Amount</label>
                                <div class="fw-bold">{{ $advance->total_amount ? number_format($advance->total_amount, 2) : '0.00' }}</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Current Balance</label>
                                <div class="fw-bold">{{ $advance->current_balance ? number_format($advance->current_balance, 2) : '0.00' }}</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Repayment Months</label>
                                <div class="fw-bold">{{ $advance->repayment_months ?? '-' }}</div>
                            </div>
                        </div>

                        <!-- Charges & Fees -->
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Charges & Fees</h6>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Miscellaneous Charge</label>
                                <div class="fw-bold">{{ $advance->invoiceableitems->item_name ?? '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Charge Amount</label>
                                <div class="fw-bold">{{ $advance->charge_amount ? number_format($advance->charge_amount, 2) : '0.00' }}</div>
                            </div>
                        </div>

                        <!-- Dates -->
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Important Dates</h6>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Principal Start Date</label>
                                <div class="fw-bold">{{ $advance->principal_start_date ? $advance->principal_start_date->format('Y-m-d') : '-' }}</div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="text-muted small">Disbursement Date</label>
                                <div class="fw-bold">{{ $advance->disbursement_date ? $advance->disbursement_date->format('Y-m-d') : '-' }}</div>
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Additional Information</h6>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Advance Type</label>
                                <div class="fw-bold">
                                    @if($advance->advance_type_id == 1)
                                    <span class="badge bg-primary">New Advance</span>
                                    @elseif($advance->advance_type_id == 2)
                                    <span class="badge bg-info">Top Up Advance</span>
                                    @elseif($advance->advance_type_id == 3)
                                    <span class="badge bg-primary">Reschedule Advance</span>
                                    @elseif($advance->advance_type_id == 4)
                                    <span class="badge bg-info">Lumpsum Advance</span>
                                    @else
                                    -
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Previous Advance</label>
                                <div class="fw-bold">
                                    @if($advance->previous_advance_id)
                                    {{ $advance->previousAdvance->reference_number ?? 'REF #' . $advance->previous_advance_id }}
                                    @else
                                    -
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Top Up Amount</label>
                                <div class="fw-bold">{{ $advance->top_up ? number_format($advance->top_up, 2) : '-' }}</div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">One Off</label>
                                <div class="fw-bold">
                                    @if($advance->one_off)
                                    <span class="badge bg-info">Yes</span>
                                    @else
                                    <span class="badge bg-secondary">No</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-muted small">Status</label>
                                <div class="fw-bold">
                                    @if($advance->current_status_id == 1)
                                    <span class="badge badge-light-warning">Pending</span>
                                    @elseif($advance->current_status_id == 2)
                                    <span class="badge badge-light-success">Cleared</span>
                                    @elseif($advance->current_status_id == 3)
                                    <span class="badge badge-light-danger">Cancelled</span>
                                    @elseif($advance->current_status_id == 4)
                                    <span class="badge badge-light-danger">Suspended</span>
                                    @elseif($advance->current_status_id == 5)
                                    <span class="badge badge-light-success">Approved</span>
                                    @elseif($advance->current_status_id == 6)
                                    <span class="badge badge-light-success">Posted</span>
                                    @elseif($advance->current_status_id == 8)
                                    <span class="badge badge-light-info">Rescheduled</span>
                                    @else
                                    <span class="badge bg-secondary">Unknown</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="text-muted small">Created By</label>
                                <div class="fw-bold">{{ $advance->createdBy->name ?? '-' }}</div>
                            </div>
                        </div>

                        <!-- Narration -->
                        @if($advance->narration)
                        <div class="row mb-2">
                            <div class="col-12">
                                <h6 class="border-bottom pb-1 mb-2">Narration</h6>
                            </div>
                            <div class="col-12">
                                <div class="p-3 bg-light text-dark rounded">
                                    {{ $advance->narration }}
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Action Buttons -->
                        <div class="row mt-3">
                            <div class="col-12 d-flex gap-2">
                                @permission('update_client_advances')
                                <a href="{{ route('property.advances.edit', Crypt::encrypt($advance->id)) }}" class="btn btn-primary btn-sm">
                                    <i class="fa fa-edit"></i> Edit Advance
                                </a>
                                @endpermission
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


@endsection