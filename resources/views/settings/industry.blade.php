@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Industry Settings</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addIndustryModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Industry</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            {{-- toastr will be used for notifications --}}
                            <table class="display" id="basic-1">
                                <thead>
                                    <tr>
                                        <th>Industry Name</th>
                                        <th>Description</th>
                                        <th>Other</th>
                                        <th>Added By</th>
                                        <th>Added On</th>
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
<!-- Add/Edit Industry Modal -->
<div class="modal fade" id="addIndustryModal" tabindex="-1" aria-labelledby="addIndustryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addIndustryModalLabel">Add Industry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="industryForm">
                    <input type="hidden" name="id" id="industryId">
                    <div class="mb-3">
                        <label for="industry_name" class="form-label">Industry Name <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="industry_name" id="industry_name" required placeholder="Enter industry name">
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <input type="text" class="form-control" name="description" id="description" placeholder="Enter description">
                    </div>
                    <div class="mb-3">
                        <label for="other" class="form-label">Other</label>
                        <input type="text" class="form-control" name="other" id="other" placeholder="Other info">
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-success d-flex align-items-center" id="saveIndustryBtn">
                            <span class="material-symbols-outlined">check_circle</span>
                            <span class="ms-1">Save</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<script>
    // use toastr for notifications
    $(function() {
        var table = $('#basic-1').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/industry/list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'industries'
            },
            columns: [{
                    data: 'industry_name'
                },
                {
                    data: 'description'
                },
                {
                    data: 'other'
                },
                {
                    data: 'added_by',
                    defaultContent: ''
                },
                {
                    data: 'added_on',
                    defaultContent: ''
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return '<div class="d-flex flex-row gap-1">' +
                            '<button class="btn btn-xs btn-light edit-industry d-flex align-items-center" ' +
                            'data-id="' + row.id + '" data-industry_name="' + row.industry_name + '" data-description="' + (row.description || '') + '" data-other="' + (row.other || '') + '" style="padding:2px 6px;">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>' +
                            '</button>' +
                            '<button class="btn btn-xs btn-danger delete-industry d-flex align-items-center" ' +
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
                [10, 25, 50, 100, "All"]
            ]
        });
        $('#industryForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#industryId').val();
            var url = id ? "{{ url('/ajax/industry') }}/" + id : "{{ route('ajax.industry.store') }}";
            var method = id ? 'POST' : 'POST';
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveIndustryBtn');
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
                        $('#addIndustryModal').modal('hide');
                        $('#industryForm')[0].reset();
                        $('#industryId').val('');
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
        $(document).on('click', '.edit-industry', function() {
            $('#industryId').val($(this).data('id'));
            $('#industry_name').val($(this).data('industry_name'));
            $('#description').val($(this).data('description'));
            $('#other').val($(this).data('other'));
            $('#addIndustryModal').modal('show');
        });
        $(document).on('click', '.delete-industry', function() {
            var $btn = $(this);
            var $icon = $btn.find('.material-symbols-outlined').first();
            var name = $btn.siblings('.edit-industry').data('industry_name');
            if (!confirm('Are you sure you want to delete "' + name + '"?')) {
                return;
            }
            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);
            $.ajax({
                url: "{{ url('/ajax/industry') }}/" + $btn.data('id'),
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
        $('#addIndustryModal').on('hidden.bs.modal', function() {
            $('#industryForm')[0].reset();
            $('#industryId').val('');
        });
    });
</script>
@endsection
