<!-- Step 11: Save Final Outcome -->
<div class="tab-pane fade {{ $case->current_step == 11 ? 'show active' : '' }}" 
     id="step11" 
     role="tabpanel" 
     aria-labelledby="step11-tab">
    
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h5 class="mb-0">
                <span class="material-symbols-outlined" style="vertical-align:middle;">gavel</span>
                Step 11: Final Outcome
            </h5>
            <div class="ms-auto">
                @if($case->current_step > 11)
                    <span class="badge bg-success">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>
                        Completed
                    </span>
                @elseif($case->current_step == 11)
                    <span class="badge bg-primary">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Current Step
                    </span>
                @else
                    <span class="badge bg-secondary"><span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Pending</span>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if($case->outcome()->first())
                <!-- View Mode -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Decision Date</label>
                        <div class="fw-bold">{{ $case->outcome->first()->decision_date ? \Carbon\Carbon::parse($case->outcome->first()->decision_date)->format('d M Y') : '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Decision</label>
                        <div>
                            @php
                                $badgeClass = match($case->outcome->first()->decision) {
                                    'No Action' => 'badge-light-success',
                                    'Verbal Warning' => 'badge-light-info',
                                    'Written Warning' => 'badge-light-warning',
                                    'Final Warning' => 'badge-light-danger',
                                    'Suspension' => 'badge-light-dark',
                                    'Demotion' => 'badge-light-secondary',
                                    'Dismissal' => 'badge-light-danger',
                                    default => 'badge-light-primary'
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }} fs-6">{{ $case->outcome->first()->decision }}</span>
                        </div>
                    </div>

                    @if($case->outcome->first()->decision == 'Suspension' && $case->outcome->first()->suspension_days)
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Suspension Duration</label>
                            <div class="fw-bold">{{ $case->outcome->first()->suspension_days }} Days</div>
                        </div>
                    @endif

                    @if($case->outcome->first()->effective_date)
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Effective Date</label>
                            <div class="fw-bold">{{ \Carbon\Carbon::parse($case->outcome->first()->effective_date)->format('d M Y') }}</div>
                        </div>
                    @endif

                    <div class="col-12 mb-3">
                        <label class="form-label text-muted">Findings</label>
                        <div class="border rounded p-3 bg-primary text-white">{{ $case->outcome->first()->findings ?? '-' }}</div>
                    </div>

                    @if($case->outcome->first()->reasoning)
                        <div class="col-12 mb-3">
                            <label class="form-label text-muted">Reasoning</label>
                            <div class="border rounded p-3 bg-primary text-white">{{ $case->outcome->first()->reasoning }}</div>
                        </div>
                    @endif

                    <div class="col-12 mb-3">
                        <label class="form-label text-muted">Sanctions Applied</label>
                        <div class="border rounded p-3 bg-primary text-white">{{ $case->outcome->first()->sanctions ?? '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Appeal Allowed</label>
                        <div class="fw-bold">{{ $case->outcome->first()->appeal_allowed ? 'Yes' : 'No' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Appeal Deadline</label>
                        <div class="fw-bold">{{ $case->outcome->first()->appeal_deadline ? \Carbon\Carbon::parse($case->outcome->first()->appeal_deadline)->format('d M Y') : '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Decided By</label>
                        <div class="fw-bold">{{ $case->outcome->first()->decidedBy->full_names ?? '-' }}</div>
                    </div>

                    @if($case->outcome->first()->approved_by)
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Approved By</label>
                            <div class="fw-bold">{{ $case->outcome->first()->approvedBy->full_names }}</div>
                        </div>
                    @endif

                    @if($case->outcome->first()->remarks)
                        <div class="col-12 mb-3">
                            <label class="form-label text-muted">Remarks</label>
                            <div class="border rounded p-3 bg-light text-primary">{{ $case->outcome->first()->remarks }}</div>
                        </div>
                    @endif


                    @if($case->outcome->documents && $case->outcome->documents->count() > 0)
                        <div class="col-12 mb-3">
                            <label class="form-label text-muted">Outcome Documents</label>
                            <div class="list-group">
                                @foreach($case->outcome->first()->documents as $doc)
                                    <a href="{{ Storage::url($doc->document_path) }}" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center text-primary">
                                        <span class="material-symbols-outlined me-2">description</span>
                                        {{ $doc->document_name }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @elseif($case->current_step == 11)
                <!-- Edit Mode -->
                <form id="step11Form">
                    @csrf
                    <input type="hidden" name="case_id" value="{{ $case->id }}">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="decision_date" class="form-label">Decision Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="decision_date" name="decision_date" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="decision" class="form-label">Decision <span class="text-danger">*</span></label>
                            <select class="form-select" id="decision" name="decision" required>
                                <option value="">Select Decision</option>
                                <option value="No Action">No Action</option>
                                <option value="Verbal Warning">Verbal Warning</option>
                                <option value="Written Warning">Written Warning</option>
                                <option value="Final Warning">Final Warning</option>
                                <option value="Suspension">Suspension</option>
                                <option value="Demotion">Demotion</option>
                                <option value="Dismissal">Dismissal</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3" id="suspension_days_wrapper" style="display:none;">
                            <label for="suspension_days" class="form-label">Suspension Days <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="suspension_days" name="suspension_days" min="1" max="365">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="effective_date" class="form-label">Effective Date</label>
                            <input type="date" class="form-control" id="effective_date" name="effective_date">
                            <small class="text-muted">When the sanctions take effect</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="action_taken_id" class="form-label">Related Action Taken</label>
                            <select class="form-select" id="action_taken_id" name="action_taken_id">
                                <option value="">None</option>
                                <!-- Will be populated via AJAX if needed -->
                            </select>
                            <small class="text-muted">Link to existing action if applicable</small>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="findings" class="form-label">Findings <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="findings" name="findings" rows="4" 
                                      placeholder="Detail the findings of the investigation and hearing" required></textarea>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="reasoning" class="form-label">Reasoning</label>
                            <textarea class="form-control" id="reasoning" name="reasoning" rows="3" 
                                      placeholder="Explain the reasoning behind this decision"></textarea>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="sanctions" class="form-label">Sanctions Applied</label>
                            <textarea class="form-control" id="sanctions" name="sanctions" rows="3" 
                                      placeholder="Specify all sanctions being applied"></textarea>
                        </div>

                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="appeal_allowed" id="appeal_allowed" value="1" checked>
                                <label class="form-check-label" for="appeal_allowed">
                                    Allow Appeal
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="appeal_deadline" class="form-label">Appeal Deadline</label>
                            <input type="date" class="form-control" id="appeal_deadline" name="appeal_deadline">
                            <small class="text-muted">Employee has the right to appeal this decision</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="decided_by_id" class="form-label">Decided By <span class="text-danger">*</span></label>
                            <select class="form-select" id="decided_by_id" name="decided_by_id" required>
                                <option value="">Select Decision Maker</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="approved_by" class="form-label">Approved By</label>
                            <select class="form-select" id="approved_by" name="approved_by">
                                <option value="">Select Approving Authority</option>
                            </select>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="documents" class="form-label">Upload Outcome Documents</label>
                            <input type="file" class="form-control" id="documents" name="documents[]" multiple>
                            <small class="text-muted">Upload signed outcome letter, decision documentation (Max 10MB each)</small>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="2" 
                                      placeholder="Any additional remarks"></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary" id="saveOutcomeBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="saveOutcomeSpinner"></span>
                            <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">save</span>
                            Save Final Outcome
                        </button>
                    </div>
                </form>

                <script>
                $(document).ready(function() {
                    // Load staff for decided_by and approved_by dropdowns
                    $.ajax({
                        url: "{{ url('/ajax/hr/staff/list') }}",
                        type: 'GET',
                        success: function(response) {
                            let decidedByOptions = '<option value="">Select Decision Maker</option>';
                            let approvedByOptions = '<option value="">Select Approving Authority</option>';
                            
                            if (response.staff) {
                                response.staff.forEach(function(staff) {
                                    let option = `<option value="${staff.id}">${staff.full_names} - ${staff.position || 'N/A'}</option>`;
                                    decidedByOptions += option;
                                    approvedByOptions += option;
                                });
                            }
                            
                            $('#decided_by_id').html(decidedByOptions).select2({ width: '100%' });
                            $('#approved_by').html(approvedByOptions).select2({ width: '100%' });
                        }
                    });

                    // Show/hide suspension days field
                    $('#decision').on('change', function() {
                        if ($(this).val() === 'Suspension') {
                            $('#suspension_days_wrapper').show();
                            $('#suspension_days').attr('required', true);
                        } else {
                            $('#suspension_days_wrapper').hide();
                            $('#suspension_days').attr('required', false);
                            $('#suspension_days').val('');
                        }
                    });

                    // Toggle appeal deadline based on appeal allowed
                    $('#appeal_allowed').on('change', function() {
                        if ($(this).is(':checked')) {
                            $('#appeal_deadline').attr('disabled', false);
                        } else {
                            $('#appeal_deadline').attr('disabled', true).val('');
                        }
                    });

                    // Set minimum dates
                    var today = new Date().toISOString().split('T')[0];
                    $('#decision_date').attr('max', today);
                    $('#effective_date').attr('min', today);
                    
                    var tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    $('#appeal_deadline').attr('min', tomorrow.toISOString().split('T')[0]);

                    $('#step11Form').on('submit', function(e) {
                        e.preventDefault();
                        
                        var formData = new FormData(this);
                        var submitButton = $('#saveOutcomeBtn');
                        var spinner = $('#saveOutcomeSpinner');
                        
                        // Show spinner and disable button
                        spinner.removeClass('d-none');
                        submitButton.prop('disabled', true);
                        submitButton.css('opacity', '0.6');
                        
                        $.ajax({
                            url: "{{ route('discipline.tracker.save.outcome', $case->id) }}",
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
                                    toastr.error('An error occurred while saving outcome');
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