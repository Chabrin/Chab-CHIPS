<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give the tenant-facing invoiceable items their default billing cycle.
     *
     * The cycles exist (Monthly, Quarterly, Semi-Annually, Annually, One-Time) but RENT and the
     * electricity and water items were never given one, so the SLA's Tenant Invoiceable Items picker
     * showed "-" for them and rent invoices were saved with no cycle. Deposits and Service Charge
     * already have theirs. Only items whose cycle is still empty are filled, so a cycle an admin has
     * set in Invoiceable Items settings is never overwritten. Matched on billing code, not id.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sys_set_invoiceable_items') || !Schema::hasTable('sys_set_invoiceable_items_cycles')) {
            return;
        }

        if (!Schema::hasColumn('sys_set_invoiceable_items', 'default_cycle_id')) {
            return;
        }

        $defaults = [
            'Monthly' => ['0024', '0001', '0002', '0003', '0004'], // RENT, Electricity Bill (+ Charge), Water Bill (+ Charge)
        ];

        foreach ($defaults as $cycleName => $billingCodes) {
            $cycleId = DB::table('sys_set_invoiceable_items_cycles')->where('cycle_name', $cycleName)->value('id');

            if (!$cycleId) {
                continue;
            }

            DB::table('sys_set_invoiceable_items')
                ->whereIn('billing_code', $billingCodes)
                ->whereNull('default_cycle_id')
                ->update(['default_cycle_id' => $cycleId]);
        }
    }

    /**
     * Not reversed: once filled, these rows cannot be told apart from cycles an admin set by hand.
     */
    public function down(): void
    {
    }
};
