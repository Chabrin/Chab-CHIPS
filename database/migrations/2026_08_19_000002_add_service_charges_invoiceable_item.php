<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "Service Charges" is one of the fixed SLA billable items (see
     * sys_pm_service_agreements.service_charges_selected) but had no
     * corresponding row in sys_set_invoiceable_items — needed since
     * LeaseAgreementInvoiceableItem.item_id is a foreign key into this
     * table, and Lease creation's Invoiceable Items now derives its rows
     * from the property's SLA billable items.
     */
    public function up(): void
    {
        if (DB::table('sys_set_invoiceable_items')->where('item_name', 'Service Charges')->exists()) {
            return;
        }

        DB::table('sys_set_invoiceable_items')->insert([
            'date_time' => now(),
            'item_name' => 'Service Charges',
            'billing_code' => '0037',
            'commissionable' => 'No',
            'current_status_id' => 1,
            'is_bill' => 'No',
            'fixed_amount' => 'Yes',
            'amount' => 0.00,
        ]);
    }

    public function down(): void
    {
        DB::table('sys_set_invoiceable_items')->where('item_name', 'Service Charges')->delete();
    }
};
