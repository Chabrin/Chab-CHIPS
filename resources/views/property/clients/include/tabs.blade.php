@permission('read_clients')
<li class="nav-item" role="presentation">
    <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
        <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">person</span>
        Profile
    </button>
</li>
@endpermission
@permission('read_clients')
<li class="nav-item" role="presentation">
    <button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab">
        <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">analytics</span>
        Stats
    </button>
</li>
@endpermission
@permission('read_properties')
<li class="nav-item" role="presentation">
    <button class="nav-link" id="properties-tab" data-bs-toggle="tab" data-bs-target="#properties" type="button" role="tab">
        <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">home</span>
        Properties
    </button>
</li>
@endpermission
@permission('read_service_agreements')
<li class="nav-item" role="presentation">
    <button class="nav-link" id="sla-tab" data-bs-toggle="tab" data-bs-target="#sla" type="button" role="tab">
        <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">approval_delegation</span>
        SLA
    </button>
</li>
@endpermission
@permission('read_units')
<li class="nav-item" role="presentation">
    <button class="nav-link" id="units-tab" data-bs-toggle="tab" data-bs-target="#units" type="button" role="tab">
        <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">apartment</span>
        Units
    </button>
</li>
@endpermission
@permission('read_tenants')
<li class="nav-item" role="presentation">
    <button class="nav-link" id="tenants-tab" data-bs-toggle="tab" data-bs-target="#tenants" type="button" role="tab">
        <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">group</span>
        Tenants
    </button>
</li>
@endpermission
@permission('read_lease_agreements')
<li class="nav-item" role="presentation">
    <button class="nav-link" id="leases-tab" data-bs-toggle="tab" data-bs-target="#leases" type="button" role="tab">
        <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">description</span>
        Leases
    </button>
</li>
@endpermission
@permission('read_recoveries')
<li class="nav-item" role="presentation">
    <button class="nav-link" id="recoveries-tab" data-bs-toggle="tab" data-bs-target="#recoveries" type="button" role="tab">
        <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">payments</span>
        Recoveries
    </button>
</li>
@endpermission
@permission('read_inspections')
<li class="nav-item" role="presentation">
    <button class="nav-link" id="inspections-tab" data-bs-toggle="tab" data-bs-target="#inspections" type="button" role="tab">
        <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">fact_check</span>
        Inspections
    </button>
</li>
@endpermission
@permission('read_repair_requests')
<li class="nav-item" role="presentation">
    <button class="nav-link" id="repairs-tab" data-bs-toggle="tab" data-bs-target="#repairs" type="button" role="tab">
        <span class="material-symbols-outlined" style="font-size:18px; vertical-align: middle;">build</span>
        Repairs & Maintenance
    </button>
</li>
@endpermission