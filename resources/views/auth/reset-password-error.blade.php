@extends('layouts.auth')
@section('title', 'Password Reset Error')
@section('content')
<div class="container-fluid p-0">
    <div class="row m-0">
        <div class="col-12 p-0">
            <div class="login-card login-dark">
                <div>
                    <div class="login-main">
                        <div class="theme-form">
                            <h3>Pension Master</h3>
                            <p>Password Reset Error</p>

                            <div class="alert alert-light-danger" role="alert">
                                <div class="d-flex align-items-center">
                                    <i data-feather="alert-circle" style="width: 20px; height: 20px; margin-right: 10px;"></i>
                                    <div>
                                        <p class="txt-danger mb-1"><strong>{{ $errorTitle ?? 'Unable to Reset Password' }}</strong></p>
                                        <p class="txt-danger mb-0">{{ $errorMessage ?? 'The password reset link is invalid or has expired.' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-0">
                                <div class="text-center mt-3">
                                    <a href="{{ route('password.request') }}" class="btn btn-primary btn-block w-100 d-inline-flex align-items-center justify-content-center">
                                        <i data-feather="mail" style="width: 16px; height: 16px; margin-right: 6px; margin-top: -1px;"></i>
                                        <span>Request New Reset Link</span>
                                    </a>
                                </div>
                                <div class="text-center mt-3">
                                    <a class="link d-inline-flex align-items-center justify-content-center" href="{{ route('login') }}" style="text-decoration: none;">
                                        <i data-feather="arrow-left" style="width: 16px; height: 16px; margin-right: 6px; margin-top: -1px;"></i>
                                        <span>Back to Login</span>
                                    </a>
                                </div>
                                <div class="text-center mt-2">
                                    <small class="text-muted">or</small>
                                </div>
                                <div class="text-center mt-2">
                                    <a href="{{ route('login') }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center">
                                        <i data-feather="log-in" style="width: 14px; height: 14px; margin-right: 4px; margin-top: -1px;"></i>
                                        <span>Sign In Instead</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                        <script>
                            $(function() {
                                // Initialize Feather icons
                                if (typeof feather !== 'undefined') {
                                    feather.replace();
                                }
                            });
                        </script>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection