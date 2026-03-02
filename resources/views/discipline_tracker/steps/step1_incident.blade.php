<!-- Step 1: Incident Report -->
<div class="tab-pane fade {{ $case->current_step == 1 ? 'show active' : '' }}" 
     id="step1" 
     role="tabpanel" 
     aria-labelledby="step1-tab">
    
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h5 class="mb-0">
                <span class="material-symbols-outlined" style="vertical-align:middle;">report</span>
                Step 1: Incident Report
            </h5>
            <div class="ms-auto">
                @if($case->current_step >= 1)
                    <span class="badge bg-success">
                        <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">check_circle</span>
                        Completed
                    </span>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if($case->incidentReports->first())
                <div class="row">
                    <!-- Incident Details -->
                    <div class="col-12 mb-3">
                        <h6 class="border-bottom pb-2">
                            <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">info</span>
                            Incident Information
                        </h6>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Incident Date & Time</label>
                        <div class="fw-bold">
                            {{ \Carbon\Carbon::parse($case->incidentReports->first()->incident_date_time)->format('d M Y, h:i A') }}
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Location</label>
                        <div class="fw-bold">{{ $case->incidentReports->first()->location ?? '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Incident Type</label>
                        <div class="fw-bold">
                            @if($case->incidentReports->first()->incidentType)
                                <span class="badge bg-info">{{ $case->incidentReports->first()->incidentType->name }}</span>
                            @else
                                -
                            @endif
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Severity Level</label>
                        <div class="fw-bold">
                            @php
                                $severityColor = [
                                    'Minor' => 'info',
                                    'Moderate' => 'warning',
                                    'Serious' => 'danger',
                                    'Gross Misconduct' => 'dark'
                                ][$case->severity_level] ?? 'secondary';
                            @endphp
                            <span class="badge bg-{{ $severityColor }}">{{ $case->severity_level }}</span>
                        </div>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label text-muted">Incident Description</label>
                        <div class="p-3 bg-primary text-white rounded">
                            {{ $case->incidentReports->first()->incident_description }}
                        </div>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label text-muted">Immediate Action Taken</label>
                        <div class="p-3 bg-primary text-white rounded">
                            {{ $case->incidentReports->first()->immediate_action_taken ?? 'No immediate action recorded' }}
                        </div>
                    </div>

                    <!-- Reporter Details -->
                    <div class="col-12 mb-3 mt-3">
                        <h6 class="border-bottom pb-2">
                            <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">person</span>
                            Reporter Information
                        </h6>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Reported By</label>
                        <div class="fw-bold">
                            {{ $case->incidentReports->first()->reportedBy->full_names ?? 'Unknown' }}
                        </div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Witness Present</label>
                        <div class="fw-bold">
                            @if($case->incidentReports->first()->witness_present)
                                <span class="badge bg-success">Yes</span>
                            @else
                                <span class="badge bg-secondary">No</span>
                            @endif
                        </div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Police Involved</label>
                        <div class="fw-bold">
                            @if($case->incidentReports->first()->police_involved)
                                <span class="badge bg-warning">Yes</span>
                            @else
                                <span class="badge bg-secondary">No</span>
                            @endif
                        </div>
                    </div>

                    @if($case->is_anonymous)
                        <div class="col-md-12 mb-3">
                            <div class="alert alert-warning">
                                <span class="material-symbols-outlined" style="vertical-align:middle;">visibility_off</span>
                                <strong>Anonymous Report:</strong> Reporter identity is protected.
                            </div>
                        </div>
                    @endif

                    <!-- Supporting Documents -->
                    @if($case->incidentReports->first()->documents && count($case->incidentReports->first()->documents) > 0)
                        <div class="col-12 mb-3 mt-3">
                            <h6 class="border-bottom pb-2">
                                <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">attach_file</span>
                                Supporting Documents ({{ count($case->incidentReports->first()->documents) }})
                            </h6>
                        </div>

                        <div class="col-md-12">
                            <div class="list-group">
                                @foreach($case->incidentReports->first()->documents as $document)
                                    <a href="{{ asset('storage/' . $document->document_path) }}" 
                                       target="_blank" 
                                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">description</span>
                                            <strong>{{ $document->document_name }}</strong>
                                            <br>
                                            <small class="text-muted">
                                                {{ $document->document_type ?? 'Unknown type' }} | 
                                                {{ number_format($document->file_size / 1024, 2) }} KB | 
                                                Uploaded by {{ $document->uploader->name ?? 'Unknown' }} on 
                                                {{ $document->uploaded_at ? $document->uploaded_at->format('d M Y') : 'Unknown date' }}
                                            </small>
                                        </div>
                                        <span class="badge bg-primary">
                                            <span class="material-symbols-outlined" style="font-size:14px;">download</span>
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="col-12 mb-3 mt-3">
                            <div class="alert alert-info">
                                <span class="material-symbols-outlined" style="vertical-align:middle;">info</span>
                                No supporting documents attached.
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <div class="alert alert-warning">
                    <span class="material-symbols-outlined" style="vertical-align:middle;">warning</span>
                    No incident report found for this case.
                </div>
            @endif
        </div>
        <div class="card-footer text-muted">
            <small>
                <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">schedule</span>
                Created on {{ $case->date_time->format('d M Y, h:i A') }} by {{ $case->createdBy->name ?? 'System' }}
            </small>
        </div>
    </div>
</div>