@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <!-- Header Card -->
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Repair Request Details - {{ $repairRequest->ref_number }}</h5>
                        <div class="d-flex gap-2">
                            @permission('update_repair_requests')
                                <a href="{{ route('property.repairs.edit', Crypt::encrypt($repairRequest->id)) }}" 
                                   class="btn btn-primary btn-sm d-flex align-items-center">
                                    <span class="material-symbols-outlined">edit_document</span>
                                    <span class="ms-1">Edit</span>
                                </a>
                            @endpermission
                            <a href="{{ route('property.repairs.index') }}" class="btn btn-secondary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
                            </a>
                        </div>
                    </div>
                </div>

            <div class="row mt-3">
                <!-- Request Information -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <span class="material-symbols-outlined align-middle">info</span>
                                Request Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tbody>
                                    <tr>
                                        <td class="fw-bold" style="width: 40%;">Reference Number:</td>
                                        <td>{{ $repairRequest->ref_number ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Status:</td>
                                        <td>
                                            @if($repairRequest->current_status_id == 1)
                                                <span class="badge bg-warning">
                                                    <span class="material-symbols-outlined align-middle" style="font-size: 16px;">pending</span>
                                                    Pending
                                                </span>
                                            @elseif($repairRequest->current_status_id == 5)
                                                <span class="badge bg-success">
                                                    <span class="material-symbols-outlined align-middle" style="font-size: 16px;">check_circle</span>
                                                    Approved
                                                </span>
                                            @elseif($repairRequest->current_status_id == 41)
                                                <span class="badge bg-danger">
                                                    <span class="material-symbols-outlined align-middle" style="font-size: 16px;">cancel</span>
                                                    Rejected
                                                </span>
                                            @else
                                                <span class="badge bg-secondary">
                                                    <span class="material-symbols-outlined align-middle" style="font-size: 16px;">help</span>
                                                    Unknown
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Created Date:</td>
                                        <td>{{ \Carbon\Carbon::parse($repairRequest->date_time)->format('d M Y, H:i') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Visited Date & Time:</td>
                                        <td>{{ \Carbon\Carbon::parse($repairRequest->visited_date_time)->format('d M Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Created By:</td>
                                        <td>{{ $repairRequest->createdBy->name ?? '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Property Information -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <span class="material-symbols-outlined align-middle">apartment</span>
                                Property Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tbody>
                                    <tr>
                                        <td class="fw-bold" style="width: 40%;">Client:</td>
                                        <td>{{ $repairRequest->client->names ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Client Ref:</td>
                                        <td>{{ $repairRequest->client->reference_number ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Property:</td>
                                        <td>{{ $repairRequest->property->property_name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Property Ref:</td>
                                        <td>{{ $repairRequest->property->reference_number ?? '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Repair Items -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">
                        <span class="material-symbols-outlined align-middle">build</span>
                        Repair Items
                    </h5>
                </div>
                <div class="card-body">
                    @if($items->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Unit</th>
                                        <th>Element</th>
                                        <th>Repair Type</th>
                                        <th>Amount</th>
                                        <th>Remarks</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $index => $item)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            @if($item->unit_id)
                                                {{ \App\Models\Unit::find($item->unit_id)->unit_name ?? '-' }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            {{ \App\Models\PropertyElement::find($item->property_element_id)->element_name ?? '-' }}
                                        </td>
                                        <td>{{ $item->repairType->element_name ?? '-' }}</td>
                                        <td class="text-end">{{ number_format($item->amount, 2) }}</td>
                                        <td>{{ $item->remarks ?? '-' }}</td>
                                        <td>
                                            @if($item->current_status_id == 1)
                                                <span class="badge bg-warning">Pending</span>
                                            @elseif($item->current_status_id == 5)
                                                <span class="badge bg-success">Approved</span>
                                            @elseif($item->current_status_id == 41)
                                                <span class="badge bg-danger">Rejected</span>
                                            @else
                                                <span class="badge bg-secondary">Unknown</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                    <tr class="table-light fw-bold">
                                        <td colspan="4" class="text-end">Total Amount:</td>
                                        <td class="text-end">{{ number_format($items->sum('amount'), 2) }}</td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info mb-0">
                            <span class="material-symbols-outlined align-middle">info</span>
                            No repair items found for this request.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
</div>
@endsection
