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
        Schema::table('sys_pm_clients_advances_requisitions', function (Blueprint $table) {
            $table->dropColumn(['expense_category', 'charge_billed_to', 'payment_method', 'payment_instructions']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_pm_clients_advances_requisitions', function (Blueprint $table) {
            $table->string('expense_category')->nullable()->after('advance_type_id');
            $table->string('charge_billed_to')->nullable()->after('charge_amount');
            $table->string('payment_method')->nullable()->after('charge_billed_to');
            $table->text('payment_instructions')->nullable()->after('payment_method');
        });
    }
};
