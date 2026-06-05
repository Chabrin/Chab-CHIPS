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
        Schema::table('cb_returns_summary', function (Blueprint $table) {
            $table->integer('voucher_generated')->default(0)->after('paid_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cb_returns_summary', function (Blueprint $table) {
            $table->dropColumn('voucher_generated');
        });
    }
};
