<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cb_staff_messages', function (Blueprint $table) {
            $table->uuid('client_token')->nullable();
            $table->unique(['sender_id', 'client_token'], 'staff_messages_sender_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cb_staff_messages', function (Blueprint $table) {
            $table->dropUnique('staff_messages_sender_token_unique');
            $table->dropColumn('client_token');
        });
    }
};
