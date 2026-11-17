<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cb_staff_conversations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('first_user_id')
                ->constrained('josya_users')
                ->restrictOnDelete();

            $table->foreignId('second_user_id')
                ->constrained('josya_users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(
                ['first_user_id', 'second_user_id'],
                'staff_conversations_pair_unique'
            );

            $table->index('second_user_id');
        });

        Schema::create('cb_staff_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained('cb_staff_conversations')
                ->cascadeOnDelete();

            $table->foreignId('sender_id')
                ->constrained('josya_users')
                ->restrictOnDelete();

            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->index(['conversation_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cb_staff_messages');
        Schema::dropIfExists('cb_staff_conversations');
    }
};