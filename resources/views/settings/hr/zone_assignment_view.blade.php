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
                                                @permission('update_zone_assignments')
                                                <button class="btn btn-sm btn-outline-danger unassign-property-btn d-inline-flex align-items-center"
                                                    data-property-id="{{ $property->id }}"
                                                    data-property-name="{{ $property->property_name ?? $property->reference_number }}">
                                                    <span class="material-symbols-outlined" style="font-size:16px;">link_off</span>
                                                    <span class="ms-1">Unassign</span>
                                                </button>
                                                @endpermission
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
                columnDefs: [
                    { orderable: false, targets: [0, 3] },
                    { searchable: false, targets: [0, 3] }
                ]
            });
        }

        $(document).on('click', '.unassign-property-btn', function() {
            let $btn = $(this);
            let propertyId = $btn.data('property-id');
            let propertyName = $btn.data('property-name');

            if (!confirm('Unassign property "' + propertyName + '" from this assignment?')) {
                return;
            }

            $btn.prop('disabled', true);
            let $icon = $btn.find('.material-symbols-outlined');
            let origIcon = $icon.text();
            $icon.text('sync');

            $.ajax({
                url: "{{ url('/ajax/hr/zone/assignments') }}/{{ $assignment->id }}/unassign-property",
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    property_id: propertyId
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message || 'Property unassigned');

                        if (res.deleted) {
                            // Last property removed; assignment was deleted - go back to list
                            setTimeout(function() {
                                window.location.href = "{{ url('/hr/zone/assignments') }}";
                            }, 800);
                            return;
                        }

                        // Remove the row from the table
                        let $row = $btn.closest('tr');
                        if ($.fn.DataTable.isDataTable('#assigned-properties-table')) {
                            $('#assigned-properties-table').DataTable().row($row).remove().draw();
                        } else {
                            $row.remove();
                        }

                        $('#assignedPropertiesCount').text((res.remaining_count || 0) + ' total');

                        if ((res.remaining_count || 0) === 0) {
                            setTimeout(function() {
                                window.location.href = "{{ url('/hr/zone/assignments') }}";
                            }, 800);
                        }
                    } else {
                        $btn.prop('disabled', false);
                        $icon.text(origIcon);
                        toastr.error(res.error || 'Failed to unassign');
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false);
                    $icon.text(origIcon);
                    let msg = 'Failed to unassign property';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                        msg = Object.values(xhr.responseJSON.errors).join('<br>');
                    }
                    toastr.error(msg);
                }
            });
        });
    });
</script>
