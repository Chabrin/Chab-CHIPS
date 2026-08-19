<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_pm_lease_agreements', function (Blueprint $table) {
            $table->timestamp('tenant_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sys_pm_lease_agreements', function (Blueprint $table) {
            $table->dropColumn('tenant_verified_at');
        });
    }
};
