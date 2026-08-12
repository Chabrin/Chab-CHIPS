@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">System Users</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_users')
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add User</span>
                            </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="bulkActionsBar" class="d-none flex-column gap-2 mb-3 p-2" style="background:#f0f7fd;border:1px solid #d9ebf7;border-radius:6px;">
                            <div class="d-flex align-items-center gap-2">
                                <span class="small fw-semibold" id="bulkSelectedCount">0 selected</span>
                                <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="collapse" data-bs-target="#selectedUsersPanel">
                                    View selected
                                </button>
                                <div class="ms-auto d-flex gap-2">
                                    @permission('delete_users')
                                    <button class="btn btn-outline-primary btn-sm d-flex align-items-center" id="bulkActivateBtn" type="button">
                                        <span class="material-symbols-outlined" style="font-size:16px;">change_circle</span>
                                        <span class="ms-1">Activate</span>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm d-flex align-items-center" id="bulkSuspendBtn" type="button">
                                        <span class="material-symbols-outlined" style="font-size:16px;">block</span>
                                        <span class="ms-1">Suspend</span>
                                    </button>
                                    @endpermission
                                    @permission('update_users')
                                    <button class="btn btn-outline-secondary btn-sm d-flex align-items-center" id="bulkResetPasswordBtn" type="button">
                                        <span class="material-symbols-outlined" style="font-size:16px;">key</span>
                                        <span class="ms-1">Reset Password</span>
                                    </button>
                                    @endpermission
                                </div>
                            </div>
                            <div class="collapse" id="selectedUsersPanel">
                                <div id="selectedUsersList" class="border rounded bg-white p-1" style="max-height:220px; overflow-y:auto;"></div>
                            </div>
                        </div>
                        <div class="table-responsive custom-scrollbar">
                            <table class="table" id="basic-1">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="selectAllUsers" class="form-check-input"></th>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Linked Staff</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Loaded via Ajax -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Add System User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addUserForm">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_name" name="name" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="add_username" class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_username" name="username" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="add_email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="add_email" name="email" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="add_password" class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="add_password" name="password" required minlength="8" autocomplete="new-password">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="add_role" class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_role" name="role_id" required>
                            <option value="">Select Role</option>
                            @foreach(DB::table('cb_roles')->get() as $role)
                            <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="add_staff_id" class="form-label">Linked Staff <span class="text-muted small">(optional)</span></label>
                        <select class="form-select" id="add_staff_id" name="staff_id" style="width:100%;">
                            <option value="">Search by name or staff number...</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                        <span class="ms-1">Cancel</span>
                    </button>
                    @permission('create_users')
                    <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center" id="addUserBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                        <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                        <span>Add User</span>
                    </button>
                    @endpermission
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Edit System User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editUserForm">
                @csrf
                <input type="hidden" id="edit_user_id" name="user_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_password" class="form-label">New Password <span class="text-muted small">(leave blank to keep current)</span></label>
                        <input type="password" class="form-control" id="edit_password" name="password" minlength="8" autocomplete="new-password">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_role" name="role_id" required>
                            <option value="">Select Role</option>
                            @foreach(DB::table('cb_roles')->get() as $role)
                            <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_staff_id" class="form-label">Linked Staff <span class="text-muted small">(optional)</span></label>
                        <select class="form-select" id="edit_staff_id" name="staff_id" style="width:100%;">
                            <option value="">Search by name or staff number...</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm d-flex align-items-center" data-bs-dismiss="modal">
                        <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                        <span class="ms-1">Cancel</span>
                    </button>
                    @permission('update_users')
                    <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center" id="editUserBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                        <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                        <span>Update User</span>
                    </button>
                    @endpermission
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Suspend Confirmation Modal -->
<div class="modal fade" id="suspendUserModal" tabindex="-1" aria-labelledby="suspendUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="suspendUserModalLabel">Confirm Suspension</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to suspend this user?</p>
                <p class="text-muted small mb-0" id="suspendUserName"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                    <span class="ms-1">Cancel</span>
                </button>
                @permission('delete_users')
                <button type="button" class="btn btn-danger btn-sm d-flex align-items-center" id="confirmSuspendBtn">
                    <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                    <span class="material-symbols-outlined me-1" style="font-size:18px;">block</span>
                    <span>Suspend</span>
                </button>
                @endpermission
            </div>
        </div>
    </div>
</div>

<!-- Activate Confirmation Modal -->
<div class="modal fade" id="activateUserModal" tabindex="-1" aria-labelledby="activateUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="activateUserModalLabel">Confirm Activation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to activate this user?</p>
                <p class="text-muted small mb-0" id="activateUserName"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                    <span class="ms-1">Cancel</span>
                </button>
                @permission('delete_users')
                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="confirmActivateBtn">
                    <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                    <span class="material-symbols-outlined me-1" style="font-size:18px;">change_circle</span>
                    <span>Activate</span>
                </button>
                @endpermission
            </div>
        </div>
    </div>
</div>

<!-- Bulk Suspend Confirmation Modal -->
<div class="modal fade" id="bulkSuspendModal" tabindex="-1" aria-labelledby="bulkSuspendModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkSuspendModalLabel">Confirm Bulk Suspension</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to suspend <strong id="bulkSuspendCount">0</strong> user(s)?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                    <span class="ms-1">Cancel</span>
                </button>
                <button type="button" class="btn btn-danger btn-sm d-flex align-items-center" id="confirmBulkSuspendBtn">
                    <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                    <span class="material-symbols-outlined me-1" style="font-size:18px;">block</span>
                    <span>Suspend</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Activate Confirmation Modal -->
<div class="modal fade" id="bulkActivateModal" tabindex="-1" aria-labelledby="bulkActivateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkActivateModalLabel">Confirm Bulk Activation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to activate <strong id="bulkActivateCount">0</strong> user(s)?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                    <span class="ms-1">Cancel</span>
                </button>
                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="confirmBulkActivateBtn">
                    <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                    <span class="material-symbols-outlined me-1" style="font-size:18px;">change_circle</span>
                    <span>Activate</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Reset Password Modal -->
<div class="modal fade" id="bulkResetPasswordModal" tabindex="-1" aria-labelledby="bulkResetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkResetPasswordModalLabel">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">This will reset the password for <strong id="bulkResetCount">0</strong> user(s). Each affected user will be required to set a new password the next time they log in.</p>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="reset_mode" id="reset_mode_auto" value="auto" checked>
                        <label class="form-check-label" for="reset_mode_auto">
                            Auto-generate a unique password for each user
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="reset_mode" id="reset_mode_fixed" value="fixed">
                        <label class="form-check-label" for="reset_mode_fixed">
                            Set the same password for all selected users
                        </label>
                    </div>
                </div>

                <div class="mb-3 d-none" id="bulkFixedPasswordWrapper">
                    <label for="bulkFixedPassword" class="form-label">New Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="bulkFixedPassword" minlength="8" autocomplete="new-password">
                    <div class="invalid-feedback"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                    <span class="ms-1">Cancel</span>
                </button>
                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="confirmBulkResetPasswordBtn">
                    <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                    <span class="material-symbols-outlined me-1" style="font-size:18px;">key</span>
                    <span>Reset Password</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Reset Password Results Modal -->
<div class="modal fade" id="bulkResetResultsModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="bulkResetResultsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkResetResultsModalLabel">New Passwords</h5>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning d-flex align-items-center gap-2">
                    <span class="material-symbols-outlined">warning</span>
                    <div>These passwords are shown only once. Copy and share them with each user before closing this window.</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>New Password</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="bulkResetResultsBody">
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary btn-sm d-flex align-items-center" id="copyAllResultsBtn">
                    <span class="material-symbols-outlined me-1" style="font-size:18px;">content_copy</span>
                    <span>Copy All</span>
                </button>
                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                    <span>Done</span>
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        let usersTable;
        let suspendUserId = null;
        let activateUserId = null;
        let canUpdate = false;
        let canDelete = false;
        // Persists across pagination/sort/filter — keyed by user id, value is {id, name, email}
        let selectedUsers = new Map();

        function updateSelectAllHeaderState() {
            const total = usersTable ? usersTable.rows().count() : 0;
            const checkedCount = selectedUsers.size;
            $('#selectAllUsers').prop('checked', total > 0 && checkedCount === total);
            $('#selectAllUsers').prop('indeterminate', checkedCount > 0 && checkedCount < total);
        }

        // Re-check/un-check whatever rows are currently rendered in the DOM
        // (a page of the table) to match selectedUsers, since DataTables only
        // keeps the current page's rows as real elements.
        function syncVisibleCheckboxes() {
            $('.row-select-user').each(function() {
                const user = $(this).data('user');
                $(this).prop('checked', !!(user && selectedUsers.has(user.id)));
            });
            updateSelectAllHeaderState();
        }

        function renderSelectedList() {
            const $list = $('#selectedUsersList');
            if (selectedUsers.size === 0) {
                $list.html('<div class="text-muted small p-2">No users selected.</div>');
                return;
            }
            const rows = Array.from(selectedUsers.values()).map(function(u) {
                return '<div class="d-flex align-items-center justify-content-between py-1 px-2 border-bottom">' +
                    '<span class="small"><strong>' + $('<div>').text(u.name).html() + '</strong> ' +
                    '<span class="text-muted">' + $('<div>').text(u.email).html() + '</span></span>' +
                    '<button type="button" class="btn btn-sm btn-link text-danger p-0 remove-selected-user" data-id="' + u.id + '" title="Remove from selection">' +
                    '<span class="material-symbols-outlined" style="font-size:16px;">close</span></button>' +
                    '</div>';
            });
            $list.html(rows.join(''));
        }

        function updateBulkBar() {
            const count = selectedUsers.size;
            $('#bulkSelectedCount').text(count + ' selected');
            $('#bulkActionsBar').toggleClass('d-none', count === 0).toggleClass('d-flex', count > 0);
            renderSelectedList();
        }

        // Load users via Ajax
        function loadUsers() {
            // Check if DataTable already exists and destroy it properly
            if ($.fn.DataTable.isDataTable('#basic-1')) {
                $('#basic-1').DataTable().clear().destroy();
            }

            usersTable = $('#basic-1').DataTable({
                processing: true,
                ajax: {
                    url: '{{ route("user.ajax.list") }}',
                    dataSrc: function(json) {
                        canUpdate = json.permissions.canUpdate;
                        canDelete = json.permissions.canDelete;
                        return json.users;
                    }
                },
                columns: [{
                        data: null,
                        orderable: false,
                        render: function() {
                            return '<input type="checkbox" class="form-check-input row-select-user">';
                        }
                    },
                    {
                        data: null,
                        render: function(data, type, row, meta) {
                            return meta.row + 1;
                        }
                    },
                    {
                        data: 'name'
                    },
                    {
                        data: 'email'
                    },
                    {
                        data: 'role_name',
                        defaultContent: '-'
                    },
                    {
                        data: null,
                        render: function(data, type, row) {
                            if (row.linked_staff_id) {
                                return '<span>' + row.linked_staff_name + '</span>'
                                     + '<br><small class="text-muted">' + (row.linked_staff_number || '') + '</small>';
                            }
                            return '<span class="text-muted">—</span>';
                        }
                    },
                    {
                        data: 'block',
                        render: function(data) {
                            return data == 0 ?
                                '<span class="badge badge-light-success">Active</span>' :
                                '<span class="badge badge-light-danger">Suspended</span>';
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        render: function(data, type, row) {
                            let actions = '<div class="d-flex flex-row gap-2">';

                            if (canUpdate) {
                                actions += `<a href="#" class="d-flex align-items-center text-decoration-none edit-user" data-id="${row.id}" title="Edit User">
                                    <span class="material-symbols-outlined" style="font-size:18px; color:#3598db;">edit_square</span>
                                </a>`;
                            }

                            if (canDelete) {
                                if (row.block == 0) {
                                    actions += `<a href="#" class="d-flex align-items-center text-decoration-none suspend-user" data-id="${row.id}" data-name="${row.name}" title="Suspend User">
                                        <span class="material-symbols-outlined" style="font-size:18px; color:#dc3545;">block</span>
                                        <span class="ms-1">Suspend</span>
                                    </a>`;
                                } else {
                                    actions += `<a href="#" class="d-flex align-items-center text-decoration-none activate-user" data-id="${row.id}" data-name="${row.name}" title="Activate User">
                                        <span class="material-symbols-outlined" style="font-size:18px; color:#3598db;">change_circle</span>
                                        <span class="ms-1">Activate</span>
                                    </a>`;
                                }
                            }

                            actions += '</div>';
                            return actions;
                        }
                    }
                ],
                order: [
                    [2, 'asc']
                ],
                createdRow: function(row, data) {
                    // Store the full row object on its checkbox (not just the id) so
                    // the "view selected" list can show name/email without a lookup.
                    $(row).find('.row-select-user').data('user', data);
                }
            });

            // Rows are rebuilt as new DOM nodes on every redraw (pagination, sort,
            // filter) — re-check whichever of them are already selected instead of
            // wiping the selection, since selection must survive pagination.
            usersTable.on('draw', function() {
                syncVisibleCheckboxes();
                updateBulkBar();
            });
        }

        loadUsers();

        // Row checkbox selection
        $(document).on('change', '.row-select-user', function() {
            const user = $(this).data('user');
            if ($(this).is(':checked')) {
                selectedUsers.set(user.id, user);
            } else {
                selectedUsers.delete(user.id);
            }

            updateSelectAllHeaderState();
            updateBulkBar();
        });

        // Select-all checkbox — selects every user in the table (all pages,
        // since this DataTable loads its full dataset up front and only
        // paginates client-side), not just the currently rendered page.
        $(document).on('change', '#selectAllUsers', function() {
            const checked = $(this).is(':checked');
            $(this).prop('indeterminate', false);

            if (checked) {
                usersTable.rows().every(function() {
                    const rowData = this.data();
                    selectedUsers.set(rowData.id, {
                        id: rowData.id,
                        name: rowData.name,
                        email: rowData.email
                    });
                });
            } else {
                selectedUsers.clear();
            }

            syncVisibleCheckboxes();
            updateBulkBar();
        });

        // Remove a single user from the selection via the "View selected" list
        $(document).on('click', '.remove-selected-user', function() {
            const id = parseInt($(this).data('id'), 10);
            selectedUsers.delete(id);
            syncVisibleCheckboxes();
            updateBulkBar();
        });

        // Select2 staff search config factory
        function staffSelect2Config(modalSelector) {
            return {
                placeholder: 'Search by name or staff number...',
                allowClear: true,
                minimumInputLength: 1,
                dropdownParent: $(modalSelector),
                ajax: {
                    url: '{{ route("user.ajax.staff.search") }}',
                    dataType: 'json',
                    delay: 300,
                    data: function(params) { return { q: params.term }; },
                    processResults: function(data) {
                        return {
                            results: data.map(function(s) {
                                return {
                                    id: s.id,
                                    text: s.full_names + (s.staff_number ? ' (' + s.staff_number + ')' : '')
                                };
                            })
                        };
                    }
                }
            };
        }

        // Init Select2 on Add modal open
        $('#addUserModal').on('shown.bs.modal', function() {
            $('#add_staff_id').select2(staffSelect2Config('#addUserModal'));
        });
        $('#addUserModal').on('hidden.bs.modal', function() {
            if ($('#add_staff_id').hasClass('select2-hidden-accessible')) {
                $('#add_staff_id').select2('destroy');
            }
        });

        // Clear form errors
        function clearFormErrors(form) {
            form.find('.is-invalid').removeClass('is-invalid');
            form.find('.invalid-feedback').text('');
        }

        // Show form errors
        function showFormErrors(form, errors) {
            clearFormErrors(form);
            $.each(errors, function(field, messages) {
                const input = form.find(`[name="${field}"]`);
                input.addClass('is-invalid');
                input.siblings('.invalid-feedback').text(messages[0]);
            });
        }

        // Add User Form Submit
        $('#addUserForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const btn = $('#addUserBtn');
            const btnText = btn.find('.btn-text');
            const spinner = btn.find('.spinner-border');

            clearFormErrors(form);
            btn.prop('disabled', true);
            spinner.removeClass('d-none');

            $.ajax({
                url: '{{ route("user.ajax.store") }}',
                method: 'POST',
                data: form.serialize(),
                success: function(response) {
                    if (response.success) {
                        $('#addUserModal').modal('hide');
                        form[0].reset();
                        usersTable.ajax.reload();
                        toastr.success(response.message);
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        showFormErrors(form, xhr.responseJSON.errors);
                    } else {
                        toastr.error(xhr.responseJSON.error || 'Failed to add user');
                    }
                },
                complete: function() {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });

        // Edit User Button Click
        $(document).on('click', '.edit-user', function(e) {
            e.preventDefault();
            const userId = $(this).data('id');
            const form = $('#editUserForm');

            clearFormErrors(form);

            $.ajax({
                url: `/users/ajax/${userId}`,
                method: 'GET',
                success: function(response) {
                    if (response.user) {
                        $('#edit_user_id').val(response.user.id);
                        $('#edit_name').val(response.user.name);
                        $('#edit_username').val(response.user.username);
                        $('#edit_email').val(response.user.email);
                        $('#edit_role').val(response.user.role_id);
                        $('#edit_password').val('');

                        // Init Select2 then pre-populate linked staff
                        const $staffSelect = $('#edit_staff_id');
                        if ($staffSelect.hasClass('select2-hidden-accessible')) {
                            $staffSelect.select2('destroy');
                        }
                        $staffSelect.select2(staffSelect2Config('#editUserModal'));
                        $staffSelect.empty();

                        if (response.user.linked_staff_id) {
                            const label = response.user.linked_staff_name +
                                (response.user.linked_staff_number ? ' (' + response.user.linked_staff_number + ')' : '');
                            $staffSelect.append(new Option(label, response.user.linked_staff_id, true, true));
                        } else {
                            $staffSelect.append(new Option('', '', true, true));
                        }
                        $staffSelect.trigger('change');

                        $('#editUserModal').modal('show');
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON.error || 'Failed to load user data');
                }
            });
        });

        // Destroy Select2 on edit modal close
        $('#editUserModal').on('hidden.bs.modal', function() {
            const $staffSelect = $('#edit_staff_id');
            if ($staffSelect.hasClass('select2-hidden-accessible')) {
                $staffSelect.select2('destroy');
            }
        });

        // Edit User Form Submit
        $('#editUserForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const userId = $('#edit_user_id').val();
            const btn = $('#editUserBtn');
            const btnText = btn.find('.btn-text');
            const spinner = btn.find('.spinner-border');

            clearFormErrors(form);
            btn.prop('disabled', true);
            spinner.removeClass('d-none');

            $.ajax({
                url: `/users/ajax/update/${userId}`,
                method: 'POST',
                data: form.serialize(),
                success: function(response) {
                    if (response.success) {
                        $('#editUserModal').modal('hide');
                        usersTable.ajax.reload();
                        toastr.success(response.message);
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        showFormErrors(form, xhr.responseJSON.errors);
                    } else {
                        toastr.error(xhr.responseJSON.error || 'Failed to update user');
                    }
                },
                complete: function() {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });

        // Suspend User Button Click
        $(document).on('click', '.suspend-user', function(e) {
            e.preventDefault();
            suspendUserId = $(this).data('id');
            const userName = $(this).data('name');
            $('#suspendUserName').text(userName);
            $('#suspendUserModal').modal('show');
        });

        // Activate User Button Click
        $(document).on('click', '.activate-user', function(e) {
            e.preventDefault();
            activateUserId = $(this).data('id');
            const userName = $(this).data('name');
            $('#activateUserName').text(userName);
            $('#activateUserModal').modal('show');
        });

        // Confirm Activate
        $('#confirmActivateBtn').on('click', function() {
            const btn = $(this);
            const spinner = btn.find('.spinner-border');

            btn.prop('disabled', true);
            spinner.removeClass('d-none');

            $.ajax({
                url: `/users/ajax/activate/${activateUserId}`,
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        $('#activateUserModal').modal('hide');
                        usersTable.ajax.reload();
                        toastr.success(response.message);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON.error || 'Failed to activate user');
                },
                complete: function() {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });

        // Confirm Suspend
        $('#confirmSuspendBtn').on('click', function() {
            const btn = $(this);
            const btnText = btn.find('.btn-text');
            const spinner = btn.find('.spinner-border');

            btn.prop('disabled', true);
            spinner.removeClass('d-none');

            $.ajax({
                url: `/users/ajax/suspend/${suspendUserId}`,
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        $('#suspendUserModal').modal('hide');
                        usersTable.ajax.reload();
                        toastr.success(response.message);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON.error || 'Failed to suspend user');
                },
                complete: function() {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });

        // Bulk Activate Button Click
        $('#bulkActivateBtn').on('click', function() {
            $('#bulkActivateCount').text(selectedUsers.size);
            $('#bulkActivateModal').modal('show');
        });

        // Bulk Suspend Button Click
        $('#bulkSuspendBtn').on('click', function() {
            $('#bulkSuspendCount').text(selectedUsers.size);
            $('#bulkSuspendModal').modal('show');
        });

        // Bulk Reset Password Button Click
        $('#bulkResetPasswordBtn').on('click', function() {
            $('#bulkResetCount').text(selectedUsers.size);
            $('#reset_mode_auto').prop('checked', true);
            $('#bulkFixedPasswordWrapper').addClass('d-none');
            $('#bulkFixedPassword').val('').removeClass('is-invalid');
            $('#bulkResetPasswordModal').modal('show');
        });

        // Toggle fixed-password field based on reset mode
        $('input[name="reset_mode"]').on('change', function() {
            $('#bulkFixedPasswordWrapper').toggleClass('d-none', $(this).val() !== 'fixed');
        });

        // Confirm Bulk Activate
        $('#confirmBulkActivateBtn').on('click', function() {
            const btn = $(this);
            const spinner = btn.find('.spinner-border');

            btn.prop('disabled', true);
            spinner.removeClass('d-none');

            $.ajax({
                url: '{{ route("user.ajax.bulk-activate") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    ids: Array.from(selectedUsers.keys())
                },
                success: function(response) {
                    if (response.success) {
                        $('#bulkActivateModal').modal('hide');
                        usersTable.ajax.reload();
                        toastr.success(response.message);
                    }
                },
                error: function(xhr) {
                    toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to activate users');
                },
                complete: function() {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });

        // Confirm Bulk Suspend
        $('#confirmBulkSuspendBtn').on('click', function() {
            const btn = $(this);
            const spinner = btn.find('.spinner-border');

            btn.prop('disabled', true);
            spinner.removeClass('d-none');

            $.ajax({
                url: '{{ route("user.ajax.bulk-suspend") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    ids: Array.from(selectedUsers.keys())
                },
                success: function(response) {
                    if (response.success) {
                        $('#bulkSuspendModal').modal('hide');
                        usersTable.ajax.reload();
                        toastr.success(response.message);
                    }
                },
                error: function(xhr) {
                    toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to suspend users');
                },
                complete: function() {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });

        // Confirm Bulk Reset Password
        $('#confirmBulkResetPasswordBtn').on('click', function() {
            const btn = $(this);
            const spinner = btn.find('.spinner-border');
            const mode = $('input[name="reset_mode"]:checked').val();
            const fixedPassword = $('#bulkFixedPassword');

            fixedPassword.removeClass('is-invalid');

            if (mode === 'fixed' && fixedPassword.val().length < 8) {
                fixedPassword.addClass('is-invalid');
                fixedPassword.siblings('.invalid-feedback').text('Password must be at least 8 characters.');
                return;
            }

            btn.prop('disabled', true);
            spinner.removeClass('d-none');

            $.ajax({
                url: '{{ route("user.ajax.bulk-reset-password") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    ids: Array.from(selectedUsers.keys()),
                    mode: mode,
                    fixed_password: mode === 'fixed' ? fixedPassword.val() : null
                },
                success: function(response) {
                    if (response.success) {
                        $('#bulkResetPasswordModal').modal('hide');
                        toastr.success(response.message);

                        const rows = response.results.map(function(r) {
                            return '<tr>' +
                                '<td>' + $('<div>').text(r.name).html() + '</td>' +
                                '<td>' + $('<div>').text(r.email).html() + '</td>' +
                                '<td><code>' + $('<div>').text(r.password).html() + '</code></td>' +
                                '<td><button type="button" class="btn btn-sm btn-outline-secondary copy-single-password" data-password="' + $('<div>').text(r.password).html() + '"><span class="material-symbols-outlined" style="font-size:16px;">content_copy</span></button></td>' +
                                '</tr>';
                        });
                        $('#bulkResetResultsBody').html(rows.join(''));
                        $('#bulkResetResultsModal').modal('show');
                        usersTable.ajax.reload();
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        toastr.error('Please check the form and try again.');
                    } else {
                        toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to reset passwords');
                    }
                },
                complete: function() {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });

        // Copy a single generated password
        $(document).on('click', '.copy-single-password', function() {
            navigator.clipboard.writeText($(this).data('password')).then(function() {
                toastr.success('Password copied');
            });
        });

        // Copy all results as "Name <email>: password" lines
        $('#copyAllResultsBtn').on('click', function() {
            const lines = [];
            $('#bulkResetResultsBody tr').each(function() {
                const cells = $(this).find('td');
                const name = $(cells[0]).text();
                const email = $(cells[1]).text();
                const password = $(cells[2]).text();
                lines.push(name + ' <' + email + '>: ' + password);
            });
            navigator.clipboard.writeText(lines.join('\n')).then(function() {
                toastr.success('All passwords copied');
            });
        });

        // Reset forms on modal close
        $('#addUserModal').on('hidden.bs.modal', function() {
            $('#addUserForm')[0].reset();
            clearFormErrors($('#addUserForm'));
            const $s = $('#add_staff_id');
            if ($s.hasClass('select2-hidden-accessible')) { $s.select2('destroy'); }
            $s.empty().append(new Option('', '', true, true));
        });

        $('#editUserModal').on('hidden.bs.modal', function() {
            $('#editUserForm')[0].reset();
            clearFormErrors($('#editUserForm'));
        });
    });
</script>
@endsection