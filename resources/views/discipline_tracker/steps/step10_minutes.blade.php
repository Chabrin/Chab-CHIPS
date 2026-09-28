<!-- Step 10: Record Hearing Minutes -->
<div class="tab-pane fade {{ $case->current_step == 10 ? 'show active' : '' }}" 
     id="step10" 
     role="tabpanel" 
     aria-labelledby="step10-tab">
    
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h5 class="mb-0">
                <span class="material-symbols-outlined" style="vertical-align:middle;">description</span>
                Step 10: Record Hearing Minutes
            </h5>
            <div class="ms-auto">
                @if($case->current_step > 10)
                    <span class="badge bg-success">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>
                        Completed
                    </span>
                @elseif($case->current_step == 10)
                    <span class="badge bg-primary">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Current Step
                    </span>
                    <button class="btn btn-sm btn-light-warning ms-2" onclick="skipStep(10)">
                        <span class="material-symbols-outlined text-warning" style="font-size:14px;">skip_next</span>
                    </button>
                @else
                    <span class="badge bg-secondary"><span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Pending</span>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if($case->hearings->first() && $case->hearings->first()->minutes)
                <!-- View Mode -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Start Time</label>
                        <div class="fw-bold">{{ $case->hearings->first()->minutes->start_time }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">End Time</label>
                        <div class="fw-bold">{{ $case->hearings->first()->minutes->end_time ?? '-' }}</div>
                    </div>

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

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Minute Taker</label>
                        <div class="fw-bold">
                            @if($case->hearings->first()->minutes->minute_taker_id)
                                @php
                                    $minuteTaker = \App\Models\Staff::find($case->hearings->first()->minutes->minute_taker_id);
                                @endphp
                                {{ $minuteTaker->full_names ?? 'Unknown' }}
                            @else
                                -
                            @endif
                        </div>
                    </div>

                    @if($case->hearings->first()->minutes->attendees)
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-muted">Attendees</label>
                            <div>
                                @php
                                    $attendees = json_decode($case->hearings->first()->minutes->attendees, true);
                                    if(is_array($attendees)) {
                                        foreach($attendees as $attendee) {
                                            echo '<span class="badge badge-light-info me-1">' . $attendee . '</span>';
                                        }
                                    }
                                @endphp
                            </div>
                        </div>
                    @endif

                    <div class="col-md-12 mb-3">
                        <label class="form-label text-muted">Proceedings Summary</label>
                        <div class="p-3 bg-primary text-white rounded">{{ $case->hearings->first()->minutes->proceedings_summary }}</div>
                    </div>

                    @if($case->hearings->first()->minutes->employee_statement)
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-muted">Employee Statement</label>
                            <div class="p-3 bg-primary text-white rounded">{{ $case->hearings->first()->minutes->employee_statement }}</div>
                        </div>
                    @endif

                    @if($case->hearings->first()->minutes->panel_questions)
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-muted">Panel Questions</label>
                            <div class="p-3 bg-primary text-white rounded">{{ $case->hearings->first()->minutes->panel_questions }}</div>
                        </div>
                    @endif

                    @if($case->hearings->first()->minutes->panel_recommendation)
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-muted">Panel Recommendation</label>
                            <div class="p-3 bg-primary text-white rounded">{{ $case->hearings->first()->minutes->panel_recommendation }}</div>
                        </div>
                    @endif

                    @if($case->hearings->first()->documents && count($case->hearings->first()->documents) > 0)
                        <div class="col-md-12 mt-3">
                            <h6>Documents:</h6>
                            <ul class="list-group">
                                @foreach($case->hearings->first()->documents as $doc)
                                    <li class="list-group-item">
                                        <a href="{{ asset('storage/' . $doc->document_path) }}" target="_blank">{{ $doc->document_name }}</a>
                                        <span class="badge bg-info ms-2">{{ $doc->document_type }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="col-md-12 mt-3">
                        <hr>
                        <h6>Sign-off</h6>
                        @if($case->hearings->first()->minutes->approved_at)
                            <div class="alert alert-success">
                                <span class="material-symbols-outlined" style="vertical-align:middle;">verified</span>
                                Signed by {{ $case->hearings->first()->minutes->approvedBy->name ?? 'Unknown' }}
                                on {{ $case->hearings->first()->minutes->approved_at->format('d M Y, h:i A') }}
                                — "{{ $case->hearings->first()->minutes->signature_attestation }}"
                            </div>
                        @else
                            <div class="alert alert-warning mb-2">
                                <span class="material-symbols-outlined" style="vertical-align:middle;">pending</span>
                                These minutes have not been signed yet.
                            </div>
                            <form id="approveMinutesForm" class="row g-2">
                                @csrf
                                <div class="col-md-8">
                                    <input type="text" class="form-control" name="signature_attestation" id="signature_attestation" placeholder="Type your full name to attest these minutes are accurate" required>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-success w-100" id="approveMinutesBtn">
                                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">draw</span>
                                        Approve &amp; Sign
                                    </button>
                                </div>
                            </form>
                            <script>
                            $(document).ready(function() {
                                $('#approveMinutesForm').on('submit', function(e) {
                                    e.preventDefault();
                                    $.ajax({
                                        url: "{{ route('discipline.tracker.approve.minutes', $case->id) }}",
                                        type: 'POST',
                                        data: $(this).serialize(),
                                        success: function(response) {
                                            if (response.success) {
                                                toastr.success(response.message);
                                                window.reloadCasePage();
                                            }
                                        },
                                        error: function(xhr) {
                                            toastr.error(xhr.responseJSON?.error || 'Failed to approve minutes');
                                        }
                                    });
                                });
                            });
                            </script>
                        @endif
                    </div>
                </div>
            @elseif($case->current_step == 10)
                <!-- Form Mode -->
                @if($case->hearings->first())
                    <form id="recordHearingMinutesForm" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            @if($case->showCauseNotice)
                            <div class="col-md-12 mb-3">
                                <h6 class="text-muted">Reading of Allegations</h6>
                                <div class="p-3 bg-light rounded border">{{ $case->showCauseNotice->allegations }}</div>
                                <small class="text-muted">Reference only — restated from the Show Cause notice, not editable here.</small>
                            </div>
                            @endif

                            <div class="col-md-12"><h6 class="text-muted">Attendance & Representation</h6></div>

                            <div class="col-md-6 mb-3">
                                <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="start_time" id="start_time" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="end_time" class="form-label">End Time</label>
                                <input type="time" class="form-control" name="end_time" id="end_time">
                            </div>

                            <div class="col-md-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="employee_attended" id="employee_attended" value="1" checked>
                                    <label class="form-check-label" for="employee_attended">
                                        <strong>Employee Attended the Hearing</strong>
                                    </label>
                                </div>
                                <small class="text-muted">Uncheck if the employee did not attend</small>
                            </div>

                            @if($case->hearings->first()->employee_representative)
                            <div class="col-md-12 mb-3">
                                <label class="form-label text-muted">Employee's Representative</label>
                                <div class="fw-bold">{{ $case->hearings->first()->employee_representative }}</div>
                            </div>
                            @endif

                            <div class="col-md-12 mb-3">
                                <label for="attendees" class="form-label">Attendees</label>
                                <input type="text" class="form-control" name="attendees[]" id="attendees" placeholder="Enter attendee names, comma-separated">
                                <small class="text-muted">e.g., John Doe, Jane Smith, etc.</small>
                            </div>

                            <div class="col-md-12"><h6 class="text-muted">Employee's Statement</h6></div>

                            <div class="col-md-12 mb-3">
                                <textarea class="form-control" name="employee_statement" id="employee_statement" rows="4" placeholder="Employee's statement during hearing..."></textarea>
                            </div>

                            <div class="col-md-12"><h6 class="text-muted">Panel Questions & Deliberation</h6></div>

                            <div class="col-md-12 mb-3">
                                <label for="proceedings_summary" class="form-label">Proceedings Summary <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="proceedings_summary" id="proceedings_summary" rows="6" required placeholder="Summarize the hearing proceedings..."></textarea>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label for="panel_questions" class="form-label">Panel Questions & Responses</label>
                                <textarea class="form-control" name="panel_questions" id="panel_questions" rows="4" placeholder="Questions asked and responses..."></textarea>
                            </div>

                            <div class="col-md-12"><h6 class="text-muted">Panel Recommendation</h6></div>

                            <div class="col-md-12 mb-3">
                                <textarea class="form-control" name="panel_recommendation" id="panel_recommendation" rows="3" placeholder="The panel's recommendation to be carried forward to the final outcome..."></textarea>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="minute_taker_id" class="form-label">Minute Taker</label>
                                <select class="form-select" name="minute_taker_id" id="minute_taker_id"></select>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label for="documents" class="form-label">Documents (Minutes, Recording, etc.)</label>
                                <input type="file" class="form-control" name="documents[]" id="documents" multiple>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label for="signed_copy" class="form-label">Upload Signed Copy (if a hard copy was signed on paper)</label>
                                <input type="file" class="form-control" name="signed_copy" id="signed_copy">
                            </div>

                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary" id="recordMinutesBtn">
                                    <span class="spinner-border spinner-border-sm me-2 d-none" id="recordMinutesSpinner"></span>
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">save</span>
                                    Record Minutes
                                </button>
                            </div>
                        </div>
                    </form>

                    <script>
                    $(document).ready(function() {
                        // Load users for minute taker
                        $.ajax({
                            url: "{{ url('/ajax/hr/staff/list') }}",
                            type: 'GET',
                            success: function(response) {
                                let options = '<option value="">Select minute taker</option>';
                                if (response.staff) {
                                    response.staff.forEach(function(staff) {
                                        // staff.id, matching DisciplineHearingMinutes::minuteTaker() (belongsTo Staff)
                                        options += `<option value="${staff.id}">${staff.full_names}</option>`;
                                    });
                                }
                                $('#minute_taker_id').html(options).select2({ placeholder: 'Select minute taker', allowClear: true });
                            },
                            error: function(xhr) {
                                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to load minute taker options.');
                            }
                        });

                        $('#recordHearingMinutesForm').on('submit', function(e) {
                            e.preventDefault();
                            
                            let formData = new FormData(this);
                            let btn = $('#recordMinutesBtn');
                            let spinner = $('#recordMinutesSpinner');
                            
                            // Show loading state
                            spinner.removeClass('d-none');
                            btn.prop('disabled', true).css('opacity', '0.6');
                            
                            $.ajax({
                                url: "{{ route('discipline.tracker.record.minutes', $case->id) }}",
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
                                    toastr.error(xhr.responseJSON?.error || 'Failed to record minutes');
                                    // Re-enable button on error
                                    spinner.addClass('d-none');
                                    btn.prop('disabled', false).css('opacity', '1');
                                }
                            });
                        });
                    });
                    </script>
                @else
                    <div class="alert alert-warning">
                        <span class="material-symbols-outlined" style="vertical-align:middle;">warning</span>
                        No hearing has been scheduled yet. Please schedule a hearing in Step 9 first.
                    </div>
                @endif
            @else
                <div class="alert alert-info">
                    <span class="material-symbols-outlined" style="vertical-align:middle;">lock</span>
                    This step will be available after completing the previous steps.
                </div>
            @endif
        </div>
    </div>
</div>