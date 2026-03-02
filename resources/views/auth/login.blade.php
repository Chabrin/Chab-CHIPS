@extends('layouts.auth')
@section('title', 'Login')
@section('content')
<div class="container-fluid min-vh-100 d-flex align-items-center justify-content-center bg-light py-4">
    <div class="row w-100 justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5 col-xxl-4">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <h3 class="mb-3" style="color:#3598db !important;"><b>CHABRIN AGENCIES LTD.</b></h3>
                        <p class="text-muted">Sign in to your account</p>
                    </div>

                    <form class="theme-form" id="loginForm" method="POST" action="{{ route('login') }}">
                        <div id="alert-container"></div>
                        @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        <div class="mb-3">
                            <label for="user_email" class="form-label fw-semibold">Email Address</label>
                            <input class="form-control form-control-lg" type="email" id="user_email" name="user_email" required placeholder="Enter your email address" autocomplete="email">
                        </div>

                        <div class="mb-3">
                            <label for="user_password" class="form-label fw-semibold">Password</label>
                            <div class="position-relative">
                                <input class="form-control form-control-lg" type="password" id="user_password" name="user_password" required placeholder="Enter your password" autocomplete="current-password">
                                <div class="show-hide position-absolute top-50 end-0 translate-middle-y me-3" style="cursor: pointer;">
                                    <span class="show"></span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="checkbox1">
                                    <label class="form-check-label text-muted" for="checkbox1">
                                        Remember me
                                    </label>
                                </div>
                                <a class="text-primary text-decoration-none" href="{{ route('password.request') }}">Forgot password?</a>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button class="btn btn-primary btn-lg" id="loginBtn" type="submit">
                                <span id="loginBtnText">Sign in</span>
                                <span id="loginSpinner" class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(function() {
        // Show/hide password functionality
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

        // AJAX login
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
                    console.log('Login response:', response);
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
                                } else {
                                    console.warn('No redirect URL received for 2FA flow', response);
                                    if (response && response.two_factor_token) {
                                        window.location.href = '/authenticator/' + response.two_factor_token;
                                    }
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
                            window.location.href = '/dashboard';
                        }, 1200);
                    }
                    $('#alert-container').html(alertHtml);
                },
                error: function(xhr) {
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