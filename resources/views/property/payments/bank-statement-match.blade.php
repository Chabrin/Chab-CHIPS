@extends('layouts.main')
@php
    // Where "Back to the statement" goes: the Bank Statements page with this line's statement open
    $backUrl = url('/tenant/bank-statements') . '?open=' . $page['upload_id'] . '#' . $page['bank'];
@endphp

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/bank-statements.css') }}?v={{ filemtime(public_path('assets/css/bank-statements.css')) }}">
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid bs-page">

        <div class="bs-hero bs-hero-plain">
            <div class="bs-hero-top">
                <div>
                    <h1>Match a line by hand</h1>
                    <p>{{ $page['bank_label'] }} &middot; {{ $page['file_name'] }} &middot; row {{ $page['row_no'] }}. Choose the client, the property and then the tenant this money belongs to, then confirm.</p>
                </div>
                <div class="bs-hero-actions">
                    <a class="bs-btn bs-btn-ghost" href="{{ $backUrl }}"><span class="material-symbols-outlined">arrow_back</span>Back to the statement</a>
                </div>
            </div>
        </div>

        <div class="bs-card">
            <div class="bs-card-head">
                <h5 class="bs-card-title"><span class="material-symbols-outlined">receipt_long</span>The bank line</h5>
                <span class="bs-pill {{ $page['status'] }}" id="line-status">{{ $page['status'] }}</span>
            </div>
            <div class="bs-linegrid">
                <div><div class="bs-k">Amount</div><div class="bs-amount">{{ $page['amount'] }}</div></div>
                <div><div class="bs-k">Date</div><div class="bs-v">{{ $page['date'] ?: '-' }}</div></div>
                <div><div class="bs-k">Reference</div><div class="bs-v">{{ $page['reference'] ?: '-' }}</div></div>
                <div><div class="bs-k">Payer name in the file</div><div class="bs-v">{{ $page['payer_name'] ?: '-' }}</div></div>
                <div class="bs-wide"><div class="bs-k">Details in the file</div><div class="bs-v">{{ $page['description'] ?: '-' }}</div></div>
                @if($page['note'])
                <div class="bs-wide" id="line-note"><div class="bs-k">Why it did not match</div><div class="bs-warnnote">{{ $page['note'] }}</div></div>
                @endif
            </div>
        </div>

        @if($page['refusal'])
        <div class="bs-card">
            <div class="bs-empty">
                <span class="material-symbols-outlined">lock</span>
                <h4>This line cannot be matched by you</h4>
                {{ $page['refusal'] }}
                <div class="mt-3"><a class="bs-btn bs-btn-light" href="{{ $backUrl }}"><span class="material-symbols-outlined">arrow_back</span>Back to the statement</a></div>
            </div>
        </div>
        @else
        <div id="match-flow">
            <div class="bs-card bs-form">
                <div class="bs-card-head"><h5 class="bs-card-title"><span class="material-symbols-outlined">person_search</span>Who does this money belong to?</h5></div>

                <div class="bs-formgrid">
                    <div class="bs-field">
                        <div class="bs-fl-row"><label class="bs-fl" for="sel-client"><span class="bs-stepn">1</span>Client (landlord)</label></div>
                        <select id="sel-client"><option></option></select>
                    </div>
                    <div class="bs-field">
                        <div class="bs-fl-row"><label class="bs-fl" for="sel-property"><span class="bs-stepn">2</span>Property</label></div>
                        <select id="sel-property" disabled><option></option></select>
                    </div>
                    <div class="bs-field">
                        <div class="bs-fl-row">
                            <label class="bs-fl" for="sel-lease"><span class="bs-stepn">3</span>Unit and tenant</label>
                            <label class="bs-check" title="Also list leases that have ended, for a tenant who has left but still owes arrears"><input type="checkbox" id="ended-toggle"> Include ended leases</label>
                        </div>
                        <select id="sel-lease" disabled><option></option></select>
                    </div>
                </div>

                @if(!empty($page['mobile']))
                <div class="bs-info" id="mobile-hint">
                    @if(count($page['candidates']))
                    <b>Probable tenant by mobile number {{ $page['mobile'] }}</b> (the number in the bank's details). Check it is the right person, then use it to fill the three steps:
                    <div id="mobile-candidates"></div>
                    @else
                    The bank's details hold the mobile number <b>{{ $page['mobile'] }}</b>, but no single tenant in the system has it (or several share it), so choose the tenant yourself.
                    @endif
                </div>
                @endif

                <div id="suggest-note" class="bs-info d-none">
                    The file named unit <b>{{ $page['unit_code'] }}</b>, so the client and property are already chosen and the unit list shows that unit's leases, ended ones included in case the tenant has left. Type in the unit box to search the whole property, or <a href="#" id="suggest-clear">show the whole property</a>.
                </div>

                <div class="bs-confirmbar">
                    <div class="bs-confirmtext">
                        <div id="confirm-text" class="bs-hint">Choose the client, the property and then the unit and tenant.</div>
                        <div id="confirm-warn" class="bs-warnnote d-none"></div>
                    </div>
                    <button type="button" class="bs-btn bs-btn-ok bs-btn-lg" id="confirm-btn" disabled><span class="material-symbols-outlined">check_circle</span>Confirm match and pay</button>
                </div>
                <p class="bs-hint mb-0 mt-2">Confirming matches the line and pays it in at once: it is applied to the tenant's unpaid invoices (arrears first, then bills, then rent) and a receipt is emailed.</p>
            </div>
        </div>

        <div class="bs-card d-none" id="done-card"></div>
        @endif

    </div>
</div>
@endsection

@section('scripts')
@if(!$page['refusal'])
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function() {
    var page = @json($page, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    var csrf = '{{ csrf_token() }}';
    var urls = {
        list: {
            client: '{{ route('ajax.tenant.bank-statements.match.clients') }}',
            property: '{{ route('ajax.tenant.bank-statements.match.properties') }}',
            lease: '{{ route('ajax.tenant.bank-statements.match.leases') }}'
        },
        store: '{{ route('ajax.tenant.bank-statements.match.store', ['bank' => $page['bank'], 'line' => $page['id']]) }}',
        matchBase: '{{ url('/tenant/bank-statements') }}',
        back: @json($backUrl)
    };
    var sel = { client: null, property: null, lease: null };
    var suggestUnit = null;
    var busy = false;
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var $client = $('#sel-client');
    var $property = $('#sel-property');
    var $lease = $('#sel-lease');

    function esc(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

    function errorText(xhr, fallback) {
        return (xhr.responseJSON && xhr.responseJSON.message) || fallback;
    }

    // 2026-01-10 -> 10 Jan 2026
    function day(value) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value || '');
        return m ? parseInt(m[3], 10) + ' ' + months[parseInt(m[2], 10) - 1] + ' ' + m[1] : '';
    }

    function leaseText(r) {
        return r.unit_code + ' · ' + r.tenant;
    }

    // One drop-down option: a bold line and a muted line, built as elements so nothing from the data is read as HTML
    function option(main, sub, ended) {
        var box = $('<div>');
        $('<div class="bs-opt-main">').text(main).appendTo(box);
        var line = $('<div class="bs-opt-sub">').text(sub).appendTo(box);
        if (ended) { $('<span class="bs-pill Skipped">').text(ended).appendTo(line.append(' ')); }
        return box;
    }

    var kinds = {
        client: {
            url: urls.list.client, placeholder: 'Search clients by name or reference',
            text: function(r) { return r.names; },
            template: function(r) { return option(r.names, r.reference + ' · ' + r.properties + ' ' + (r.properties === 1 ? 'property' : 'properties')); },
            params: function() { return {}; }
        },
        property: {
            url: urls.list.property, placeholder: 'Search properties by name or reference',
            text: function(r) { return r.name; },
            template: function(r) { return option(r.name, r.reference + ' · ' + r.units + ' ' + (r.units === 1 ? 'unit' : 'units')); },
            params: function() { return { client_id: sel.client ? sel.client.id : 0 }; }
        },
        lease: {
            url: urls.list.lease, placeholder: 'Search by unit, tenant name or reference',
            text: leaseText,
            template: function(r) {
                var span = r.start ? day(r.start) + (r.end ? ' to ' + day(r.end) : ' onwards') : '';
                return option(leaseText(r), r.reference + (span ? ' · ' + span : ''), r.status === 'Active' ? '' : r.status);
            },
            params: function() { return { property_id: sel.property ? sel.property.id : 0, include_ended: $('#ended-toggle').is(':checked') ? 1 : 0 }; }
        }
    };

    // A searchable drop-down that asks the server for its rows as you type
    function dropdown($el, kind) {
        var k = kinds[kind];

        $el.select2({
            width: '100%', placeholder: k.placeholder, allowClear: true, dropdownParent: $('.bs-page'),
            language: { searching: function() { return 'Searching...'; }, noResults: function() { return kind === 'lease' ? 'No active lease found. Tick "Include ended leases" for a tenant who has left.' : 'Nothing found.'; } },
            ajax: {
                url: k.url, dataType: 'json', delay: 250, cache: false,
                data: function(params) { return $.extend({ q: params.term || (kind === 'lease' && suggestUnit) || '' }, k.params()); },
                processResults: function(res) {
                    var items = res.data.map(function(r) { return { id: r.id, text: k.text(r), row: r }; });
                    if (res.total > res.data.length) { items.push({ id: '', text: 'Showing the first ' + res.data.length + ' of ' + res.total + '. Type to narrow the list.', disabled: true }); }
                    return { results: items };
                }
            },
            templateResult: function(item) { return item.row ? k.template(item.row) : item.text; },
            templateSelection: function(item) { return item.text; }
        });
    }

    function clear($el, disable, text) {
        $el.empty().append('<option></option>').val(null).trigger('change');
        $el.prop('disabled', !!disable);
    }

    // What the confirm bar says, and whether the button works
    function refresh() {
        var l = sel.lease;

        $('#confirm-btn').prop('disabled', !l || busy);
        $('#confirm-warn').toggleClass('d-none', !l || l.status === 'Active').text(l && l.status !== 'Active' ? 'This lease is ' + l.status + ': the tenant is no longer in the unit. The money is applied to the unpaid invoices of this lease.' : '');

        if (!l) {
            $('#confirm-text').addClass('bs-hint').text('Choose the client, the property and then the unit and tenant.');
            return;
        }
        $('#confirm-text').removeClass('bs-hint').html('Pay <b>' + esc(page.amount) + '</b> (reference ' + esc(page.reference || '-') + ', ' + esc(page.date || 'no date') + ') to <b>' + esc(l.tenant) + '</b>, unit <b>' + esc(l.unit_code) + '</b> at ' + esc(sel.property.name) + ' (client ' + esc(sel.client.names) + ').');
    }

    function openLater($el) {
        setTimeout(function() { $el.select2('open'); }, 0);
    }

    // Close the list first, so a list that is already open asks the server again
    function reopenLater($el) {
        $el.select2('close');
        openLater($el);
    }

    dropdown($client, 'client');
    dropdown($property, 'property');
    dropdown($lease, 'lease');

    $client.on('select2:select', function(e) {
        sel.client = e.params.data.row;
        sel.property = null;
        sel.lease = null;
        clear($property, false);
        clear($lease, true);
        refresh();
        openLater($property);
    }).on('select2:clear', function() {
        sel.client = sel.property = sel.lease = null;
        clear($property, true);
        clear($lease, true);
        refresh();
    });

    $property.on('select2:select', function(e) {
        sel.property = e.params.data.row;
        sel.lease = null;
        clear($lease, false);
        refresh();
        openLater($lease);
    }).on('select2:clear', function() {
        sel.property = sel.lease = null;
        clear($lease, true);
        refresh();
    });

    $lease.on('select2:select', function(e) {
        sel.lease = e.params.data.row;
        refresh();
        $('#confirm-btn').trigger('focus');
    }).on('select2:clear', function() {
        sel.lease = null;
        refresh();
    });

    // Ticking or unticking changes which leases are offered, so a chosen ended lease cannot stay chosen
    $('#ended-toggle').on('change', function() {
        if (!$lease.prop('disabled')) {
            if (sel.lease && !this.checked && sel.lease.status !== 'Active') { sel.lease = null; clear($lease, false); refresh(); }
            reopenLater($lease);
        }
    });

    $('#suggest-clear').on('click', function(e) {
        e.preventDefault();
        suggestUnit = null;
        $('#suggest-note').addClass('d-none');
        reopenLater($lease);
    });

    // ── Confirm: match the line and pay it in ──
    function showDone(res) {
        var back = '<a class="bs-btn bs-btn-light" href="' + esc(urls.back) + '"><span class="material-symbols-outlined">arrow_back</span>Back to the statement</a>';
        var next = res.next_line_id ? '<a class="bs-btn bs-btn-primary" href="' + esc(urls.matchBase + '/' + page.bank + '/lines/' + res.next_line_id + '/match') + '"><span class="material-symbols-outlined">arrow_forward</span>Match the next unmatched line</a>' : '';
        var html;

        if (res.paid) {
            html = '<div class="bs-done"><span class="material-symbols-outlined">check_circle</span><h4>Matched and paid in</h4>' +
                '<div class="bs-kv"><span>Receipt</span><span>' + esc(res.receipt_number) + '</span><span>Tenant</span><span>' + esc(res.tenant) + '</span><span>Unit</span><span>' + esc(res.unit_code) + '</span>' +
                '<span>Amount</span><span>' + esc(res.amount) + '</span><span>Applied to invoices</span><span>' + esc(res.allocated) + '</span>' +
                (res.credit && res.credit !== '0.00' ? '<span>Held as credit</span><span>' + esc(res.credit) + '</span>' : '') + '</div>' +
                '<div class="bs-hint">The receipt is emailed to the tenant in the background.</div><div class="bs-actions">' + next + back + '</div></div>';
        } else {
            html = '<div class="bs-done warn"><span class="material-symbols-outlined">warning</span><h4>Not paid</h4><p>' + esc(res.message) + '</p><div class="bs-actions">' + next + back + '</div></div>';
        }

        if (res.paid) {
            $('#line-status').attr('class', 'bs-pill Paid').text('Paid');
            $('#line-note').addClass('d-none');
        }
        $('#match-flow').addClass('d-none');
        $('#done-card').html(html).removeClass('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function submit() {
        if (busy) { return; }
        busy = true;
        var button = $('#confirm-btn').prop('disabled', true).html('<span class="material-symbols-outlined bs-spin">progress_activity</span>Matching and paying...');

        $.ajax({ url: urls.store, method: 'POST', data: { _token: csrf, unit_id: sel.lease.unit_id, lease_id: sel.lease.id }, dataType: 'json' })
            .done(function(res) { showDone(res); })
            .fail(function(xhr) { toastr.error(errorText(xhr, 'The line could not be matched.')); })
            .always(function() {
                busy = false;
                button.html('<span class="material-symbols-outlined">check_circle</span>Confirm match and pay');
                refresh();
            });
    }

    $('#confirm-btn').on('click', function() {
        if (!sel.lease || busy) { return; }
        Swal.fire({
            title: 'Match and pay this line?',
            html: 'Pay ' + esc(page.amount) + ' to ' + esc(sel.lease.tenant) + ' (unit ' + esc(sel.lease.unit_code) + ')? This cannot be undone from this page.',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Match and pay'
        }).then(function(result) { if (result.isConfirmed) { submit(); } });
    });

    // When the file's unit code points to one unit, the client and property are already chosen and the unit list starts on that unit
    // A mobile number in the details names probable tenants: each one fills the client, property and lease in one click
    function useCandidate(c) {
        sel.client = { id: c.client.id, names: c.client.names, reference: c.client.reference };
        sel.property = { id: c.property.id, name: c.property.name, reference: c.property.reference };
        sel.lease = { id: c.lease_id, unit_id: c.unit.id, unit_code: c.unit.code, unit_name: c.unit.name, unit_number: c.unit.number, tenant: c.tenant, reference: c.tenant_reference, status: c.status };
        $client.empty().append(new Option(sel.client.names, sel.client.id, true, true)).trigger('change');
        $property.empty().append(new Option(sel.property.name, sel.property.id, true, true)).trigger('change').prop('disabled', false);
        $lease.empty().append(new Option(leaseText(sel.lease), sel.lease.id, true, true)).trigger('change').prop('disabled', false);
        $('#ended-toggle').prop('checked', c.status !== 'Active');
        refresh();
        $('#confirm-btn').trigger('focus');
    }

    $.each(page.candidates || [], function(i, c) {
        var row = $('<div class="bs-cand">').css({ display: 'flex', gap: '12px', alignItems: 'center', justifyContent: 'space-between', padding: '8px 0' });
        var text = $('<div>').appendTo(row);
        $('<div class="bs-opt-main">').text(c.tenant + ' · unit ' + c.unit.code).appendTo(text);
        $('<div class="bs-opt-sub">').text(c.property.name + ' · client ' + c.client.names + ' · lease ' + (c.status || '')).appendTo(text);
        $('<button type="button" class="bs-btn bs-btn-light">').text('Use this tenant').on('click', function() { useCandidate(c); }).appendTo(row);
        $('#mobile-candidates').append(row);
    });

    if (page.suggestion) {
        var sug = page.suggestion;

        sel.client = { id: sug.client.id, names: sug.client.names, reference: sug.client.reference };
        sel.property = { id: sug.property.id, name: sug.property.name, reference: sug.property.reference };
        $client.append(new Option(sel.client.names, sel.client.id, true, true)).trigger('change');
        $property.empty().append(new Option(sel.property.name, sel.property.id, true, true)).trigger('change').prop('disabled', false);
        clear($lease, false);
        $('#ended-toggle').prop('checked', true);
        suggestUnit = sug.unit.code;
        $('#suggest-note').removeClass('d-none');
        openLater($lease);
    } else {
        openLater($client);
    }
});
</script>
@endif
@endsection
