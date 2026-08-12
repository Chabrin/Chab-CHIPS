@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Countries</h4>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_countries')
                            <button class="btn btn-primary btn-sm d-flex align-items-center" id="addCountryBtn">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Country</span>
                            </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="countriesTable" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Country Code</th>
                                        <th>Country Name</th>
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

<!-- Add / Edit Country Modal -->
<div class="modal fade" id="countryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="countryModalTitle">Add Country</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="countryForm">
                    <input type="hidden" id="countryId" name="id">

                    <div class="mb-3">
                        <label class="form-label">Country Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="country_code" id="country_code" placeholder="e.g. KE" maxlength="10" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Country Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="country_name" id="country_name" placeholder="e.g. Kenya" required>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-primary d-flex align-items-center" id="saveCountryBtn">
                            <span class="material-symbols-outlined" id="saveCountryIcon">check_circle</span>
                            <span class="ms-1" id="saveCountryText">Save</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

<script>
$(function () {

    // ── DataTable ──────────────────────────────────────────────────────────────
    var table = $('#countriesTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ url('/manage/countries/data') }}",
            dataSrc: 'data'
        },
        columns: [
            { data: null, render: function(d, t, r, m) { return m.row + 1; }, orderable: false },
            { data: 'country_code' },
            { data: 'country_name' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(d, t, r) {
                    var html = '<div class="d-flex flex-row gap-1">';
                    if (r.can_edit) {
                        html += '<button class="btn btn-xs btn-light edit-country d-flex align-items-center" ' +
                            'data-row=\'' + JSON.stringify(r) + '\' style="padding:2px 6px;">' +
                            '<span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>' +
                            '</button>';
                    }
                    if (r.can_delete) {
                        html += '<button class="btn btn-xs btn-outline-primary delete-country d-flex align-items-center" ' +
                            'data-id="' + r.id + '" style="padding:2px 6px;">' +
                            '<span class="material-symbols-outlined del-icon" style="font-size:18px;">delete_sweep</span>' +
                            '<span class="spinner-border spinner-border-sm del-spin" style="display:none;"></span>' +
                            '</button>';
                    }
                    html += '</div>';
                    return html;
                }
            }
        ],
        paging: true,
        info: true,
        lengthChange: true,
        lengthMenu: [[10,25,50,-1],[10,25,50,'All']]
    });

    // ── Add Button ─────────────────────────────────────────────────────────────
    $('#addCountryBtn').on('click', function() {
        $('#countryForm')[0].reset();
        $('#countryId').val('');
        $('#countryModalTitle').text('Add Country');
        $('#countryModal').modal('show');
    });

    // ── Edit Button ────────────────────────────────────────────────────────────
    $(document).on('click', '.edit-country', function() {
        var r = JSON.parse($(this).attr('data-row'));
        $('#countryId').val(r.id);
        $('#country_code').val(r.country_code);
        $('#country_name').val(r.country_name);
        $('#countryModalTitle').text('Edit Country');
        $('#countryModal').modal('show');
    });

    // ── Form Submit (Add / Edit) ───────────────────────────────────────────────
    $('#countryForm').on('submit', function(e) {
        e.preventDefault();
        var id  = $('#countryId').val();
        var url = id
            ? "{{ url('/manage/countries/update') }}/" + id
            : "{{ url('/manage/countries/store') }}";

        var $btn  = $('#saveCountryBtn');
        var $icon = $('#saveCountryIcon');
        var $text = $('#saveCountryText');
        $icon.text('sync').addClass('spin');
        $text.text('Saving...');
        $btn.prop('disabled', true);

        $.ajax({
            url: url,
            type: 'POST',
            data: $(this).serialize() + '&_token={{ csrf_token() }}',
            success: function(res) {
                $icon.text('check_circle').removeClass('spin');
                $text.text('Save');
                $btn.prop('disabled', false);
                if (res.success) {
                    toastr.success('Saved successfully');
                    $('#countryModal').modal('hide');
                    $('#countryForm')[0].reset();
                    table.ajax.reload(null, false);
                } else {
                    toastr.error(res.error || 'Error saving country');
                }
            },
            error: function(xhr) {
                $icon.text('check_circle').removeClass('spin');
                $text.text('Save');
                $btn.prop('disabled', false);
                var msg = 'Validation error';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).join('\n');
                } else if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                }
                toastr.error(msg);
            }
        });
    });

    // ── Delete ─────────────────────────────────────────────────────────────────
    $(document).on('click', '.delete-country', function() {
        if (!confirm('Delete this country?')) return;
        var $btn  = $(this);
        var $icon = $btn.find('.del-icon');
        var $spin = $btn.find('.del-spin');
        $icon.hide(); $spin.show(); $btn.prop('disabled', true);

        $.ajax({
            url: "{{ url('/manage/countries/delete') }}/" + $btn.data('id'),
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
                $icon.show(); $spin.hide(); $btn.prop('disabled', false);
                if (res.success) {
                    toastr.success('Deleted');
                    table.ajax.reload(null, false);
                } else {
                    toastr.error(res.error || 'Error deleting');
                }
            },
            error: function(xhr) {
                $icon.show(); $spin.hide(); $btn.prop('disabled', false);
                var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Delete failed';
                toastr.error(msg);
            }
        });
    });

    // ── Reset modal on close ───────────────────────────────────────────────────
    $('#countryModal').on('hidden.bs.modal', function() {
        $('#countryForm')[0].reset();
        $('#countryId').val('');
    });
});
</script>
