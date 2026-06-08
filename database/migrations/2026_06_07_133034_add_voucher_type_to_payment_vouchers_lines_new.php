<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cb_payment_vouchers_lines_new', function (Blueprint $table) {
            $table->string('voucher_type', 50)->nullable()->after('cheque_number');
        });

        // Add check constraint for enum values
        DB::statement("ALTER TABLE cb_payment_vouchers_lines_new ADD CONSTRAINT voucher_type_check CHECK (voucher_type IN ('client_returns', 'general_voucher', 'internal_voucher'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop constraint first
        DB::statement("ALTER TABLE cb_payment_vouchers_lines_new DROP CONSTRAINT IF EXISTS voucher_type_check");
        
        Schema::table('cb_payment_vouchers_lines_new', function (Blueprint $table) {
            $table->dropColumn('voucher_type');
        });
    }
};
