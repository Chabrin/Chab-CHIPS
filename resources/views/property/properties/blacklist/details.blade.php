@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Blacklist Details</h4>
                        <div class="ms-auto d-flex gap-2">
                            @if($blacklist->current_status_id == 17)
                            @permission('update_tenant_blacklist')
                            <a href="{{ route('property.blacklist.edit', Crypt::encrypt($blacklist->id)) }}" class="btn btn-warning btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">edit</span>
                                <span class="ms-1">Edit</span>
                            </a>
                            @endpermission
                            @endif
                            <a href="{{ route('property.blacklist.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Status Badge -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="d-flex align-items-center">
                                    <h5 class="mb-0 me-3">Status:</h5>
                                    @if($blacklist->current_status_id == 17)
                                    <span class="badge bg-success d-flex align-items-center">
                                        <span class="material-symbols-outlined me-1">check_circle</span>
                                        Processed
                                    </span>
                                    @elseif($blacklist->current_status_id == 1)
                                    <span class="badge bg-warning d-flex align-items-center">
                                        <span class="material-symbols-outlined me-1">pending</span>
                                        Pending
                                    </span>
                                    @else
                                    <span class="badge bg-secondary d-flex align-items-center">
                                        <span class="material-symbols-outlined me-1">help</span>
                                        Unknown
                                    </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Tenant Information -->
                            <div class="col-md-6 mb-4">
                                <div class="card border">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Tenant Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr>
                                                <th width="40%">Tenant Name:</th>
                                                <td>
                                                    @if($blacklist->tenant_id)
                                                    <a href="{{ url('/property/clients/' . $blacklist->tenant_id) }}" class="text-primary">
                                                        {{ $blacklist->tenant->names ?? 'N/A' }}
                                                    </a>
                                                    @else
                                                    {{ $blacklist->tenant->names ?? 'N/A' }}
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Blacklist Date:</th>
                                                <td>{{ $blacklist->blacklist_date ? \Carbon\Carbon::parse($blacklist->blacklist_date)->format('d-m-Y') : 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Current Balance:</th>
                                                <td class="text-danger fw-bold">{{ number_format($blacklist->current_balance, 2) }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Property Information -->
                            <div class="col-md-6 mb-4">
                                <div class="card border">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Property Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr>
                                                <th width="40%">Client:</th>
                                                <td>{{ $blacklist->client->names ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Property:</th>
                                                <td>{{ $blacklist->property->property_name ?? 'N/A' }} ({{ $blacklist->property->reference_number ?? 'N/A' }})</td>
                                            </tr>
                                            <tr>
                                                <th>Zone:</th>
                                                <td>{{ $blacklist->zone->zone_name ?? 'N/A' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Unit & Lease Information -->
                            <div class="col-md-6 mb-4">
                                <div class="card border">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Unit & Lease Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr>
                                                <th width="40%">Unit:</th>
                                                <td>{{ $blacklist->unit->unit_code ?? $blacklist->unit->unit_name ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Lease Reference:</th>
                                                <td>{{ $blacklist->lease->ref_number ?? 'N/A' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Blacklist Reason -->
                            <div class="col-md-6 mb-4">
                                <div class="card border">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Blacklist Reason</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr>
                                                <th width="40%">Reason:</th>
                                                <td><span class="badge bg-danger">{{ $blacklist->reason->reason_name ?? 'N/A' }}</span></td>
                                            </tr>
                                            <tr>
                                                <th>Description:</th>
                                                <td>{{ $blacklist->description ?? 'No description provided' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Information -->
                            <div class="col-md-12 mb-4">
                                <div class="card border">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Additional Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr>
                                                <th width="20%">Created By:</th>
                                                <td>{{ $blacklist->createdBy->name ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Date Created:</th>
                                                <td>{{ $blacklist->date_time ? \Carbon\Carbon::parse($blacklist->date_time)->format('d-m-Y H:i:s') : 'N/A' }}</td>
                                            </tr>
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
