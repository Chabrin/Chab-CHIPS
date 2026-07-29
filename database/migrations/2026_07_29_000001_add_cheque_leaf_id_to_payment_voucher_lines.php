<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cb_payment_vouchers_lines_new', function (Blueprint $table) {
            $table->unsignedBigInteger('cheque_leaf_id')->nullable()->after('cheque_number');
        });
    }

    public function down(): void
    {
        Schema::table('cb_payment_vouchers_lines_new', function (Blueprint $table) {
            $table->dropColumn('cheque_leaf_id');
        });
    }
};
