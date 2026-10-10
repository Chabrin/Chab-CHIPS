@extends('layouts.auth')
@section('title', 'Forgot Password')

@push('styles')
<style>
    :root {
        --fp-blue: #007bff;
        --fp-blue-hover: #0069d9;
        --fp-text-dark: #0f172a;
        --fp-input-bg: #eef2f6;
        --fp-input-border: #cbd5e1;
        --fp-shape-accent: #007bff;
        --fp-shape-light: #dbeafe;
    }

    body {
        margin: 0;
        padding: 0;
        min-height: 100vh;
        background-color: #f1f5f9;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    /* Screen Wrapper */
    .forgot-page-wrapper {
        min-height: 100vh;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
    }

    /* Outer White Card with Clean Elevation */
    .forgot-card {
        width: 100%;
        max-width: 920px;
        min-height: 520px;
        background: #ffffff;
        border-radius: 20px;
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08), 0 4px 12px rgba(15, 23, 42, 0.04);
        position: relative;
        overflow: hidden;
        display: flex;
    }

    /* Organic Corner Shapes matching the reference design */
    .corner-shape-tl {
        position: absolute;
        top: 0;
        left: 0;
        width: 160px;
        height: 160px;
        background: var(--fp-shape-light);
        clip-path: polygon(0 0, 100% 0, 0 85%);
        pointer-events: none;
        z-index: 1;
        opacity: 0.7;
    }

    .corner-shape-tr {
        position: absolute;
        top: -60px;
        right: -50px;
        width: 250px;
        height: 200px;
        background: var(--fp-shape-light);
        border-radius: 60% 40% 50% 50% / 40% 50% 50% 60%;
        pointer-events: none;
        z-index: 1;
        opacity: 0.75;
    }

    .corner-shape-bl {
        position: absolute;
        bottom: -55px;
        left: -45px;
        width: 290px;
        height: 180px;
        background: var(--fp-shape-accent);
        border-radius: 40% 60% 40% 60% / 50% 60% 40% 50%;
        transform: rotate(-10deg);
        pointer-events: none;
        z-index: 1;
        opacity: 0.95;
    }

    /* Left Column: Illustration */
    .forgot-illustration-pane {
        flex: 1.15;
        display: flex;
        align-items: flex-end; /* Ground the character neatly to the baseline */
        justify-content: center;
        padding: 2.5rem 2rem 2.5rem;
        position: relative;
        z-index: 2;
    }

    .forgot-illustration-pane img {
        max-width: 95%;
        max-height: 400px;
        object-fit: contain;
    }

    /* Right Column: Form */
    .forgot-form-pane {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 3rem 4rem 3rem 2rem;
        position: relative;
        z-index: 2;
    }

    /* Brand Logo */
    .brand-logo-wrap {
        margin-bottom: 1.5rem;
        text-align: left;
    }

    .brand-logo {
        max-width: 200px;
        height: auto;
        object-fit: contain;
    }

    /* Stacked Heading */
    .forgot-title {
        font-size: 32px;
        font-weight: 800;
        color: var(--fp-text-dark);
        line-height: 1.2;
        letter-spacing: -0.025em;
        margin-bottom: 2rem;
        text-align: left;
    }

    .forgot-title span {
        display: block;
    }

    /* Soft Muted Input */
    .fp-input {
        background-color: var(--fp-input-bg) !important;
        border: 1px solid var(--fp-input-border) !important;
        border-radius: 8px !important;
        padding: 0.85rem 1.25rem !important;
        font-size: 0.92rem !important;
        color: #1e293b !important;
        box-shadow: none !important;
        transition: all 0.2s ease !important;
    }

    .fp-input::placeholder {
        color: #64748b !important; /* Clear readable contrast */
        font-size: 0.9rem !important;
    }

    .fp-input:focus {
        background-color: #ffffff !important;
        border-color: var(--fp-blue) !important;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15) !important;
    }

    /* Vibrant Blue Action Button */
    .btn-fp-action {
        background-color: var(--fp-blue) !important;
        border: none !important;
        color: #ffffff !important;
        font-size: 0.84rem !important;
        font-weight: 700 !important;
        letter-spacing: 0.05em !important;
        text-transform: uppercase;
        border-radius: 8px !important;
        padding: 0.82rem 1.5rem !important;
        transition: all 0.2s ease !important;
        box-shadow: 0 4px 14px rgba(0, 123, 255, 0.25) !important;
        width: 100%;
    }

    .btn-fp-action:hover {
        background-color: var(--fp-blue-hover) !important;
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(0, 123, 255, 0.35) !important;
    }

    /* Back to sign in subtle text link */
    .fp-back-link {
        color: #94a3b8;
        font-size: 0.85rem;
        font-weight: 500;
        text-decoration: none;
        transition: color 0.15s ease;
    }

    .fp-back-link:hover {
        color: var(--fp-blue);
        text-decoration: underline;
    }

    /* Mobile / Tablet Responsive */
    @media (max-width: 850px) {
        .forgot-card {
            flex-direction: column;
            max-width: 440px;
        }

        .forgot-illustration-pane {
            padding: 2.5rem 1.5rem 0.5rem;
        }

        .forgot-illustration-pane img {
            max-height: 240px;
        }

        .forgot-form-pane {
            padding: 1.5rem 2rem 2.5rem;
        }

        .brand-logo-wrap,
        .forgot-title {
            text-align: center;
        }

        .forgot-title {
            font-size: 26px;
            margin-bottom: 1.5rem;
        }
    }
</style>
@endpush

@section('content')
<div class="forgot-page-wrapper">
    <div class="forgot-card">
        
        {{-- Background decorative corner shapes matching template --}}
        <div class="corner-shape-tl"></div>
        <div class="corner-shape-tr"></div>
        <div class="corner-shape-bl"></div>

        {{-- Left: Illustration Pane --}}
        <div class="forgot-illustration-pane">
            <img src="{{ asset('assets/images/login/forgot-password-illustration.jpeg') }}" 
                 alt="Forgot Password Illustration">
        </div>

        {{-- Right: Form Pane --}}
        <div class="forgot-form-pane">
            
            {{-- Top Logo (Cleanly Left-Aligned) --}}
            <div class="brand-logo-wrap">
                <img src="{{ asset('assets/images/logo/logo1.png') }}" 
                     alt="Chabrin Agencies Ltd" 
                     class="brand-logo">
            </div>

            {{-- Stacked Heading --}}
            <h2 class="forgot-title">
                <span>Forgot</span>
                <span>Your Password ?</span>
            </h2>

            <form id="forgotPasswordForm" method="POST" action="{{ route('password.email') ?? '#' }}">
                @csrf
                <div id="alert-container"></div>

                {{-- Email Input --}}
                <div class="mb-3">
                    <input class="form-control fp-input" 
                           type="email" 
                           id="email" 
                           name="email" 
                           required 
                           placeholder="Email Address" 
                           autocomplete="email">
                </div>

                {{-- Reset Button --}}
                <div class="mb-3">
                    <button class="btn btn-fp-action" id="forgotBtn" type="submit">
                        <span id="forgotBtnText">Reset Password</span>
                        <span id="forgotSpinner" class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
                    </button>
                </div>

                {{-- Back to signin --}}
                <div class="text-center pt-1">
                    <a class="fp-back-link" href="{{ route('login') }}">Back to login</a>
                </div>
            </form>

        </div>
    </div>
</div>

@push('scripts')
<script>
    $(function() {
        // Initialize Feather icons
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        // AJAX forgot password
        $('#forgotPasswordForm').on('submit', function(e) {
            e.preventDefault();
            $('#alert-container').html('');
            $('#forgotBtnText').text('Processing');
            $('#forgotSpinner').removeClass('d-none');
            $('#forgotBtn').prop('disabled', true);
            $.ajax({
                url: '/forgot-password',
                type: 'POST',
                data: (function() {
                    var formData = $($('#forgotPasswordForm')[0]).serializeArray();
                    formData.push({
                        name: '_token',
                        value: '{{ csrf_token() }}'
                    });
                    return $.param(formData);
                })(),
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                success: function(response) {
                    let alertHtml = '';
                    if (response.status === 'error') {
                        alertHtml = `<div class=\"alert alert-danger alert-dismissible fade show\" role=\"alert\"><strong>Error!</strong> ${response.message}<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button></div>`;
                        $('#forgotBtnText').text('Send Reset Link');
                        $('#forgotSpinner').addClass('d-none');
                        $('#forgotBtn').prop('disabled', false);
                    } else if (response.status === 'success') {
                        alertHtml = `<div class=\"alert alert-success alert-dismissible fade show\" role=\"alert\"><strong>Success!</strong> ${response.message}</div>`;
                        $('#forgotBtnText').text('Email Sent');
                        $('#forgotSpinner').addClass('d-none');
                        $('#forgotBtn').prop('disabled', true);
                        // Reset form after 3 seconds
                        setTimeout(function() {
                            $('#forgotBtnText').text('Send Reset Link');
                            $('#forgotBtn').prop('disabled', false);
                            $('#forgotPasswordForm')[0].reset();
                            $('#alert-container').html('');
                        }, 3000);
                    }
                    $('#alert-container').html(alertHtml);
                },
                error: function(xhr) {
                    $('#alert-container').html('<div class=\"alert alert-danger alert-dismissible fade show\" role=\"alert\"><strong>Error!</strong> Server error. Please try again.<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button></div>');
                    $('#forgotBtnText').text('Send Reset Link');
                    $('#forgotSpinner').addClass('d-none');
                    $('#forgotBtn').prop('disabled', false);
                }
            });
        });
    });
</script>
@endpush
@endsection