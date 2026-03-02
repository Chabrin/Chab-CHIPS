<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_activity_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            
            $table->tinyInteger('step_number')->comment('1-14');
            $table->string('step_name', 100);
            
            // $table->enum('activity_type', [
            //     'Step Completed', 'Step Skipped', 'Document Added', 'Comment Added', 'Assignment Changed'
            // ]);
            $table->string('activity_type', 250);
            $table->text('activity_description')->nullable();
            
            $table->unsignedBigInteger('performed_by');
            $table->timestamp('performed_at')->useCurrent();
            
            $table->json('metadata')->nullable()->comment('Additional context data');
            
            $table->foreign('case_id')
                  ->references('id')
                  ->on('discipline_cases')
                  ->onDelete('cascade');
            // $table->foreign('performed_by')->references('id')->on('users');
            
            $table->index(['case_id', 'performed_at'], 'idx_case_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_activity_log');
    }
};
