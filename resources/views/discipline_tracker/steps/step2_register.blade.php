<!-- Step 2: Register Case & Notify HR -->
<div class="tab-pane fade {{ $case->current_step == 2 ? 'show active' : '' }}" 
     id="step2" 
     role="tabpanel" 
     aria-labelledby="step2-tab">
    
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h5 class="mb-0">
                <span class="material-symbols-outlined" style="vertical-align:middle;">assignment</span>
                Step 2: Register Case & Notify HR
            </h5>
            <div class="ms-auto">
                @if($case->current_step > 2)
                    <span class="badge bg-success">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>
                        Completed
                    </span>
                @elseif($case->current_step == 2)
                    <span class="badge bg-primary">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Current Step
                    </span>
                @else
                    <span class="badge bg-secondary"><span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Pending</span>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if($case->registration)
                <!-- View Mode -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Registered By</label>
                        <div class="fw-bold">{{ $case->registration->registeredBy->name ?? '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Registration Date</label>
                        <div class="fw-bold">{{ $case->registration->registration_date ? \Carbon\Carbon::parse($case->registration->registration_date)->format('d M Y, h:i A') : '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Priority</label>
                        <div>
                            @php
                                $priorityColors = [
                                    'Low' => 'info',
                                    'Medium' => 'warning',
                                    'High' => 'danger',
                                    'Critical' => 'dark'
                                ];
                                $color = $priorityColors[$case->registration->priority] ?? 'secondary';
                            @endphp
                            <span class="badge bg-{{ $color }}">{{ $case->registration->priority }}</span>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Estimated Closure Date</label>
                        <div class="fw-bold">{{ $case->registration->estimated_closure_date ? \Carbon\Carbon::parse($case->registration->estimated_closure_date)->format('d M Y') : '-' }}</div>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label text-muted">HR Notes</label>
                        <div class="p-3 bg-primary text-white rounded">
                            {{ $case->registration->hr_notes ?? 'No notes recorded' }}
                        </div>
                    </div>

                    @if($case->registration->hr_notified)
                        <div class="col-md-12">
                            <div class="alert alert-success">
                                <span class="material-symbols-outlined" style="vertical-align:middle;">check_circle</span>
                                HR notified on {{ $case->registration->notification_sent_at ? \Carbon\Carbon::parse($case->registration->notification_sent_at)->format('d M Y, h:i A') : 'Unknown' }}
                            </div>
                        </div>
                    @endif
                </div>
            @elseif($case->current_step == 2)
                <!-- Form Mode -->
                <form id="registerCaseForm">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="priority" class="form-label">Priority <span class="text-danger">*</span></label>
                            <select class="form-select" name="priority" id="priority" required>
                                <option value="">Select priority</option>
                                <option value="Low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="estimated_closure_date" class="form-label">Estimated Closure Date</label>
                            <input type="date" class="form-control" name="estimated_closure_date" id="estimated_closure_date">
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="hr_notes" class="form-label">HR Notes</label>
                            <textarea class="form-control" name="hr_notes" id="hr_notes" rows="4" placeholder="Add any relevant notes..."></textarea>
                        </div>

                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary" id="registerCaseBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="registerSpinner"></span>
                                <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">check_circle</span>
                                Register Case
                            </button>
                        </div>
                    </div>
                </form>

                <script>
                $(document).ready(function() {
                    $('#registerCaseForm').on('submit', function(e) {
                        e.preventDefault();
                        
                        var submitButton = $('#registerCaseBtn');
                        var spinner = $('#registerSpinner');
                        
                        // Show spinner and disable button
                        spinner.removeClass('d-none');
                        submitButton.prop('disabled', true);
                        submitButton.css('opacity', '0.6');
                        
                        $.ajax({
                            url: "{{ route('discipline.tracker.register.case', Crypt::encrypt($case->id)) }}",
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
                                
                                toastr.error(xhr.responseJSON?.error || 'Failed to register case');
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