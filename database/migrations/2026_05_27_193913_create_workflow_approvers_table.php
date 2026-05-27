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
        Schema::create('workflow_approvers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_id');
            $table->unsignedBigInteger('approver_id');
            $table->integer('approval_level')->default(1);
            $table->integer('is_final')->default(0);
            $table->integer('is_active')->default(1);
            $table->unsignedBigInteger('added_by')->nullable();
            $table->timestamp('added_on')->nullable();

            $table->foreign('workflow_id')->references('id')->on('approval_workflows')->onDelete('cascade');
            $table->foreign('approver_id')->references('id')->on('josya_users')->onDelete('cascade');
            $table->foreign('added_by')->references('id')->on('josya_users')->onDelete('set null');

            $table->unique(['workflow_id', 'approver_id'], 'unique_workflow_approver');
            $table->index('workflow_id');
            $table->index('approver_id');
            $table->index('approval_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_approvers');
    }
};
