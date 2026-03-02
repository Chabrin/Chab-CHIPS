<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_hearings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            
            $table->dateTime('hearing_date');
            $table->string('hearing_location', 255)->nullable();
            $table->enum('hearing_type', [
                'Preliminary', 'Full Hearing', 'Appeal'
            ])->default('Full Hearing');
            
            $table->unsignedBigInteger('chairperson_id')->nullable();
            $table->json('panel_members')->nullable()->comment('Array of user IDs');
            
            $table->boolean('employee_attended')->nullable();
            $table->string('employee_representative', 255)->nullable();
            
            $table->unsignedBigInteger('scheduled_by');
            $table->timestamp('scheduled_at')->useCurrent();
            
            $table->dateTime('rescheduled_from')->nullable();
            $table->text('reschedule_reason')->nullable();
            
            $table->foreign('case_id')
                  ->references('id')
                  ->on('discipline_cases')
                  ->onDelete('cascade');
            // $table->foreign('chairperson_id')->references('id')->on('users');
            // $table->foreign('scheduled_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_hearings');
    }
};
