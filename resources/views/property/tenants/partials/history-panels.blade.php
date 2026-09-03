{{-- Shared by the Returning Tenant flow and the standalone Tenant History
     page — "Current Assignment" (what a tenant's Client row + any Active
     LeaseAgreement shows right now) and "Previous Tenancies" (cb_tenant_history,
     what they've ever had). Kept as one partial so the two pages' markup can't
     drift apart; each page supplies its own JS to populate these tables via
     TenantsController::currentTenancy() / tenantHistory(). --}}

<div class="row mb-4" id="currentAssignmentSection">
    <div class="col-12 mb-3">
        <h6 class="text-muted border-bottom pb-2">Current Assignment</h6>
    </div>
    <div class="col-12">
        <div class="alert alert-light-warning py-2 px-3 mb-3 d-none" id="hasCurrentAssignmentAlert">
            <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">warning</span>
            <span id="currentAssignmentWarningText">This tenant currently has a unit/lease on record.</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 d-none" id="currentAssignmentTable">
                <thead>
                    <tr>
                        <th>Property</th>
                        <th>Unit</th>
                        <th>SLA</th>
                        <th>Rent</th>
                        <th>Lease Start</th>
                        <th>Lease Years</th>
                        <th>Frequency</th>
                        <th>Active Lease Ref</th>
                        <th>Lease Status</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="text-muted small p-2 d-none" id="noCurrentAssignmentNote">No current unit/lease on record for this tenant.</div>
    </div>
</div>

<div class="row mb-4" id="tenantHistorySection">
    <div class="col-12 mb-3">
        <h6 class="text-muted border-bottom pb-2">Previous Tenancies</h6>
    </div>
    <div class="col-12">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" id="tenantHistoryTable">
                <thead>
                    <tr>
                        <th>Property</th>
                        <th>Unit</th>
                        <th>Landlord</th>
                        <th>Lease Start</th>
                        <th>Exit Date</th>
                        <th>Duration</th>
                        <th>Rent</th>
                        <th>SLA</th>
                        <th>Exit Reason</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
            <div class="text-muted small p-2 d-none" id="noHistoryNote">No prior tenancy history found for this tenant.</div>
        </div>
    </div>
</div>
