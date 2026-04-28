<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_set_bank_accounts', function (Blueprint $table) {
            $table->string('account_type', 50)->nullable()->after('branch_id');
            $table->text('notes')->nullable()->after('current_status_id');
        });
    }

    public function down(): void
    {
        Schema::table('sys_set_bank_accounts', function (Blueprint $table) {
            $table->dropColumn(['account_type', 'notes']);
        });
    }
};
