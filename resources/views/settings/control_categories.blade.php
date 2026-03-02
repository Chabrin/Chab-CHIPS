@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Control Categories</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Category</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="categoriesTable">
                                <thead>
                                    <tr>
                                        <th>Category Name</th>
                                        <th>Display Name</th>
                                        <th>Precedence</th>
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

<!-- Add/Edit Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">Add Control Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="categoryForm">
                    <input type="hidden" name="id" id="categoryId">
                    <div class="mb-3">
                        <label for="category_name" class="form-label">Category Name <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="category_name" id="category_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="display_name" class="form-label">Display Name <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="display_name" id="display_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="precedence" class="form-label">Precedence</label>
                        <input type="number" class="form-control" name="precedence" id="precedence">
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-success d-flex align-items-center" id="saveCategoryBtn">
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
    $(function() {
        var table = $('#categoriesTable').DataTable({
            ajax: {
                url: "{{ url('/control-categories/list') }}",
                dataSrc: 'categories'
            },
            columns: [{
                    data: 'category_name'
                },
                {
                    data: 'display_name'
                },
                {
                    data: 'precedence'
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return '<div class="d-flex gap-1">' +
                            '<button class="btn btn-xs btn-light edit-cat" data-id="' + row.id + '" data-category_name="' + row.category_name + '" data-display_name="' + row.display_name + '" data-precedence="' + (row.precedence || '') + '">' +
                            '<span class="material-symbols-outlined">edit_document</span></button>' +
                            '<button class="btn btn-xs btn-danger delete-cat" data-id="' + row.id + '">' +
                            '<span class="material-symbols-outlined">delete_sweep</span></button>' +
                            '</div>';
                    }
                }
            ]
        });

        $('#categoryForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#categoryId').val();
            var url = id ? "{{ url('/control-categories') }}/" + id : "{{ route('control_categories.store') }}";
            var method = 'POST';
            var data = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#saveCategoryBtn');
            $btn.prop('disabled', true);
            $.ajax({
                url: url,
                type: method,
                data: data,
                success: function(res) {
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('Saved');
                        $('#addCategoryModal').modal('hide');
                        $('#categoryForm')[0].reset();
                        $('#categoryId').val('');
                        table.ajax.reload();
                    } else {
                        toastr.error(res.error || 'Error');
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false);
                    var msg = 'Error';
                    if (xhr.responseJSON && xhr.responseJSON.errors) msg = Object.values(xhr.responseJSON.errors).join('\n');
                    toastr.error(msg);
                }
            });
        });

        $(document).on('click', '.edit-cat', function() {
            $('#categoryId').val($(this).data('id'));
            $('#category_name').val($(this).data('category_name'));
            $('#display_name').val($(this).data('display_name'));
            $('#precedence').val($(this).data('precedence'));
            $('#addCategoryModal').modal('show');
        });

        $(document).on('click', '.delete-cat', function() {
            if (!confirm('Delete this category and its controls?')) return;
            var id = $(this).data('id');
            $.ajax({
                url: "{{ url('/control-categories') }}/" + id,
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    if (res.success) {
                        toastr.success('Deleted');
                        table.ajax.reload();
                    } else toastr.error(res.error || 'Error');
                },
                error: function(xhr) {
                    toastr.error('Error deleting');
                }
            });
        });
    });
</script>