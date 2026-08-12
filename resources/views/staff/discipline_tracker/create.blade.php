@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Staff Discipline Records</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="disciplineForm" class="btn btn-primary btn-sm d-flex align-items-center" id="createButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Save Discipline Record</span>
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

                        <form action="{{ route('discipline.tracker.store') }}" method="POST" id="disciplineForm" enctype="multipart/form-data">
                            @csrf
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="staff_id" class="form-label">Staff <span style="color:red">*</span></label>
                                    <select class="form-select" name="staff_id" id="staff_id" required></select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="position_id" class="form-label">Position <span style="color:red">*</span></label>
                                    <select class="form-select" name="position_id" id="staff_positions" required></select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="incident_date_time" class="form-label">Incident Date/Time <span style="color:red">*</span></label>
                                    <input type="datetime-local" class="form-control" name="incident_date_time" id="incident_date_time" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="documents" class="form-label">Supporting Documents</label>
                                    <input type="file" class="form-control" name="documents[]" id="documents" multiple>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="incident_id" class="form-label">Incident <span style="color:red">*</span></label>
                                    <div class="input-group">
                                        <select class="form-select" name="incident_id" id="incident_id" required ></select>
                                        <input type="text" class="form-control" id="new_incident" placeholder="Add new incident" >
                                        <button type="button" class="btn btn-outline-primary" id="addIncidentBtn">Add</button>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="action_taken_id" class="form-label">Action Taken <span style="color:red">*</span></label>
                                    <div class="input-group">
                                        <select class="form-select" name="action_taken_id" id="action_taken_id" required ></select>
                                        <input type="text" class="form-control" id="new_action_taken" placeholder="Add new action" >
                                        <button type="button" class="btn btn-outline-primary" id="addActionTakenBtn">Add</button>
                                    </div>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" name="description" id="description"></textarea>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {

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
                            text: staff.full_names
                        }));
                    });
                }

                // Check if Select2 is already initialized and destroy it
                if ($staff_id.hasClass("select2-hidden-accessible")) {
                    $staff_id.select2('destroy');
                }
                
                // Initialize Select2
                $staff_id.select2({
                    placeholder: 'Select Staff',
                    allowClear: true
                });
            },
            error: function() {
                toastr.error('Failed to load staff.');
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
                            text: position.position_name
                        }));
                    });
                }

                // Check if Select2 is already initialized and destroy it
                if ($staff_positions.hasClass("select2-hidden-accessible")) {
                    $staff_positions.select2('destroy');
                }
                
                // Initialize Select2
                $staff_positions.select2({
                    placeholder: 'Select Position',
                    allowClear: true
                });
            },
            error: function() {
                toastr.error('Failed to load staff positions.');
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
                // response is an array of {id, name}
                if (Array.isArray(response)) {
                    response.forEach(function(incident) {
                        $incident_id.append($('<option>', {
                            value: incident.id,
                            text: incident.name
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
            },
            error: function() {
                toastr.error('Failed to load incidents.');
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
                // response is an array of {id, name}
                if (Array.isArray(response)) {
                    response.forEach(function(action) {
                        $action_taken_id.append($('<option>', {
                            value: action.id,
                            text: action.name
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
            },
            error: function() {
                toastr.error('Failed to load actions.');
            }
        });

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

        // Handle form submission
        $('#disciplineForm').on('submit', function() {
            var submitButton = $('#createButton');
            var spinner = $('#submitSpinner');

            // Show spinner and disable button
            spinner.removeClass('d-none');
            submitButton.prop('disabled', true);
            submitButton.css('opacity', '0.6');
        });

    });
</script>
@endsection