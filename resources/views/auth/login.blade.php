@extends('layouts.auth')
@section('title', 'Login')

@push('styles')
<style>
    :root {
        --cal-navy: #0f172a;
        --cal-navy-hover: #020617;
        --cal-cyan: #0284c7;
        --cal-cyan-hover: #0369a1;
        --input-border: #cbd5e1;
        --label-color: #334155;
    }

    body {
        margin: 0;
        padding: 0;
        min-height: 100vh;
        background-color: #0f172a;
    }

    /* City background with deep contrast overlay */
    .login-wrapper {
        min-height: 100vh;
        width: 100%;
        background: 
            linear-gradient(135deg, rgba(20, 100, 106, 0.82) 0%, rgba(13, 62, 70, 0.92) 100%),
            url("{{ asset('assets/images/bg images/city-bg.jpeg') }}") no-repeat center bottom;
        background-size: cover;
        position: relative;
    }

    
 .login-wrapper {
    min-height: 100vh;
    width: 100%;
    display: grid;
    place-items: center; /* Centers both horizontally & vertically */
    padding: 2rem 1rem;
    box-sizing: border-box;
    box-shadow: 
    0 20px 25px -5px rgba(15, 23, 42, 0.25),
    0 8px 10px -6px rgba(15, 23, 42, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.6);
}
    

.template-card {
    width: 100%;
    max-width: 900px;
    min-height: 520px;
    display: flex; /* Puts image on left, form on right */
    position: relative;
    overflow: hidden;
}

/* Left side background image with teal tint */
.card-visual-side {
    flex: 1.1;
    position: relative;
    background: 
        linear-gradient(135deg, rgba(20, 100, 106, 0.82) 0%, rgba(13, 62, 70, 0.92) 100%),
        url("{{ asset('assets/images/bg images/city-bg.jpeg') }}") center center / cover no-repeat;
    min-height: 100%;
}

/* Right side white form card */
.card-form-side {
    flex: 1;
    background: #ffffff;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 3rem 3rem 2.5rem 2rem;
    position: relative;
    z-index: 2;
}

.curve-divider {
    position: absolute;
    top: 0;
    bottom: 0;
    left: -110px;     
    width: 110px;
    height: 100%;
    pointer-events: none;
    z-index: 4;
}

.curve-divider svg {
    width: 100%;
    height: 100%;
    display: block;
}



.custom-input-group {
    border-radius: 50px;
    padding: 2px 10px;
}

.btn-template-primary {
    border-radius: 50px;
    padding: 0.65rem 3rem;
}

    .brand-logo {
        max-width: 200px;
        height: auto;
        object-fit: contain;
    }

    /* High-contrast crisp input container */
    .custom-input-group {
        background-color: #ffffff;
        border-radius: 8px;
        border: 1.5px solid var(--input-border);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        overflow: hidden;
    }

    .custom-input-group:focus-within {
        border-color: var(--cal-cyan);
        box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
    }

    .custom-input-group .input-group-text {
        background: transparent;
        border: none;
        color: #64748b;
        padding-left: 1rem;
        padding-right: 0.5rem;
    }

    .custom-input-group .form-control {
        background: transparent;
        border: none;
        box-shadow: none !important;
        padding: 0.65rem 0.75rem 0.65rem 0.25rem;
        color: #0f172a;
        font-size: 0.95rem;
    }

    .custom-input-group .form-control::placeholder {
        color: #94a3b8;
        font-size: 0.9rem;
    }

    /* Form labels with proper reading contrast */
    .form-field-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--label-color);
        margin-bottom: 0.35rem;
    }

    /* Styled button */
    .btn-template-primary {
        background-color: var(--cal-cyan);
        border-color: var(--cal-cyan);
        color: #ffffff;
        font-weight: 600;
        border-radius: 8px;
        padding: 0.75rem 1rem;
        box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
        transition: all 0.2s ease;
    }

    .btn-template-primary:hover,
    .btn-template-primary:focus {
        background-color: var(--cal-cyan-hover);
        border-color: var(--cal-cyan-hover);
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(2, 132, 199, 0.35);
    }

    .cal-link {
        color: var(--cal-cyan);
        font-weight: 500;
        text-decoration: none;
    }

    .cal-link:hover {
        color: var(--cal-cyan-hover);
        text-decoration: underline;
    }

    .form-check-input:checked {
        background-color: var(--cal-cyan);
        border-color: var(--cal-cyan);
    }

    @media (max-width: 575.98px) {
        .template-card {
            border-radius: 14px;
        }
        .brand-logo {
            max-width: 150px;
        }
    }
</style>
@endpush

@section('content')
<div class="login-wrapper">
    <div class="template-card">
        
        <!-- Left half: City image + teal overlay -->
        <div class="card-visual-side"></div>

        <!-- Right half: Form area -->
        <div class="card-form-side">
            <!-- Curve SVG inserted here -->
            <div class="curve-divider">
                <svg viewBox="0 0 100 100" preserveAspectRatio="none">
                   <path d="M100,0 C30,30 30,70 100,100 Z" fill="#ffffff" />
                </svg>
            </div>
                        
                        {{-- Top Logo & Header --}}
                        <div class="text-center mb-4">
                            <div class="mb-3">
                                <img src="{{ asset('assets/images/logo/logo1.png') }}" 
                                     alt="Chabrin Agencies Ltd" 
                                     class="brand-logo">
                            </div>
                            <h3 class="fw-bold mb-1" style="color: var(--cal-navy); letter-spacing: -0.02em;">Welcome Back</h3>
                            <p class="small mb-0" style="color: #64748b;">
                                login in to access your portal account
                            </p>
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

                            {{-- Email Field --}}
                            <div class="mb-3">
                                <label for="user_email" class="form-label form-field-label">Email Address</label>
                                <div class="input-group custom-input-group">
                                    <span class="input-group-text">
                                        <i class="fa fa-envelope-o"></i>
                                    </span>
                                    <input class="form-control" 
                                           type="email" 
                                           id="user_email" 
                                           name="user_email" 
                                           required 
                                           placeholder="name@company.com" 
                                           autocomplete="email">
                                </div>
                            </div>

                            {{-- Password Field --}}
                            <div class="mb-3">
                                <label for="user_password" class="form-label form-field-label">Password</label>
                                <div class="input-group custom-input-group position-relative">
                                    <span class="input-group-text">
                                        <i class="fa fa-lock"></i>
                                    </span>
                                    <input class="form-control pe-5" 
                                           type="password" 
                                           id="user_password" 
                                           name="user_password" 
                                           required 
                                           placeholder="••••••••" 
                                           autocomplete="current-password">
                                    <div class="show-hide position-absolute top-50 end-0 translate-middle-y me-3" style="cursor: pointer; z-index: 10;">
                                        <span class="show small text-muted"></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Remember & Forgot Password --}}
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                        <label class="form-check-label small fw-medium" for="remember" style="color: #475569;">
                                            Remember me
                                        </label>
                                    </div>
                                    <a class="cal-link small" href="{{ route('password.request') }}">Forgot password?</a>
                                </div>
                            </div>

                            {{-- Submit Button --}}
                            <div class="d-grid mb-2">
                                <button class="btn btn-template-primary" id="loginBtn" type="submit">
                                    <span id="loginBtnText">Login In</span>
                                    <span id="loginSpinner" class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
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