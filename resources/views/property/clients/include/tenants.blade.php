<div class="tab-pane fade" id="tenants" role="tabpanel" aria-labelledby="tenants-tab">
    <div class="row mb-3 align-items-end">
        <div class="col-5">
            <label for="tenantPropertySelect" class="form-label">Select Property</label>
            <select id="tenantPropertySelect" class="form-select">
                <option value="">-- Select Property --</option>
            </select>
        </div>
        <div class="col-auto">
            <div id="tenantSpinner" class="spinner-border text-primary" role="status" style="display: none;">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>
    <div class="table-responsive custom-scrollbar">
        <table id="tenantsTable" class="display" style="width:100%;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Names</th>
                    <th>Mobile Number</th>
                    <th>Email Address</th>
                    <th>Unit Name</th>
                    <th>Reference Number</th>
                    <th>Registered Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>