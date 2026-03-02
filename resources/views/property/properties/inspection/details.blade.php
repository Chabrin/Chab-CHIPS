@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Property Inspection Details</h4>
                        <div class="ms-auto d-flex gap-2">
                            @if($inspection->current_status_id != 5)
                            <a href="{{ route('property.inspection.edit', Crypt::encrypt($inspection->id)) }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">edit</span>
                                <span>Edit</span>
                            </a>
                            @endif
                            <a href="{{ route('property.inspections.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Inspection Overview -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="border-bottom pb-2">Inspection Information</h5>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="text-muted small">Client</label>
                                <p class="mb-0">{{ $inspection->property->client->names ?? 'N/A' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="text-muted small">Property</label>
                                <p class="mb-0">{{ $inspection->property->reference_number }} - {{ $inspection->property->property_name }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="text-muted small">Zone</label>
                                <p class="mb-0">{{ $inspection->zone->zone_name ?? $inspection->property->zone->zone_name ?? 'N/A' }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="text-muted small">Status</label>
                                <p class="mb-0">
                                    @if($inspection->current_status_id == 1)
                                        <span class="badge bg-warning">Draft</span>
                                    @elseif($inspection->current_status_id == 2)
                                        <span class="badge bg-info">In Progress</span>
                                    @elseif($inspection->current_status_id == 5)
                                        <span class="badge bg-success">Completed</span>
                                    @else
                                        <span class="badge bg-secondary">Unknown</span>
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="text-muted small">Visited Date & Time</label>
                                <p class="mb-0">{{ \Carbon\Carbon::parse($inspection->visited_date_time)->format('d/m/Y H:i') }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="text-muted small">Left Date & Time</label>
                                <p class="mb-0">{{ \Carbon\Carbon::parse($inspection->left_date_time)->format('d/m/Y H:i') }}</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="text-muted small">Duration</label>
                                <p class="mb-0">{{ $inspection->duration }} minutes</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="text-muted small">Created By</label>
                                <p class="mb-0">{{ $inspection->createdBy->name ?? 'N/A' }}</p>
                            </div>
                        </div>

                        <!-- Units Inspected -->
                        <div class="row">
                            <div class="col-md-12">
                                <h5 class="border-bottom pb-2">Units Inspected ({{ $inspection->units->count() }})</h5>
                            </div>
                        </div>

                        @if($inspection->units->count() > 0)
                            @foreach($inspection->units as $index => $inspectionUnit)
                            <div class="card mb-3 border">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12 mb-2">
                                            <h6 class="mb-0">
                                                <span class="badge bg-primary">Unit {{ $index + 1 }}</span>
                                                {{ $inspectionUnit->unit->unit_number ?? 'N/A' }} - {{ $inspectionUnit->unit->unit_name ?? 'N/A' }}
                                            </h6>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="text-muted small">Unit Code</label>
                                            <p class="mb-0">{{ $inspectionUnit->unit->unit_code ?? 'N/A' }}</p>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="text-muted small">Previous Status</label>
                                            <p class="mb-0">
                                                @php
                                                    $prevStatus = match($inspectionUnit->previous_status_id) {
                                                        31 => 'Vacant',
                                                        32 => 'Occupied',
                                                        33 => 'Under Maintenance',
                                                        34 => 'Reserved',
                                                        default => 'N/A'
                                                    };
                                                @endphp
                                                <span class="badge bg-secondary">{{ $prevStatus }}</span>
                                            </p>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="text-muted small">New Status</label>
                                            <p class="mb-0">
                                                @php
                                                    $newStatus = match($inspectionUnit->unit_status_id) {
                                                        31 => ['label' => 'Vacant', 'class' => 'info'],
                                                        32 => ['label' => 'Occupied', 'class' => 'success'],
                                                        33 => ['label' => 'Under Maintenance', 'class' => 'warning'],
                                                        34 => ['label' => 'Reserved', 'class' => 'primary'],
                                                        default => ['label' => 'N/A', 'class' => 'secondary']
                                                    };
                                                @endphp
                                                <span class="badge bg-{{ $newStatus['class'] }}">{{ $newStatus['label'] }}</span>
                                            </p>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="text-muted small">Event Date</label>
                                            <p class="mb-0">{{ \Carbon\Carbon::parse($inspectionUnit->event_date)->format('d/m/Y') }}</p>
                                        </div>
                                        <div class="col-md-12 mb-2">
                                            <label class="text-muted small">Remarks</label>
                                            <p class="mb-0">{{ $inspectionUnit->remarks ?? 'N/A' }}</p>
                                        </div>

                                        <!-- Photos -->
                                        @if($inspectionUnit->pictures->count() > 0)
                                        <div class="col-md-12 mt-3">
                                            <label class="text-muted small">Photos ({{ $inspectionUnit->pictures->count() }})</label>
                                            <div class="row g-2 mt-1">
                                                @foreach($inspectionUnit->pictures as $picture)
                                                <div class="col-md-2">
                                                    <a href="{{ asset('storage/' . $picture->unit_photos) }}" target="_blank">
                                                        <img src="{{ asset('storage/' . $picture->unit_photos) }}" 
                                                             class="img-thumbnail" 
                                                             style="width: 100%; height: 120px; object-fit: cover;" 
                                                             alt="Unit Photo">
                                                    </a>
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        @else
                            <div class="col-md-12">
                                <div class="alert alert-info">No units were inspected.</div>
                            </div>
                        @endif
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
    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 20;
    }
</style>
@endsection
