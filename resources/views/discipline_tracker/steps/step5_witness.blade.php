<!-- Step 5: Add Witness Statements -->
<div class="tab-pane fade {{ $case->current_step == 5 ? 'show active' : '' }}" 
     id="step5" 
     role="tabpanel" 
     aria-labelledby="step5-tab">
    
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h5 class="mb-0">
                <span class="material-symbols-outlined" style="vertical-align:middle;">groups</span>
                Step 5: Add Witness Statements
            </h5>
            <div class="ms-auto">
                @if($case->current_step > 5)
                    <span class="badge bg-success">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>
                        Completed
                    </span>
                @elseif($case->current_step == 5)
                    <span class="badge bg-primary">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Current Step
                    </span>
                    <button class="btn btn-sm btn-light-warning ms-2" onclick="skipStep(5)">
                        <span class="material-symbols-outlined text-warning" style="font-size:14px;">skip_next</span>
                    </button>
                @else
                    <span class="badge bg-secondary"><span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Pending</span>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if($case->witnessStatements && count($case->witnessStatements) > 0)
                @foreach($case->witnessStatements as $witness)
                    <div class="card mb-3">
                        <div class="card-header">
                            <strong>{{ $witness->witness_name }}</strong>
                            @if($witness->is_sworn)
                                <span class="badge bg-success ms-2">Sworn Statement</span>
                            @endif
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <strong>Role:</strong> {{ $witness->witness_role ?? '-' }}
                                </div>
                                <div class="col-md-6 mb-2">
                                    <strong>Contact:</strong> {{ $witness->witness_contact ?? '-' }}
                                </div>
                                <div class="col-md-12 mb-2">
                                    <strong>Statement Date:</strong> {{ $witness->statement_date ? \Carbon\Carbon::parse($witness->statement_date)->format('d M Y') : '-' }}
                                </div>
                                @if($witness->is_sworn && $witness->sworn_before)
                                    <div class="col-md-12 mb-2">
                                        <strong>Sworn Before:</strong> {{ $witness->sworn_before }}
                                    </div>
                                @endif
                                <div class="col-md-12 mt-2">
                                    <strong>Statement:</strong>
                                    <div class="p-3 bg-primary text-white rounded mt-2">{{ $witness->statement_text }}</div>
                                </div>

                                @if($witness->documents && count($witness->documents) > 0)
                                    <div class="col-md-12 mt-3">
                                        <strong>Documents:</strong>
                                        <ul class="list-group mt-2">
                                            @foreach($witness->documents as $doc)
                                                <li class="list-group-item">
                                                    <a href="{{ asset('storage/' . $doc->document_path) }}" target="_blank">{{ $doc->document_name }}</a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="card-footer text-muted">
                            <small>Recorded by {{ $witness->recorder->name ?? 'Unknown' }} on {{ $witness->recorded_at ? $witness->recorded_at->format('d M Y') : '-' }}</small>
                        </div>
                    </div>
                @endforeach
            @endif

            @if($case->current_step >= 5)
                <button class="btn btn-outline-primary mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#addWitnessForm">
                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">add</span>
                    Add Witness Statement
                </button>

                <div class="collapse {{ $case->witnessStatements && count($case->witnessStatements) > 0 ? '' : 'show' }}" id="addWitnessForm">
                    <form id="addWitnessStatementForm" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="witness_name" class="form-label">Witness Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="witness_name" id="witness_name" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="witness_staff_id" class="form-label">Select if Internal Staff</label>
                                <select class="form-select" name="witness_staff_id" id="witness_staff_id"></select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="witness_contact" class="form-label">Contact</label>
                                <input type="text" class="form-control" name="witness_contact" id="witness_contact">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="witness_role" class="form-label">Witness Role</label>
                                <input type="text" class="form-control" name="witness_role" id="witness_role" placeholder="e.g., Eyewitness, Character witness">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="statement_date" class="form-label">Statement Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="statement_date" id="statement_date" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="is_sworn" id="is_sworn" value="1">
                                    <label class="form-check-label" for="is_sworn">Sworn Statement</label>
                                </div>
                            </div>

                            <div class="col-md-12 mb-3" id="sworn_before_field" style="display:none;">
                                <label for="sworn_before" class="form-label">Sworn Before</label>
                                <input type="text" class="form-control" name="sworn_before" id="sworn_before">
                            </div>

                            <div class="col-md-12 mb-3">
                                <label for="statement_text" class="form-label">Statement <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="statement_text" id="statement_text" rows="5" required></textarea>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label for="documents" class="form-label">Supporting Documents</label>
                                <input type="file" class="form-control" name="documents[]" id="documents" multiple>
                            </div>

                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary" id="addWitnessBtn">
                                    <span class="spinner-border spinner-border-sm me-2 d-none" id="addWitnessSpinner"></span>
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">person_add</span>
                                    Add Witness Statement
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <script>
               $(document).ready(function() {
                    // Load staff
                    $.ajax({
                        url: "{{ url('/ajax/hr/staff/list') }}",
                        type: 'GET',
                        success: function(response) {
                            let $select = $('#witness_staff_id');
                            $select.empty().append('<option value="">Not internal staff</option>');
                            if (response.staff) {
                                response.staff.forEach(function(staff) {
                                    $select.append(`<option value="${staff.id}">${staff.full_names}</option>`);
                                });
                            }
                            $select.select2({ placeholder: 'Select if internal staff', allowClear: true });
                        }
                    });

                    $('#is_sworn').on('change', function() {
                        $('#sworn_before_field').toggle(this.checked);
                    });

                    $('#addWitnessStatementForm').on('submit', function(e) {
                        e.preventDefault();
                        
                        let formData = new FormData(this);
                        var submitButton = $('#addWitnessBtn');
                        var spinner = $('#addWitnessSpinner');
                        
                        // Show spinner and disable button
                        spinner.removeClass('d-none');
                        submitButton.prop('disabled', true);
                        submitButton.css('opacity', '0.6');
                        
                        $.ajax({
                            url: "{{ route('discipline.tracker.add.witness', $case->id) }}",
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
                                
                                toastr.error(xhr.responseJSON?.error || 'Failed to add witness statement');
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