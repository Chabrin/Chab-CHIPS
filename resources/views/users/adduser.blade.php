@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Add User from Employees</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-end mb-2">
                            <!-- maybe future actions -->
                        </div>
                        <div class="table-responsive custom-scrollbar">
                            <div id="users-toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>
                            <table class="display" id="eligible-employees-dt">
                                <thead>
                                    <tr>
                                        <th>Employee No</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>National ID</th>
                                        <th style="width:140px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Created confirmation modal -->
        <div class="modal fade" id="createdUserModal" tabindex="-1" aria-labelledby="createdUserModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-md">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createdUserModalLabel">User Created</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>User was created successfully. Temporary password:</p>
                        <div class="alert alert-secondary"><strong id="createdPassword"></strong></div>
                        <div class="text-muted small">Advise the user to change password on first login.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Create User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addUserForm">
                    <input type="hidden" name="employee_id" id="modal_employee_id">
                    <div class="row g-2">
                        <div class="col-lg-4">
                            <label class="form-label">Employee</label>
                            <input type="text" class="form-control" id="modal_employee_name" readonly>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Email</label>
                            <input type="text" class="form-control" id="modal_employee_email" readonly>
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="modal_username" class="form-control" required>
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role_id" id="modal_role_id" class="form-select" required>
                                <option value="">Select role</option>
                                @foreach($roles as $r)
                                <option value="{{ $r->id }}">{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>


                        <div class="col-lg-4">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="modal_start_date" class="form-control" required>
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" id="modal_end_date" class="form-control" required>
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label">Time In <span class="text-danger">*</span></label>
                            <input type="time" name="time_in" id="modal_time_in" class="form-control" required>
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label">Time Out <span class="text-danger">*</span></label>
                            <input type="time" name="time_out" id="modal_time_out" class="form-control" required>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label">Assigned Companies</label>
                            <select name="role_companies[]" id="modal_role_companies" class="form-select" multiple>
                                <!-- options populated via AJAX; Host (0) will be injected client-side -->
                            </select>
                            <div class="small text-muted">Assign companies. Host (0) is always included by default.</div>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Login Days <span class="text-danger">*</span></label>
                            <select name="login_days[]" id="modal_login_days" class="form-select" multiple required>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </div>

                        <div class="col-md-12 d-flex justify-content-end mt-3">
                            <button type="button" class="btn btn-secondary me-2 d-flex align-items-center" data-bs-dismiss="modal">
                                <span class="material-symbols-outlined">close</span>
                                <span class="ms-1">Cancel</span>
                            </button>
                            <button type="submit" id="saveUserBtn" class="btn btn-primary d-flex align-items-center">
                                <span class="material-symbols-outlined">check_circle</span>
                                <span class="ms-1">Create User</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $(function() {
        // populate companies select
        function loadCompaniesIntoSelect() {
            var $sel = $('#modal_role_companies');
            $sel.empty();
            // ensure Host(0) present
            $sel.append('<option value="0" selected>Host</option>');
            $.get('{{ url("/ajax/companies/list") }}').done(function(res) {
                if (res && res.success && res.data) {
                    res.data.forEach(function(c) {
                        // avoid duplicating Host id 0
                        if (parseInt(c.id) === 0) return;
                        $sel.append('<option value="' + c.id + '">' + (c.name || c.id) + '</option>');
                    });
                }
            }).fail(function() {
                console.warn('Failed to load companies for add user select');
                if (typeof toastr !== 'undefined') toastr.warning('Failed to load companies. Only Host is available.');
            });
        }

        loadCompaniesIntoSelect();

        function showSuccess(m) {
            toastr.success(m);
        }

        function showError(m) {
            toastr.error(m);
        }

        var table = $('#eligible-employees-dt').DataTable({
            ajax: {
                url: "{{ url('/ajax/eligible-employees') }}",
                dataSrc: function(json) {
                    if (!json || json.error) {
                        showError(json && json.error ? json.error : 'Failed to load employees');
                        $('#eligible-employees-dt tbody').html('<tr class="odd"><td valign="top" colspan="5" class="dataTables_empty">Error loading data</td></tr>');
                        return [];
                    }
                    return json.employees || [];
                },
                error: function() {
                    showError('Failed to load employees');
                    $('#eligible-employees-dt tbody').html('<tr class="odd"><td valign="top" colspan="5" class="dataTables_empty">Error loading data</td></tr>');
                }
            },
            columns: [{
                    data: 'employee_no'
                },
                {
                    data: function(row) {
                        return row.first_name + ' ' + row.surname;
                    }
                },
                {
                    data: 'email_address'
                },
                {
                    data: 'national_id'
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(row) {
                        return '<div class="d-flex gap-1">' +
                            '<button class="btn btn-sm btn-primary add-user-btn" data-emp="' + encodeURIComponent(JSON.stringify(row)) + '">' +
                            '<span class="material-symbols-outlined">person_add</span></button>' +
                            '</div>';
                    }
                }
            ],
            paging: true,
            info: true
        });

        // open modal with employee info
        $(document).on('click', '.add-user-btn', function() {
            var row = JSON.parse(decodeURIComponent($(this).attr('data-emp')));
            $('#modal_employee_id').val(row.id);
            $('#modal_employee_name').val(row.first_name + ' ' + row.surname);
            $('#modal_employee_email').val(row.email_address);
            $('#modal_username').val(row.first_name.toLowerCase() + '.' + row.surname.toLowerCase());
            $('#modal_role_id').val('');
            $('#modal_login_days').val([]);
            $('#modal_start_date').val('');
            $('#modal_end_date').val('');
            $('#addUserModal').modal('show');
        });

        $('#addUserForm').on('submit', function(e) {
            e.preventDefault();
            var $btn = $('#saveUserBtn');
            var $icon = $btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();
            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Processing...');
            $btn.prop('disabled', true);

            // ensure we include selected companies; if none selected, include Host(0)
            var selCompanies = $('#modal_role_companies').val() || [];
            if (selCompanies.length === 0) selCompanies = ['0'];
            // serialize and append role_companies
            var form = $(this);
            var dataArr = form.serializeArray();
            // remove any existing role_companies entries
            dataArr = dataArr.filter(function(i) {
                return i.name !== 'role_companies[]';
            });
            selCompanies.forEach(function(c) {
                dataArr.push({
                    name: 'role_companies[]',
                    value: c
                });
            });
            dataArr.push({
                name: '_token',
                value: '{{ csrf_token() }}'
            });
            var data = $.param(dataArr);
            $.post("{{ url('/ajax/users') }}", data).done(function(res) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);
                if (res.success) {
                    showSuccess('User created successfully');
                    // show created password in confirmation modal
                    if (res.password) {
                        $('#createdPassword').text(res.password);
                        $('#createdUserModal').modal('show');
                    }
                    $('#addUserModal').modal('hide');
                    table.ajax.reload(null, false);
                } else if (res.errors) {
                    var err = Object.values(res.errors).flat().join('<br>');
                    showError(err);
                } else {
                    showError(res.error || 'Failed to create user');
                }
            }).fail(function(jqXHR) {
                $icon.removeClass('spin');
                $btn.find('span.ms-1').text(origText);
                $btn.prop('disabled', false);
                var msg = 'Failed to create user';
                if (jqXHR && jqXHR.responseJSON) msg = jqXHR.responseJSON.error || jqXHR.responseJSON.message || JSON.stringify(jqXHR.responseJSON);
                showError(msg);
            });
        });
    });
</script>

<style>
    .material-symbols-outlined.spin {
        display: inline-block;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        100% {
            transform: rotate(360deg);
        }
    }
</style>

@endsection