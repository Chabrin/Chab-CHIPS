@php
    $context = $context ?? 'profile';
@endphp

<form id="passwordChangeForm" autocomplete="off">
    @csrf
    <div class="mb-3">
        <label for="current_password_{{ $context }}" class="form-label">Current Password <span class="text-danger">*</span></label>
        <input type="password" class="form-control" id="current_password_{{ $context }}" name="current_password" required autocomplete="current-password">
        <div class="invalid-feedback"></div>
    </div>
    <div class="mb-3">
        <label for="new_password_{{ $context }}" class="form-label">New Password <span class="text-danger">*</span></label>
        <input type="password" class="form-control" id="new_password_{{ $context }}" name="new_password" required minlength="8" autocomplete="new-password">
        <div class="invalid-feedback"></div>
    </div>
    <div class="mb-3">
        <label for="new_password_confirmation_{{ $context }}" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
        <input type="password" class="form-control" id="new_password_confirmation_{{ $context }}" name="new_password_confirmation" required minlength="8" autocomplete="new-password">
        <div class="invalid-feedback"></div>
    </div>
    <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center" id="passwordChangeBtn">
        <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
        <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
        <span>Update Password</span>
    </button>
</form>

<script>
    (function ($) {
        var $form = $('#passwordChangeForm');
        var context = @json($context);

        function clearErrors(form) {
            form.find('.is-invalid').removeClass('is-invalid');
            form.find('.invalid-feedback').text('');
        }

        function showErrors(form, errors) {
            clearErrors(form);
            $.each(errors, function (field, messages) {
                var input = form.find('[name="' + field + '"]');
                input.addClass('is-invalid');
                input.siblings('.invalid-feedback').text(messages[0]);
            });
        }

        $form.off('submit').on('submit', function (e) {
            e.preventDefault();
            var btn = $('#passwordChangeBtn');
            var spinner = btn.find('.spinner-border');
            var icon = btn.find('.material-symbols-outlined');

            clearErrors($form);
            btn.prop('disabled', true);
            spinner.removeClass('d-none');
            icon.hide();

            $.ajax({
                url: '{{ route('user.password.update') }}',
                method: 'POST',
                data: $form.serialize(),
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function (response) {
                    if (response.success) {
                        toastr.success(response.message);
                        $form[0].reset();
                        if (context === 'forced') {
                            var modalEl = document.getElementById('forcePasswordChangeModal');
                            if (modalEl) {
                                var modalInstance = bootstrap.Modal.getInstance(modalEl);
                                if (modalInstance) {
                                    modalInstance.hide();
                                }
                            }
                        }
                    }
                },
                error: function (xhr) {
                    if (xhr.status === 422) {
                        showErrors($form, xhr.responseJSON.errors);
                    } else {
                        toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to update password');
                    }
                },
                complete: function () {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                    icon.show();
                }
            });
        });
    })(jQuery);
</script>
