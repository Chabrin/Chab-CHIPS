@extends('layouts.main')

@section('styles')
<style>
.section-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    color: #3598db;
    margin-bottom: 6px;
}
.form-label { font-size: 0.78rem; font-weight: 500; margin-bottom: 3px; }

.priority-option {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 10px 12px;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
}
.priority-option:hover { border-color: #3598db; background: #f8fbff; }
.priority-option input[type="radio"] { margin-top: 2px; }
.priority-option.selected { border-color: #3598db; background: #f0f7ff; }

.priority-dot { width:8px; height:8px; border-radius:50%; display:inline-block; margin-right:4px; margin-top:6px; flex-shrink:0; }
.priority-low    { background:#6c757d; }
.priority-medium { background:#ffc107; }
.priority-high   { background:#fd7e14; }
.priority-urgent { background:#dc3545; }

.char-counter { font-size: 0.7rem; color: #9ca3af; }
.char-counter.near-limit { color: #fd7e14; }
.char-counter.at-limit   { color: #dc3545; }

.drop-zone {
    border: 2px dashed #c8d6e5;
    border-radius: 6px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
    font-size: 0.8rem;
    color: #6c757d;
}
.drop-zone:hover, .drop-zone.dragover { border-color: #3598db; background: #f0f7ff; color: #1a5f9e; }
.drop-zone .material-symbols-outlined { font-size: 28px; display: block; margin-bottom: 4px; opacity: 0.5; }

.file-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #f0f7ff;
    border: 1px solid #c8e0f9;
    border-radius: 4px;
    padding: 2px 8px;
    font-size: 0.72rem;
    color: #1a5f9e;
}
.file-chip .remove-file { cursor: pointer; color: #dc3545; font-size: 14px; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
<div class="container-fluid">

    {{-- Page header --}}
    

    <div class="row justify-content-center">
        
        <div class="col-lg-12">

            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="mb-0">Submit a Support Ticket</h6>
                <a href="{{ route('tickets.my') }}"
                class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                    <span class="material-symbols-outlined" style="font-size:15px;">arrow_back</span> My Tickets
                </a>
            </div>

            <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" id="createTicketForm">
                @csrf

                {{-- ── Basic Info ── --}}
                <div class="card mb-3">
                    <div class="card-header card-no-border py-2">
                        <div class="section-label">Ticket Information</div>
                    </div>
                    <div class="card-body pb-3">

                        <div class="mb-3">
                            <label class="form-label">Subject <span class="text-danger">*</span></label>
                            <input type="text" name="subject" class="form-control form-control-sm @error('subject') is-invalid @enderror"
                                   placeholder="Brief summary of your issue"
                                   value="{{ old('subject') }}" maxlength="150">
                            <div class="d-flex justify-content-between mt-1">
                                @error('subject')
                                <div class="text-danger" style="font-size:0.75rem;">{{ $message }}</div>
                                @else
                                <div></div>
                                @enderror
                                <span class="char-counter" id="subjectCounter">0 / 150</span>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Category <span class="text-danger">*</span></label>
                                <select name="category" class="form-select form-select-sm @error('category') is-invalid @enderror">
                                    <option value="">-- Select Category --</option>
                                    @foreach($categories as $cat)
                                    <option value="{{ $cat }}" {{ old('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                    @endforeach
                                </select>
                                @error('category')
                                <div class="text-danger mt-1" style="font-size:0.75rem;">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" id="descriptionField"
                                      class="form-control form-control-sm @error('description') is-invalid @enderror"
                                      rows="6"
                                      placeholder="Describe your issue in detail. Include any relevant steps, error messages, or context…"
                                      maxlength="3000">{{ old('description') }}</textarea>
                            <div class="d-flex justify-content-between mt-1">
                                @error('description')
                                <div class="text-danger" style="font-size:0.75rem;">{{ $message }}</div>
                                @else
                                <div></div>
                                @enderror
                                <span class="char-counter" id="descCounter">0 / 3000</span>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- ── Priority ── --}}
                <div class="card mb-3">
                    <div class="card-header card-no-border py-2">
                        <div class="section-label">Priority</div>
                    </div>
                    <div class="card-body pb-3">
                        <div class="row g-2">

                            @php
                            $priorities = [
                                'low'    => ['label' => 'Low',    'desc' => 'General question or minor inconvenience.'],
                                'medium' => ['label' => 'Medium', 'desc' => 'Issue affecting your work but a workaround exists.'],
                                'high'   => ['label' => 'High',   'desc' => 'Significant impact, no viable workaround.'],
                                'urgent' => ['label' => 'Urgent', 'desc' => 'Critical issue, system down or data at risk.'],
                            ];
                            @endphp

                            @foreach($priorities as $val => $p)
                            <div class="col-md-6">
                                <label class="priority-option {{ old('priority', 'medium') === $val ? 'selected' : '' }}" id="label_{{ $val }}">
                                    <input type="radio" name="priority" value="{{ $val }}"
                                           {{ old('priority', 'medium') === $val ? 'checked' : '' }}
                                           class="priority-radio">
                                    <span class="priority-dot priority-{{ $val }}" style="margin-top:3px;"></span>
                                    <div>
                                        <div style="font-size:0.82rem; font-weight:600;">{{ $p['label'] }}</div>
                                        <div style="font-size:0.72rem; color:#6c757d;">{{ $p['desc'] }}</div>
                                    </div>
                                </label>
                            </div>
                            @endforeach

                        </div>
                        @error('priority')
                        <div class="text-danger mt-1" style="font-size:0.75rem;">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- ── Attachments ── --}}
                <div class="card mb-3">
                    <div class="card-header card-no-border py-2">
                        <div class="section-label">Attachments <span class="text-muted fw-normal" style="font-size:0.65rem;">(optional — max 5 files, 5MB each)</span></div>
                    </div>
                    <div class="card-body pb-3">

                        <div class="drop-zone" id="dropZone">
                            <span class="material-symbols-outlined">upload_file</span>
                            Drag & drop files here, or <strong>click to browse</strong>
                        </div>
                        <input type="file" name="attachments[]" id="fileInput" multiple class="d-none"
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.csv">

                        <div id="fileList" class="d-flex flex-wrap gap-1 mt-2"></div>

                        @error('attachments.*')
                        <div class="text-danger mt-1" style="font-size:0.75rem;">{{ $message }}</div>
                        @enderror

                    </div>
                </div>

                {{-- ── Submit ── --}}
                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('tickets.my') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
                    <button type="submit" id="submitBtn"
                            class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                        <span class="btn-label d-flex align-items-center gap-1">
                            <span class="material-symbols-outlined" style="font-size:15px;">send</span> Submit Ticket
                        </span>
                        <span class="btn-spinner d-none">
                            <span class="spinner-border spinner-border-sm"></span> Submitting…
                        </span>
                    </button>
                </div>

            </form>
        </div>
    </div>

</div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function () {

    // ── Character counters ──────────────────────────────────────────
    function updateCounter(input, counterId, max) {
        const len = $(input).val().length;
        const el  = $(counterId);
        el.text(len + ' / ' + max);
        el.toggleClass('near-limit', len > max * 0.8 && len < max);
        el.toggleClass('at-limit', len >= max);
    }

    $('[name="subject"]').on('input', function () { updateCounter(this, '#subjectCounter', 150); }).trigger('input');
    $('#descriptionField').on('input', function () { updateCounter(this, '#descCounter', 3000); }).trigger('input');

    // ── Priority radio highlight ────────────────────────────────────
    $('.priority-radio').on('change', function () {
        $('.priority-option').removeClass('selected');
        $(this).closest('.priority-option').addClass('selected');
    });

    // ── File drop zone ──────────────────────────────────────────────
    let selectedFiles = [];

    $('#dropZone').on('click', function () { $('#fileInput').click(); });

    $('#dropZone').on('dragover', function (e) {
        e.preventDefault();
        $(this).addClass('dragover');
    }).on('dragleave', function () {
        $(this).removeClass('dragover');
    }).on('drop', function (e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        addFiles(e.originalEvent.dataTransfer.files);
    });

    $('#fileInput').on('change', function () {
        addFiles(this.files);
        this.value = '';
    });

    function addFiles(fileList) {
        Array.from(fileList).forEach(function (f) {
            if (selectedFiles.length >= 5) { toastr.warning('Maximum 5 attachments allowed'); return; }
            if (f.size > 5 * 1024 * 1024)  { toastr.warning(f.name + ' exceeds 5MB limit');   return; }
            if (selectedFiles.find(x => x.name === f.name)) return; // dedupe
            selectedFiles.push(f);
        });
        renderFileList();
    }

    function renderFileList() {
        const list = $('#fileList');
        list.empty();

        const dt = new DataTransfer();
        selectedFiles.forEach(function (f, i) {
            dt.items.add(f);
            list.append(
                $('<span class="file-chip">')
                    .append('<span class="material-symbols-outlined" style="font-size:13px;">description</span>')
                    .append(document.createTextNode(f.name))
                    .append(
                        $('<span class="material-symbols-outlined remove-file">close</span>')
                            .on('click', function () {
                                selectedFiles.splice(i, 1);
                                renderFileList();
                            })
                    )
            );
        });

        // Sync to actual input
        $('#fileInput')[0].files = dt.files;
    }

    // ── Submit button busy state ────────────────────────────────────
    $('#createTicketForm').on('submit', function () {
        const subject = $('[name="subject"]').val().trim();
        const desc    = $('#descriptionField').val().trim();
        const cat     = $('[name="category"]').val();
        if (!subject) { toastr.warning('Please enter a subject'); return false; }
        if (!cat)     { toastr.warning('Please select a category'); return false; }
        if (!desc)    { toastr.warning('Please enter a description'); return false; }

        $('#submitBtn .btn-label').addClass('d-none');
        $('#submitBtn .btn-spinner').removeClass('d-none');
        $('#submitBtn').prop('disabled', true);
    });

});
</script>
@endsection
