<!-- Step 13: Update Disciplinary History -->
<div class="tab-pane fade {{ $case->current_step == 13 ? 'show active' : '' }}" 
     id="step13" 
     role="tabpanel" 
     aria-labelledby="step13-tab">
    
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h5 class="mb-0">
                <span class="material-symbols-outlined" style="vertical-align:middle;">history</span>
                Step 13: Update Disciplinary History
            </h5>
            <div class="ms-auto">
                @if($case->current_step > 13)
                    <span class="badge bg-success">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>
                        Completed
                    </span>
                @elseif($case->current_step == 13)
                    <span class="badge bg-primary">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Current Step
                    </span>
                @else
                    <span class="badge bg-secondary"><span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Pending</span>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if($case->historyUpdates->first())
                <!-- View Mode -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Update Type</label>
                        <div>
                            <span class="badge bg-info">{{ $case->historyUpdates->first()->update_type }}</span>
                        </div>
                    </div>

                    @if($case->historyUpdates->first()->expiry_date)
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Expiry Date</label>
                            <div class="fw-bold">{{ \Carbon\Carbon::parse($case->historyUpdates->first()->expiry_date)->format('d M Y') }}</div>
                        </div>
                    @endif

                    <div class="col-12 mb-3">
                        <label class="form-label text-muted">History Details</label>
                        <div class="border rounded p-3 bg-primary text-white">{{ $case->historyUpdates->first()->details ?? '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Updated By</label>
                        <div class="fw-bold">{{ $case->historyUpdates->first()->updatedBy->name ?? '-' }}</div>
                    </div>

                    @if($case->historyUpdates->first()->notes)
                        <div class="col-12 mb-3">
                            <label class="form-label text-muted">Notes</label>
                            <div class="border rounded p-3 bg-light">{{ $case->historyUpdates->first()->notes }}</div>
                        </div>
                    @endif

                    <div class="col-12">
                        <div class="alert alert-success">
                            <span class="material-symbols-outlined" style="vertical-align:middle;">check_circle</span>
                            Employee's disciplinary history has been updated in the system.
                        </div>
                    </div>
                </div>
            @elseif($case->current_step == 13)
                <!-- Edit Mode -->
                <div class="alert alert-info mb-3">
                    <span class="material-symbols-outlined" style="vertical-align:middle;">info</span>
                    Update the employee's disciplinary record based on the final outcome. This ensures accurate tracking of warnings and sanctions.
                </div>

                <form id="step13Form">
                    @csrf
                    <input type="hidden" name="case_id" value="{{ $case->id }}">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="update_type" class="form-label">Update Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="update_type" name="update_type" required>
                                <option value="">Select Update Type</option>
                                <option value="Warning Added">Warning Added</option>
                                <option value="Sanction Recorded">Sanction Recorded</option>
                                <option value="Performance Review Flag">Performance Review Flag</option>
                                <option value="Promotion Block">Promotion Block</option>
                                <option value="Record Expunged">Record Expunged</option>
                            </select>
                        </div>
                
                        <div class="col-md-6 mb-3" id="expiry_date_wrapper">
                            <label for="expiry_date" class="form-label">Expiry Date</label>
                            <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                            <small class="text-muted">When this record will be removed from active history</small>
                        </div>
                
                        <div class="col-12 mb-3">
                            <label for="details" class="form-label">Details</label>
                            <textarea class="form-control" id="details" name="details" rows="4" 
                                      placeholder="Provide detailed information about this disciplinary record"></textarea>
                        </div>
                
                        <div class="col-12 mb-3">
                            <div class="alert alert-warning">
                                <span class="material-symbols-outlined" style="vertical-align:middle;">warning</span>
                                <strong>Important:</strong> This will permanently update the employee's disciplinary record. Ensure all details are accurate.
                            </div>
                        </div>
                    </div>
                
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary" id="updateHistoryBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="updateHistorySpinner"></span>
                            <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">save</span>
                            Update History
                        </button>
                    </div>
                </form>
                
                <script>
                $(document).ready(function() {
                    // Set minimum expiry date to today
                    var today = new Date().toISOString().split('T')[0];
                    $('#expiry_date').attr('min', today);
                
                    // Show/hide expiry date based on update type
                    $('#update_type').on('change', function() {
                        if ($(this).val() === 'Record Expunged') {
                            $('#expiry_date_wrapper').hide();
                            $('#expiry_date').val('');
                        } else {
                            $('#expiry_date_wrapper').show();
                        }
                    });
                
                    $('#step13Form').on('submit', function(e) {
                        e.preventDefault();
                        
                        var formData = new FormData(this);
                        var submitButton = $('#updateHistoryBtn');
                        var spinner = $('#updateHistorySpinner');
                        
                        // Show spinner and disable button
                        spinner.removeClass('d-none');
                        submitButton.prop('disabled', true);
                        submitButton.css('opacity', '0.6');
                        
                        $.ajax({
                            url: '{{ route("discipline.tracker.update.history", $case->id) }}',
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
                                    toastr.error('An error occurred while updating history');
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