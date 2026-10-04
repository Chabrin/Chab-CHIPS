<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Roles that bypass zone/property access scoping (HasZoneAccessScope) —
     * they always see all clients/tenants/leases/properties, even if the
     * logged-in user also happens to hold a zone_manager/zone_supervisor/
     * zone_auditor/field_officer staff assignment somewhere. Everyone else
     * (zone_manager, property_manager, office_assistant, field_officer,
     * senior_field_officer) stays scoped to their own zone(s)/properties.
     */
    private array $bypassRoles = [
        'qc_manager',
        'it_department',
        'accountant',
        'assistant_accountant',
        'hr_manager',
        'office_admin_payments',
        'internal_auditor',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('cb_roles')->whereIn('name', $this->bypassRoles)->update(['is_global' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('cb_roles')->whereIn('name', $this->bypassRoles)->update(['is_global' => false]);
    }
};
