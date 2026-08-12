@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">
                            <span class="material-symbols-outlined me-2">percent</span>
                            Tax Bands Management
                        </h4>
                        @permission('create_taxbands')
                        <div class="ms-auto">
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addTaxBandModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Tax Band</span>
                            </button>
                        </div>
                        @endpermission
                    </div>
                    <div class="card-body">
                        @permission('read_taxbands')
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="basic-1">
                                <thead>
                                    <tr>
                                        <th>Range</th>
                                        <th>Tax Rate (%)</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- DataTable will populate this -->
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="alert alert-warning">
                            <span class="material-symbols-outlined me-2">warning</span>
                            You don't have permission to view tax bands.
                        </div>
                        @endpermission
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Tax Band Modal -->
    <div class="modal fade" id="addTaxBandModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span class="material-symbols-outlined me-2">add</span>
                        Add Tax Band
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addTaxBandForm" action="#" method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_range_from" class="form-label">Range From</label>
                                    <input type="number" class="form-control" id="add_range_from" name="range_from"
                                        step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_range_to" class="form-label">Range To</label>
                                    <input type="number" class="form-control" id="add_range_to" name="range_to"
                                        step="0.01" min="0" placeholder="Leave blank for unlimited">
                                    <small class="form-text text-muted">Leave blank for "over" ranges</small>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_tax_rate" class="form-label">Tax Rate (%)</label>
                                    <input type="number" class="form-control" id="add_tax_rate" name="tax_rate"
                                        step="0.01" min="0" max="100" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        @permission('create_taxbands')
                        <button type="submit" class="btn btn-primary" id="addTaxBandBtn">
                            <span class="material-symbols-outlined me-2">save</span>
                            <span class="ms-1">Save Tax Band</span>
                        </button>
                        @endpermission
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Tax Band Modal -->
    <div class="modal fade" id="editTaxBandModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span class="material-symbols-outlined me-2">edit</span>
                        Edit Tax Band
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editTaxBandForm" action="#" method="POST">
                    <div class="modal-body">
                        <input type="hidden" id="edit_tax_band_id" name="id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_range_from" class="form-label">Range From</label>
                                    <input type="number" class="form-control" id="edit_range_from" name="range_from"
                                        step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_range_to" class="form-label">Range To</label>
                                    <input type="number" class="form-control" id="edit_range_to" name="range_to"
                                        step="0.01" min="0" placeholder="Leave blank for unlimited">
                                    <small class="form-text text-muted">Leave blank for "over" ranges</small>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_tax_rate" class="form-label">Tax Rate (%)</label>
                                    <input type="number" class="form-control" id="edit_tax_rate" name="tax_rate"
                                        step="0.01" min="0" max="100" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        @permission('update_taxbands')
                        <button type="submit" class="btn btn-primary" id="editTaxBandBtn">
                            <span class="material-symbols-outlined me-2">save</span>
                            <span class="ms-1">Update Tax Band</span>
                        </button>
                        @endpermission
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Tax Band Modal -->
    <div class="modal fade" id="deleteTaxBandModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span class="material-symbols-outlined me-2">delete</span>
                        Delete Tax Band
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <span class="material-symbols-outlined me-2">warning</span>
                        Are you sure you want to delete this tax band? This action cannot be undone.
                    </div>
                    <div id="deleteTaxBandInfo"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    @permission('delete_taxbands')
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <span class="material-symbols-outlined me-2">delete</span>
                        <span class="ms-1">Delete Tax Band</span>
                    </button>
                    @endpermission
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</div>
</div>
</div>

<script>
    // use toastr for notifications
    $(document).ready(function() {
        // Configure toastr for better notifications
        toastr.options = {
            "closeButton": true,
            "debug": false,
            "newestOnTop": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": false,
            "onclick": null,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "8000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut",
            "escapeHtml": false // Enable HTML content for better error display
        };
        let table = $('#basic-1').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: '{{ route("tax.data") }}',
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: function(json) {
                    if (json.success) {
                        return json.data;
                    } else {
                        toastr.error(json.error || 'Failed to load data');
                        return [];
                    }
                }
            },
            columns: [{
                    data: 'range_display'
                },
                {
                    data: 'tax_rate',
                    render: function(data, type, row) {
                        return data + '%';
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        return `
                <div class="d-flex flex-row gap-1">
                    @permission('update_taxbands')
                    <button class="btn btn-xs btn-light edit-taxband d-flex align-items-center" 
                        data-id="${row.id}" data-range_from="${row.range_from}" data-range_to="${row.range_to || ''}" data-tax_rate="${row.tax_rate}" style="padding:2px 6px;">
                        <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                    </button>
                    @endpermission
                    @permission('delete_taxbands')
                    <button class="btn btn-xs btn-danger delete-taxband d-flex align-items-center" 
                        data-id="${row.id}" style="padding:2px 6px;">
                        <span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>
                        <span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>
                    </button>
                    @endpermission
                </div>`;
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true
        });

        $('#addTaxBandForm').on('submit', function(e) {
            e.preventDefault();
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#addTaxBandBtn');
            var $icon = $btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();
            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Processing...');
            $btn.prop('disabled', true);
            $.ajax({
                url: '{{ route("tax.store") }}',
                type: 'POST',
                data: formData,
                success: function(res) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('Tax band saved successfully');
                        $('#addTaxBandModal').modal('hide');
                        $('#addTaxBandForm')[0].reset();
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error saving tax band');
                    }
                },
                error: function(xhr) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);
                    let msg = 'Validation error';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        // Format multiple validation errors with better spacing
                        let errors = Object.values(xhr.responseJSON.errors);
                        msg = '<div style="text-align: left;">' + errors.join('<br><br>') + '</div>';
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = '<div style="text-align: left;">' + xhr.responseJSON.error + '</div>';
                    }
                    toastr.error(msg, 'Validation Error', {
                        timeOut: 10000, // Show longer for validation errors
                        extendedTimeOut: 2000
                    });
                }
            });
        });

        $('#editTaxBandForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#edit_tax_band_id').val();
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
            var $btn = $('#editTaxBandBtn');
            var $icon = $btn.find('.material-symbols-outlined');
            var origText = $btn.find('span.ms-1').text();
            $icon.addClass('spin');
            $btn.find('span.ms-1').text('Processing...');
            $btn.prop('disabled', true);
            $.ajax({
                url: '{{ url("tax/bands") }}/' + id,
                type: 'PUT',
                data: formData,
                success: function(res) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('Tax band updated successfully');
                        $('#editTaxBandModal').modal('hide');
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error updating tax band');
                    }
                },
                error: function(xhr) {
                    $icon.removeClass('spin');
                    $btn.find('span.ms-1').text(origText);
                    $btn.prop('disabled', false);
                    let msg = 'Validation error';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        // Format multiple validation errors with better spacing
                        let errors = Object.values(xhr.responseJSON.errors);
                        msg = '<div style="text-align: left;">' + errors.join('<br><br>') + '</div>';
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = '<div style="text-align: left;">' + xhr.responseJSON.error + '</div>';
                    }
                    toastr.error(msg, 'Validation Error', {
                        timeOut: 10000, // Show longer for validation errors
                        extendedTimeOut: 2000
                    });
                }
            });
        });

        $(document).on('click', '.edit-taxband', function() {
            $('#edit_tax_band_id').val($(this).data('id'));
            $('#edit_range_from').val($(this).data('range_from'));
            $('#edit_range_to').val($(this).data('range_to'));
            $('#edit_tax_rate').val($(this).data('tax_rate'));
            $('#editTaxBandModal').modal('show');
        });

        $(document).on('click', '.delete-taxband', function() {
            var $btn = $(this);
            var $icon = $btn.find('.material-symbols-outlined').first();
            $icon.show();
            $btn.find('.material-symbols-outlined').last().hide();
            $btn.prop('disabled', true);
            $.ajax({
                url: '{{ url("tax/bands") }}/' + $btn.data('id'),
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    $icon.hide();
                    $btn.find('.material-symbols-outlined').last().show();
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('Tax band deleted successfully');
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error deleting tax band');
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

        $('#addTaxBandModal').on('hidden.bs.modal', function() {
            $('#addTaxBandForm')[0].reset();
        });

        $('#editTaxBandModal').on('hidden.bs.modal', function() {
            $('#editTaxBandForm')[0].reset();
        });
    });
</script>

@endsection