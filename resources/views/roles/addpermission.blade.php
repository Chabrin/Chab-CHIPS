@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Add New Permission</h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">close</span>
                                <span class="ms-1">Close</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @include('components.error_reports')
                        <form id="addPermissionForm">
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
                                    <label for="base_name">Permission Base Name <span style="color:red">*</span></label>
                                    <input type="text" name="base_name" id="base_name" class="form-control" required placeholder="e.g. user">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label>Preview Permissions:</label>
                                <div id="crudPreview" class="fw-bold text-primary"></div>
                            </div>
                            <button type="submit" class="btn btn-primary d-flex align-items-center" id="submitBtn">
                                <span class="material-symbols-outlined" id="modal-perm-spinner" style="display:none;">autorenew</span>
                                <span class="material-symbols-outlined">check_small</span>
                                <span class="ms-1">Save Permissions</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />
<script>
    $(function() {
        function updatePreview() {
            var base = $('#base_name').val().trim();
            if (base) {
                var perms = ['create', 'read', 'update', 'delete'].map(function(action) {
                    return action + '_' + base;
                });
                $('#crudPreview').html(perms.join(', '));
            } else {
                $('#crudPreview').html('');
            }
        }
        $('#base_name').on('input', updatePreview);
        updatePreview();
        $('#addPermissionForm').on('submit', function(e) {
            e.preventDefault();
            var btn = $('#submitBtn');
            var spinner = $('#modal-perm-spinner');
            btn.attr('disabled', true);
            spinner.show();
            $.ajax({
                url: "{{ route('permissions.add') }}",
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    toastr.success('Permissions added: ' + response.created.join(', '));
                    setTimeout(function() {
                        location.reload();
                    }, 1200);
                },
                error: function(xhr) {
                    var msg = 'Error adding permissions.';
                    if (xhr.status === 409 && xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    } else if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        msg = Object.values(xhr.responseJSON.errors).join('<br>');
                    }
                    toastr.error(msg);
                    btn.attr('disabled', false);
                    spinner.hide();
                }
            });
        });
    });
</script>
@endsection