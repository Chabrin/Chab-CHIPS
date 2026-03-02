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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // Notification type (e.g., LeaseExpiring, PaymentDue, etc.)
            $table->morphs('notifiable'); // User who receives the notification (creates index automatically)
            $table->text('data'); // JSON data for the notification
            $table->string('title'); // Notification title
            $table->text('message')->nullable(); // Notification message
            $table->string('action_url')->nullable(); // URL to redirect when clicked
            $table->timestamp('read_at')->nullable(); // When notification was read
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
