{{-- Shared by the Add and Edit Recovery forms (included inside their <script>). Needs #unit_id, #tenant_id, #lease_id and a
     loadInvoices(unitId, tenantId) function. Picking a unit fills in the tenant and lease for the user: the unit's active
     lease and its tenant. The user can still change either. --}}
        var unitParties = { leases: [], tenants: [] };

        function leaseLabel(lease) {
            var dates = (lease.date_start || '') + (lease.date_end ? ' to ' + lease.date_end : '');
            return (lease.reference_number || ('Lease ' + lease.id)) + ' - ' + (lease.is_active ? 'Active' : (lease.status || 'Inactive')) + (dates ? ' (' + dates + ')' : '');
        }

        // preferred: {tenantId, leaseId} to keep what is already saved (the Edit form); without it the active lease is used
        function loadUnitParties(unitId, preferred) {
            $.getJSON('{{ url('/property/recoveries/lookup/unit') }}/' + unitId + '/parties', function(data) {
                unitParties = data;
                var tenantSelect = $('#tenant_id').empty().append('<option value="">Please select</option>');
                var leaseSelect = $('#lease_id').empty().append('<option value="">Please select</option>');

                data.tenants.forEach(function(tenant) {
                    tenantSelect.append($('<option>').val(tenant.id).text((tenant.reference_number ? tenant.reference_number + ' - ' : '') + (tenant.names || '')));
                });
                data.leases.forEach(function(lease) {
                    leaseSelect.append($('<option>').val(lease.id).text(leaseLabel(lease)));
                });
                if (!data.leases.length) {
                    leaseSelect.append('<option value="">No lease found for this unit</option>');
                }

                var leaseId = (preferred && preferred.leaseId) || data.default_lease_id;
                var lease = data.leases.filter(function(l) { return l.id == leaseId; })[0];
                var tenantId = (preferred && preferred.tenantId) || (lease ? lease.tenant_id : null);

                if (tenantId && !data.tenants.some(function(t) { return t.id == tenantId; })) {
                    tenantSelect.append($('<option>').val(tenantId).text('Tenant ' + tenantId)); // a saved tenant with no lease on this unit
                }
                leaseSelect.val(leaseId || '');
                tenantSelect.val(tenantId || '');
                $('#tenant_id, #lease_id').trigger('change.select2');
                loadInvoices(unitId, tenantId);
            }).fail(function() {
                $('#tenant_id').empty().append('<option value="">Error loading tenant</option>').trigger('change.select2');
                $('#lease_id').empty().append('<option value="">Error loading leases</option>').trigger('change.select2');
            });
        }

        // Choosing another lease selects that lease's tenant; choosing another tenant narrows the invoices
        $('#lease_id').on('change', function() {
            var leaseId = $(this).val();
            var lease = unitParties.leases.filter(function(l) { return l.id == leaseId; })[0];
            if (lease && lease.tenant_id) {
                $('#tenant_id').val(lease.tenant_id).trigger('change.select2');
                loadInvoices($('#unit_id').val(), lease.tenant_id);
            }
        });
        $('#tenant_id').on('change', function() {
            if ($('#unit_id').val()) {
                loadInvoices($('#unit_id').val(), $(this).val());
            }
        });
