@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Zone Assignment Details</h5>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ url('/hr/zone/assignments') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Zone</label>
                                <div class="fw-bold">
                                    {{ $assignment->zone->zone_name ?? '-' }}
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Assigned As</label>
                                <div class="fw-bold">
                                    {{ $assignment->assignment_type->type_name ?? '-' }}
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Staff</label>
                                <div class="fw-bold">
                                    {{ $assignment->staff->full_names ?? '-' }}
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Status</label>
                                <div>
                                    <span class="badge {{ $assignment->current_status_id == 5 ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ $assignment->current_status_id == 5 ? 'Active' : 'Pending' }}
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Created By</label>
                                <div class="fw-bold">
                                    {{ $assignment->createdBy->name ?? '-' }}
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Date / Time</label>
                                <div class="fw-bold">
                                    {{ $assignment->date_time ? \Carbon\Carbon::parse($assignment->date_time)->format('d M Y, H:i') : '-' }}
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Assigned Properties</h6>
                            <span class="badge bg-primary text-white" id="assignedPropertiesCount">{{ $properties->count() }} total</span>
                        </div>

                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="assigned-properties-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Property Ref</th>
                                        <th>Property Name</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($properties as $index => $property)
                                    <tr data-property-id="{{ $property->id }}">
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $property->reference_number ?? '-' }}</td>
                                        <td>{{ $property->property_name ?? '-' }}</td>
                                        <td class="text-end">
                                            {{-- Unassign functionality removed per new UX requirements --}}
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">No properties assigned.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        if ($('#assigned-properties-table tbody tr').length > 1 || $('#assigned-properties-table tbody tr td').length > 1) {
            $('#assigned-properties-table').DataTable({
                paging: true,
                pageLength: 10,
                searching: true,
                info: true,
                lengthChange: true,
                columnDefs: [{
                        orderable: false,
                        targets: [0, 3]
                    },
                    {
                        searchable: false,
                        targets: [0, 3]
                    }
                ]
            });
        }

        // Unassign functionality removed per UX request.
    });
</script>