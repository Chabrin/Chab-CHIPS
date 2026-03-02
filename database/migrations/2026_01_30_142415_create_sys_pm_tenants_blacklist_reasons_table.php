<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sys_pm_tenants_blacklist_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('reason_name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
        });

        // Insert default reasons
        DB::table('sys_pm_tenants_blacklist_reasons')->insert([
            ['reason_name' => 'Absconded Rent', 'description' => 'Tenant absconded without paying rent', 'is_active' => true],
            ['reason_name' => 'Property Damage', 'description' => 'Caused significant damage to property', 'is_active' => true],
            ['reason_name' => 'Breach of Lease', 'description' => 'Violated terms of lease agreement', 'is_active' => true],
            ['reason_name' => 'Illegal Activities', 'description' => 'Engaged in illegal activities on premises', 'is_active' => true],
            ['reason_name' => 'Unpaid Bills', 'description' => 'Left with unpaid utility bills', 'is_active' => true],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_pm_tenants_blacklist_reasons');
    }
};
