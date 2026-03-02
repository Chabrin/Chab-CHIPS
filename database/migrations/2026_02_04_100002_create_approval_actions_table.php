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
        Schema::create('cb_approval_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_approver_id')->comment('Link to cb_item_approvers');
            $table->unsignedBigInteger('approval_item_id')->comment('Link to cb_approval_items for quick lookup');
            $table->integer('actor_id')->comment('User who performed the action');
            $table->tinyInteger('from_level')->nullable()->comment('Previous approval level');
            $table->tinyInteger('to_level')->nullable()->comment('Next approval level (for returns/forwards)');
            $table->enum('action', ['Initiated', 'Approved', 'Rejected', 'Returned', 'Forwarded', 'Commented', 'Escalated'])->comment('Action taken');
            $table->integer('returned_to_approver_id')->nullable()->comment('If returned, user ID returned to');
            $table->tinyInteger('returned_to_level')->nullable()->comment('Level returned to');
            $table->text('comments')->nullable()->comment('Comments/notes from actor');
            $table->datetime('action_timestamp')->comment('When action was performed');
            $table->string('ip_address', 45)->nullable()->comment('IP address of actor');
            $table->integer('sequence_number')->default(1)->comment('Action sequence for tracking back-and-forth');
            $table->tinyInteger('is_current')->default(1)->comment('1=Latest action, 0=Historical');
            $table->json('metadata')->nullable()->comment('Additional data: attachments, references, etc.');
            $table->timestamps();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cb_approval_actions');
    }
};
