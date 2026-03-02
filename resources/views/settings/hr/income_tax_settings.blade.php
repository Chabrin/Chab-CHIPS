@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
	<div class="container-fluid">
		<div class="row">
			<div class="col-sm-12">
				<div class="card">
					<div class="card-header card-no-border d-flex align-items-center">
						<h5 class="mb-0">Income Tax Settings</h5>
					</div>
					<div class="card-body">
						<div class="table-responsive custom-scrollbar">
							<table class="display" id="income-tax-settings-table">
								<thead>
									<tr>
										<th>#</th>
										<th>Band Name</th>
										<th>Min</th>
										<th>Max</th>
										<th>Rate (%)</th>
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
<!-- Add/Edit Income Tax Setting Modal -->
<div class="modal fade" id="addIncomeTaxSettingModal" tabindex="-1" aria-labelledby="addIncomeTaxSettingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addIncomeTaxSettingModalLabel">Add Income Tax Band</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="incomeTaxSettingForm">
                    <input type="hidden" name="id" id="incomeTaxSettingId">
                    <div class="mb-3">
                        <label for="band_name" class="form-label">Band Name <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="band_name" id="band_name" required placeholder="Enter band name">
                    </div>
                    <div class="mb-3">
                        <label for="min" class="form-label">Min <span style="color:red">*</span></label>
                        <input type="number" class="form-control" name="min" id="min" required placeholder="Enter min" step="0.01" min="0">
                    </div>
                    <div class="mb-3">
                        <label for="max" class="form-label">Max <span style="color:red">*</span></label>
                        <input type="number" class="form-control" name="max" id="max" required placeholder="Enter max" step="0.01" min="0">
                    </div>
                    <div class="mb-3">
                        <label for="rate" class="form-label">Rate (%) <span style="color:red">*</span></label>
                        <input type="number" class="form-control" name="rate" id="rate" required placeholder="Enter rate" step="0.01" min="0">
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="material-symbols-outlined">close</span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center" id="saveIncomeTaxSettingBtn">
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
		let table = $('#income-tax-settings-table').DataTable({
			processing: true,
			serverSide: false,
			ajax: {
				url: "{{ url('/ajax/hr/income-tax-settings/list') }}",
				type: 'GET',
				headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				},
				dataType: 'json',
				dataSrc: 'income_tax_settings'
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
					data: 'band_name'
				},
				{
					data: 'min',
					render: function(data) {
						return data !== null ? data : '-';
					}
				},
				{
					data: 'max',
					render: function(data) {
						return data !== null ? data : '-';
					}
				},
				{
					data: 'rate',
					render: function(data) {
						return data !== null ? data : '-';
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
								<button class="btn btn-xs btn-light edit-income-tax-setting" 
									data-id="${row.id}" 
									data-band_name="${row.band_name}" 
									data-min="${row.min}" 
									data-max="${row.max}" 
									data-rate="${row.rate}" 
									style="padding:1px 2px;">
									<span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
								</button>`;
						}
						if (row.can_delete) {
							buttons += `
								<button class="btn btn-xs btn-light delete-income-tax-setting" 
									data-id="${row.id}" style="padding:1px 2px;">
									<span class="material-symbols-outlined spin" style="font-size:18px;display:none;">sync</span>
									<span class="material-symbols-outlined" style="font-size:18px;">delete_sweep</span>
								</button>`;
						}
						buttons += '</div>';
						return buttons;
					}
				}
			],
			paging: true,
			info: true,
			lengthChange: true
		});
		$('#incomeTaxSettingForm').on('submit', function(e) {
			e.preventDefault();
			var id = $('#incomeTaxSettingId').val();
			var url = id ? "{{ url('/ajax/hr/income-tax-settings') }}/" + id : "{{ url('/ajax/hr/income-tax-settings') }}";
			var method = 'POST';
			var formData = $(this).serialize() + '&_token={{ csrf_token() }}';
			var $btn = $('#saveIncomeTaxSettingBtn');
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
						$('#addIncomeTaxSettingModal').modal('hide');
						$('#incomeTaxSettingForm')[0].reset();
						$('#incomeTaxSettingId').val('');
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

		$(document).on('click', '.edit-income-tax-setting', function() {
			$('#incomeTaxSettingId').val($(this).data('id'));
			$('#band_name').val($(this).data('band_name'));
			$('#min').val($(this).data('min'));
			$('#max').val($(this).data('max'));
			$('#rate').val($(this).data('rate'));
			$('#addIncomeTaxSettingModal').modal('show');
		});

		$(document).on('click', '.delete-income-tax-setting', function() {
			var $btn = $(this);
			var $icon = $btn.find('.material-symbols-outlined').first();

			if (!confirm('Are you sure you want to delete this income tax band?')) {
				return;
			}

			$icon.show();
			$btn.find('.material-symbols-outlined').last().hide();
			$btn.prop('disabled', true);

			$.ajax({
				url: "{{ url('/ajax/hr/income-tax-settings') }}/" + $btn.data('id'),
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

		$('#addIncomeTaxSettingModal').on('hidden.bs.modal', function() {
			$('#incomeTaxSettingForm')[0].reset();
			$('#incomeTaxSettingId').val('');
		});
	});
</script>
