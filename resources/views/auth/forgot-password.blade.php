@extends('layouts.auth')
@section('title', 'Forgot Password')
@section('content')
<div class="container-fluid min-vh-100 d-flex align-items-center justify-content-center bg-light py-4">
    <div class="row w-100 justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5 col-xxl-4">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <h3 class="mb-3" style="color:#3598db !important;"><b>CHABRIN AGENCIES LTD.</b></h3>
                        <p class="text-muted">Enter your email address to reset your password</p>
                    </div>

                    <form class="theme-form" id="forgotPasswordForm">
                        <div id="alert-container"></div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Email Address</label>
                            <input class="form-control form-control-lg" type="email" id="email" name="email" required placeholder="Enter your email address" autocomplete="email">
                        </div>

                        <div class="d-grid">
                            <button class="btn btn-primary btn-lg" id="forgotBtn" type="submit">
                                <span id="forgotBtnText">Send Reset Link</span>
                                <span id="forgotSpinner" class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
                            </button>
                        </div>

                        <div class="text-center mt-4">
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