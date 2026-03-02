<!-- Step 12: Assign Training -->
<div class="tab-pane fade {{ $case->current_step == 12 ? 'show active' : '' }}" 
     id="step12" 
     role="tabpanel" 
     aria-labelledby="step12-tab">
    
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h5 class="mb-0">
                <span class="material-symbols-outlined" style="vertical-align:middle;">school</span>
                Step 12: Assign Training
            </h5>
            <div class="ms-auto">
                @if($case->current_step > 12)
                    <span class="badge bg-success">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>
                        Completed
                    </span>
                @elseif($case->current_step == 12)
                    <span class="badge bg-primary">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Current Step
                    </span>
                    <button class="btn btn-sm btn-light-warning ms-2" onclick="skipStep(12)">
                        <span class="material-symbols-outlined text-warning" style="font-size:14px;">skip_next</span>
                    </button>
                @else
                    <span class="badge bg-secondary"><span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Pending</span>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if($case->trainingAssignments->first())
                <!-- View Mode -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Training Type</label>
                        <div class="fw-bold">{{ $case->trainingAssignments->first()->training_type ?? '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Training Provider</label>
                        <div class="fw-bold">{{ $case->trainingAssignments->first()->training_provider ?? '-' }}</div>
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label text-muted">Training Description</label>
                        <div class="border rounded p-3 bg-light">{{ $case->trainingAssignments->first()->training_description ?? '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Scheduled Date</label>
                        <div class="fw-bold">{{ $case->trainingAssignments->first()->scheduled_date ? \Carbon\Carbon::parse($case->trainingAssignments->first()->scheduled_date)->format('d M Y') : '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Completion Deadline</label>
                        <div class="fw-bold">{{ $case->trainingAssignments->first()->completion_deadline ? \Carbon\Carbon::parse($case->trainingAssignments->first()->completion_deadline)->format('d M Y') : '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Assigned By</label>
                        <div class="fw-bold">{{ $case->trainingAssignments->first()->assignedBy->name ?? '-' }}</div>
                    </div>

                    @if($case->trainingAssignments->first()->completion_status)
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Status</label>
                            <div>
                                <span class="badge {{ $case->trainingAssignments->first()->completion_status == 'Completed' ? 'bg-success' : 'bg-warning' }}">
                                    {{ $case->trainingAssignments->first()->completion_status }}
                                </span>
                            </div>
                        </div>
                    @endif

                    @if($case->trainingAssignments->first()->notes)
                        <div class="col-12 mb-3">
                            <label class="form-label text-muted">Notes</label>
                            <div class="border rounded p-3 bg-light">{{ $case->trainingAssignments->first()->notes }}</div>
                        </div>
                    @endif
                </div>
            @elseif($case->current_step == 12)
                <!-- Edit Mode -->
                <div class="alert alert-info mb-3">
                    <span class="material-symbols-outlined" style="vertical-align:middle;">info</span>
                    Assign remedial or development training to the employee as part of the corrective action plan. This step is optional.
                </div>

                <form id="step12Form">
                    @csrf
                    <input type="hidden" name="case_id" value="{{ $case->id }}">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="training_type" class="form-label">Training Type <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="training_type" name="training_type" 
                                   placeholder="e.g., Behavioral Training, Technical Skills" required maxlength="255">
                        </div>
                
                        <div class="col-md-6 mb-3">
                            <label for="training_provider" class="form-label">Training Provider</label>
                            <input type="text" class="form-control" id="training_provider" name="training_provider" 
                                   placeholder="e.g., Internal HR, External Consultant" maxlength="255">
                        </div>
                
                        <div class="col-12 mb-3">
                            <label for="training_description" class="form-label">Training Description</label>
                            <textarea class="form-control" id="training_description" name="training_description" rows="3" 
                                      placeholder="Describe the training program and expected outcomes"></textarea>
                        </div>
                
                        <div class="col-md-6 mb-3">
                            <label for="scheduled_date" class="form-label">Scheduled Date</label>
                            <input type="date" class="form-control" id="scheduled_date" name="scheduled_date">
                        </div>
                
                        <div class="col-md-6 mb-3">
                            <label for="completion_deadline" class="form-label">Completion Deadline</label>
                            <input type="date" class="form-control" id="completion_deadline" name="completion_deadline">
                        </div>
                    </div>
                
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary" id="assignTrainingBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="assignTrainingSpinner"></span>
                            <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">save</span>
                            Assign Training
                        </button>
                    </div>
                </form>
                
                <script>
                $(document).ready(function() {
                    // Set minimum dates
                    var today = new Date().toISOString().split('T')[0];
                    $('#scheduled_date').attr('min', today);
                    $('#completion_deadline').attr('min', today);
                
                    // Ensure completion deadline is after scheduled date
                    $('#scheduled_date').on('change', function() {
                        $('#completion_deadline').attr('min', $(this).val());
                    });
                
                    $('#step12Form').on('submit', function(e) {
                        e.preventDefault();
                        
                        var formData = new FormData(this);
                        var submitButton = $('#assignTrainingBtn');
                        var spinner = $('#assignTrainingSpinner');
                        
                        // Show spinner and disable button
                        spinner.removeClass('d-none');
                        submitButton.prop('disabled', true);
                        submitButton.css('opacity', '0.6');
                        
                        $.ajax({
                            url: '{{ route("discipline.tracker.assign.training", $case->id) }}',
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function(response) {
                                if(response.success) {
                                    toastr.success(response.message);
                                    window.reloadCasePage();
                                }
                            },
                            error: function(xhr) {
                                // Re-enable button on error
                                spinner.addClass('d-none');
                                submitButton.prop('disabled', false);
                                submitButton.css('opacity', '1');
                                
                                if(xhr.responseJSON?.errors) {
                                    $.each(xhr.responseJSON.errors, function(key, value) {
                                        toastr.error(value[0]);
                                    });
                                } else if(xhr.responseJSON?.error) {
                                    toastr.error(xhr.responseJSON.error);
                                } else {
                                    toastr.error('An error occurred while assigning training');
                                }
                            }
                        });
                    });
                });
                </script>
            @else
                <!-- Locked State -->
                <div class="alert alert-warning">
                    <span class="material-symbols-outlined" style="vertical-align:middle;">lock</span>
                    This step will be available after completing previous steps.
                </div>
            @endif
        </div>
    </div>
</div>