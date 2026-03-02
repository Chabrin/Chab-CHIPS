<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_case_closures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            
            $table->date('closure_date');
            $table->enum('closure_reason', [
                'Resolved', 'Withdrawn', 'No Case to Answer', 'Settled', 'Other'
            ]);
            $table->text('closure_notes')->nullable();
            
            $table->text('lessons_learned')->nullable();
            $table->text('policy_recommendations')->nullable();
            
            $table->unsignedBigInteger('closed_by');
            $table->timestamp('closed_at')->useCurrent();
            
            $table->foreign('case_id')
                  ->references('id')
                  ->on('discipline_cases')
                  ->onDelete('cascade');
            // $table->foreign('closed_by')->references('id')->on('users');
            
            $table->unique('case_id', 'unique_case_closure');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_case_closures');
    }
};
