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
        Schema::table('cb_tenant_exits', function (Blueprint $table) {
            $table->date('vacate_notice_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cb_tenant_exits', function (Blueprint $table) {
            $table->date('vacate_notice_date')->nullable(false)->change();
        });
    }
};
