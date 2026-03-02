<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_outcomes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            
            $table->date('decision_date');
            $table->enum('decision', [
                'No Action', 'Verbal Warning', 'Written Warning', 'Final Warning', 
                'Suspension', 'Demotion', 'Dismissal', 'Other'
            ]);
            
            $table->unsignedBigInteger('action_taken_id')->nullable()->comment('FK to action_taken table');
            
            $table->text('findings');
            $table->text('reasoning')->nullable();
            $table->text('sanctions')->nullable();
            
            $table->date('effective_date')->nullable();
            $table->integer('suspension_days')->nullable()->comment('If suspended');
            
            $table->boolean('appeal_allowed')->default(true);
            $table->date('appeal_deadline')->nullable();
            
            $table->unsignedBigInteger('decided_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('case_id')
                  ->references('id')
                  ->on('discipline_cases')
                  ->onDelete('cascade');
            $table->foreign('action_taken_id')->references('id')->on('action_taken');
            // $table->foreign('decided_by')->references('id')->on('users');
            // $table->foreign('approved_by')->references('id')->on('users');
            
            $table->unique('case_id', 'unique_case_outcome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_outcomes');
    }
};
