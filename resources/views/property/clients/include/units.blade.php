<div class="tab-pane fade" id="units" role="tabpanel" aria-labelledby="units-tab">
    <div class="row mb-3 align-items-end">
        <div class="col-5">
            <label for="propertySelect" class="form-label">Select Property</label>
            <select id="propertySelect" class="form-select">
                <option value="">-- Select Property --</option>
            </select>
        </div>
        <div class="col-auto">
            <div id="propertySpinner" class="spinner-border text-primary" role="status" style="display: none;">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>
    <div class="table-responsive custom-scrollbar">
        <table id="unitsTable" class="display" style="width:100%;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Unit Code</th>
                    <th>Unit Name</th>
                    <th>Unit Number</th>
                    <th>Description</th>
                    <th>Rent Amount</th>
                    <th>Zone</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>