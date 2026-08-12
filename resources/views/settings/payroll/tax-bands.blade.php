@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Income Tax Bands</h4>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_tax_bands')
                            <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addTaxBandModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Tax Band</span>
                            </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="taxband-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Range From</th>
                                        <th>Range To</th>
                                        <th>Tax Rate (%)</th>
                                        <th>Added By</th>
                                        <th>Date Added</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data loaded via DataTables AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Tax Band Modal -->
<div class="modal fade" id="addTaxBandModal" tabindex="-1" aria-labelledby="addTaxBandModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTaxBandModalLabel">Add Tax Band</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="taxBandForm">
                    <input type="hidden" name="id" id="taxBandId">

                    <div class="mb-3">
                        <label for="range_from" class="form-label">Range From <span style="color:red">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="range_from" id="range_from" required placeholder="e.g. 0.00">
                        <small class="text-muted">Enter the minimum amount for this tax band</small>
                    </div>

                    <div class="mb-3" id="range_to_field">
                        <label for="range_to" class="form-label">Range To</label>
                        <input type="number" step="0.01" class="form-control" name="range_to" id="range_to" placeholder="e.g. 235000.00">
                        <small class="text-muted">Enter the maximum amount for this tax band</small>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_last" id="is_last" value="1">
                            <label class="form-check-label" for="is_last">
                                This is the last tax band (And Above)
                            </label>
                        </div>
                        <small class="text-muted">Check this if there's no upper limit for this band</small>
                    </div>

                    <div class="mb-3">
                        <label for="tax_rate" class="form-label">Tax Rate (%) <span style="color:red">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="tax_rate" id="tax_rate" required placeholder="e.g. 10.00">
                        <small class="text-muted">Enter the tax rate as a percentage</small>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        @permission('create_tax_bands')
                        <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center" id="saveTaxBandBtn">
                            <span class="material-symbols-outlined">check_circle</span>
                            <span class="ms-1">Save</span>
                        </button>
                        @endpermission
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

<script>
    $(document).ready(function() {
        let table = $('#taxband-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/payroll/tax-bands/list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: 'taxBands'
            },
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'range_from',
                    render: function(data) {
                        return data ? parseFloat(data).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }) : '0.00';
                    }
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        if (row.is_last) {
                            return '<span class="badge bg-info">And Above</span>';
                        }
                        return row.range_to ? parseFloat(row.range_to).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }) : '-';
                    }
                },
                {
                    data: 'tax_rate',
                    render: function(data) {
                        return data ? parseFloat(data).toFixed(2) + '%' : '0.00%';
                    }
                },
                {
                    data: 'added_by',
                    render: function(data) {
                        return data || '-';
                    }
                },
                {
                    data: 'created_at',
                    render: function(data) {
                        return data ? new Date(data).toLocaleString() : '-';
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';

                        if (row.can_update) {
                            buttons += `
                                <button class="btn btn-xs btn-light edit-taxband" 
                                    data-id="${row.id}" 
                                    data-range_from="${row.range_from}"
                                    data-range_to="${row.range_to || ''}"
                                    data-tax_rate="${row.tax_rate}"
                                    data-is_last="${row.is_last ? 1 : 0}"
                                    style="padding:1px 2px;">
                                    <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                                </button>`;
                        }

                        buttons += '</div>';
                        return buttons;
                    }
                }
            ],
            order: [
                [1, 'asc']
            ],
            paging: true,
            info: true,
            lengthChange: true
        });

        // Toggle range_to field based on is_last checkbox
        $('#is_last').on('change', function() {
            if ($(this).is(':checked')) {
                $('#range_to_field').hide();
                $('#range_to').val('').prop('required', false);
            } else {
                $('#range_to_field').show();
                $('#range_to').prop('required', false);
            }
        });

        $('#taxBandForm').on('submit', function(e) {
            e.preventDefault();

            var id = $('#taxBandId').val();
            var url = id ? "{{ url('/ajax/payroll/tax-bands') }}/" + id : "{{ url('/ajax/payroll/tax-bands') }}";
            var method = 'POST';
            var formData = $(this).serialize() + '&_token={{ csrf_token() }}';

            var $btn = $('#saveTaxBandBtn');
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
                        toastr.success('Tax band saved successfully');
                        $('#addTaxBandModal').modal('hide');
                        $('#taxBandForm')[0].reset();
                        $('#taxBandId').val('');
                        $('#range_to_field').show();
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
                        msg = Object.values(xhr.responseJSON.errors).join('<br>');
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });

        $(document).on('click', '.edit-taxband', function() {
            var id = $(this).data('id');
            var rangeFrom = $(this).data('range_from');
            var rangeTo = $(this).data('range_to');
            var taxRate = $(this).data('tax_rate');
            var isLast = $(this).data('is_last');

            $('#taxBandId').val(id);
            $('#range_from').val(rangeFrom);
            $('#range_to').val(rangeTo);
            $('#tax_rate').val(taxRate);
            $('#is_last').prop('checked', isLast == 1);

            if (isLast == 1) {
                $('#range_to_field').hide();
                $('#range_to').prop('required', false);
            } else {
                $('#range_to_field').show();
            }

            $('#addTaxBandModalLabel').text('Edit Tax Band');
            $('#addTaxBandModal').modal('show');
        });

        $('#addTaxBandModal').on('hidden.bs.modal', function() {
            $('#taxBandForm')[0].reset();
            $('#taxBandId').val('');
            $('#addTaxBandModalLabel').text('Add Tax Band');
            $('#range_to_field').show();
        });
    });
</script>