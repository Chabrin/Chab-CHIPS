<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cb_landlord_account', function (Blueprint $table) {
            $table->unsignedBigInteger('advance_id')->nullable()->after('property_id');
            $table->unsignedBigInteger('schedule_id')->nullable()->after('advance_id');
        });
    }

    public function down(): void
    {
        Schema::table('cb_landlord_account', function (Blueprint $table) {
            $table->dropColumn(['advance_id', 'schedule_id']);
        });
    }
};
