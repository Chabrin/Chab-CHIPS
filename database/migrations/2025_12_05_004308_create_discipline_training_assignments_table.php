<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_training_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            
            $table->string('training_type', 255);
            $table->string('training_provider', 255)->nullable();
            $table->text('training_description')->nullable();
            
            $table->date('scheduled_date')->nullable();
            $table->date('completion_deadline')->nullable();
            
            $table->boolean('completed')->default(false);
            $table->date('completion_date')->nullable();
            $table->string('completion_certificate_path', 500)->nullable();
            
            $table->unsignedBigInteger('assigned_by');
            $table->timestamp('assigned_at')->useCurrent();
            
            $table->foreign('case_id')
                  ->references('id')
                  ->on('discipline_cases')
                  ->onDelete('cascade');
            // $table->foreign('assigned_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_training_assignments');
    }
};
