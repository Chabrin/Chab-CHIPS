@extends('layouts.main')

@section('title', 'Page Not Found')

@section('content')
<div class="container mt-5">
    <div class="text-center">
        <h1 class="display-4 text-danger">404</h1>
        <p class="lead">Oops! The page you are looking for does not exist.</p>
        <a href="{{ route('dashboard') }}" class="btn btn-primary">Go to Dashboard</a>
    </div>
</div>
@endsection