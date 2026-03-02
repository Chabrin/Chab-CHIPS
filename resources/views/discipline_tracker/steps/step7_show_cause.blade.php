<!-- Step 7: Issue Show Cause Notice -->
<div class="tab-pane fade {{ $case->current_step == 7 ? 'show active' : '' }}" 
     id="step7" 
     role="tabpanel" 
     aria-labelledby="step7-tab">
    
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h5 class="mb-0">
                <span class="material-symbols-outlined" style="vertical-align:middle;">gavel</span>
                Step 7: Issue Show Cause Notice
            </h5>
            <div class="ms-auto">
                @if($case->current_step > 7)
                    <span class="badge bg-success">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>
                        Completed
                    </span>
                @elseif($case->current_step == 7)
                    <span class="badge bg-primary">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Current Step
                    </span>
                @else
                    <span class="badge bg-secondary"><span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Pending</span>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if($case->showCauseNotice)
                @php
                    // Calculate deadline status
                    $originalDeadline = \Carbon\Carbon::parse($case->showCauseNotice->response_deadline);
                    $currentDeadline = $originalDeadline;
                    
                    // Check for extensions
                    $latestExtension = $case->deadlineExtensions()->where('request_status', 'Approved')->orderBy('extended_at', 'desc')->first();
                    if ($latestExtension) {
                        $currentDeadline = \Carbon\Carbon::parse($latestExtension->new_deadline);
                    }
                    
                    // Check for pending employee requests
                    $pendingRequests = $case->deadlineExtensions()
                        ->where('request_status', 'Pending')
                        ->whereNotNull('requested_by')
                        ->whereNull('reviewed_at')
                        ->orderBy('requested_at', 'desc')
                        ->get();
                    
                    $now = \Carbon\Carbon::now();
                    $deadlineExpired = $now->greaterThan($currentDeadline);
                    $hoursRemaining = $now->diffInHours($currentDeadline, false);
                    $isUrgent = $hoursRemaining > 0 && $hoursRemaining <= 24;
                @endphp

                <!-- Pending Extension Requests Alert -->
                @if($pendingRequests->count() > 0)
                    <div class="alert alert-warning mb-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <span class="material-symbols-outlined me-2" style="font-size:24px;">pending_actions</span>
                                <div>
                                    <strong>{{ $pendingRequests->count() }} Pending Extension Request(s)</strong><br>
                                    <small>Employee has requested a deadline extension. Please review below.</small>
                                </div>
                            </div>
                            <span class="badge bg-warning text-dark">Action Required</span>
                        </div>
                    </div>
                @endif

                <!-- View Mode -->
                <div class="row">
                    <!-- Deadline Status Alert -->
                    <div class="col-md-12 mb-3">
                        @if($deadlineExpired)
                            <div class="alert alert-danger d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <span class="material-symbols-outlined me-2" style="font-size:24px;">error</span>
                                    <div>
                                        <strong>Response Deadline Expired!</strong><br>
                                        <small>Deadline was {{ $currentDeadline->format('d M Y, h:i A') }} ({{ $currentDeadline->diffForHumans() }})</small>
                                    </div>
                                </div>
                                @permission('extend_discipline_response_deadline')
                                    @if($case->current_step <= 8 && !$case->employeeResponse)
                                        <button type="button" class="btn btn-warning btn-sm" onclick="openExtendDeadlineModal()">
                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">update</span>
                                            Extend Deadline
                                        </button>
                                    @endif
                                @endpermission
                            </div>
                        @elseif($isUrgent)
                            <div class="alert alert-warning d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <span class="material-symbols-outlined me-2" style="font-size:24px;">schedule</span>
                                    <div>
                                        <strong>Urgent: Deadline Approaching!</strong><br>
                                        <small>Response deadline: {{ $currentDeadline->format('d M Y, h:i A') }} ({{ round($hoursRemaining) }} hours remaining)</small>
                                    </div>
                                </div>
                                @permission('extend_discipline_response_deadline')
                                    @if($case->current_step <= 8 && !$case->employeeResponse)
                                        <button type="button" class="btn btn-warning btn-sm" onclick="openExtendDeadlineModal()">
                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">update</span>
                                            Extend Deadline
                                        </button>
                                    @endif
                                @endpermission
                            </div>
                        @else
                            <div class="alert alert-info d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <span class="material-symbols-outlined me-2" style="font-size:24px;">schedule</span>
                                    <div>
                                        <strong>Response Deadline:</strong> {{ $currentDeadline->format('d M Y, h:i A') }}<br>
                                        <small>Time remaining: {{ $currentDeadline->diffForHumans() }}</small>
                                    </div>
                                </div>
                                @permission('extend_discipline_response_deadline')
                                    @if($case->current_step <= 8 && !$case->employeeResponse)
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openExtendDeadlineModal()">
                                            <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">update</span>
                                            Extend Deadline
                                        </button>
                                    @endif
                                @endpermission
                            </div>
                        @endif
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Notice Number</label>
                        <div class="fw-bold">{{ $case->showCauseNotice->notice_number ?? '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Issue Date</label>
                        <div class="fw-bold">{{ $case->showCauseNotice->issue_date ? \Carbon\Carbon::parse($case->showCauseNotice->issue_date)->format('d M Y') : '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Original Response Deadline</label>
                        <div class="fw-bold">{{ $originalDeadline->format('d M Y, h:i A') }}</div>
                    </div>

                    @if($latestExtension)
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Current Response Deadline</label>
                            <div class="fw-bold text-success">
                                {{ $currentDeadline->format('d M Y, h:i A') }}
                                <span class="badge bg-success ms-2">Extended</span>
                            </div>
                        </div>
                    @else
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Delivery Method</label>
                            <div class="fw-bold">{{ $case->showCauseNotice->delivered_method }}</div>
                        </div>
                    @endif

                    <div class="col-md-12 mb-3">
                        <label class="form-label text-muted">Allegations</label>
                        <div class="p-3 bg-primary text-white border rounded">{{ $case->showCauseNotice->allegations }}</div>
                    </div>

                    @if($case->showCauseNotice->potential_consequences)
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-muted">Potential Consequences</label>
                            <div class="p-3 bg-primary text-white border rounded">{{ $case->showCauseNotice->potential_consequences }}</div>
                        </div>
                    @endif

                    @if($case->showCauseNotice->acknowledgment_received)
                        <div class="col-md-12">
                            <div class="alert alert-success">
                                <span class="material-symbols-outlined" style="vertical-align:middle;">check_circle</span>
                                Acknowledgment received on {{ $case->showCauseNotice->acknowledgment_date ? \Carbon\Carbon::parse($case->showCauseNotice->acknowledgment_date)->format('d M Y') : 'Unknown' }}
                            </div>
                        </div>
                    @endif

                    @if($case->showCauseNotice->documents && count($case->showCauseNotice->documents) > 0)
                        <div class="col-md-12 mt-3">
                            <h6>Documents:</h6>
                            <ul class="list-group">
                                @foreach($case->showCauseNotice->documents as $doc)
                                    <li class="list-group-item">
                                        <a href="{{ asset('storage/' . $doc->document_path) }}" target="_blank">{{ $doc->document_name }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Employee Extension Requests -->
                    @if($pendingRequests->count() > 0)
                        <div class="col-md-12 mt-4">
                            <h6 class="mb-3">
                                <span class="material-symbols-outlined" style="font-size:20px; vertical-align:middle;">person_raised_hand</span>
                                Employee Extension Requests
                                <span class="badge bg-warning text-dark">{{ $pendingRequests->count() }}</span>
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 150px;">Requested At</th>
                                            <th style="width: 120px;">Requested By</th>
                                            <th>Reason</th>
                                            <th style="width: 100px;">Days Requested</th>
                                            <th style="width: 120px;">Current Deadline</th>
                                            <th style="width: 100px;">Lapsed?</th>
                                            <th style="width: 180px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pendingRequests as $request)
                                        <tr>
                                            <td>{{ \Carbon\Carbon::parse($request->requested_at)->format('d M Y, h:i A') }}</td>
                                            <td>{{ $request->requestedBy->name ?? $case->staff->full_names }}</td>
                                            <td>{{ $request->extension_reason }}</td>
                                            <td class="text-center">
                                                <span class="badge bg-info">{{ $request->requested_days }} days</span>
                                            </td>
                                            <td>{{ \Carbon\Carbon::parse($request->original_deadline)->format('d M Y') }}</td>
                                            <td>
                                                @if($request->deadline_lapsed_before_extension)
                                                    <span class="badge bg-danger">Yes</span>
                                                @else
                                                    <span class="badge bg-success">No</span>
                                                @endif
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-light me-1 mb-2" onclick="reviewExtensionRequest({{ $request->id }}, 'approve')">
                                                    <span class="material-symbols-outlined" style="font-size:14px;">check</span>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-light" onclick="reviewExtensionRequest({{ $request->id }}, 'reject')">
                                                    <span class="material-symbols-outlined" style="font-size:14px;">close</span>
                                                </button>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- Deadline Extension History -->
                    @if($case->deadlineExtensions && $case->deadlineExtensions->count() > 0)
                        <div class="col-md-12 mt-4">
                            <h6 class="mb-3">
                                <span class="material-symbols-outlined" style="font-size:20px; vertical-align:middle;">history</span>
                                Deadline Extension History
                                <span class="badge bg-primary">{{ $case->deadlineExtensions->count() }}</span>
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 150px;">Original Deadline</th>
                                            <th style="width: 150px;">New Deadline</th>
                                            <th>Requested Days</th>
                                            <th>Reason</th>
                                            <th style="width: 120px;">Type</th>
                                            <th style="width: 120px;">Extended/Reviewed By</th>
                                            <th style="width: 100px;">Status</th>
                                            <th style="width: 150px;">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($case->deadlineExtensions()->orderBy('requested_at', 'desc')->orderBy('extended_at', 'desc')->get() as $extension)
                                        <tr>
                                            <td>{{ \Carbon\Carbon::parse($extension->original_deadline)->format('d M Y, h:i A') }}</td>
                                            <td>
                                                @if($extension->new_deadline)
                                                    <strong>{{ \Carbon\Carbon::parse($extension->new_deadline)->format('d M Y, h:i A') }}</strong>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>{{ $extension->requested_days }}</td>
                                            <td>{{ $extension->extension_reason }}</td>
                                            <td>
                                                @if($extension->requested_by)
                                                    <span class="badge bg-info">Employee Request</span>
                                                @else
                                                    <span class="badge bg-primary">HR Extension</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($extension->reviewedBy)
                                                    {{ $extension->reviewedBy->name }}
                                                @elseif($extension->extendedBy)
                                                    {{ $extension->extendedBy->name }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                @if($extension->request_status == 'Approved')
                                                    <span class="badge bg-success">Approved</span>
                                                @elseif($extension->request_status == 'Rejected')
                                                    <span class="badge bg-danger">Rejected</span>
                                                @elseif($extension->request_status == 'Pending')
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                @else
                                                    <span class="badge bg-secondary">N/A</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($extension->reviewed_at)
                                                    {{ \Carbon\Carbon::parse($extension->reviewed_at)->format('d M Y, h:i A') }}
                                                @elseif($extension->extended_at)
                                                    {{ \Carbon\Carbon::parse($extension->extended_at)->format('d M Y, h:i A') }}
                                                @elseif($extension->requested_at)
                                                    {{ \Carbon\Carbon::parse($extension->requested_at)->format('d M Y, h:i A') }}
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            @elseif($case->current_step == 7)
                <!-- Form Mode -->
                <form id="issueShowCauseForm" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="alert alert-info">
                                <span class="material-symbols-outlined" style="vertical-align:middle;">info</span>
                                <strong>Note:</strong> The standard response deadline is 48 hours from the issue date. You can extend this deadline later if needed.
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="notice_number" class="form-label">Notice Number</label>
                            <input type="text" class="form-control" name="notice_number" id="notice_number" placeholder="Auto-generated if left blank">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="issue_date" class="form-label">Issue Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="issue_date" id="issue_date" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="response_deadline" class="form-label">Response Deadline <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="response_deadline" id="response_deadline" required>
                            <small class="text-muted">Default: 48 hours from issue date</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="delivered_method" class="form-label">Delivery Method <span class="text-danger">*</span></label>
                            <select class="form-select" name="delivered_method" id="delivered_method" required>
                                <option value="">Select method</option>
                                <option value="Hand Delivery">Hand Delivery</option>
                                <option value="Email">Email</option>
                                <option value="Registered Mail">Registered Mail</option>
                                <option value="Courier">Courier</option>
                            </select>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="allegations" class="form-label">Allegations <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="allegations" id="allegations" rows="5" required placeholder="State the allegations clearly..."></textarea>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="potential_consequences" class="form-label">Potential Consequences</label>
                            <textarea class="form-control" name="potential_consequences" id="potential_consequences" rows="3" placeholder="Outline potential consequences..."></textarea>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="documents" class="form-label">Supporting Documents</label>
                            <input type="file" class="form-control" name="documents[]" id="documents" multiple>
                        </div>

                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary" id="issueShowCauseBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="issueShowCauseSpinner"></span>
                                <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">send</span>
                                Issue Show Cause Notice
                            </button>
                        </div>
                    </div>
                </form>

                <script>
                $(document).ready(function() {
                    // Auto-calculate deadline (48 hours from issue date)
                    $('#issue_date').on('change', function() {
                        let issueDate = new Date($(this).val());
                        if (!isNaN(issueDate)) {
                            issueDate.setHours(issueDate.getHours() + 48);
                            let deadlineStr = issueDate.toISOString().slice(0, 16);
                            $('#response_deadline').val(deadlineStr);
                        }
                    });

                    // Trigger on page load if date is already set
                    if ($('#issue_date').val()) {
                        $('#issue_date').trigger('change');
                    }

                    $('#issueShowCauseForm').on('submit', function(e) {
                        e.preventDefault();
                        
                        let formData = new FormData(this);
                        var submitButton = $('#issueShowCauseBtn');
                        var spinner = $('#issueShowCauseSpinner');
                        
                        // Show spinner and disable button
                        spinner.removeClass('d-none');
                        submitButton.prop('disabled', true);
                        submitButton.css('opacity', '0.6');
                        
                        $.ajax({
                            url: "{{ route('discipline.tracker.issue.show.cause', $case->id) }}",
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
                                
                                toastr.error(xhr.responseJSON?.error || 'Failed to issue show cause notice');
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

<!-- Extend Deadline Modal (HR Manual Extension) -->
<div class="modal fade" id="extendDeadlineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Extend Response Deadline</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="extendDeadlineForm">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Current Deadline</label>
                        <input type="text" class="form-control" id="current_deadline_display" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="new_deadline" class="form-label">New Deadline <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" name="new_deadline" id="new_deadline" required>
                        <small class="text-muted">Must be after the current deadline</small>
                    </div>
                    <div class="mb-3">
                        <label for="extension_reason" class="form-label">Reason for Extension <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="extension_reason" id="extension_reason" rows="3" required placeholder="E.g., Employee requested more time to gather evidence..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined">close</span>
                        <span class="ms-1">Cancel</span>
                    </button>
                    <button type="submit" class="btn btn-primary" id="extendDeadlineBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="extendDeadlineSpinner"></span>
                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">update</span>
                        Extend Deadline
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Review Extension Request Modal -->
<div class="modal fade" id="reviewExtensionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Review Extension Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="reviewExtensionForm">
                @csrf
                <input type="hidden" name="request_id" id="review_request_id">
                <input type="hidden" name="action" id="review_action">
                
                <div class="modal-body">
                    <div id="approveSection" style="display:none;">
                        <div class="alert alert-success">
                            <strong>Approving Extension Request</strong>
                        </div>
                        <div class="mb-3">
                            <label for="approved_deadline" class="form-label">New Deadline <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="new_deadline" id="approved_deadline">
                            <small class="text-muted">Set the new deadline for the employee</small>
                        </div>
                    </div>
                    
                    <div id="rejectSection" style="display:none;">
                        <div class="alert alert-danger">
                            <strong>Rejecting Extension Request</strong>
                        </div>
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="rejection_reason" id="rejection_reason" rows="3" placeholder="Explain why the extension request is being rejected..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined">close</span>
                        <span class="ms-1">Cancel</span>
                    </button>
                    <button type="submit" class="btn btn-primary" id="reviewSubmitBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="reviewSubmitSpinner"></span>
                        <span id="reviewSubmitText">Submit</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openExtendDeadlineModal() {
    @if($case->showCauseNotice)
        @php
            $displayDeadline = $latestExtension 
                ? \Carbon\Carbon::parse($latestExtension->new_deadline)
                : \Carbon\Carbon::parse($case->showCauseNotice->response_deadline);
        @endphp
        
        $('#current_deadline_display').val('{{ $displayDeadline->format("d M Y, h:i A") }}');
        
        // Set minimum date to current deadline (whichever is later: current deadline or now)
        let currentDeadline = new Date('{{ $displayDeadline->toIso8601String() }}');
        let now = new Date();
        let minDate = currentDeadline > now ? currentDeadline : now;
        minDate.setMinutes(minDate.getMinutes() - minDate.getTimezoneOffset());
        document.getElementById('new_deadline').min = minDate.toISOString().slice(0, 16);
    @endif
    
    $('#extendDeadlineModal').modal('show');
}

function reviewExtensionRequest(requestId, action) {
    $('#review_request_id').val(requestId);
    $('#review_action').val(action);
    
    if(action === 'approve') {
        $('#approveSection').show();
        $('#rejectSection').hide();
        $('#approved_deadline').prop('required', true);
        $('#rejection_reason').prop('required', false);
        $('#reviewSubmitBtn').removeClass('btn-danger').addClass('btn-success');
        $('#reviewSubmitText').text('Approve');
        
        // Set minimum date to now (can't set deadline in the past)
        let now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('approved_deadline').min = now.toISOString().slice(0, 16);
    } else {
        $('#approveSection').hide();
        $('#rejectSection').show();
        $('#approved_deadline').prop('required', false);
        $('#rejection_reason').prop('required', true);
        $('#reviewSubmitBtn').removeClass('btn-success').addClass('btn-danger');
        $('#reviewSubmitText').text('Reject');
    }
    
    $('#reviewExtensionModal').modal('show');
}

$(document).ready(function() {
    // HR Manual Extension
    $('#extendDeadlineForm').on('submit', function(e) {
        e.preventDefault();
        
        var submitButton = $('#extendDeadlineBtn');
        var spinner = $('#extendDeadlineSpinner');
        
        // Show spinner and disable button
        spinner.removeClass('d-none');
        submitButton.prop('disabled', true);
        submitButton.css('opacity', '0.6');
        
        let formData = {
            _token: '{{ csrf_token() }}',
            new_deadline: $('#new_deadline').val(),
            extension_reason: $('#extension_reason').val()
        };
        
        $.ajax({
            url: "{{ route('discipline.tracker.extend.deadline', $case->id) }}",
            type: 'POST',
            data: formData,
            success: function(response) {
                if(response.success) {
                    $('#extendDeadlineModal').modal('hide');
                    toastr.success(response.message);
                    window.reloadCasePage();
                }
            },
            error: function(xhr) {
                // Re-enable button on error
                spinner.addClass('d-none');
                submitButton.prop('disabled', false);
                submitButton.css('opacity', '1');
                
                toastr.error(xhr.responseJSON?.error || 'Failed to extend deadline');
            }
        });
    });

    // Review Extension Request
    $('#reviewExtensionForm').on('submit', function(e) {
        e.preventDefault();
        
        var submitButton = $('#reviewSubmitBtn');
        var spinner = $('#reviewSubmitSpinner');
        
        // Show spinner and disable button
        spinner.removeClass('d-none');
        submitButton.prop('disabled', true);
        submitButton.css('opacity', '0.6');
        
        let formData = {
            _token: '{{ csrf_token() }}',
            request_id: $('#review_request_id').val(),
            action: $('#review_action').val(),
            new_deadline: $('#approved_deadline').val(),
            rejection_reason: $('#rejection_reason').val()
        };
        
        $.ajax({
            url: "{{ route('discipline.tracker.review.extension') }}",
            type: 'POST',
            data: formData,
            success: function(response) {
                if(response.success) {
                    $('#reviewExtensionModal').modal('hide');
                    toastr.success(response.message);
                    window.reloadCasePage();
                }
            },
            error: function(xhr) {
                // Re-enable button on error
                spinner.addClass('d-none');
                submitButton.prop('disabled', false);
                submitButton.css('opacity', '1');
                
                toastr.error(xhr.responseJSON?.error || 'Failed to review extension request');
            }
        });
    });
});
</script>