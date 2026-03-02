@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/chabrin.css') }}">
<style>
    .card-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.85);
        z-index: 10;
        display: none;
        justify-content: center;
        align-items: center;
    }

    .card-overlay.active {
        display: flex;
    }

    .card.position-relative {
        position: relative;
    }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card position-relative">
                    <div class="card-overlay" id="card-overlay">
                        <div class="text-center">
                            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Loading tenant details...</p>
                        </div>
                    </div>
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Tenant Management</h5>
                    </div>
                    <div class="card-body">
                        <!-- Search Form -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="tenant_search_input" class="form-label">Search Tenant</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="tenant_search_input"
                                        placeholder="Search by Name, Mobile Number, PIN Number, or Reference Number">
                                    <button type="button" class="btn btn-primary" id="search_tenant_btn">
                                        <span class="material-symbols-outlined" id="search_icon">search</span>
                                        <span class="spinner-border spinner-border-sm" role="status" id="search_spinner" style="display: none;"></span>
                                    </button>
                                </div>
                                <small class="text-muted">Enter at least 2 characters to search</small>
                            </div>
                            <div class="col-md-6">
                                <label for="tenant_select" class="form-label">Select Tenant</label>
                                <select class="form-select" id="tenant_select" name="tenant_id" required>
                                    <option value="">-- Select Tenant --</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        // Search tenant button click
        $('#search_tenant_btn').on('click', function() {
            const searchTerm = $('#tenant_search_input').val().trim();
            const $btn = $(this);
            const $icon = $('#search_icon');
            const $spinner = $('#search_spinner');

            if (searchTerm.length < 2) {
                toastr.error('Please enter at least 2 characters to search');
                return;
            }

            // Disable button, hide icon, show spinner
            $btn.prop('disabled', true);
            $icon.hide();
            $spinner.show();
            $('#tenant_search_input').prop('disabled', true);
            $('#tenant_select').prop('disabled', true);

            $.ajax({
                url: '/tenants/search',
                method: 'GET',
                data: {
                    search: searchTerm
                },
                success: function(response) {
                    if (response.tenants && response.tenants.length > 0) {
                        let options = '<option value="">-- Select Tenant --</option>';
                        response.tenants.forEach(function(tenant) {
                            options += `<option value="${tenant.encrypted_id}">
                                ${tenant.names} - Mobile: ${tenant.mobile_number || 'N/A'} - PIN: ${tenant.national_id || 'N/A'} - Ref: ${tenant.reference_number || 'N/A'}
                            </option>`;
                        });
                        $('#tenant_select').html(options);
                        toastr.success(`Found ${response.tenants.length} tenant(s)`);
                    } else {
                        toastr.info('No tenants found');
                        $('#tenant_select').html('<option value="">-- Select Tenant --</option>');
                    }
                },
                error: function() {
                    toastr.error('Search failed');
                },
                complete: function() {
                    // Re-enable button, hide spinner, show icon
                    $btn.prop('disabled', false);
                    $spinner.hide();
                    $icon.show();
                    $('#tenant_search_input').prop('disabled', false);
                    $('#tenant_select').prop('disabled', false);
                }
            });
        });

        // Allow search on Enter key
        $('#tenant_search_input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#search_tenant_btn').click();
            }
        });

        // Redirect on tenant selection
        $('#tenant_select').on('change', function() {
            const tenantId = $(this).val();
            if (tenantId) {
                // Show card overlay spinner, disable controls
                $('#card-overlay').addClass('active');
                $('#search_tenant_btn').prop('disabled', true);
                $('#tenant_search_input').prop('disabled', true);
                $('#tenant_select').prop('disabled', true);
                // Navigate to tenant details page
                window.location.href = `/tenants/${tenantId}`;
            }
        });
    });
</script>