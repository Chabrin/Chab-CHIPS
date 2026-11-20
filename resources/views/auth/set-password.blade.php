@extends('layouts.auth')
@section('title', 'Set Your Password')
@section('content')
<div class="container-fluid min-vh-100 d-flex align-items-center justify-content-center bg-light py-4">
    <div class="row w-100 justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5 col-xxl-4">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <h3 class="mb-3" style="color:#3598db !important;"><b>CHABRIN AGENCIES LTD.</b></h3>
                        <p class="text-muted mb-0">Welcome{{ $name ? ', ' . $name : '' }}. Choose your own password.</p>
                    </div>

                    <form class="theme-form" id="setPasswordForm" autocomplete="off" novalidate>
                        <div id="alert-container"></div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Your login email</label>
                            <input class="form-control form-control-lg" type="email" id="email" value="{{ $email }}" readonly style="background-color:#f8f9fa;" autocomplete="username">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">New password</label>
                            <input class="form-control form-control-lg" type="password" name="password" id="password" required autocomplete="new-password" maxlength="{{ $maxLength }}" aria-describedby="passwordHelp">
                            <div id="passwordHelp" class="form-text">At least {{ $minLength }} characters. A phrase of several words works well, for example four words you will remember.</div>
                            <div id="lengthHint" class="small mt-1 text-muted" aria-live="polite">0 of {{ $minLength }} characters</div>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label fw-semibold">Type it again</label>
                            <input class="form-control form-control-lg" type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password" maxlength="{{ $maxLength }}">
                            <div id="matchHint" class="small mt-1 text-muted" aria-live="polite"></div>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="showPasswords">
                            <label class="form-check-label" for="showPasswords">Show passwords</label>
                        </div>

                        <div class="d-grid mb-3">
                            <button class="btn btn-primary btn-lg" id="saveBtn" type="submit" disabled>
                                <span id="saveBtnText">Set my password</span>
                                <span id="saveSpinner" class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
                            </button>
                        </div>

                        <p class="small text-muted text-center mb-0">Nobody, including HR and IT, can see your password.</p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(function () {
        var minLength = {{ (int) $minLength }};
        var submitUrl = '{{ route('password.setup.store', ['token' => $token]) }}';
        var $password = $('#password');
        var $confirm = $('#password_confirmation');
        var $button = $('#saveBtn');

        function showAlert(kind, text) {
            var $box = $('<div class="alert" role="alert"></div>').addClass('alert-' + kind).text(text);
            $('#alert-container').empty().append($box);
        }

        // Hints only: the server makes the real checks
        function refresh() {
            var length = $password.val().length;
            var longEnough = length >= minLength;
            var same = length > 0 && $password.val() === $confirm.val();
            $('#lengthHint').text(length + ' of ' + minLength + ' characters').toggleClass('text-success', longEnough).toggleClass('text-muted', !longEnough);
            $('#matchHint').text($confirm.val() === '' ? '' : (same ? 'The two match.' : 'The two do not match yet.')).toggleClass('text-success', same).toggleClass('text-danger', $confirm.val() !== '' && !same);
            $button.prop('disabled', !(longEnough && same));
        }

        $password.on('input', refresh);
        $confirm.on('input', refresh);
        $('#showPasswords').on('change', function () {
            var type = this.checked ? 'text' : 'password';
            $password.attr('type', type);
            $confirm.attr('type', type);
        });

        $('#setPasswordForm').on('submit', function (event) {
            event.preventDefault();
            if ($button.prop('disabled')) {
                return;
            }
            $('#alert-container').empty();
            $button.prop('disabled', true);
            $('#saveSpinner').removeClass('d-none');
            $('#saveBtnText').text('Saving');
            $.ajax({
                url: submitUrl,
                type: 'POST',
                data: { password: $password.val(), password_confirmation: $confirm.val(), _token: '{{ csrf_token() }}' },
                dataType: 'json',
                success: function (response) {
                    showAlert('success', 'Your password has been set. Taking you to the sign-in page.');
                    setTimeout(function () { window.location.href = response.redirect; }, 1200);
                },
                error: function (xhr) {
                    var body = xhr.responseJSON || {};
                    showAlert('danger', body.message || 'We could not save your password. Please try again.');
                    $('#saveSpinner').addClass('d-none');
                    $('#saveBtnText').text('Set my password');
                    if (body.link_problem) {
                        $button.prop('disabled', true);
                    } else {
                        refresh();
                    }
                }
            });
        });
    });
</script>
@endpush
@endsection
