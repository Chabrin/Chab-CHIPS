@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Tenant History — {{ $tenant->names }}</h5>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('property.tenants.show', $tenant->encrypted_id) }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to Tenant</span>
                            </a>
                            <a href="{{ route('units.tenants.returning.create', ['tenant' => $tenant->encrypted_id]) }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">move_up</span>
                                <span class="ms-1">Reassign This Tenant</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @include('property.tenants.partials.history-panels')
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        function renderCurrentAssignment(data) {
            var $table = $('#currentAssignmentTable');
            var $note = $('#noCurrentAssignmentNote');
            var $alert = $('#hasCurrentAssignmentAlert');

            if (!data || !data.has_current) {
                $table.addClass('d-none');
                $note.removeClass('d-none');
                $alert.addClass('d-none');
                return;
            }

            $note.addClass('d-none');
            $table.removeClass('d-none').find('tbody').empty().append(
                '<tr>' +
                '<td>' + (data.property_name || '-') + '</td>' +
                '<td>' + (data.unit_code || '-') + '</td>' +
                '<td>' + (data.sla_reference || '-') + '</td>' +
                '<td>' + (data.rent_amount !== null ? Number(data.rent_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-') + '</td>' +
                '<td>' + (data.lease_start_date || '-') + '</td>' +
                '<td>' + (data.lease_years || '-') + '</td>' +
                '<td>' + (data.frequency || '-') + '</td>' +
                '<td>' + (data.active_lease ? data.active_lease.ref_number : '-') + '</td>' +
                '<td>' + (data.active_lease ? data.active_lease.lease_status : '-') + '</td>' +
                '</tr>'
            );
            $alert.removeClass('d-none');
        }

        function renderTenantHistory(rows) {
            var $tbody = $('#tenantHistoryTable tbody');
            var $note = $('#noHistoryNote');
            $tbody.empty();
            if (!rows || rows.length === 0) {
                $note.removeClass('d-none');
                return;
            }
            $note.addClass('d-none');
            rows.forEach(function(h) {
                $tbody.append(
                    '<tr>' +
                    '<td>' + h.property_name + '</td>' +
                    '<td>' + h.unit_code + '</td>' +
                    '<td>' + h.landlord_name + '</td>' +
                    '<td>' + h.lease_start_date + '</td>' +
                    '<td>' + h.exit_date + '</td>' +
                    '<td>' + h.duration_label + '</td>' +
                    '<td>' + (h.rent_amount !== null ? Number(h.rent_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-') + '</td>' +
                    '<td>' + h.sla_reference + '</td>' +
                    '<td>' + h.exit_reason + '</td>' +
                    '</tr>'
                );
            });
        }

        var tenantId = '{{ $tenant->encrypted_id }}';

        $.ajax({
            url: '/units/tenants/returning/' + tenantId + '/current',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                renderCurrentAssignment(response.success ? response : null);
            },
            error: function() {
                toastr.error('Failed to load current tenancy');
            }
        });

        $.ajax({
            url: '/units/tenants/returning/' + tenantId + '/history',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                renderTenantHistory(response.success ? response.history : []);
            },
            error: function() {
                toastr.error('Failed to load tenancy history');
            }
        });
    });
</script>
@endsection
