@extends('layouts.main')
@section('content')
<div class="page-body"><div class="container-fluid"><div class="card">
<div class="card-header"><h4>Correct Tenant Submission · NT-{{ $submission->id }}</h4></div>
<div class="card-body">
    @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <p>Resubmission starts a fresh signature sequence. Previously submitted versions remain in the review history.</p>
    <form method="post" action="{{ route('units.tenants.store') }}" enctype="multipart/form-data">
        @csrf<input type="hidden" name="onboarding_id" value="{{ $submission->id }}"><input type="hidden" name="onboarding_revision" value="{{ $submission->revision }}">
        @include('property.tenant-onboarding.fields', ['values' => $submission->payload, 'prefix' => '', 'editable' => true])
        @foreach($submission->documents as $field => $document)
        @php $parts = explode('.', $field); $name = array_shift($parts); foreach ($parts as $part) { $name .= '['.$part.']'; } @endphp
        <label class="form-label d-block">Replace {{ $document['name'] }}<input type="file" class="form-control" name="{{ $name }}"></label>
        @endforeach
        <button class="btn btn-primary">Resubmit for Review</button><a class="btn btn-light" href="{{ route('tenant-onboarding.show', $submission) }}">Back</a>
    </form>
</div></div></div></div>
@endsection
