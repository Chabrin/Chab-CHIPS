<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Temporarily disable strict mode
        DB::statement('SET SQL_MODE = ""');

        Schema::table('josya_users', function (Blueprint $table) {
            $table->string('reset_token', 255)->nullable()->after('otpKey');
            $table->timestamp('reset_token_expires_at')->nullable()->after('reset_token');
        });

        // Re-enable strict mode
        DB::statement('SET SQL_MODE = "STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION"');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('josya_users', function (Blueprint $table) {
            $table->dropColumn(['reset_token', 'reset_token_expires_at']);
        });
    }
};
