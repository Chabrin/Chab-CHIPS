@extends('layouts.main')

@section('styles')
    @include('approvals.partials.styles')
@endsection

@section('content')
<div class="page-body sla-page" style="padding-top:10px;">
    <div class="container-fluid">

        <div class="sla-eyebrow">Approval Control</div>
        <h1 class="sla-title">Service Agreements</h1>
        <p class="sla-sub">Review each agreement with its client and property, then approve or reject.</p>

        <div class="sla-tabs" id="sla-tabs">
            <button type="button" class="sla-tab" data-status="1">Pending <span class="n" id="count-pending">{{ number_format($counts['pending']) }}</span></button>
            <button type="button" class="sla-tab" data-status="5">Approved <span class="n" id="count-approved">{{ number_format($counts['approved']) }}</span></button>
            <button type="button" class="sla-tab" data-status="41">Rejected <span class="n" id="count-rejected">{{ number_format($counts['rejected']) }}</span></button>
            <button type="button" class="sla-tab" data-status="all">All</button>
        </div>

        <div class="sla-card">
            <div class="sla-filters">
                <input type="hidden" id="f-status" value="1">
                <div class="sla-field">
                    <span class="material-symbols-outlined">search</span>
                    <input type="text" id="f-search" class="sla-input" placeholder="Search client, property or SLA ref" autocomplete="off">
                </div>
                <select id="f-zone" class="sla-select" aria-label="Zone">
                    <option value="">All zones</option>
                    @foreach($options['zones'] as $zone)
                        <option value="{{ $zone->id }}">Zone {{ $zone->name }}</option>
                    @endforeach
                </select>
                <select id="f-creator" class="sla-select" aria-label="Created by">
                    <option value="">Anyone</option>
                    @foreach($options['creators'] as $creator)
                        <option value="{{ $creator->id }}">{{ $creator->name }}</option>
                    @endforeach
                </select>
                <div class="sla-range" title="Date submitted">
                    <input type="date" id="f-from" aria-label="Submitted from">
                    <span>to</span>
                    <input type="date" id="f-to" aria-label="Submitted to">
                </div>
                <button type="button" class="sla-reset" id="f-reset"><span class="material-symbols-outlined">restart_alt</span>Reset</button>
            </div>

            <div class="sla-table-wrap">
                <table class="table mb-0" id="sla-table">
                    <thead>
                        <tr>
                            <th style="width:36px;"><input type="checkbox" id="select-all" class="form-check-input" aria-label="Select all on page"></th>
                            <th>Agreement</th>
                            <th>Client</th>
                            <th>Property</th>
                            <th>Zone</th>
                            <th>Commission</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th style="width:120px;"></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('approvals.partials.dialogs', ['noun' => 'service agreement'])
@endsection

@section('scripts')
@include('approvals.partials.helpers')
<script>
(function () {
    const H = window.QH, esc = H.esc, dash = H.dash, money = H.money, icon = H.icon, kv = H.kv, avatar = H.avatar, pill = H.statusPill;

    function renderModal(d) {
        const s = d.sla, c = d.client, p = d.property;
        const r = H.readiness(d.checks), block = H.checksBlock(d.checks);

        const hero =
            '<div class="hero-row">' + avatar(c ? c.names : '?', true) +
              '<div><div class="hero-name">' + esc(c ? c.names : 'No client linked') + '</div>' +
              '<div class="hero-sub">' + esc(p ? p.name : 'No property linked') + (p && p.zone ? ' &middot; Zone ' + esc(p.zone) : '') + '</div>' +
              '<div class="hero-meta">' + pill(d.status_id, d.status) + '<span class="chip ref">SLA ' + esc(s.reference) + '</span>' +
              (c && c.reference ? '<span class="chip">' + esc(c.reference) + '</span>' : '') + '</div></div></div>' +
            '<div class="kpis">' +
              '<div class="kpi"><div class="k">Commission</div><div class="v">' + (s.commission !== null ? esc(s.commission) + '<small>%</small>' : '&mdash;') + '</div></div>' +
              '<div class="kpi"><div class="k">Term</div><div class="v">' + (s.years ? esc(s.years) + '<small> yr</small>' : (s.date_end && s.date_end !== '-' ? '<small style="font-size:14px;color:#334155;">to ' + esc(s.date_end) + '</small>' : '<small style="font-size:14px;color:#334155;">Open-ended</small>')) + '</div></div>' +
              '<div class="kpi"><div class="k">Units</div><div class="v">' + (p ? p.units_total : 0) + (p && p.units_pending ? '<small> · ' + p.units_pending + ' pending</small>' : '') + '</div></div>' +
              '<div class="kpi ' + r.tone + '"><div class="k">Readiness</div><div class="v">' + r.passed + '<small> / ' + r.total + ' checks</small></div></div>' +
            '</div>';

        const overview = block.banner + '<div class="grid2"><div><h6 class="sec-title">Readiness checks</h6>' + block.list + '</div><div>' +
            '<h6 class="sec-title">At a glance</h6>' + kv([
                ['Client', c ? c.names : null], ['Phone', c ? c.mobile : null], ['Email', c ? c.email : null],
                ['Property', p ? p.name : null], ['Field officer', p ? p.field_officer : null],
                ['Commission', s.commission !== null ? s.commission + '%' : null], ['Starts', s.date_start], ['Submitted by', s.created_by],
            ]) + '</div></div>';

        let banks = '';
        if (c) {
            banks = c.banks.map(b => '<div class="bank-card"><span class="ico">' + icon('account_balance') + '</span><div><div class="t-main">' + esc(b.bank || 'Bank') + (b.is_default ? ' <span class="chip zone">Default</span>' : '') + '</div><div class="t-sub">' + esc(b.account_number) + ' &middot; ' + esc(b.account_name) + '</div></div></div>').join('');
            if (c.legacy_bank) { banks += '<div class="bank-card"><span class="ico">' + icon('account_balance') + '</span><div><div class="t-main">' + esc(c.legacy_bank.account_number) + '</div><div class="t-sub">' + esc(c.legacy_bank.account_name) + ' &middot; on client record</div></div></div>'; }
            if (!banks) { banks = H.warn('No bank account on file for this client.'); }
        }
        const clientPane = c ? '<div class="grid2"><div><h6 class="sec-title">Contact &amp; identity</h6>' + kv([
            ['Name', c.names], ['Reference', c.reference], ['Mobile', c.mobile], ['Email', c.email], ['PIN', c.pin], ['National ID', c.national_id], ['VAT number', c.vat_number], ['Address', c.address], ['Status', c.status],
        ]) + '</div><div><h6 class="sec-title">Payout accounts</h6>' + banks + '</div></div>' : H.warn('No client is linked to this agreement.');

        let unitsHtml = '<div class="t-sub">No units on this property yet.</div>';
        if (p && p.units.length) {
            unitsHtml = '<div class="units-tbl"><div class="scroll"><table><thead><tr><th>Unit</th><th>Name</th><th class="r">Rent</th><th>Status</th></tr></thead><tbody>' +
                p.units.map(u => '<tr><td>' + esc(u.code || '-') + '</td><td>' + esc(u.name || '') + '</td><td class="r">' + money(u.rent) + '</td><td>' + esc(u.status) + '</td></tr>').join('') +
                '</tbody></table></div></div>' + (p.units_total > p.units.length ? '<div class="t-sub mt-2">Showing the first ' + p.units.length + ' of ' + p.units_total + ' units.</div>' : '');
        }
        const propertyPane = p ? '<div class="grid2"><div><h6 class="sec-title">Property</h6>' + kv([
            ['Name', p.name], ['Reference', p.reference], ['LR number', p.lr_number], ['Usage', p.usage_type], ['Zone', p.zone], ['Area', p.area],
            ['Field officer', p.field_officer], ['Supervisor', p.zone_supervisor], ['Manager', p.zone_manager], ['Status', p.status],
        ]) + '</div><div><h6 class="sec-title">Units (' + p.units_total + ')</h6>' + unitsHtml + '</div></div>' : H.warn('No property is linked to this agreement.');

        const items = '<div class="item-grid">' + s.items.map(i => '<div class="item ' + (i.selected ? '' : 'off') + '">' + icon(i.selected ? 'check_circle' : 'do_not_disturb_on') + esc(i.label) + (i.selected && i.cost ? '<span class="cost">' + money(i.cost) + '</span>' : '') + '</div>').join('') + '</div>';
        const payDay = /^\d+$/.test(String(s.payment_date || '')) ? 'Day ' + s.payment_date + ' of the month' : s.payment_date;
        const termsPane = '<div class="grid2"><div><h6 class="sec-title">Agreement</h6>' + kv([
            ['Reference', s.reference], ['Commission', s.commission !== null ? s.commission + '%' : null], ['VAT', s.vat_per ? s.vat_per + '%' : s.vat], ['Flat-rate VAT', s.flat_rate_vat],
            ['Starts', s.date_start], ['Ends', s.date_end], ['Years', s.years], ['Landlord paid on', payDay], ['Auto renewal', s.auto_renewal ? 'Yes' : 'No'],
            ['Registration fee', s.registration_fee ? 'Charged' : 'Not charged'], ['Move-in inspection', s.move_in_inspection ? 'Required' : 'Not required'],
            ['Returns', s.returns_combination ? String(s.returns_combination).replace(/_/g, ' ').replace(/^./, ch => ch.toUpperCase()) : null],
            ['Submitted by', s.created_by], ['Submitted', s.created_at],
        ]) + '</div><div><h6 class="sec-title">Billed items</h6>' + items + '</div></div>';

        return { hero: hero, panes: { overview: overview, client: clientPane, property: propertyPane, terms: termsPane } };
    }

    window.ApprovalQueue = {
        urls: {
            list: @json(route('approvals.sla.list')),
            show: @json(url('/ajax/approvals/sla')),
            approve: @json(route('approvals.sla.approve')),
            reject: @json(route('approvals.sla.reject')),
        },
        noun: 'agreement',
        plural: 'agreements',
        filterFields: { search: '#f-search', zone_id: '#f-zone', created_by: '#f-creator', date_from: '#f-from', date_to: '#f-to' },
        defaultOrder: [6, 'desc'],
        orderKeys: { 1: 'reference', 2: 'client', 3: 'property', 4: 'zone', 5: 'commission', 6: 'created', default: 'created' },
        tabs: [
            { key: 'overview', label: 'Overview' },
            { key: 'client', label: 'Client' },
            { key: 'property', label: 'Property & units' },
            { key: 'terms', label: 'Terms & billing' },
        ],
        columns: [
            { data: 'reference', render: (v, t, r) => '<div class="t-ref">' + dash(v) + '</div><div class="t-sub">' +
                (r.date_start !== '-' ? 'From ' + esc(r.date_start) : 'No start date') + (r.years ? ' · ' + esc(r.years) + ' yr' : '') +
                (!r.registration_fee ? ' · <span style="color:#b7791f;">No reg. fee</span>' : '') + '</div>' },
            { data: 'client_name', render: (v, t, r) => '<div class="t-main">' + dash(v) + '</div><div class="t-sub">' + esc(r.client_ref) + '</div>' },
            { data: 'property_name', render: (v, t, r) => '<div class="t-main">' + dash(v) + '</div><div class="t-sub">' + esc(r.property_ref) + ' &middot; ' + r.units_count + ' unit' + (r.units_count === 1 ? '' : 's') + '</div>' },
            { data: 'zone', render: v => v && v !== '-' ? '<span class="chip zone">Zone ' + esc(v) + '</span>' : dash(v) },
            { data: 'commission', render: v => v && v !== '-' ? '<span class="rate">' + esc(v) + '</span>' : dash(v) },
            { data: 'created_at', render: (v, t, r) => '<div class="t-main">' + dash(v) + '</div><div class="t-sub">' + dash(r.created_by) + '</div>' },
        ],
        renderModal: renderModal,
        approveNote: many => 'The client, property and units behind ' + (many ? 'them' : 'it') + ' that are still pending will be approved too.',
        footNote: 'Approving also approves the client, property and units behind this agreement that are still pending.',
    };
})();
</script>
@include('approvals.partials.engine')
@endsection
