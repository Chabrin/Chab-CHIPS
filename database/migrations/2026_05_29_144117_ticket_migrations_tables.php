<?php

// ═══════════════════════════════════════════════════════════════════
//  run:  php artisan make:migration create_tickets_tables
//  then replace the up() / down() with what's below.
// ═══════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── tickets ──────────────────────────────────────────────────
        Schema::create('cb_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 20)->unique();  // e.g. TKT-2026-00001
            $table->foreignId('user_id')->constrained('josya_users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('josya_users')->nullOnDelete();
            $table->string('subject', 150);
            $table->string('category', 60)->nullable();
            $table->text('description');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'priority']);
            $table->index('assigned_to');
        });

        // ── ticket_replies ────────────────────────────────────────────
        Schema::create('cb_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('cb_tickets')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('josya_users')->nullOnDelete();
            $table->enum('sender_role', ['user', 'admin', 'system'])->default('user');
            $table->text('body');
            $table->boolean('is_internal_note')->default(false);
            $table->boolean('read_by_admin')->default(false);
            $table->boolean('read_by_user')->default(false);
            $table->timestamps();

            $table->index(['ticket_id', 'sender_role']);
            $table->index(['ticket_id', 'read_by_admin']);
            $table->index(['ticket_id', 'read_by_user']);
        });

        // ── ticket_attachments ────────────────────────────────────────
        Schema::create('cb_ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('cb_tickets')->cascadeOnDelete();
            $table->foreignId('reply_id')->nullable()->constrained('cb_ticket_replies')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');           // stored in the 'private' disk
            $table->string('mime_type', 80)->nullable();
            $table->unsignedBigInteger('size')->nullable();  // bytes
            $table->timestamps();
        });

        // ── ticket_activities ─────────────────────────────────────────
        Schema::create('cb_ticket_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('cb_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('josya_users')->nullOnDelete();
            $table->string('description');
            $table->timestamps();

            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cb_ticket_activities');
        Schema::dropIfExists('cb_ticket_attachments');
        Schema::dropIfExists('cb_ticket_replies');
        Schema::dropIfExists('cb_tickets');
    }
};