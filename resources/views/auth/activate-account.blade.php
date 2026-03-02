@extends('layouts.auth')
@section('title', 'Activate Account')
@section('content')
<div class="container-fluid min-vh-100 d-flex align-items-center justify-content-center bg-light py-4">
    <div class="row w-100 justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5 col-xxl-4">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <h3 class="mb-3" style="color:#3598db !important;"><b>CHABRIN AGENCIES LTD.</b></h3>
                        <p class="text-muted">Set a strong password to activate your account</p>
                    </div>

                    <form class="theme-form" id="activateAccountForm">
                        <div id="alert-container"></div>

                        <input type="hidden" name="token" id="activationToken" value="{{ $token ?? '' }}">

                        @if($userEmail)
                        <div class="mb-3">
                            <label for="user_email" class="form-label fw-semibold">Email Address</label>
                            <input class="form-control form-control-lg" type="email" id="user_email" value="{{ $userEmail }}" readonly style="background-color: #f8f9fa;">
                        </div>
                        @endif

                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">Password</label>
                            <div class="position-relative">
                                <input class="form-control form-control-lg" type="password" name="password" id="password" required placeholder="Enter your password" autocomplete="new-password">
                                <div class="show-hide position-absolute top-50 end-0 translate-middle-y me-3" style="cursor: pointer;">
                                    <span class="show"></span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="passwordConfirmation" class="form-label fw-semibold">Confirm Password</label>
                            <div class="position-relative">
                                <input class="form-control form-control-lg" type="password" name="password_confirmation" id="passwordConfirmation" required placeholder="Confirm your password" autocomplete="new-password">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Password strength</label>
                            <div class="progress" style="height:10px">
                                <div id="passwordStrengthBar" class="progress-bar" role="progressbar" style="width:0%"></div>
                            </div>
                            <small id="passwordStrengthText" class="form-text text-muted d-block mt-2">Enter a strong password (min 10 chars, mixed-case, numbers and symbols)</small>
                        </div>

                        <div class="d-grid mb-3">
                            <button class="btn btn-primary btn-lg" id="activateBtn" type="submit">
                                <span id="activateBtnText">Activate Account</span>
                                <span id="activateSpinner" class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
                            </button>
                        </div>

                        <div class="text-center">
                            <a class="text-primary text-decoration-none" href="{{ route('login') }}">Back to Login</a>
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
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        // show/hide password
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

        function assessPasswordStrength(pw) {
            var score = 0;
            if (!pw) return 0;
            if (pw.length >= 10) score += 2;
            if (pw.length >= 14) score += 1;
            if (/[A-Z]/.test(pw)) score += 1;
            if (/[a-z]/.test(pw)) score += 1;
            if (/[0-9]/.test(pw)) score += 1;
            if (/[^A-Za-z0-9]/.test(pw)) score += 2;
            return Math.min(score, 7);
        }

        function updateStrengthUI(pw) {
            var score = assessPasswordStrength(pw);
            var pct = Math.round((score / 7) * 100);
            var bar = $('#passwordStrengthBar');
            var txt = $('#passwordStrengthText');
            var activateBtn = $('#activateBtn');

            bar.css('width', pct + '%');
            bar.removeClass('bg-danger bg-warning bg-success');

            if (pct < 35) {
                bar.addClass('bg-danger');
                txt.text('Weak password - requires improvement');
                txt.removeClass('text-success text-warning').addClass('text-danger');
                activateBtn.prop('disabled', true);
            } else if (pct < 70) {
                bar.addClass('bg-warning');
                txt.text('Moderate password - consider strengthening');
                txt.removeClass('text-success text-danger').addClass('text-warning');
                activateBtn.prop('disabled', true);
            } else {
                bar.addClass('bg-success');
                txt.text('Strong password - ready to activate');
                txt.removeClass('text-danger text-warning').addClass('text-success');
                activateBtn.prop('disabled', false);
            }
        }

        $('#password').on('input', function() {
            updateStrengthUI($(this).val());
        });

        // Initial state - disable button until strong password
        $('#activateBtn').prop('disabled', true);

        // Activation submit
        $('#activateAccountForm').on('submit', function(e) {
            e.preventDefault();

            // Double-check password strength before submission
            var password = $('#password').val();
            var score = assessPasswordStrength(password);
            var pct = Math.round((score / 7) * 100);

            if (pct < 70) {
                $('#alert-container').html('<div class="alert alert-danger alert-dismissible fade show" role="alert"><strong>Error!</strong> Please use a stronger password before activating your account.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
                return;
            }

            $('#alert-container').html('');
            $('#activateBtnText').text('Processing');
            $('#activateSpinner').removeClass('d-none');
            $('#activateBtn').prop('disabled', true);

            var token = $('#activationToken').val();
            var password = $('#password').val();
            var password_confirmation = $('#passwordConfirmation').val();
            var csrf = $('meta[name="csrf-token"]').attr('content');

            $.ajax({
                url: '/activate-account/' + encodeURIComponent(token),
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf
                },
                data: (function() {
                    var payload = {
                        password: password,
                        password_confirmation: password_confirmation,
                        _token: '{{ csrf_token() }}'
                    };
                    return payload;
                })(),
                success: function(response) {
                    if (response.status === 'error') {
                        $('#alert-container').html(`<div class="alert alert-danger alert-dismissible fade show" role="alert"><strong>Error!</strong> ${response.message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`);
                        $('#activateBtnText').text('Activate Account');
                        $('#activateSpinner').addClass('d-none');
                        $('#activateBtn').prop('disabled', false);
                    } else {
                        $('#alert-container').html(`<div class="alert alert-success alert-dismissible fade show" role="alert"><strong>Success!</strong> ${response.message}</div>`);
                        $('#activateBtnText').text('Activated');
                        $('#activateSpinner').addClass('d-none');
                        setTimeout(function() {
                            window.location.href = '{{ route("login") }}';
                        }, 1400);
                    }
                },
                error: function(xhr) {
                    var msg = 'Server error. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    $('#alert-container').html(`<div class="alert alert-danger alert-dismissible fade show" role="alert"><strong>Error!</strong> ${msg}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`);
                    $('#activateBtnText').text('Activate Account');
                    $('#activateSpinner').addClass('d-none');
                    $('#activateBtn').prop('disabled', false);
                }
            });
        });
    });
</script>
@endpush
@endsection