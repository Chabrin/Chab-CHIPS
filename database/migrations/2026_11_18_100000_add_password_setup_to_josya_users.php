<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "set your own password" link a new system user is emailed. Only a hash of the link's secret is kept, so nobody who can
 * read the database (or the System Users page) can open someone else's link.
 *
 * - password_setup_token: SHA-256 of the secret in the emailed link. A newer link replaces it, which cancels the older one.
 * - password_setup_expires_at: the link stops working after this (48 hours after it was made).
 * - password_setup_sent_at: when the email left the server; empty when it could not be sent.
 * - password_setup_used_at: when the person set a password. The hash stays so a used link can say "already used".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('josya_users', function (Blueprint $table) {
            if (!Schema::hasColumn('josya_users', 'password_setup_token')) {
                $table->string('password_setup_token', 64)->nullable()->index();
            }
            if (!Schema::hasColumn('josya_users', 'password_setup_expires_at')) {
                $table->timestamp('password_setup_expires_at')->nullable();
            }
            if (!Schema::hasColumn('josya_users', 'password_setup_sent_at')) {
                $table->timestamp('password_setup_sent_at')->nullable();
            }
            if (!Schema::hasColumn('josya_users', 'password_setup_used_at')) {
                $table->timestamp('password_setup_used_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('josya_users', function (Blueprint $table) {
            foreach (['password_setup_token', 'password_setup_expires_at', 'password_setup_sent_at', 'password_setup_used_at'] as $column) {
                if (Schema::hasColumn('josya_users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
