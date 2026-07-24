<!-- Step 8: Employee Submits Response -->
<div class="tab-pane fade {{ $case->current_step == 8 ? 'show active' : '' }}" 
     id="step8" 
     role="tabpanel" 
     aria-labelledby="step8-tab">
    
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h5 class="mb-0">
                <span class="material-symbols-outlined" style="vertical-align:middle;">reply</span>
                Step 8: Employee Submits Response
            </h5>
            <div class="ms-auto">
                @if($case->current_step > 8)
                    <span class="badge bg-success">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>
                        Completed
                    </span>
                @elseif($case->current_step == 8)
                    <span class="badge bg-primary">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Current Step
                    </span>
                @else
                    <span class="badge bg-secondary"><span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Pending</span>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if($case->employeeResponse)
                <!-- View Mode -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Response Date</label>
                        <div class="fw-bold">{{ $case->employeeResponse->response_date ? \Carbon\Carbon::parse($case->employeeResponse->response_date)->format('d M Y') : '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Admits Allegations</label>
                        <div>
                            @if($case->employeeResponse->admits_allegations === true)
                                <span class="badge bg-danger">Yes</span>
                            @elseif($case->employeeResponse->admits_allegations === false)
                                <span class="badge bg-success">No</span>
                            @else
                                <span class="badge bg-secondary">Not specified</span>
                            @endif
                        </div>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label text-muted">Employee Response</label>
                        <div class="p-3 bg-primary text-white rounded">{{ $case->employeeResponse->response_text }}</div>
                    </div>

                    @if($case->employeeResponse->mitigating_circumstances)
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-muted">Mitigating Circumstances</label>
                            <div class="p-3 bg-primary text-white rounded">{{ $case->employeeResponse->mitigating_circumstances }}</div>
                        </div>
                    @endif

                    @if($case->employeeResponse->documents && count($case->employeeResponse->documents) > 0)
                        <div class="col-md-12 mt-3">
                            <h6>Supporting Documents:</h6>
                            <ul class="list-group">
                                @foreach($case->employeeResponse->documents as $doc)
                                    <li class="list-group-item">
                                        <a href="{{ asset('storage/' . $doc->document_path) }}" target="_blank">{{ $doc->document_name }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="col-md-12 mt-3">
                        <div class="alert alert-info">
                            <small>
                                Submitted by {{ $case->employeeResponse->submittedBy->name ?? 'Unknown' }} on {{ $case->employeeResponse->submitted_at ? $case->employeeResponse->submitted_at->format('d M Y, h:i A') : '-' }}
                                <br>
                                Received by {{ $case->employeeResponse->receivedBy->name ?? 'Unknown' }} on {{ $case->employeeResponse->received_at ? $case->employeeResponse->received_at->format('d M Y, h:i A') : '-' }}
                            </small>
                        </div>
                    </div>

                    @if($case->current_step == 9 && !$case->closure)
                    <div class="col-md-12 mt-3">
                        <hr>
                        <p class="mb-2">If the employee's response resolves the matter, you can close the case now instead of scheduling a hearing.</p>
                        <button type="button" class="btn btn-outline-success btn-sm" id="toggleCloseAfterResponse">
                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">task_alt</span>
                            Close Case (Response Resolves This)
                        </button>

                        <form id="closeAfterResponseForm" class="mt-3 d-none">
                            @csrf
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="closure_date_r" class="form-label">Closure Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="closure_date_r" name="closure_date" value="{{ date('Y-m-d') }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="closure_reason_r" class="form-label">Closure Reason <span class="text-danger">*</span></label>
                                    <select class="form-select" id="closure_reason_r" name="closure_reason" required>
                                        <option value="">Select Closure Reason</option>
                                        <option value="Resolved">Resolved - All actions completed</option>
                                        <option value="No Case to Answer">No Case to Answer</option>
                                        <option value="Settled">Settled - Mutual agreement reached</option>
                                        <option value="Withdrawn">Withdrawn - Case withdrawn</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="closure_notes_r" class="form-label">Closure Notes</label>
                                    <textarea class="form-control" id="closure_notes_r" name="closure_notes" rows="2" placeholder="Why the response resolves this case..."></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-success" id="closeAfterResponseBtn">
                                        <span class="spinner-border spinner-border-sm me-2 d-none" id="closeAfterResponseSpinner"></span>
                                        Confirm Close Case
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <script>
                    $(document).ready(function() {
                        $('#toggleCloseAfterResponse').on('click', function() {
                            $('#closeAfterResponseForm').toggleClass('d-none');
                        });

                        $('#closeAfterResponseForm').on('submit', function(e) {
                            e.preventDefault();
                            if (!confirm('Close this case without a hearing? This cannot be undone.')) {
                                return false;
                            }

                            var formData = new FormData(this);
                            var submitButton = $('#closeAfterResponseBtn');
                            var spinner = $('#closeAfterResponseSpinner');
                            spinner.removeClass('d-none');
                            submitButton.prop('disabled', true);

                            $.ajax({
                                url: '{{ route("discipline.tracker.close.case", $case->id) }}',
                                type: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false,
                                success: function(response) {
                                    if (response.success) {
                                        toastr.success(response.message);
                                        window.reloadCasePage();
                                    }
                                },
                                error: function(xhr) {
                                    spinner.addClass('d-none');
                                    submitButton.prop('disabled', false);
                                    toastr.error(xhr.responseJSON?.error || 'Failed to close case');
                                }
                            });
                        });
                    });
                    </script>
                    @endif
                </div>
            @elseif($case->current_step == 8)
                <!-- Form Mode -->
                <form id="submitEmployeeResponseForm" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="alert alert-warning">
                                <span class="material-symbols-outlined" style="vertical-align:middle;">info</span>
                                <strong>Notice:</strong> This is the employee's opportunity to respond to the allegations made in the show cause notice.
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="response_date" class="form-label">Response Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="response_date" id="response_date" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="admits_allegations" class="form-label">Do you admit the allegations?</label>
                            <select class="form-select" name="admits_allegations" id="admits_allegations">
                                <option value="">Select</option>
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="response_text" class="form-label">Your Response <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="response_text" id="response_text" rows="6" required placeholder="Provide your response to the allegations..."></textarea>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="mitigating_circumstances" class="form-label">Mitigating Circumstances</label>
                            <textarea class="form-control" name="mitigating_circumstances" id="mitigating_circumstances" rows="4" placeholder="Any circumstances that should be considered..."></textarea>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="documents" class="form-label">Supporting Documents</label>
                            <input type="file" class="form-control" name="documents[]" id="documents" multiple>
                        </div>

                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">Submit Response</button>
                        </div>
                    </div>
                </form>

                <script>
                $(document).ready(function() {
                    $('#submitEmployeeResponseForm').on('submit', function(e) {
                        e.preventDefault();
                        
                        let formData = new FormData(this);
                        
                        $.ajax({
                            url: "{{ route('discipline.tracker.submit.response', $case->id) }}",
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
                                toastr.error(xhr.responseJSON?.error || 'Failed to submit response');
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