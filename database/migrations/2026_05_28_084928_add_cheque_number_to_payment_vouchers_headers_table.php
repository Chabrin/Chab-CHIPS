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
        Schema::table('cb_payment_vouchers_headers_new', function (Blueprint $table) {
            $table->string('cheque_number', 50)->nullable()->after('voucher_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cb_payment_vouchers_headers_new', function (Blueprint $table) {
            $table->dropColumn('cheque_number');
        });
    }
};
