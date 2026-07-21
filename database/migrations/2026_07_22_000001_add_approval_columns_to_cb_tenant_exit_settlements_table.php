<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cb_tenant_exit_settlements', function (Blueprint $table) {
            $table->unsignedTinyInteger('current_status_id')->default(1)->after('settled_at'); // 1=Pending, 5=Approved, 41=Rejected
            $table->foreignId('approved_by')->nullable()->after('current_status_id')->constrained('josya_users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('cb_tenant_exit_settlements', function (Blueprint $table) {
            $table->dropColumn(['current_status_id', 'approved_by', 'approved_at', 'rejection_reason']);
        });
    }
};
