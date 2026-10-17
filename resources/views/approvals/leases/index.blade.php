@extends('layouts.main')

@section('styles')
    @include('approvals.partials.styles')
@endsection

@section('content')
<div class="page-body sla-page" style="padding-top:10px;">
    <div class="container-fluid">

        <div class="sla-eyebrow">Approval Control</div>
        <h1 class="sla-title">Lease Agreements</h1>
        <p class="sla-sub">Review each lease with its tenant, unit and property, then approve or reject.</p>

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
                    <input type="text" id="f-search" class="sla-input" placeholder="Search tenant, unit, property or lease ref" autocomplete="off">
                </div>
                <select id="f-zone" class="sla-select" aria-label="Zone">
                    <option value="">All zones</option>
                    @foreach($options['zones'] as $zone)
                        <option value="{{ $zone->id }}">Zone {{ $zone->name }}</option>
                    @endforeach
                </select>
                <select id="f-source" class="sla-select" aria-label="Source">
                    <option value="">All sources</option>
                    <option value="chips">Entered in CHIPS</option>
                    <option value="migrated">Migrated</option>
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
                            <th>Lease</th>
                            <th>Tenant</th>
                            <th>Unit</th>
                            <th>Property</th>
                            <th>Zone</th>
                            <th>Rent</th>
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

@include('approvals.partials.dialogs', ['noun' => 'lease'])
@endsection

@section('scripts')
@include('approvals.partials.helpers')
<script>
(function () {
    const H = window.QH, esc = H.esc, dash = H.dash, money = H.money, icon = H.icon, kv = H.kv, avatar = H.avatar, pill = H.statusPill;

    function renderModal(d) {
        const l = d.lease, t = d.tenant, u = d.unit, p = d.property, sla = d.sla;
        const r = H.readiness(d.checks), block = H.checksBlock(d.checks);
        const where = [u ? (u.code || u.number) : null, p ? p.name : null].filter(Boolean).join(' · ');

        const hero =
            '<div class="hero-row">' + avatar(t ? t.names : '?', true) +
              '<div><div class="hero-name">' + esc(t ? t.names : 'No tenant linked') + '</div>' +
              '<div class="hero-sub">' + esc(where || 'No unit linked') + (p && p.zone ? ' &middot; Zone ' + esc(p.zone) : '') + '</div>' +
              '<div class="hero-meta">' + pill(d.status_id, d.status) + '<span class="chip ref">Lease ' + esc(l.reference) + '</span>' +
              (t && t.reference ? '<span class="chip">' + esc(t.reference) + '</span>' : '') + '<span class="chip">' + esc(l.source) + '</span></div></div></div>' +
            '<div class="kpis">' +
              '<div class="kpi"><div class="k">Monthly rent</div><div class="v">' + (u && u.rent !== null ? money(u.rent) : '&mdash;') + '</div></div>' +
              '<div class="kpi"><div class="k">Term</div><div class="v">' + (l.years ? esc(l.years) + '<small> yr</small>' : '<small style="font-size:14px;color:#334155;">' + (l.open_ended ? 'Open-ended' : (l.date_end !== '-' ? 'to ' + esc(l.date_end) : '&mdash;')) + '</small>') + '</div></div>' +
              '<div class="kpi"><div class="k">Starts</div><div class="v"><small style="font-size:14px;color:#334155;">' + esc(l.date_start) + '</small></div></div>' +
              '<div class="kpi ' + r.tone + '"><div class="k">Readiness</div><div class="v">' + r.passed + '<small> / ' + r.total + ' checks</small></div></div>' +
            '</div>';

        const slaText = sla ? sla.reference + ' (' + sla.status + ')' + (sla.via_property ? ', from the property' : '') : null;
        const overview = block.banner + '<div class="grid2"><div><h6 class="sec-title">Readiness checks</h6>' + block.list + '</div><div>' +
            '<h6 class="sec-title">At a glance</h6>' + kv([
                ['Tenant', t ? t.names : null], ['Phone', t ? t.mobile : null], ['Email', t ? t.email : null],
                ['Unit', u ? [u.code, u.name].filter(Boolean).join(' · ') : null], ['Property', p ? p.name : null], ['Landlord', p ? p.landlord : null],
                ['Monthly rent', u && u.rent !== null ? money(u.rent) : null], ['Starts', l.date_start], ['Ends', l.date_end],
                ['Service agreement', slaText], ['Source', l.source], ['Submitted by', l.created_by],
            ]) + '</div></div>';

        const tenantPane = t ? '<div class="grid2"><div><h6 class="sec-title">Contact &amp; identity</h6>' + kv([
            ['Name', t.names], ['Reference', t.reference], ['Mobile', t.mobile], ['Email', t.email], ['National ID', t.national_id], ['Passport', t.passport], ['PIN', t.pin], ['Address', t.address], ['Status', t.status],
        ]) + '</div><div></div></div>' : H.warn('No tenant is linked to this lease.');

        let others = '';
        if (d.other_leases.length) {
            others = '<h6 class="sec-title" style="margin-top:22px;">Other leases on this unit</h6><div class="units-tbl"><div class="scroll"><table><thead><tr><th>Lease</th><th>Tenant</th><th>Lease status</th><th>Approval</th><th>From</th><th>To</th></tr></thead><tbody>' +
                d.other_leases.map(o => '<tr><td>' + esc(o.reference || '-') + '</td><td>' + esc(o.tenant || '-') + '</td><td>' + esc(o.lease_status || '-') + '</td><td>' + esc(o.status) + '</td><td>' + esc(o.date_start) + '</td><td>' + esc(o.date_end) + '</td></tr>').join('') +
                '</tbody></table></div></div>';
        }
        const unitPane = '<div class="grid2"><div><h6 class="sec-title">Unit</h6>' + (u ? kv([
            ['Code', u.code], ['Name', u.name], ['Number', u.number], ['Monthly rent', u.rent !== null ? money(u.rent) : null], ['VAT applies', u.vatable], ['Status', u.status],
        ]) : H.warn('No unit is linked to this lease.')) + '</div><div><h6 class="sec-title">Property</h6>' + (p ? kv([
            ['Name', p.name], ['Reference', p.reference], ['Zone', p.zone], ['Area', p.area], ['Landlord', p.landlord], ['Landlord ref', p.landlord_ref], ['Field officer', p.field_officer],
        ]) : H.warn('No property is linked to this lease.')) + '</div></div>' + others;

        const items = l.items.length
            ? '<div class="item-grid">' + l.items.map(i => '<div class="item">' + icon('check_circle') + esc(i.name) + (i.amount ? '<span class="cost">' + money(i.amount) + '</span>' : '') + '</div>').join('') + '</div>'
            : '<div class="t-sub">No extra billed items on this lease.</div>';
        const slaCard = sla ? kv([['Reference', sla.reference], ['Status', sla.status], ['Commission', sla.commission !== null ? sla.commission + '%' : null]]) : H.warn('No service agreement is linked to this lease.');
        const termsPane = '<div class="grid2"><div><h6 class="sec-title">Lease terms</h6>' + kv([
            ['Reference', l.reference], ['Lease status', l.lease_status], ['Starts', l.date_start], ['Ends', l.date_end], ['Years', l.years], ['Auto renewal', l.auto_renewal],
            ['Escalation', l.escalation_rate !== null ? l.escalation_rate + '%' + (l.escalation_frequency ? ' ' + String(l.escalation_frequency).toLowerCase() : '') : null],
            ['Service charges', l.service_charges ? money(l.service_charges) : null], ['Water meter start', l.water_meter], ['Linked lease', l.linked_lease],
        ]) + '<h6 class="sec-title" style="margin-top:22px;">Contract</h6>' + kv([
            ['Template', l.contract_template], ['Sent', l.contract_sent], ['Signed', l.contract_signed], ['Signed by', l.signer], ['Tenant verified', l.tenant_verified],
        ]) + '</div><div><h6 class="sec-title">Billed items</h6>' + items + '<h6 class="sec-title" style="margin-top:22px;">Service agreement</h6>' + slaCard + '</div></div>';

        return { hero: hero, panes: { overview: overview, tenant: tenantPane, unit: unitPane, terms: termsPane } };
    }

    window.ApprovalQueue = {
        urls: {
            list: @json(route('approvals.leases.list')),
            show: @json(url('/ajax/approvals/leases')),
            approve: @json(route('approvals.leases.approve')),
            reject: @json(route('approvals.leases.reject')),
        },
        noun: 'lease',
        plural: 'leases',
        filterFields: { search: '#f-search', zone_id: '#f-zone', source: '#f-source', created_by: '#f-creator', date_from: '#f-from', date_to: '#f-to' },
        defaultOrder: [7, 'desc'],
        orderKeys: { 1: 'reference', 2: 'tenant', 3: 'unit', 4: 'property', 5: 'zone', 6: 'rent', 7: 'created', default: 'created' },
        tabs: [
            { key: 'overview', label: 'Overview' },
            { key: 'tenant', label: 'Tenant' },
            { key: 'unit', label: 'Unit & property' },
            { key: 'terms', label: 'Terms & billing' },
        ],
        columns: [
            { data: 'reference', render: (v, t, r) => '<div class="t-ref">' + dash(v) + '</div><div class="t-sub">' +
                (r.date_start !== '-' ? 'From ' + esc(r.date_start) : 'No start date') + ' · ' + (r.date_end === 'Open-ended' ? 'Open-ended' : (r.date_end !== '-' ? 'to ' + esc(r.date_end) : 'No end date')) +
                (r.status_id === 1 && r.block_reason ? ' · <span style="color:#b7791f;">' + (r.sla_status === null ? 'No SLA' : 'SLA not approved') + '</span>' : '') + '</div>' },
            { data: 'tenant_name', render: (v, t, r) => '<div class="t-main">' + dash(v) + '</div><div class="t-sub">' + esc(r.tenant_ref) + '</div>' },
            { data: 'unit_code', render: (v, t, r) => '<div class="t-main">' + dash(v) + '</div><div class="t-sub">' + esc(r.unit_name) + '</div>' },
            { data: 'property_name', render: (v, t, r) => '<div class="t-main">' + dash(v) + '</div><div class="t-sub">' + esc(r.property_ref) + '</div>' },
            { data: 'zone', render: v => v && v !== '-' ? '<span class="chip zone">Zone ' + esc(v) + '</span>' : dash(v) },
            { data: 'rent', render: v => v && v !== '-' ? '<span class="rate">' + esc(v) + '</span>' : dash(v) },
            { data: 'created_at', render: (v, t, r) => '<div class="t-main">' + dash(v) + '</div><div class="t-sub">' + dash(r.created_by) + '</div>' },
        ],
        renderModal: renderModal,
        blockReason: r => r.block_reason,
        itemLabel: d => d.lease.reference,
        approveNote: many => 'The tenant' + (many ? 's' : '') + ' on ' + (many ? 'these leases' : 'this lease') + ' that ' + (many ? 'are' : 'is') + ' still pending will be approved too.',
        footNote: 'Needs an approved service agreement. Approving also approves the tenant on this lease if the tenant is still pending.',
    };
})();
</script>
@include('approvals.partials.engine')
@endsection
