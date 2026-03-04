<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Notification settings (one global row) ─────────────────
        Schema::create('cb_lease_escalation_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->json('notify_roles')->nullable()->comment('Roles that receive the pre-effective-date notification');
            $table->json('cancel_roles')->nullable()->comment('Roles that are allowed to cancel an approved-pending escalation');
            $table->timestamps();
        });

        // ── 2. Extra columns on the escalations table ─────────────────
        Schema::table('cb_lease_escalations', function (Blueprint $table) {
            $table->dateTime('notification_sent_at')->nullable()
                ->after('effective_date')
                ->comment('Timestamp when the pre-effective-date notification was dispatched');

            $table->dateTime('applied_at')->nullable()
                ->after('notification_sent_at')
                ->comment('Timestamp when the escalation was actually applied to the lease');
        });
    }

    public function down(): void
    {
        Schema::table('cb_lease_escalations', function (Blueprint $table) {
            $table->dropColumn(['notification_sent_at', 'applied_at']);
        });

        Schema::dropIfExists('cb_lease_escalation_notification_settings');
    }
};
