@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <div>
                            <h5 class="mb-0">{{ $material->title }}</h5>
                            <span class="text-muted small">Version: {{ $material->version }} | Type: {{ strtoupper($material->file_type) }}</span>
                        </div>
                        <div class="ms-auto">
                            <a href="{{ route('hr.training.index') }}" class="btn btn-light btn-sm">Back to List</a>
                        </div>
                    </div>
                    <div class="card-body">
                        
                        @if($material->description)
                        <div class="alert alert-light border mb-3">
                            <strong>Description:</strong> {{ $material->description }}
                        </div>
                        @endif

                        <!-- Scrollable Document Container -->
                        <div id="document-scroll-container" style="height: 500px; overflow-y: scroll; border: 1px solid #ced4da; border-radius: 4px; padding: 25px; background: #fff; margin-bottom: 20px;">
                            
                            @if($material->file_type === 'txt')
                                {!! $fileContent !!}
                            @elseif(in_array($material->file_type, ['pdf', 'doc', 'docx']))
                                <!-- Fallback preview / PDF iframe or rendered content block -->
                                <div class="text-center py-4">
                                    <p class="text-muted mb-3">Previewing document stream below. Please scroll through the frame completely.</p>
                                </div>
                                <iframe src="{{ route('hr.training.stream', $material->id) }}" width="100%" height="600px" style="border: none;"></iframe>
                            @else
                                <div class="text-center py-5">
                                    <span class="material-symbols-outlined fs-1 text-primary">download</span>
                                    <p class="mt-2">This file format requires downloading to review fully.</p>
                                    <a href="{{ route('hr.training.stream', $material->id) }}" class="btn btn-outline-primary btn-sm">Download File</a>
                                </div>
                            @endif

                            <div class="text-center mt-5 py-3 border-top text-muted">
                                <em>[ End of Training Material Preview ]</em>
                            </div>
                        </div>

                        <!-- Instruction Alert -->
                        <div class="alert alert-warning d-flex align-items-center mb-3" id="reading-status-alert">
                            <span class="material-symbols-outlined me-2">info</span>
                            <span id="reading-instruction-text">Please scroll down to the bottom of the document viewer to unlock the acknowledgment button.</span>
                        </div>

                        <!-- Acknowledge Form -->
                        @php
                            $user = Auth::user();
                            $staff = $user->linkedStaff();
                        @endphp

                        @if(!$staff)
    <div class="alert alert-danger">Your login is not linked to a staff record, so you cannot acknowledge training.</div>
@elseif($done)
    <div class="alert alert-success d-flex align-items-center">
        <span class="material-symbols-outlined me-2">check_circle</span>
        <div>You have already completed and acknowledged this training material.</div>
    </div>
@else
    <!-- Notice we added id="auto-ack-form" -->
    <form id="auto-ack-form" action="{{ route('hr.training.acknowledge', $material->id) }}" method="POST">
        @csrf
        <button type="submit" id="acknowledge-btn" class="btn btn-success w-100 py-2" disabled>
            <span class="material-symbols-outlined align-middle me-1">task_alt</span> 
            <span id="btn-text">Scroll to the bottom to automatically record completion</span>
        </button>
    </form>
@endif

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const scrollContainer = document.getElementById('document-scroll-container');
    const acknowledgeBtn = document.getElementById('acknowledge-btn');
    const btnText = document.getElementById('btn-text');
    const statusAlert = document.getElementById('reading-status-alert');
    const instructionText = document.getElementById('reading-instruction-text');
    const ackForm = document.getElementById('auto-ack-form');
    
    let isTriggered = false;

    if (scrollContainer && ackForm && !isTriggered) {
        scrollContainer.addEventListener('scroll', function() {
            if (isTriggered) return;

            // Threshold of 15px for safe edge-detection
            const threshold = 15;
            const currentPosition = scrollContainer.scrollTop + scrollContainer.clientHeight;
            const totalHeight = scrollContainer.scrollHeight;

            if (currentPosition >= totalHeight - threshold) {
                isTriggered = true;
                
                // Update UI feedback immediately
                statusAlert.classList.remove('alert-warning');
                statusAlert.classList.add('alert-success');
                instructionText.innerHTML = '<strong>Document fully read!</strong> Automatically recording your acknowledgment...';
                
                acknowledgeBtn.classList.remove('btn-success');
                acknowledgeBtn.classList.add('btn-primary');
                btnText.innerHTML = 'Saving completion record...';
                acknowledgeBtn.removeAttribute('disabled');

                // Automatically submit the form after a brief 1-second pause
                setTimeout(function() {
                    ackForm.submit();
                }, 1000);
            }
        });
    }
</script>
@endsection