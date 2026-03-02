@extends('layouts.main')

@section('title', 'Access Denied')

@section('content')
<div class="container mt-5">
    <div class="text-center">
        <h1 class="display-4 text-danger">403</h1>
        <p class="lead">You do not have permission to access this page.</p>
        <a href="{{ route('dashboard') }}" class="btn btn-primary">Go to Dashboard</a>
    </div>
</div>
@endsection