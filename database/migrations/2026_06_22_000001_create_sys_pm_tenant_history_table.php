<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cb_tenant_history', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('landlord_id')->nullable();   // property owner at time of exit
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('unit_id');
            $table->unsignedBigInteger('exit_id')->nullable();        // reference to the exit workflow record

            $table->date('lease_start_date')->nullable();
            $table->date('exit_date');
            $table->unsignedInteger('duration_days')->nullable();     // exit_date - lease_start_date

            $table->string('exit_reason', 50)->nullable();

            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('sys_pm_clients')->onDelete('restrict');
            $table->foreign('property_id')->references('id')->on('sys_pm_properties')->onDelete('restrict');
            $table->foreign('unit_id')->references('id')->on('sys_pm_units')->onDelete('restrict');
            $table->foreign('exit_id')->references('id')->on('cb_tenant_exits')->onDelete('set null');
            $table->foreign('recorded_by')->references('id')->on('josya_users')->onDelete('set null');

            $table->index('tenant_id');
            $table->index('landlord_id');
            $table->index('property_id');
            $table->index('exit_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cb_tenant_history');
    }
};
