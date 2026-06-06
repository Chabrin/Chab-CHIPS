<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cb_tenant_exits', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('unit_id');

            $table->date('vacate_notice_date');
            $table->date('intended_exit_date');
            $table->date('actual_exit_date')->nullable();

            $table->string('exit_reason', 50);
            $table->text('exit_notes')->nullable();
            $table->text('final_notes')->nullable();

            // 1 = In Progress, 2 = Completed
            $table->unsignedTinyInteger('current_status_id')->default(1);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('finalised_by')->nullable();
            $table->timestamp('finalised_at')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('sys_pm_clients')->onDelete('restrict');
            $table->foreign('property_id')->references('id')->on('sys_pm_properties')->onDelete('restrict');
            $table->foreign('unit_id')->references('id')->on('sys_pm_units')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('josya_users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('josya_users')->onDelete('set null');
            $table->foreign('finalised_by')->references('id')->on('josya_users')->onDelete('set null');

            $table->index('tenant_id');
            $table->index('property_id');
            $table->index('unit_id');
            $table->index('current_status_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cb_tenant_exits');
    }
};
