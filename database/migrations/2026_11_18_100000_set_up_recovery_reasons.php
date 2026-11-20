<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The five recovery reasons (Paid Owner, Sat Deposit, Overpaid, Absconded, Paid Vacant) are all confirmed on the Recovery
 * page. Four of them already exist in sys_set_transactions_types; the fifth is the 'Overpayment' row (item code 2079,
 * never used on a recovery), which becomes 'Overpaid' with commission kept, as the old system and the brief have it.
 * The confirmer workflow's description now says it covers every reason. Safe to run more than once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sys_set_transactions_types')) {
            DB::table('sys_set_transactions_types')
                ->where('item_code', '2079')
                ->where('type_name', 'Overpayment')
                ->update(['type_name' => 'Overpaid', 'is_commissionable' => 'Yes']);
        }

        if (Schema::hasTable('approval_workflows')) {
            DB::table('approval_workflows')
                ->where('workflow_name', 'paid_owner_confirmation')
                ->update(['description' => 'Who confirms a recovery (Paid Owner, Sat Deposit, Overpaid, Absconded, Paid Vacant). Add one approver at level 1, a role or a named user.']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sys_set_transactions_types')) {
            DB::table('sys_set_transactions_types')
                ->where('item_code', '2079')
                ->where('type_name', 'Overpaid')
                ->update(['type_name' => 'Overpayment', 'is_commissionable' => 'No']);
        }

        if (Schema::hasTable('approval_workflows')) {
            DB::table('approval_workflows')
                ->where('workflow_name', 'paid_owner_confirmation')
                ->update(['description' => 'Who confirms a paid-owner recovery (a tenant paid the landlord directly). Add one approver at level 1, a role or a named user.']);
        }
    }
};
