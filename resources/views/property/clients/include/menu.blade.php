@permission('create_clients')
<li>
    <a class="dropdown-item d-flex align-items-center" href="#" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">
        <span class="material-symbols-outlined me-2" style="font-size:18px;">upload_file</span>
        Bulk Upload Data
    </a>
</li>
@endpermission
@permission('create_properties')
<li>
    <a class="dropdown-item d-flex align-items-center" href="{{ route('property.properties.create', ['client' => Crypt::encrypt($client->id)]) }}">
        <span class="material-symbols-outlined me-2" style="font-size:18px;">add_home</span>
        Add Property
    </a>
</li>
@endpermission
@permission('create_service_agreements')
<li>
    <a class="dropdown-item d-flex align-items-center" href="{{ route('service.agreements.create', ['client' => Crypt::encrypt($client->id)]) }}">
        <span class="material-symbols-outlined me-2" style="font-size:18px;">approval_delegation</span>
        Add SLA
    </a>
</li>
@endpermission
@permission('create_tenants')
<li>
    <a class="dropdown-item d-flex align-items-center" href="{{ route('units.tenants.create', ['client' => Crypt::encrypt($client->id)]) }}">
        <span class="material-symbols-outlined me-2" style="font-size:18px;">person</span>
        Add Tenants
    </a>
</li>
@endpermission
@permission('create_lease_agreements')
<li>
    <a class="dropdown-item d-flex align-items-center" href="{{ route('tenant.lease.create', ['client' => Crypt::encrypt($client->id)]) }}">
        <span class="material-symbols-outlined me-2" style="font-size:18px;">document_scanner</span>
        Add Lease
    </a>
</li>
@endpermission
