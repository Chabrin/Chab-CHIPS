<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sys_hr_leave_calendar_slots')) {
            Schema::create('sys_hr_leave_calendar_slots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('leave_calendar_plan_id');
                $table->date('start_date');
                $table->date('end_date');
                $table->integer('days_count'); // Working days (excluding weekends & holidays)
                $table->string('notes')->nullable();
                $table->integer('current_status_id')->default(1); // 1 = Draft/Pending, 5 = Approved, 41 = Cancelled
                $table->timestamps();

                $table->foreign('leave_calendar_plan_id')
                      ->references('id')
                      ->on('sys_hr_leave_calendar')
                      ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_hr_leave_calendar_slots');
    }
};