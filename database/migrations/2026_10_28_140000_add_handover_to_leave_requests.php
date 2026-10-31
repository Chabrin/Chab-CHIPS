<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_hr_leave_requests', function (Blueprint $table) {
            // Nullable so existing applications remain valid. Staff IDs use the legacy table's type.
            $table->unsignedBigInteger('handover_staff_id')->nullable()->index();
            $table->text('handover_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sys_hr_leave_requests', function (Blueprint $table) {
            $table->dropIndex(['handover_staff_id']);
            $table->dropColumn(['handover_staff_id', 'handover_notes']);
        });
    }
};
