@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Training Materials</h5>
                        <div class="ms-auto d-flex gap-2">
                            @if($canManage)
                            <a href="{{ route('settings.hr.training_materials.compliance') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">fact_check</span>
                                <span class="ms-1">Compliance Report</span>
                            </a>
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#uploadTrainingModal">
                                <span class="material-symbols-outlined">upload_file</span>
                                <span class="ms-1">Upload Material</span>
                            </button>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        @if(!$staffLinked)
                        <div class="alert alert-warning">Your login is not linked to a staff record, so you cannot acknowledge training. Ask HR to link your account.</div>
                        @endif

                        <div class="table-responsive custom-scrollbar">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Version</th>
                                        <th>Type</th>
                                        <th>Mandatory</th>
                                        <th>My Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($materials as $material)
                                    @php
                                        $record = $myRecords->get($material->id);
                                        $done = $record && $record->completed_at && $record->version_acknowledged === $material->version;
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $material->title }}</strong>
                                            @if($material->description)
                                            <div class="text-muted small">{{ $material->description }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $material->version }}</td>
                                        <td>{{ strtoupper($material->file_type) }}</td>
                                        <td>
                                            @if($material->is_mandatory)
                                            <span class="badge bg-danger">Mandatory</span>
                                            @else
                                            <span class="badge bg-secondary">Optional</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($done)
                                            <span class="badge bg-success">Completed {{ $record->completed_at->format('d M Y') }}</span>
                                            @elseif($record && $record->due_date)
                                            <span class="badge bg-warning text-dark">Due {{ $record->due_date->format('d M Y') }}</span>
                                            @else
                                            <span class="badge bg-light text-dark">Not started</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('settings.hr.training_materials.stream', $material->id) }}" target="_blank" class="btn btn-light btn-sm">View</a>
                                            @if($staffLinked && !$done)
                                            <form action="{{ route('settings.hr.training_materials.acknowledge', $material->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-sm">Mark as read &amp; acknowledge</button>
                                            </form>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">No training materials have been uploaded yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($canManage)
<div class="modal fade" id="uploadTrainingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" action="{{ route('settings.hr.training_materials.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Upload Training Material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
                <div class="mb-3">
                    <label for="training_title" class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="training_title" name="title" value="{{ old('title') }}" required>
                </div>
                <div class="mb-3">
                    <label for="training_description" class="form-label">Description</label>
                    <textarea class="form-control" id="training_description" name="description" rows="2">{{ old('description') }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="training_version" class="form-label">Version</label>
                        <input type="text" class="form-control" id="training_version" name="version" value="{{ old('version', '1.0') }}" maxlength="20">
                    </div>
                    <div class="col-md-6 mb-3 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="training_mandatory" name="is_mandatory" value="1" checked>
                            <label class="form-check-label" for="training_mandatory">Mandatory for all active staff (due in 14 days)</label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="training_document" class="form-label">File <span class="text-danger">*</span></label>
                    <input type="file" class="form-control" id="training_document" name="document" required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.mp4,.webm,.zip">
                    <small class="text-muted">PDF, Office documents, MP4/WebM video or ZIP. Max 50MB.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
