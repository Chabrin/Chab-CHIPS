@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Register New Submodule</h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">close</span>
                                <span class="ms-1">Close</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @include('components.error_reports')
                        <form id="addSubmoduleForm">
                            @csrf
                            <div class="row">
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="module_id">Module <span style="color:red">*</span></label>
                                    <select name="module_id" class="form-control" required>
                                        <option value="">Select Module</option>
                                        @foreach($modules as $mod)
                                        <option value="{{ $mod->id }}">{{ $mod->module_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="sub_module_name">Submodule Name <span style="color:red">*</span></label>
                                    <input type="text" name="sub_module_name" class="form-control" required placeholder="Enter submodule name">
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                    <label for="sub_module_url">Submodule URL</label>
                                    <input type="text" name="sub_module_url" class="form-control" placeholder="e.g. /users">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary d-flex align-items-center" id="submitBtn">
                                <span class="material-symbols-outlined">add_circle</span>
                                Register Submodule
                                <span id="spinner" class="spinner-border spinner-border-sm ms-2" style="display:none;"></span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />
<style>
    .toast-success {
        color: #fff !important;
    }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
    $(function() {
        $('#addSubmoduleForm').on('submit', function(e) {
            e.preventDefault();
            var $btn = $('#submitBtn');
            var $spinner = $('#spinner');
            $btn.prop('disabled', true);
            $spinner.show();
            $.ajax({
                url: "{{ route('submodules.store') }}",
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message || 'Submodule created successfully!');
                        $('#addSubmoduleForm')[0].reset();
                        $spinner.hide();
                        // Keep button disabled briefly to prevent double submission
                        setTimeout(function() {
                            $btn.prop('disabled', false);
                        }, 1500);
                    } else {
                        toastr.error(response.error || 'Failed to add submodule.');
                        $btn.prop('disabled', false);
                        $spinner.hide();
                    }
                },
                error: function(xhr) {
                    toastr.error('Failed to add submodule.');
                    $btn.prop('disabled', false);
                    $spinner.hide();
                }
            });
        });
    });
</script> });
</script>
@endsection