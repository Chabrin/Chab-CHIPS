@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Company Details</h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('listcompanies') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back_ios_new</span>
                                <span class="ms-1">Back to List</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @include('components.error_reports')
                        @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                        @endif
                        @if(isset($error))
                        <div class="alert alert-danger">{{ $error }}</div>
                        @endif
                        @if(isset($company))
                        <ul class="nav nav-tabs mb-3" id="companyTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab" aria-controls="summary" aria-selected="true">
                                    <span class="material-symbols-outlined me-1">business</span> Summary
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab" aria-controls="users" aria-selected="false">
                                    <span class="material-symbols-outlined me-1">group</span> Users
                                </button>
                            </li>
                            {{-- Fund Managers tab removed; assignment handled from Summary --}}
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="funds-tab" data-bs-toggle="tab" data-bs-target="#funds" type="button" role="tab" aria-controls="funds" aria-selected="false">
                                    <span class="material-symbols-outlined me-1">account_balance</span> Funds
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="contributions-tab" data-bs-toggle="tab" data-bs-target="#contributions" type="button" role="tab" aria-controls="contributions" aria-selected="false">
                                    <span class="material-symbols-outlined me-1">payments</span> Contributions
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content" id="companyTabContent">
                            <div class="tab-pane fade show active" id="summary" role="tabpanel" aria-labelledby="summary-tab">
                                <div class="row mb-2 align-items-center">
                                    <div class="col-md-8">
                                        @if($company->fund_manager)
                                        <div class="mb-3">
                                            <div class="mb-1"><strong>Fund Manager</strong></div>
                                            <h5 class="mb-1">
                                                <span class="badge badge-light-success">
                                                    {{ $company->fund_manager->fund_manager_name }}
                                                </span>
                                                <small class="text-muted">({{ sprintf('%07d', $company->fund_manager->id) }})</small>
                                            </h5>
                                            <div class="text-muted">
                                                &nbsp;|&nbsp;
                                                Email: {{ $company->fund_manager->fund_manager_email ?? '-' }}
                                                &nbsp;|&nbsp;
                                                Phone: {{ $company->fund_manager->fund_manager_phone ?? '-' }}
                                            </div>
                                        </div>
                                        @else
                                        <div class="text-muted">No fund manager assigned</div>
                                        @endif
                                    </div>
                                    <div class="col-md-4 text-end">
                                        @if($company->logo)
                                        <img src="{{ asset('storage/' . $company->logo) }}" alt="Logo" style="max-width:140px;max-height:140px;object-fit:contain;border:1px solid #eaeaea;padding:6px;">
                                        @else
                                        <div class="text-muted">No logo uploaded for this company.</div>
                                        @endif
                                    </div>
                                </div>
                                <hr />
                                <div class="row">
                                    <div class="col-md-12">
                                        <table class="table mb-0 employee-details-table" style="border:none;">
                                            <tbody>
                                                <tr>
                                                    <td><strong>Company Name:</strong></td>
                                                    <td>{{ $company->company_name }}</td>
                                                    <td><strong>Contact Name:</strong></td>
                                                    <td>{{ $company->contact_name }}</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Email:</strong></td>
                                                    <td>{{ $company->email }}</td>
                                                    <td><strong>PIN No:</strong></td>
                                                    <td>{{ $company->pin_no }}</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Address:</strong></td>
                                                    <td>{{ $company->address }}</td>
                                                    <td><strong>Town:</strong></td>
                                                    <td>{{ $company->town }}</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Tel:</strong></td>
                                                    <td>{{ $company->tel }}</td>
                                                    <td><strong>Tel2:</strong></td>
                                                    <td>{{ $company->tel2 }}</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Mobile:</strong></td>
                                                    <td>{{ $company->mobile }}</td>
                                                    <td><strong>Fax:</strong></td>
                                                    <td>{{ $company->fax }}</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Website:</strong></td>
                                                    <td>{{ $company->website }}</td>
                                                    <td><strong>Physical Address:</strong></td>
                                                    <td>{{ $company->physical_add }}</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Reg Cert:</strong></td>
                                                    <td>{{ $company->reg_cert }}</td>
                                                    <td></td>
                                                    <td></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>VAT No:</strong></td>
                                                    <td>{{ $company->vat_no }}</td>
                                                    <td><strong>Financial Start:</strong></td>
                                                    <td>{{ $company->fin_start }}</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Other Names:</strong></td>
                                                    <td>{{ $company->other_names }}</td>
                                                    <td><strong>Registered:</strong></td>
                                                    <td>{{ $company->registered ? 'Yes' : 'No' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            {{-- Users Tab --}}
                            <div class="tab-pane fade" id="users" role="tabpanel" aria-labelledby="users-tab">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="d-flex justify-content-end mb-2">
                                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                                <span class="material-symbols-outlined">add_circle</span>
                                                <span class="ms-1">Add User</span>
                                            </button>
                                        </div>
                                        <div class="table-responsive custom-scrollbar">
                                            <div id="users-toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>
                                            <table class="display" id="users-dt">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Email</th>
                                                        <th>Role</th>
                                                        <th>Status</th>
                                                        <th>Added On</th>
                                                        <th style="width:150px;">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <!-- Add/Edit User Modal -->
                                <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="addUserModalLabel">Add User</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form id="userForm">
                                                    <input type="hidden" name="company_id" value="{{ $company->id }}">
                                                    <input type="hidden" name="id" id="userId">
                                                    <div class="row">
                                                        <div class="col-lg-6 col-md-6 col-sm-12 mb-3">
                                                            <label for="names" class="form-label">Full Name <span style="color:red">*</span></label>
                                                            <input type="text" class="form-control" name="names" id="names" required placeholder="Enter full name">
                                                        </div>
                                                        <div class="col-lg-6 col-md-6 col-sm-12 mb-3">
                                                            <label for="email_address" class="form-label">Email Address <span style="color:red">*</span></label>
                                                            <input type="email" class="form-control" name="email_address" id="email_address" required placeholder="Enter email address">
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-lg-6 col-md-6 col-sm-12 mb-3">
                                                            <label for="role" class="form-label">Role <span style="color:red">*</span></label>
                                                            <select class="form-control" name="role" id="role" required>
                                                                <option value="">Select role</option>
                                                                <option value="user">User</option>
                                                                <option value="admin">Admin</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-lg-6 col-md-6 col-sm-12 mb-3">
                                                            <label for="password" class="form-label">Password</label>
                                                            <input type="password" class="form-control" name="password" id="password" placeholder="Enter password (min 6 characters)">
                                                            <small class="text-muted" id="passwordHint">Leave blank to keep current password (min 6 characters if provided)</small>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex justify-content-end gap-2">
                                                        <button type="button" class="btn btn-secondary btn-sm d-flex align-items-center" data-bs-dismiss="modal">
                                                            <span class="material-symbols-outlined">close</span>
                                                            <span class="ms-1">Cancel</span>
                                                        </button>
                                                        <button type="submit" class="btn btn-success btn-sm d-flex align-items-center" id="saveUserBtn">
                                                            <span class="material-symbols-outlined">check_circle</span>
                                                            <span class="ms-1">Save</span>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {{-- Fund Managers tab removed. Show assigned fund manager in Summary with assignment modal. --}}
                            <div class="tab-pane fade" id="funds" role="tabpanel" aria-labelledby="funds-tab">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="d-flex justify-content-end mb-2">
                                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addFundModal">
                                                <span class="material-symbols-outlined">add_circle</span>
                                                <span class="ms-1">Add Fund</span>
                                            </button>
                                        </div>
                                        <div class="table-responsive custom-scrollbar">
                                            <div id="funds-toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>
                                            <table class="display" id="funds-dt">
                                                <thead>
                                                    <tr>
                                                        <th>Code</th>
                                                        <th>Name</th>
                                                        <th>Currency</th>
                                                        <th style="width:120px;">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <!-- Add/Edit Fund Modal -->
                                <div class="modal fade" id="addFundModal" tabindex="-1" aria-labelledby="addFundModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="addFundModalLabel">Add Fund</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form id="fundForm">
                                                    <input type="hidden" name="company_id" value="{{ $company->id }}">
                                                    <input type="hidden" name="id" id="fundId">
                                                    <div class="row">
                                                        <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                                            <label for="fund_code" class="form-label">Fund Code <span style="color:red">*</span></label>
                                                            <input type="text" class="form-control" name="fund_code" id="fund_code" required placeholder="Enter fund code">
                                                        </div>
                                                        <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                                            <label for="fund_name" class="form-label">Fund Name <span style="color:red">*</span></label>
                                                            <input type="text" class="form-control" name="fund_name" id="fund_name" required placeholder="Enter fund name">
                                                        </div>
                                                        <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                                            <label for="currency_id" class="form-label">Currency <span style="color:red">*</span></label>
                                                            <select class="form-control" name="currency_id" id="currency_id" required>
                                                                <option value="">Select currency</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex justify-content-end gap-2">
                                                        <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                                                            <span class="material-symbols-outlined">close</span>
                                                            <span class="ms-1">Cancel</span>
                                                        </button>
                                                        <button type="submit" class="btn btn-success d-flex align-items-center" id="saveFundBtn">
                                                            <span class="material-symbols-outlined">check_circle</span>
                                                            <span class="ms-1">Save</span>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- Contributions Tab --}}
                        <div class="tab-pane fade" id="contributions" role="tabpanel" aria-labelledby="contributions-tab">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="table-responsive custom-scrollbar">
                                        <table class="display" id="contributions-dt">
                                            <thead>
                                                <tr>
                                                    <th>Code</th>
                                                    <th>Employee Amt</th>
                                                    <th>Employer Amt</th>
                                                    <th>Voluntary Amt</th>
                                                    <th>Total</th>
                                                    <th>Members</th>
                                                    <th>MOP</th>
                                                    <th>Value Date</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


{{-- company-data removed; assign via add/edit views instead --}}

<style>
    .spin {
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .employee-details-table {
        font-size: 15px;
        font-family: inherit;
    }

    /* Smooth tab transitions */
    .tab-pane {
        display: none;
        opacity: 0;
        transition: opacity 0.15s linear;
    }

    .tab-pane.active {
        display: block;
        opacity: 1;
    }

    .tab-pane.show {
        opacity: 1;
    }

    /* Ensure DataTables are hidden when tab is not active */
    .tab-pane:not(.active) .dataTables_wrapper {
        display: none !important;
    }

    /* Clickable contribution rows */
    #contributions-dt tbody tr {
        cursor: pointer;
    }

    #contributions-dt tbody tr:hover {
        background-color: #f8f9fa;
    }
</style>
<script>
    $(function() {
        // Fund managers DataTable
        var fundManagersTable = $('#fundmanagers-dt').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ route('ajax.fundmanagers.list', $company->id) }}",
                type: 'GET',
                dataType: 'json',
                dataSrc: 'managers'
            },
            columns: [{
                    data: 'fund_manager_name'
                },
                {
                    data: 'fund_manager_email',
                    defaultContent: ''
                },
                {
                    data: 'fund_manager_website',
                    defaultContent: ''
                },
                {
                    data: 'fund_manager_address',
                    defaultContent: ''
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return '<div class="d-flex flex-row gap-1">' +
                            '<button class="btn btn-xs btn-light edit-fundmanager d-flex align-items-center" ' +
                            'data-id="' + row.id + '" data-name="' + (row.fund_manager_name || '') + '" data-email="' + (row.fund_manager_email || '') + '" data-website="' + (row.fund_manager_website || '') + '" data-address="' + (row.fund_manager_address || '') + '" style="padding:2px 6px;">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>' +
                            '</button>' +
                            '<button class="btn btn-xs btn-danger delete-fundmanager d-flex align-items-center" ' +
                            'data-id="' + row.id + '" style="padding:2px 6px;">' +
                            '<span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>' +
                            '</button>' +
                            '</div>';
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, 'All']
            ]
        });

        $('#fundManagerForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#fundManagerId').val();
            var url = id ? "{{ url('/ajax/fundmanagers') }}/" + id : "{{ route('ajax.fundmanagers.store') }}";
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveFundManagerBtn');
            var $icon = $btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();
            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Processing...');
            $btn.prop('disabled', true);
            $.ajax({
                url: url,
                type: 'POST',
                data: formData,
                success: function(res) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('Saved successfully');
                        $('#addFundManagerModal').modal('hide');
                        $('#fundManagerForm')[0].reset();
                        $('#fundManagerId').val('');
                        fundManagersTable.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error saving');
                    }
                },
                error: function(xhr) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);
                    let msg = 'Validation error';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        msg = Object.values(xhr.responseJSON.errors).join('<br>');
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });

        $(document).on('click', '.edit-fundmanager', function() {
            $('#fundManagerId').val($(this).data('id'));
            $('#fund_manager_name').val($(this).data('name'));
            $('#fund_manager_email').val($(this).data('email'));
            $('#fund_manager_website').val($(this).data('website'));
            $('#fund_manager_address').val($(this).data('address'));
            $('#addFundManagerModal').modal('show');
        });

        $(document).on('click', '.delete-fundmanager', function() {
            var $btn = $(this);
            var $icon = $btn.find('.material-symbols-outlined').first();
            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);
            $.ajax({
                url: "{{ url('/ajax/fundmanagers') }}/" + $btn.data('id'),
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('Deleted');
                        fundManagersTable.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error deleting');
                    }
                },
                error: function(xhr) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);
                    let msg = 'Delete error';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });

        $('#addFundManagerModal').on('hidden.bs.modal', function() {
            $('#fundManagerForm')[0].reset();
            $('#fundManagerId').val('');
        });

        // Funds DataTable
        var fundsTable = $('#funds-dt').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ route('ajax.funds.list', $company->id) }}",
                type: 'GET',
                dataType: 'json',
                dataSrc: 'funds'
            },
            columns: [{
                    data: 'fund_code'
                },
                {
                    data: 'fund_name'
                },
                {
                    data: function(row) {
                        return row.currency ? row.currency.currency_code : '';
                    },
                    defaultContent: ''
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return '<div class="d-flex flex-row gap-1">' +
                            '<button class="btn btn-xs btn-light edit-fund d-flex align-items-center" data-id="' + row.id + '" data-code="' + (row.fund_code || '') + '" data-name="' + (row.fund_name || '') + '" data-currency="' + (row.currency_id || '') + '" style="padding:2px 6px;">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>' +
                            '</button>' +
                            '<button class="btn btn-xs btn-danger delete-fund d-flex align-items-center" data-id="' + row.id + '" style="padding:2px 6px;">' +
                            '<span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>' +
                            '</button>' +
                            '</div>';
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, 'All']
            ]
        });

        // populate currency dropdown when funds table loads via ajax
        fundsTable.on('xhr', function() {
            var json = fundsTable.ajax.json();
            if (json && json.currencies) {
                var currencyOptions = '<option value="">Select Currency</option>';
                $.each(json.currencies, function(i, cur) {
                    currencyOptions += '<option value="' + cur.id + '">' + cur.currency_code + ' - ' + cur.currency_name + '</option>';
                });
                $('#currency_id').html(currencyOptions);
            }
        });

        $('#fundForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#fundId').val();
            var url = id ? "{{ url('/ajax/funds') }}/" + id : "{{ route('ajax.funds.store') }}";
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveFundBtn');
            var $icon = $btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();
            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Processing...');
            $btn.prop('disabled', true);
            $.ajax({
                url: url,
                type: 'POST',
                data: formData,
                success: function(res) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('Saved successfully');
                        $('#addFundModal').modal('hide');
                        $('#fundForm')[0].reset();
                        $('#fundId').val('');
                        fundsTable.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error saving');
                    }
                },
                error: function(xhr) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);
                    let msg = 'Validation error';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        msg = Object.values(xhr.responseJSON.errors).join('<br>');
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });

        $(document).on('click', '.edit-fund', function() {
            $('#fundId').val($(this).data('id'));
            $('#fund_code').val($(this).data('code'));
            $('#fund_name').val($(this).data('name'));
            $('#currency_id').val($(this).data('currency'));
            $('#addFundModal').modal('show');
        });

        $(document).on('click', '.delete-fund', function() {
            var $btn = $(this);
            var $icon = $btn.find('.material-symbols-outlined').first();
            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);
            $.ajax({
                url: "{{ url('/ajax/funds') }}/" + $btn.data('id'),
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('Deleted');
                        fundsTable.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error deleting');
                    }
                },
                error: function(xhr) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);
                    let msg = 'Delete error';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });

        $('#addFundModal').on('hidden.bs.modal', function() {
            $('#fundForm')[0].reset();
            $('#fundId').val('');
        });

        // Users DataTable
        var usersTable = $('#users-dt').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ route('ajax.employer-logins.list', $company->id) }}",
                type: 'GET',
                dataType: 'json',
                dataSrc: 'logins'
            },
            columns: [{
                    data: 'names'
                },
                {
                    data: 'email_address'
                },
                {
                    data: 'role',
                    render: function(data) {
                        if (data === 'admin') {
                            return '<span class="badge badge-light-primary">Admin</span>';
                        }
                        return '<span class="badge badge-light-info">User</span>';
                    }
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        if (row.is_suspended) {
                            return '<span class="badge badge-light-danger">Suspended</span>';
                        }
                        if (row.is_locked) {
                            return '<span class="badge badge-light-warning">Locked</span>';
                        }
                        return '<span class="badge badge-light-success">Active</span>';
                    }
                },
                {
                    data: 'added_on',
                    render: function(data) {
                        if (!data) return '-';
                        return new Date(data).toLocaleString();
                    },
                    defaultContent: '-'
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        var suspendBtn = '';
                        if (row.is_suspended) {
                            suspendBtn = '<button class="btn btn-xs btn-success activate-user d-flex align-items-center" ' +
                                'data-id="' + row.id + '" style="padding:2px 6px;" title="Activate">' +
                                '<span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>' +
                                '<span class="material-symbols-outlined" style="font-size:18px;">check_circle</span>' +
                                '</button>';
                        } else {
                            suspendBtn = '<button class="btn btn-xs btn-warning suspend-user d-flex align-items-center" ' +
                                'data-id="' + row.id + '" style="padding:2px 6px;" title="Suspend">' +
                                '<span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>' +
                                '<span class="material-symbols-outlined" style="font-size:18px;">block</span>' +
                                '</button>';
                        }

                        return '<div class="d-flex flex-row gap-1">' +
                            '<button class="btn btn-xs btn-light edit-user d-flex align-items-center" ' +
                            'data-id="' + row.id + '" data-names="' + (row.names || '') + '" ' +
                            'data-email="' + (row.email_address || '') + '" ' +
                            'data-role="' + (row.role || '') + '" style="padding:2px 6px;" title="Edit">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>' +
                            '</button>' +
                            suspendBtn +
                            '<button class="btn btn-xs btn-danger delete-user d-flex align-items-center" ' +
                            'data-id="' + row.id + '" style="padding:2px 6px;" title="Delete">' +
                            '<span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>' +
                            '</button>' +
                            '</div>';
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, 'All']
            ]
        });

        $('#userForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#userId').val();
            var url = "{{ route('ajax.employer-logins.store') }}";
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveUserBtn');
            var $icon = $btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();
            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Processing...');
            $btn.prop('disabled', true);
            $.ajax({
                url: url,
                type: 'POST',
                data: formData,
                success: function(res) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('User saved successfully');
                        $('#addUserModal').modal('hide');
                        $('#userForm')[0].reset();
                        $('#userId').val('');
                        usersTable.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error saving user');
                    }
                },
                error: function(xhr) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);
                    let msg = 'Validation error';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        msg = Object.values(xhr.responseJSON.errors).join('<br>');
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });

        $(document).on('click', '.edit-user', function() {
            $('#userId').val($(this).data('id'));
            $('#names').val($(this).data('names'));
            $('#email_address').val($(this).data('email'));
            $('#role').val($(this).data('role'));
            $('#password').val('');
            $('#addUserModalLabel').text('Edit User');
            $('#addUserModal').modal('show');
        });

        $(document).on('click', '.suspend-user, .activate-user', function() {
            var $btn = $(this);
            var isSuspend = $btn.hasClass('suspend-user');
            var $icon = $btn.find('.material-symbols-outlined').first();
            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);
            $.ajax({
                url: "{{ url('/ajax/employer-logins') }}/" + $btn.data('id') + '/suspend',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    action: isSuspend ? 'suspend' : 'activate'
                },
                success: function(res) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success(res.message);
                        usersTable.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error updating status');
                    }
                },
                error: function(xhr) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);
                    let msg = 'Error updating status';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });

        $(document).on('click', '.delete-user', function() {
            if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                return;
            }
            var $btn = $(this);
            var $icon = $btn.find('.material-symbols-outlined').first();
            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);
            $.ajax({
                url: "{{ url('/ajax/employer-logins') }}/" + $btn.data('id'),
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('User deleted successfully');
                        usersTable.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error deleting user');
                    }
                },
                error: function(xhr) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);
                    let msg = 'Delete error';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });

        $('#addUserModal').on('hidden.bs.modal', function() {
            $('#userForm')[0].reset();
            $('#userId').val('');
            $('#addUserModalLabel').text('Add User');
        });

        // Contributions DataTable
        var contributionsTable = $('#contributions-dt').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ route('ajax.contributions.list', $company->id) }}",
                type: 'GET',
                dataType: 'json',
                dataSrc: 'contributions'
            },
            columns: [{
                    data: 'fund_code',
                    defaultContent: '-'
                },
                {
                    data: 'total_employee_amount',
                    render: function(data) {
                        return data ? parseFloat(data).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }) : '0.00';
                    }
                },
                {
                    data: 'total_employer_amount',
                    render: function(data) {
                        return data ? parseFloat(data).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }) : '0.00';
                    }
                },
                {
                    data: 'total_voluntary_amount',
                    render: function(data) {
                        return data ? parseFloat(data).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }) : '0.00';
                    }
                },
                {
                    data: 'grand_total_amount',
                    render: function(data) {
                        return data ? parseFloat(data).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }) : '0.00';
                    }
                },
                {
                    data: 'number_of_members',
                    defaultContent: '0'
                },
                {
                    data: 'mop',
                    defaultContent: '-'
                },
                {
                    data: 'value_date',
                    render: function(data) {
                        if (!data) return '-';
                        return new Date(data).toLocaleDateString();
                    },
                    defaultContent: '-'
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        if (row.confirmed) {
                            var confirmedDate = row.confirmed_on ? new Date(row.confirmed_on).toLocaleDateString() : '';
                            return '<span class="badge badge-light-success" title="Confirmed on ' + confirmedDate + '">Confirmed</span>';
                        }
                        return '<span class="badge badge-light-warning">Pending</span>';
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, 'All']
            ],
            order: [
                [7, 'desc']
            ] // Sort by value_date descending
        });

        // Make contribution rows clickable
        $('#contributions-dt tbody').on('click', 'tr', function() {
            var data = contributionsTable.row(this).data();
            if (data && data.id) {
                // Open details page in new tab
                var url = "{{ url('/contributions/details') }}/" + data.encrypted_id;
                window.open(url, '_blank');
            }
        });

        // Add pointer cursor for clickable rows
        $('#contributions-dt tbody').css('cursor', 'pointer');

        // Assign/Change UI removed; fund manager selection will be in add/edit forms instead
    });
</script>