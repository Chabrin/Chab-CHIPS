@extends('layouts.main') 

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex align-items-center justify-content-between bg-white py-3">
                    <h5 class="mb-0 fw-bold">Edit Training Material</h5>
                    <a href="{{ route('hr.training.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                        <span class="material-symbols-outlined">arrow_back</span>
                        <span class="ms-1">Back to Training</span>
                    </a>
                </div>
                <div class="card-body">
                <form action="{{ route('hr.training.update', $material->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    {{-- Title --}}
                    <div class="mb-3">
                        <label for="title" class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" 
                            class="form-control @error('title') is-invalid @enderror" 
                            id="title" 
                            name="title" 
                            value="{{ old('title', $material->title) }}" 
                            required>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Description --}}
                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" 
                                id="description" 
                                name="description" 
                                rows="3">{{ old('description', $material->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Version + Mandatory + Due Days --}}
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="version" class="form-label fw-semibold">Version</label>
                            <input type="text" 
                                class="form-control @error('version') is-invalid @enderror" 
                                id="version" 
                                name="version" 
                                value="{{ old('version', $material->version) }}">
                            @error('version')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold d-block">Options</label>
                            <div class="d-flex align-items-center gap-3">
                                <div class="form-check mb-0">
                                    <input type="checkbox" 
                                        class="form-check-input" 
                                        id="is_mandatory" 
                                        name="is_mandatory" 
                                        value="1"
                                        {{ old('is_mandatory', $material->is_mandatory) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="is_mandatory">Mandatory</label>
                                </div>

                                <div class="input-group input-group-sm" style="width: 130px;">
                                    <input type="number" 
                                        name="due_days" 
                                        class="form-control @error('due_days') is-invalid @enderror"
                                        value="{{ old('due_days', $material->due_days ?? 14) }}" 
                                        min="1">
                                    <span class="input-group-text">days</span>
                                </div>
                            </div>
                            @error('due_days')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Current Document --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Document</label>
                        <div class="p-2 bg-light border rounded d-flex align-items-center justify-content-between">
                            <span class="text-muted text-truncate" style="max-width: 80%;">
                                {{ $material->file_name ?? basename($material->file_path) }}
                            </span>
                            <a href="{{ route('hr.training.stream', $material->id) }}" 
                            target="_blank" 
                            class="btn btn-sm btn-outline-secondary">View File</a>
                        </div>
                    </div>

                    {{-- Replace Document --}}
                    <div class="mb-4">
                        <label for="document" class="form-label fw-semibold">Replace Document (Optional)</label>
                        <input type="file" 
                            class="form-control @error('document') is-invalid @enderror" 
                            id="document" 
                            name="document"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.mp4,.webm,.zip">
                        <div class="form-text">PDF, Office documents, MP4/WebM video or ZIP. Max 50MB.</div>
                        @error('document')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Buttons --}}
                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('hr.training.index') }}" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Training Material</button>
                    </div>
                </form>
            </div>
            </div>
        </div>
    </div>
</div>
@endsection