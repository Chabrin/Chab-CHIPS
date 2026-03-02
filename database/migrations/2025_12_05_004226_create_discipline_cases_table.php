<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number', 50)->unique()->comment('e.g., DSC-2025-001');
            $table->unsignedBigInteger('staff_id')->comment('Subject employee');
            $table->unsignedBigInteger('position_id')->nullable();
            
            $table->tinyInteger('current_step')->default(1)->comment('1-14 step progression');
            $table->unsignedBigInteger('current_status_id')->default(1)->comment('Open/Closed/Suspended');
            
            $table->enum('severity_level', ['Minor', 'Moderate', 'Serious', 'Gross Misconduct'])->nullable();
            $table->boolean('is_anonymous')->default(false);
            
            $table->unsignedBigInteger('created_by');
            $table->dateTime('date_time');
            $table->dateTime('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            
            // $table->foreign('staff_id')->references('id')->on('staff');
            // $table->foreign('position_id')->references('id')->on('positions');
            // $table->foreign('created_by')->references('id')->on('users');
            // $table->foreign('closed_by')->references('id')->on('users');
            
            $table->index('case_number', 'idx_case_number');
            $table->index('staff_id', 'idx_staff');
            $table->index('current_status_id', 'idx_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_cases');
    }
};
