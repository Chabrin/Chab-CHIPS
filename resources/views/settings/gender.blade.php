@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Gender Settings</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addGenderModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Gender</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            {{-- toastr will be used for notifications --}}
                            <table class="display" id="basic-1">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Status</th>
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
<!-- Add/Edit Gender Modal -->
<div class="modal fade" id="addGenderModal" tabindex="-1" aria-labelledby="addGenderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addGenderModalLabel">Add Gender</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="genderForm">
                    <input type="hidden" name="id" id="genderId">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="name" id="name" required placeholder="Enter gender name">
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <input type="text" class="form-control" name="description" id="description" placeholder="Enter description">
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-success d-flex align-items-center" id="saveGenderBtn">
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

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    // use toastr for notifications
    $(document).ready(function() {
        let table = $('#basic-1').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/gender/list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'genders'
            },
            columns: [{
                    data: 'name'
                },
                {
                    data: 'description',
                    defaultContent: ''
                },
                {
                    data: 'deleted_at',
                    render: function(data, type, row) {
                        if (!data) {
                            return `<span class="bg-success-focus text-success-main px-2 py-1 rounded-pill fw-medium text-sm">Active</span>`;
                        } else {
                            return `<span class="bg-danger-focus text-danger-main px-2 py-1 rounded-pill fw-medium text-sm">Deleted</span>`;
                        }
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        return `
                <div class="d-flex flex-row gap-1">
                    <button class="btn btn-xs btn-light edit-gender d-flex align-items-center" 
                        data-id="${row.id}" data-name="${row.name}" data-description="${row.description || ''}" style="padding:2px 6px;">
                        <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                    </button>
                    <button class="btn btn-xs btn-danger delete-gender d-flex align-items-center" 
                        data-id="${row.id}" style="padding:2px 6px;">
                        <span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>
                        <span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>
                    </button>
                </div>`;
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true
        });

        $('#genderForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#genderId').val();
            var url = id ? "{{ url('/ajax/gender') }}/" + id : "{{ route('ajax.gender.store') }}";
            var method = id ? 'POST' : 'POST';
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveGenderBtn');
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
                        $('#addGenderModal').modal('hide');
                        $('#genderForm')[0].reset();
                        $('#genderId').val('');
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
        $(document).on('click', '.edit-gender', function() {
            $('#genderId').val($(this).data('id'));
            $('#name').val($(this).data('name'));
            $('#description').val($(this).data('description'));
            $('#addGenderModal').modal('show');
        });
        $(document).on('click', '.delete-gender', function() {
            var $btn = $(this);
            var $icon = $btn.find('.material-symbols-outlined').first();
            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);
            $.ajax({
                url: "{{ url('/ajax/gender') }}/" + $btn.data('id'),
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
        $('#addGenderModal').on('hidden.bs.modal', function() {
            $('#genderForm')[0].reset();
            $('#genderId').val('');
        });
    });
</script>