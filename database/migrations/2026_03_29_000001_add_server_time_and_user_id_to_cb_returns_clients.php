<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cb_returns_clients', function (Blueprint $table) {
            $table->timestamp('server_time')->nullable()->after('is_commissionable');
            $table->unsignedBigInteger('user_id')->nullable()->after('server_time');
        });
    }

    public function down(): void
    {
        Schema::table('cb_returns_clients', function (Blueprint $table) {
            $table->dropColumn(['server_time', 'user_id']);
        });
    }
};
