@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/property-listing.css') }}?v={{ filemtime(public_path('assets/css/property-listing.css')) }}">
@endsection

@section('content')
@include('property.properties.partials.property-directory', ['inactive' => true])
@endsection
