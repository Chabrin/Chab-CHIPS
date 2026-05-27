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
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_type', 50); // e.g., 'voucher', 'leave', 'expense'
            $table->unsignedBigInteger('request_id'); // ID of the item being approved
            $table->unsignedBigInteger('workflow_id');
            $table->integer('current_level')->default(1);
            $table->string('status', 30)->default('pending'); // pending, in_progress, approved, rejected, changes_requested
            $table->unsignedBigInteger('initiated_by')->nullable();
            $table->timestamp('initiated_on')->nullable();
            $table->timestamp('completed_on')->nullable();

            $table->foreign('workflow_id')->references('id')->on('approval_workflows')->onDelete('cascade');
            $table->foreign('initiated_by')->references('id')->on('josya_users')->onDelete('set null');

            $table->index(['request_type', 'request_id']);
            $table->index(['status']);
            $table->index(['workflow_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
