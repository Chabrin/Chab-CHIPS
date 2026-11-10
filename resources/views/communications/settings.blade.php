@extends('layouts.main')

@section('styles')
@include('communications._styles')
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid cm-page">
        @include('communications._header', ['active' => 'settings', 'title' => 'Mail and SMS settings', 'subtitle' => 'The accounts the system sends email and SMS through. Passwords and keys are stored encrypted and are never shown again.'])

        <div class="row">
            {{-- Mail --}}
            <div class="col-xl-6">
                <div class="cm-card">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <h3 class="cm-card-title mb-0"><span class="material-symbols-outlined" style="color:var(--cm-blue);">mail</span>Email (SMTP)</h3>
                        <span class="cm-pill queued" id="mail_status">Loading…</span>
                    </div>
                    <p class="cm-hint mb-3" id="mail_defaults"></p>

                    <form id="mail_form" autocomplete="off" novalidate>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="cm-label" for="m_host">SMTP server</label>
                                <input type="text" class="cm-input" id="m_host" autocomplete="off" placeholder="smtp.example.com">
                            </div>
                            <div class="col-md-4">
                                <label class="cm-label" for="m_port">Port</label>
                                <input type="number" class="cm-input" id="m_port" min="1" max="65535" placeholder="587">
                            </div>
                            <div class="col-md-4">
                                <label class="cm-label" for="m_encryption">Security</label>
                                <select class="cm-select" id="m_encryption">
                                    <option value="tls">TLS (port 587)</option>
                                    <option value="ssl">SSL (port 465)</option>
                                    <option value="none">None</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="cm-label" for="m_username">Username</label>
                                <input type="text" class="cm-input" id="m_username" autocomplete="off">
                            </div>
                            <div class="col-12">
                                <label class="cm-label" for="m_password">Password</label>
                                <input type="password" class="cm-input" id="m_password" autocomplete="new-password" placeholder="">
                                <div class="cm-hint mt-1" id="m_password_hint"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="cm-label" for="m_from_address">From address</label>
                                <input type="email" class="cm-input" id="m_from_address" autocomplete="off" placeholder="noreply@yourdomain.com">
                            </div>
                            <div class="col-md-6">
                                <label class="cm-label" for="m_from_name">From name</label>
                                <input type="text" class="cm-input" id="m_from_name" autocomplete="off" placeholder="{{ config('app.company_name') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="cm-label" for="m_reply_to">Reply-to address <span style="text-transform:none;letter-spacing:0;font-weight:500;">(optional)</span></label>
                                <input type="email" class="cm-input" id="m_reply_to" autocomplete="off" placeholder="accounts@yourdomain.com">
                            </div>
                            <div class="col-md-6">
                                <label class="cm-label" for="m_reply_to_name">Reply-to name <span style="text-transform:none;letter-spacing:0;font-weight:500;">(optional)</span></label>
                                <input type="text" class="cm-input" id="m_reply_to_name" autocomplete="off" placeholder="Same as From name">
                            </div>
                        </div>

                        <div class="d-flex gap-2 flex-wrap mt-3">
                            <button type="submit" class="cm-btn cm-btn-primary" id="mail_save"><span class="material-symbols-outlined">save</span>Save mail settings</button>
                            <button type="button" class="cm-btn cm-btn-light" id="mail_reset" style="display:none;">Use server defaults</button>
                        </div>
                    </form>

                    <hr style="border-color:var(--cm-border);margin:20px 0;">
                    <label class="cm-label" for="mail_test_to">Send a test email</label>
                    <div class="d-flex gap-2">
                        <input type="email" class="cm-input" id="mail_test_to" autocomplete="off" placeholder="you@yourdomain.com">
                        <button type="button" class="cm-btn cm-btn-light" id="mail_test" style="white-space:nowrap;"><span class="material-symbols-outlined" id="mail_test_icon">outgoing_mail</span>Send test</button>
                    </div>
                    <div class="cm-hint mt-2">This sends one real email through the settings above once they are saved. Save first, then test.</div>
                </div>
            </div>

            {{-- SMS --}}
            <div class="col-xl-6">
                <div class="cm-card">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <h3 class="cm-card-title mb-0"><span class="material-symbols-outlined" style="color:#6d28d9;">sms</span>SMS gateway</h3>
                        <span class="cm-pill queued" id="sms_status">Loading…</span>
                    </div>
                    <p class="cm-hint mb-3" id="sms_defaults"></p>

                    <form id="sms_form" autocomplete="off" novalidate>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="cm-label" for="s_provider">Provider</label>
                                <select class="cm-select" id="s_provider">
                                    <option value="none">Not set up</option>
                                    <option value="africastalking">Africa's Talking</option>
                                </select>
                            </div>
                            <div class="col-12 sms-fields">
                                <label class="cm-label" for="s_username">Username</label>
                                <input type="text" class="cm-input" id="s_username" autocomplete="off">
                            </div>
                            <div class="col-12 sms-fields">
                                <label class="cm-label" for="s_api_key">API key</label>
                                <input type="password" class="cm-input" id="s_api_key" autocomplete="new-password">
                                <div class="cm-hint mt-1" id="s_key_hint"></div>
                            </div>
                            <div class="col-12 sms-fields">
                                <label class="cm-label" for="s_sender_id">Sender ID <span style="text-transform:none;letter-spacing:0;font-weight:500;">(optional, up to 20 characters)</span></label>
                                <input type="text" class="cm-input" id="s_sender_id" maxlength="20" autocomplete="off">
                            </div>
                        </div>

                        <div class="d-flex gap-2 flex-wrap mt-3">
                            <button type="submit" class="cm-btn cm-btn-primary" id="sms_save"><span class="material-symbols-outlined">save</span>Save SMS settings</button>
                            <button type="button" class="cm-btn cm-btn-light" id="sms_reset" style="display:none;">Use server defaults</button>
                        </div>
                    </form>

                    <hr style="border-color:var(--cm-border);margin:20px 0;">
                    <label class="cm-label" for="sms_test_to">Send a test SMS</label>
                    <div class="d-flex gap-2">
                        <input type="text" class="cm-input" id="sms_test_to" autocomplete="off" placeholder="0722 000 000">
                        <button type="button" class="cm-btn cm-btn-light" id="sms_test" style="white-space:nowrap;"><span class="material-symbols-outlined" id="sms_test_icon">send</span>Send test</button>
                    </div>
                    <div class="cm-hint mt-2">This sends one real SMS and uses one SMS unit.</div>
                </div>

                <div class="cm-card" style="background:#f9fbfd;">
                    <h3 class="cm-card-title"><span class="material-symbols-outlined" style="color:var(--cm-blue);">lock</span>How these are protected</h3>
                    <ul class="cm-hint mb-0" style="padding-left:18px;line-height:1.8;list-style:disc outside;">
                        <li>Only roles given the Settings page can open this screen or change anything on it.</li>
                        <li>The password and API key are encrypted in the database and are never sent back to your browser, so nobody can read them here. Leave a field blank to keep what is saved.</li>
                        <li>Who saved a change is recorded in the system log. Test sends are limited to five a minute.</li>
                        <li>"Use server defaults" removes the saved settings and sends through the server's own configuration again.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        var urls = {
            data: '{{ route("ajax.communications.settings") }}',
            mail: '{{ route("ajax.communications.settings.mail") }}',
            sms: '{{ route("ajax.communications.settings.sms") }}',
            mailTest: '{{ route("ajax.communications.settings.mail.test") }}',
            smsTest: '{{ route("ajax.communications.settings.sms.test") }}'
        };
        var csrf = '{{ csrf_token() }}';

        function toast(type, msg) { if (window.toastr) toastr[type](msg); else alert(msg); }
        function errText(xhr) {
            var msg = 'Request failed.';
            if (xhr.responseJSON) {
                if (xhr.responseJSON.message) msg = xhr.responseJSON.message;
                if (xhr.responseJSON.errors) msg = Object.values(xhr.responseJSON.errors).map(function(e) { return e[0]; }).join(' ');
            }
            if (xhr.status === 429) msg = 'Too many tests. Wait a minute and try again.';
            return msg;
        }
        function busy($btn, on, iconId, idleIcon) {
            $btn.prop('disabled', on);
            if (iconId) $('#' + iconId).text(on ? 'progress_activity' : idleIcon).toggleClass('cm-spin', on);
        }

        function paintStatus($pill, saved, label) {
            $pill.attr('class', 'cm-pill ' + (saved ? 'done' : 'queued')).text(saved ? 'Using saved settings' : 'Using server defaults');
        }

        function smsFieldsVisible() { $('.sms-fields').toggle($('#s_provider').val() !== 'none'); }

        function load() {
            $.get(urls.data).done(function(res) {
                if (!res.success) return;
                var m = res.mail, s = res.sms, d = res.defaults;

                paintStatus($('#mail_status'), m.saved);
                $('#mail_defaults').text(m.saved ? 'These saved settings are used for every email sent from the Communications module.' : 'Nothing saved yet, so email goes through the server\'s own settings (' + (d.mailer || 'none') + (d.mail_host ? ' via ' + d.mail_host : '') + (d.mail_from ? ', from ' + d.mail_from : ', no from-address set') + ').');
                $('#m_host').val(m.host || ''); $('#m_port').val(m.port || '587'); $('#m_encryption').val(m.encryption || 'tls');
                $('#m_username').val(m.username || ''); $('#m_password').val('');
                $('#m_password_hint').text(m.has_secret ? 'A password is saved. Leave this blank to keep it, or type a new one to replace it.' : 'No password saved.');
                $('#m_from_address').val(m.from_address || ''); $('#m_from_name').val(m.from_name || ''); $('#m_reply_to').val(m.reply_to || ''); $('#m_reply_to_name').val(m.reply_to_name || '');
                $('#mail_reset').toggle(!!m.saved);
                if (!$('#mail_test_to').val()) $('#mail_test_to').val(res.user_email || '');

                paintStatus($('#sms_status'), s.saved && s.provider && s.provider !== 'none');
                $('#sms_defaults').text(s.saved && s.provider && s.provider !== 'none' ? 'These saved settings are used for every SMS sent from the Communications module.' : 'No gateway saved here, so SMS uses the server\'s own settings (' + (d.sms_provider && d.sms_provider !== 'none' ? d.sms_provider : 'not set up, so SMS cannot be sent yet') + ').');
                $('#s_provider').val(s.provider || 'none'); $('#s_username').val(s.username || ''); $('#s_api_key').val(''); $('#s_sender_id').val(s.sender_id || '');
                $('#s_key_hint').text(s.has_secret ? 'An API key is saved. Leave this blank to keep it, or type a new one to replace it.' : 'No API key saved.');
                $('#sms_reset').toggle(!!s.saved);
                smsFieldsVisible();
            }).fail(function(xhr) { toast('error', errText(xhr)); });
        }

        $('#s_provider').on('change', smsFieldsVisible);
        $('#m_encryption').on('change', function() {
            var port = $('#m_port');
            if ($(this).val() === 'ssl' && (port.val() === '587' || port.val() === '')) port.val('465');
            if ($(this).val() === 'tls' && port.val() === '465') port.val('587');
        });

        $('#mail_form').on('submit', function(e) {
            e.preventDefault();
            var $b = $('#mail_save'); busy($b, true);
            $.post(urls.mail, { _token: csrf, host: $('#m_host').val(), port: $('#m_port').val(), encryption: $('#m_encryption').val(), username: $('#m_username').val(), password: $('#m_password').val(), from_address: $('#m_from_address').val(), from_name: $('#m_from_name').val(), reply_to: $('#m_reply_to').val(), reply_to_name: $('#m_reply_to_name').val() })
                .done(function(res) { toast('success', res.message); load(); })
                .fail(function(xhr) { toast('error', errText(xhr)); })
                .always(function() { busy($b, false); });
        });

        $('#sms_form').on('submit', function(e) {
            e.preventDefault();
            var $b = $('#sms_save'); busy($b, true);
            $.post(urls.sms, { _token: csrf, provider: $('#s_provider').val(), username: $('#s_username').val(), api_key: $('#s_api_key').val(), sender_id: $('#s_sender_id').val() })
                .done(function(res) { toast('success', res.message); load(); })
                .fail(function(xhr) { toast('error', errText(xhr)); })
                .always(function() { busy($b, false); });
        });

        $('#mail_reset').on('click', function() {
            if (!confirm('Remove the saved mail settings and send through the server defaults again?')) return;
            $.post(urls.mail, { _token: csrf, use_env: 1 }).done(function(res) { toast('success', res.message); load(); }).fail(function(xhr) { toast('error', errText(xhr)); });
        });
        $('#sms_reset').on('click', function() {
            if (!confirm('Remove the saved SMS settings and use the server defaults again?')) return;
            $.post(urls.sms, { _token: csrf, use_env: 1 }).done(function(res) { toast('success', res.message); load(); }).fail(function(xhr) { toast('error', errText(xhr)); });
        });

        $('#mail_test').on('click', function() {
            var $b = $(this); busy($b, true, 'mail_test_icon', 'outgoing_mail');
            $.post(urls.mailTest, { _token: csrf, to: $('#mail_test_to').val() })
                .done(function(res) { toast('success', res.message); })
                .fail(function(xhr) { toast('error', errText(xhr)); })
                .always(function() { busy($b, false, 'mail_test_icon', 'outgoing_mail'); });
        });

        $('#sms_test').on('click', function() {
            var $b = $(this); busy($b, true, 'sms_test_icon', 'send');
            $.post(urls.smsTest, { _token: csrf, to: $('#sms_test_to').val() })
                .done(function(res) { toast('success', res.message); })
                .fail(function(xhr) { toast('error', errText(xhr)); })
                .always(function() { busy($b, false, 'sms_test_icon', 'send'); });
        });

        load();
    });
</script>
@endsection
