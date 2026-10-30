<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_hr_leave_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('sys_hr_leave_requests', 'recalled_at')) $table->timestamp('recalled_at')->nullable();
            if (!Schema::hasColumn('sys_hr_leave_requests', 'recall_effective_date')) $table->date('recall_effective_date')->nullable();
            if (!Schema::hasColumn('sys_hr_leave_requests', 'recall_reason')) $table->text('recall_reason')->nullable();
            if (!Schema::hasColumn('sys_hr_leave_requests', 'recalled_by')) $table->unsignedBigInteger('recalled_by')->nullable();
            if (!Schema::hasColumn('sys_hr_leave_requests', 'recalled_days')) $table->decimal('recalled_days', 8, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sys_hr_leave_requests', function (Blueprint $table) {
            foreach (['recalled_at', 'recall_effective_date', 'recall_reason', 'recalled_by', 'recalled_days'] as $column) {
                if (Schema::hasColumn('sys_hr_leave_requests', $column)) $table->dropColumn($column);
            }
        });
    }
};
