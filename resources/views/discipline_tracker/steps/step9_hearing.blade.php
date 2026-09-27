<!-- Step 9: Schedule Hearing -->
<div class="tab-pane fade {{ $case->current_step == 9 ? 'show active' : '' }}" 
     id="step9" 
     role="tabpanel" 
     aria-labelledby="step9-tab">
    
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h5 class="mb-0">
                <span class="material-symbols-outlined" style="vertical-align:middle;">event</span>
                Step 9: Schedule Hearing
            </h5>
            <div class="ms-auto">
                @if($case->current_step > 9)
                    <span class="badge bg-success">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>
                        Completed
                    </span>
                @elseif($case->current_step == 9)
                    <span class="badge bg-primary">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Current Step
                    </span>
                    <button class="btn btn-sm btn-light-warning ms-2" onclick="skipStep(9)">
                        <span class="material-symbols-outlined text-warning" style="font-size:14px;">skip_next</span>
                    </button>
                @else
                    <span class="badge bg-secondary"><span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Pending</span>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if($case->hearings->first())
                <!-- View Mode -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Hearing Date & Time</label>
                        <div class="fw-bold">{{ $case->hearings->first()->hearing_date ? \Carbon\Carbon::parse($case->hearings->first()->hearing_date)->format('d M Y, h:i A') : '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Location</label>
                        <div class="fw-bold">{{ $case->hearings->first()->hearing_location ?? '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Hearing Type</label>
                        <div>
                            <span class="badge bg-info">{{ $case->hearings->first()->hearing_type }}</span>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Chairperson</label>
                        <div class="fw-bold">{{ $case->hearings->first()->chairperson->full_names ?? '-' }}</div>
                    </div>

                    @if($case->hearings->first()->panel_members)
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-muted">Panel Members</label>
                            <div>
                                @php
                                    $panelIds = json_decode($case->hearings->first()->panel_members, true);
                                    if(is_array($panelIds)) {
                                        foreach($panelIds as $userId) {
                                            $user = \App\Models\Staff::find($userId);
                                            if($user) {
                                                echo '<span class="badge badge-light-info me-1">' . $user->full_names . '</span>';
                                            }
                                        }
                                    }
                                @endphp
                            </div>
                        </div>
                    @endif

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Employee Attended</label>
                        <div>
                            @if($case->hearings->first()->employee_attended === true)
                                <span class="badge bg-success">Yes</span>
                            @elseif($case->hearings->first()->employee_attended === false)
                                <span class="badge bg-danger">No</span>
                            @else
                                <span class="badge bg-secondary">Not recorded</span>
                            @endif
                        </div>
                    </div>

                    @if($case->hearings->first()->employee_representative)
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Employee Representative</label>
                            <div class="fw-bold">{{ $case->hearings->first()->employee_representative }}</div>
                        </div>
                    @endif
                </div>
            @elseif($case->current_step == 9)
                <!-- Form Mode -->
                <form id="scheduleHearingForm">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="hearing_date" class="form-label">Hearing Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="hearing_date" id="hearing_date" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="hearing_location" class="form-label">Location</label>
                            <input type="text" class="form-control" name="hearing_location" id="hearing_location" placeholder="e.g., Conference Room A">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="hearing_type" class="form-label">Hearing Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="hearing_type" id="hearing_type" required>
                                <option value="">Select type</option>
                                <option value="Preliminary">Preliminary</option>
                                <option value="Full Hearing">Full Hearing</option>
                                <option value="Appeal">Appeal</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="chairperson_id" class="form-label">Chairperson</label>
                            <select class="form-select" name="chairperson_id" id="chairperson_id"></select>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="panel_members" class="form-label">Panel Members</label>
                            <select class="form-select" name="panel_members[]" id="panel_members" multiple></select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple members</small>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="employee_representative" class="form-label">Employee's Representative</label>
                            <input type="text" class="form-control" name="employee_representative" id="employee_representative" placeholder="Name of colleague/representative the employee is bringing (if any)">
                            <small class="text-muted">The employee has the right to bring a colleague or representative to the hearing — record who, if anyone, they've named.</small>
                        </div>

                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary" id="scheduleHearingBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="scheduleHearingSpinner"></span>
                                <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">event_available</span>
                                Schedule Hearing
                            </button>
                        </div>
                    </div>
                </form>

                <script>
                $(document).ready(function() {
                    // Staff IDs of everyone already assigned as investigator on this case —
                    // excluded from the chairperson/panel pickers so the same person can't
                    // investigate and then sit in judgment on the same case.
                    const investigatorIds = @json($case->investigators->pluck('investigator_id')->map(fn($id) => (string) $id)->values());

                    // Load users for chairperson and panel
                    $.ajax({
                        url: "{{ url('/ajax/hr/staff/list') }}",
                        type: 'GET',
                        success: function(response) {
                            let chairOptions = '<option value="">Select chairperson</option>';
                            let panelOptions = '';

                            if (response.staff) {
                                response.staff.forEach(function(staff) {
                                    if (investigatorIds.includes(String(staff.id))) {
                                        return; // skip the case's investigator(s)
                                    }
                                    // Use staff.id consistently (matches the investigator picker
                                    // and how chairperson/panel are looked up elsewhere) — this
                                    // used to fall back to staff.user_id, which put chairperson_id/
                                    // panel_members in a different ID space than investigator_id
                                    // and silently broke both the notification lookup and any
                                    // investigator/panel conflict check.
                                    chairOptions += `<option value="${staff.id}">${staff.full_names}</option>`;
                                    panelOptions += `<option value="${staff.id}">${staff.full_names}</option>`;
                                });
                            }
                            
                            $('#chairperson_id').html(chairOptions).select2({ placeholder: 'Select chairperson', allowClear: true });
                            $('#panel_members').html(panelOptions).select2({ placeholder: 'Select panel members' });
                        },
                        error: function(xhr) {
                            toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to load staff for hearing panel.');
                        }
                    });

                    $('#scheduleHearingForm').on('submit', function(e) {
                        e.preventDefault();
                        
                        var submitButton = $('#scheduleHearingBtn');
                        var spinner = $('#scheduleHearingSpinner');
                        
                        // Show spinner and disable button
                        spinner.removeClass('d-none');
                        submitButton.prop('disabled', true);
                        submitButton.css('opacity', '0.6');
                        
                        $.ajax({
                            url: "{{ route('discipline.tracker.schedule.hearing', $case->id) }}",
                            type: 'POST',
                            data: $(this).serialize(),
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
                                
                                toastr.error(xhr.responseJSON?.error || 'Failed to schedule hearing');
                            }
                        });
                    });
                });
                </script>
            @else
                <div class="alert alert-info">
                    <span class="material-symbols-outlined" style="vertical-align:middle;">lock</span>
                    This step will be available after completing the previous steps.
                </div>
            @endif
        </div>
    </div>
</div>