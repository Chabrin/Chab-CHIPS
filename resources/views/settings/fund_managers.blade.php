@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Fund Managers</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addFundManagerModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Fund Manager</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="fund-managers-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Website</th>
                                        <th>Phone</th>
                                        <th>Address</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Fund Manager Modal -->
<div class="modal fade" id="addFundManagerModal" tabindex="-1" aria-labelledby="addFundManagerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addFundManagerModalLabel">Add Fund Manager</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addFundManagerForm">
                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <label for="add_fund_manager_name" class="form-label">Name <span style="color:red">*</span></label>
                            <input type="text" class="form-control" name="fund_manager_name" id="add_fund_manager_name" required placeholder="Enter fund manager name">
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label for="add_fund_manager_email" class="form-label">Email</label>
                            <input type="email" class="form-control" name="fund_manager_email" id="add_fund_manager_email" placeholder="Enter email address">
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label for="add_fund_manager_website" class="form-label">Website</label>
                            <input type="text" class="form-control" name="fund_manager_website" id="add_fund_manager_website" placeholder="Enter website URL">
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label for="add_fund_manager_phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" name="fund_manager_phone" id="add_fund_manager_phone" placeholder="Enter phone number">
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label for="add_fund_manager_address" class="form-label">Address</label>
                            <input type="text" class="form-control" name="fund_manager_address" id="add_fund_manager_address" placeholder="Enter address">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-success d-flex align-items-center" id="addSaveFundManagerBtn">
                            <span class="material-symbols-outlined">check_circle</span>
                            <span class="ms-1">Save</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Fund Manager Modal -->
<div class="modal fade" id="editFundManagerModal" tabindex="-1" aria-labelledby="editFundManagerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editFundManagerModalLabel">Edit Fund Manager</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editFundManagerForm">
                    <input type="hidden" name="id" id="editFundManagerId">
                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <label for="edit_fund_manager_name" class="form-label">Name <span style="color:red">*</span></label>
                            <input type="text" class="form-control" name="fund_manager_name" id="edit_fund_manager_name" required placeholder="Enter fund manager name">
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label for="edit_fund_manager_email" class="form-label">Email</label>
                            <input type="email" class="form-control" name="fund_manager_email" id="edit_fund_manager_email" placeholder="Enter email address">
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label for="edit_fund_manager_website" class="form-label">Website</label>
                            <input type="text" class="form-control" name="fund_manager_website" id="edit_fund_manager_website" placeholder="Enter website URL">
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label for="edit_fund_manager_phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" name="fund_manager_phone" id="edit_fund_manager_phone" placeholder="Enter phone number">
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label for="edit_fund_manager_address" class="form-label">Address</label>
                            <input type="text" class="form-control" name="fund_manager_address" id="edit_fund_manager_address" placeholder="Enter address">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-success d-flex align-items-center" id="editSaveFundManagerBtn">
                            <span class="material-symbols-outlined">check_circle</span>
                            <span class="ms-1">Save changes</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(function() {
        var table = $('#fund-managers-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/fundmanagers') }}",
                type: 'GET',
                dataType: 'json',
                dataSrc: 'managers'
            },
            columns: [{
                    data: 'fund_manager_name'
                },
                {
                    data: 'fund_manager_email'
                },
                {
                    data: 'fund_manager_website'
                },
                {
                    data: 'fund_manager_phone'
                },
                {
                    data: 'fund_manager_address'
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return '<div class="d-flex flex-row gap-1">' +
                            '<button class="btn btn-xs btn-light edit-manager d-flex align-items-center" data-id="' + row.id + '" data-name="' + (row.fund_manager_name || '') + '" data-email="' + (row.fund_manager_email || '') + '" data-website="' + (row.fund_manager_website || '') + '" data-phone="' + (row.fund_manager_phone || '') + '" data-address="' + (row.fund_manager_address || '') + '" style="padding:2px 6px;">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>' +
                            '</button>' +
                            '<button class="btn btn-xs btn-danger delete-manager d-flex align-items-center" data-id="' + row.id + '" style="padding:2px 6px;">' +
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

        // Add form submit
        $('#addFundManagerForm').on('submit', function(e) {
            e.preventDefault();
            var url = "{{ route('ajax.fundmanagers.store') }}";
            var method = 'POST';
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#addSaveFundManagerBtn');
            var $icon = $btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();
            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Processing...');
            $btn.prop('disabled', true);
            $.ajax({
                url: url,
                type: method,
                data: formData,
                success: function(res) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('Saved successfully');
                        $('#addFundManagerModal').modal('hide');
                        $('#addFundManagerForm')[0].reset();
                        table.ajax.reload(null, false);
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

        // Edit button opens edit modal and populates fields
        $(document).on('click', '.edit-manager', function() {
            $('#editFundManagerId').val($(this).data('id'));
            $('#edit_fund_manager_name').val($(this).data('name'));
            $('#edit_fund_manager_email').val($(this).data('email'));
            $('#edit_fund_manager_website').val($(this).data('website'));
            $('#edit_fund_manager_phone').val($(this).data('phone'));
            $('#edit_fund_manager_address').val($(this).data('address'));
            $('#editFundManagerModal').modal('show');
        });

        // Edit form submit
        $('#editFundManagerForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#editFundManagerId').val();
            var url = "{{ url('/ajax/fundmanagers') }}/" + id;
            var method = 'POST';
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#editSaveFundManagerBtn');
            var $icon = $btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();
            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Processing...');
            $btn.prop('disabled', true);
            $.ajax({
                url: url,
                type: method,
                data: formData,
                success: function(res) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('Updated successfully');
                        $('#editFundManagerModal').modal('hide');
                        $('#editFundManagerForm')[0].reset();
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error updating');
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

        $(document).on('click', '.delete-manager', function() {
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
                        table.ajax.reload(null, false);
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
            $('#addFundManagerForm')[0].reset();
        });

        $('#editFundManagerModal').on('hidden.bs.modal', function() {
            $('#editFundManagerForm')[0].reset();
            $('#editFundManagerId').val('');
        });
    });
</script>