@php
    $context = $context ?? 'profile';
@endphp

@once
<style>
    #passwordChangeForm .password-input-wrap { position: relative; }
    #passwordChangeForm .password-input-wrap > .form-control {
        min-height: 46px;
        padding: 11px 54px 11px 14px;
        border: 1px solid #dbe2ea;
        border-radius: 12px;
        background-color: #fff;
        color: #0f172a;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    #passwordChangeForm .password-input-wrap > .form-control:focus {
        border-color: #647f9e;
        box-shadow: 0 0 0 3px rgba(15, 39, 68, .08);
    }
    #passwordChangeForm .password-input-wrap > .form-control.is-invalid {
        border-color: #dc3545;
        background-image: none;
    }
    #passwordChangeForm .password-visibility-toggle {
        position: absolute;
        top: 6px;
        right: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        padding: 0;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: #64748b;
        cursor: pointer;
    }
    #passwordChangeForm .password-visibility-toggle:hover {
        background: #f1f5f9;
        color: #0f2744;
    }
    #passwordChangeForm .password-visibility-toggle:focus-visible {
        outline: 2px solid #647f9e;
        outline-offset: 1px;
    }
    #passwordChangeForm .password-visibility-toggle svg { width: 20px; height: 20px; }
</style>
@endonce

<form id="passwordChangeForm" autocomplete="off">
    @csrf
    <div class="mb-3">
        <label for="current_password_{{ $context }}" class="form-label">Current Password <span class="text-danger">*</span></label>
        <div class="password-input-wrap">
            <input type="password" class="form-control" id="current_password_{{ $context }}" name="current_password" required autocomplete="current-password">
            <button type="button" class="password-visibility-toggle" data-password-toggle data-password-label="current password" aria-controls="current_password_{{ $context }}" aria-label="Show current password" aria-pressed="false" title="Show current password">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                    <circle cx="12" cy="12" r="3" />
                    <path data-password-slash class="d-none" d="m3 3 18 18" />
                </svg>
            </button>
            <div class="invalid-feedback"></div>
        </div>
    </div>
    <div class="mb-3">
        <label for="new_password_{{ $context }}" class="form-label">New Password <span class="text-danger">*</span></label>
        <div class="password-input-wrap">
            <input type="password" class="form-control" id="new_password_{{ $context }}" name="new_password" required minlength="8" autocomplete="new-password">
            <button type="button" class="password-visibility-toggle" data-password-toggle data-password-label="new password" aria-controls="new_password_{{ $context }}" aria-label="Show new password" aria-pressed="false" title="Show new password">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                    <circle cx="12" cy="12" r="3" />
                    <path data-password-slash class="d-none" d="m3 3 18 18" />
                </svg>
            </button>
            <div class="invalid-feedback"></div>
        </div>
    </div>
    <div class="mb-3">
        <label for="new_password_confirmation_{{ $context }}" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
        <div class="password-input-wrap">
            <input type="password" class="form-control" id="new_password_confirmation_{{ $context }}" name="new_password_confirmation" required minlength="8" autocomplete="new-password">
            <button type="button" class="password-visibility-toggle" data-password-toggle data-password-label="password confirmation" aria-controls="new_password_confirmation_{{ $context }}" aria-label="Show password confirmation" aria-pressed="false" title="Show password confirmation">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                    <circle cx="12" cy="12" r="3" />
                    <path data-password-slash class="d-none" d="m3 3 18 18" />
                </svg>
            </button>
            <div class="invalid-feedback"></div>
        </div>
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

        function setPasswordVisibility(button, visible) {
            var $button = $(button);
            $button.siblings('input').attr('type', visible ? 'text' : 'password');
            var label = (visible ? 'Hide ' : 'Show ') + $button.attr('data-password-label');
            $button.attr('aria-pressed', visible ? 'true' : 'false')
                .attr('aria-label', label).attr('title', label);
            $button.find('[data-password-slash]').toggleClass('d-none', !visible);
        }

        $form.off('click.passwordVisibility', '[data-password-toggle]')
            .on('click.passwordVisibility', '[data-password-toggle]', function () {
                setPasswordVisibility(this, $(this).siblings('input').attr('type') === 'password');
            });

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
                        $form.find('[data-password-toggle]').each(function () {
                            setPasswordVisibility(this, false);
                        });
                        if (@json($reloadAfterPasswordChange ?? false)) {
                            window.location.href = @json(route('user.profile', ['tab' => 'security']));
                            return;
                        }
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
