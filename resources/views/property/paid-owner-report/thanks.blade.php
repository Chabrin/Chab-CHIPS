@extends('layouts.auth')

@section('title', 'Thank you')

@section('content')
<div class="d-flex align-items-center justify-content-center" style="min-height:100vh; background:#f5f6fa;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card card-no-border">
                    <div class="card-body text-center py-5">
                        <div class="mb-3"><i class="fa fa-check-circle fa-4x text-success"></i></div>
                        <h4 class="mb-2">Thank you, {{ $landlord }}</h4>
                        <p class="text-muted mb-1">We have received your report. Our team will check it before anything changes on the tenant's or your account.</p>
                        <p class="mb-0">Your reference: <strong>{{ $reference }}</strong></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
