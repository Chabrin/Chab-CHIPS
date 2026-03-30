<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cb_returns_clients', function (Blueprint $table) {
            $table->string('payable_status_reason')->nullable()->after('payable_status');
        });
    }

    public function down(): void
    {
        Schema::table('cb_returns_clients', function (Blueprint $table) {
            $table->dropColumn('payable_status_reason');
        });
    }
};
