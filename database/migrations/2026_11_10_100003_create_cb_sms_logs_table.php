<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per SMS recipient per send, written by SmsService for every SMS the system sends.
     * units = billable SMS segments, the cost metric; gateway_response = raw provider reply.
     */
    public function up(): void
    {
        if (Schema::hasTable('cb_sms_logs')) {
            return;
        }

        Schema::create('cb_sms_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->string('type', 30)->default('general');
            $table->string('recipient_type', 20)->nullable(); // tenant | landlord | staff | user
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone', 20);
            $table->text('message'); // resolved, after placeholder substitution
            $table->smallInteger('units')->default(1); // billable SMS segments
            $table->string('status', 10)->default('sent'); // sent | failed | skipped
            $table->text('error')->nullable();
            $table->text('gateway_response')->nullable();
            $table->string('message_id')->nullable(); // provider reference
            $table->string('entity_type', 40)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('sent_at');
            $table->index('batch_id');
            $table->index(['status', 'sent_at']);
            $table->index(['recipient_type', 'recipient_id', 'sent_at'], 'cb_sms_logs_recipient_idx');
            $table->index(['entity_type', 'entity_id']);
            $table->index(['type', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cb_sms_logs');
    }
};
