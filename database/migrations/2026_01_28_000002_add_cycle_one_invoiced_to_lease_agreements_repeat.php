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
        Schema::table('sys_pm_lease_agreements_27_repeat', function (Blueprint $table) {
            $table->integer('cycle_one_invoiced')->nullable()->default(null)->after('cycle_id')
                ->comment('Tracks if cycle_id=1 item has been invoiced. 1=invoiced, NULL=not invoiced');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_pm_lease_agreements_27_repeat', function (Blueprint $table) {
            $table->dropColumn('cycle_one_invoiced');
        });
    }
};
