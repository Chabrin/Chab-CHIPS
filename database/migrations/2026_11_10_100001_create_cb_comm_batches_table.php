<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per send action (a single message or a bulk blast), on either channel.
     * The per-recipient outcomes live in cb_email_logs / cb_sms_logs, keyed by batch_id.
     */
    public function up(): void
    {
        if (Schema::hasTable('cb_comm_batches')) {
            return;
        }

        Schema::create('cb_comm_batches', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 10); // email | sms
            $table->string('subject')->nullable(); // null for sms
            $table->text('body'); // as typed, with {placeholders}
            $table->string('audience_type', 30); // single_client | clients | tenants_all | landlords_all | property | zone | staff_all
            $table->json('audience_params')->nullable(); // property_id, zone_id, client_ids ...
            $table->string('audience_label')->nullable(); // human description shown on lists
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->integer('recipient_count')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('skipped_count')->default(0); // in the audience but no email/mobile on record
            $table->string('status', 20)->default('queued'); // queued | sending | done | failed
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['channel', 'created_at']);
            $table->index('created_by');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cb_comm_batches');
    }
};
