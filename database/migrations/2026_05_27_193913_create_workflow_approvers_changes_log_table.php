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
        Schema::create('workflow_approvers_changes_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_id');
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->string('action', 50);
            $table->integer('old_level')->nullable();
            $table->integer('new_level')->nullable();
            $table->integer('old_is_final')->nullable();
            $table->integer('new_is_final')->nullable();
            $table->text('details')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('changed_on')->nullable();

            $table->foreign('workflow_id')->references('id')->on('approval_workflows')->onDelete('cascade');
            $table->foreign('approver_id')->references('id')->on('josya_users')->onDelete('set null');
            $table->foreign('changed_by')->references('id')->on('josya_users')->onDelete('set null');

            $table->index('workflow_id');
            $table->index('changed_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_approvers_changes_log');
    }
};
