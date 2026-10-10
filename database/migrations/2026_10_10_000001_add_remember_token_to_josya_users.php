<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the remember_token column Laravel needs for "Remember me" logins.
     */
    public function up(): void
    {
        if (Schema::hasTable('josya_users') && !Schema::hasColumn('josya_users', 'remember_token')) {
            Schema::table('josya_users', function (Blueprint $table) {
                $table->string('remember_token', 100)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('josya_users', 'remember_token')) {
            Schema::table('josya_users', function (Blueprint $table) {
                $table->dropColumn('remember_token');
            });
        }
    }
};
