<!-- Step 6: Record Investigation Notes -->
<div class="tab-pane fade {{ $case->current_step == 6 ? 'show active' : '' }}" 
     id="step6" 
     role="tabpanel" 
     aria-labelledby="step6-tab">
    
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h5 class="mb-0">
                <span class="material-symbols-outlined" style="vertical-align:middle;">note_add</span>
                Step 6: Record Investigation Notes
            </h5>
            <div class="ms-auto">
                @if($case->current_step > 6)
                    <span class="badge bg-success">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>
                        Completed
                    </span>
                @elseif($case->current_step == 6)
                    <span class="badge bg-primary">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Current Step
                    </span>
                    <button class="btn btn-sm btn-light-warning ms-2" onclick="skipStep(6)">
                        <span class="material-symbols-outlined text-warning" style="font-size:14px;">skip_next</span>
                    </button>
                @else
                    <span class="badge bg-secondary"><span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>Pending</span>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if($case->investigationNotes && count($case->investigationNotes) > 0)
                <div class="row">
                    @foreach($case->investigationNotes as $note)
                        <div class="col-md-12 mb-3">
                            <div class="card {{ $note->is_confidential ? 'border-danger' : '' }}">
                                <div class="card-header d-flex justify-content-between">
                                    <div>
                                        <strong>{{ $note->note_type }}</strong>
                                        <span class="text-muted ms-2">{{ $note->note_date ? \Carbon\Carbon::parse($note->note_date)->format('d M Y') : '-' }}</span>
                                    </div>
                                    @if($note->is_confidential)
                                        <span class="badge bg-danger">Confidential</span>
                                    @endif
                                </div>
                                <div class="card-body">
                                    <div class="p-3 bg-primary text-white rounded">{{ $note->note_text }}</div>
                                </div>
                                <div class="card-footer text-muted">
                                    <small>Created by {{ $note->creator->name ?? 'Unknown' }} on {{ $note->created_at ? $note->created_at->format('d M Y, h:i A') : '-' }}</small>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($case->current_step >= 6)
                <button class="btn btn-outline-primary mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#addNoteForm">
                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">add</span>
                    Add Investigation Note
                </button>

                <div class="collapse {{ $case->investigationNotes && count($case->investigationNotes) > 0 ? '' : 'show' }}" id="addNoteForm">
                    <form id="addInvestigationNoteForm">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="note_type" class="form-label">Note Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="note_type" id="note_type" required>
                                    <option value="">Select type</option>
                                    <option value="Interview">Interview</option>
                                    <option value="Site Visit">Site Visit</option>
                                    <option value="Analysis">Analysis</option>
                                    <option value="Correspondence">Correspondence</option>
                                    <option value="General">General</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="note_date" class="form-label">Note Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="note_date" id="note_date" required>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label for="note_text" class="form-label">Investigation Note <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="note_text" id="note_text" rows="5" required></textarea>
                            </div>

                            <div class="col-md-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_confidential" id="is_confidential" value="1">
                                    <label class="form-check-label" for="is_confidential">
                                        Mark as Confidential
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary" id="addNoteBtn">
                                    <span class="spinner-border spinner-border-sm me-2 d-none" id="addNoteSpinner"></span>
                                    <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">note_add</span>
                                    Add Note
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <script>
                $(document).ready(function() {
                    $(document).ready(function() {
                        $('#addInvestigationNoteForm').on('submit', function(e) {
                            e.preventDefault();
                            
                            var submitButton = $('#addNoteBtn');
                            var spinner = $('#addNoteSpinner');
                            
                            // Show spinner and disable button
                            spinner.removeClass('d-none');
                            submitButton.prop('disabled', true);
                            submitButton.css('opacity', '0.6');
                            
                            $.ajax({
                                url: "{{ route('discipline.tracker.add.note', $case->id) }}",
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
                                    
                                    toastr.error(xhr.responseJSON?.error || 'Failed to add note');
                                }
                            });
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