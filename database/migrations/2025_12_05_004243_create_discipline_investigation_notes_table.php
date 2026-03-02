<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_investigation_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            
            $table->date('note_date');
            $table->text('note_text');
            $table->enum('note_type', [
                'Interview', 'Site Visit', 'Analysis', 'Correspondence', 'General'
            ])->default('General');
            
            $table->boolean('is_confidential')->default(false);
            
            $table->unsignedBigInteger('created_by');
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('case_id')
                  ->references('id')
                  ->on('discipline_cases')
                  ->onDelete('cascade');
            // $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_investigation_notes');
    }
};
