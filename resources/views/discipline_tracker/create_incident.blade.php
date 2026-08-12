@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Create Incident Report - Step 1</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="incidentForm" class="btn btn-primary btn-sm d-flex align-items-center" id="createButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Create Case</span>
                            </button>
                            <a href="{{ route('discipline.tracker.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to Cases</span>
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

                        @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        <form action="{{ route('discipline.tracker.store.incident') }}" method="POST" id="incidentForm" enctype="multipart/form-data">
                            @csrf
                            
                            <div class="row">
                                <!-- Employee Details Section -->
                                <div class="col-12 mb-4">
                                    <h5 class="border-bottom pb-2">
                                        <span class="material-symbols-outlined" style="vertical-align:middle;">person</span>
                                        Employee Information
                                    </h5>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="staff_id" class="form-label">Employee <span class="text-danger">*</span></label>
                                    <select class="form-select" name="staff_id" id="staff_id" required>
                                        <option value="" disabled selected>Select employee</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="position_id" class="form-label">Position <span class="text-danger">*</span></label>
                                    <select class="form-select" name="position_id" id="position_id" required>
                                        <option value="" disabled selected>Select position</option>
                                    </select>
                                </div>

                                <!-- Incident Details Section -->
                                <div class="col-12 mb-4 mt-3">
                                    <h5 class="border-bottom pb-2">
                                        <span class="material-symbols-outlined" style="vertical-align:middle;">report</span>
                                        Incident Details
                                    </h5>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="incident_date_time" class="form-label">Incident Date & Time <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" name="incident_date_time" id="incident_date_time" required value="{{ old('incident_date_time') }}">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" class="form-control" name="location" id="location" placeholder="Where did the incident occur?" value="{{ old('location') }}">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="incident_type_id" class="form-label">Incident Type</label>
                                    <div class="input-group">
                                        <select class="form-select" name="incident_type_id" id="incident_type_id">
                                            <option value="" selected>Select incident type</option>
                                        </select>
                                        <input type="text" class="form-control" id="new_incident_type" placeholder="Add new type" style="max-width: 200px;">
                                        <button type="button" class="btn btn-outline-primary" id="addIncidentTypeBtn">
                                            <span class="material-symbols-outlined" style="font-size:18px;">add</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="severity_level" class="form-label">Severity Level <span class="text-danger">*</span></label>
                                    <select class="form-select" name="severity_level" id="severity_level" required>
                                        <option value="" disabled selected>Select severity</option>
                                        <option value="Minor">Minor</option>
                                        <option value="Moderate">Moderate</option>
                                        <option value="Serious">Serious</option>
                                        <option value="Gross Misconduct">Gross Misconduct</option>
                                    </select>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="incident_description" class="form-label">Incident Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="incident_description" id="incident_description" rows="4" required placeholder="Provide detailed description of the incident...">{{ old('incident_description') }}</textarea>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="immediate_action_taken" class="form-label">Immediate Action Taken</label>
                                    <textarea class="form-control" name="immediate_action_taken" id="immediate_action_taken" rows="3" placeholder="What immediate actions were taken (if any)?">{{ old('immediate_action_taken') }}</textarea>
                                </div>

                                <!-- Reporter & Additional Info Section -->
                                <div class="col-12 mb-4 mt-3">
                                    <h5 class="border-bottom pb-2">
                                        <span class="material-symbols-outlined" style="vertical-align:middle;">person_add</span>
                                        Reporter & Additional Information
                                    </h5>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="reported_by" class="form-label">Reported By <span class="text-danger">*</span></label>
                                    <select class="form-select" name="reported_by" id="reported_by" required>
                                        <option value="" disabled selected>Select reporter</option>
                                    </select>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="witness_present" class="form-label">Witness Present?</label>
                                    <select class="form-select" name="witness_present" id="witness_present">
                                        <option value="0" selected>No</option>
                                        <option value="1">Yes</option>
                                    </select>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="police_involved" class="form-label">Police Involved?</label>
                                    <select class="form-select" name="police_involved" id="police_involved">
                                        <option value="0" selected>No</option>
                                        <option value="1">Yes</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="is_anonymous" class="form-label">Anonymous Report?</label>
                                    <select class="form-select" name="is_anonymous" id="is_anonymous">
                                        <option value="0" selected>No</option>
                                        <option value="1">Yes</option>
                                    </select>
                                    <small class="text-muted">If yes, reporter identity will be protected</small>
                                </div>

                                <!-- Documents Section -->
                                <div class="col-12 mb-4 mt-3">
                                    <h5 class="border-bottom pb-2">
                                        <span class="material-symbols-outlined" style="vertical-align:middle;">attach_file</span>
                                        Supporting Documents
                                    </h5>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="documents" class="form-label">Upload Documents</label>
                                    <input type="file" class="form-control" name="documents[]" id="documents" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xlsx,.xls">
                                    <small class="text-muted">Max file size: 10MB per file. Allowed: PDF, Word, Excel, Images</small>
                                </div>

                                <div class="col-12 mt-4">
                                    <div class="alert alert-info">
                                        <span class="material-symbols-outlined" style="vertical-align:middle;">info</span>
                                        <strong>Note:</strong> Creating this incident report will initiate a new discipline case. You will be redirected to the case details page to continue with the next steps.
                                    </div>
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
    
    // Store staff and positions data for auto-selection
    let staffData = [];
    let positionsData = [];
    
    // Load Staff List
    $.ajax({
        url: "{{ url('/ajax/hr/staff/list') }}",
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            let $staff_id = $('#staff_id');
            $staff_id.empty();
            $staff_id.append($('<option>', {
                value: '',
                text: 'Select employee',
                disabled: true,
                selected: true
            }));
            
            if (response.staff && Array.isArray(response.staff)) {
                staffData = response.staff; // Store for later use
                response.staff.forEach(function(staff) {
                    $staff_id.append($('<option>', {
                        value: staff.id,
                        text: staff.full_names + (staff.staff_number ? ' (' + staff.staff_number + ')' : ''),
                        'data-position': staff.position // Store position name in data attribute
                    }));
                });
            }

            // Initialize Select2
            if ($staff_id.hasClass("select2-hidden-accessible")) {
                $staff_id.select2('destroy');
            }
            
            $staff_id.select2({
                placeholder: 'Search for employee...',
                allowClear: true
            });
        },
        error: function() {
            toastr.error('Failed to load staff list.');
        }
    });

    // Load Positions List
    $.ajax({
        url: "{{ url('/ajax/payroll/elements/staff_positions/list') }}",
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            let $position_id = $('#position_id');
            $position_id.empty();
            $position_id.append($('<option>', {
                value: '',
                text: 'Select position',
                disabled: true,
                selected: true
            }));
            
            if (response.staff_positions && Array.isArray(response.staff_positions)) {
                positionsData = response.staff_positions; // Store for later use
                response.staff_positions.forEach(function(position) {
                    $position_id.append($('<option>', {
                        value: position.id,
                        text: position.position_name
                    }));
                });
            }

            if ($position_id.hasClass("select2-hidden-accessible")) {
                $position_id.select2('destroy');
            }
            
            $position_id.select2({
                placeholder: 'Select position',
                allowClear: true
            });
        },
        error: function() {
            toastr.error('Failed to load positions.');
        }
    });

    // Load Incident Types
    $.ajax({
        url: "{{ url('/ajax/incidents/list') }}",
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            let $incident_type_id = $('#incident_type_id');
            $incident_type_id.empty();
            $incident_type_id.append($('<option>', {
                value: '',
                text: 'Select incident type',
                selected: true
            }));
            
            if (Array.isArray(response)) {
                response.forEach(function(incident) {
                    $incident_type_id.append($('<option>', {
                        value: incident.id,
                        text: incident.name
                    }));
                });
            }

            if ($incident_type_id.hasClass("select2-hidden-accessible")) {
                $incident_type_id.select2('destroy');
            }
            
            $incident_type_id.select2({
                placeholder: 'Select or add incident type',
                allowClear: true
            });
        },
        error: function() {
            toastr.error('Failed to load incident types.');
        }
    });

    // Load Users for Reported By
    $.ajax({
        url: "{{ url('/ajax/hr/staff/list') }}", // Or create a users endpoint
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            let $reported_by = $('#reported_by');
            $reported_by.empty();
            $reported_by.append($('<option>', {
                value: '',
                text: 'Select reporter',
                disabled: true,
                selected: true
            }));
            
            if (response.staff && Array.isArray(response.staff)) {
                response.staff.forEach(function(staff) {
                    // Set current user as default
                    let isCurrentUser = staff.id == {{ Auth::id() }};
                    $reported_by.append($('<option>', {
                        value: staff.user_id || staff.id, // Adjust based on your user relationship
                        text: staff.full_names,
                        selected: isCurrentUser
                    }));
                });
            }

            if ($reported_by.hasClass("select2-hidden-accessible")) {
                $reported_by.select2('destroy');
            }
            
            $reported_by.select2({
                placeholder: 'Select reporter',
                allowClear: true
            });
        },
        error: function() {
            toastr.error('Failed to load users.');
        }
    });

    // Add New Incident Type
    $('#addIncidentTypeBtn').on('click', function() {
        let newIncidentType = $('#new_incident_type').val().trim();
        if (!newIncidentType) {
            return toastr.error('Please enter an incident type name');
        }
        
        $.ajax({
            url: "{{ url('/ajax/incidents/store') }}",
            type: 'POST',
            data: { 
                name: newIncidentType, 
                _token: '{{ csrf_token() }}' 
            },
            success: function(res) {
                if (res.success && res.incident) {
                    let $incident_type_id = $('#incident_type_id');
                    $incident_type_id.append($('<option>', {
                        value: res.incident.id,
                        text: res.incident.name,
                        selected: true
                    }));
                    $incident_type_id.val(res.incident.id).trigger('change');
                    $('#new_incident_type').val('');
                    toastr.success('Incident type added successfully');
                } else {
                    toastr.error(res.error || 'Could not add incident type');
                }
            },
            error: function(xhr) {
                let errorMsg = 'Failed to add incident type';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                toastr.error(errorMsg);
            }
        });
    });

    // Form Submission Handler
    $('#incidentForm').on('submit', function() {
        var submitButton = $('#createButton');
        var spinner = $('#submitSpinner');

        // Show spinner and disable button
        spinner.removeClass('d-none');
        submitButton.prop('disabled', true);
        submitButton.css('opacity', '0.6');
    });

    // Auto-populate position when staff is selected
    $('#staff_id').on('change', function() {
        let staffId = $(this).val();
        if (!staffId) {
            $('#position_id').val('').trigger('change');
            return;
        }
        
        // Get the staff member's position name from the data attribute
        let selectedOption = $(this).find('option:selected');
        let staffPositionName = selectedOption.data('position');
        
        if (staffPositionName && positionsData.length > 0) {
            // Find matching position by name
            let matchingPosition = positionsData.find(function(pos) {
                return pos.position_name === staffPositionName;
            });
            
            if (matchingPosition) {
                $('#position_id').val(matchingPosition.id).trigger('change');
            } else {
                // If no exact match, clear the selection
                $('#position_id').val('').trigger('change');
            }
        }
    });
});
</script>
@endsection