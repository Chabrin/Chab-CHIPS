@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Roles Listing</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button id="add-role-btn" class="btn btn-primary btn-sm d-flex align-items-center" type="button" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Role</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table id="basic-1" class="table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Role Name</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($roles as $index => $role)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $role->display_name }}</td>
                                        <td>
                                            <div class="d-flex flex-row gap-2">
                                                <a href="{{ route('role.modules', Crypt::encrypt($role->id)) }}" class="d-flex align-items-center text-decoration-none" title="Modules ">
                                                    <span class="material-symbols-outlined" style="font-size:18px; color:#3598db;">security</span>
                                                    <span class="ms-1" style="text-decoration:underline; color:black;">Modules </span>
                                                </a>
                                                <a href="{{ route('role.permissions', Crypt::encrypt($role->id)) }}" class="d-flex align-items-center text-decoration-none" title="Permissions ">
                                                    <span class="material-symbols-outlined" style=" font-size:18px; color:#6c757d;">lock</span>
                                                    <span class="ms-1" style="text-decoration:underline; color:black;">Permissions </span>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div id="role-modules"></div>
                        <div id="role-permissions"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{asset('assets/js/jquery.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />
<!-- Add Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-labelledby="addRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addRoleModalLabel">Add New Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="add-role-form">
                    <div class="mb-3">
                        <label for="role-name" class="form-label">Role Name <span style="color:red">*</span></label>
                        <input type="text" class="form-control" id="role-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="role-desc" class="form-label">Description</label>
                        <textarea class="form-control" id="role-desc" name="description" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined">close_small</span>
                    <span class="ms-1">Close</span>
                </button>
                <button type="button" id="save-role-modal-btn" class="btn btn-success d-flex align-items-center">
                    <span class="material-symbols-outlined" id="modal-role-spinner" style="display:none;">autorenew</span>
                    <span class="material-symbols-outlined">check_small</span>
                    <span class="ms-1">Save Role</span>
                </button>
            </div>
        </div>
    </div>
</div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />
<script>
    $(function() {
        $('#save-role-modal-btn').on('click', function() {
            var btn = $(this);
            var spinner = $('#modal-role-spinner');
            btn.attr('disabled', true);
            spinner.show();
            $.ajax({
                url: "{{ route('roles.add') }}",
                type: 'POST',
                data: $('#add-role-form').serialize() + '&_token={{ csrf_token() }}',
                success: function(response) {
                    toastr.success('Role added successfully!');
                    setTimeout(function() {
                        location.reload();
                    }, 1200);
                },
                error: function(xhr) {
                    toastr.error('Error adding role.');
                    btn.attr('disabled', false);
                    spinner.hide();
                }
            });
        });
    });
</script>


@endsection