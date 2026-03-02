<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_case_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            
            $table->unsignedBigInteger('registered_by')->comment('HR person');
            $table->dateTime('registration_date');
            
            $table->text('hr_notes')->nullable();
            $table->enum('priority', ['Low', 'Medium', 'High', 'Critical'])->default('Medium');
            $table->date('estimated_closure_date')->nullable();
            
            $table->boolean('hr_notified')->default(true);
            $table->dateTime('notification_sent_at')->nullable();
            
            $table->foreign('case_id')
                  ->references('id')
                  ->on('discipline_cases')
                  ->onDelete('cascade');
            // $table->foreign('registered_by')->references('id')->on('users');
            
            $table->unique('case_id', 'unique_case_registration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_case_registrations');
    }
};