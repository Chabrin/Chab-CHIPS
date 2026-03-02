<div class="tab-pane fade" id="leases" role="tabpanel" aria-labelledby="leases-tab">
    <div class="row mb-3 align-items-end">
        <div class="col-5">
            <label for="leasesPropertySelect" class="form-label">Select Property</label>
            <select id="leasesPropertySelect" class="form-select">
                <option value="">-- Select Property --</option>
            </select>
        </div>
        <div class="col-auto">
            <div id="leasesSpinner" class="spinner-border text-primary" role="status" style="display: none;">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>
    <div class="table-responsive custom-scrollbar">
        <table id="leasesTable" class="display" style="width:100%;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Ref</th>
                    <th>Tenant</th>
                    <th>Property</th>
                    <th>Unit</th>
                    <th>Start</th>
                    <th>End</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>