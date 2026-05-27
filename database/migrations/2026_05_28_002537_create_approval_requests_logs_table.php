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
        Schema::create('approval_requests_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('approval_request_id');
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->integer('approval_level');
            $table->string('action', 30); // submitted, approved, rejected, changes_requested, returned
            $table->text('comment')->nullable();
            $table->integer('returned_to_level')->nullable(); // For 'returned' action
            $table->timestamp('action_on')->nullable();

            $table->foreign('approval_request_id')->references('id')->on('approval_requests')->onDelete('cascade');
            $table->foreign('approver_id')->references('id')->on('josya_users')->onDelete('set null');

            $table->index(['approval_request_id']);
            $table->index(['approver_id']);
            $table->index(['action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_requests_logs');
    }
};
