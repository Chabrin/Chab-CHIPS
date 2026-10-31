<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sys_hr_leave_requests', function (Blueprint $table) {
            $table->json('handover_attachments')->nullable();
            $table->timestamp('handover_acknowledged_at')->nullable();
            $table->unsignedBigInteger('handover_acknowledged_by')->nullable();
            $table->text('handover_acknowledgement_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sys_hr_leave_requests', function (Blueprint $table) {
            $table->dropColumn(['handover_attachments', 'handover_acknowledged_at', 'handover_acknowledged_by', 'handover_acknowledgement_note']);
        });
    }
};
