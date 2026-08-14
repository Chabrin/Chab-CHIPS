@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Move-In Inspection &mdash; {{ $inspection->tenant->names ?? 'Unknown Tenant' }}</h5>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('move.in.inspections.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        @endif

                        <div class="row g-3">
                            <div class="col-lg-6">
                                <div class="card h-100">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0" style="font-size:0.9rem;">Inspection Details</h6>
                                    </div>
                                    <div class="card-body py-2">
                                        <div class="row g-1">
                                            <div class="col-5"><small class="text-muted">Tenant:</small></div>
                                            <div class="col-7"><small><strong>{{ $inspection->tenant->names ?? '-' }}</strong></small></div>
                                            <div class="col-5"><small class="text-muted">Property:</small></div>
                                            <div class="col-7"><small><strong>{{ $inspection->property->property_name ?? '-' }}</strong></small></div>
                                            <div class="col-5"><small class="text-muted">Unit:</small></div>
                                            <div class="col-7"><small><strong>{{ $inspection->unit->unit_name ?? $inspection->unit->unit_number ?? '-' }}</strong></small></div>
                                            <div class="col-5"><small class="text-muted">Inspection Date:</small></div>
                                            <div class="col-7"><small><strong>{{ $inspection->inspection_date ? $inspection->inspection_date->format('d-m-Y') : '-' }}</strong></small></div>
                                            <div class="col-5"><small class="text-muted">Inspector:</small></div>
                                            <div class="col-7"><small><strong>{{ $inspection->inspector_name ?? '-' }}</strong></small></div>
                                            <div class="col-5"><small class="text-muted">Conducted By:</small></div>
                                            <div class="col-7"><small><strong>{{ $inspection->conductedBy->name ?? '-' }}</strong></small></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="card h-100">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0" style="font-size:0.9rem;">Condition Ratings</h6>
                                    </div>
                                    <div class="card-body py-2">
                                        @php
                                            $conditionColors = ['Excellent' => 'success', 'Good' => 'success', 'Fair' => 'warning', 'Poor' => 'danger'];
                                            $conditions = [
                                                'Overall' => $inspection->overall_condition,
                                                'Walls' => $inspection->walls_condition,
                                                'Floors' => $inspection->floors_condition,
                                                'Ceiling' => $inspection->ceiling_condition,
                                                'Doors' => $inspection->doors_condition,
                                                'Windows' => $inspection->windows_condition,
                                                'Plumbing' => $inspection->plumbing_condition,
                                                'Electrical' => $inspection->electrical_condition,
                                            ];
                                        @endphp
                                        <div class="row g-1">
                                            @foreach($conditions as $label => $value)
                                            <div class="col-6"><small class="text-muted">{{ $label }}:</small></div>
                                            <div class="col-6 mb-1">
                                                @if($value)
                                                <span class="badge badge-light-{{ $conditionColors[$value] ?? 'secondary' }}">{{ $value }}</span>
                                                @else
                                                <span class="text-muted">-</span>
                                                @endif
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if($inspection->notes)
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0" style="font-size:0.9rem;">Notes</h6>
                                    </div>
                                    <div class="card-body py-2">
                                        <p class="mb-0">{{ $inspection->notes }}</p>
                                    </div>
                                </div>
                            </div>
                            @endif

                            @if(!empty($inspection->photo_paths))
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0" style="font-size:0.9rem;">Photos</h6>
                                    </div>
                                    <div class="card-body py-2 d-flex flex-wrap gap-2">
                                        @foreach($inspection->photo_paths as $path)
                                        <a href="{{ Storage::url($path) }}" target="_blank">
                                            <img src="{{ Storage::url($path) }}" alt="Inspection photo" style="width:120px;height:120px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;">
                                        </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            @endif

                            @if(!empty($inspection->video_paths))
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0" style="font-size:0.9rem;">Videos</h6>
                                    </div>
                                    <div class="card-body py-2 d-flex flex-wrap gap-2">
                                        @foreach($inspection->video_paths as $path)
                                        <video src="{{ Storage::url($path) }}" controls style="width:220px;border-radius:6px;border:1px solid #dee2e6;"></video>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
