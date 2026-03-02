<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_hearing_minutes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hearing_id');
            
            $table->json('attendees')->nullable()->comment('Names and roles');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            
            $table->text('proceedings_summary');
            $table->text('employee_statement')->nullable();
            $table->text('panel_questions')->nullable();
            
            $table->unsignedBigInteger('minute_taker_id')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('hearing_id')
                  ->references('id')
                  ->on('discipline_hearings')
                  ->onDelete('cascade');
            // $table->foreign('minute_taker_id')->references('id')->on('users');
            // $table->foreign('approved_by')->references('id')->on('users');
            
            $table->unique('hearing_id', 'unique_hearing_minutes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_hearing_minutes');
    }
};