<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cb_discipline_deadline_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('discipline_cases')->onDelete('cascade');
            $table->date('original_deadline');
            $table->date('new_deadline')->nullable(); // Nullable for employee requests
            $table->text('extension_reason');
            $table->integer('requested_days')->nullable(); // Nullable for HR extensions
            $table->unsignedBigInteger('extended_by')->nullable(); // Nullable for employee requests
            $table->unsignedBigInteger('requested_by')->nullable(); // Employee who requested
            $table->timestamp('extended_at')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->boolean('deadline_lapsed_before_extension')->default(false);
            $table->enum('request_status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->unsignedBigInteger('reviewed_by')->nullable(); // HR who reviewed
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cb_discipline_deadline_extensions');
    }
};
