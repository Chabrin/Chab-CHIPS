(function ($) {
    'use strict';
    window.returnStatementHasUnsavedRemarks = function () {
        return $('.statement-remarks-form').toArray().some(function (form) {
            var $form = $(form);
            return $form.find('.statement-save').prop('disabled') ||
                $form.find('[name="remarks"]').val() !== $form.find('[name="expected_remarks"]').val();
        });
    };
    window.addEventListener('beforeunload', function (event) {
        if (window.returnStatementHasUnsavedRemarks()) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    window.renderReturnStatement = function (response) {
        $('#return-statement').html(response.statement_html || '');
        $('#return-transaction-details').prop('open', !response.statement_html);
    };

    $(document).on('input', '.statement-remarks', function () {
        $(this).closest('form').find('.statement-save-status').text('Unsaved changes').removeClass('text-success text-danger').addClass('text-muted');
    });
    $(document).on('submit', '.statement-remarks-form', function (event) {
        event.preventDefault();
        var $form = $(this);
        var $button = $form.find('.statement-save');
        var $field = $form.find('[name="remarks"]');
        var $status = $form.find('.statement-save-status');
        $button.prop('disabled', true).text('Saving...');
        $field.prop('readonly', true);
        $status.text('').removeClass('text-success text-danger text-muted');
        $.ajax({
            url: $form.attr('action'), type: 'POST', data: $form.serialize(),
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
            success: function (response) {
                $field.val(response.remarks);
                $form.find('[name="expected_remarks"]').val(response.remarks);
                $status.addClass('text-success').text('Saved. Included in PDF and Excel.');
            },
            error: function (xhr) {
                var data = xhr.responseJSON || {};
                var errors = data.errors || {};
                $status.addClass('text-danger').text((errors.remarks || [])[0] || data.message || 'Could not save remarks. Please try again.');
            },
            complete: function () {
                $button.prop('disabled', false).text('Save');
                $field.prop('readonly', false);
            }
        });
    });
})(jQuery);
