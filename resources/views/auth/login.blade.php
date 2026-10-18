@extends('layouts.auth')
@section('title', 'Login')

@push('styles')
<style>
    :root {
        --cal-navy: #10275c;
        --cal-navy-hover: #091a40;
        --cal-cyan: #0798cc;
        --cal-cyan-hover: #0579aa;
        --input-border: #d8e2f0;
        --label-color: #263b61;
    }

    * { box-sizing: border-box; }

    html, body {
        margin: 0;
        min-height: 100%;
    }

    body {
        min-height: 100vh;
        padding: 0;
        background: #eaf1f8;
        color: var(--cal-navy);
        font-family: 'Inter', 'Segoe UI', sans-serif;
        -webkit-font-smoothing: antialiased;
    }

    .login-wrapper {
        min-height: 100vh;
        width: 100%;
        display: grid;
        place-items: center;
        padding: 24px 16px;
        position: relative;
        isolation: isolate;
        background:
            linear-gradient(135deg, rgba(9, 26, 64, .18), rgba(7, 152, 204, .08)),
            url("{{ asset('assets/images/bg images/city-bg.jpeg') }}") center center / cover no-repeat;
    }

    .template-card {
        width: 100%;
        max-width: 820px;          /* was 1060px */
        min-height: 480px;         /* was 580px */
        display: flex;
        position: relative;
        overflow: hidden;
        background: #fff;
        border: 1px solid rgba(255, 255, 255, .8);
        border-radius: 20px;
        box-shadow: 0 24px 60px rgba(9, 26, 64, .20), 0 6px 18px rgba(9, 26, 64, .07);
        animation: loginFadeIn .5s ease both;
    }

    .card-visual-side {
        flex: 0 0 40%;
        min-width: 0;
        min-height: 480px;
        position: relative;
        overflow: hidden;
        padding: 48px 32px;
        color: #fff;
        background:
            linear-gradient(160deg, rgba(10, 38, 83, .58), rgba(0, 120, 170, .16) 55%, rgba(7, 29, 66, .55)),
            url("{{ asset('assets/images/bg images/city-bg.jpeg') }}") center center / cover no-repeat;
    }

    .card-visual-side::after {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(180deg, #39d6e8, var(--cal-cyan), #075d9c);
    }

    .visual-eyebrow {
        position: relative;
        z-index: 1;
        display: block;
        margin-bottom: 10px;
        color: #a9f0fa;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 2.5px;
        text-transform: uppercase;
    }

    .visual-heading {
        position: relative;
        z-index: 1;
        max-width: 280px;
        margin: 0 0 12px;
        color: #fff;
        font-size: clamp(22px, 2.6vw, 28px);
        font-weight: 750;
        line-height: 1.2;
        letter-spacing: -.6px;
    }

    .visual-description {
        position: relative;
        z-index: 1;
        max-width: 260px;
        margin: 0;
        color: rgba(255, 255, 255, .9);
        font-size: 13px;
        line-height: 1.7;
    }

    .visual-accent-line {
        position: relative;
        z-index: 1;
        width: 44px;
        height: 3px;
        margin-top: 18px;
        border-radius: 10px;
        background: #26c6da;
    }

    .card-form-side {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 36px clamp(24px, 4vw, 48px);
        position: relative;
        z-index: 2;
        background: #fff;
    }

    .curve-divider {
        position: absolute;
        top: 0;
        bottom: 0;
        left: -1px;
        width: 22px;
        height: 100%;
        pointer-events: none;
        z-index: 4;
    }

    .curve-divider svg {
        display: block;
        width: 100%;
        height: 100%;
    }

    .login-form-content {
        width: 100%;
        max-width: 340px;
        margin: 0 auto;
    }

    .brand-header {
        margin-bottom: 22px;
        text-align: center;
    }

    .brand-logo {
        display: block;
        width: auto;
        max-width: 150px;
        max-height: 58px;
        margin: 0 auto 18px;
        object-fit: contain;
    }

    .login-heading {
        margin: 0 0 6px;
        color: var(--cal-navy);
        font-size: 24px;
        font-weight: 750;
        line-height: 1.3;
        letter-spacing: -.5px;
    }

    .login-subtitle {
        margin: 0;
        color: #718096;
        font-size: 12.5px;
        line-height: 1.6;
    }

    #loginForm .mb-3 { margin-bottom: 16px !important; }

    .form-field-label {
        display: inline-block;
        margin-bottom: 6px;
        color: var(--label-color);
        font-size: 12.5px;
        font-weight: 650;
    }

    .custom-input-group {
        display: flex;
        align-items: center;
        min-height: 44px;
        padding: 0 12px;
        overflow: hidden;
        background: #f8faff;
        border: 1px solid var(--input-border);
        border-radius: 9px;
        transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
    }

    .custom-input-group:hover { border-color: #a7bfdc; }

    .custom-input-group:focus-within {
        background: #fff;
        border-color: var(--cal-cyan);
        box-shadow: 0 0 0 3px rgba(7, 152, 204, .12);
    }

    .custom-input-group .input-group-text {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        padding: 0 6px 0 0;
        color: #6880a5;
        background: transparent;
        border: 0;
    }

    .custom-input-group .form-control {
        width: 100%;
        min-width: 0;
        min-height: 42px;
        padding: 8px 4px;
        color: #172b4d;
        background: transparent;
        border: 0;
        outline: 0;
        box-shadow: none !important;
        font-size: 13px;
    }

    .custom-input-group .form-control:focus {
        background: transparent;
        border: 0;
    }

    .custom-input-group .form-control::placeholder {
        color: #9aa9bf;
        font-size: 13px;
    }

    .custom-input-group .show-hide {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 32px;
        padding-left: 6px;
        color: #6880a5;
        font-size: 11.5px;
        cursor: pointer;
    }

    .login-options {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        margin: 4px 0 20px;
        font-size: 12px;
    }

    .form-check {
        display: flex;
        align-items: center;
        gap: 7px;
        min-height: auto;
        margin: 0;
        padding-left: 0;
    }

    .form-check-input {
        flex-shrink: 0;
        width: 15px;
        height: 15px;
        margin: 0 !important;
        border: 1px solid #cbd5e1;
        cursor: pointer;
        box-shadow: none !important;
    }

    .form-check-input:checked {
        background-color: var(--cal-cyan);
        border-color: var(--cal-cyan);
    }

    .form-check-label {
        margin: 0 !important;
        color: #64748b;
        font-size: 12px;
        font-weight: 400 !important;
        cursor: pointer;
    }

    .cal-link {
        color: var(--cal-cyan-hover);
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: color .2s ease;
    }

    .cal-link:hover {
        color: var(--cal-navy);
        text-decoration: underline;
    }

    .btn-template-primary {
        width: 100%;
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 11px 18px;
        color: #fff;
        background: linear-gradient(135deg, var(--cal-cyan), #087fba);
        border: 1px solid transparent;
        border-radius: 9px;
        box-shadow: 0 6px 14px rgba(7, 152, 204, .20);
        font-size: 13.5px;
        font-weight: 700;
        letter-spacing: .2px;
        transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
    }

    .btn-template-primary:hover,
    .btn-template-primary:focus {
        color: #fff;
        background: linear-gradient(135deg, var(--cal-cyan-hover), var(--cal-navy));
        border-color: transparent;
        box-shadow: 0 8px 18px rgba(7, 100, 150, .23);
        transform: translateY(-1px);
    }

    .btn-template-primary:active { transform: translateY(0); }

    .btn-template-primary:disabled {
        opacity: .7;
        cursor: not-allowed;
        transform: none;
    }

    #loginForm .invalid-feedback {
        display: block;
        margin-top: 5px;
        font-size: 11.5px;
    }

    #loginForm .alert {
        border-radius: 8px;
        font-size: 12.5px;
    }

    .login-footer {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 20px;
        color: #91a0b8;
        font-size: 9.5px;
        font-weight: 700;
        letter-spacing: 1.2px;
        text-transform: uppercase;
    }

    .login-footer::before,
    .login-footer::after {
        content: "";
        width: 24px;
        height: 1px;
        background: #dce5f0;
    }

    @keyframes loginFadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Tablet */
    @media (max-width: 900px) {
        .template-card {
            max-width: 680px;
            min-height: 460px;
        }
        .card-visual-side {
            flex-basis: 36%;
            min-height: 460px;
            padding: 36px 22px;
        }
        .card-form-side {
            padding: 32px 28px;
        }
        .visual-heading { font-size: 23px; }
        .curve-divider { display: none; }
    }

    /* Mobile */
    @media (max-width: 640px) {
        .login-wrapper {
            padding: 14px 12px;
            background-position: center;
        }
        .template-card {
            max-width: 400px;
            min-height: auto;
            flex-direction: column;
            border-radius: 16px;
        }
        .card-visual-side {
            flex: none;
            min-height: 130px;
            padding: 22px 20px;
            background-position: center 60%;
        }
        .visual-eyebrow {
            margin-bottom: 6px;
            font-size: 9px;
            letter-spacing: 2px;
        }
        .visual-heading {
            max-width: 100%;
            margin-bottom: 6px;
            font-size: 20px;
        }
        .visual-description {
            max-width: 100%;
            font-size: 12px;
            line-height: 1.5;
        }
        .visual-accent-line {
            width: 36px;
            height: 3px;
            margin-top: 10px;
        }
        .card-visual-side::after {
            top: auto;
            right: 0;
            bottom: 0;
            width: 100%;
            height: 3px;
        }
        .card-form-side {
            padding: 26px 20px;
        }
        .brand-header { margin-bottom: 18px; }
        .brand-logo {
            max-width: 130px;
            max-height: 52px;
            margin-bottom: 14px;
        }
        .login-heading { font-size: 22px; }
        .login-options { align-items: flex-start; }
        .login-footer { margin-top: 18px; }
    }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            animation-duration: .01ms !important;
            transition-duration: .01ms !important;
        }
    }
</style>
@endpush

@section('content')
<div class="login-wrapper">
    <div class="template-card">

        {{-- Left visual panel --}}
        <div class="card-visual-side">
            <span class="visual-eyebrow">Welcome to</span>
            <h2 class="visual-heading">Chabrin Agencies Ltd</h2>
            <p class="visual-description">
                Productivity Redefined for Success
            </p>
            <div class="visual-accent-line"></div>
        </div>

        {{-- Right login panel --}}
        <div class="card-form-side">
            <div class="curve-divider" aria-hidden="true">
                <svg viewBox="0 0 100 100" preserveAspectRatio="none">
                    <path d="M100,0 C28,25 28,75 100,100 Z" fill="#ffffff"></path>
                </svg>
            </div>

            <div class="login-form-content">
                <div class="brand-header">
                    <img
                        src="{{ asset('assets/images/logo/logo1.png') }}"
                        alt="Chabrin Agencies Ltd"
                        class="brand-logo"
                    >

                    <h1 class="login-heading">Welcome Back</h1>
                    <p class="login-subtitle">Log in to access your portal account</p>
                </div>

                <form class="theme-form" id="loginForm" method="POST" action="{{ route('login') }}">
                    @csrf

                    <div id="alert-container"></div>

                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">
                            Please check your login details and try again.
                        </div>
                    @endif

                    {{-- Email field --}}
                    <div class="mb-3">
                        <label for="user_email" class="form-label form-field-label">Email Address</label>
                        <div class="input-group custom-input-group">
                            <span class="input-group-text" aria-hidden="true">
                                <i class="fa fa-envelope-o"></i>
                            </span>
                            <input
                                class="form-control"
                                type="email"
                                id="user_email"
                                name="user_email"
                                value="{{ old('user_email') }}"
                                required
                                autofocus
                                placeholder="name@company.com"
                                autocomplete="username"
                            >
                        </div>
                        @error('user_email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Password field --}}
                    <div class="mb-3">
                        <label for="user_password" class="form-label form-field-label">Password</label>
                        <div class="input-group custom-input-group position-relative">
                            <span class="input-group-text" aria-hidden="true">
                                <i class="fa fa-lock"></i>
                            </span>
                            <input
                                class="form-control pe-5"
                                type="password"
                                id="user_password"
                                name="user_password"
                                required
                                placeholder="Enter your password"
                                autocomplete="current-password"
                            >
                            <div
                                class="show-hide position-absolute top-50 end-0 translate-middle-y me-3"
                                style="z-index: 10;"
                                role="button"
                                tabindex="0"
                                aria-label="Toggle password visibility"
                                aria-controls="user_password"
                            >
                                <span class="show small text-muted">Show</span>
                            </div>
                        </div>
                        @error('user_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Remember me and forgot password --}}
                    <div class="login-options">
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="remember"
                                name="remember"
                                value="1"
                                {{ old('remember') ? 'checked' : '' }}
                            >
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>

                        <a class="cal-link" href="{{ route('password.request') }}">
                            Forgot password?
                        </a>
                    </div>

                    {{-- Submit button --}}
                    <div class="d-grid">
                        <button class="btn btn-template-primary" id="loginBtn" type="submit">
                            <span id="loginBtnText">Login</span>
                            <span
                                id="loginSpinner"
                                class="spinner-border spinner-border-sm d-none"
                                role="status"
                                aria-hidden="true"
                            ></span>
                        </button>
                    </div>
                </form>

                <div class="login-footer">Secure Access</div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(function() {
        // Show/hide password toggle
        $('.show-hide').on('click', function() {
            var passwordInput = $(this).closest('.position-relative').find('input');
            var showIcon = $(this).find('.show');
            if (passwordInput.attr('type') === 'password') {
                passwordInput.attr('type', 'text');
                showIcon.addClass('active');
            } else {
                passwordInput.attr('type', 'password');
                showIcon.removeClass('active');
            }
        });

        // AJAX login handler
        $('#loginForm').on('submit', function(e) {
            e.preventDefault();
            $('#alert-container').html('');
            $('#loginBtnText').text('Processing');
            $('#loginSpinner').removeClass('d-none');
            $('#loginBtn').prop('disabled', true);

            var payload = (function() {
                var formData = $($('#loginForm')[0]).serializeArray();
                formData.push({
                    name: '_token',
                    value: '{{ csrf_token() }}'
                });
                return $.param(formData);
            })();

            $.ajax({
                url: '/login/submit',
                type: 'POST',
                data: payload,
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                success: function(response) {
                    var alertHtml = '';
                    if (response.status === 'error') {
                        alertHtml = '<div class="alert alert-danger alert-dismissible fade show" role="alert"><strong>Error!</strong> ' + response.message + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                        $('#loginBtnText').text('Sign in');
                        $('#loginSpinner').addClass('d-none');
                        $('#loginBtn').prop('disabled', false);
                    } else if (response.status === '2fa' || response.status === '2fa-setup') {
                        alertHtml = '<div class="alert alert-info alert-dismissible fade show" role="alert"><strong>Info!</strong> ' + response.message + ' Proceeding to 2FA...</div>';
                        $('#loginBtnText').text('2FA Required...');
                        $('#loginSpinner').removeClass('d-none');
                        setTimeout(function() {
                            try {
                                if (response && response.redirect) {
                                    window.location.href = response.redirect;
                                } else if (response && response.two_factor_token) {
                                    window.location.href = '/authenticator/' + response.two_factor_token;
                                }
                            } catch (e) {
                                console.error('Error during redirect to 2FA', e, response);
                            }
                        }, 700);
                    } else if (response.status === 'success') {
                        alertHtml = '<div class="alert alert-success alert-dismissible fade show" role="alert"><strong>Success!</strong> ' + response.message + ' Proceeding...</div>';
                        $('#loginBtnText').text('Proceeding...');
                        $('#loginSpinner').removeClass('d-none');
                        setTimeout(function() {
                            window.location.href = response.redirect || '/dashboard';
                        }, 1200);
                    }
                    $('#alert-container').html(alertHtml);
                },
                error: function() {
                    $('#alert-container').html('<div class="alert alert-danger alert-dismissible fade show" role="alert"><strong>Error!</strong> Server error. Please try again.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
                    $('#loginBtnText').text('Sign in');
                    $('#loginSpinner').addClass('d-none');
                    $('#loginBtn').prop('disabled', false);
                }
            });
        });
    });
</script>
@endpush
@endsection