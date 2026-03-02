<div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
    <div class="row g-3">
        <!-- Personal Information Card -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light py-2">
                    <h6 class="mb-0" style="font-size: 0.9rem;">
                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">badge</span>
                        Personal Information
                    </h6>
                </div>
                <div class="card-body py-2">
                    <div class="row g-1">
                        <div class="col-5"><small class="text-muted">Full Names:</small></div>
                        <div class="col-7"><small><strong>{{ $client->names ?? '-' }}</strong></small></div>

                        <div class="col-5"><small class="text-muted">National ID:</small></div>
                        <div class="col-7"><small><strong>{{ $client->national_id ?? '-' }}</strong></small></div>

                        <div class="col-5"><small class="text-muted">Reference No:</small></div>
                        <div class="col-7"><small><strong>{{ $client->reference_number ?? '-' }}</strong></small></div>

                        <div class="col-5"><small class="text-muted">Mobile Number:</small></div>
                        <div class="col-7"><small><strong>{{ $client->mobile_number ?? '-' }}</strong></small></div>

                        <div class="col-5"><small class="text-muted">Email Address:</small></div>
                        <div class="col-7"><small><strong>{{ $client->email_address ?? '-' }}</strong></small></div>

                        <div class="col-5"><small class="text-muted">Registered:</small></div>
                        <div class="col-7"><small><strong>{{ $client->registered_date ? $client->registered_date->format('d-m-Y') : '-' }}</strong></small></div>

                        <div class="col-12 mt-2"><small class="text-muted">Address:</small></div>
                        <div class="col-12"><small><strong>{{ $client->address ?? '-' }}</strong></small></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Banking & Tax Details Card -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light py-2">
                    <h6 class="mb-0" style="font-size: 0.9rem;">
                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">account_balance</span>
                        Banking & Tax Details
                    </h6>
                </div>
                <div class="card-body py-2">
                    <div class="row g-1">
                        <div class="col-5"><small class="text-muted">Account Name:</small></div>
                        <div class="col-7"><small><strong>{{ $client->account_name ?? '-' }}</strong></small></div>

                        <div class="col-5"><small class="text-muted">Account Number:</small></div>
                        <div class="col-7"><small><strong>{{ $client->account_number ?? '-' }}</strong></small></div>

                        <div class="col-5"><small class="text-muted">VAT Number:</small></div>
                        <div class="col-7"><small><strong>{{ $client->vat_number ?? '-' }}</strong></small></div>

                        <div class="col-5"><small class="text-muted">PIN Number:</small></div>
                        <div class="col-7"><small><strong>{{ $client->pin_number ?? '-' }}</strong></small></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>