<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_investigators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            
            $table->unsignedBigInteger('investigator_id')->comment('Staff assigned');
            $table->unsignedBigInteger('assigned_by');
            $table->dateTime('assigned_at');
            
            $table->date('investigation_start_date')->nullable();
            $table->date('investigation_deadline')->nullable();
            
            $table->enum('status', ['Assigned', 'In Progress', 'Completed', 'Reassigned'])
                  ->default('Assigned');
            
            $table->unsignedBigInteger('reassigned_to')->nullable();
            $table->text('reassignment_reason')->nullable();
            
            $table->foreign('case_id')
                  ->references('id')
                  ->on('discipline_cases')
                  ->onDelete('cascade');
            // $table->foreign('investigator_id')->references('id')->on('staff');
            // $table->foreign('assigned_by')->references('id')->on('users');
            // $table->foreign('reassigned_to')->references('id')->on('staff');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_investigators');
    }
};
