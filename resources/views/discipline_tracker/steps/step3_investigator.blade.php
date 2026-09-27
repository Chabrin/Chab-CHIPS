<!-- Step 3: Assign Investigator -->
<div class="tab-pane fade {{ $case->current_step == 3 ? 'show active' : '' }}" 
     id="step3" 
     role="tabpanel" 
     aria-labelledby="step3-tab">
    
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h5 class="mb-0">
                <span class="material-symbols-outlined" style="vertical-align:middle;">person_search</span>
                Step 3: Assign Investigator
            </h5>
            <div class="ms-auto">
                @if($case->current_step > 3)
                    <span class="badge bg-success">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>
                        Completed
                    </span>
                @elseif($case->current_step == 3)
                    <span class="badge bg-primary">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Current Step
                    </span>
                    <button class="btn btn-sm btn-light-warning ms-2" onclick="skipStep(3)">
                        <span class="material-symbols-outlined text-warning" style="font-size:14px;">skip_next</span>
                    </button>
                @else
                    <span class="badge bg-secondary"><span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Pending</span>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if($case->investigators && count($case->investigators) > 0)
                <!-- View Mode -->
                @foreach($case->investigators as $inv)
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">Investigator</label>
                                    <div class="fw-bold">{{ $inv->investigator->full_names ?? '-' }}</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">Status</label>
                                    <div>
                                        @php
                                            $statusColors = [
                                                'Assigned' => 'info',
                                                'In Progress' => 'primary',
                                                'Completed' => 'success',
                                                'Reassigned' => 'warning'
                                            ];
                                            $color = $statusColors[$inv->status] ?? 'secondary';
                                        @endphp
                                        <span class="badge bg-{{ $color }}">{{ $inv->status }}</span>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">Assigned By</label>
                                    <div class="fw-bold">{{ $inv->assignedBy->name ?? '-' }}</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">Assigned Date</label>
                                    <div class="fw-bold">{{ $inv->assigned_at ? \Carbon\Carbon::parse($inv->assigned_at)->format('d M Y') : '-' }}</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">Start Date</label>
                                    <div class="fw-bold">{{ $inv->investigation_start_date ?? '-' }}</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">Deadline</label>
                                    <div class="fw-bold">{{ $inv->investigation_deadline ?? '-' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @elseif($case->current_step == 3)
                <!-- Form Mode -->
                <form id="assignInvestigatorForm">
                    @csrf
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="investigator_id" class="form-label">Investigator <span class="text-danger">*</span></label>
                            <select class="form-select" name="investigator_id" id="investigator_id" required></select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="investigation_start_date" class="form-label">Investigation Start Date</label>
                            <input type="date" class="form-control" name="investigation_start_date" id="investigation_start_date">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="investigation_deadline" class="form-label">Investigation Deadline</label>
                            <input type="date" class="form-control" name="investigation_deadline" id="investigation_deadline">
                        </div>

                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary" id="assignInvestigatorBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="assignInvestigatorSpinner"></span>
                                <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">person_add</span>
                                Assign Investigator
                            </button>
                        </div>
                    </div>
                </form>

                <script>
                $(document).ready(function() {
                    // Load staff for investigators
                    $.ajax({
                        url: "{{ url('/ajax/hr/staff/list') }}",
                        type: 'GET',
                        success: function(response) {
                            let $select = $('#investigator_id');
                            $select.empty().append('<option value="">Select investigator</option>');
                            if (response.staff) {
                                response.staff.forEach(function(staff) {
                                    $select.append(`<option value="${staff.id}">${staff.full_names}</option>`);
                                });
                            }
                            $select.select2({ placeholder: 'Search investigator...', allowClear: true });
                        },
                        error: function(xhr) {
                            toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to load investigator options.');
                        }
                    });

                    $('#assignInvestigatorForm').on('submit', function(e) {
                        e.preventDefault();
                        
                        var submitButton = $('#assignInvestigatorBtn');
                        var spinner = $('#assignInvestigatorSpinner');
                        
                        // Show spinner and disable button
                        spinner.removeClass('d-none');
                        submitButton.prop('disabled', true);
                        submitButton.css('opacity', '0.6');
                        
                        $.ajax({
                            url: "{{ route('discipline.tracker.assign.investigator', $case->id) }}",
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
                                
                                toastr.error(xhr.responseJSON?.error || 'Failed to assign investigator');
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