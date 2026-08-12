<!-- filepath: d:\otherProjects\Chips\chips_2025\resources\views\settings\hr\templates\create.blade.php -->
@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Create Communication Template</h5>
                        <div class="ms-auto">
                            <a href="{{ route('settings.hr.communication.templates') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to Templates</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="templateForm" enctype="multipart/form-data">
                            @csrf
                            <div class="row">
                                <!-- Left Column - Form Fields -->
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label">Template Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="templateName" name="template_name" required>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Template Type <span class="text-danger">*</span></label>
                                            <select class="form-select" id="templateType" name="template_type" required>
                                                <option value="">Select Type</option>
                                                <option value="general">General Communication</option>
                                                <option value="contract">Employee Contract</option>
                                                <option value="leave_application">Leave Application</option>
                                                <option value="contract_termination">Contract Termination</option>
                                                <optgroup label="Discipline Process">
                                                    <option value="discipline_incident_reported">Discipline: Incident Reported</option>
                                                    <option value="discipline_case_registered">Discipline: Case Registered</option>
                                                    <option value="discipline_investigator_assigned">Discipline: Investigator Assigned</option>
                                                    <option value="discipline_show_cause">Discipline: Show Cause Notice</option>
                                                    <option value="discipline_response_received">Discipline: Response Received</option>
                                                    <option value="discipline_hearing_scheduled">Discipline: Hearing Scheduled</option>
                                                    <option value="discipline_outcome">Discipline: Outcome Decision</option>
                                                    <option value="discipline_training_assigned">Discipline: Training Assigned</option>
                                                    <option value="discipline_case_closed">Discipline: Case Closed</option>
                                                </optgroup>
                                            </select>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Communication Method <span class="text-danger">*</span></label>
                                            <select class="form-select" id="communicationMethod" name="communication_method" required>
                                                <option value="">Select Method</option>
                                                <option value="email">Email</option>
                                                <option value="sms">SMS</option>
                                            </select>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3" id="subjectField">
                                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="subject" name="subject">
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Body <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="body" name="body" rows="10" required></textarea>
                                        <div class="invalid-feedback"></div>
                                        <small class="text-muted" id="smsCharCount" style="display:none;">
                                            <span id="charCount">0</span>/160 characters
                                        </small>
                                    </div>
                                    <div class="mb-3" id="fileField" style="display:none;">
                                        <label class="form-label">Attach File (optional)</label>
                                        <input type="file" class="form-control" id="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                        <small class="text-muted">Allowed: PDF, Word, Excel, Images (Max 10MB)</small>
                                        <div class="invalid-feedback"></div>
                                    </div>

                                    <!-- General Communication Fields -->
                                    <div class="mb-3" id="generalCommunicationFields" style="display:none;">
                                        <hr>
                                        <h6 class="mb-3">General Communication Settings</h6>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Recipient Type <span class="text-danger">*</span></label>
                                            <select class="form-select" id="recipientType" name="recipient_type">
                                                <option value="">Select Recipient Type</option>
                                                <option value="staff">Select Staff</option>
                                                <option value="position">Select Department/Position</option>
                                                <option value="everyone">Everyone</option>
                                            </select>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    
                                        <div class="mb-3 d-none" id="staffSelectDiv">
                                            <label class="form-label">Select Staff <span class="text-danger">*</span></label>
                                            <select class="form-select" id="staffIds" name="staff_ids[]" multiple></select>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    
                                        <div class="mb-3 d-none" id="positionSelectDiv">
                                            <label class="form-label">Select Department/Position <span class="text-danger">*</span></label>
                                            <select class="form-select" id="positionIds" name="position_ids[]" multiple></select>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Scheduled Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" id="scheduledDate" name="scheduled_date">
                                                <div class="invalid-feedback"></div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Scheduled Time <span class="text-danger">*</span></label>
                                                <input type="time" class="form-control" id="scheduledTime" name="scheduled_time">
                                                <div class="invalid-feedback"></div>
                                            </div>
                                        </div>
                                    
                                        <div class="mb-3" id="attachmentDiv" style="display:none;">
                                            <label class="form-label">Attach Document (optional)</label>
                                            <input type="file" class="form-control" id="attachment" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png">
                                            <small class="text-muted">Allowed: PDF, Word, Excel, Images (Max 10MB)</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right Column - Placeholders -->
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-header py-2">
                                            <h6 class="mb-0">Available Placeholders</h6>
                                            <small class="text-muted">Click to insert</small>
                                        </div>
                                        <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                                            <!-- Common Placeholders -->
                                            <div class="mb-3">
                                                <strong class="d-block mb-2" style="font-size: 0.9rem;">Common</strong>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-primary insert-placeholder" data-placeholder="{employee_name}">Employee Name</button>
                                                    <button type="button" class="btn btn-sm btn-outline-primary insert-placeholder" data-placeholder="{staff_number}">Staff Number</button>
                                                    <button type="button" class="btn btn-sm btn-outline-primary insert-placeholder" data-placeholder="{position}">Position</button>
                                                    <button type="button" class="btn btn-sm btn-outline-primary insert-placeholder" data-placeholder="{date}">Date</button>
                                                    <button type="button" class="btn btn-sm btn-outline-primary insert-placeholder" data-placeholder="{department}">Department</button>
                                                </div>
                                            </div>

                                            <!-- Contract Specific -->
                                            <div class="mb-3 placeholder-group" data-type="contract" style="display:none;">
                                                <strong class="d-block mb-2" style="font-size: 0.9rem;">Contract Specific</strong>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-success insert-placeholder" data-placeholder="{basic_salary}">Basic Salary</button>
                                                    <button type="button" class="btn btn-sm btn-outline-success insert-placeholder" data-placeholder="{contract_start}">Contract Start</button>
                                                    <button type="button" class="btn btn-sm btn-outline-success insert-placeholder" data-placeholder="{contract_end}">Contract End</button>
                                                    <button type="button" class="btn btn-sm btn-outline-success insert-placeholder" data-placeholder="{contract_duration}">Duration</button>
                                                </div>
                                            </div>

                                            <!-- Leave Application Specific -->
                                            <div class="mb-3 placeholder-group" data-type="leave_application" style="display:none;">
                                                <strong class="d-block mb-2" style="font-size: 0.9rem;">Leave Specific</strong>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-info insert-placeholder" data-placeholder="{leave_type}">Leave Type</button>
                                                    <button type="button" class="btn btn-sm btn-outline-info insert-placeholder" data-placeholder="{start_date}">Start Date</button>
                                                    <button type="button" class="btn btn-sm btn-outline-info insert-placeholder" data-placeholder="{end_date}">End Date</button>
                                                    <button type="button" class="btn btn-sm btn-outline-info insert-placeholder" data-placeholder="{days}">Days</button>
                                                    <button type="button" class="btn btn-sm btn-outline-info insert-placeholder" data-placeholder="{days_balance}">Days Balance</button>
                                                    <button type="button" class="btn btn-sm btn-outline-info insert-placeholder" data-placeholder="{entitled_days}">Entitled Days</button>
                                                </div>
                                            </div>

                                            <!-- Termination Specific -->
                                            <div class="mb-3 placeholder-group" data-type="contract_termination" style="display:none;">
                                                <strong class="d-block mb-2" style="font-size: 0.9rem;">Termination Specific</strong>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-danger insert-placeholder" data-placeholder="{severance_payment}">Severance Payment</button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger insert-placeholder" data-placeholder="{years_served}">Years Served</button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger insert-placeholder" data-placeholder="{termination_date}">Termination Date</button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger insert-placeholder" data-placeholder="{termination_reason}">Reason</button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger insert-placeholder" data-placeholder="{last_working_day}">Last Working Day</button>
                                                </div>
                                            </div>

                                            <!-- Disciplinary Specific -->
                                            <div class="mb-3 placeholder-group" data-type="discipline_incident_reported discipline_case_registered discipline_investigator_assigned discipline_show_cause discipline_response_received discipline_hearing_scheduled discipline_outcome discipline_training_assigned discipline_case_closed" style="display:none;">
                                                <strong class="d-block mb-2" style="font-size: 0.9rem;">Discipline Case Details</strong>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-warning insert-placeholder" data-placeholder="{case_number}">Case Number</button>
                                                    <button type="button" class="btn btn-sm btn-outline-warning insert-placeholder" data-placeholder="{employee_name}">Employee Name</button>
                                                    <button type="button" class="btn btn-sm btn-outline-warning insert-placeholder" data-placeholder="{staff_number}">Staff Number</button>
                                                    <button type="button" class="btn btn-sm btn-outline-warning insert-placeholder" data-placeholder="{position}">Position</button>
                                                    <button type="button" class="btn btn-sm btn-outline-warning insert-placeholder" data-placeholder="{department}">Department</button>
                                                    <button type="button" class="btn btn-sm btn-outline-warning insert-placeholder" data-placeholder="{severity_level}">Severity Level</button>
                                                    <button type="button" class="btn btn-sm btn-outline-warning insert-placeholder" data-placeholder="{current_step}">Current Step</button>
                                                    <button type="button" class="btn btn-sm btn-outline-warning insert-placeholder" data-placeholder="{date}">Date</button>
                                                    <button type="button" class="btn btn-sm btn-outline-warning insert-placeholder" data-placeholder="{time}">Time</button>
                                                </div>
                                            </div>

                                            <!-- Incident Report Specific -->
                                            <div class="mb-3 placeholder-group" data-type="discipline_incident_reported" style="display:none;">
                                                <strong class="d-block mb-2" style="font-size: 0.9rem;">Incident Details</strong>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-danger insert-placeholder" data-placeholder="{incident_date}">Incident Date</button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger insert-placeholder" data-placeholder="{incident_description}">Description</button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger insert-placeholder" data-placeholder="{location}">Location</button>
                                                </div>
                                            </div>

                                            <!-- Show Cause Specific -->
                                            <div class="mb-3 placeholder-group" data-type="discipline_show_cause" style="display:none;">
                                                <strong class="d-block mb-2" style="font-size: 0.9rem;">Show Cause Notice</strong>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-danger insert-placeholder" data-placeholder="{notice_number}">Notice Number</button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger insert-placeholder" data-placeholder="{allegations}">Allegations</button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger insert-placeholder" data-placeholder="{response_deadline}">Response Deadline</button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger insert-placeholder" data-placeholder="{potential_consequences}">Potential Consequences</button>
                                                </div>
                                            </div>

                                            <!-- Hearing Specific -->
                                            <div class="mb-3 placeholder-group" data-type="discipline_hearing_scheduled" style="display:none;">
                                                <strong class="d-block mb-2" style="font-size: 0.9rem;">Hearing Details</strong>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-info insert-placeholder" data-placeholder="{hearing_date}">Hearing Date</button>
                                                    <button type="button" class="btn btn-sm btn-outline-info insert-placeholder" data-placeholder="{hearing_time}">Hearing Time</button>
                                                    <button type="button" class="btn btn-sm btn-outline-info insert-placeholder" data-placeholder="{hearing_location}">Location</button>
                                                    <button type="button" class="btn btn-sm btn-outline-info insert-placeholder" data-placeholder="{chairperson}">Chairperson</button>
                                                    <button type="button" class="btn btn-sm btn-outline-info insert-placeholder" data-placeholder="{hearing_type}">Hearing Type</button>
                                                </div>
                                            </div>

                                            <!-- Outcome Specific -->
                                            <div class="mb-3 placeholder-group" data-type="discipline_outcome" style="display:none;">
                                                <strong class="d-block mb-2" style="font-size: 0.9rem;">Outcome Details</strong>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-success insert-placeholder" data-placeholder="{decision}">Decision</button>
                                                    <button type="button" class="btn btn-sm btn-outline-success insert-placeholder" data-placeholder="{findings}">Findings</button>
                                                    <button type="button" class="btn btn-sm btn-outline-success insert-placeholder" data-placeholder="{sanctions}">Sanctions</button>
                                                    <button type="button" class="btn btn-sm btn-outline-success insert-placeholder" data-placeholder="{suspension_days}">Suspension Days</button>
                                                    <button type="button" class="btn btn-sm btn-outline-success insert-placeholder" data-placeholder="{appeal_deadline}">Appeal Deadline</button>
                                                </div>
                                            </div>

                                            <!-- Training Assignment Specific -->
                                            <div class="mb-3 placeholder-group" data-type="discipline_training_assigned" style="display:none;">
                                                <strong class="d-block mb-2" style="font-size: 0.9rem;">Training Assignment</strong>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-primary insert-placeholder" data-placeholder="{training_course}">Training Course</button>
                                                    <button type="button" class="btn btn-sm btn-outline-primary insert-placeholder" data-placeholder="{training_provider}">Provider</button>
                                                    <button type="button" class="btn btn-sm btn-outline-primary insert-placeholder" data-placeholder="{scheduled_date}">Scheduled Date</button>
                                                    <button type="button" class="btn btn-sm btn-outline-primary insert-placeholder" data-placeholder="{completion_deadline}">Deadline</button>
                                                </div>
                                            </div>

                                            <!-- Case Closure Specific -->
                                            <div class="mb-3 placeholder-group" data-type="discipline_case_closed" style="display:none;">
                                                <strong class="d-block mb-2" style="font-size: 0.9rem;">Case Closure</strong>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary insert-placeholder" data-placeholder="{closure_reason}">Closure Reason</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary insert-placeholder" data-placeholder="{closure_date}">Closure Date</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary insert-placeholder" data-placeholder="{lessons_learned}">Lessons Learned</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary insert-placeholder" data-placeholder="{final_notes}">Final Notes</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-3">
                                <a href="{{ route('settings.hr.communication.templates') }}" class="btn btn-light d-flex align-items-center">
                                    <span class="material-symbols-outlined">close</span>
                                    <span class="ms-1">Cancel</span>
                                </a>
                                <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center">
                                    <span class="material-symbols-outlined">check_circle</span>
                                    <span class="ms-1">Save Template</span>
                                </button>
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
    let lastFocusedField = null;

    $('#subject, #body').on('focus', function() {
        lastFocusedField = this;
    });

    $('#communicationMethod').on('change', function() {
        let method = $(this).val();
        let templateType = $('#templateType').val();
        
        if (method === 'sms') {
            $('#subjectField').hide();
            $('#subject').removeAttr('required');
            $('#smsCharCount').show();
            $('#body').attr('maxlength', 160);
            $('#fileField').hide();
            $('#attachmentDiv').hide();
        } else if (method === 'email') {
            $('#subjectField').show();
            $('#subject').attr('required', true);
            $('#smsCharCount').hide();
            $('#body').removeAttr('maxlength');
            
            if (templateType && templateType !== 'general') {
                $('#fileField').show();
            } else {
                $('#fileField').hide();
            }
            
            if (templateType === 'general') {
                $('#attachmentDiv').show();
            }
        }
    });

    $('#body').on('input', function() {
        if ($('#communicationMethod').val() === 'sms') {
            let count = $(this).val().length;
            $('#charCount').text(count);
            if (count > 160) {
                $('#smsCharCount').addClass('text-danger');
            } else {
                $('#smsCharCount').removeClass('text-danger');
            }
        }
    });

    $('#templateType').on('change', function() {
        let selectedType = $(this).val();
        let communicationMethod = $('#communicationMethod').val();
        $('.placeholder-group').hide();
        
        if (selectedType === 'general') {
            $('#generalCommunicationFields').show();
            $('#fileField').hide();
            if (communicationMethod === 'email') {
                $('#attachmentDiv').show();
            }
            loadStaffAndPositions();
        } else {
            $('#generalCommunicationFields').hide();
            $('#attachmentDiv').hide();
            
            if (communicationMethod === 'email' && selectedType) {
                $('#fileField').show();
            } else {
                $('#fileField').hide();
            }
            
            if (selectedType) {
                // Show placeholder groups that match this template type
                $('.placeholder-group').each(function() {
                    let types = $(this).data('type').toString().split(' ');
                    if (types.includes(selectedType)) {
                        $(this).show();
                    }
                });
            }
        }
    });

    $('#recipientType').on('change', function() {
        if ($(this).val() === 'staff') {
            $('#staffSelectDiv').removeClass('d-none');
            $('#positionSelectDiv').addClass('d-none');
        } else if ($(this).val() === 'position') {
            $('#staffSelectDiv').addClass('d-none');
            $('#positionSelectDiv').removeClass('d-none');
        } else {
            $('#staffSelectDiv').addClass('d-none');
            $('#positionSelectDiv').addClass('d-none');
        }
    });

    function loadStaffAndPositions() {
        $.ajax({
            url: "{{ url('/ajax/hr/staff/list') }}",
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                let $staffIds = $('#staffIds');
                $staffIds.empty();
                if (response.staff && Array.isArray(response.staff)) {
                    response.staff.forEach(function(staff) {
                        $staffIds.append($('<option>', {
                            value: staff.id,
                            text: staff.full_names
                        }));
                    });
                }
                if ($staffIds.hasClass("select2-hidden-accessible")) {
                    $staffIds.select2('destroy');
                }
                $staffIds.select2({
                    placeholder: 'Select Staff',
                    allowClear: true,
                    width: '100%'
                });
            }
        });

        $.ajax({
            url: "{{ url('/ajax/payroll/elements/staff_positions/list') }}",
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                let $positionIds = $('#positionIds');
                $positionIds.empty();
                if (response.staff_positions && Array.isArray(response.staff_positions)) {
                    response.staff_positions.forEach(function(position) {
                        $positionIds.append($('<option>', {
                            value: position.id,
                            text: position.position_name
                        }));
                    });
                }
                if ($positionIds.hasClass("select2-hidden-accessible")) {
                    $positionIds.select2('destroy');
                }
                $positionIds.select2({
                    placeholder: 'Select Department/Position',
                    allowClear: true,
                    width: '100%'
                });
            }
        });
    }

    $(document).on('click', '.insert-placeholder', function(e) {
        e.preventDefault();
        let placeholder = $(this).data('placeholder');
        
        if (!lastFocusedField) {
            lastFocusedField = document.getElementById('body');
        }
        
        let field = $(lastFocusedField);
        let currentValue = field.val();
        let cursorPos = lastFocusedField.selectionStart;
        
        let newValue = currentValue.substring(0, cursorPos) + placeholder + currentValue.substring(cursorPos);
        field.val(newValue);
        
        let newCursorPos = cursorPos + placeholder.length;
        lastFocusedField.setSelectionRange(newCursorPos, newCursorPos);
        lastFocusedField.focus();
        
        if ($('#communicationMethod').val() === 'sms') {
            field.trigger('input');
        }
    });

    $('#templateForm').on('submit', function(e) {
        e.preventDefault();
        $('.is-invalid').removeClass('is-invalid');
        
        let formData = new FormData(this);
        
        if ($('#templateType').val() === 'general') {
            let generalConfig = {
                recipient_type: $('#recipientType').val(),
                staff_ids: $('#staffIds').val(),
                position_ids: $('#positionIds').val(),
                scheduled_date: $('#scheduledDate').val(),
                scheduled_time: $('#scheduledTime').val()
            };
            formData.append('general_config', JSON.stringify(generalConfig));
        }

        // Add loading state to button
        let $submitBtn = $(this).find('button[type="submit"]');
        let $icon = $submitBtn.find('.material-symbols-outlined');
        let $text = $submitBtn.find('span.ms-1');
        let originalText = $text.text();
        
        $submitBtn.prop('disabled', true);
        $icon.text('hourglass_empty').addClass('spin');
        $text.text('Saving...');

        $.ajax({
            url: "{{ url('/ajax/hr-settings/communication-templates/store') }}",
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                if (res.success) {
                    toastr.success('Template created successfully');
                    window.location.href = "{{ route('settings.hr.communication.templates') }}";
                } else {
                    if (res.errors) {
                        $.each(res.errors, function(key, value) {
                            $('#' + key.replace(/_/g, '')).addClass('is-invalid').siblings('.invalid-feedback').text(value[0]);
                        });
                    }
                    toastr.error(res.error || 'Failed to save template');

                     // Reset button
                    $submitBtn.prop('disabled', false);
                    $icon.text('check_circle').removeClass('spin');
                    $text.text(originalText);
                }
            },
            error: function(xhr) {
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    $.each(xhr.responseJSON.errors, function(key, value) {
                        $('#' + key.replace(/_/g, '')).addClass('is-invalid').siblings('.invalid-feedback').text(value[0]);
                    });
                }
                toastr.error('Failed to save template');
            }
        });
    });
});
</script>


@endsection