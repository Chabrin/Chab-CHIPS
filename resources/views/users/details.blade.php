@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">System User - {{ $user->name }}</h5>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('system.users') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <style>
                            .info-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #e9ecef; }
                            .info-row:last-child { border-bottom: none; }
                            .info-label { color: #6c757d; font-size: 0.875rem; flex-shrink: 0; }
                            .info-value { font-weight: 500; display: inline-flex; align-items: center; gap: 6px; text-align: right; word-break: break-word; }
                            .copy-icon { cursor: pointer; color: #5aa9e0; font-size: 16px; line-height: 1; flex-shrink: 0; transition: color 0.15s ease; }
                            .copy-icon:hover { color: #3598db; }
                            .copy-icon.copied { color: #3598db; }
                        </style>
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <div class="card h-100">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0" style="font-size:0.9rem;">System User Details</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="info-row">
                                            <span class="info-label">Full Name:</span>
                                            <span class="info-value">{{ $user->name ?? '-' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Username:</span>
                                            <span class="info-value">{{ $user->username ?? '-' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Email:</span>
                                            <span class="info-value">
                                                {{ $user->email ?? '-' }}
                                                @if($user->email ?? null)
                                                <span class="material-symbols-outlined copy-icon" data-copy="{{ $user->email }}" title="Copy">content_copy</span>
                                                @endif
                                            </span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Role:</span>
                                            <span class="info-value">{{ $user->role_name ?? '-' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Status:</span>
                                            <span class="info-value">
                                                @if($user->block == 0)
                                                    <span class="badge badge-light-success">Active</span>
                                                @else
                                                    <span class="badge badge-light-danger">Suspended</span>
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if($staff)
                            <div class="col-lg-6">
                                <div class="card h-100">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0" style="font-size:0.9rem;">Linked Staff Details</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="info-row">
                                            <span class="info-label">Full Name:</span>
                                            <span class="info-value">{{ $staff->full_names ?? '-' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Staff Number:</span>
                                            <span class="info-value">{{ $staff->staff_number ?? '-' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Status:</span>
                                            <span class="info-value">{{ $staff->status_label ?? '-' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Position:</span>
                                            <span class="info-value">{{ $staff->position_name ?? '-' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Department:</span>
                                            <span class="info-value">{{ $staff->department_name ?? '-' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Zone:</span>
                                            <span class="info-value">{{ $staff->zone_name ?? '-' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Mobile Number:</span>
                                            <span class="info-value">{{ $staff->mobile_number ?? '-' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Email Address:</span>
                                            <span class="info-value">
                                                {{ $staff->email_address ?? '-' }}
                                                @if($staff->email_address ?? null)
                                                <span class="material-symbols-outlined copy-icon" data-copy="{{ $staff->email_address }}" title="Copy">content_copy</span>
                                                @endif
                                            </span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">National ID:</span>
                                            <span class="info-value">{{ $staff->national_id ?? '-' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Hire Date:</span>
                                            <span class="info-value">{{ $staff->hire_date ?? '-' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @else
                            <div class="col-lg-6">
                                <div class="card h-100">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0" style="font-size:0.9rem;">Linked Staff Details</h6>
                                    </div>
                                    <div class="card-body py-2 d-flex align-items-center justify-content-center text-center text-muted" style="min-height:100px;">
                                        <small class="mb-0">This user account is not linked to a staff record.</small>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).on('click', '.copy-icon', function() {
        var $icon = $(this);
        var text = $icon.data('copy');
        if (!text) return;

        function showCopied() {
            var original = $icon.text();
            $icon.addClass('copied').text('check');
            setTimeout(function() {
                $icon.removeClass('copied').text(original);
            }, 1200);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(String(text)).then(showCopied);
        } else {
            var $tmp = $('<textarea>').val(text).appendTo('body').select();
            document.execCommand('copy');
            $tmp.remove();
            showCopied();
        }
    });
</script>
@endsection
