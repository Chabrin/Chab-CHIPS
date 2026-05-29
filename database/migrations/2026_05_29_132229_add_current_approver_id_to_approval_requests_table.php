<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('current_approver_id')->nullable()->after('current_level');
            $table->foreign('current_approver_id')->references('id')->on('josya_users')->onDelete('set null');
            $table->index('current_approver_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->dropForeign(['current_approver_id']);
            $table->dropColumn('current_approver_id');
        });
    }
};
