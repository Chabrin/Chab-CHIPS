<script>
    // Opens the read-message popup for a batch ('.../batches/{id}/message') or one recipient's message ('.../logs/{channel}/{id}/message')
    window.cmOpenMessage = function(url) {
        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('cm_msg_modal'));
        var statusLabels = { sent: 'Delivered', failed: 'Failed', skipped: 'Skipped', done: 'Delivered', sending: 'Sending', queued: 'Queued' };

        $('#cmm_content').hide();
        $('#cmm_loading').show();
        modal.show();

        function row(label, valueNode) {
            $('#cmm_meta').append($('<dt>').text(label)).append($('<dd>').append(valueNode));
        }
        function pill(status) { return $('<span>').addClass('cm-pill ' + status).text(statusLabels[status] || status); }

        $.get(url).done(function(r) {
            if (!r.success) { modal.hide(); return; }

            var isSms = r.channel === 'sms';
            $('#cmm_meta').empty();

            if (r.kind === 'batch') {
                $('#cmm_title').text(isSms ? 'SMS as written' : (r.subject || 'Email as written'));
                row('Channel', document.createTextNode(isSms ? 'SMS' : 'Email'));
                row('Audience', document.createTextNode(r.audience || ''));
                row('Sent by', document.createTextNode((r.sender || '—') + ' · ' + (r.when || '')));
                row('Delivery', document.createTextNode(Number(r.sent).toLocaleString() + ' of ' + Number(r.recipients).toLocaleString() + ' delivered' + (r.failed ? ', ' + r.failed + ' failed' : '') + (r.skipped ? ', ' + r.skipped + ' skipped' : '')));
                if (r.attachment) row('Attachment', document.createTextNode(r.attachment));
                $('#cmm_error').hide();
                $('#cmm_note').text('This is the message as it was written. Placeholders like {name} were filled in separately for each recipient.');
            } else {
                $('#cmm_title').text(isSms ? 'SMS to ' + r.recipient : (r.subject || 'Email to ' + r.recipient));
                row('To', document.createTextNode(r.recipient + (r.contact ? ' · ' + r.contact : '')));
                row('Status', pill(r.status));
                row('When', document.createTextNode(r.when || '—'));
                row('Type', document.createTextNode(r.type || 'general'));
                if (r.batch_id) row('Batch', $('<a>').attr('href', '{{ url("/communications/batches") }}/' + r.batch_id).text('#' + r.batch_id));
                if (isSms && r.units) row('SMS units', document.createTextNode(String(r.units)));
                if (isSms && r.message_id) row('Gateway ID', document.createTextNode(r.message_id));
                if (r.has_attachment) row('Attachment', document.createTextNode('Yes'));
                if (r.error) { $('#cmm_error').text(r.error).show(); } else { $('#cmm_error').hide(); }
                $('#cmm_note').text(r.status === 'skipped' ? 'Nothing was sent to this person.' : 'This is the message after the placeholders were filled in for this person.');
            }

            // textContent only, so nothing in a stored message can run in the browser
            $('#cmm_subject').text(isSms ? '' : (r.subject || '')).toggle(!isSms && !!r.subject);
            $('#cmm_body').text(r.body || '');
            $('#cmm_loading').hide();
            $('#cmm_content').show();
        }).fail(function(xhr) {
            modal.hide();
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not load the message.';
            if (window.toastr) toastr.error(msg); else alert(msg);
        });
    };

    $(document).on('click', '#cmm_copy', function() {
        var text = ($('#cmm_subject').is(':visible') ? $('#cmm_subject').text() + '\n\n' : '') + $('#cmm_body').text();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() { if (window.toastr) toastr.success('Copied.'); });
        }
    });
</script>
