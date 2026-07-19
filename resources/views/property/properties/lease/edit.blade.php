@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Lease Agreement — {{ $lease->ref_number }}</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="leaseEditForm" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Save</span>
                            </button>
                            <a href="{{ route('property.leases.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> Please correct the following errors:
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        <div class="row mb-4">
                            <div class="col-12 mb-3">
                                <h6 class="text-muted border-bottom pb-2">Lease Details (not editable — use Tenant Movement to reassign)</h6>
                            </div>
                            <div class="col-md-3 mb-2">
                                <small class="text-muted d-block">Tenant</small>
                                <strong>{{ $lease->tenant->names ?? '-' }}</strong>
                            </div>
                            <div class="col-md-3 mb-2">
                                <small class="text-muted d-block">Property</small>
                                <strong>{{ $lease->property->property_name ?? '-' }}</strong>
                            </div>
                            <div class="col-md-3 mb-2">
                                <small class="text-muted d-block">Unit</small>
                                <strong>{{ $lease->unit->unit_code ?? $lease->unit->unit_number ?? '-' }}</strong>
                            </div>
                            <div class="col-md-3 mb-2">
                                <small class="text-muted d-block">Landlord</small>
                                <strong>{{ $lease->client->names ?? '-' }}</strong>
                            </div>
                        </div>

                        <form action="{{ route('property.leases.update', $lease->id) }}" method="POST" id="leaseEditForm">
                            @csrf
                            @method('PUT')

                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <h6 class="text-muted border-bottom pb-2">Editable Terms</h6>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="date_start" class="form-label">Start Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('date_start') is-invalid @enderror"
                                           id="date_start" name="date_start"
                                           value="{{ old('date_start', optional($lease->date_start)->format('Y-m-d')) }}" required>
                                    @error('date_start')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="years" class="form-label">Duration (Years) <span class="text-danger">*</span></label>
                                    <input type="number" step="1" min="1" max="10" class="form-control @error('years') is-invalid @enderror"
                                           id="years" name="years" value="{{ old('years', $lease->years) }}" required>
                                    <small class="text-muted">End date recalculates from start date + years on save. Current end date: {{ optional($lease->date_end)->format('d-m-Y') ?? '-' }}</small>
                                    @error('years')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="auto_renewal" class="form-label">Auto Renewal <span class="text-danger">*</span></label>
                                    <select class="form-select @error('auto_renewal') is-invalid @enderror" id="auto_renewal" name="auto_renewal" required>
                                        <option value="Yes" {{ old('auto_renewal', $lease->auto_renewal) == 'Yes' ? 'selected' : '' }}>Yes</option>
                                        <option value="No" {{ old('auto_renewal', $lease->auto_renewal) == 'No' ? 'selected' : '' }}>No</option>
                                    </select>
                                    @error('auto_renewal')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="escalation_rate" class="form-label">Escalation Rate (%) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control @error('escalation_rate') is-invalid @enderror"
                                           id="escalation_rate" name="escalation_rate" value="{{ old('escalation_rate', $lease->escalation_rate) }}" required>
                                    @error('escalation_rate')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="escalation_frequency" class="form-label">Escalation Frequency <span class="text-danger">*</span></label>
                                    <select class="form-select @error('escalation_frequency') is-invalid @enderror" id="escalation_frequency" name="escalation_frequency" required>
                                        @foreach(['Annually', 'Semi-Annually', 'Quarterly', 'Monthly'] as $freq)
                                        <option value="{{ $freq }}" {{ old('escalation_frequency', $lease->escalation_frequency) == $freq ? 'selected' : '' }}>{{ $freq }}</option>
                                        @endforeach
                                    </select>
                                    @error('escalation_frequency')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
