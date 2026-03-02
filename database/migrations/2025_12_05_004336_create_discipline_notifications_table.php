<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            
            $table->string('notification_type', 100)->comment('incident_reported, case_registered, investigator_assigned, etc');
            $table->unsignedBigInteger('recipient_user_id')->nullable();
            $table->string('recipient_email', 255)->nullable();
            
            $table->string('subject', 255);
            $table->text('message');
            
            $table->enum('sent_via', ['Email', 'SMS', 'System', 'All'])->default('Email');
            $table->dateTime('sent_at')->nullable();
            
            $table->dateTime('read_at')->nullable();
            
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('case_id')
                  ->references('id')
                  ->on('discipline_cases')
                  ->onDelete('cascade');
            // $table->foreign('recipient_user_id')->references('id')->on('users');
            
            $table->index(['recipient_user_id', 'read_at'], 'idx_recipient');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_notifications');
    }
};
