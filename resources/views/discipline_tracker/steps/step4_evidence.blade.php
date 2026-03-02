<!-- Step 4: Upload Evidence -->
<div class="tab-pane fade {{ $case->current_step == 4 ? 'show active' : '' }}" 
     id="step4" 
     role="tabpanel" 
     aria-labelledby="step4-tab">
    
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h5 class="mb-0">
                <span class="material-symbols-outlined" style="vertical-align:middle;">folder_open</span>
                Step 4: Upload Evidence
            </h5>
            <div class="ms-auto">
                @if($case->current_step > 4)
                    <span class="badge bg-success">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>
                        Completed
                    </span>
                @elseif($case->current_step == 4)
                    <span class="badge bg-primary">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Current Step
                    </span>
                    <button class="btn btn-sm btn-light-warning ms-2" onclick="skipStep(4)">
                        <span class="material-symbols-outlined text-warning" style="font-size:14px;">skip_next</span>
                    </button>
                @else
                    <span class="badge bg-secondary"><span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Pending</span>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if($case->evidence && count($case->evidence) > 0)
                <!-- View existing evidence -->
                <div class="row">
                    @foreach($case->evidence as $evidence)
                        <div class="col-md-12 mb-3">
                            <div class="card">
                                <div class="card-header">
                                    <strong>{{ $evidence->evidence_type }}</strong>
                                    <span class="badge bg-info ms-2">{{ $evidence->is_original ? 'Original' : 'Copy' }}</span>
                                </div>
                                <div class="card-body">
                                    <p><strong>Description:</strong> {{ $evidence->evidence_description }}</p>
                                    @if($evidence->evidence_source)
                                        <p><strong>Source:</strong> {{ $evidence->evidence_source }}</p>
                                    @endif
                                    @if($evidence->chain_of_custody)
                                        <p><strong>Chain of Custody:</strong> {{ $evidence->chain_of_custody }}</p>
                                    @endif
                                    
                                    @if($evidence->files && count($evidence->files) > 0)
                                        <h6>Files:</h6>
                                        <ul class="list-group">
                                            @foreach($evidence->files as $file)
                                                <li class="list-group-item">
                                                    <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank">
                                                        {{ $file->file_name }}
                                                    </a>
                                                    <small class="text-muted">({{ number_format($file->file_size / 1024, 2) }} KB)</small>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                                <div class="card-footer text-muted">
                                    <small>Uploaded by {{ $evidence->uploader->name ?? 'Unknown' }} on {{ $evidence->uploaded_at ? $evidence->uploaded_at->format('d M Y') : '-' }}</small>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($case->current_step >= 4)
                <!-- Add more evidence form -->
                <button class="btn btn-outline-primary mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#addEvidenceForm">
                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">add</span>
                    Add More Evidence
                </button>

                <div class="collapse {{ $case->evidence && count($case->evidence) > 0 ? '' : 'show' }}" id="addEvidenceForm">
                    <form id="uploadEvidenceForm" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="evidence_type" class="form-label">Evidence Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="evidence_type" id="evidence_type" required>
                                    <option value="">Select type</option>
                                    <option value="Document">Document</option>
                                    <option value="Photo">Photo</option>
                                    <option value="Video">Video</option>
                                    <option value="Audio">Audio</option>
                                    <option value="Physical Item">Physical Item</option>
                                    <option value="Digital">Digital</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="evidence_source" class="form-label">Source</label>
                                <input type="text" class="form-control" name="evidence_source" id="evidence_source" placeholder="Where was this obtained?">
                            </div>

                            <div class="col-md-12 mb-3">
                                <label for="evidence_description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="evidence_description" id="evidence_description" rows="3" required></textarea>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label for="chain_of_custody" class="form-label">Chain of Custody</label>
                                <textarea class="form-control" name="chain_of_custody" id="chain_of_custody" rows="2"></textarea>
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_original" id="is_original" value="1" checked>
                                    <label class="form-check-label" for="is_original">
                                        This is the original evidence
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label for="files" class="form-label">Files</label>
                                <input type="file" class="form-control" name="files[]" id="files" multiple>
                                <small class="text-muted">Max 20MB per file</small>
                            </div>

                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary" id="uploadEvidenceBtn">
                                    <span class="spinner-border spinner-border-sm me-2 d-none" id="uploadEvidenceSpinner"></span>
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">upload_file</span>
                                    Upload Evidence
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <script>
                $(document).ready(function() {
                    $('#uploadEvidenceForm').on('submit', function(e) {
                        e.preventDefault();
                        
                        let formData = new FormData(this);
                        var submitButton = $('#uploadEvidenceBtn');
                        var spinner = $('#uploadEvidenceSpinner');
                        
                        // Show spinner and disable button
                        spinner.removeClass('d-none');
                        submitButton.prop('disabled', true);
                        submitButton.css('opacity', '0.6');
                        
                        $.ajax({
                            url: "{{ route('discipline.tracker.upload.evidence', $case->id) }}",
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
                                
                                toastr.error(xhr.responseJSON?.error || 'Failed to upload evidence');
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