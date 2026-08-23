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
        Schema::table('sys_set_invoiceable_items', function (Blueprint $table) {
            $table->enum('applies_to', ['TENANT', 'CLIENT', 'BOTH'])->default('BOTH')->after('item_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_set_invoiceable_items', function (Blueprint $table) {
            $table->dropColumn('applies_to');
        });
    }
};
