@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Bank Branches Settings</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addBranchModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Branch</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="branchesTable" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>Branch Code</th>
                                        <th>Branch Name</th>
                                        <th>Bank</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Branch Modal -->
<div class="modal fade" id="addBranchModal" tabindex="-1" aria-labelledby="addBranchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addBranchModalLabel">Add Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="branchForm">
                    <input type="hidden" name="id" id="branchId">
                    <div class="mb-3">
                        <label for="branch_code" class="form-label">Branch Code <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="branch_code" id="branch_code" required placeholder="Enter branch code">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="branch_name" class="form-label">Branch Name <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="branch_name" id="branch_name" required placeholder="Enter branch name">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="bank_id" class="form-label">Bank <span style="color:red">*</span></label>
                        <select class="form-select" id="bank_id" name="bank_id" required>
                            <option value="">Select bank</option>
                            @foreach($banks as $bank)
                            <option value="{{ $bank->id }}">{{ $bank->bank_name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-primary d-flex align-items-center" id="saveBranchBtn">
                            <span class="material-symbols-outlined">check_circle</span>
                            <span class="ms-1">Save</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(function() {
        var table = $('#branchesTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/settings/branches/list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'branches'
            },
            columns: [{
                    data: 'branch_code',
                    render: data => data || '-'
                },
                {
                    data: 'branch_name',
                    render: data => data || '-'
                },
                {
                    data: null,
                    defaultContent: '-',
                    render: function(data, type, row) {
                        return (row.bank && row.bank.bank_name) ? row.bank.bank_name : '-';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return '<div class="d-flex flex-row gap-1">' +
                            '<a href="javascript:void(0)" class="text-primary edit-branch" ' +
                            'data-id="' + row.id + '" data-branch_code="' + (row.branch_code || '') + '" data-branch_name="' + (row.branch_name || '') + '" data-bank_id="' + (row.bank_id || '') + '" title="Edit">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>' +
                            '</a>' +
                            '<a href="javascript:void(0)" class="text-primary delete-branch" ' +
                            'data-id="' + row.id + '" data-name="' + (row.branch_name || '') + '" title="Delete">' +
                            '<span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>' +
                            '</a>' +
                            '</div>';
                    }
                }
            ],
            columnDefs: [{
                targets: 0,
                className: 'text-start'
            }],
            paging: true,
            info: true,
            lengthChange: true,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ]
        });

        $('#branchForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#branchId').val();
            var url = id ? "{{ url('/settings/branches/update') }}/" + id : "{{ url('/settings/branches/store') }}";
            var method = 'POST';
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveBranchBtn');
            var $icon = $btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();
            $('.invalid-feedback').text('').hide();
            $('.form-control, .form-select').removeClass('is-invalid');
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
                        $('#addBranchModal').modal('hide');
                        $('#branchForm')[0].reset();
                        $('#branchId').val('');
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error saving');
                    }
                },
                error: function(xhr) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        $.each(xhr.responseJSON.errors, function(field, messages) {
                            const input = $(`[name="${field}"]`);
                            input.addClass('is-invalid');
                            input.siblings('.invalid-feedback').text(messages[0]).show();
                        });
                    } else {
                        toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Error saving');
                    }
                }
            });
        });

        $(document).on('click', '.edit-branch', function() {
            $('#branchId').val($(this).data('id'));
            $('#branch_code').val($(this).data('branch_code'));
            $('#branch_name').val($(this).data('branch_name'));
            $('#bank_id').val($(this).data('bank_id'));
            $('.invalid-feedback').text('').hide();
            $('.form-control, .form-select').removeClass('is-invalid');
            $('#addBranchModal').modal('show');
        });

        $(document).on('click', '.delete-branch', function() {
            var $btn = $(this);
            var name = $btn.data('name');
            Swal.fire({
                title: 'Delete branch?',
                text: `Are you sure you want to delete "${name}"? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (!result.isConfirmed) return;

                var $icon = $btn.find('.material-symbols-outlined').first();
                $icon.show();
                $btn.find('.material-symbols-outlined').last().hide();
                $btn.prop('disabled', true);
                $.ajax({
                    url: "{{ url('/settings/branches/delete') }}/" + $btn.data('id'),
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
                        toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Delete error');
                    }
                });
            });
        });

        $('#addBranchModal').on('hidden.bs.modal', function() {
            $('#branchForm')[0].reset();
            $('#branchId').val('');
            $('.invalid-feedback').text('').hide();
            $('.form-control, .form-select').removeClass('is-invalid');
        });
    });
</script>
