@extends('layouts.auth')
@section('title', 'Two-Factor Authentication')
@section('content')
<div class="container d-flex align-items-center justify-content-center" style="min-height:100vh; padding:40px 0;">
    @if(isset($qrCode) && isset($secret))
    <div class="card shadow-sm" style="max-width: 820px; width: 100%; border-radius: 10px; margin: 16px 0; background: #fff;">
        <div class="row g-0 align-items-stretch" style="background: #fff;">
            <!-- Left: QR and instructions -->
            <div class="col-md-6 d-flex flex-column justify-content-center px-3 py-2 border-end" style="background: #fff; min-height: 380px;">
                <div class="d-flex align-items-center justify-content-center mb-2" style="gap:10px;">
                    <img src="{{ asset('assets/images/Google_Authenticator.png') }}" alt="Google Authenticator" style="width:38px; height:38px; object-fit:contain;">
                    <h5 class="mb-0">Set up Google Authenticator</h5>
                </div>
                <div class="text-center mb-2">
                    <div class="p-1 bg-white rounded shadow-sm d-inline-block" style="padding:6px;">
                        {!! $qrCode !!}
                    </div>
                    <p class="mt-2 small">Secret:
                        <strong class="text-monospace">{{ $secret }}</strong>
                    </p>
                </div>
                <div class="p-2 rounded mb-2" style="background: #f8fafc; border:1px solid #e0e7ef; font-size:12px;">
                    <div class="fw-bold mb-1 text-success">How to set up:</div>
                    <ul class="mb-2 ps-3" style="list-style-type: '- '; padding-left: 1.2em;">
                        <li>Install <b>Google Authenticator</b> from the App Store or Play Store.</li>
                        <li>Open the app and tap <b>+</b> to add a new account.</li>
                        <li>Scan the QR code above <b>or</b> enter the secret key manually.</li>
                        <li>After setup, your app will show a 6-digit code for Pension Master.</li>
                    </ul>
                    <div class="small text-muted">Keep your secret key safe. You will need it to log in.</div>
                </div>
            </div>

            <!-- Right: OTP input -->
            <div class="col-md-6 d-flex flex-column justify-content-center px-3 py-2" style="background: #fff; min-height: 380px;">
                <form class="theme-form" id="2faForm">
                    <input type="hidden" id="two_factor_token" name="two_factor_token" value="{{$two_factor_token}}">
                    <h5 class="mb-2">Enter the 6-digit code</h5>
                    <div class="mb-2 text-muted" style="font-size:14px;">Open your authenticator app and enter the code shown for Pension Master.</div>
                    <div id="2fa-alert-container"></div>
                    <div class="form-group mb-2">
                        <label class="col-form-label">Authenticator Code</label>
                        <div class="d-flex justify-content-between" style="gap:8px;">
                            @for ($i = 1; $i <= 6; $i++)
                                <input type="text" maxlength="1"
                                class="form-control otp-input text-center"
                                style="border:1px solid #2c575e; height:45px; width:45px; font-size:20px; font-weight:bold;"
                                inputmode="numeric" pattern="[0-9]*" required>
                                @endfor
                        </div>
                        <input type="hidden" name="one_time_password" id="one_time_password">
                    </div>
                    @csrf
                    <div class="form-group">
                        <button id="2faBtn" class="btn btn-primary w-100 py-2" type="submit">
                            <span id="2faBtnText">Verify</span>
                            <span id="2faSpinner" class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
                        </button>
                    </div>
                    <div class="text-center mt-2">
                        <a class="link d-inline-flex align-items-center" href="{{ route('login') }}" style="text-decoration: none;">
                            <i data-feather="arrow-left" class="me-1"></i> Back to Login
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @else
    <div class="card shadow-sm" style="max-width: 420px; width: 100%; border-radius: 10px; margin: 12px 0;">
        <div class="card-body p-4">
            <div class="text-center mb-3">
                <img src="{{ asset('assets/images/Google_Authenticator.png') }}" alt="Google Authenticator" style="width:48px; height:48px; object-fit:contain;">
                <h5 class="mt-2 mb-1">Two-Factor Authentication</h5>
                <div class="small text-dark mb-2">Check your authenticator app for the OTP</div>
            </div>
            <form class="theme-form mt-2" id="2faForm">
                <input type="hidden" id="two_factor_token" name="two_factor_token" value="{{$two_factor_token}}">
                <h6 class="mb-1">Enter the 6-digit code from your authenticator app</h6>
                <div id="2fa-alert-container"></div>
                <div class="form-group mb-2">
                    <label class="col-form-label">Authenticator Code</label>
                    <div class="d-flex justify-content-between" style="gap:8px;">
                        @for ($i = 1; $i <= 6; $i++)
                            <input type="text" maxlength="1"
                            class="form-control otp-input text-center"
                            style="border:1px solid #2c575e; width:45px; height:45px; font-size:20px; font-weight:bold;"
                            inputmode="numeric" pattern="[0-9]*" required>
                            @endfor
                    </div>
                    <input type="hidden" name="one_time_password" id="one_time_password">
                </div>
                @csrf
                <div class="form-group">
                    <button id="2faBtn" class="btn btn-primary w-100 py-2" type="submit">
                        <span id="2faBtnText">Verify</span>
                        <span id="2faSpinner" class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
                    </button>
                </div>
                <div class="text-center mt-3">
                    <a class="link d-inline-flex align-items-center" href="{{ route('login') }}" style="text-decoration: none;">
                        <i data-feather="arrow-left" class="me-1"></i> Back to Login
                    </a>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>

<script>
    $(function() {
        const inputs = document.querySelectorAll(".otp-input");

        inputs.forEach((input, index) => {
            input.addEventListener("input", function() {
                if (this.value.length > 0 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
                updateHiddenField();
            });

            input.addEventListener("keydown", function(e) {
                if (e.key === "Backspace" && !this.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });
        });

        function updateHiddenField() {
            let otp = "";
            inputs.forEach(input => otp += input.value);
            document.getElementById("one_time_password").value = otp;

            if (otp.length === 6) {
                // Auto-submit when all 6 digits are filled
                $("#2faForm").submit();
            }
        }

        $('#2faForm').on('submit', function(e) {
            e.preventDefault();
            $('#2fa-alert-container').html('');
            $('#2faBtnText').text('Verifying');
            $('#2faSpinner').removeClass('d-none');
            $('#2faBtn').prop('disabled', true);

            $.ajax({
                url: '/authenticator/verify',
                type: 'POST',
                data: $(this).serialize(),
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.status === 'error') {
                        $('#2fa-alert-container').html(`<div class="alert alert-danger">${response.message}</div>`);
                        $('#2faBtnText').text('Verify');
                        $('#2faSpinner').addClass('d-none');
                        $('#2faBtn').prop('disabled', false);
                        inputs.forEach(input => input.value = ""); // clear OTP fields
                        inputs[0].focus();
                    } else if (response.status === 'success') {
                        $('#2fa-alert-container').html(`<div class="alert alert-success">${response.message} Redirecting...</div>`);
                        $('#2faBtnText').text('Verified');
                        $('#2faSpinner').addClass('d-none');
                        setTimeout(function() {
                            window.location.href = response.redirect || '/dashboard';
                        }, 900);
                    }
                },
                error: function(xhr) {
                    let msg = 'Server error. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    $('#2fa-alert-container').html(`<div class="alert alert-danger">${msg}</div>`);
                    $('#2faBtnText').text('Verify');
                    $('#2faSpinner').addClass('d-none');
                    $('#2faBtn').prop('disabled', false);
                }
            });
        });
    });
</script>
@endsection