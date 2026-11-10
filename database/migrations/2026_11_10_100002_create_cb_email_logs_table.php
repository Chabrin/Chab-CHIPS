<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per email recipient per send, written by EmailService for every email the system
     * sends — bulk blasts (batch_id set) and system mails (payslips, activations: batch_id null).
     */
    public function up(): void
    {
        if (Schema::hasTable('cb_email_logs')) {
            return;
        }

        Schema::create('cb_email_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->string('type', 30)->default('general'); // general | payslip | invoice | tenant_exit | ...
            $table->string('recipient_type', 20)->nullable(); // tenant | landlord | staff | user
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email');
            $table->string('subject');
            $table->text('body'); // resolved, after placeholder substitution
            $table->boolean('has_attachment')->default(false);
            $table->string('status', 10)->default('sent'); // sent | failed | skipped
            $table->text('error')->nullable();
            $table->string('entity_type', 40)->nullable(); // what caused it: invoice, return, exit ...
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('sent_at');
            $table->index('batch_id');
            $table->index(['status', 'sent_at']);
            $table->index(['recipient_type', 'recipient_id', 'sent_at'], 'cb_email_logs_recipient_idx');
            $table->index(['entity_type', 'entity_id']);
            $table->index(['type', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cb_email_logs');
    }
};
