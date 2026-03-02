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
        Schema::create('cb_item_approvers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('approval_item_id')->comment('Link to cb_approval_items');
            $table->integer('approver_id')->comment('User ID who should approve at this level');
            $table->tinyInteger('approver_level')->comment('Level/order: 1, 2, 3, 4, etc.');
            $table->tinyInteger('is_optional')->default(0)->comment('1=Optional, 0=Required');
            $table->enum('approval_status', ['Pending', 'Approved', 'Rejected', 'Skipped'])->default('Pending')->comment('Status for this specific approver');
            $table->datetime('assigned_at')->comment('When assigned to this approver');
            $table->datetime('actioned_at')->nullable()->comment('When approver took action');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cb_item_approvers');
    }
};
