@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Merge - {{ $merge->merge_reference }}</h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('property.merge.show', $merge->encrypted_id) }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-light-warning d-flex align-items-center">
                            <span class="material-symbols-outlined me-2" style="font-size:24px;">info</span>
                            <div>
                                <p class="mb-0">Property merges cannot be edited after creation. If you need to make changes, please <strong>reverse</strong> the current merge and create a new one.</p>
                            </div>
                        </div>

                        <!-- Summary of current merge -->
                        <div class="card border">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Current Merge Summary</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tr>
                                        <td class="fw-bold" style="width:30%;">Reference:</td>
                                        <td>{{ $merge->merge_reference }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Client:</td>
                                        <td>{{ $merge->client->names ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Merge Type:</td>
                                        <td>{{ ucfirst($merge->merge_type) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Target Property:</td>
                                        <td>{{ $merge->targetProperty->property_name ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Merged Name:</td>
                                        <td>{{ $merge->merged_property_name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Status:</td>
                                        <td>
                                            <span class="badge bg-{{ (int)$merge->current_status_id === 1 ? 'warning' : ((int)$merge->current_status_id === 2 ? 'success' : 'danger') }}">
                                                {{ $merge->status_label }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Items Merged:</td>
                                        <td><span class="badge bg-info">{{ $merge->items->count() }}</span></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="mt-3 d-flex gap-2">
                            @if((int)$merge->current_status_id === 2)
                            <a href="{{ route('property.merge.show', $merge->encrypted_id) }}" class="btn btn-danger btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">undo</span>
                                Go to Reverse
                            </a>
                            @endif
                            <a href="{{ route('property.merge.create') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">add_circle</span>
                                Create New Merge
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection