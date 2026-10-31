<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('trainer')->nullable();
            $table->string('location_or_link')->nullable();
            $table->dateTime('scheduled_at');
            $table->integer('duration_minutes')->default(60);
            $table->boolean('is_mandatory')->default(false);
            $table->timestamps();
        });

        Schema::create('training_session_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained()->cascadeOnDelete();
            
            // Pointing directly to your 'sys_staff' table
            $table->foreignId('staff_id')->constrained('sys_staff')->cascadeOnDelete();
            
            $table->timestamp('attended_at')->nullable();
            $table->enum('status', ['invited', 'attended', 'absent', 'excused'])->default('invited');
            $table->integer('rating')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();
            
            $table->unique(['training_session_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_session_attendees');
        Schema::dropIfExists('training_sessions');
    }
};