@extends('layouts.auth')
@section('title', 'Set Your Password')
@section('content')
<div class="container-fluid min-vh-100 d-flex align-items-center justify-content-center bg-light py-4">
    <div class="row w-100 justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5 col-xxl-4">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4 p-md-5 text-center">
                    <h3 class="mb-3" style="color:#3598db !important;"><b>CHABRIN AGENCIES LTD.</b></h3>

                    @if(in_array($kind, ['sent', 'not_sent'], true))
                        <div class="alert {{ $kind === 'sent' ? 'alert-light-success' : 'alert-light-danger' }}" role="alert">{{ $message }}</div>
                    @else
                        <div class="alert alert-light-danger" role="alert">{{ $message }}</div>
                    @endif

                    @if($kind === 'expired' && $token)
                        <form method="POST" action="{{ route('password.setup.resend', ['token' => $token]) }}" class="mb-3">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-lg w-100">Send me a new link</button>
                        </form>
                        <p class="small text-muted">The new link goes to the email address on your account.</p>
                    @endif

                    <a class="btn btn-outline-secondary" href="{{ route('login') }}">Go to sign in</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
