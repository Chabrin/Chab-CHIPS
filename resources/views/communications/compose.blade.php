@extends('layouts.main')

@section('styles')
@include('communications._styles')
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid cm-page">
        @include('communications._header', ['active' => 'compose', 'title' => 'New message', 'subtitle' => 'Email or SMS to one client, a property, a zone or everyone. You review the audience and message before anything is sent.'])

        <div class="row">
            <div class="col-xl-7">
                {{-- 1. Channel --}}
                <div class="cm-card">
                    <h3 class="cm-card-title"><span class="cm-step">1</span>How do you want to send it?</h3>
                    <div class="cm-seg" id="channel_seg">
                        <button type="button" class="active" data-ch="email"><span class="material-symbols-outlined">mail</span>Email</button>
                        <button type="button" data-ch="sms"><span class="material-symbols-outlined">sms</span>SMS</button>
                    </div>
                </div>

                {{-- 2. Audience --}}
                <div class="cm-card">
                    <h3 class="cm-card-title"><span class="cm-step">2</span>Who should get it?</h3>
                    <div class="cm-aud-grid" id="aud_grid">
                        <button type="button" class="cm-aud active" data-aud="single_client"><span class="cm-aud-ico"><span class="material-symbols-outlined">person</span></span><span><div class="cm-aud-t">One client</div><div class="cm-aud-s">Tenant or landlord</div></span></button>
                        <button type="button" class="cm-aud" data-aud="clients"><span class="cm-aud-ico"><span class="material-symbols-outlined">group_add</span></span><span><div class="cm-aud-t">Selected clients</div><div class="cm-aud-s">Pick several</div></span></button>
                        <button type="button" class="cm-aud" data-aud="property"><span class="cm-aud-ico"><span class="material-symbols-outlined">apartment</span></span><span><div class="cm-aud-t">A property</div><div class="cm-aud-s">Its current tenants</div></span></button>
                        <button type="button" class="cm-aud" data-aud="zone"><span class="cm-aud-ico"><span class="material-symbols-outlined">map</span></span><span><div class="cm-aud-t">A zone</div><div class="cm-aud-s">Tenants in the zone</div></span></button>
                        <button type="button" class="cm-aud" data-aud="tenants_all"><span class="cm-aud-ico"><span class="material-symbols-outlined">groups</span></span><span><div class="cm-aud-t">All tenants</div><div class="cm-aud-s">Everyone on a lease</div></span></button>
                        <button type="button" class="cm-aud" data-aud="landlords_all"><span class="cm-aud-ico"><span class="material-symbols-outlined">real_estate_agent</span></span><span><div class="cm-aud-t">All landlords</div><div class="cm-aud-s">Every landlord</div></span></button>
                        <button type="button" class="cm-aud" data-aud="staff_all"><span class="cm-aud-ico"><span class="material-symbols-outlined">badge</span></span><span><div class="cm-aud-t">All staff</div><div class="cm-aud-s">Active employees</div></span></button>
                    </div>

                    <div class="mt-3" id="picker_single_client">
                        <label class="cm-label">Client</label>
                        <select id="client_id" class="cm-select"></select>
                    </div>
                    <div class="mt-3" id="picker_clients" style="display:none;">
                        <label class="cm-label">Clients (search and pick several)</label>
                        <select id="client_ids" multiple></select>
                    </div>
                    <div class="mt-3" id="picker_property" style="display:none;">
                        <label class="cm-label">Property</label>
                        <select id="property_id" class="cm-select"></select>
                    </div>
                    <div class="mt-3" id="picker_zone" style="display:none;">
                        <label class="cm-label">Zone</label>
                        <select id="zone_id" class="cm-select">
                            <option value="">Choose a zone</option>
                            @foreach($zones as $z)
                            <option value="{{ $z->id }}">{{ $z->zone_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="cm-reach loading" id="reach_box">
                        <span class="material-symbols-outlined">groups</span>
                        <span id="reach_text">Pick who it goes to and we'll count them.</span>
                    </div>
                </div>

                {{-- 3. Message --}}
                <div class="cm-card">
                    <h3 class="cm-card-title"><span class="cm-step">3</span>What do you want to say?</h3>

                    <div class="mb-3" id="subject_wrap">
                        <label class="cm-label" for="subject">Subject</label>
                        <input type="text" class="cm-input" id="subject" maxlength="255" placeholder="e.g. Notice of planned water maintenance">
                    </div>

                    <label class="cm-label" for="body">Message</label>
                    <textarea class="cm-textarea" id="body" placeholder="Dear {first_name}, ..."></textarea>
                    <div class="cm-chips">
                        <span class="cm-hint">Personalise:</span>
                        <button type="button" class="cm-chip-btn" data-ph="{name}">Name</button>
                        <button type="button" class="cm-chip-btn" data-ph="{first_name}">First name</button>
                        <button type="button" class="cm-chip-btn" data-ph="{property}">Property</button>
                        <button type="button" class="cm-chip-btn" data-ph="{unit}">Unit</button>
                        <button type="button" class="cm-chip-btn" data-ph="{reference}">Reference</button>
                        <button type="button" class="cm-chip-btn" data-ph="{date}">Date</button>
                    </div>
                    <div class="cm-counter" id="sms_counter" style="display:none;">
                        <span id="char_info">0 characters</span>
                        <div class="cm-meter"><i id="char_meter"></i></div>
                        <span id="unit_info">1 SMS</span>
                    </div>

                    <div class="mt-3" id="attachment_wrap">
                        <label class="cm-label">Attachment <span style="text-transform:none;letter-spacing:0;font-weight:500;">(optional, up to 10 MB)</span></label>
                        <label class="cm-drop" id="drop_zone">
                            <input type="file" id="attachment">
                            <span id="drop_idle"><span class="material-symbols-outlined">upload_file</span>Drop a file here or click to browse</span>
                            <span id="drop_file" class="cm-file" style="display:none;"><span class="material-symbols-outlined" style="font-size:20px !important;display:inline;margin:0;">attach_file</span><span id="drop_name"></span><button type="button" id="drop_clear" title="Remove">&times;</button></span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Live preview + send --}}
            <div class="col-xl-5">
                <div class="cm-sticky">
                    <div class="cm-card">
                        <h3 class="cm-card-title"><span class="material-symbols-outlined" style="color:var(--cm-blue);">visibility</span>Live preview</h3>

                        <div id="prev_email">
                            <div class="cm-mail-mock">
                                <div class="cm-mail-bar">To: <b id="pe_to">—</b><br>Subject: <b id="pe_subject">—</b></div>
                                <div class="cm-mail-head">{{ config('app.company_name') }}</div>
                                <div class="cm-mail-body" id="pe_body"></div>
                                <div class="cm-mail-foot">&copy; {{ date('Y') }} {{ config('app.company_name') }}</div>
                            </div>
                        </div>

                        <div id="prev_sms" style="display:none;">
                            <div class="cm-phone">
                                <div class="cm-phone-top"></div>
                                <div class="cm-phone-screen">
                                    <div class="cm-phone-from" id="ps_from">{{ config('app.company_name') }}</div>
                                    <div class="cm-bubble" id="ps_body"></div>
                                </div>
                            </div>
                        </div>
                        <div class="cm-sample-note" id="sample_note"></div>
                    </div>

                    <button type="button" class="cm-btn cm-btn-primary cm-btn-lg w-100" id="review_btn" disabled>
                        <span class="material-symbols-outlined">send</span>Review &amp; send
                    </button>
                    <div class="cm-hint text-center mt-2" id="review_hint">Choose an audience and write a message to continue.</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Review modal --}}
<div class="modal fade cm-modal" id="review_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Review before sending</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2"><span class="cm-label" style="display:inline;">To</span> <strong id="rv_audience"></strong></div>
                <div class="cm-review-nums">
                    <div><b id="rv_sendable">0</b><span>will receive it</span></div>
                    <div><b id="rv_skipped">0</b><span>skipped (no contact)</span></div>
                    <div><b id="rv_units">—</b><span id="rv_units_l">SMS units</span></div>
                </div>
                <div class="cm-msg-card">
                    <div class="cm-msg-subject" id="rv_subject"></div>
                    <div class="cm-msg-body" id="rv_body"></div>
                </div>
                <div class="cm-hint mt-2" id="rv_first"></div>
                <div class="cm-warn-box" id="rv_confirm_box" style="display:none;">
                    This goes to <strong id="rv_big_n"></strong> people and cannot be recalled. Type <strong>SEND</strong> to confirm.
                    <input type="text" class="cm-input mt-2" id="rv_confirm" autocomplete="off" placeholder="SEND">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="cm-btn cm-btn-light" data-bs-dismiss="modal">Back to editing</button>
                <button type="button" class="cm-btn cm-btn-primary" id="send_btn"><span class="material-symbols-outlined">send</span><span id="send_label">Send now</span></button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        var urls = {
            estimate: '{{ route("ajax.communications.estimate") }}',
            send: '{{ route("ajax.communications.send") }}',
            clients: '{{ route("ajax.communications.client.search") }}',
            properties: '{{ route("ajax.communications.property.search") }}',
            batch: '{{ url("/communications/batches") }}'
        };
        var csrf = '{{ csrf_token() }}';
        var NO_PICKER = ['tenants_all', 'landlords_all', 'staff_all'];
        var FALLBACK = { name: 'Jane Wanjiru', property: 'Sample Court', unit: 'A12', reference: 'TEN-000123' };

        var state = { channel: 'email', aud: 'single_client', reach: null, sample: null, reqId: 0, timer: null };

        function toast(type, msg) {
            if (window.toastr) { toastr[type](msg); } else { alert(msg); }
        }
        function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

        /* ---------- pickers ---------- */
        $('#client_id').select2({
            width: '100%', placeholder: 'Search a client by name or reference…', allowClear: true, minimumInputLength: 2,
            ajax: { url: urls.clients, dataType: 'json', delay: 300, data: function(p) { return { q: p.term, kind: 'any' }; }, processResults: function(d) { return { results: d.results || [] }; } }
        });
        $('#client_ids').select2({
            width: '100%', placeholder: 'Search clients…', minimumInputLength: 2,
            ajax: { url: urls.clients, dataType: 'json', delay: 300, data: function(p) { return { q: p.term, kind: 'any' }; }, processResults: function(d) { return { results: d.results || [] }; } }
        });
        $('#property_id').select2({
            width: '100%', placeholder: 'Search a property by name or code…', allowClear: true, minimumInputLength: 2,
            ajax: { url: urls.properties, dataType: 'json', delay: 300, data: function(p) { return { q: p.term }; }, processResults: function(d) { return { results: d.results || [] }; } }
        });

        function pickerReady() {
            switch (state.aud) {
                case 'single_client': return !!$('#client_id').val();
                case 'clients': return ($('#client_ids').val() || []).length > 0;
                case 'property': return !!$('#property_id').val();
                case 'zone': return !!$('#zone_id').val();
                default: return true;
            }
        }

        /* ---------- audience reach ---------- */
        function reachMessage(text, cls) {
            $('#reach_box').attr('class', 'cm-reach ' + (cls || '')).find('#reach_text').html(text);
        }

        function payload(extra) {
            var d = {
                _token: csrf, channel: state.channel, audience_type: state.aud,
                subject: $('#subject').val(), body: $('#body').val(),
                client_id: $('#client_id').val(), property_id: $('#property_id').val(), zone_id: $('#zone_id').val()
            };
            ($('#client_ids').val() || []).forEach(function(v, i) { d['client_ids[' + i + ']'] = v; });
            return $.extend(d, extra || {});
        }

        function scheduleReach() {
            clearTimeout(state.timer);
            state.reach = null;
            state.sample = null;
            refreshPreview();
            refreshButton();

            if (!pickerReady()) {
                reachMessage('Pick who it goes to and we\'ll count them.', 'loading');
                return;
            }
            reachMessage('<span class="material-symbols-outlined cm-spin">progress_activity</span> Counting recipients…', 'loading');

            var id = ++state.reqId;
            state.timer = setTimeout(function() {
                $.post(urls.estimate, payload({ body: '' }))
                    .done(function(res) {
                        if (id !== state.reqId) return;
                        state.reach = res;
                        state.sample = res.sample || null;
                        var what = state.channel === 'sms' ? 'mobile number' : 'email address';
                        if (res.total === 0) {
                            reachMessage('Nobody matches that choice.', 'skip');
                        } else {
                            reachMessage('<span><b>' + res.total.toLocaleString() + '</b> in audience</span><span class="ok"><b>' + res.sendable.toLocaleString() + '</b> will receive it</span>' +
                                (res.skipped ? '<span class="skip"><b>' + res.skipped.toLocaleString() + '</b> have no ' + what + ' and will be skipped</span>' : ''), '');
                        }
                        refreshPreview();
                        refreshButton();
                    })
                    .fail(function(xhr) {
                        if (id !== state.reqId) return;
                        reachMessage(esc(errorText(xhr)), 'skip');
                    });
            }, 280);
        }

        function errorText(xhr) {
            var msg = 'Request failed.';
            if (xhr.responseJSON) {
                if (xhr.responseJSON.message) msg = xhr.responseJSON.message;
                if (xhr.responseJSON.errors) msg = Object.values(xhr.responseJSON.errors).map(function(e) { return e[0]; }).join(' ');
            }
            return msg;
        }

        /* ---------- live preview ---------- */
        function fill(text, s) {
            var name = s.name || '';
            var date = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            return text.replace(/\{name\}/g, name).replace(/\{first_name\}/g, name.split(' ')[0] || name)
                .replace(/\{property\}/g, s.property || '').replace(/\{unit\}/g, s.unit || '')
                .replace(/\{reference\}/g, s.reference || '').replace(/\{date\}/g, date);
        }

        function refreshPreview() {
            var s = state.sample || FALLBACK;
            var body = $('#body').val();
            var subject = $('#subject').val();
            var isSms = state.channel === 'sms';

            $('#prev_email').toggle(!isSms);
            $('#prev_sms').toggle(isSms);

            var bodyHtml = body.trim() ? esc(fill(body, s)) : '<span class="cm-placeholder-text">Your message will appear here as you type.</span>';
            $('#pe_body').html(bodyHtml);
            $('#ps_body').html(bodyHtml);
            $('#pe_subject').text(subject.trim() ? fill(subject, s) : '—');
            $('#pe_to').text(s.name + (state.sample ? '' : ' (sample)'));
            $('#sample_note').text(state.sample ? 'Showing how it looks for ' + s.name + ' — every recipient gets their own details.' : 'Sample details shown until an audience is chosen.');

            if (isSms) {
                var len = body.length;
                var units = len <= 160 ? 1 : Math.ceil(len / 153);
                var seg = len <= 160 ? 160 : 153;
                var inSeg = len === 0 ? 0 : (len % seg === 0 ? seg : len % seg);
                var pct = inSeg / seg * 100;
                $('#char_info').text(len + ' characters');
                $('#unit_info').text(units + ' SMS' + (units > 1 ? ' units' : ''));
                $('#char_meter').css('width', pct + '%').toggleClass('over', units > 1);
            }
        }

        function refreshButton() {
            var haveBody = $('#body').val().trim().length > 0;
            var haveSubject = state.channel === 'sms' || $('#subject').val().trim().length > 0;
            var haveAudience = state.reach && state.reach.sendable > 0;
            $('#review_btn').prop('disabled', !(haveBody && haveSubject && haveAudience));

            var hint = 'Ready — you will see a final review first.';
            if (!haveAudience) hint = 'Choose an audience that has recipients.';
            else if (!haveSubject) hint = 'Add a subject.';
            else if (!haveBody) hint = 'Write your message.';
            $('#review_hint').text(hint);
        }

        /* ---------- wiring ---------- */
        function applyChannel() {
            var isSms = state.channel === 'sms';
            $('#subject_wrap, #attachment_wrap').toggle(!isSms);
            $('#sms_counter').toggle(isSms);
            $('#channel_seg button').removeClass('active').filter('[data-ch="' + state.channel + '"]').addClass('active');
            scheduleReach();
        }

        function applyAudience() {
            $('#aud_grid .cm-aud').removeClass('active').filter('[data-aud="' + state.aud + '"]').addClass('active');
            ['single_client', 'clients', 'property', 'zone'].forEach(function(t) { $('#picker_' + t).toggle(state.aud === t); });
            scheduleReach();
        }

        $('#channel_seg button').on('click', function() { state.channel = $(this).data('ch'); applyChannel(); });
        $('#aud_grid .cm-aud').on('click', function() { state.aud = $(this).data('aud'); applyAudience(); });
        $('#client_id, #client_ids, #property_id, #zone_id').on('change', scheduleReach);
        $('#body, #subject').on('input', function() { refreshPreview(); refreshButton(); });

        $('.cm-chip-btn').on('click', function() {
            var el = document.getElementById('body');
            var start = el.selectionStart == null ? el.value.length : el.selectionStart;
            var end = el.selectionEnd == null ? start : el.selectionEnd;
            el.value = el.value.slice(0, start) + $(this).data('ph') + el.value.slice(end);
            el.selectionStart = el.selectionEnd = start + $(this).data('ph').length;
            $(el).trigger('input').focus();
        });

        /* attachment drop zone */
        var $drop = $('#drop_zone');
        function showFile() {
            var f = document.getElementById('attachment').files[0];
            $('#drop_idle').toggle(!f);
            $('#drop_file').toggle(!!f);
            if (f) $('#drop_name').text(f.name + ' (' + (f.size / 1024 / 1024).toFixed(1) + ' MB)');
        }
        $('#attachment').on('change', showFile);
        $('#drop_clear').on('click', function(e) { e.preventDefault(); e.stopPropagation(); $('#attachment').val(''); showFile(); });
        $drop.on('dragover dragenter', function(e) { e.preventDefault(); $drop.addClass('drag'); });
        $drop.on('dragleave drop', function(e) { e.preventDefault(); $drop.removeClass('drag'); });
        $drop.on('drop', function(e) {
            var files = e.originalEvent.dataTransfer.files;
            if (files.length) { document.getElementById('attachment').files = files; showFile(); }
        });

        /* ---------- review + send ---------- */
        var reviewModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('review_modal'));
        var reviewed = null;

        $('#review_btn').on('click', function() {
            var $btn = $(this).prop('disabled', true);
            $.post(urls.estimate, payload())
                .done(function(res) {
                    reviewed = res;
                    var isSms = state.channel === 'sms';
                    $('#rv_audience').text(res.audience_label);
                    $('#rv_sendable').text(res.sendable.toLocaleString());
                    $('#rv_skipped').text(res.skipped.toLocaleString());
                    $('#rv_units').text(isSms ? res.sms_units.toLocaleString() : '—');
                    $('#rv_units_l').text(isSms ? 'SMS units (cost)' : 'emails');
                    if (!isSms) $('#rv_units').text(res.sendable.toLocaleString());
                    $('#rv_subject').text(isSms ? '' : (res.preview ? res.preview.subject : '')).toggle(!isSms);
                    $('#rv_body').text(res.preview ? res.preview.body : '');
                    $('#rv_first').text(res.preview ? 'Preview shown for ' + res.preview.name + '.' : '');

                    var big = res.sendable > 500;
                    $('#rv_confirm_box').toggle(big);
                    $('#rv_big_n').text(res.sendable.toLocaleString());
                    $('#rv_confirm').val('');
                    $('#send_btn').prop('disabled', big);
                    $('#send_label').text('Send to ' + res.sendable.toLocaleString());
                    reviewModal.show();
                })
                .fail(function(xhr) { toast('error', errorText(xhr)); })
                .always(function() { $btn.prop('disabled', false); refreshButton(); });
        });

        $('#rv_confirm').on('input', function() { $('#send_btn').prop('disabled', $(this).val().trim().toUpperCase() !== 'SEND'); });

        $('#send_btn').on('click', function() {
            var $btn = $(this).prop('disabled', true);
            var fd = new FormData();
            var d = payload();
            Object.keys(d).forEach(function(k) { if (d[k] !== null && d[k] !== undefined) fd.append(k, d[k]); });
            var file = document.getElementById('attachment').files[0];
            if (file && state.channel === 'email') fd.append('attachment', file);

            $.ajax({ url: urls.send, type: 'POST', data: fd, processData: false, contentType: false })
                .done(function(res) {
                    toast('success', res.message || 'Queued.');
                    window.location = urls.batch + '/' + res.batch_id;
                })
                .fail(function(xhr) { toast('error', errorText(xhr)); $btn.prop('disabled', false); });
        });

        applyChannel();
        applyAudience();
        refreshPreview();
    });
</script>
@endsection
