@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Register New Module</h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">close</span>
                                <span class="ms-1">Close</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @include('components.error_reports')
                        <form id="addModuleForm">
                            @csrf
                            <div class="row">
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="module_name">Module Name <span style="color:red">*</span></label>
                                    <input type="text" name="module_name" class="form-control" required placeholder="Enter module name">
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="icon">Icon</label>
                                    <input type="text" name="icon" class="form-control" placeholder="e.g. dashboard">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <div class="alert alert-light-success" role="alert">
                                        <div class="text-success">
                                            <strong>How to get the icon name:</strong><br>
                                            Go to <a style="text-decoration: underline;" href="https://fonts.google.com/icons"
                                                target="_blank">Google Fonts Icons</a>,
                                            search for the relevant icon (e.g. <b>settings</b>),
                                            click the icon, and copy the icon name shown at
                                            the bottom right (e.g. <b>settings</b>, <b>check</b>,
                                            <b>refresh</b>).
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary d-flex align-items-center" id="submitBtn">
                                <span class="material-symbols-outlined">add_circle</span>
                                Register Module
                                <span id="spinner" class="spinner-border spinner-border-sm ms-2" style="display:none;"></span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
    $(function() {
        $('#addModuleForm').on('submit', function(e) {
            e.preventDefault();
            var $btn = $('#submitBtn');
            var $spinner = $('#spinner');
            $btn.prop('disabled', true);
            $spinner.show();
            $.ajax({
                url: "{{ route('modules.store') }}",
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message || 'Module created successfully!');
                        $('#addModuleForm')[0].reset();
                        $spinner.hide();
                        // Keep button disabled briefly to prevent double submission
                        setTimeout(function() {
                            $btn.prop('disabled', false);
                        }, 1500);
                    } else {
                        toastr.error(response.error || 'Failed to add module.');
                        $btn.prop('disabled', false);
                        $spinner.hide();
                    }
                },
                error: function(xhr) {
                    toastr.error('Failed to add module.');
                    $btn.prop('disabled', false);
                    $spinner.hide();
                }
            });
        });
    });
</script>
@endsection