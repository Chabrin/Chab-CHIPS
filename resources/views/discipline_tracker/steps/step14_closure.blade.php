<!-- Step 14: Close Case -->
<div class="tab-pane fade {{ $case->current_step == 14 ? 'show active' : '' }}" 
     id="step14" 
     role="tabpanel" 
     aria-labelledby="step14-tab">
    
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h5 class="mb-0">
                <span class="material-symbols-outlined" style="vertical-align:middle;">task_alt</span>
                Step 14: Close Case
            </h5>
            <div class="ms-auto">
                @if($case->current_step == 14 && $case->current_status_id != 42)
                    <span class="badge bg-primary">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Current Step
                    </span>
                @elseif($case->current_step == 14 && $case->current_status_id == 42)
                    <span class="badge bg-success"><span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Case Closed</span>
                @endif
                
            </div>
        </div>
        <div class="card-body">
            @if($case->closure)
                <!-- View Mode - Case Closed -->
                <div class="alert alert-success mb-4">
                    <div class="d-flex align-items-center">
                        <span class="material-symbols-outlined me-2" style="font-size:48px;">check_circle</span>
                        <div>
                            <h5 class="mb-0">Case Successfully Closed</h5>
                            <small>Closed on {{ \Carbon\Carbon::parse($case->closure->created_at)->format('d M Y, h:i A') }}</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Closure Reason</label>
                        <div>
                            <span class="badge bg-success fs-6">{{ $case->closure->closure_reason }}</span>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Closed By</label>
                        <div class="fw-bold">{{ $case->closure->closedBy->name ?? '-' }}</div>
                    </div>

                    @if($case->closure->lessons_learned)
                        <div class="col-12 mb-3">
                            <label class="form-label text-muted">Lessons Learned</label>
                            <div class="border rounded p-3 bg-primary text-white">{{ $case->closure->lessons_learned }}</div>
                        </div>
                    @endif

                    @if($case->closure->policy_recommendations)
                        <div class="col-12 mb-3">
                            <label class="form-label text-muted">Policy Recommendations</label>
                            <div class="border rounded p-3 bg-primary text-white">{{ $case->closure->policy_recommendations }}</div>
                        </div>
                    @endif

                    @if($case->closure->final_notes)
                        <div class="col-12 mb-3">
                            <label class="form-label text-muted">Final Notes</label>
                            <div class="border rounded p-3 bg-primary text-white">{{ $case->closure->final_notes }}</div>
                        </div>
                    @endif

                    <div class="col-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="mb-3">Case Summary</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <small class="text-muted">Case Reference</small>
                                        <div class="fw-bold text-black">{{ $case->case_number }}</div>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Total Duration</small>
                                        <div class="fw-bold text-black">
                                            {{ \Carbon\Carbon::parse($case->date_time)->diffInDays($case->closure->created_at) }} days
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Final Status</small>
                                        <div><span class="badge bg-dark">{{ ($case->current_status_id == 42) ? 'Closed' : 'Open' }}</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @elseif($case->current_step == 14)
                <!-- Edit Mode - Close the Case -->
                <div class="alert alert-info mb-3">
                    <span class="material-symbols-outlined" style="vertical-align:middle;">info</span>
                    You are about to close this disciplinary case. Please provide closure details and any recommendations for future prevention.
                </div>

                <form id="step14Form">
                    @csrf
                    <input type="hidden" name="case_id" value="{{ $case->id }}">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="closure_date" class="form-label">Closure Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="closure_date" name="closure_date" value="{{ date('Y-m-d') }}" required>
                        </div>
                
                        <div class="col-md-6 mb-3">
                            <label for="closure_reason" class="form-label">Closure Reason <span class="text-danger">*</span></label>
                            <select class="form-select" id="closure_reason" name="closure_reason" required>
                                <option value="">Select Closure Reason</option>
                                <option value="Resolved">Resolved - All actions completed</option>
                                <option value="Withdrawn">Withdrawn - Case withdrawn</option>
                                <option value="No Case to Answer">No Case to Answer</option>
                                <option value="Settled">Settled - Mutual agreement reached</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                
                        <div class="col-12 mb-3">
                            <label for="lessons_learned" class="form-label">Lessons Learned</label>
                            <textarea class="form-control" id="lessons_learned" name="lessons_learned" rows="3" 
                                      placeholder="What insights or lessons were gained from this case?"></textarea>
                            <small class="text-muted">Document key learnings for organizational improvement</small>
                        </div>
                
                        <div class="col-12 mb-3">
                            <label for="policy_recommendations" class="form-label">Policy Recommendations</label>
                            <textarea class="form-control" id="policy_recommendations" name="policy_recommendations" rows="3" 
                                      placeholder="Recommend any policy changes or improvements"></textarea>
                            <small class="text-muted">Suggest changes to prevent similar incidents</small>
                        </div>
                
                        <div class="col-12 mb-3">
                            <label for="closure_notes" class="form-label">Closure Notes</label>
                            <textarea class="form-control" id="closure_notes" name="closure_notes" rows="2" 
                                      placeholder="Any final remarks or observations"></textarea>
                        </div>
                
                        <div class="col-12">
                            <div class="card bg-warning bg-opacity-10 border-warning">
                                <div class="card-body">
                                    <h6 class="text-warning mb-2">
                                        <span class="material-symbols-outlined" style="vertical-align:middle;">warning</span>
                                        Important Notice
                                    </h6>
                                    <ul class="mb-0 small text-black list-group">
                                        <li class="list-group-item">Closing this case will mark it as complete and lock all editing</li>
                                        <li class="list-group-item">All documentation will be archived for compliance purposes</li>
                                        <li class="list-group-item">This action cannot be undone without administrative intervention</li>
                                        <li class="list-group-item">Ensure all required steps have been completed before closing</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-success btn-lg" id="closeCaseBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="closeCaseSpinner"></span>
                            <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">task_alt</span>
                            Close Case Permanently
                        </button>
                    </div>
                </form>
                
                <script>
                $(document).ready(function() {
                    // Set max closure date to today
                    var today = new Date().toISOString().split('T')[0];
                    $('#closure_date').attr('max', today);
                
                    $('#step14Form').on('submit', function(e) {
                        e.preventDefault();
                        
                        // Confirmation dialog
                        if (!confirm('Are you sure you want to close this case? This action cannot be undone.')) {
                            return false;
                        }
                        
                        var formData = new FormData(this);
                        var submitButton = $('#closeCaseBtn');
                        var spinner = $('#closeCaseSpinner');
                        
                        // Show spinner and disable button
                        spinner.removeClass('d-none');
                        submitButton.prop('disabled', true);
                        submitButton.css('opacity', '0.6');
                        
                        $.ajax({
                            url: '{{ route("discipline.tracker.close.case", $case->id) }}',
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function(response) {
                                if(response.success) {
                                    toastr.success(response.message);
                                    setTimeout(function() {
                                        window.location.href = '{{ route("discipline.tracker.index") }}';
                                    }, 2000);
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
                                    toastr.error('An error occurred while closing the case');
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
                    This step will be available after completing all previous steps.
                </div>
            @endif
        </div>
    </div>
</div>