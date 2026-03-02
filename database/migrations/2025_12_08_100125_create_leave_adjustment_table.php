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
        Schema::create('sys_hr_leave_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('leave_request_id');
            $table->decimal('days_refunded', 5, 2);
            $table->text('reason');
            $table->unsignedBigInteger('requested_by'); // Person requesting the adjustment
            $table->dateTime('requested_at');
            $table->unsignedBigInteger('current_status_id')->default(1); // 1=Pending, 5=Approved, 41=Rejected
            $table->unsignedBigInteger('approved_by')->nullable(); // First approver
            $table->dateTime('approved_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable(); // Second approver
            $table->dateTime('confirmed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('day_ids')->nullable();
            $table->timestamps();
            
            // $table->foreign('leave_request_id')->references('id')->on('sys_hr_leave_requests')->onDelete('cascade');
            // $table->foreign('requested_by')->references('id')->on('sys_users')->onDelete('restrict');
            // $table->foreign('approved_by')->references('id')->on('sys_users')->onDelete('restrict');
            // $table->foreign('confirmed_by')->references('id')->on('sys_users')->onDelete('restrict');
            // $table->foreign('current_status_id')->references('id')->on('sys_workflow_statuses')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_hr_leave_adjustments');
    }
};
