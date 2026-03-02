@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Staff Discipline Record</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="disciplineForm" class="btn btn-success btn-sm d-flex align-items-center" id="updateButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Update Record</span>
                            </button>
                            <a href="{{ route('discipline.tracker.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
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

                        <form action="{{ route('discipline.tracker.update', $record->id) }}" method="POST" id="disciplineForm" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label for="staff_id" class="form-label">Staff <span style="color:red">*</span></label>
                                <select class="form-select" name="staff_id" id="staff_id" required></select>
                            </div>
                            <div class="mb-3">
                                <label for="position_id" class="form-label">Position <span style="color:red">*</span></label>
                                <select class="form-select" name="position_id" id="staff_positions" required></select>
                            </div>
                            <div class="mb-3">
                                <label for="incident_date_time" class="form-label">Incident Date/Time <span style="color:red">*</span></label>
                                <input type="datetime-local" class="form-control" name="incident_date_time" id="incident_date_time" required value="{{ \Carbon\Carbon::parse($record->incident_date_time)->format('Y-m-d\TH:i') }}">
                            </div>
                            <div class="mb-3">
                                <label for="incident_id" class="form-label">Incident <span style="color:red">*</span></label>
                                <div class="input-group">
                                    <select class="form-select" name="incident_id" id="incident_id" required style="max-width:70%;"></select>
                                    <input type="text" class="form-control" id="new_incident" placeholder="Add new incident" style="max-width:30%;">
                                    <button type="button" class="btn btn-outline-primary" id="addIncidentBtn">Add</button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="action_taken_id" class="form-label">Action Taken <span style="color:red">*</span></label>
                                <div class="input-group">
                                    <select class="form-select" name="action_taken_id" id="action_taken_id" required style="max-width:70%;"></select>
                                    <input type="text" class="form-control" id="new_action_taken" placeholder="Add new action" style="max-width:30%;">
                                    <button type="button" class="btn btn-outline-primary" id="addActionTakenBtn">Add</button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="documents" class="form-label">Supporting Documents</label>
                                <input type="file" class="form-control" name="documents[]" id="documents" multiple>
                                @if($record->documentTrackers && count($record->documentTrackers))
                                    <div class="mt-2">
                                        <strong>Existing Documents:</strong>
                                        <ul>
                                            @foreach($record->documentTrackers as $doc)
                                                <li>
                                                    <a href="{{ asset('storage/' . $doc->params) }}" target="_blank">{{ $doc->documents }}</a>
                                                    <button type="button" class="btn btn-sm btn-danger ms-2 delete-document-btn"
                                                        data-doc-id="{{ $doc->id }}">Delete</button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="description">{{ $record->description }}</textarea>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
$(document).ready(function() {
    // Populate selects and set selected values
    $.ajax({
        url: "{{ url('/ajax/hr/staff/list') }}",
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            let $staff_id = $('#staff_id');
            $staff_id.empty();
            $staff_id.append($('<option>', {
                value: '',
                text: 'Select staff',
                disabled: true,
                selected: true
            }));
            if (response.staff && Array.isArray(response.staff)) {
                response.staff.forEach(function(staff) {
                    $staff_id.append($('<option>', {
                        value: staff.id,
                        text: staff.full_names,
                        selected: staff.id == "{{ $record->staff_id }}"
                    }));
                });
            }
            if ($staff_id.hasClass("select2-hidden-accessible")) {
                $staff_id.select2('destroy');
            }
            $staff_id.select2({
                placeholder: 'Select Staff',
                allowClear: true
            });
            $staff_id.val("{{ $record->staff_id }}").trigger('change');
        }
    });

    $.ajax({
        url: "{{ url('/ajax/payroll/elements/staff_positions/list') }}",
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            let $staff_positions = $('#staff_positions');
            $staff_positions.empty();
            $staff_positions.append($('<option>', {
                value: '',
                text: 'Select staff positions',
                disabled: true,
                selected: true
            }));
            if (response.staff_positions && Array.isArray(response.staff_positions)) {
                response.staff_positions.forEach(function(position) {
                    $staff_positions.append($('<option>', {
                        value: position.id,
                        text: position.position_name,
                        selected: position.id == "{{ $record->position_id }}"
                    }));
                });
            }
            if ($staff_positions.hasClass("select2-hidden-accessible")) {
                $staff_positions.select2('destroy');
            }
            $staff_positions.select2({
                placeholder: 'Select Position',
                allowClear: true
            });
            $staff_positions.val("{{ $record->position_id }}").trigger('change');
        }
    });

    $.ajax({
        url: "{{ url('/ajax/incidents/list') }}",
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            let $incident_id = $('#incident_id');
            $incident_id.empty();
            $incident_id.append($('<option>', {
                value: '',
                text: 'Select incident',
                disabled: true,
                selected: true
            }));
            if (Array.isArray(response)) {
                response.forEach(function(incident) {
                    $incident_id.append($('<option>', {
                        value: incident.id,
                        text: incident.name,
                        selected: incident.id == "{{ $record->incident_id }}"
                    }));
                });
            }
            if ($incident_id.hasClass("select2-hidden-accessible")) {
                $incident_id.select2('destroy');
            }
            $incident_id.select2({
                placeholder: 'Select Incident',
                allowClear: true
            });
            $incident_id.val("{{ $record->incident_id }}").trigger('change');
        }
    });

    $.ajax({
        url: "{{ url('/ajax/action-taken/list') }}",
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            let $action_taken_id = $('#action_taken_id');
            $action_taken_id.empty();
            $action_taken_id.append($('<option>', {
                value: '',
                text: 'Select action taken',
                disabled: true,
                selected: true
            }));
            if (Array.isArray(response)) {
                response.forEach(function(action) {
                    $action_taken_id.append($('<option>', {
                        value: action.id,
                        text: action.name,
                        selected: action.id == "{{ $record->action_taken_id }}"
                    }));
                });
            }
            if ($action_taken_id.hasClass("select2-hidden-accessible")) {
                $action_taken_id.select2('destroy');
            }
            $action_taken_id.select2({
                placeholder: 'Select Action Taken',
                allowClear: true
            });
            $action_taken_id.val("{{ $record->action_taken_id }}").trigger('change');
        }
    });

    // Add new incident
    $('#addIncidentBtn').on('click', function() {
        let newIncident = $('#new_incident').val().trim();
        if (!newIncident) return toastr.error('Enter incident name');
        $.ajax({
            url: "{{ url('/ajax/incidents/store') }}",
            type: 'POST',
            data: { name: newIncident, _token: '{{ csrf_token() }}' },
            success: function(res) {
                if (res.success && res.incident) {
                    let $incident_id = $('#incident_id');
                    $incident_id.append($('<option>', {
                        value: res.incident.id,
                        text: res.incident.name,
                        selected: true
                    }));
                    $incident_id.val(res.incident.id).trigger('change');
                    $('#new_incident').val('');
                    toastr.success('Incident added');
                } else {
                    toastr.error(res.error || 'Could not add incident');
                }
            },
            error: function(xhr) {
                toastr.error('Failed to add incident');
            }
        });
    });

    // Add new action taken
    $('#addActionTakenBtn').on('click', function() {
        let newAction = $('#new_action_taken').val().trim();
        if (!newAction) return toastr.error('Enter action name');
        $.ajax({
            url: "{{ url('/ajax/action-taken/store') }}",
            type: 'POST',
            data: { name: newAction, _token: '{{ csrf_token() }}' },
            success: function(res) {
                if (res.success && res.action) {
                    let $action_taken_id = $('#action_taken_id');
                    $action_taken_id.append($('<option>', {
                        value: res.action.id,
                        text: res.action.name,
                        selected: true
                    }));
                    $action_taken_id.val(res.action.id).trigger('change');
                    $('#new_action_taken').val('');
                    toastr.success('Action added');
                } else {
                    toastr.error(res.error || 'Could not add action');
                }
            },
            error: function(xhr) {
                toastr.error('Failed to add action');
            }
        });
    });

    $(document).on('click', '.delete-document-btn', function() {
        var docId = $(this).data('doc-id');
        var $btn = $(this);
        if (!confirm('Are you sure you want to delete this document?')) return;
        $.ajax({
            url: "{{ url('/ajax/discipline/document/delete') }}/" + docId,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
                if (res.success) {
                    toastr.success('Document deleted');
                    $btn.closest('li').remove();
                } else {
                    toastr.error(res.error || 'Failed to delete document');
                }
            },
            error: function(xhr) {
                toastr.error('Failed to delete document');
            }
        });
    });

    // Handle form submission spinner
    $('#disciplineForm').on('submit', function() {
        var submitButton = $('#updateButton');
        var spinner = $('#submitSpinner');
        spinner.removeClass('d-none');
        submitButton.prop('disabled', true);
        submitButton.css('opacity', '0.6');
    });
});
</script>
@endsection