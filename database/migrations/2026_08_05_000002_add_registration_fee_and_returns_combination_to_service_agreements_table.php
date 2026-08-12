<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sys_pm_service_agreements', function (Blueprint $table) {
            $table->tinyInteger('charge_registration_fee')->default(1)->after('move_in_inspection_required');

            // How billable items combine into returns:
            //   rent_separate_rest_combined - Rent on its own, everything else combined
            //   each_separate               - every billable item returned on its own
            //   all_combined                - Rent + all billable items in one return
            $table->string('returns_combination', 40)->default('each_separate')->after('charge_registration_fee');

            // Billable items — fixed set (Rent + the 3 standard billing codes),
            // stored directly here rather than a child table.
            $table->tinyInteger('rent_selected')->default(1)->after('returns_combination');

            $table->tinyInteger('electricity_bill_selected')->default(0)->after('rent_selected');
            $table->decimal('electricity_bill_cost', 12, 2)->nullable()->after('electricity_bill_selected');

            $table->tinyInteger('water_bill_selected')->default(0)->after('electricity_bill_cost');
            $table->decimal('water_bill_cost', 12, 2)->nullable()->after('water_bill_selected');

            $table->tinyInteger('service_charges_selected')->default(0)->after('water_bill_cost');
            $table->decimal('service_charges_cost', 12, 2)->nullable()->after('service_charges_selected');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_pm_service_agreements', function (Blueprint $table) {
            $table->dropColumn([
                'charge_registration_fee',
                'returns_combination',
                'rent_selected',
                'electricity_bill_selected',
                'electricity_bill_cost',
                'water_bill_selected',
                'water_bill_cost',
                'service_charges_selected',
                'service_charges_cost',
            ]);
        });
    }
};
